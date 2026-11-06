<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Audit\AuditActor;
use Illuminate\Log\Context\Repository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
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
}
