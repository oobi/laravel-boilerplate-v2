<?php

namespace Tests\Feature\Admin\Roles;

use App\Enums\SystemPermission;
use App\Livewire\Admin\Roles\CreateRole;
use App\Models\Role;
use App\Models\User;
use App\Support\Theme\DaisyColor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_is_forbidden_from_creating_a_role(): void
    {
        $support = User::factory()->support()->create();

        $this->actingAs($support)->get('/admin/roles/create')->assertForbidden();
    }

    public function test_an_unknown_scope_is_not_found(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/admin/roles/create?scope=nope')->assertNotFound();
    }

    public function test_super_admins_can_create_a_role_with_permissions(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $component = Livewire::actingAs($admin)
            ->test(CreateRole::class)
            ->set('data.name', 'Editor')
            ->set('data.permissions_user_management', [SystemPermission::MANAGE_USERS->value])
            ->call('create');

        $this->assertDatabaseHas('roles', ['name' => 'Editor']);

        $role = Role::findByName('Editor');

        $component->assertRedirect(route('roles.edit', $role));

        $this->assertTrue($role->fresh()->checkPermissionTo(SystemPermission::MANAGE_USERS->value));
    }

    public function test_super_admins_can_create_a_role_with_a_custom_badge_color(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(CreateRole::class)
            ->set('data.name', 'Editor')
            ->set('data.color', DaisyColor::WARNING->value)
            ->call('create')
            ->assertHasNoErrors();

        $this->assertSame(DaisyColor::WARNING, Role::findByName('Editor')->color);
    }
}
