<?php

declare(strict_types=1);

namespace Tests\Feature\Teams;

use App\Enums\SystemPermission;
use App\Models\User;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Enums\TeamSystemPermission;
use Concise\Teams\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Team access lost while a team page is open is refused at the next Livewire
 * update: membership and the team's active flag (ResolveTeamContext, made
 * persistent), each page's team ability (its booted()) and the admin pages'
 * `view teams` (route `can:`) are re-checked on every update, not only on
 * the page load.
 */
class TeamLivewireUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_removed_with_a_team_page_open_is_refused_at_the_next_update(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();
        $team->addMember($member);

        $page = $this->actingAs($member)->get(route('team.dashboard', ['team' => $team->slug]))->assertOk();
        $this->livewireUpdate($page)->assertOk();

        $team->removeMember($member);

        $this->livewireUpdate($page)->assertForbidden();
    }

    public function test_a_member_whose_role_loses_a_page_is_refused_at_the_next_update(): void
    {
        Team::createRole('Viewer', [TeamPermission::VIEW_MEMBERS]);
        Team::createRole('Member');
        $member = User::factory()->create();
        $team = Team::factory()->ownedBy(User::factory()->create())->create();
        $team->addMember($member, 'Viewer');

        $page = $this->actingAs($member)->get(route('team.members', ['team' => $team->slug]))->assertOk();
        $this->livewireUpdate($page)->assertOk();

        $team->changeMemberRole($member, 'Member');

        $this->livewireUpdate($page)->assertForbidden();
    }

    public function test_a_team_deactivated_with_its_page_open_is_refused_at_the_next_update(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        $page = $this->actingAs($owner)->get(route('team.dashboard', ['team' => $team->slug]))->assertOk();

        $team->forceFill(['active' => false])->save();

        $this->livewireUpdate($page)->assertForbidden();
    }

    public function test_losing_view_teams_with_the_admin_teams_list_open_refuses_the_next_update(): void
    {
        $admin = User::factory()->withPermission(TeamSystemPermission::VIEW_TEAMS)->create();
        $page = $this->actingAs($admin)->get(route('teams.index'))->assertOk();

        $admin->syncPermissions([SystemPermission::ACCESS_ADMIN_PANEL->value]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $admin->unsetRelation('permissions')->unsetRelation('roles');

        $this->livewireUpdate($page)->assertForbidden();
    }
}
