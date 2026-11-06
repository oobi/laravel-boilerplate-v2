<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Impersonation\StartImpersonation;
use App\Actions\Impersonation\StopImpersonation;
use App\Actions\Users\ToggleUserActive;
use App\Enums\SystemPermission;
use App\Livewire\Admin\Roles\CreateRole;
use App\Livewire\Admin\Roles\ManageRoles;
use App\Livewire\Admin\Users\ShowUser;
use App\Models\AuditEntry;
use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\User;
use App\Panels\Users\SecurityPanel;
use App\Support\Audit\AuditActor;
use App\Support\Audit\AuditCategory;
use App\Support\Auth\GoogleLogin;
use App\Support\TwoFactor\GracePeriod;
use Database\Seeders\SystemRolesSeeder;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Lab404\Impersonate\Services\ImpersonateManager;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Activitylog\Actions\LogActivityAction;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CapturedActsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        LogActivityAction::clearBeforeLoggingCallbacks();

        parent::tearDown();
    }

    public function test_a_login_is_recorded_as_the_person_with_no_user_updated_for_its_timestamp(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $entry = $this->entry('auth.login');

        $this->assertTrue($entry->causer->is($user));
        $this->assertTrue($entry->subject->is($user));
        $this->assertSame(AuditCategory::AUTH->value, $entry->log_name);
        $this->assertSame('127.0.0.1', $entry->ip_address);
        $this->assertFalse($this->wasRecorded('user.updated'));
    }

    public function test_a_logout_is_recorded(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout');

        $this->assertTrue($this->entry('auth.logout')->causer->is($user));
    }

    public function test_a_failed_login_keeps_the_address_but_never_the_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-secret-value']);

        $entry = $this->entry('auth.login_failed');

        $this->assertSame(AuditActor::GUEST, $entry->actor_type);
        $this->assertSame($user->email, $entry->getProperty('identifier'));
        $this->assertTrue($entry->subject->is($user));
        $this->assertStringNotContainsString('wrong-secret-value', (string) DB::table('activity_log')->pluck('properties')->implode(' '));
    }

    public function test_a_password_typed_as_the_address_is_not_stored(): void
    {
        $this->post('/login', ['email' => 'pasted-secret-value', 'password' => 'anything']);

        $entry = $this->entry('auth.login_failed');

        $this->assertNull($entry->getProperty('identifier'));
        $this->assertStringNotContainsString('pasted-secret-value', (string) DB::table('activity_log')->pluck('properties')->implode(' '));
    }

    public function test_a_lockout_is_recorded(): void
    {
        event(new Lockout(Request::create('/login', 'POST', ['email' => 'Someone@Example.test'])));

        $this->assertSame('someone@example.test', $this->entry('auth.lockout')->getProperty('identifier'));
    }

    public function test_registration_is_recorded(): void
    {
        $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'new@example.test',
            'password' => 'a-new-long-password-1',
            'password_confirmation' => 'a-new-long-password-1',
        ]);

        $this->assertSame('new@example.test', $this->entry('auth.registered')->subject->email);
    }

    public function test_an_admin_marking_an_email_verified_is_recorded_as_the_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->unverified()->create();

        Livewire::actingAs($admin)->test(ShowUser::class, ['user' => $user])->callAction('verifyEmail');

        $entry = $this->entry('auth.email_verified');

        $this->assertTrue($entry->causer->is($admin));
        $this->assertTrue($entry->subject->is($user));
        $this->assertFalse($this->wasRecorded('user.updated'));
    }

    public function test_an_audit_outage_does_not_stop_a_login_and_is_reported(): void
    {
        Exceptions::fake();
        $user = User::factory()->create();
        $this->breakTheAuditLog();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_an_audit_outage_does_not_stop_a_logout(): void
    {
        Exceptions::fake();
        $user = User::factory()->create();
        $this->breakTheAuditLog();

        $this->actingAs($user)->post('/logout');

        $this->assertGuest();
    }

    /** A real failed INSERT: Postgres aborts the whole transaction unless the failure was in a savepoint. */
    public function test_a_failed_audit_insert_inside_a_transaction_leaves_it_usable(): void
    {
        Exceptions::fake();
        $user = User::factory()->make();
        LogActivityAction::beforeLogging(fn (Model $activity) => $activity->setAttribute('no_such_column', 1));

        DB::transaction(function () use ($user): void {
            event(new Lockout(Request::create('/login', 'POST')));

            LogActivityAction::clearBeforeLoggingCallbacks();
            $user->save();
        });

        Exceptions::assertReported(QueryException::class);
        $this->assertModelExists($user);
    }

    public function test_an_audit_outage_does_not_stop_a_two_factor_event(): void
    {
        Exceptions::fake();
        $user = User::factory()->create();
        $this->breakTheAuditLog();

        event(new TwoFactorAuthenticationDisabled($user));

        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_a_password_reset_by_link_is_recorded_as_a_guest_acting_on_the_user(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.update'), [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'a-new-long-password-1',
            'password_confirmation' => 'a-new-long-password-1',
        ]);

        $entry = $this->entry('user.password_reset');

        $this->assertTrue($entry->subject->is($user));
        $this->assertSame(AuditActor::GUEST, $entry->actor_type);
        $this->assertSame(AuditCategory::SECURITY->value, $entry->log_name);
    }

    public function test_sending_a_reset_link_is_recorded_only_when_one_is_sent(): void
    {
        $admin = User::factory()->withPermission(SystemPermission::MANAGE_USERS)->create();
        $user = User::factory()->create();
        $component = Livewire::actingAs($admin)->test(ShowUser::class, ['user' => $user]);

        $component->callAction('resetPassword');

        $entry = $this->entry('user.password_reset_link_sent');

        $this->assertTrue($entry->causer->is($admin));
        $this->assertTrue($entry->subject->is($user));

        $component->callAction('resetPassword');

        $this->assertSame(1, AuditEntry::query()->where('event', 'user.password_reset_link_sent')->count());
    }

    public function test_linking_a_google_account_is_recorded(): void
    {
        config(['services.google.client_id' => 'client-id', 'services.google.client_secret' => 'client-secret']);
        $user = User::factory()->create(['email' => 'jo@example.com']);

        $this->returnFromGoogle();

        $entry = $this->entry('user.google_linked');

        $this->assertTrue($entry->subject->is($user));
        $this->assertSame('jo@example.com', $entry->getProperty('google_email'));
    }

    public function test_a_google_address_changing_at_sign_in_is_recorded_and_an_outage_does_not_stop_the_sign_in(): void
    {
        Exceptions::fake();
        config(['services.google.client_id' => 'client-id', 'services.google.client_secret' => 'client-secret']);
        $user = User::factory()->create(['email' => 'jo@example.com']);
        $this->returnFromGoogle();
        Auth::logout();

        $this->returnFromGoogle(['email' => 'jo.new@example.com']);

        $entry = $this->entry('user.google_email_changed');

        $this->assertSame('jo@example.com', $entry->getProperty('before'));
        $this->assertSame('jo.new@example.com', $entry->getProperty('after'));
        Auth::logout();

        $this->breakTheAuditLog();

        $this->returnFromGoogle(['email' => 'jo.newer@example.com']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_bp_make_admin_records_the_role_it_gives(): void
    {
        $role = Role::findOrCreate(SystemRolesSeeder::ADMINISTRATOR);

        $this->artisan('bp:make-admin', ['--administrator' => true, '--first-name' => 'Ada', '--last-name' => 'Lovelace', '--email' => 'ada@example.test'])
            ->expectsQuestion('Password', 'a-new-long-password-1')
            ->assertSuccessful();

        $entry = $this->entry('user.roles_changed');

        $this->assertSame([], $entry->getProperty('before'));
        $this->assertSame([$role->name], $entry->getProperty('after'));
    }

    public function test_syncing_role_implications_records_what_it_granted(): void
    {
        $role = Role::findOrCreate('Desk');
        $role->givePermissionTo(Permission::findOrCreate(SystemPermission::DELETE_USERS->value));

        $this->artisan('bp:roles:sync-implications')->assertSuccessful();

        $entry = $this->entry('role.permissions_changed');

        $this->assertSame([SystemPermission::DELETE_USERS->value], $entry->getProperty('before'));
        $this->assertContains(SystemPermission::VIEW_USERS->value, $entry->getProperty('after'));
    }

    public function test_creating_a_role_records_its_permissions(): void
    {
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(CreateRole::class)
            ->set('data.name', 'Editor')
            ->set('data.permissions_user_management', [SystemPermission::MANAGE_USERS->value])
            ->call('create');

        $entry = $this->entry('role.permissions_changed');

        $this->assertSame('Editor', $entry->subject->name);
        $this->assertContains(SystemPermission::MANAGE_USERS->value, $entry->getProperty('after'));
    }

    public function test_deleting_a_role_says_how_many_lost_it_and_what_it_granted(): void
    {
        $role = Role::findOrCreate('Editor');
        $role->givePermissionTo(Permission::findOrCreate(SystemPermission::VIEW_USERS->value));
        User::factory()->count(2)->create()->each(fn (User $user) => $user->assignRole($role));

        $role->delete();

        $entry = $this->entry('role.deleted');

        $this->assertSame(2, $entry->getProperty('holder_count'));
        $this->assertSame([SystemPermission::VIEW_USERS->value], $entry->getProperty('permissions'));
        $this->assertNull($this->entry('role.created')->getProperty('holder_count'));
    }

    public function test_a_grant_with_no_role_save_clears_the_permission_cache_again_after_its_commit(): void
    {
        $role = Role::findOrCreate('Editor');
        $permission = Permission::findOrCreate(SystemPermission::VIEW_USERS->value);
        $cacheKey = app(PermissionRegistrar::class)->cacheKey;

        DB::transaction(function () use ($role, $permission, $cacheKey): void {
            $role->givePermissionTo($permission);

            // Another request, still seeing the old grants, caching them before the commit.
            Cache::put($cacheKey, 'stale');
        });

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_a_role_write_clears_the_permission_cache_again_after_its_commit(): void
    {
        $role = Role::findOrCreate('Editor');
        $cacheKey = app(PermissionRegistrar::class)->cacheKey;

        DB::transaction(function () use ($role, $cacheKey): void {
            $role->update(['name' => 'Renamed']);

            // Another request, still seeing the old grants, caching them before the commit.
            Cache::put($cacheKey, 'stale');
        });

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_a_role_write_clears_the_two_factor_role_cache_again_after_its_commit(): void
    {
        $role = Role::findOrCreate('Editor');

        DB::transaction(function () use ($role): void {
            $role->update(['requires_two_factor' => true]);

            // Another request caching the old list before the commit.
            Cache::put('two_factor.required_roles', ['Stale role']);
        });

        $this->assertSame(['Editor'], GracePeriod::requiredRoleNames());
    }

    public function test_impersonation_that_fails_to_start_leaves_no_entry(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        $this->actingAs($admin);
        $this->mock(ImpersonateManager::class)->shouldIgnoreMissing()->shouldReceive('take')->andReturn(false);

        try {
            app(StartImpersonation::class)->handle($target);
            $this->fail('A failed start went unnoticed.');
        } catch (RuntimeException) {
            //
        }

        $this->assertFalse($this->wasRecorded('impersonation.started'));
    }

    public function test_changing_your_own_password_is_recorded_once(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        app(UpdateUserPassword::class)->update($user, [
            'current_password' => 'password',
            'password' => 'a-new-long-password-1',
            'password_confirmation' => 'a-new-long-password-1',
        ]);

        $this->assertTrue($this->entry('user.password_changed')->causer->is($user));
        $this->assertFalse($this->wasRecorded('user.updated'));
    }

    public function test_a_rehash_of_the_password_on_login_records_nothing(): void
    {
        $user = User::factory()->create();

        $user->forceFill(['password' => Hash::make('password')])->save();

        $this->assertFalse($this->wasRecorded('user.updated'));
    }

    public function test_an_admin_setting_a_password_is_recorded_without_the_password(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();

        Livewire::actingAs($admin)->test(ShowUser::class, ['user' => $user])->callAction('resetPassword', data: [
            'password' => 'a-new-long-password-1',
            'password_confirmation' => 'a-new-long-password-1',
        ]);

        $entry = $this->entry('user.password_set_by_admin');

        $this->assertTrue($entry->causer->is($admin));
        $this->assertTrue($entry->subject->is($user));
        $this->assertSame(AuditCategory::ACCESS->value, $entry->log_name);
        $this->assertStringNotContainsString('a-new-long-password-1', (string) DB::table('activity_log')->pluck('properties')->implode(' '));
    }

    public function test_a_password_is_not_set_if_its_entry_cannot_be_written(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();
        $this->breakTheAuditLog();

        try {
            Livewire::actingAs($admin)->test(ShowUser::class, ['user' => $user])->callAction('resetPassword', data: [
                'password' => 'a-new-long-password-1',
                'password_confirmation' => 'a-new-long-password-1',
            ]);
        } catch (RuntimeException) {
            //
        }

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_granting_and_revoking_super_admin_are_recorded_with_before_and_after(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();
        $component = Livewire::actingAs($admin)->test(ShowUser::class, ['user' => $user]);

        $component->callAction('manageRoles', data: ['is_super_admin' => true, 'roles' => []]);

        $granted = $this->entry('user.super_admin_granted', $user);

        $this->assertTrue($granted->causer->is($admin));
        $this->assertFalse($granted->getProperty('before'));
        $this->assertTrue($granted->getProperty('after'));

        $component->callAction('manageRoles', data: ['is_super_admin' => false, 'roles' => []]);

        $this->assertFalse($this->entry('user.super_admin_revoked', $user)->getProperty('after'));
    }

    public function test_a_user_created_as_a_super_admin_is_recorded(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->assertTrue($this->entry('user.super_admin_granted', $user)->subject->is($user));
    }

    public function test_changing_a_users_roles_is_recorded_with_before_and_after(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();
        Role::findOrCreate('Editor');
        $user->assignRole(Role::findOrCreate('Support'));

        Livewire::actingAs($admin)->test(ShowUser::class, ['user' => $user])->callAction('manageRoles', data: ['roles' => ['Editor']]);

        $entry = $this->entry('user.roles_changed');

        $this->assertTrue($entry->causer->is($admin));
        $this->assertSame(['Support'], $entry->getProperty('before'));
        $this->assertSame(['Editor'], $entry->getProperty('after'));
    }

    public function test_saving_the_same_roles_records_nothing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Support'));

        Livewire::actingAs($admin)->test(ShowUser::class, ['user' => $user])->callAction('manageRoles', data: ['roles' => ['Support']]);

        $this->assertFalse($this->wasRecorded('user.roles_changed'));
    }

    public function test_roles_are_not_changed_if_the_entry_cannot_be_written(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();
        Role::findOrCreate('Editor');
        $this->breakTheAuditLog();

        try {
            Livewire::actingAs($admin)->test(ShowUser::class, ['user' => $user])->callAction('manageRoles', data: ['roles' => ['Editor']]);
        } catch (RuntimeException) {
            //
        }

        $this->assertSame([], $user->fresh()->roles->pluck('name')->all());
    }

    public function test_deactivating_and_reactivating_are_named_entries_and_not_a_user_updated(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();
        $this->actingAs($admin);

        app(ToggleUserActive::class)($user);
        app(ToggleUserActive::class)($user);

        $this->assertTrue($this->entry('user.deactivated')->causer->is($admin));
        $this->assertTrue($this->entry('user.reactivated')->getProperty('after'));
        $this->assertFalse($this->wasRecorded('user.updated'));
    }

    public function test_an_ordinary_change_alongside_a_named_one_gives_one_entry_for_each(): void
    {
        $user = User::factory()->create();

        $user->update(['first_name' => 'Changed', 'active' => false]);

        $changes = $this->entry('user.updated')->attribute_changes;

        $this->assertSame(['first_name' => 'Changed'], $changes['attributes']);
        $this->assertTrue($this->wasRecorded('user.deactivated'));
    }

    public function test_a_user_is_logged_without_anything_sensitive_on_any_path(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $user->update(['first_name' => 'Changed']);
        $user->delete();
        $user->restore();
        $user->forceDelete();

        $stored = DB::table('activity_log')->pluck('attribute_changes')->implode(' ');

        foreach (['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'] as $column) {
            $this->assertStringNotContainsString($column, $stored);
        }
    }

    public function test_a_two_factor_grace_clock_is_noise_and_records_nothing(): void
    {
        $user = User::factory()->create();

        $user->forceFill(['two_factor_grace_started_at' => now()])->save();

        $this->assertFalse($this->wasRecorded('user.updated'));
    }

    public function test_soft_and_permanent_deletes_are_told_apart(): void
    {
        $user = User::factory()->create();

        $user->delete();
        $this->assertTrue($this->wasRecorded('user.deleted'));
        $this->assertFalse($this->wasRecorded('user.force_deleted'));

        $user->forceDelete();
        $this->assertSame('User force deleted', $this->entry('user.force_deleted')->description);
    }

    public function test_impersonation_starting_and_leaving_are_recorded(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        $this->actingAs($admin);

        app(StartImpersonation::class)->handle($target);

        $started = $this->entry('impersonation.started');

        $this->assertTrue($started->causer->is($admin));
        $this->assertTrue($started->subject->is($target));
        $this->assertSame($target->getKey(), Auth::id());

        app(StopImpersonation::class)->handle();

        $left = $this->entry('impersonation.left');

        $this->assertTrue($left->causer->is($admin));
        $this->assertTrue($left->subject->is($target));
    }

    public function test_impersonation_does_not_start_if_its_entry_cannot_be_written(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        $this->actingAs($admin);
        $this->breakTheAuditLog();

        try {
            app(StartImpersonation::class)->handle($target);
        } catch (RuntimeException) {
            //
        }

        $this->assertSame($admin->getKey(), Auth::id());
    }

    public function test_an_audit_outage_does_not_trap_an_admin_as_someone_else(): void
    {
        Exceptions::fake();
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        $this->actingAs($admin);
        app(StartImpersonation::class)->handle($target);
        $this->breakTheAuditLog();

        app(StopImpersonation::class)->handle();

        $this->assertSame($admin->getKey(), Auth::id());
    }

    public function test_force_disabling_two_factor_is_recorded_as_the_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->twoFactorEnabled()->create();
        $this->actingAs($admin);

        app(SecurityPanel::class)->actions()['force-disable-2fa']($user);

        $this->assertTrue($this->entry('two_factor.force_disabled')->causer->is($admin));
        $this->assertNull($user->fresh()->two_factor_secret);
        $this->assertFalse($this->wasRecorded('user.updated'));
    }

    public function test_resetting_a_two_factor_grace_period_is_recorded(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->graceStartedDaysAgo(30)->create();
        $this->actingAs($admin);

        app(SecurityPanel::class)->actions()['reset-2fa-grace']($user);

        $this->assertTrue($this->entry('two_factor.grace_reset')->subject->is($user));
    }

    public function test_enabling_two_factor_is_one_named_entry(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        app(EnableTwoFactorAuthentication::class)($user);

        $this->assertTrue($this->entry('two_factor.enabled')->causer->is($user));
        $this->assertFalse($this->wasRecorded('user.updated'));
    }

    /** @param  class-string  $eventClass */
    #[DataProvider('twoFactorEvents')]
    public function test_each_two_factor_event_is_recorded(string $eventClass, string $expected): void
    {
        $user = User::factory()->create();

        event($eventClass === RecoveryCodeReplaced::class ? new RecoveryCodeReplaced($user, 'a-used-code') : new $eventClass($user));

        $this->assertSame(AuditCategory::SECURITY->value, $this->entry($expected)->log_name);
        $this->assertStringNotContainsString('a-used-code', (string) DB::table('activity_log')->pluck('properties')->implode(' '));
    }

    /** @return array<string, array{class-string, string}> */
    public static function twoFactorEvents(): array
    {
        return [
            'enabled' => [TwoFactorAuthenticationEnabled::class, 'two_factor.enabled'],
            'confirmed' => [TwoFactorAuthenticationConfirmed::class, 'two_factor.confirmed'],
            'disabled' => [TwoFactorAuthenticationDisabled::class, 'two_factor.disabled'],
            'failed' => [TwoFactorAuthenticationFailed::class, 'two_factor.failed'],
            'codes generated' => [RecoveryCodesGenerated::class, 'two_factor.recovery_codes_generated'],
            'code used' => [RecoveryCodeReplaced::class, 'two_factor.recovery_code_used'],
        ];
    }

    public function test_unlinking_a_google_account_is_recorded_only_when_there_was_one(): void
    {
        $user = User::factory()->create();

        GoogleLogin::unlink($user);
        $this->assertFalse($this->wasRecorded('user.google_unlinked'));

        $account = SocialAccount::factory()->for($user)->create();
        GoogleLogin::unlink($user);

        $entry = $this->entry('user.google_unlinked');

        $this->assertTrue($entry->subject->is($user));
        $this->assertSame($account->email, $entry->getProperty('google_email'));
    }

    public function test_changing_a_roles_permissions_is_recorded_with_before_and_after(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::findOrCreate('Editor');

        Livewire::actingAs($admin)
            ->test(ManageRoles::class, ['role' => $role])
            ->set('data.permissions_user_management', [SystemPermission::SUSPEND_USERS->value])
            ->call('save');

        $entry = $this->entry('role.permissions_changed');

        $this->assertTrue($entry->causer->is($admin));
        $this->assertSame([], $entry->getProperty('before'));
        $this->assertContains(SystemPermission::SUSPEND_USERS->value, $entry->getProperty('after'));
    }

    public function test_saving_a_role_unchanged_records_no_permission_change(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::findOrCreate('Editor');
        $role->givePermissionTo(Permission::findOrCreate(SystemPermission::ACCESS_ADMIN_PANEL->value));

        Livewire::actingAs($admin)->test(ManageRoles::class, ['role' => $role])->call('save');

        $this->assertFalse($this->wasRecorded('role.permissions_changed'));
    }

    public function test_a_role_keeps_its_changes_from_rolling_back_only_when_the_entry_is_written(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::findOrCreate('Editor');
        $this->breakTheAuditLog();

        try {
            Livewire::actingAs($admin)
                ->test(ManageRoles::class, ['role' => $role])
                ->set('data.name', 'Renamed')
                ->call('save');
        } catch (RuntimeException) {
            //
        }

        $this->assertSame('Editor', $role->fresh()->name);
    }

    public function test_a_role_is_not_deleted_if_its_entry_cannot_be_written(): void
    {
        $role = Role::findOrCreate('Editor');
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->breakTheAuditLog();

        try {
            $role->delete();
            $this->fail('The role was deleted without its entry.');
        } catch (RuntimeException) {
            //
        }

        $this->assertModelExists($role);
        $this->assertTrue($user->fresh()->hasRole('Editor'));
    }

    public function test_a_role_lifecycle_is_logged(): void
    {
        $role = Role::findOrCreate('Editor');
        $role->update(['name' => 'Renamed']);
        $role->delete();

        $this->assertSame('Editor', $this->entry('role.updated')->attribute_changes['old']['name']);
        $this->assertTrue($this->wasRecorded('role.created'));
        $this->assertTrue($this->wasRecorded('role.deleted'));
    }

    /** @param  array<string, mixed>  $attributes */
    private function returnFromGoogle(array $attributes = []): void
    {
        Socialite::fake('google', GoogleUser::fake([
            'id' => 'google-123',
            'email' => 'jo@example.com',
            'email_verified' => true,
            'hd' => 'example.com',
            ...$attributes,
        ]));

        $this->get(route('google.callback'));
    }

    private function breakTheAuditLog(): void
    {
        LogActivityAction::beforeLogging(fn () => throw new RuntimeException('The audit log is down.'));
    }

    private function entry(string $event, ?User $subject = null): AuditEntry
    {
        return AuditEntry::query()
            ->where('event', $event)
            ->when($subject !== null, fn ($query) => $query->where('subject_id', $subject->getKey()))
            ->latest('id')
            ->firstOrFail();
    }

    private function wasRecorded(string $event): bool
    {
        return AuditEntry::query()->where('event', $event)->exists();
    }
}
