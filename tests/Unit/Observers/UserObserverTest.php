<?php

namespace Tests\Unit\Observers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivating_a_user_destroys_their_sessions_and_remember_token(): void
    {
        $user = User::factory()->create(['remember_token' => Str::random(60)]);

        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode('payload'),
            'last_activity' => now()->getTimestamp(),
        ]);

        $user->update(['active' => false]);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertNull($user->fresh()->remember_token);
    }

    public function test_updating_an_active_user_without_deactivating_leaves_sessions_intact(): void
    {
        $user = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode('payload'),
            'last_activity' => now()->getTimestamp(),
        ]);

        $user->update(['first_name' => 'Updated']);

        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_reactivating_a_user_does_not_destroy_sessions(): void
    {
        $user = User::factory()->inactive()->create();

        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode('payload'),
            'last_activity' => now()->getTimestamp(),
        ]);

        $user->update(['active' => true]);

        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());
    }
}
