<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Create a user with the given role key.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function userWithRole(string $roleKey, array $attributes = []): User
    {
        $roleId = Role::where('key', $roleKey)->value('id');

        return User::factory()->create(array_merge(['role_id' => $roleId], $attributes));
    }
}
