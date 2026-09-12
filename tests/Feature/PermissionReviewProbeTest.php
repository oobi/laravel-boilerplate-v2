<?php

namespace Tests\Feature;

use App\Enums\SystemPermission;
use App\Models\User;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Probe for the one open finding in ~dev/permission-review.md. It asserts the
 * CORRECT behaviour, so it is red as evidence today and becomes the regression
 * check once H2 is fixed (at which point its assertions move into a real test
 * class and this file is deleted).
 *
 * TEMPORARY: exclude from a normal run with
 * `php artisan test --exclude-group permission-review`.
 *
 * Fixed and moved: H5, M7 (batch 1); H1, H3 (batch 2); H4 (batch 3,
 * TeamRolesScopeTest).
 */
#[Group('permission-review')]
class PermissionReviewProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_h2_system_permissions_still_resolve_inside_a_team_route(): void
    {
        // A non-super system admin: the actor for whom scope bugs are visible.
        $admin = User::factory()
            ->withPermission(SystemPermission::ACCESS_ADMIN_PANEL, SystemPermission::MANAGE_TEAMS)
            ->create();
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

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
