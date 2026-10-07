<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Auth\PasswordChecks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fortify's password endpoints (GitHub #18). Limits are per email and
 * address, never per address alone, until trusted proxies are set (#19).
 */
class AuthThrottleTest extends TestCase
{
    use RefreshDatabase;

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

    /** Sign-up is on in the boilerplate: per email and address until #19 adds a per-address ceiling. */
    public function test_sign_up_is_throttled_per_email_and_address(): void
    {
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
}
