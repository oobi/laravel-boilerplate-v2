<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Livewire\Team\ListMembers;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamMembersTest extends TestCase
{
    use RefreshDatabase;

    private function team(User $owner): Team
    {
        return Team::factory()->create(['user_id' => $owner->id]);
    }

    private function addMember(Team $team, User $user, string $role): void
    {
        $team->users()->attach($user);
        app(TeamContext::class)->run($team, fn () => $user->assignRole($role));
    }

    public function test_creating_a_team_sets_up_the_owner_with_the_owner_role(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);

        $this->assertTrue($team->hasUser($owner));
        $this->assertSame('owner', $team->roleFor($owner));
    }

    public function test_an_admin_can_view_the_members_page(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);

        $this->actingAs($owner)
            ->get(route('team.members', ['team' => $team->slug]))
            ->assertOk()
            ->assertSee('Members')
            ->assertSee($owner->email);
    }

    public function test_a_regular_member_cannot_manage_members(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $member = User::factory()->create();
        $this->addMember($team, $member, 'member');

        $this->actingAs($member)
            ->get(route('team.members', ['team' => $team->slug]))
            ->assertForbidden();
    }

    public function test_an_admin_can_change_a_members_role(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $member = User::factory()->create();
        $this->addMember($team, $member, 'member');

        Livewire::actingAs($owner)
            ->test(ListMembers::class, ['team' => $team])
            ->call('changeRole', $member->id, 'admin');

        $this->assertSame('admin', $team->roleFor($member));
    }

    public function test_an_admin_can_remove_a_member(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $member = User::factory()->create();
        $this->addMember($team, $member, 'member');

        Livewire::actingAs($owner)
            ->test(ListMembers::class, ['team' => $team])
            ->call('removeMember', $member->id);

        $this->assertFalse($team->fresh()->hasUser($member));
        $this->assertNull($team->roleFor($member));
    }

    public function test_the_owner_cannot_be_removed_from_their_own_team(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);

        Livewire::actingAs($owner)
            ->test(ListMembers::class, ['team' => $team])
            ->call('removeMember', $owner->id);

        $this->assertTrue($team->fresh()->hasUser($owner));
    }

    public function test_a_regular_member_cannot_call_member_actions(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $member = User::factory()->create();
        $this->addMember($team, $member, 'member');

        Livewire::actingAs($member)
            ->test(ListMembers::class, ['team' => $team])
            ->assertForbidden();
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
