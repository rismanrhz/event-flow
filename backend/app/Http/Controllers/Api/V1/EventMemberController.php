<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\StoreEventMemberRequest;
use App\Http\Requests\Member\UpdateEventMemberRequest;
use App\Http\Resources\EventMemberResource;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\User;
use App\Services\EventMemberService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventMemberController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly EventMemberService $members) {}

    public function index(Request $request, Event $event): JsonResponse
    {
        $this->authorize('viewMembers', $event);

        $members = $this->members->index($event);

        return $this->success('Committee members retrieved.', EventMemberResource::collection($members));
    }

    public function candidates(Request $request, Event $event): JsonResponse
    {
        $this->authorize('manageMembers', $event);

        $candidates = $this->members->candidates($event);

        return $this->success('Candidate users retrieved.', $candidates->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => [
                'key' => $user->role?->key->value,
                'label' => $user->role?->key->label(),
            ],
        ]));
    }

    public function store(StoreEventMemberRequest $request, Event $event): JsonResponse
    {
        $this->authorize('manageMembers', $event);

        $user = User::findOrFail($request->integer('user_id'));

        if (! $user->isCommitteeMember() && ! $user->isEventOwner()) {
            return $this->error('Only committee members and event owners can join a committee.', 422);
        }

        if ($event->eventMembers()->where('user_id', $user->id)->exists()) {
            return $this->error('This user is already a committee member.', 422);
        }

        $member = $this->members->add(
            $event,
            $user,
            $request->input('role_title'),
            $request->input('permissions', []),
            $request->user()
        );

        return $this->success('Committee member added.', new EventMemberResource($member), 201);
    }

    public function update(UpdateEventMemberRequest $request, Event $event, EventMember $member): JsonResponse
    {
        $this->authorize('manageMembers', $event);

        if ($member->event_id !== $event->id) {
            return $this->error('Membership not found for this event.', 404);
        }

        $member = $this->members->update(
            $event,
            $member,
            $request->input('role_title'),
            $request->input('permissions', []),
            $request->user()
        );

        return $this->success('Committee member updated.', new EventMemberResource($member));
    }

    public function destroy(Request $request, Event $event, EventMember $member): JsonResponse
    {
        $this->authorize('manageMembers', $event);

        if ($member->event_id !== $event->id) {
            return $this->error('Membership not found for this event.', 404);
        }

        $this->members->remove($event, $member, $request->user());

        return $this->success('Committee member removed.');
    }
}
