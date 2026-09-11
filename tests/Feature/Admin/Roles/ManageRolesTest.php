<?php

namespace Tests\Feature\Admin\Roles;

use App\Enums\SystemPermission;
use App\Livewire\Admin\Roles\ManageRoles;
use App\Models\Role;
use App\Models\User;
use App\Support\Roles\AdminRoleScopes;
use App\Support\Roles\RoleScopeRegistry;
use App\Support\Theme\DaisyColor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ManageRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_roles_page(): void
    {
        $this->get('/admin/roles')->assertRedirect('/login');
    }

    public function test_support_is_forbidden_from_viewing_roles(): void
    {
        $support = User::factory()->support()->create();

        $this->actingAs($support)->get('/admin/roles')->assertForbidden();
    }

    public function test_support_is_forbidden_from_editing_a_role(): void
    {
        $support = User::factory()->support()->create();
        $role = Role::findOrCreate('Editor');

        $this->actingAs($support)->get("/admin/roles/{$role->id}/edit")->assertForbidden();
    }

    public function test_visiting_the_bare_roles_page_defaults_to_the_first_role_alphabetically(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Role::findOrCreate('Zeta');
        $first = Role::findOrCreate('Alpha');

        Livewire::actingAs($admin)
            ->test(ManageRoles::class)
            ->assertSet('selectedRoleId', (string) $first->id)
            ->assertSee('Alpha');
    }

    public function test_super_admins_can_update_a_roles_permissions(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::findOrCreate('Editor');

        Livewire::actingAs($admin)
            ->test(ManageRoles::class, ['role' => $role])
            ->set('data.permissions_user_management', [SystemPermission::SUSPEND_USERS->value])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($role->fresh()->checkPermissionTo(SystemPermission::SUSPEND_USERS->value));
        $this->assertFalse($role->fresh()->checkPermissionTo(SystemPermission::MANAGE_USERS->value));
    }

    public function test_form_is_prefilled_with_neutral_color_when_a_role_has_none_set(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::findOrCreate('Editor');

        Livewire::actingAs($admin)
            ->test(ManageRoles::class, ['role' => $role])
            ->assertSet('data.color', DaisyColor::NEUTRAL->value)
            // the colour picker is a listbox whose options preview this role's badge, each named by its colour
            ->assertSeeHtml('aria-haspopup="listbox"')
            ->assertSeeHtml('aria-label="'.DaisyColor::NEUTRAL->getLabel().'"')
            ->assertSeeHtml('x-text="preview()">Editor<');
    }

    public function test_super_admins_can_update_a_roles_badge_color(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::findOrCreate('Editor');

        Livewire::actingAs($admin)
            ->test(ManageRoles::class, ['role' => $role])
            ->set('data.color', DaisyColor::ERROR->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(DaisyColor::ERROR, $role->fresh()->color);
    }

    public function test_form_is_prefilled_with_permissions_split_by_category(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::findOrCreate('Editor');
        $role->givePermissionTo([
            Permission::findOrCreate(SystemPermission::MANAGE_USERS->value),
            Permission::findOrCreate(SystemPermission::VIEW_SYSTEM_ANALYTICS->value),
        ]);

        Livewire::actingAs($admin)
            ->test(ManageRoles::class, ['role' => $role])
            ->assertSet('data.permissions_user_management', [SystemPermission::MANAGE_USERS->value])
            ->assertSet('data.permissions_system_administration', [SystemPermission::VIEW_SYSTEM_ANALYTICS->value]);
    }

    public function test_each_categorys_select_all_checkbox_is_labelled_for_screen_readers(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::findOrCreate('Editor');

        Livewire::actingAs($admin)
            ->test(ManageRoles::class, ['role' => $role])
            ->assertSeeHtml('aria-label="Select all User Management"')
            ->assertSeeHtml('aria-label="Select all System Administration"')
            // the indeterminate binding watches the category's own list at the form's state path
            ->assertSeeHtml("\$wire.get('data.permissions_user_management')");
    }

    public function test_select_all_toggle_selects_every_permission_in_its_category(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::findOrCreate('Editor');

        Livewire::actingAs($admin)
            ->test(ManageRoles::class, ['role' => $role])
            ->set('data.permissions_user_management_select_all', true)
            ->assertSet('data.permissions_user_management', [
                SystemPermission::MANAGE_USERS->value,
                SystemPermission::SUSPEND_USERS->value,
                SystemPermission::DELETE_USERS->value,
                SystemPermission::IMPERSONATE_USERS->value,
            ]);
    }

    public function test_select_all_is_prefilled_true_when_a_role_already_has_every_permission_in_a_category(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::findOrCreate('Editor');
        $role->givePermissionTo([
            Permission::findOrCreate(SystemPermission::MANAGE_USERS->value),
            Permission::findOrCreate(SystemPermission::SUSPEND_USERS->value),
            Permission::findOrCreate(SystemPermission::DELETE_USERS->value),
            Permission::findOrCreate(SystemPermission::IMPERSONATE_USERS->value),
        ]);

        Livewire::actingAs($admin)
            ->test(ManageRoles::class, ['role' => $role])
            ->assertSet('data.permissions_user_management_select_all', true)
            ->assertSet('data.permissions_system_administration_select_all', false);
    }

    public function test_deselecting_a_permission_unchecks_select_all(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::findOrCreate('Editor');

        Livewire::actingAs($admin)
            ->test(ManageRoles::class, ['role' => $role])
            ->set('data.permissions_user_management_select_all', true)
            ->assertSet('data.permissions_user_management_select_all', true)
            ->set('data.permissions_user_management', [SystemPermission::MANAGE_USERS->value])
            ->assertSet('data.permissions_user_management_select_all', false);
    }

    public function test_saving_merges_permissions_selected_across_category_tabs(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::findOrCreate('Editor');

        Livewire::actingAs($admin)
            ->test(ManageRoles::class, ['role' => $role])
            ->set('data.permissions_user_management', [SystemPermission::MANAGE_USERS->value])
            ->set('data.permissions_system_administration', [SystemPermission::VIEW_SYSTEM_ANALYTICS->value])
            ->call('save')
            ->assertHasNoErrors();

        $role->refresh();
        $this->assertTrue($role->checkPermissionTo(SystemPermission::MANAGE_USERS->value));
        $this->assertTrue($role->checkPermissionTo(SystemPermission::VIEW_SYSTEM_ANALYTICS->value));
    }

    public function test_switching_the_dropdown_redirects_to_that_roles_edit_page(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $roleA = Role::findOrCreate('Alpha');
        $roleB = Role::findOrCreate('Beta');

        Livewire::actingAs($admin)
            ->test(ManageRoles::class, ['role' => $roleA])
            ->set('selectedRoleId', (string) $roleB->id)
            ->assertRedirect(route('roles.edit', $roleB));
    }

    public function test_an_unknown_scope_is_not_found(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/admin/roles?scope=nope')->assertNotFound();
    }

    public function test_a_role_in_an_unregistered_scope_cannot_be_edited_here(): void
    {
        $admin = User::factory()->superAdmin()->create();
        // e.g. left behind by an add-on that has since been removed
        $orphan = Role::create(['name' => 'Orphan', 'scope' => 'legacy']);

        $this->actingAs($admin)->get(route('roles.edit', $orphan))->assertNotFound();
    }

    public function test_scope_tabs_are_hidden_when_only_the_system_scope_is_registered(): void
    {
        RoleScopeRegistry::flush();
        AdminRoleScopes::define();
        $admin = User::factory()->superAdmin()->create();
        Role::findOrCreate('Editor');

        Livewire::actingAs($admin)
            ->test(ManageRoles::class)
            ->assertDontSeeHtml('role="tab"')
            ->assertSee('Editor');
    }

    public function test_super_admins_can_delete_a_role(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::findOrCreate('Editor');

        Livewire::actingAs($admin)
            ->test(ManageRoles::class, ['role' => $role])
            ->callAction('deleteRole')
            ->assertRedirect(route('roles.index'));

        $this->assertModelMissing($role);
    }
}
