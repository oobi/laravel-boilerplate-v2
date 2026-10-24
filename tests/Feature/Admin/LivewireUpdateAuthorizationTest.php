<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\SystemPermission;
use App\Http\Middleware\DenyLowAssuranceSessions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Access lost while a page is open is refused at the next Livewire update,
 * not only on the next page load: each area's read floor is `can:` middleware
 * on its route, which Livewire re-runs on every update.
 */
class LivewireUpdateAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function revokeAllBut(User $user, SystemPermission ...$kept): void
    {
        $user->syncPermissions(array_map(fn (SystemPermission $permission): string => $permission->value, $kept));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->unsetRelation('permissions')->unsetRelation('roles');
    }

    public function test_losing_view_users_with_the_users_list_open_refuses_the_next_update(): void
    {
        $admin = User::factory()->withPermission(SystemPermission::VIEW_USERS)->create();
        $page = $this->actingAs($admin)->get(route('users.index'))->assertOk();

        $this->livewireUpdate($page)->assertOk();

        $this->revokeAllBut($admin, SystemPermission::ACCESS_ADMIN_PANEL);

        $this->livewireUpdate($page)->assertForbidden();
    }

    public function test_losing_manage_roles_with_the_roles_screen_open_refuses_the_next_update(): void
    {
        $admin = User::factory()->withPermission(SystemPermission::MANAGE_ROLES)->create();
        $page = $this->actingAs($admin)->get(route('roles.index'))->assertOk();

        $this->revokeAllBut($admin, SystemPermission::ACCESS_ADMIN_PANEL);

        $this->livewireUpdate($page)->assertForbidden();
    }

    public function test_the_impersonation_block_on_account_pages_is_rerun_on_updates(): void
    {
        $this->assertContains(DenyLowAssuranceSessions::class, app(PersistentMiddleware::class)->getPersistentMiddleware());
    }
}
