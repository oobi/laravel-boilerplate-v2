<?php

namespace Tests\Feature\Admin\Users;

use App\Enums\SystemPermission;
use App\Livewire\Admin\Users\ListUsers;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
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

    public function test_panel_entry_alone_does_not_open_the_users_area(): void
    {
        // A stats-only admin reaches the dashboard, not the roster.
        $panelOnly = User::factory()->withPermission(SystemPermission::ACCESS_ADMIN_PANEL)->create();

        $this->actingAs($panelOnly)->get('/admin/users')->assertForbidden();
    }

    public function test_a_stats_only_admin_cannot_open_the_users_area(): void
    {
        // The reason the users area has its own read floor: `view system analytics`
        // legitimately reaches the panel, but must not carry the roster with it.
        $statsAdmin = User::factory()->withPermission(SystemPermission::VIEW_SYSTEM_ANALYTICS)->create();

        $this->actingAs($statsAdmin)->get('/admin/users')->assertForbidden();
    }

    public function test_view_users_opens_the_list_read_only(): void
    {
        $viewer = User::factory()->withPermission(SystemPermission::VIEW_USERS)->create();
        $target = User::factory()->create();

        $this->actingAs($viewer)->get('/admin/users')->assertOk();

        Livewire::actingAs($viewer)
            ->test(ListUsers::class)
            ->assertCanSeeTableRecords([$target])
            ->assertActionVisible(TestAction::make('view')->table($target))
            ->assertActionHidden(TestAction::make('edit')->table($target))
            ->assertActionHidden(TestAction::make('toggleActive')->table($target))
            ->assertActionHidden(TestAction::make('delete')->table($target));
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
            ->selectTableRecords($targets)->callAction(TestAction::make('activate')->table()->bulk());

        $targets->each(fn (User $user) => $this->assertTrue($user->fresh()->active));
    }

    public function test_selected_users_can_be_bulk_deactivated(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $targets = User::factory()->count(2)->create(['active' => true]);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->selectTableRecords($targets)->callAction(TestAction::make('deactivate')->table()->bulk());

        $targets->each(fn (User $user) => $this->assertFalse($user->fresh()->active));
    }

    public function test_bulk_deactivate_never_deactivates_the_acting_admin(): void
    {
        $admin = User::factory()->superAdmin()->create(['active' => true]);
        $target = User::factory()->create(['active' => true]);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->selectTableRecords(collect([$admin, $target]))->callAction(TestAction::make('deactivate')->table()->bulk());

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
            ->selectTableRecords(collect([$protectedSuperAdmin, $target]))->callAction(TestAction::make('deactivate')->table()->bulk());

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
            ->selectTableRecords($targets)->callAction(TestAction::make('delete')->table()->bulk());

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
            ->selectTableRecords($targets)->callAction(TestAction::make('restore')->table()->bulk());

        $targets->each(fn (User $user) => $this->assertNotSoftDeleted($user->fresh()));
    }

    public function test_support_cannot_bulk_delete_or_restore_users(): void
    {
        $support = User::factory()->support()->create();

        Livewire::actingAs($support)
            ->test(ListUsers::class)
            ->assertActionHidden(TestAction::make('delete')->table()->bulk())
            ->assertActionHidden(TestAction::make('restore')->table()->bulk());
    }

    public function test_activate_and_deactivate_bulk_actions_are_hidden_in_the_trash_view(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->filterTable('trashed', '0')
            ->assertActionHidden(TestAction::make('activate')->table()->bulk())
            ->assertActionHidden(TestAction::make('deactivate')->table()->bulk());
    }

    public function test_a_non_self_user_can_be_deactivated(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create(['active' => true]);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callAction(TestAction::make('toggleActive')->table($target));

        $this->assertFalse($target->fresh()->active);
    }

    public function test_an_admin_cannot_deactivate_themselves(): void
    {
        $admin = User::factory()->superAdmin()->create(['active' => true]);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertActionHidden(TestAction::make('toggleActive')->table($admin));
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

    public function test_emptying_trash_deletes_purged_users_profile_photos(): void
    {
        // Regression for GitHub #12: the bulk purge must not leave uploaded photos
        // orphaned in storage.
        Storage::fake('public');

        $admin = User::factory()->superAdmin()->create();
        $trashed = User::factory()->count(2)->create();
        $paths = $trashed->map(function (User $user): string {
            $user->updateProfilePhoto(UploadedFile::fake()->image('avatar.jpg'));

            return $user->profile_photo_path;
        });
        $trashed->each->delete();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callAction('emptyTrash');

        $this->assertModelMissing($trashed->first());
        $this->assertModelMissing($trashed->last());
        Storage::disk('public')->assertMissing($paths->first());
        Storage::disk('public')->assertMissing($paths->last());
    }

    public function test_force_deleting_a_user_deletes_their_profile_photo(): void
    {
        // Individual force-delete and empty-trash share this cleanup through the
        // forceDeleted model event.
        Storage::fake('public');

        $user = User::factory()->create();
        $user->updateProfilePhoto(UploadedFile::fake()->image('avatar.jpg'));
        $path = $user->profile_photo_path;

        $user->forceDelete();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_emptying_trash_completes_even_when_a_photo_cannot_be_deleted(): void
    {
        // A storage failure on one file must not abort the whole purge.
        Storage::fake('public');

        $admin = User::factory()->superAdmin()->create();
        $trashed = User::factory()->count(2)->create();
        $trashed->each(fn (User $user) => $user->updateProfilePhoto(UploadedFile::fake()->image('avatar.jpg')));
        $trashed->each->delete();

        $throwing = Mockery::mock(Filesystem::class);
        $throwing->shouldReceive('delete')->andThrow(new RuntimeException('storage down'));
        Storage::set('public', $throwing);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callAction('emptyTrash');

        $this->assertModelMissing($trashed->first());
        $this->assertModelMissing($trashed->last());
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
            ->assertActionVisible(TestAction::make('edit')->table($target))
            ->assertActionVisible(TestAction::make('toggleActive')->table($target));
    }

    public function test_support_cannot_edit_or_toggle_active_for_a_super_admin(): void
    {
        $support = User::factory()->support()->create();
        $target = User::factory()->superAdmin()->create();

        Livewire::actingAs($support)
            ->test(ListUsers::class)
            ->assertActionHidden(TestAction::make('edit')->table($target))
            ->assertActionHidden(TestAction::make('toggleActive')->table($target));
    }

    public function test_support_cannot_delete_restore_or_force_delete_users(): void
    {
        $support = User::factory()->support()->create();
        $target = User::factory()->create();

        Livewire::actingAs($support)
            ->test(ListUsers::class)
            ->assertActionHidden(TestAction::make('delete')->table($target));
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
