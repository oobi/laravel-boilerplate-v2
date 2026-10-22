<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Auth\PasswordChecks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

/**
 * Fortify's password endpoints (GitHub #18): per email and address, with a
 * looser ceiling per address on login, sign-up and reset (#19).
 */
class AuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The password broker pads every reset request to ~200 ms so timing can't tell
        // a known email from an unknown one; these tests make dozens of them.
        config(['auth.timebox_duration' => 0]);
    }

    public function test_reset_link_requests_are_throttled_per_email(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('password.email'), ['email' => 'someone@example.com'])->assertStatus(302);
        }

        $this->post(route('password.email'), ['email' => 'someone@example.com'])->assertStatus(429);
        $this->post(route('password.email'), ['email' => 'another@example.com'])->assertStatus(302);
    }

    public function test_a_throttled_request_gets_a_page_back_to_sign_in(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('password.email'), ['email' => 'someone@example.com']);
        }

        $this->post(route('password.email'), ['email' => 'someone@example.com'])
            ->assertStatus(429)
            ->assertSee('Too many tries')
            ->assertSee(route('login'));
    }

    public function test_the_confirm_password_endpoint_is_throttled(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (range(1, 5) as $attempt) {
            $this->post(route('password.confirm.store'), ['password' => 'wrong-'.$attempt])->assertStatus(302);
        }

        $this->post(route('password.confirm.store'), ['password' => 'password'])->assertStatus(429);
    }

    public function test_a_signed_in_user_is_sent_home_not_to_sign_in(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (range(1, 5) as $attempt) {
            $this->post(route('password.confirm.store'), ['password' => 'wrong-'.$attempt]);
        }

        $this->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertStatus(429)
            ->assertSee('Take me home')
            ->assertDontSee('Back to sign in');
    }

    /** Sign-up (when on): per email and address, then per address below. */
    public function test_sign_up_is_throttled_per_email_and_address(): void
    {
        $this->skipUnlessFortifyFeature(Features::registration());

        $attempt = fn (string $email) => $this->post('/register', ['first_name' => 'A', 'last_name' => 'B', 'email' => $email, 'password' => 'x', 'password_confirmation' => 'y']);

        foreach (range(1, 5) as $try) {
            $attempt('taken@example.com')->assertStatus(302);
        }

        $attempt('taken@example.com')->assertStatus(429);
        $attempt('another@example.com')->assertStatus(302);
    }

    public function test_reset_password_submissions_are_throttled_per_email(): void
    {
        $attempt = fn () => $this->post(route('password.update'), ['token' => 'nope', 'email' => 'someone@example.com', 'password' => 'a-new-long-password-1', 'password_confirmation' => 'a-new-long-password-1']);

        foreach (range(1, 5) as $try) {
            $attempt()->assertStatus(302);
        }

        $attempt()->assertStatus(429);
    }

    /** Fortify's form and the in-app prompts share one count of wrong passwords. */
    public function test_fortifys_confirm_form_counts_with_the_in_app_prompts(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 5) as $attempt) {
            PasswordChecks::failed($user);
        }

        $this->actingAs($user)
            ->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertSessionHasErrors(['password' => PasswordChecks::throttledMessage($user)]);
    }

    /** One address working through a list of emails hits the ceiling; the visitor behind a trusted proxy is the address. */
    public function test_login_has_a_ceiling_per_address(): void
    {
        config(['trustedproxy.proxies' => '*']);
        $attempt = fn (string $email, string $visitor) => $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeaders(['X-Forwarded-For' => $visitor])
            ->post('/login', ['email' => $email, 'password' => 'wrong']);

        foreach (range(1, 30) as $try) {
            $attempt("user{$try}@example.com", '203.0.113.9')->assertStatus(302);
        }

        $attempt('user31@example.com', '203.0.113.9')->assertStatus(429);
        $attempt('user31@example.com', '203.0.113.10')->assertStatus(302);
    }

    public function test_sign_up_has_a_ceiling_per_address(): void
    {
        $this->skipUnlessFortifyFeature(Features::registration());

        $attempt = fn (string $email, string $address) => $this->withServerVariables(['REMOTE_ADDR' => $address])
            ->post('/register', ['first_name' => 'A', 'last_name' => 'B', 'email' => $email, 'password' => 'x', 'password_confirmation' => 'y']);

        foreach (range(1, 10) as $try) {
            $attempt("user{$try}@example.com", '203.0.113.9')->assertStatus(302);
        }

        $attempt('user11@example.com', '203.0.113.9')->assertStatus(429);
        $attempt('user11@example.com', '203.0.113.10')->assertStatus(302);
    }

    public function test_reset_link_requests_have_a_ceiling_per_address(): void
    {
        $attempt = fn (string $email, string $address) => $this->withServerVariables(['REMOTE_ADDR' => $address])
            ->post(route('password.email'), ['email' => $email]);

        foreach (range(1, 10) as $try) {
            $attempt("user{$try}@example.com", '203.0.113.9')->assertStatus(302);
        }

        $attempt('user11@example.com', '203.0.113.9')->assertStatus(429);
        $attempt('user11@example.com', '203.0.113.10')->assertStatus(302);
    }

    /** Five wrong passwords for one email from one address, then 429 (#45). */
    public function test_login_is_throttled_per_email_after_five_attempts(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $try) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(302);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(429);
        $this->assertGuest();
    }

    /** The two-factor challenge allows five codes per pending login, then 429 (#45). */
    public function test_the_two_factor_challenge_is_throttled(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');

        foreach (range(1, 5) as $try) {
            $this->post('/two-factor-challenge', ['code' => '000000'])->assertStatus(302);
        }

        $this->post('/two-factor-challenge', ['code' => '000000'])->assertStatus(429);
        $this->assertGuest();
    }
}
