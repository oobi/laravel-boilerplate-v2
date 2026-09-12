<?php

namespace Tests\Feature;

use App\Enums\SystemPermission;
use App\Livewire\Admin\Roles\ManageRoles;
use App\Models\User;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Probes for the findings in ~dev/permission-review.md. Each test asserts the
 * CORRECT behaviour, so a failing test is the evidence for its finding and a
 * passing one is the regression check once it's fixed. Method names carry the
 * finding id so the two documents cross-reference.
 *
 * TEMPORARY: lives only for the duration of the review and the work responding
 * to it. Expected red until the fixes land; exclude from a normal run with
 * `php artisan test --exclude-group permission-review`. Once a finding is fixed,
 * move its assertions into the proper test class for the code under test and
 * delete the probe here — this file should not outlive the review.
 *
 * Fixed and moved so far: H5, M7 (batch 1); H1 (ShowUserTest, ListUsersTest)
 * and H3 (TeamMembersTest) (batch 2).
 */
#[Group('permission-review')]
class PermissionReviewProbeTest extends TestCase
{
    use RefreshDatabase;

    /** A non-super system admin: the actor for whom scope bugs are visible (a super admin bypasses spatie entirely). */
    private function systemAdmin(): User
    {
        return User::factory()
            ->withPermission(SystemPermission::ACCESS_ADMIN_PANEL, SystemPermission::MANAGE_TEAMS)
            ->create();
    }

    public function test_h2_system_permissions_still_resolve_inside_a_team_route(): void
    {
        $admin = $this->systemAdmin();
        $team = Team::factory()->create();
        $team->addMember($admin);

        // Control: outside the team area the account menu offers the admin dashboard.
        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(team_trans('nav.admin_dashboard'));

        // Inside a team route the same system permission must still answer true.
        $this->actingAs($admin->fresh())
            ->get(route('team.dashboard', ['team' => $team->slug]))
            ->assertOk()
            ->assertSee(team_trans('nav.admin_dashboard'));
    }

    public function test_h4_saving_a_team_role_with_domains_off_keeps_its_manage_domains_grant(): void
    {
        config(['teams.domains.enabled' => false]);
        $role = Team::createRole('Probe Admin', [TeamPermission::MANAGE_DOMAINS, TeamPermission::UPDATE_TEAM]);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ManageRoles::class, ['role' => $role])
            ->call('save')
            ->assertHasNoErrors();

        $role->refresh()->unsetRelation('permissions');
        $this->assertTrue($role->checkPermissionTo(TeamPermission::MANAGE_DOMAINS->value), 'an untouched save must not strip a permission the form merely hid');
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
