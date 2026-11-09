<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\Role;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Audit\AuditCategory;
use App\Support\Audit\SessionTrail;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * A few weeks of the things an audit log is for, so the screen has something
 * worth reading in a demo. On top of what creating the demo users already logged.
 */
class AuditSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('is_super_admin', true)->first();
        $people = User::query()->where('is_super_admin', false)->limit(5)->get();

        if ($admin === null || $people->isEmpty()) {
            return;
        }

        $role = Role::systemRoles()->first();

        $people->each(function (User $person, int $index) use ($admin, $role): void {
            $day = now()->subDays(20 - ($index * 3));

            // Each person's own session: a mistyped password, then in.
            $this->inOneSession(function () use ($person, $day): void {
                Audit::record('auth.login_failed', $person, ['identifier' => $person->email], category: AuditCategory::AUTH, at: $day);
                Audit::record('auth.login', $person, ['remember' => false], category: AuditCategory::AUTH, by: $person, at: $day->copy()->addMinute());
            });

            // Later that day, an admin's session looking after them.
            $this->inOneSession(function () use ($admin, $person, $role, $day): void {
                Audit::record('auth.login', $admin, ['remember' => false], category: AuditCategory::AUTH, by: $admin, at: $day->copy()->addHours(2));
                Audit::record('user.password_set_by_admin', $person, category: AuditCategory::ACCESS, by: $admin, at: $day->copy()->addHours(2)->addMinutes(3));

                if ($role !== null) {
                    Audit::record('user.roles_changed', $person, ['before' => [], 'after' => [$role->name]], category: AuditCategory::ACCESS, by: $admin, at: $day->copy()->addHours(2)->addMinutes(5));
                }

                Audit::record('impersonation.started', $person, category: AuditCategory::ACCESS, by: $admin, at: $day->copy()->addHours(2)->addMinutes(8));
                Audit::record('impersonation.left', $person, category: AuditCategory::ACCESS, by: $admin, at: $day->copy()->addHours(2)->addMinutes(12));
                Audit::record('auth.logout', $admin, category: AuditCategory::AUTH, by: $admin, at: $day->copy()->addHours(2)->addMinutes(15));
            });
        });

        Audit::record('schedule.task_finished', properties: ['task' => 'activitylog:clean', 'runtime_seconds' => 0.4], category: AuditCategory::SYSTEM, at: now()->subDay());
        Audit::record('schedule.task_failed', properties: ['task' => 'reports:send', 'exit_code' => 1], category: AuditCategory::SYSTEM, at: now()->subHours(6));
        Audit::record('queue.job_failed', properties: ['job' => 'App\Jobs\SendReport', 'exception' => 'RuntimeException'], category: AuditCategory::SYSTEM, at: now()->subHours(3));
    }

    /** A seeder runs outside a browser, so it lends its entries a trail the way a queued job carries one. */
    private function inOneSession(Closure $record): void
    {
        Context::scope($record, hidden: [SessionTrail::CONTEXT => (string) Str::ulid()]);
    }
}
