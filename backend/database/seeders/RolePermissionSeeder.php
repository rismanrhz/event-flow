<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = collect(RoleEnum::cases())->mapWithKeys(function (RoleEnum $role) {
            return [
                $role->value => Role::query()->firstOrCreate(
                    ['key' => $role->value],
                    ['name' => $role->label(), 'description' => $role->label()]
                ),
            ];
        });

        $permissions = collect(PermissionEnum::cases())->mapWithKeys(function (PermissionEnum $permission) {
            return [
                $permission->value => Permission::query()->firstOrCreate(
                    ['key' => $permission->value],
                    [
                        'name' => $permission->label(),
                        'module' => $permission->module(),
                    ]
                ),
            ];
        });

        // Super admins hold every global permission through the role_permissions pivot.
        $roles[RoleEnum::SuperAdmin->value]
            ->permissions()
            ->sync($permissions->pluck('id'));
    }
}
