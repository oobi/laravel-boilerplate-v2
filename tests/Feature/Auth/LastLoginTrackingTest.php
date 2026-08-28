<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LastLoginTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_login_at_is_set_when_a_user_logs_in(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->last_login_at);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_last_login_at_is_updated_on_subsequent_logins(): void
    {
        $user = User::factory()->create(['last_login_at' => now()->subDay()]);
        $firstLoginAt = $user->last_login_at;

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertTrue($user->fresh()->last_login_at->isAfter($firstLoginAt));
    }
}
