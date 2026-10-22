<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Forgot and reset password: the reply never says whether an address has an
 * account, and an inactive account can't be reset back into use (#45).
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.timebox_duration' => 0]);
    }

    public function test_the_forgot_password_reply_is_the_same_for_a_known_and_an_unknown_address(): void
    {
        $user = User::factory()->create();

        $known = $this->from(route('password.request'))->post(route('password.email'), ['email' => $user->email]);
        $this->flushSession();
        $unknown = $this->from(route('password.request'))->post(route('password.email'), ['email' => 'nobody@example.com']);

        $this->assertSame($known->headers->get('Location'), $unknown->headers->get('Location'));
        $this->assertSame($known->getSession()->get('status'), $unknown->getSession()->get('status'));
        $this->assertNotNull($known->getSession()->get('status'));
        $unknown->assertSessionHasNoErrors();
    }

    public function test_an_inactive_account_cant_be_reset(): void
    {
        $user = User::factory()->inactive()->create();

        $this->resetWithAFreshToken($user)->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    /** The control: the same request resets an active account. */
    public function test_an_active_account_can_be_reset(): void
    {
        $user = User::factory()->create();

        $this->resetWithAFreshToken($user)->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('a-new-long-password-1', $user->fresh()->password));
    }

    private function resetWithAFreshToken(User $user): TestResponse
    {
        return $this->post(route('password.update'), [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'a-new-long-password-1',
            'password_confirmation' => 'a-new-long-password-1',
        ]);
    }
}
