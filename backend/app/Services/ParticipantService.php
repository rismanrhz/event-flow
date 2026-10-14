<?php

namespace App\Services;

use App\Enums\ParticipantStatus;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ParticipantService
{
    public function __construct(private readonly ActivityLogService $activityLog) {}

    public function index(Event $event, Request $request): LengthAwarePaginator
    {
        $query = EventParticipant::query()
            ->where('event_id', $event->id)
            ->with('user:id,name,email,role_id');

        return QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::callback('search', function ($query, string $term) {
                    $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%"));
                }),
            ])
            ->allowedSorts(['checked_in_at', 'created_at'])
            ->defaultSort('created_at')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();
    }

    /**
     * Capacity/summary counts for the given event.
     *
     * @return array<string, mixed>
     */
    public function stats(Event $event): array
    {
        $counts = $event->participants()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $total = array_sum($counts);
        $registered = (int) ($counts[ParticipantStatus::Registered->value] ?? 0);
        $waitlisted = (int) ($counts[ParticipantStatus::Waitlisted->value] ?? 0);

        return [
            'total' => $total,
            'registered' => $registered,
            'checked_in' => (int) ($counts[ParticipantStatus::CheckedIn->value] ?? 0),
            'waitlisted' => $waitlisted,
            'cancelled' => (int) ($counts[ParticipantStatus::Cancelled->value] ?? 0),
            'capacity' => $event->capacity,
            'remaining' => $event->capacity === null
                ? null
                : max(0, $event->capacity - ($registered + $waitlisted)),
        ];
    }

    /**
     * Active users eligible to be added as a participant of the event.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function candidates(Event $event): \Illuminate\Database\Eloquent\Collection
    {
        $already = $event->participants()
            ->whereNull('deleted_at')
            ->pluck('user_id');

        return User::query()
            ->with('role')
            ->where('is_active', true)
            ->when($already->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $already))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role_id']);
    }

    /**
     * Self registration for the authenticated user.
     */
    public function register(Event $event, User $user, User $actor): EventParticipant
    {
        $this->ensureEventAcceptingRegistrations($event);
        $this->ensureNotAlreadyRegistered($event, $user);

        $participant = $this->createRegistration($event, $user, $actor);

        $this->activityLog->record(
            $participant->isWaitlisted() ? 'event.participant_waitlisted' : 'event.participant_registered',
            'participants',
            "{$user->name} {$this->statusPhraseAction($participant, $event)}.",
            $actor->id,
            $event->id,
            ['user_id' => $user->id, 'status' => $participant->status->value],
        );

        return $participant;
    }

    /**
     * Manager-added registration.
     */
    public function add(Event $event, User $user, User $actor): EventParticipant
    {
        $this->ensureNotAlreadyRegistered($event, $user);

        $participant = $this->createRegistration($event, $user, $actor);

        $this->activityLog->record(
            $participant->isWaitlisted() ? 'event.participant_waitlisted' : 'event.participant_added',
            'participants',
            "{$user->name} was added as a participant of \"{$event->title}\".",
            $actor->id,
            $event->id,
            ['user_id' => $user->id, 'status' => $participant->status->value],
        );

        return $participant;
    }

    /**
     * Cancel a registration and promote the oldest waitlisted participant.
     */
    public function cancel(Event $event, EventParticipant $participant, ?string $reason, User $actor): EventParticipant
    {
        $this->ensureBelongsToEvent($event, $participant);

        if ($participant->isCancelled()) {
            throw ValidationException::withMessages([
                'participant' => 'This registration is already cancelled.',
            ]);
        }

        if ($participant->isCheckedIn()) {
            throw ValidationException::withMessages([
                'participant' => 'A checked-in registration cannot be cancelled.',
            ]);
        }

        $participant->cancel($reason);

        $this->activityLog->record(
            'event.participant_cancelled',
            'participants',
            "Registration of {$participant->user->name} for \"{$event->title}\" was cancelled.",
            $actor->id,
            $event->id,
            ['user_id' => $participant->user_id, 'reason' => $reason],
        );

        $this->promoteWaitlisted($event, $actor);

        return $participant->load('user:id,name,email,role_id');
    }

    /**
     * Check in a participant by event-scoped registration code or participant id.
     */
    public function checkIn(Event $event, ?string $code, ?int $participantId, User $actor): EventParticipant
    {
        $participant = $participantId !== null
            ? $event->participants()->find($participantId)
            : $event->participants()->where('registration_code', $code)->first();

        if ($participant === null) {
            throw ValidationException::withMessages([
                'code' => 'No registration found for that code.',
            ]);
        }

        if ($participant->isWaitlisted()) {
            throw ValidationException::withMessages([
                'code' => 'Waitlisted participants cannot check in yet.',
            ]);
        }

        if ($participant->isCancelled()) {
            throw ValidationException::withMessages([
                'code' => 'Cancelled registrations cannot check in.',
            ]);
        }

        if ($participant->isCheckedIn()) {
            return $participant->load('user:id,name,email,role_id');
        }

        $participant->markCheckedIn();

        $this->activityLog->record(
            'event.participant_checked_in',
            'attendance',
            "{$participant->user->name} checked in to \"{$event->title}\".",
            $actor->id,
            $event->id,
            ['user_id' => $participant->user_id],
        );

        return $participant->load('user:id,name,email,role_id');
    }

    public function remove(Event $event, EventParticipant $participant, User $actor): void
    {
        $this->ensureBelongsToEvent($event, $participant);

        $name = $participant->user->name;

        $eventId = $participant->event_id;
        $participant->delete();

        $this->activityLog->record(
            'event.participant_removed',
            'participants',
            "{$name} was removed from the participants of \"{$event->title}\".",
            $actor->id,
            $eventId,
            ['user_id' => $participant->user_id],
        );
    }

    /**
     * The caller's own registration for the event, when present.
     */
    public function mine(Event $event, User $user): ?EventParticipant
    {
        return $event->participants()
            ->where('user_id', $user->id)
            ->first();
    }

    private function createRegistration(Event $event, User $user, User $actor): EventParticipant
    {
        $available = $event->capacity === null
            || $event->participants()
                ->whereIn('status', [ParticipantStatus::Registered->value, ParticipantStatus::Waitlisted->value])
                ->whereNull('deleted_at')
                ->count() < $event->capacity;

        return EventParticipant::query()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'registration_code' => $this->uniqueCode(),
            'status' => $available ? ParticipantStatus::Registered : ParticipantStatus::Waitlisted,
            'created_by' => $actor->id,
        ]);
    }

    private function ensureEventAcceptingRegistrations(Event $event): void
    {
        if ($event->status?->value !== 'published') {
            throw ValidationException::withMessages([
                'event' => 'Registrations are only open for published events.',
            ]);
        }
    }

    private function ensureNotAlreadyRegistered(Event $event, User $user): void
    {
        $exists = $event->participants()
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'user_id' => 'This user is already registered for the event.',
            ]);
        }
    }

    private function ensureBelongsToEvent(Event $event, EventParticipant $participant): void
    {
        if ($participant->event_id !== $event->id) {
            throw ValidationException::withMessages([
                'participant' => 'Participant not found for this event.',
            ]);
        }
    }

    private function uniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(10));
        } while (EventParticipant::query()->where('registration_code', $code)->exists());

        return $code;
    }

    private function promoteWaitlisted(Event $event, User $actor): void
    {
        $next = $event->participants()
            ->where('status', ParticipantStatus::Waitlisted->value)
            ->oldest()
            ->first();

        if ($next === null) {
            return;
        }

        $next->update(['status' => ParticipantStatus::Registered]);

        $this->activityLog->record(
            'event.participant_promoted',
            'participants',
            "{$next->user->name} was moved from the waitlist to registered for \"{$event->title}\".",
            $actor->id,
            $event->id,
            ['user_id' => $next->user_id],
        );
    }

    private function statusPhraseAction(EventParticipant $participant, Event $event): string
    {
        return $participant->isWaitlisted()
            ? "was placed on the waitlist for \"{$event->title}\""
            : "registered for \"{$event->title}\"";
    }
}
