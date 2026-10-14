<?php

namespace App\Policies;

use App\Enums\EventStatus;
use App\Enums\Permission as PermissionEnum;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Event $event): bool
    {
        if ($user->isSuperAdmin() || $user->isEventOwner() || $user->isCommitteeMember()) {
            return true;
        }

        return in_array($event->status?->value, [
            EventStatus::Published->value,
            EventStatus::Completed->value,
        ], true);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isEventOwner();
    }

    public function update(User $user, Event $event): bool
    {
        return $user->hasEventPermission($event, PermissionEnum::EditEvent);
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->isSuperAdmin() || ($user->isEventOwner() && $event->created_by === $user->id);
    }

    public function manageMembers(User $user, Event $event): bool
    {
        return $user->isSuperAdmin() || ($user->isEventOwner() && $event->created_by === $user->id);
    }

    public function viewMembers(User $user, Event $event): bool
    {
        if ($this->manageMembers($user, $event)) {
            return true;
        }

        return $event->eventMembers()->where('user_id', $user->id)->exists();
    }

    public function viewTasks(User $user, Event $event): bool
    {
        return $user->hasEventPermission($event, PermissionEnum::ManageTasks);
    }

    public function manageTasks(User $user, Event $event): bool
    {
        return $user->hasEventPermission($event, PermissionEnum::ManageTasks);
    }

    public function viewParticipants(User $user, Event $event): bool
    {
        if ($user->isSuperAdmin() || ($user->isEventOwner() && $event->created_by === $user->id)) {
            return true;
        }

        return $user->hasEventPermission($event, PermissionEnum::ManageParticipants)
            || $user->hasEventPermission($event, PermissionEnum::ManageAttendance);
    }

    public function manageParticipants(User $user, Event $event): bool
    {
        if ($user->isSuperAdmin() || ($user->isEventOwner() && $event->created_by === $user->id)) {
            return true;
        }

        return $user->hasEventPermission($event, PermissionEnum::ManageParticipants);
    }

    public function manageAttendance(User $user, Event $event): bool
    {
        if ($user->isSuperAdmin() || ($user->isEventOwner() && $event->created_by === $user->id)) {
            return true;
        }

        return $user->hasEventPermission($event, PermissionEnum::ManageAttendance)
            || $user->hasEventPermission($event, PermissionEnum::ManageParticipants);
    }
}
