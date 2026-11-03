<?php

namespace Tests\Feature\Teams;

use App\Enums\SystemPermission;
use App\Livewire\Admin\Users\ListUsers;
use App\Models\User;
use Concise\Teams\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** The Teams column on the admin Users list (GitHub #71). */
class UsersTeamsColumnTest extends TestCase
{
    use RefreshDatabase;

    private User $loner;

    private User $solo;

    private User $joiner;

    private Team $north;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loner = User::factory()->create(['last_name' => 'Aardvark']);
        $this->solo = User::factory()->create(['last_name' => 'Badger']);
        $this->joiner = User::factory()->create(['last_name' => 'Cheetah']);

        // A deleted team doesn't count, nor is it named; joined first, so it's the one a lookup ignoring deletion would pick.
        $gone = Team::factory()->create(['name' => 'Gone Co']);
        $gone->addMember($this->solo);
        $gone->delete();

        $this->north = Team::factory()->create(['name' => 'Northwind']);
        $this->north->addMember($this->solo);
        $this->north->addMember($this->joiner);

        // A suspended membership does.
        $south = Team::factory()->create();
        $south->addMember($this->joiner);
        $south->suspendMember($this->joiner);
        Team::factory()->create()->addMember($this->joiner);
    }

    public function test_one_team_is_named_and_several_are_counted_with_a_link_to_the_memberships(): void
    {
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListUsers::class)
            ->assertSeeHtml('href="'.route('teams.show', $this->north).'"')
            ->assertSeeHtml('href="'.route('users.show', $this->joiner).'#team-memberships"')
            ->assertSee(team_trans_choice('memberships.count', 3, ['count' => 3]))
            ->assertDontSee('Gone Co')
            ->assertDontSeeHtml('href="'.route('users.show', $this->solo).'#team-memberships"')
            ->assertDontSeeHtml('href="'.route('users.show', $this->loner).'#team-memberships"');
    }

    public function test_the_count_links_to_the_memberships_section_on_the_user_page(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('users.show', $this->joiner))
            ->assertSee('id="team-memberships"', false);
    }

    public function test_the_column_costs_no_query_per_user(): void
    {
        $administrator = User::factory()->administrator()->create();
        $few = $this->queries(fn () => Livewire::actingAs($administrator->fresh())->test(ListUsers::class));

        User::factory()->count(6)->create()->each(fn (User $user) => $this->north->addMember($user));
        User::factory()->count(6)->create()->each(function (User $user): void {
            $this->north->addMember($user);
            Team::factory()->create()->addMember($user);
        });

        $this->assertSame($few, $this->queries(fn () => Livewire::actingAs($administrator->fresh())->test(ListUsers::class)));
    }

    public function test_a_team_name_is_not_linked_for_someone_who_cannot_view_teams(): void
    {
        Livewire::actingAs(User::factory()->withPermission(SystemPermission::VIEW_USERS)->create())
            ->test(ListUsers::class)
            ->assertSee('Northwind')
            ->assertDontSeeHtml('href="'.route('teams.show', $this->north).'"');
    }

    public function test_the_list_sorts_by_the_number_of_teams(): void
    {
        $admin = User::factory()->superAdmin()->create(['last_name' => 'Zebra']);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->sortTable('teams', 'desc')
            ->assertCanSeeTableRecords([$this->joiner, $this->solo, $this->loner], inOrder: true);
    }

    /** Queries for one render, after a warm-up render pays the one-off lookups (as AdminQueryCountTest). */
    private function queries(callable $render): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $render();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $render();

        return count(DB::getQueryLog());
    }
}
