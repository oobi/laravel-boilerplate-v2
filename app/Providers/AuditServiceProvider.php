<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\SystemPermission;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditActor;
use App\Support\Audit\AuditDisplay;
use App\Support\Audit\AuthEventSubscriber;
use App\Support\Audit\SystemEventSubscriber;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Log\Context\Repository;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Lab404\Impersonate\Services\ImpersonateManager;

/**
 * Wires the audit log into the framework. See docs/boilerplate/audit-log.md.
 * AUDIT_ENABLED=false skips all of it.
 */
class AuditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! config('activitylog.enabled')) {
            return;
        }

        $this->rememberWhoDispatchedAJob();
        $this->markWhileAJobRuns();

        Event::subscribe(AuthEventSubscriber::class);
        Event::subscribe(SystemEventSubscriber::class);

        $this->scheduleRetentionClean();
        $this->describeCoreSubjects();
    }

    /** A link only for a viewer who may open the page behind it. */
    private function describeCoreSubjects(): void
    {
        AuditDisplay::registerSubject(User::class, fn (User $user): array => [
            'label' => $user->name,
            'url' => Gate::allows(SystemPermission::VIEW_USERS->value) ? route('users.show', $user) : null,
        ]);

        AuditDisplay::registerSubject(Role::class, fn (Role $role): array => [
            'label' => $role->name,
            'url' => Gate::allows(SystemPermission::MANAGE_ROLES->value) ? route('roles.edit', $role) : null,
        ]);
    }

    /**
     * A queued job has no signed-in user or session, so carry the person who
     * dispatched it, and anyone impersonating them, with the job. A job dispatched
     * by a job keeps the original people.
     */
    private function rememberWhoDispatchedAJob(): void
    {
        Context::dehydrating(function (Repository $context): void {
            $userId = Auth::id();

            if ($userId === null || $context->hasHidden(AuditActor::CONTEXT_DISPATCHED_BY)) {
                return;
            }

            $context->addHidden(AuditActor::CONTEXT_DISPATCHED_BY, $userId);

            $impersonatorId = app(ImpersonateManager::class)->getImpersonatorId();

            if ($impersonatorId !== null) {
                $context->addHidden(AuditActor::CONTEXT_IMPERSONATED_BY, $impersonatorId);
            }
        });
    }

    /**
     * So an entry from a job nobody dispatched, such as a webhook's, still says
     * `queue`. A count, because a sync job can run inside another job.
     */
    private function markWhileAJobRuns(): void
    {
        Event::listen(JobProcessing::class, fn () => Context::addHidden(
            AuditActor::CONTEXT_IN_JOB,
            Context::getHidden(AuditActor::CONTEXT_IN_JOB, 0) + 1,
        ));

        Event::listen([JobProcessed::class, JobFailed::class], function (): void {
            $stillRunning = Context::getHidden(AuditActor::CONTEXT_IN_JOB, 0) - 1;

            $stillRunning > 0
                ? Context::addHidden(AuditActor::CONTEXT_IN_JOB, $stillRunning)
                : Context::forgetHidden(AuditActor::CONTEXT_IN_JOB);
        });
    }

    /** 0 keeps entries forever, and spatie's command refuses it. */
    private function scheduleRetentionClean(): void
    {
        if (config()->integer('activitylog.clean_after_days') <= 0) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // As text: the array form compiles to --force='1', which the command refuses.
            $schedule->command('activitylog:clean --force')->daily();
        });
    }
}
