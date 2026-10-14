<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EventService
{
    /** Statuses visible to everyone. Non-managers never see drafts. */
    private const PUBLIC_STATUSES = ['published', 'completed'];

    public function __construct(private readonly ActivityLogService $activityLog) {}

    /**
     * Build a filtered, paginated list of events for the given user.
     */
    public function index(Request $request): LengthAwarePaginator
    {
        $query = Event::query()
            ->with('creator:id,name')
            ->when(
                $this->shouldHideDrafts($request->user()),
                fn (Builder $query) => $query->whereIn('status', self::PUBLIC_STATUSES)
            );

        return QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::partial('title'),
            ])
            ->defaultSort('-starts_at')
            ->allowedSorts(['starts_at', 'title', 'created_at'])
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $creator): Event
    {
        $event = Event::create([
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['title']),
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'status' => $data['status'] ?? Event::DEFAULT_STATUS,
            'created_by' => $creator->id,
        ]);

        $this->handleCover($event, $data);
        $event->load('creator:id,name');

        $this->activityLog->record(
            'event.created',
            'events',
            "Event \"{$event->title}\" was created.",
            $creator->id,
            $event->id,
        );

        return $event;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Event $event, array $data): Event
    {
        if (isset($data['title']) && $data['title'] !== $event->title) {
            $data['slug'] = $this->uniqueSlug($data['title'], $event->id);
        }

        $event->update($data);
        $this->handleCover($event, $data);
        $event->load('creator:id,name');

        $this->activityLog->record(
            'event.updated',
            'events',
            "Event \"{$event->title}\" was updated.",
            $event->created_by,
            $event->id,
        );

        return $event;
    }

    public function delete(Event $event): void
    {
        $title = $event->title;

        $event->delete();

        $this->activityLog->record(
            'event.deleted',
            'events',
            "Event \"{$title}\" was deleted.",
            $event->created_by ?? auth()->id(),
            $event->id,
        );
    }

    /**
     * Replace (or remove) the cover image without touching the rest of the model.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleCover(Event $event, array $data): void
    {
        if (! array_key_exists('cover', $data)) {
            return;
        }

        if ($data['cover'] === null && $event->cover_path) {
            Storage::disk('public')->delete($event->cover_path);
            $event->update(['cover_path' => null]);

            return;
        }

        if ($data['cover'] instanceof UploadedFile) {
            if ($event->cover_path) {
                Storage::disk('public')->delete($event->cover_path);
            }

            $event->update(['cover_path' => $data['cover']->store('event-covers', 'public')]);
        }
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'event';
        $slug = $base;
        $suffix = 1;

        while (
            Event::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.++$suffix;
        }

        return $slug;
    }

    private function shouldHideDrafts(?User $user): bool
    {
        if ($user === null) {
            return true;
        }

        return ! ($user->isSuperAdmin() || $user->isEventOwner() || $user->isCommitteeMember());
    }
}
