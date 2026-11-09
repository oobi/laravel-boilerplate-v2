<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\Role;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Audit\AuditCategory;
use Illuminate\Database\Seeder;

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
            $daysAgo = 20 - ($index * 3);

            Audit::record('auth.login', $person, ['remember' => false], category: AuditCategory::AUTH, by: $person, at: now()->subDays($daysAgo));
            Audit::record('auth.login_failed', $person, ['identifier' => $person->email], category: AuditCategory::AUTH, at: now()->subDays($daysAgo)->addHour());
            Audit::record('user.password_set_by_admin', $person, category: AuditCategory::ACCESS, by: $admin, at: now()->subDays($daysAgo)->addHours(2));

            if ($role !== null) {
                Audit::record('user.roles_changed', $person, ['before' => [], 'after' => [$role->name]], category: AuditCategory::ACCESS, by: $admin, at: now()->subDays($daysAgo)->addHours(3));
            }

            Audit::record('impersonation.started', $person, category: AuditCategory::ACCESS, by: $admin, at: now()->subDays($daysAgo)->addHours(4));
            Audit::record('impersonation.left', $person, category: AuditCategory::ACCESS, by: $admin, at: now()->subDays($daysAgo)->addHours(5));
        });

        Audit::record('schedule.task_finished', properties: ['task' => 'activitylog:clean', 'runtime_seconds' => 0.4], category: AuditCategory::SYSTEM, at: now()->subDay());
        Audit::record('schedule.task_failed', properties: ['task' => 'reports:send', 'exit_code' => 1], category: AuditCategory::SYSTEM, at: now()->subHours(6));
        Audit::record('queue.job_failed', properties: ['job' => 'App\Jobs\SendReport', 'exception' => 'RuntimeException'], category: AuditCategory::SYSTEM, at: now()->subHours(3));
    }
}
