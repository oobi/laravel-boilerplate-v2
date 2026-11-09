<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Actions\Impersonation\StartImpersonation;
use App\Models\AuditEntry;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Audit\SessionTrail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Mockery\MockInterface;
use Tests\TestCase;

class AuditSessionTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_browser_session_from_failed_login_to_logout_shares_one_trail(): void
    {
        $this->auditRoute();
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/audit-test')->assertOk();
        $sessionId = session()->getId();
        $this->post('/logout');

        $entries = AuditEntry::query()->whereIn('event', ['auth.login_failed', 'auth.login', 'invoice.sent', 'auth.logout'])->orderBy('id')->get();
        $trail = $entries->first()->session_trail;

        $this->assertSame(['auth.login_failed', 'auth.login', 'invoice.sent', 'auth.logout'], $entries->pluck('event')->all());
        $this->assertTrue(Str::isUlid((string) $trail));
        $this->assertSame([$trail], $entries->pluck('session_trail')->unique()->values()->all());
        // Never the session ID, which would let a reader of the log take the session over.
        $this->assertNotSame($sessionId, $trail);
        $this->assertStringNotContainsString((string) $trail, $sessionId);
    }

    public function test_a_session_ended_by_logout_or_another_browser_gets_a_new_trail(): void
    {
        $this->auditRoute();
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->flushSession();
        $this->actingAs($user)->post('/audit-test')->assertOk();

        $trails = AuditEntry::query()->whereIn('event', ['auth.login', 'invoice.sent'])->orderBy('id')->pluck('session_trail');

        $this->assertCount(3, $trails);
        $this->assertCount(3, $trails->unique());
    }

    public function test_a_queued_job_dispatched_in_the_session_joins_its_trail(): void
    {
        config(['queue.default' => 'database']);
        $user = User::factory()->create();
        Route::post('/audit-dispatch', function () {
            Audit::record('report.requested');
            dispatch(function (): void {
                Audit::record('report.generated');
            });

            return response()->noContent(200);
        })->middleware('web');

        $this->actingAs($user)->post('/audit-dispatch')->assertOk();

        $this->asAnotherProcess();
        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertSuccessful();

        $requested = AuditEntry::query()->where('event', 'report.requested')->sole();
        $generated = AuditEntry::query()->where('event', 'report.generated')->sole();

        $this->assertNotNull($requested->session_trail);
        $this->assertSame($requested->session_trail, $generated->session_trail);
    }

    public function test_a_job_dispatched_by_a_queued_job_keeps_the_session_trail(): void
    {
        config(['queue.default' => 'database']);
        Route::post('/audit-dispatch', function () {
            Audit::record('report.requested');
            dispatch(function (): void {
                dispatch(function (): void {
                    Audit::record('report.generated');
                });
            });

            return response()->noContent(200);
        })->middleware('web');

        $this->actingAs(User::factory()->create())->post('/audit-dispatch')->assertOk();

        $this->asAnotherProcess();
        $this->artisan('queue:work', ['--stop-when-empty' => true])->assertSuccessful();

        $requested = AuditEntry::query()->where('event', 'report.requested')->sole();

        $this->assertNotNull($requested->session_trail);
        $this->assertSame($requested->session_trail, AuditEntry::query()->where('event', 'report.generated')->sole()->session_trail);
    }

    public function test_a_two_factor_login_keeps_one_trail_from_the_password_to_the_logout(): void
    {
        $this->auditRoute();
        $this->mock(TwoFactorAuthenticationProvider::class, fn (MockInterface $mock) => $mock->shouldReceive('verify')->andReturnTrue());
        $user = User::factory()->twoFactorEnabled()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->post('/two-factor-challenge', ['code' => '123456']);
        $this->assertAuthenticatedAs($user);
        $this->post('/audit-test')->assertOk();
        $this->post('/logout');

        $this->assertOneTrail(['auth.login_failed', 'auth.login', 'invoice.sent', 'auth.logout']);
    }

    public function test_impersonation_stays_in_the_admins_trail(): void
    {
        $this->auditRoute();
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        Route::post('/audit-impersonate/{user}', function (User $user, StartImpersonation $start) {
            $start->handle($user);

            return response()->noContent(200);
        })->middleware(['web', 'auth']);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $this->post('/audit-impersonate/'.$target->getKey())->assertOk();
        $this->assertAuthenticatedAs($target);
        $this->post('/audit-test')->assertOk();
        $this->post(route('users.impersonate.leave'));
        $this->assertAuthenticatedAs($admin);

        $this->assertOneTrail(['auth.login', 'impersonation.started', 'invoice.sent', 'impersonation.left']);
        $this->assertTrue(AuditEntry::query()->where('event', 'invoice.sent')->sole()->impersonator->is($admin));
    }

    public function test_work_outside_a_browser_has_no_trail(): void
    {
        Audit::record('report.generated');

        $this->assertNull(AuditEntry::query()->sole()->session_trail);
    }

    public function test_a_session_holding_something_else_under_the_key_gets_a_real_trail(): void
    {
        $this->auditRoute();

        $this->withSession([SessionTrail::SESSION_KEY => 'not-a-trail'])->post('/audit-test')->assertOk();

        $this->assertTrue(Str::isUlid((string) AuditEntry::query()->sole()->session_trail));
    }

    /** A queue worker is another process: no person, no Context, no session and no routed request. */
    private function asAnotherProcess(): void
    {
        Auth::logout();
        Context::flush();
        $this->flushSession();
        $this->app->instance('request', new Request);
    }

    /** @param  list<string>  $events  in the order they happened */
    private function assertOneTrail(array $events): void
    {
        $entries = AuditEntry::query()->whereIn('event', $events)->orderBy('id')->get();

        $this->assertSame($events, $entries->pluck('event')->all());
        $this->assertTrue(Str::isUlid((string) $entries->first()->session_trail));
        $this->assertCount(1, $entries->pluck('session_trail')->unique());
    }

    private function auditRoute(): void
    {
        Route::post('/audit-test', function () {
            Audit::record('invoice.sent');

            return response()->noContent(200);
        })->middleware('web');
    }
}
