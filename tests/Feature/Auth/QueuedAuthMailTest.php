<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\QueuedResetPassword;
use App\Notifications\Auth\QueuedVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Fortify\Features;
use Tests\TestCase;

/**
 * Verification and reset emails go through the queue, so the request never
 * talks to the mail server and a mail outage can't 500 it (GitHub #46).
 */
class QueuedAuthMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_up_queues_the_verification_email(): void
    {
        $this->skipUnlessFortifyFeature(Features::registration());

        Queue::fake();

        $this->post('/register', [
            'first_name' => 'A', 'last_name' => 'B', 'email' => 'new@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertRedirect();

        Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof QueuedVerifyEmail);
    }

    public function test_forgot_password_queues_the_reset_email(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();

        Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof QueuedResetPassword);
    }

    /** The job carries the raw reset token, which the database otherwise only stores hashed. */
    public function test_the_queued_reset_email_is_encrypted_on_the_queue(): void
    {
        config(['queue.default' => 'database']);

        User::factory()->create()->notify(new QueuedResetPassword('plain-reset-token-123'));

        $payload = DB::table('jobs')->value('payload');
        $this->assertNotNull($payload);
        $this->assertStringNotContainsString('plain-reset-token-123', $payload);
    }

    /** Laravel's subject and button are title case; the app's are sentence case like every other label. */
    public function test_the_reset_and_verification_emails_use_sentence_case(): void
    {
        $user = User::factory()->unverified()->create();

        $reset = (new QueuedResetPassword('token'))->toMail($user);
        $this->assertSame(__('auth.reset_password_subject'), $reset->subject);
        $this->assertSame(__('auth.reset_password_action'), $reset->actionText);

        $verify = (new QueuedVerifyEmail)->toMail($user);
        $this->assertSame(__('auth.verify_email_subject'), $verify->subject);
        $this->assertSame(__('auth.verify_email_action'), $verify->actionText);
    }
}
