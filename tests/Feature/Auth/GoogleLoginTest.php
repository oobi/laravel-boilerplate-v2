<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\SocialProvider;
use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\Auth\GoogleAccountLinked;
use App\Support\Auth\Destination;
use App\Support\Auth\LoginRedirectRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

/** Log in with Google: off until configured, existing accounts only, and every password-login rule applies. */
class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'client-secret',
            'services.google.allowed_domains' => [],
        ]);

        Notification::fake();
    }

    /** @param  array<string, mixed>  $attributes */
    private function returnFromGoogle(array $attributes = []): TestResponse
    {
        Socialite::fake('google', GoogleUser::fake(array_merge([
            'id' => 'google-123',
            'email' => 'jo@example.com',
            'email_verified' => true,
            'hd' => 'example.com', // a Workspace account, so Google runs the mailbox
        ], $attributes)));

        return $this->get(route('google.callback'));
    }

    private function assertRefused(TestResponse $response, string $message): void
    {
        $response->assertRedirect(route('login'))->assertSessionHasErrors(['email' => $message]);
        $this->assertGuest();
    }

    public function test_it_is_off_until_google_credentials_are_set(): void
    {
        config(['services.google.client_id' => null]);

        $this->get('/login')->assertOk()->assertDontSee(__('auth.google_continue'));
        $this->get(route('google.redirect'))->assertNotFound();
        $this->get(route('google.callback'))->assertNotFound();
    }

    public function test_the_login_page_offers_google_and_sends_the_visitor_there(): void
    {
        Socialite::fake('google');

        $this->get('/login')->assertSee(__('auth.google_continue'));
        $this->get(route('google.redirect'))->assertRedirect('https://socialite.fake/google/authorize');
    }

    public function test_a_first_google_login_links_the_verified_account_with_that_address(): void
    {
        $user = User::factory()->create(['email' => 'jo@example.com']);

        // Lands through LoginResponse, as a password login does.
        $this->returnFromGoogle()->assertRedirect(Destination::home($user));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => SocialProvider::GOOGLE->value,
            'provider_user_id' => 'google-123',
        ]);
        Notification::assertSentTo($user, GoogleAccountLinked::class);
    }

    /** Matched on Google's stable id, so either address changing later doesn't matter. */
    public function test_a_linked_google_account_signs_in_its_user_whatever_the_addresses_now_are(): void
    {
        $user = User::factory()->create(['email' => 'new-address@example.com']);
        SocialAccount::factory()->for($user)->create(['provider_user_id' => 'google-123', 'email' => 'old@example.com']);

        $this->returnFromGoogle(['email' => 'renamed@example.com']);

        $this->assertAuthenticatedAs($user);
        $this->assertSame('renamed@example.com', $user->googleAccount->email);
        Notification::assertNothingSent();
    }

    public function test_a_gmail_address_links_itself_too(): void
    {
        $user = User::factory()->create(['email' => 'jo@gmail.com']);

        $this->returnFromGoogle(['email' => 'jo@gmail.com', 'hd' => null]);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->googleAccount);
    }

    /**
     * A Google account made on an outside address stays with whoever made it
     * after they lose the mailbox, so it never links itself.
     */
    public function test_a_google_account_on_an_address_google_does_not_run_is_never_linked_by_email(): void
    {
        $user = User::factory()->create(['email' => 'jo@example.com']);

        $this->assertRefused($this->returnFromGoogle(['hd' => null]), __('auth.google_link_from_profile'));
        $this->assertNull($user->googleAccount);
    }

    /** The same answer whether or not an account uses the address, so it reveals nothing. */
    public function test_an_outside_address_gets_the_same_refusal_with_no_account(): void
    {
        $this->assertRefused($this->returnFromGoogle(['hd' => null]), __('auth.google_link_from_profile'));
    }

    /** Once linked from the profile, it signs in like any other link. */
    public function test_a_linked_google_account_on_an_outside_address_signs_in(): void
    {
        $user = User::factory()->create(['email' => 'jo@example.com']);
        SocialAccount::factory()->for($user)->create(['provider_user_id' => 'google-123', 'email' => 'jo@example.com']);

        $this->returnFromGoogle(['hd' => null]);

        $this->assertAuthenticatedAs($user);
    }

    /** Someone may have signed up in the owner's name with a password they know. */
    public function test_an_unverified_account_is_never_linked(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'jo@example.com']);

        $this->assertRefused($this->returnFromGoogle(), __('auth.google_unverified_account'));
        $this->assertNull($user->googleAccount);
    }

    public function test_an_address_google_has_not_verified_is_refused(): void
    {
        User::factory()->create(['email' => 'jo@example.com']);

        $this->assertRefused($this->returnFromGoogle(['email_verified' => false]), __('auth.google_unverified_email'));
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_google_never_creates_an_account(): void
    {
        $this->assertRefused($this->returnFromGoogle(), __('auth.google_no_account'));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_deleted_users_link_no_longer_signs_in(): void
    {
        $user = User::factory()->create();
        SocialAccount::factory()->for($user)->create(['provider_user_id' => 'google-123']);
        $user->delete();

        $this->assertRefused($this->returnFromGoogle(), __('auth.google_no_account'));
    }

    public function test_an_inactive_user_gets_the_same_refusal_as_at_the_password(): void
    {
        $user = User::factory()->inactive()->create(['email' => 'jo@example.com']);

        $this->assertRefused($this->returnFromGoogle(), __('auth.inactive'));
        $this->assertNull($user->googleAccount);
    }

    public function test_a_user_locked_out_by_the_two_factor_grace_period_is_refused(): void
    {
        $user = User::factory()->graceStartedDaysAgo(30)->create(['email' => 'jo@example.com']);
        $user->assignRole(Role::create(['name' => 'Admin', 'requires_two_factor' => true]));

        $this->assertRefused($this->returnFromGoogle(), __('auth.two_factor_locked_out'));
    }

    public function test_the_reject_fallback_refuses_a_user_with_nowhere_to_land(): void
    {
        LoginRedirectRegistry::flush();
        config(['fortify.login_fallback' => 'reject']);
        User::factory()->create(['email' => 'jo@example.com']);

        $this->assertRefused($this->returnFromGoogle(), __('auth.no_workspace'));
    }

    public function test_a_user_with_two_factor_is_challenged_before_being_signed_in(): void
    {
        $user = User::factory()->twoFactorEnabled()->create(['email' => 'jo@example.com']);

        $this->returnFromGoogle()
            ->assertRedirect(route('two-factor.login'))
            ->assertSessionHas('login.id', $user->id)
            ->assertSessionHas('login.remember', false);

        $this->assertGuest();
    }

    public function test_only_accounts_from_an_allowed_workspace_domain_may_sign_in(): void
    {
        config(['services.google.allowed_domains' => ['example.com']]);
        $user = User::factory()->create(['email' => 'jo@example.com']);

        $this->assertRefused(
            $this->returnFromGoogle(['hd' => 'elsewhere.com']),
            __('auth.google_domain_not_allowed', ['domains' => 'example.com']),
        );

        $this->returnFromGoogle(['hd' => 'example.com']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_cancelling_at_google_returns_to_the_login_page(): void
    {
        Socialite::fake('google');

        $this->assertRefused(
            $this->get(route('google.callback', ['error' => 'access_denied'])),
            __('auth.google_cancelled'),
        );
    }

    public function test_a_stale_or_forged_state_is_refused(): void
    {
        Socialite::fake('google', fn () => throw new InvalidStateException);

        $this->assertRefused($this->get(route('google.callback')), __('auth.google_expired'));
    }

    public function test_a_signed_in_user_returning_from_google_is_sent_home_unchanged(): void
    {
        $user = User::factory()->create(['email' => 'jo@example.com']);

        $this->actingAs($user);
        $this->returnFromGoogle()->assertRedirect();

        $this->assertNull($user->googleAccount);
    }

    /** Each test request is a new session, so this reaches the per-address ceiling, as a cookie-less client would. */
    public function test_the_google_routes_are_throttled(): void
    {
        Socialite::fake('google');

        collect(range(1, 60))->each(fn () => $this->get(route('google.redirect'))->assertRedirect());

        $this->get(route('google.redirect'))->assertTooManyRequests();
    }
}
