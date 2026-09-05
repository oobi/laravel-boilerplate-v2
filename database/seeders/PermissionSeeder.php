<?php

namespace Database\Seeders;

use App\Enums\SystemPermission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds one Permission row per SystemPermission case. This is the fixed,
 * code-controlled capability vocabulary — role -> permission assignment
 * itself happens via the Roles admin screen, never here.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SystemPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
