<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventMember;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventMember>
 */
class EventMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'role_title' => 'Member',
        ];
    }

    /**
     * Grant the membership the given event-scoped permission keys.
     *
     * @param  array<int, string>  $keys
     */
    public function withPermissions(array $keys): static
    {
        return $this->afterCreating(function (EventMember $member) use ($keys) {
            $ids = Permission::query()->whereIn('key', $keys)->pluck('id');
            $member->permissions()->sync($ids);
        });
    }
}
