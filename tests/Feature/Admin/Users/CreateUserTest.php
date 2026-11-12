<?php

namespace Tests\Feature\Admin\Users;

use App\Enums\SystemPermission;
use App\Livewire\Admin\Users\CreateUser;
use App\Models\AuditEntry;
use App\Models\User;
use App\Notifications\Auth\WelcomeNewUser;
use App\Support\Filament\GeneratePasswordAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Activitylog\Actions\LogActivityAction;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        LogActivityAction::clearBeforeLoggingCallbacks();

        parent::tearDown();
    }

    public function test_non_admins_are_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/users/create')->assertForbidden();
    }

    public function test_creating_users_needs_manage_users(): void
    {
        $this->actingAs(User::factory()->support()->create())->get('/admin/users/create')->assertOk();
        $this->actingAs(User::factory()->withPermission(SystemPermission::VIEW_USERS)->create())->get('/admin/users/create')->assertForbidden();
    }

    public function test_admins_can_create_a_user(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->set('data.first_name', 'Brand')
            ->set('data.last_name', 'New User')
            ->set('data.email', 'brand-new@example.com')
            ->set('data.sign_in', 'password')
            ->set('data.password', 'password')
            ->set('data.password_confirmation', 'password')
            ->call('create');

        $this->assertDatabaseHas('users', [
            'first_name' => 'Brand',
            'last_name' => 'New User',
            'email' => 'brand-new@example.com',
            'active' => true,
        ]);
    }

    public function test_creating_a_user_with_a_taken_email_fails_validation_regardless_of_casing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->set('data.first_name', 'Brand')
            ->set('data.last_name', 'New User')
            ->set('data.email', 'TAKEN@Example.com')
            ->set('data.sign_in', 'password')
            ->set('data.password', 'password')
            ->set('data.password_confirmation', 'password')
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);

        $this->assertSame(2, User::count());
    }

    /** A soft-deleted user keeps their row, so their address stays reserved. */
    public function test_creating_a_user_with_a_soft_deleted_users_email_fails_validation(): void
    {
        $admin = User::factory()->superAdmin()->create();
        User::factory()->create(['email' => 'gone@example.com'])->delete();

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->set('data.first_name', 'Brand')
            ->set('data.last_name', 'New User')
            ->set('data.email', 'gone@example.com')
            ->set('data.sign_in', 'password')
            ->set('data.password', 'password')
            ->set('data.password_confirmation', 'password')
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_a_mixed_case_email_is_stored_lowercased(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->set('data.first_name', 'Brand')
            ->set('data.last_name', 'New User')
            ->set('data.email', 'Brand.New@Example.COM')
            ->set('data.sign_in', 'password')
            ->set('data.password', 'password')
            ->set('data.password_confirmation', 'password')
            ->call('create');

        $this->assertDatabaseHas('users', ['email' => 'brand.new@example.com']);
    }

    public function test_by_default_the_new_user_is_emailed_a_link_to_choose_a_password(): void
    {
        $this->skipUnlessFortifyFeature(Features::resetPasswords());
        Notification::fake();
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->assertFormSet(['sign_in' => 'email_link'])
            ->assertFormFieldHidden('password')
            ->set('data.first_name', 'Brand')
            ->set('data.last_name', 'New User')
            ->set('data.email', 'brand-new@example.com')
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin.user_created', ['name' => 'Brand New User']));

        $user = User::query()->where('email', 'brand-new@example.com')->sole();
        Notification::assertSentTo($user, WelcomeNewUser::class);

        $entry = AuditEntry::query()->where('event', 'user.welcome_link_sent')->sole();
        $this->assertTrue($entry->subject->is($user));
        $this->assertTrue($entry->causer->is($admin));
    }

    public function test_setting_a_password_now_sends_no_email(): void
    {
        Notification::fake();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(CreateUser::class)
            ->set('data.first_name', 'Brand')
            ->set('data.last_name', 'New User')
            ->set('data.email', 'brand-new@example.com')
            ->set('data.sign_in', 'password')
            ->set('data.password', 'chosen-by-admin')
            ->set('data.password_confirmation', 'chosen-by-admin')
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'brand-new@example.com')->sole();
        $this->assertTrue(Hash::check('chosen-by-admin', $user->password));
        Notification::assertNothingSent();
        $this->assertFalse(AuditEntry::query()->where('event', 'user.welcome_link_sent')->exists());
    }

    public function test_a_project_can_default_to_setting_a_password(): void
    {
        config(['auth.welcome.email_by_default' => false]);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(CreateUser::class)
            ->assertFormSet(['sign_in' => 'password'])
            ->assertFormFieldVisible('password');
    }

    /** The link hands on to Fortify's reset page, so without that feature there's nothing to email. */
    public function test_without_password_resets_only_setting_a_password_is_offered(): void
    {
        config(['fortify.features' => array_values(array_diff(config('fortify.features'), [Features::resetPasswords()]))]);
        Notification::fake();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(CreateUser::class)
            ->assertFormFieldHidden('sign_in')
            ->assertFormFieldVisible('password')
            ->set('data.first_name', 'Brand')
            ->set('data.last_name', 'New User')
            ->set('data.email', 'brand-new@example.com')
            ->set('data.sign_in', 'email_link')
            ->call('create')
            ->assertHasFormErrors(['password' => 'required']);

        $this->assertFalse(User::query()->where('email', 'brand-new@example.com')->exists());
        Notification::assertNothingSent();
    }

    public function test_the_user_isnt_created_or_emailed_when_the_audit_log_is_down(): void
    {
        $this->skipUnlessFortifyFeature(Features::resetPasswords());
        Notification::fake();
        // Only the welcome entry fails, so this proves it shares the create's transaction.
        LogActivityAction::beforeLogging(function ($activity): void {
            if ($activity->event === 'user.welcome_link_sent') {
                throw new RuntimeException('The audit log is down.');
            }
        });

        $admin = User::factory()->superAdmin()->create();

        try {
            Livewire::actingAs($admin)
                ->test(CreateUser::class)
                ->set('data.first_name', 'Brand')
                ->set('data.last_name', 'New User')
                ->set('data.email', 'brand-new@example.com')
                ->call('create');
            $this->fail('The audit failure should have been thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The audit log is down.', $exception->getMessage());
        }

        $this->assertFalse(User::query()->where('email', 'brand-new@example.com')->exists());
        Notification::assertNothingSent();
    }

    public function test_generate_password_fills_both_fields_with_a_strong_password_and_reveals_it(): void
    {
        $page = Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(CreateUser::class)
            ->set('data.sign_in', 'password')
            ->assertActionHasLabel(TestAction::make('generatePassword')->schemaComponent('password'), __('admin.generate_password'))
            ->assertActionDoesNotExist(TestAction::make('copyPassword')->schemaComponent('password'))
            ->callAction(TestAction::make('generatePassword')->schemaComponent('password'))
            ->assertDispatched('password-generated', field: 'data.password')
            ->assertNotified(__('admin.password_generated'))
            ->assertActionHasLabel(TestAction::make('generatePassword')->schemaComponent('password'), __('admin.regenerate_password'))
            ->assertActionVisible(TestAction::make('copyPassword')->schemaComponent('password'));

        $password = $page->get('data.password');
        $this->assertMatchesRegularExpression('/^([a-km-np-zA-HJ-NP-Z2-9]{5}-){3}[a-km-np-zA-HJ-NP-Z2-9]{5}$/', $password);
        $this->assertSame($password, $page->get('data.password_confirmation'));

        $page->set('data.first_name', 'Brand')
            ->set('data.last_name', 'New User')
            ->set('data.email', 'brand-new@example.com')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check($password, User::query()->where('email', 'brand-new@example.com')->sole()->password));
    }

    /** The hint shares its row with Copy password, so it is linked to the input by hand, with any error. */
    public function test_the_password_hint_and_error_describe_the_input(): void
    {
        $page = Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(CreateUser::class)
            ->set('data.sign_in', 'password')
            ->assertSeeHtml('id="form.password-password-hint"')
            ->assertSeeHtml('aria-describedby="form.password-password-hint"');

        $page->set('data.password', 'short')
            ->call('create')
            ->assertHasFormErrors(['password'])
            ->assertSeeHtml('aria-describedby="form.password-password-hint form.password-error"')
            ->assertSeeHtml('id="form.password-error"');
    }

    public function test_generating_clears_a_stale_password_error(): void
    {
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(CreateUser::class)
            ->set('data.sign_in', 'password')
            ->set('data.password', 'one-password')
            ->set('data.password_confirmation', 'another-password')
            ->call('create')
            ->assertHasFormErrors(['password'])
            ->callAction(TestAction::make('generatePassword')->schemaComponent('password'))
            ->assertHasNoFormErrors(['password', 'password_confirmation']);
    }

    /** Copy password is offered only for a generated password, never one the admin typed. */
    public function test_copy_password_isnt_offered_for_a_typed_password(): void
    {
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(CreateUser::class)
            ->set('data.sign_in', 'password')
            ->set('data.password', 'typed-by-the-admin')
            ->assertActionDoesNotExist(TestAction::make('copyPassword')->schemaComponent('password'))
            ->assertActionHasLabel(TestAction::make('generatePassword')->schemaComponent('password'), __('admin.generate_password'));
    }

    /** Whatever the minimum, and with a project's tighter rules, a generated password passes. */
    public function test_generated_passwords_pass_the_password_rules(): void
    {
        $tighter = Password::min(8)->letters()->mixedCase()->numbers()->symbols();

        foreach (range(8, 64) as $minimum) {
            config(['auth.password_min_length' => $minimum]);

            // Several each: about 1 in 22 draws has no digit, so one sample could miss a dropped check.
            foreach (range(1, 5) as $sample) {
                $password = GeneratePasswordAction::generate();

                $this->assertGreaterThanOrEqual($minimum, strlen($password), "Minimum {$minimum}");
                $this->assertTrue(Validator::make(['password' => $password], ['password' => [$tighter]])->passes(), "Tighter rules: {$password}");
            }
        }
    }
}
