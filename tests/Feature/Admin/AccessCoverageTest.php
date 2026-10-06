<?php

namespace Tests\Feature\Admin;

use App\Enums\SystemPermission;
use App\Enums\UserAbility;
use App\Livewire\Admin\Roles\CreateRole;
use App\Livewire\Admin\Roles\ManageRoles;
use App\Livewire\Admin\Users\ListUsers;
use App\Livewire\Admin\Users\ShowUser;
use App\Models\Role;
use App\Models\User;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Nobody raises access through the admin screens (#82): you act only on a user
 * or head office role whose permissions you hold all of, never your own roles.
 * Peers act on each other; nobody but a super admin acts on a super admin.
 */
class AccessCoverageTest extends TestCase
{
    use RefreshDatabase;

    private User $support;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();
        $this->support = User::factory()->support()->create();
    }

    public function test_support_gives_a_role_it_covers_but_never_administrator(): void
    {
        $target = User::factory()->create();

        Livewire::actingAs($this->support)
            ->test(ShowUser::class, ['user' => $target])
            ->callAction('manageRoles', data: ['roles' => ['Administrator']])
            ->assertHasFormErrors(['roles.0']);

        $this->assertFalse($target->fresh()->hasRole('Administrator'));

        Livewire::actingAs($this->support)
            ->test(ShowUser::class, ['user' => $target])
            ->callAction('manageRoles', data: ['roles' => ['Support']])
            ->assertHasNoFormErrors();

        $this->assertTrue($target->fresh()->hasRole('Support'));
    }

    public function test_nobody_acts_on_someone_with_more_access(): void
    {
        $this->actingAs($this->support);

        foreach ([UserAbility::UPDATE, UserAbility::ASSIGN_ROLE, UserAbility::TOGGLE_ACTIVE, UserAbility::SEND_PASSWORD_RESET_LINK, UserAbility::RESET_TWO_FACTOR_AUTHENTICATION, UserAbility::IMPERSONATE] as $ability) {
            $this->assertFalse(Gate::allows($ability, $this->administrator), $ability->value);
        }

        Livewire::test(ShowUser::class, ['user' => $this->administrator])->assertActionHidden('manageRoles');
    }

    public function test_peers_act_on_each_other_and_those_above_act_on_those_below(): void
    {
        $peer = User::factory()->support()->create();

        $this->assertTrue(Gate::forUser($this->support)->allows(UserAbility::UPDATE, $peer));
        $this->assertTrue(Gate::forUser($this->support)->allows(UserAbility::ASSIGN_ROLE, $peer));
        $this->assertTrue(Gate::forUser($this->administrator)->allows(UserAbility::TOGGLE_ACTIVE, $this->support));
        $this->assertFalse(Gate::forUser($this->administrator)->allows(UserAbility::UPDATE, User::factory()->superAdmin()->create()));
    }

    public function test_a_role_editor_cant_change_a_role_they_hold_or_one_with_more(): void
    {
        $this->support->givePermissionTo(Permission::findOrCreate(SystemPermission::MANAGE_ROLES->value));
        $administratorRole = Role::findByName('Administrator');
        $supportRole = Role::findByName('Support');

        foreach ([$administratorRole, $supportRole] as $role) {
            Livewire::actingAs($this->support)
                ->test(ManageRoles::class, ['role' => $role])
                ->assertSee(__('admin.role_read_only'))
                ->assertActionHidden('deleteRole')
                ->call('save')
                ->assertForbidden();
        }

        $this->assertTrue(Livewire::actingAs($this->administrator)->test(ManageRoles::class, ['role' => $supportRole])->instance()->canEditRole());
    }

    public function test_a_role_editor_adds_only_permissions_they_hold(): void
    {
        $this->support->givePermissionTo(Permission::findOrCreate(SystemPermission::MANAGE_ROLES->value));
        $editor = Role::findOrCreate('Editor');
        $editor->givePermissionTo(Permission::findOrCreate(SystemPermission::VIEW_USERS->value));

        Livewire::actingAs($this->support)
            ->test(ManageRoles::class, ['role' => $editor])
            ->set('data.permissions_user_management', [SystemPermission::VIEW_USERS->value, SystemPermission::DELETE_USERS->value])
            ->call('save')
            ->assertForbidden();

        $this->assertFalse($editor->fresh()->hasPermissionTo(SystemPermission::DELETE_USERS->value));

        Livewire::actingAs($this->support)
            ->test(ManageRoles::class, ['role' => $editor])
            ->set('data.permissions_user_management', [SystemPermission::VIEW_USERS->value, SystemPermission::SUSPEND_USERS->value])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($editor->fresh()->hasPermissionTo(SystemPermission::SUSPEND_USERS->value));
    }

    public function test_a_new_role_carries_only_permissions_its_creator_holds(): void
    {
        $this->support->givePermissionTo(Permission::findOrCreate(SystemPermission::MANAGE_ROLES->value));

        Livewire::actingAs($this->support)
            ->test(CreateRole::class)
            ->set('data.name', 'Desk')
            ->set('data.permissions_user_management', [SystemPermission::VIEW_USERS->value, SystemPermission::DELETE_USERS->value])
            ->call('create')
            ->assertForbidden();

        $this->assertFalse(Role::query()->where('name', 'Desk')->exists());
    }

    public function test_emptying_the_trash_skips_anyone_with_more_access(): void
    {
        $remover = User::factory()->withPermission(SystemPermission::DELETE_USERS)->create();
        $plain = User::factory()->create();
        $plain->delete();
        $this->administrator->delete();

        Livewire::actingAs($remover)->test(ListUsers::class)->callAction('emptyTrash');

        $this->assertNull(User::withTrashed()->find($plain->id));
        $this->assertNotNull(User::withTrashed()->find($this->administrator->id));
    }

    public function test_team_roles_are_edited_only_by_those_running_teams(): void
    {
        $editor = User::factory()->withPermission(SystemPermission::MANAGE_ROLES)->create();
        $lead = Team::createRole('Lead', [TeamPermission::MANAGE_MEMBERS]);
        Team::factory()->create()->addMember($editor, 'Lead');

        // Holding a team role, they could otherwise give it every team permission.
        $this->actingAs($editor)->get(route('roles.edit', $lead))->assertForbidden();

        $runner = User::factory()->withPermission(SystemPermission::MANAGE_ROLES, SystemPermission::MANAGE_TEAMS)->create();
        $this->actingAs($runner)->get(route('roles.edit', $lead))->assertOk();
    }

    public function test_a_role_held_by_someone_with_more_access_cant_be_edited(): void
    {
        $this->support->givePermissionTo(Permission::findOrCreate(SystemPermission::MANAGE_ROLES->value));
        $editor = Role::findOrCreate('Editor');
        $editor->givePermissionTo(Permission::findOrCreate(SystemPermission::VIEW_USERS->value));

        $this->assertTrue(Livewire::actingAs($this->support)->test(ManageRoles::class, ['role' => $editor])->instance()->canEditRole());

        // An Administrator holds it too: a change would reach them.
        $this->administrator->assignRole($editor);

        $this->assertFalse(Livewire::actingAs($this->support)->test(ManageRoles::class, ['role' => $editor->fresh()])->instance()->canEditRole());
    }

    public function test_the_role_rule_is_explained_to_everyone_but_a_super_admin(): void
    {
        $target = User::factory()->create();

        Livewire::actingAs($this->support)->test(ShowUser::class, ['user' => $target])
            ->mountAction('manageRoles')
            ->assertFormFieldExists('roles', 'mountedActionSchema0', fn (CheckboxList $field): bool => str_contains((string) $field->getChildSchema(CheckboxList::BELOW_CONTENT_SCHEMA_KEY)?->toHtml(), e(__('admin.roles_coverage_help'))));

        Livewire::actingAs(User::factory()->superAdmin()->create())->test(ShowUser::class, ['user' => $target])
            ->mountAction('manageRoles')
            ->assertFormFieldExists('roles', 'mountedActionSchema0', fn (CheckboxList $field): bool => ! str_contains((string) $field->getChildSchema(CheckboxList::BELOW_CONTENT_SCHEMA_KEY)?->toHtml(), e(__('admin.roles_coverage_help'))));
    }

    public function test_emptying_the_trash_says_who_was_left_behind(): void
    {
        $remover = User::factory()->withPermission(SystemPermission::DELETE_USERS)->create();
        User::factory()->create()->delete();
        $this->administrator->delete();

        Livewire::actingAs($remover)->test(ListUsers::class)
            ->callAction('emptyTrash')
            ->assertNotified(FilamentNotification::make()
                ->title(__('admin.empty_trash_success', ['count' => 1]))
                ->body(trans_choice('admin.empty_trash_kept', 1, ['count' => 1]))
                ->success());
    }
}
