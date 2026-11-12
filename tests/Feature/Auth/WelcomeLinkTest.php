<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\WelcomeNewUser;
use App\Support\Auth\WelcomeLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Features;
use Tests\TestCase;

/**
 * The emailed link a user an admin created follows to choose their own
 * password (GitHub #40): it lasts a week, hands on to Fortify's reset page,
 * and stops working once any password is set.
 */
class WelcomeLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyFeature(Features::resetPasswords());
    }

    public function test_the_link_opens_the_reset_page_and_sets_the_password_once(): void
    {
        $user = User::factory()->create();
        $link = WelcomeLink::url($user);

        $response = $this->get($link);

        $token = basename((string) parse_url((string) $response->headers->get('Location'), PHP_URL_PATH));
        $response->assertRedirect(route('password.reset', ['token' => $token, 'email' => $user->email]));
        $this->assertTrue(Password::broker()->tokenExists($user, $token));

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('a-brand-new-password', $user->fresh()->password));

        $this->get($link)
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('link_invalid', __('auth.welcome_link_invalid'));
    }

    public function test_the_link_works_for_a_week(): void
    {
        $user = User::factory()->create();
        $link = WelcomeLink::url($user);

        $this->travel(7 * 24 * 60 - 1)->minutes();
        $this->get($link)->assertRedirectContains('/reset-password/');

        $this->travel(2)->minutes();
        $this->get($link)->assertRedirect(route('password.request'))->assertSessionHas('link_invalid');
    }

    public function test_an_admin_setting_the_password_ends_the_link(): void
    {
        $user = User::factory()->create();
        $link = WelcomeLink::url($user);

        $user->update(['password' => Hash::make('set-by-an-admin')]);

        $this->get($link)->assertRedirect(route('password.request'))->assertSessionHas('link_invalid');
    }

    /** An admin correcting a mistyped address mustn't leave a working link at the wrong one. */
    public function test_changing_the_address_ends_the_link(): void
    {
        $user = User::factory()->create(['email' => 'typo@example.com']);
        $link = WelcomeLink::url($user);

        $user->update(['email' => 'right@example.com']);

        $this->get($link)->assertRedirect(route('password.request'))->assertSessionHas('link_invalid');
    }

    public function test_the_link_is_limited_to_six_tries_a_minute(): void
    {
        $link = WelcomeLink::url(User::factory()->create());

        foreach (range(1, 6) as $try) {
            $this->get($link)->assertRedirect();
        }

        $this->get($link)->assertTooManyRequests();
    }

    public function test_a_signed_in_visitor_is_sent_home_without_a_token(): void
    {
        $user = User::factory()->create();
        $link = WelcomeLink::url($user);

        $this->actingAs(User::factory()->create())->get($link)->assertRedirect();

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_a_tampered_link_is_refused(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $link = WelcomeLink::url($user);

        $this->get(str_replace("/welcome/{$user->id}/", "/welcome/{$other->id}/", $link))
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('link_invalid');
    }

    public function test_a_deactivated_or_deleted_account_cant_use_its_link(): void
    {
        $inactive = User::factory()->create(['active' => false]);
        $deleted = User::factory()->create();
        $deletedLink = WelcomeLink::url($deleted);
        $deleted->delete();

        $this->get(WelcomeLink::url($inactive))->assertRedirect(route('password.request'))->assertSessionHas('link_invalid');
        $this->get($deletedLink)->assertRedirect(route('password.request'))->assertSessionHas('link_invalid');
    }

    public function test_the_forgot_password_page_shows_why_the_link_failed(): void
    {
        $this->withSession(['link_invalid' => __('auth.welcome_link_invalid')])
            ->get(route('password.request'))
            ->assertSee(__('auth.welcome_link_invalid'));
    }

    public function test_the_email_says_what_it_is_and_carries_the_link(): void
    {
        $user = User::factory()->create();

        $mail = (new WelcomeNewUser)->toMail($user);

        $this->assertSame(__('auth.welcome_subject', ['app' => config('app.name')]), $mail->subject);
        $this->assertSame(__('auth.welcome_action'), $mail->actionText);
        $this->assertStringContainsString('1 week', implode(' ', $mail->introLines));
        $this->assertStringContainsString("/welcome/{$user->id}/", $mail->actionUrl);
    }

    /** A rolled-back create must not leave an email on the queue. */
    public function test_the_email_waits_for_the_account_to_commit(): void
    {
        $this->assertTrue((new WelcomeNewUser)->afterCommit);
    }
}
