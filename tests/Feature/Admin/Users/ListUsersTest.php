<?php

namespace Tests\Feature\Admin\Users;

use App\Livewire\Admin\Users\ListUsers;
use App\Models\Role;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ListUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_users_list(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
    }

    public function test_users_without_admin_access_are_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    public function test_admins_can_view_the_users_list(): void
    {
        $admin = User::factory()->superAdmin()->create();
        User::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertStatus(200);
    }

    public function test_the_table_can_search_users_by_name(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $match = User::factory()->create(['first_name' => 'Findable', 'last_name' => 'Person']);
        $other = User::factory()->create(['first_name' => 'Someone', 'last_name' => 'Else']);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->set('tableSearch', 'Findable')
            ->assertSee($match->name)
            ->assertDontSee($other->name);
    }

    public function test_selected_users_can_be_bulk_activated(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $targets = User::factory()->count(2)->inactive()->create();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableBulkAction('activate', $targets);

        $targets->each(fn (User $user) => $this->assertTrue($user->fresh()->active));
    }

    public function test_selected_users_can_be_bulk_deactivated(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $targets = User::factory()->count(2)->create(['active' => true]);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableBulkAction('deactivate', $targets);

        $targets->each(fn (User $user) => $this->assertFalse($user->fresh()->active));
    }

    public function test_bulk_deactivate_never_deactivates_the_acting_admin(): void
    {
        $admin = User::factory()->superAdmin()->create(['active' => true]);
        $target = User::factory()->create(['active' => true]);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableBulkAction('deactivate', collect([$admin, $target]));

        // The actor is filtered out of the selection; the ordinary user is deactivated.
        $this->assertTrue($admin->fresh()->active);
        $this->assertFalse($target->fresh()->active);
    }

    public function test_support_bulk_deactivate_skips_super_admins(): void
    {
        $support = User::factory()->support()->create();
        $protectedSuperAdmin = User::factory()->superAdmin()->create(['active' => true]);
        $target = User::factory()->create(['active' => true]);

        Livewire::actingAs($support)
            ->test(ListUsers::class)
            ->callTableBulkAction('deactivate', collect([$protectedSuperAdmin, $target]));

        // Support may suspend an ordinary user but never a super admin.
        $this->assertTrue($protectedSuperAdmin->fresh()->active);
        $this->assertFalse($target->fresh()->active);
    }

    public function test_selected_users_can_be_bulk_deleted(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $targets = User::factory()->count(2)->create();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableBulkAction('delete', $targets);

        $targets->each(fn (User $user) => $this->assertSoftDeleted($user));
    }

    public function test_trashed_users_can_be_bulk_restored(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $targets = User::factory()->count(2)->create();
        $targets->each->delete();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->filterTable('trashed', '0')
            ->callTableBulkAction('restore', $targets);

        $targets->each(fn (User $user) => $this->assertNotSoftDeleted($user->fresh()));
    }

    public function test_support_cannot_bulk_delete_or_restore_users(): void
    {
        $support = User::factory()->support()->create();

        Livewire::actingAs($support)
            ->test(ListUsers::class)
            ->assertTableBulkActionHidden('delete')
            ->assertTableBulkActionHidden('restore');
    }

    public function test_activate_and_deactivate_bulk_actions_are_hidden_in_the_trash_view(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->filterTable('trashed', '0')
            ->assertTableBulkActionHidden('activate')
            ->assertTableBulkActionHidden('deactivate');
    }

    public function test_a_non_self_user_can_be_deactivated(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create(['active' => true]);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableAction('toggleActive', $target);

        $this->assertFalse($target->fresh()->active);
    }

    public function test_an_admin_cannot_deactivate_themselves(): void
    {
        $admin = User::factory()->superAdmin()->create(['active' => true]);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertTableActionHidden('toggleActive', $admin);
    }

    public function test_admins_can_empty_the_trash_except_their_own_account(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $admin->delete();
        $trashed = User::factory()->count(2)->create();
        $trashed->each->delete();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callAction('emptyTrash');

        $this->assertModelMissing($trashed->first());
        $this->assertModelMissing($trashed->last());
        $this->assertNotNull($admin->fresh());
    }

    public function test_a_non_privileged_admin_cannot_empty_the_trash(): void
    {
        $support = User::factory()->support()->create();
        User::factory()->create()->delete();

        Livewire::actingAs($support)
            ->test(ListUsers::class)
            ->assertActionHidden('emptyTrash');
    }

    public function test_support_can_edit_and_toggle_active_for_a_regular_user(): void
    {
        $support = User::factory()->support()->create();
        $target = User::factory()->create();

        Livewire::actingAs($support)
            ->test(ListUsers::class)
            ->assertTableActionVisible('edit', $target)
            ->assertTableActionVisible('toggleActive', $target);
    }

    public function test_support_cannot_edit_or_toggle_active_for_a_super_admin(): void
    {
        $support = User::factory()->support()->create();
        $target = User::factory()->superAdmin()->create();

        Livewire::actingAs($support)
            ->test(ListUsers::class)
            ->assertTableActionHidden('edit', $target)
            ->assertTableActionHidden('toggleActive', $target);
    }

    public function test_support_cannot_delete_restore_or_force_delete_users(): void
    {
        $support = User::factory()->support()->create();
        $target = User::factory()->create();

        Livewire::actingAs($support)
            ->test(ListUsers::class)
            ->assertTableActionHidden('delete', $target);
    }

    public function test_the_status_column_is_not_sortable(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertTableColumnExists(
                'status',
                fn (TextColumn $column): bool => ! $column->isSortable(),
            );
    }

    public function test_the_role_filter_can_show_only_super_admins(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $support = User::factory()->support()->create();
        $noRole = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->filterTable('role', '__super_admin__')
            ->assertCanSeeTableRecords([$admin])
            ->assertCanNotSeeTableRecords([$support, $noRole]);
    }

    public function test_the_role_filter_can_show_users_with_no_role(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $support = User::factory()->support()->create();
        $noRole = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->filterTable('role', '__no_role__')
            ->assertCanSeeTableRecords([$noRole])
            ->assertCanNotSeeTableRecords([$admin, $support]);
    }

    public function test_the_role_filter_lists_system_roles_only(): void
    {
        Role::findOrCreate('Editor');
        Role::query()->create(['name' => 'Scoped Role', 'scope' => 'team']);

        $options = Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListUsers::class)
            ->instance()
            ->roleFilterOptions();

        $this->assertArrayHasKey('Editor', $options);
        $this->assertArrayNotHasKey('Scoped Role', $options);
    }

    public function test_the_role_filter_can_show_users_with_a_specific_role(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $support = User::factory()->support()->create();
        $noRole = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->filterTable('role', 'Support')
            ->assertCanSeeTableRecords([$support])
            ->assertCanNotSeeTableRecords([$admin, $noRole]);
    }
}
