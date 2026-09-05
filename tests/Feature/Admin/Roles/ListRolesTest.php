<?php

namespace Tests\Feature\Admin\Roles;

use App\Livewire\Admin\Roles\ListRoles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ListRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_roles_list(): void
    {
        $this->get('/admin/roles')->assertRedirect('/login');
    }

    public function test_support_is_forbidden_from_viewing_roles(): void
    {
        $support = User::factory()->support()->create();

        $this->actingAs($support)->get('/admin/roles')->assertForbidden();
    }

    public function test_super_admins_can_view_the_roles_list(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Role::findOrCreate('Editor');

        $this->actingAs($admin)
            ->get('/admin/roles')
            ->assertOk()
            ->assertSee('Editor');
    }

    public function test_super_admins_can_delete_a_role(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::findOrCreate('Editor');

        Livewire::actingAs($admin)
            ->test(ListRoles::class)
            ->callTableAction('delete', $role);

        $this->assertModelMissing($role);
    }
}
