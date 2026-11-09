<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\AuditEntry;
use App\Providers\AuditServiceProvider;
use App\Support\Audit\Audit;
use App\Support\Audit\AuditActor;
use App\Support\Audit\AuditCategory;
use Illuminate\Console\Application;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Scheduling\Event as ScheduledTask;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Activitylog\Actions\LogActivityAction;
use Tests\TestCase;

class SystemEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        LogActivityAction::clearBeforeLoggingCallbacks();

        parent::tearDown();
    }

    public function test_a_finished_task_is_recorded_as_the_scheduler(): void
    {
        $task = $this->task('Clean the widgets');
        $task->exitCode = 0;

        event(new ScheduledTaskFinished($task, 1.5));

        $entry = AuditEntry::query()->where('event', 'schedule.task_finished')->sole();

        $this->assertSame(AuditActor::SCHEDULER, $entry->actor_type);
        $this->assertSame(AuditCategory::SYSTEM->value, $entry->log_name);
        $this->assertSame('Clean the widgets', $entry->getProperty('task'));
        $this->assertSame(1.5, $entry->getProperty('runtime_seconds'));
    }

    public function test_routine_runs_can_be_left_out_but_a_failure_cannot(): void
    {
        config(['audit.log_schedule_routine' => false]);
        $task = $this->task();
        $task->exitCode = 0;

        event(new ScheduledTaskFinished($task, 1.0));
        event(new ScheduledTaskSkipped($task));
        $this->assertSame(0, AuditEntry::count());

        event(new ScheduledTaskFailed($task, new RuntimeException('Failed.')));

        $this->assertSame(1, AuditEntry::query()->where('event', 'schedule.task_failed')->count());
    }

    public function test_a_real_task_exiting_non_zero_is_one_failure_with_its_code(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        app(Schedule::class)->exec('false');

        $this->artisan('schedule:run');

        $entry = AuditEntry::query()->where('event', 'schedule.task_failed')->sole();

        $this->assertSame(1, $entry->getProperty('exit_code'));
        $this->assertSame('Shell command', $entry->getProperty('task'));
    }

    public function test_a_real_tasks_arguments_never_reach_the_log(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        collect([
            'API_KEY=secret-in-the-env true --password=secret-in-the-args',
            'TOKEN="my secret-in-double-quotes" true',
            "TOKEN='a secret-in-single-quotes b' true",
            '/tmp/secret-in-a-path/tool',
            'https://user:secret-in-a-url@example.invalid/hook',
            "'secret-in-a-quoted-word'",
        ])->each(fn (string $shell) => app(Schedule::class)->exec($shell));
        app(Schedule::class)->command('inspire --secret=secret-in-the-args');
        app(Schedule::class)->command('--env=secret-in-an-option inspire');
        app(Schedule::class)->exec('true --password=secret-in-the-args')->description('Named task');

        $this->artisan('schedule:run');

        $tasks = AuditEntry::query()->where('event', 'like', 'schedule.task_%')->get()->map(fn (AuditEntry $entry): mixed => $entry->getProperty('task'))->countBy()->all();

        $this->assertSame(7, $tasks['Shell command']);
        $this->assertSame(1, $tasks['inspire']);
        $this->assertSame(1, $tasks['Named task']);
        $this->assertStringNotContainsString('secret-in', DB::table('activity_log')->pluck('properties')->implode(' '));
    }

    public function test_a_background_task_exiting_non_zero_is_a_failure_with_its_code(): void
    {
        $task = $this->task()->runInBackground();
        $task->exitCode = 3;

        event(new ScheduledBackgroundTaskFinished($task));

        $this->assertSame(3, AuditEntry::query()->where('event', 'schedule.task_failed')->sole()->getProperty('exit_code'));
    }

    public function test_a_task_that_throws_keeps_only_the_class_of_the_exception(): void
    {
        event(new ScheduledTaskFailed($this->task(), new RuntimeException('secret-in-the-message')));

        $entry = AuditEntry::query()->where('event', 'schedule.task_failed')->sole();

        $this->assertSame(RuntimeException::class, $entry->getProperty('exception'));
        $this->assertStringNotContainsString('secret-in-the-message', $entry->getRawOriginal('properties'));
    }

    public function test_a_background_task_is_recorded_when_it_finishes_not_when_it_starts(): void
    {
        $task = $this->task()->runInBackground();

        event(new ScheduledTaskFinished($task, 0.1));
        $this->assertSame(0, AuditEntry::count());

        $task->exitCode = 0;
        event(new ScheduledBackgroundTaskFinished($task));

        $this->assertSame(1, AuditEntry::query()->where('event', 'schedule.task_finished')->count());
    }

    public function test_an_audit_outage_does_not_stop_a_task(): void
    {
        Exceptions::fake();
        LogActivityAction::beforeLogging(fn () => throw new RuntimeException('The audit log is down.'));

        event(new ScheduledTaskSkipped($this->task()));

        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_a_real_job_nobody_dispatched_is_the_queue_and_the_mark_ends_with_it(): void
    {
        config(['queue.default' => 'database']);

        dispatch(function (): void {
            Audit::record('report.generated');
        });

        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertSuccessful();

        $this->assertSame(AuditActor::QUEUE, AuditEntry::query()->where('event', 'report.generated')->sole()->actor_type);

        Audit::record('invoice.sent');

        $this->assertSame(AuditActor::SYSTEM, AuditEntry::query()->where('event', 'invoice.sent')->sole()->actor_type);
    }

    public function test_a_job_stays_the_queue_after_a_sync_job_runs_inside_it(): void
    {
        config(['queue.default' => 'database']);

        dispatch(function (): void {
            Audit::record('outer.before');

            dispatch(function (): void {
                Audit::record('inner.ran');
            })->onConnection('sync');

            Audit::record('outer.after');
        });

        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertSuccessful();

        foreach (['outer.before', 'inner.ran', 'outer.after'] as $event) {
            $this->assertSame(AuditActor::QUEUE, AuditEntry::query()->where('event', $event)->sole()->actor_type, $event);
        }

        Audit::record('invoice.sent');

        $this->assertSame(AuditActor::SYSTEM, AuditEntry::query()->where('event', 'invoice.sent')->sole()->actor_type);
    }

    public function test_a_job_that_fails_is_recorded_with_only_the_class_of_the_exception(): void
    {
        config(['queue.default' => 'database']);

        dispatch(function (): void {
            throw new RuntimeException('secret-in-the-message');
        });

        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true, '--tries' => 1]);

        $entry = AuditEntry::query()->where('event', 'queue.job_failed')->sole();

        $this->assertSame(AuditActor::QUEUE, $entry->actor_type);
        $this->assertSame(RuntimeException::class, $entry->getProperty('exception'));
        $this->assertStringNotContainsString('secret-in-the-message', $entry->getRawOriginal('properties'));

        Audit::record('invoice.sent');

        $this->assertSame(AuditActor::SYSTEM, AuditEntry::query()->where('event', 'invoice.sent')->sole()->actor_type);
    }

    public function test_retention_is_cleaned_daily(): void
    {
        $clean = collect(app(Schedule::class)->events())->filter(fn (ScheduledTask $task): bool => str_contains((string) $task->command, 'activitylog:clean'));

        $this->assertCount(1, $clean);
        $this->assertSame('0 0 * * *', $clean->first()->expression);

        // Without it, the command stops to ask for confirmation in production.
        $this->assertStringEndsWith('activitylog:clean --force', $clean->first()->command);

        // Exactly as the scheduler will call it, on an old entry.
        $old = AuditEntry::factory()->create(['created_at' => now()->subDays(config()->integer('activitylog.clean_after_days') + 1)]);

        $this->assertSame(0, Artisan::call(Str::after($clean->first()->command, Application::formatCommandString(''))));
        $this->assertModelMissing($old);
    }

    public function test_keeping_entries_forever_schedules_no_clean(): void
    {
        config(['activitylog.clean_after_days' => 0]);

        $before = count(app(Schedule::class)->events());

        (new AuditServiceProvider($this->app))->boot();

        $this->assertCount($before, app(Schedule::class)->events());
    }

    private function task(string $description = 'A task'): ScheduledTask
    {
        return app(Schedule::class)->command('inspire')->description($description);
    }
}
