<?php

declare(strict_types=1);

namespace Concise\Teams\Support;

use Closure;
use Concise\Teams\Models\Team;
use Filament\Forms\Components\Select;

/**
 * The team-role picker every members form shares, so single-vs-multiple roles
 * per member (config `teams.multiple_roles_per_member`) is decided in one
 * place: a plain select when one, a multi-select when many. The field is
 * always named `roles`; read it back with TeamRoleField::selected($data).
 */
final class TeamRoleField
{
    /**
     * @param  (Closure(string): bool)|null  $assignable  whether the viewer may give or take away a role (by name); the rest are shown but can't be picked
     */
    public static function make(?string $label = null, ?Closure $assignable = null): Select
    {
        $multiple = Team::allowsMultipleRoles();

        return Select::make('roles')
            ->label($label ?? team_trans($multiple ? 'members.roles' : 'members.role'))
            ->options(fn (): array => Team::availableRoles()->orderBy('name')->pluck('name', 'name')->all())
            ->disableOptionWhen(fn (string $value): bool => $assignable !== null && ! $assignable($value))
            ->multiple($multiple)
            ->native(! $multiple)
            ->placeholder(team_trans($multiple ? 'members.no_roles' : 'members.no_role'));
    }

    /**
     * The chosen role names from submitted form data, in either mode.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function selected(array $data): array
    {
        return array_values(array_filter((array) ($data['roles'] ?? [])));
    }
}
