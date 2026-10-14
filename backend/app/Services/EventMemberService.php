<?php

namespace App\Services;

use App\Enums\Permission as PermissionEnum;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class EventMemberService
{
    public function __construct(private readonly ActivityLogService $activityLog) {}

    /**
     * All memberships for an event, with user and granted permissions.
     */
    public function index(Event $event): Collection
    {
        return $event->eventMembers()
            ->with(['user:id,name,email,role_id', 'user.role', 'permissions'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Users eligible to join a committee of the given event.
     */
    public function candidates(Event $event): Collection
    {
        $existing = $event->eventMembers()->pluck('user_id');

        return User::query()
            ->with('role')
            ->whereIn('role_id', $this->assignableRoleIds())
            ->when($existing->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $existing))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role_id']);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function add(Event $event, User $user, ?string $roleTitle, array $permissions, User $actor): EventMember
    {
        $member = $event->eventMembers()->create([
            'user_id' => $user->id,
            'role_title' => $roleTitle,
        ]);

        $this->syncPermissions($member, $permissions);

        $this->activityLog->record(
            'event.member_added',
            'committees',
            "{$user->name} was added to the committee of \"{$event->title}\".",
            $actor->id,
            $event->id,
            ['user_id' => $user->id, 'role_title' => $roleTitle],
        );

        return $member->load(['user:id,name,email,role_id', 'user.role', 'permissions']);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function update(Event $event, EventMember $member, ?string $roleTitle, array $permissions, User $actor): EventMember
    {
        $member->update(['role_title' => $roleTitle]);
        $this->syncPermissions($member, $permissions);

        $this->activityLog->record(
            'event.member_updated',
            'committees',
            "Membership of {$member->user->name} for \"{$event->title}\" was updated.",
            $actor->id,
            $event->id,
            ['user_id' => $member->user_id, 'role_title' => $roleTitle],
        );

        return $member->load(['user:id,name,email,role_id', 'user.role', 'permissions']);
    }

    public function remove(Event $event, EventMember $member, User $actor): void
    {
        $name = $member->user->name;

        $member->delete();

        $this->activityLog->record(
            'event.member_removed',
            'committees',
            "{$name} was removed from the committee of \"{$event->title}\".",
            $actor->id,
            $event->id,
            ['user_id' => $member->user_id],
        );
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function syncPermissions(EventMember $member, array $permissions): void
    {
        $allowed = PermissionEnum::eventAssignableKeys();

        $keys = array_values(array_filter(
            $permissions,
            fn (string $key) => in_array($key, $allowed, true)
        ));

        $permissionIds = Permission::query()
            ->whereIn('key', $keys)
            ->pluck('id');

        $member->permissions()->sync($permissionIds);
    }

    /**
     * @return array<int, int>
     */
    private function assignableRoleIds(): array
    {
        return \App\Models\Role::query()
            ->whereIn('key', ['committee_member', 'event_owner'])
            ->pluck('id')
            ->all();
    }
}
