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
    /**
     * Cases since removed from SystemPermission (nothing checked them). Deleted on
     * seed so an existing install drops them from every role too (the pivots
     * cascade); otherwise a role that still holds one never matches a re-saved
     * role in the "may manage" comparisons.
     *
     * @var list<string>
     */
    public const array RETIRED = ['manage system settings', 'view system analytics'];

    public function run(): void
    {
        foreach (SystemPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        Permission::query()->whereIn('name', self::RETIRED)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
