<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\QueuedResetPassword;
use App\Notifications\Auth\QueuedVerifyEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Links that go out by email come from APP_URL, whatever host the request
 * that sent them claimed (GitHub #58): a reset link pointing elsewhere hands
 * a real token to whoever owns that host.
 */
class MailedLinksTest extends TestCase
{
    use RefreshDatabase;

    private const APP_URL = 'https://app.example';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => self::APP_URL, 'auth.timebox_duration' => 0]);
        Notification::fake();
    }

    private function resetLinkFor(User $user): string
    {
        $link = null;
        Notification::assertSentTo($user, QueuedResetPassword::class, function (ResetPassword $notification) use ($user, &$link): bool {
            $link = $notification->toMail($user)->actionUrl;

            return true;
        });

        return $link;
    }

    private function verificationLinkFor(User $user): string
    {
        $link = null;
        Notification::assertSentTo($user, QueuedVerifyEmail::class, function (VerifyEmail $notification) use ($user, &$link): bool {
            $link = $notification->toMail($user)->actionUrl;

            return true;
        });

        return $link;
    }

    public function test_a_spoofed_host_does_not_reach_the_reset_link(): void
    {
        $user = User::factory()->create();

        $this->post('http://evil.example/forgot-password', ['email' => $user->email]);

        $this->assertStringStartsWith(self::APP_URL.'/reset-password/', $this->resetLinkFor($user));
    }

    public function test_a_spoofed_forwarded_host_does_not_reach_the_reset_link(): void
    {
        config(['trustedproxy.proxies' => '*']);
        $user = User::factory()->create();

        $this->withHeaders(['X-Forwarded-Host' => 'evil.example'])->post('/forgot-password', ['email' => $user->email]);

        $this->assertStringStartsWith(self::APP_URL.'/reset-password/', $this->resetLinkFor($user));
    }

    public function test_the_reset_link_still_opens_the_reset_form(): void
    {
        $user = User::factory()->create();
        $this->post('http://evil.example/forgot-password', ['email' => $user->email]);

        $this->get($this->resetLinkFor($user))->assertOk()->assertSee($user->email);
    }

    public function test_the_verification_link_comes_from_app_url_and_still_verifies(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post('http://evil.example/email/verification-notification');
        $link = $this->verificationLinkFor($user);

        $this->assertStringStartsWith(self::APP_URL.'/email/verify/', $link);
        $this->actingAs($user)->get($link)->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
