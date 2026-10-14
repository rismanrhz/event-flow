<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Participant\CancelParticipantRequest;
use App\Http\Requests\Participant\CheckInParticipantRequest;
use App\Http\Requests\Participant\RegisterParticipantRequest;
use App\Http\Resources\EventParticipantResource;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\User;
use App\Services\ParticipantService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParticipantController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ParticipantService $participants) {}

    public function index(Request $request, Event $event): JsonResponse
    {
        $this->authorize('viewParticipants', $event);

        $paginator = $this->participants->index($event, $request);
        $paginator->setCollection(
            $paginator->getCollection()->transform(fn (EventParticipant $participant) => new EventParticipantResource($participant))
        );

        return $this->paginated('Participants retrieved.', $paginator);
    }

    public function stats(Request $request, Event $event): JsonResponse
    {
        $this->authorize('viewParticipants', $event);

        return $this->success('Participant stats retrieved.', $this->participants->stats($event));
    }

    public function candidates(Request $request, Event $event): JsonResponse
    {
        $this->authorize('manageParticipants', $event);

        return $this->success('Participant candidates retrieved.', $this->participants->candidates($event));
    }

    public function mine(Request $request, Event $event): JsonResponse
    {
        $participant = $this->participants->mine($event, $request->user());

        if ($participant === null) {
            return $this->error('You have not registered for this event.', 404);
        }

        return $this->success('Your registration retrieved.', new EventParticipantResource($participant->load('event:id,title,starts_at')));
    }

    public function store(RegisterParticipantRequest $request, Event $event): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        if ($request->filled('user_id')) {
            $this->authorize('manageParticipants', $event);

            $user = User::findOrFail($request->integer('user_id'));
            $participant = $this->participants->add($event, $user, $actor);

            return $this->success('Participant added successfully.', new EventParticipantResource($participant->load('user:id,name,email,role_id')), 201);
        }

        $participant = $this->participants->register($event, $actor, $actor);

        return $this->success('Registered successfully.', new EventParticipantResource($participant->load('user:id,name,email,role_id')), 201);
    }

    public function checkIn(CheckInParticipantRequest $request, Event $event): JsonResponse
    {
        $this->authorize('manageAttendance', $event);

        $participant = $this->participants->checkIn(
            $event,
            $request->filled('code') ? $request->string('code')->toString() : null,
            $request->filled('participant_id') ? $request->integer('participant_id') : null,
            $request->user()
        );

        return $this->success('Participant checked in successfully.', new EventParticipantResource($participant));
    }

    public function cancel(CancelParticipantRequest $request, Event $event, EventParticipant $participant): JsonResponse
    {
        $this->authorize('manageParticipants', $event);

        if ($participant->event_id !== $event->id) {
            return $this->error('Participant not found for this event.', 404);
        }

        $participant = $this->participants->cancel($event, $participant, $request->input('reason'), $request->user());

        return $this->success('Registration cancelled successfully.', new EventParticipantResource($participant));
    }

    public function destroy(Request $request, Event $event, EventParticipant $participant): JsonResponse
    {
        $this->authorize('manageParticipants', $event);

        if ($participant->event_id !== $event->id) {
            return $this->error('Participant not found for this event.', 404);
        }

        $this->participants->remove($event, $participant, $request->user());

        return $this->success('Participant removed successfully.');
    }
}
