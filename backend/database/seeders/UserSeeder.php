<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Admin EventFlow', 'email' => 'admin@eventflow.test', 'role' => RoleEnum::SuperAdmin],
            ['name' => 'Risma Putri', 'email' => 'owner@eventflow.test', 'role' => RoleEnum::EventOwner],
            ['name' => 'Budi Santoso', 'email' => 'committee@eventflow.test', 'role' => RoleEnum::CommitteeMember],
            ['name' => 'Siti Aminah', 'email' => 'participant@eventflow.test', 'role' => RoleEnum::Participant],
        ];

        foreach ($users as $user) {
            $roleId = Role::where('key', $user['role']->value)->value('id');

            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => 'password123',
                    'role_id' => $roleId,
                    'is_active' => true,
                ]
            );
        }
    }
}
