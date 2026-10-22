<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        // Where they land is decided by LoginResponse and covered by LoginRedirectTest.
        $response->assertRedirect();
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_inactive_users_cannot_authenticate(): void
    {
        $user = User::factory()->inactive()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    /** Laravel's Failed event fires for a refused login, so lockout or audit listeners hear it (#40). */
    public function test_a_wrong_password_or_an_inactive_account_fires_the_failed_event(): void
    {
        Event::fake([Failed::class]);
        $user = User::factory()->create();
        $inactive = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        $this->post('/login', ['email' => $inactive->email, 'password' => 'password']);
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'password']);

        Event::assertDispatchedTimes(Failed::class, 3);
        Event::assertDispatched(Failed::class, fn (Failed $event): bool => $event->user?->is($user) === true);
    }

    /** An outdated hash is upgraded at login, as Laravel's own login does (#40). */
    public function test_an_outdated_password_hash_is_upgraded_at_login(): void
    {
        // Written past the model's `hashed` cast, which refuses a hash made with other settings.
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['password' => Hash::make('password', ['rounds' => 5])]);
        $this->assertTrue(Hash::needsRehash($user->fresh()->password));

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
        $this->assertFalse(Hash::needsRehash($user->fresh()->password));
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
