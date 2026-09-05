<?php

namespace Tests\Feature\Admin\Roles;

use App\Enums\SystemPermission;
use App\Livewire\Admin\Roles\EditRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EditRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_is_forbidden_from_editing_a_role(): void
    {
        $support = User::factory()->support()->create();
        $role = Role::findOrCreate('Editor');

        $this->actingAs($support)->get("/admin/roles/{$role->id}/edit")->assertForbidden();
    }

    public function test_super_admins_can_update_a_roles_permissions(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::findOrCreate('Editor');

        Livewire::actingAs($admin)
            ->test(EditRole::class, ['role' => $role])
            ->set('data.permissions', [SystemPermission::SUSPEND_USERS->value])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('roles.index'));

        $this->assertTrue($role->fresh()->checkPermissionTo(SystemPermission::SUSPEND_USERS->value));
        $this->assertFalse($role->fresh()->checkPermissionTo(SystemPermission::MANAGE_USERS->value));
    }
}
