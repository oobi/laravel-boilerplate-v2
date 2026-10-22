<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Livewire\Profile\TwoFactorAuthentication;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\Auth\GoogleAccountLinked;
use App\Support\Auth\GoogleLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Livewire\Livewire;
use Tests\TestCase;

/** Linking and unlinking a Google account from the profile's Authentication tab. */
class GoogleAccountLinkTest extends TestCase
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
        // A personal Google account on an outside address: fine to link here, never by email.
        Socialite::fake('google', GoogleUser::fake([
            'id' => 'google-123',
            'email' => 'jo.personal@example.net',
            'email_verified' => true,
        ]));
    }

    /** Back from Google mid-link, as the session the link started in. */
    private function returnFromGoogleLinking(User $user): void
    {
        $this->actingAs($user)
            ->withSession([GoogleLogin::LINKING_KEY => $user->id])
            ->get(route('google.callback'))
            ->assertRedirect(route('profile.two-factor'));
    }

    public function test_the_section_is_hidden_while_the_feature_is_off(): void
    {
        config(['services.google.client_id' => null]);

        $this->actingAs(User::factory()->create())
            ->get('/profile/two-factor')
            ->assertOk()
            ->assertDontSee(__('admin.google_link'));
    }

    /**
     * Straight to Google once the password checks out, with no shared
     * confirmation window left open for Fortify's 2FA routes to honour.
     */
    public function test_linking_asks_for_the_password_then_goes_to_google(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TwoFactorAuthentication::class)
            ->call('linkGoogle')
            ->assertSet('confirmingPassword', true)
            ->assertNoRedirect()
            ->set('confirmablePassword', 'password')
            ->call('confirmPassword')
            ->assertRedirect('https://socialite.fake/google/authorize');

        $this->assertSame($user->id, session(GoogleLogin::LINKING_KEY));
        $this->assertNull(session('auth.password_confirmed_at'));
    }

    public function test_a_wrong_password_never_starts_a_link(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(TwoFactorAuthentication::class)
            ->call('linkGoogle')
            ->set('confirmablePassword', 'wrong-password')
            ->call('confirmPassword')
            ->assertNoRedirect();

        $this->assertNull(session(GoogleLogin::LINKING_KEY));
    }

    /** Any verified Google address: the user has already proved who they are here. */
    public function test_a_signed_in_user_can_link_a_google_account_with_another_address(): void
    {
        $user = User::factory()->create(['email' => 'jo@example.com']);

        $this->returnFromGoogleLinking($user);

        $this->assertSame('google-123', $user->googleAccount->provider_user_id);
        Notification::assertSentTo($user, GoogleAccountLinked::class);
    }

    public function test_a_google_account_linked_to_someone_else_is_refused(): void
    {
        SocialAccount::factory()->create(['provider_user_id' => 'google-123']);
        $user = User::factory()->create();

        $this->returnFromGoogleLinking($user);

        $this->assertNull($user->googleAccount);
        Notification::assertNothingSent();
    }

    public function test_a_second_google_account_is_refused(): void
    {
        $user = User::factory()->create();
        $existing = SocialAccount::factory()->for($user)->create();

        $this->returnFromGoogleLinking($user);

        $this->assertTrue($user->fresh()->googleAccount->is($existing));
        Notification::assertNothingSent();
    }

    /** A link started in one session can't be finished by another account. */
    public function test_a_link_finishes_only_for_the_user_who_started_it(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->withSession([GoogleLogin::LINKING_KEY => $user->id])
            ->get(route('google.callback'))
            ->assertForbidden();

        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_an_impersonated_session_cannot_link(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $this->actingAs($target)
            ->withSession(['impersonated_by' => $admin->id, GoogleLogin::LINKING_KEY => $target->id])
            ->get(route('google.callback'))
            ->assertForbidden();

        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_unlinking_asks_for_the_password_then_removes_the_link(): void
    {
        $user = User::factory()->create();
        SocialAccount::factory()->for($user)->create();

        $component = Livewire::actingAs($user)
            ->test(TwoFactorAuthentication::class)
            ->assertSee(__('admin.linked'))
            ->call('unlinkGoogle');

        $this->assertNotNull($user->fresh()->googleAccount);

        $component->set('confirmablePassword', 'password')->call('confirmPassword');

        $this->assertNull($user->fresh()->googleAccount);
    }
}
