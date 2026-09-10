<?php

declare(strict_types=1);

namespace Concise\Teams\Database\Seeders;

use App\Support\Theme\DaisyColor;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;
use Concise\Teams\TeamsServiceProvider;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the team-level permission vocabulary and the default team roles — the
 * team counterpart of PermissionSeeder. The defaults below are what a fresh
 * install starts with; edit this list to change a project's defaults.
 *
 * Roles are seeded ONLY when no team-scoped roles exist yet. After that the
 * database is the source of truth: rename, delete or replace them freely and
 * nothing here will resurrect them. Permissions, being the code-checked
 * vocabulary, are always ensured (idempotent).
 */
class TeamRolesSeeder extends Seeder
{
    /**
     * Role name => badge colour and the TeamPermission cases it holds.
     *
     * @return array<string, array{color: DaisyColor, permissions: list<TeamPermission>}>
     */
    protected function defaults(): array
    {
        return [
            'Team Admin' => [
                'color' => DaisyColor::ERROR,
                'permissions' => [
                    TeamPermission::MANAGE_MEMBERS,
                    TeamPermission::INVITE_MEMBERS,
                    TeamPermission::UPDATE_TEAM,
                ],
            ],
            'Member' => [
                'color' => DaisyColor::PRIMARY,
                'permissions' => [],
            ],
        ];
    }

    public function run(): void
    {
        if (! TeamsServiceProvider::isActive()) {
            $this->command?->warn('Teams tier is not active — skipping team roles.');

            return;
        }

        foreach (TeamPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        if (Team::availableRoles()->doesntExist()) {
            foreach ($this->defaults() as $name => $role) {
                Team::createRole($name, $role['permissions'], $role['color']);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
