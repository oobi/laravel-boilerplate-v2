<?php

declare(strict_types=1);

namespace App\Support\Audit;

use Illuminate\Console\Application;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Scheduling\Event as ScheduledTask;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Str;

/**
 * Scheduled tasks and failed jobs. Only the exception's class is kept, since a
 * message can quote the data it failed on. An audit failure never stops a task.
 */
class SystemEventSubscriber
{
    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            ScheduledTaskFinished::class => 'onTaskFinished',
            ScheduledBackgroundTaskFinished::class => 'onBackgroundTaskFinished',
            ScheduledTaskFailed::class => 'onTaskFailed',
            ScheduledTaskSkipped::class => 'onTaskSkipped',
            JobFailed::class => 'onJobFailed',
        ];
    }

    /**
     * A background task has only started: ScheduledBackgroundTaskFinished reports
     * it. A foreground task exiting non-zero goes on to fire ScheduledTaskFailed.
     */
    public function onTaskFinished(ScheduledTaskFinished $event): void
    {
        if (! $event->task->runInBackground && (int) $event->task->exitCode === 0) {
            $this->recordRoutine('schedule.task_finished', $event->task, ['runtime_seconds' => $event->runtime]);
        }
    }

    /** The one place a background task's exit code is seen. */
    public function onBackgroundTaskFinished(ScheduledBackgroundTaskFinished $event): void
    {
        if ((int) $event->task->exitCode === 0) {
            $this->recordRoutine('schedule.task_finished', $event->task);

            return;
        }

        $this->recordTask('schedule.task_failed', $event->task, ['exit_code' => $event->task->exitCode]);
    }

    public function onTaskFailed(ScheduledTaskFailed $event): void
    {
        $this->recordTask('schedule.task_failed', $event->task, [
            'exception' => $event->exception::class,
            ...((int) $event->task->exitCode !== 0 ? ['exit_code' => $event->task->exitCode] : []),
        ]);
    }

    public function onTaskSkipped(ScheduledTaskSkipped $event): void
    {
        $this->recordRoutine('schedule.task_skipped', $event->task);
    }

    public function onJobFailed(JobFailed $event): void
    {
        Audit::as(AuditActor::QUEUE, fn () => Audit::attempt('queue.job_failed', properties: [
            'job' => $event->job->resolveName(),
            'queue' => $event->job->getQueue(),
            'connection' => $event->connectionName,
            'exception' => $event->exception::class,
        ], category: AuditCategory::SYSTEM));
    }

    /**
     * What a task does on most runs: finishing or being skipped. A frequent task
     * can make this noisy, so it has an off switch. A failure doesn't.
     *
     * @param  array<string, mixed>  $properties
     */
    private function recordRoutine(string $event, ScheduledTask $task, array $properties = []): void
    {
        if (config()->boolean('audit.log_schedule_routine')) {
            $this->recordTask($event, $task, $properties);
        }
    }

    /** @param  array<string, mixed>  $properties */
    private function recordTask(string $event, ScheduledTask $task, array $properties = []): void
    {
        Audit::as(AuditActor::SCHEDULER, fn () => Audit::attempt(
            $event,
            properties: ['task' => $this->nameOf($task), ...$properties],
            category: AuditCategory::SYSTEM,
        ));
    }

    /**
     * The task's description, or an artisan command's name. Never its arguments or
     * any other shell text, which can hold a key or password anywhere (quoted
     * values, paths, URLs), and redaction only sees property names. To name a
     * shell task in the log, give it a ->description().
     */
    private function nameOf(ScheduledTask $task): string
    {
        if (filled($task->description) || $task->command === null) {
            return $task->getSummaryForDisplay();
        }

        $artisan = Application::formatCommandString('');
        $firstWord = Str::before(Str::after($task->command, $artisan), ' ');

        // An option can come before the command's name, and is an argument like any other.
        return str_starts_with($task->command, $artisan) && ! str_starts_with($firstWord, '-')
            ? $firstWord
            : 'Shell command';
    }
}
