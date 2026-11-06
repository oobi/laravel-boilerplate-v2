<?php

declare(strict_types=1);

namespace Tests\Feature\Teams;

use App\Models\AuditEntry;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditCategory;
use Concise\Teams\Actions\AcceptInvitation;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Livewire\Team\ManageDomains;
use Concise\Teams\Models\Domain;
use Concise\Teams\Models\Team;
use Concise\Teams\Models\TeamInvitation;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Activitylog\Actions\LogActivityAction;
use Tests\TestCase;

class TeamAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->team = Team::factory()->ownedBy($this->owner)->create();
    }

    protected function tearDown(): void
    {
        LogActivityAction::clearBeforeLoggingCallbacks();

        parent::tearDown();
    }

    public function test_a_team_lifecycle_is_logged(): void
    {
        $this->team->update(['name' => 'Renamed']);
        $this->team->delete();
        $this->team->restore();

        foreach (['team.created', 'team.updated', 'team.deleted', 'team.restored'] as $event) {
            $this->assertTrue($this->wasRecorded($event), $event);
        }
    }

    public function test_adding_and_removing_a_member_are_recorded_on_the_team(): void
    {
        $this->actingAs($this->owner);

        $this->team->addMember($this->member);

        $added = $this->entry('team.member_added');

        $this->assertTrue($added->subject->is($this->team));
        $this->assertTrue($added->causer->is($this->owner));
        $this->assertSame(AuditCategory::ACCESS->value, $added->log_name);
        $this->assertSame($this->member->getKey(), $added->getProperty('member_id'));
        $this->assertSame($this->member->email, $added->getProperty('member'));

        $this->team->removeMember($this->member);

        $this->assertSame($this->member->getKey(), $this->entry('team.member_removed')->getProperty('member_id'));
    }

    public function test_adding_an_existing_member_or_removing_a_stranger_records_nothing(): void
    {
        $this->team->addMember($this->member);
        $this->team->addMember($this->member);

        $this->assertSame(1, AuditEntry::query()->where('event', 'team.member_added')->where('properties->member_id', $this->member->getKey())->count());

        $this->team->removeMember(User::factory()->create());

        $this->assertFalse($this->wasRecorded('team.member_removed'));
    }

    public function test_changing_a_members_roles_is_recorded_with_before_and_after(): void
    {
        Team::createRole('Support crew');
        Team::createRole('Editor crew');
        $this->team->addMember($this->member, 'Support crew');

        $this->team->syncMemberRoles($this->member, ['Editor crew']);

        $entry = $this->entry('team.member_roles_changed');

        $this->assertSame(['Support crew'], $entry->getProperty('before'));
        $this->assertSame(['Editor crew'], $entry->getProperty('after'));
    }

    public function test_saving_the_same_member_roles_records_nothing_more(): void
    {
        Team::createRole('Support crew');
        $this->team->addMember($this->member, 'Support crew');
        $recorded = AuditEntry::query()->where('event', 'team.member_roles_changed')->count();

        $this->team->syncMemberRoles($this->member, ['Support crew']);

        $this->assertSame($recorded, AuditEntry::query()->where('event', 'team.member_roles_changed')->count());
    }

    public function test_suspending_and_reinstating_are_recorded_only_when_they_change_something(): void
    {
        $this->team->addMember($this->member);

        $this->team->reinstateMember($this->member);
        $this->assertFalse($this->wasRecorded('team.member_reinstated'));

        $this->team->suspendMember($this->member);
        $this->team->suspendMember($this->member);
        $this->assertSame(1, AuditEntry::query()->where('event', 'team.member_suspended')->count());

        $this->team->reinstateMember($this->member);
        $this->assertTrue($this->wasRecorded('team.member_reinstated'));
    }

    public function test_promoting_and_demoting_a_co_owner_are_recorded_only_when_they_change_something(): void
    {
        $this->team->addMember($this->member);

        $this->team->revokeOwner($this->member);
        $this->assertFalse($this->wasRecorded('team.owner_removed'));

        $this->team->makeOwner($this->member);
        $this->team->makeOwner($this->member);
        $this->assertSame(1, AuditEntry::query()->where('event', 'team.owner_added')->count());

        $this->team->revokeOwner($this->member);
        $this->assertTrue($this->wasRecorded('team.owner_removed'));
    }

    public function test_transferring_ownership_is_one_named_entry_with_before_and_after(): void
    {
        $this->team->addMember($this->member);

        $this->team->transferOwnership($this->member);

        $entry = $this->entry('team.ownership_transferred');

        $this->assertSame($this->owner->getKey(), $entry->getProperty('before'));
        $this->assertSame($this->member->getKey(), $entry->getProperty('after'));
        $this->assertFalse($this->wasRecorded('team.updated'));
    }

    public function test_ownership_is_not_transferred_if_its_entry_cannot_be_written(): void
    {
        $this->team->addMember($this->member);
        $this->breakTheAuditLog();

        try {
            $this->team->transferOwnership($this->member);
            $this->fail('Ownership moved without its entry.');
        } catch (RuntimeException) {
            //
        }

        $this->assertSame($this->owner->getKey(), Team::query()->find($this->team->getKey())->user_id);
    }

    public function test_a_member_is_not_added_if_the_entry_cannot_be_written(): void
    {
        $this->breakTheAuditLog();

        try {
            $this->team->addMember($this->member);
            $this->fail('The member was added without an entry.');
        } catch (RuntimeException) {
            //
        }

        $this->assertDatabaseMissing('team_user', ['team_id' => $this->team->getKey(), 'user_id' => $this->member->getKey()]);
    }

    public function test_accepting_an_invitation_is_one_named_entry_and_all_or_nothing(): void
    {
        $this->team->update(['active' => true]);
        $invitation = TeamInvitation::factory()->for($this->team)->create(['email' => $this->member->email, 'role' => null]);

        app(AcceptInvitation::class)($invitation, $this->member);

        $this->assertSame($this->member->getKey(), $this->entry('team.invitation_accepted')->getProperty('member_id'));
        $this->assertFalse($this->wasRecorded('team_invitation.deleted'));
    }

    public function test_an_invitation_stays_usable_if_accepting_it_cannot_be_recorded(): void
    {
        $invitation = TeamInvitation::factory()->for($this->team)->create(['email' => $this->member->email, 'role' => null]);
        $this->breakTheAuditLog();

        try {
            app(AcceptInvitation::class)($invitation, $this->member);
            $this->fail('The invitation was accepted without an entry.');
        } catch (RuntimeException) {
            //
        }

        $this->assertModelExists($invitation);
        $this->assertDatabaseMissing('team_user', ['team_id' => $this->team->getKey(), 'user_id' => $this->member->getKey()]);
    }

    public function test_an_invitations_and_a_domains_lifecycle_are_logged_and_a_verification_token_is_not(): void
    {
        $invitation = TeamInvitation::factory()->for($this->team)->create();
        $invitation->delete();

        $domain = Domain::factory()->for($this->team)->create();
        $token = $domain->verification_token;
        $domain->delete();

        $this->assertTrue($this->wasRecorded('team_invitation.created'));
        $this->assertTrue($this->wasRecorded('team_invitation.deleted'));
        $this->assertTrue($this->wasRecorded('domain.created'));
        $this->assertTrue($this->wasRecorded('domain.deleted'));
        $this->assertStringNotContainsString($token, DB::table('activity_log')->pluck('attribute_changes')->implode(' '));
    }

    public function test_a_team_role_created_with_permissions_records_them(): void
    {
        $role = Team::createRole('Support crew', [TeamPermission::cases()[0]]);

        $entry = $this->entry('role.permissions_changed');

        $this->assertTrue($entry->subject->is(Role::query()->find($role->getKey())));
        $this->assertContains(TeamPermission::cases()[0]->value, $entry->getProperty('after'));
    }

    public function test_deleting_a_team_role_counts_the_members_who_held_it(): void
    {
        $role = Team::createRole('Support crew');
        $this->team->addMember($this->member, 'Support crew');

        $role->delete();

        $this->assertSame(1, $this->entry('role.deleted')->getProperty('holder_count'));
    }

    public function test_a_domain_write_clears_the_trusted_hosts_again_after_its_commit(): void
    {
        $domain = Domain::factory()->verified()->for($this->team)->create();

        DB::transaction(function () use ($domain): void {
            $domain->delete();

            // Another request caching the old list before the commit.
            Cache::put(Domain::VERIFIED_HOSTS_CACHE_KEY, [$domain->domain]);
        });

        $this->assertSame([], Domain::verifiedHosts());
    }

    public function test_a_primary_domain_is_kept_if_the_change_cannot_be_recorded(): void
    {
        config(['teams.domains.enabled' => true, 'teams.domains.custom_domains' => true]);
        $primary = Domain::factory()->verified()->primary()->for($this->team)->create();
        $other = Domain::factory()->verified()->for($this->team)->create();
        $component = Livewire::actingAs(User::factory()->superAdmin()->create())->test(ManageDomains::class, ['team' => $this->team]);
        $this->breakTheAuditLog();

        try {
            $component->callAction(TestAction::make('makePrimary')->table($other));
        } catch (RuntimeException) {
            //
        }

        $this->assertTrue($primary->fresh()->is_primary);
        $this->assertFalse($other->fresh()->is_primary);
    }

    private function breakTheAuditLog(): void
    {
        LogActivityAction::beforeLogging(fn () => throw new RuntimeException('The audit log is down.'));
    }

    private function entry(string $event): AuditEntry
    {
        return AuditEntry::query()->where('event', $event)->latest('id')->firstOrFail();
    }

    private function wasRecorded(string $event): bool
    {
        return AuditEntry::query()->where('event', $event)->exists();
    }
}
