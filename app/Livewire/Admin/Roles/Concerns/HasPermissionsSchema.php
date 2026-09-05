<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Roles\Concerns;

use App\Enums\SystemPermission;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Builds a Shield-style permissions form: one tab per SystemPermission
 * category, each with a "select all" toggle and a checkbox list. State is
 * split into one `permissions_{category}` field per tab (rather than a
 * single flat `permissions` field) so that toggling one tab's checkboxes
 * can't clobber another tab's selections; resolvePermissionsFromState()
 * flattens the per-tab fields back into one list for saving.
 */
trait HasPermissionsSchema
{
    protected function permissionsTabs(): Tabs
    {
        return Tabs::make('permissions')
            ->columnSpanFull()
            ->tabs(collect(SystemPermission::byCategory())
                ->map(fn (array $permissions, string $category): Tab => $this->permissionsTab($category, $permissions))
                ->values()
                ->all());
    }

    /** @param list<SystemPermission> $permissions */
    protected function permissionsTab(string $category, array $permissions): Tab
    {
        $field = $this->permissionsFieldName($category);
        $values = collect($permissions)->map(fn (SystemPermission $permission): string => $permission->value)->all();

        return Tab::make($category)
            ->badge(fn (Get $get): string => count($get($field) ?? []).'/'.count($values))
            ->schema([
                Toggle::make("{$field}_select_all")
                    ->label(__('admin.select_all'))
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(fn (bool $state, Set $set) => $set($field, $state ? $values : [])),

                CheckboxList::make($field)
                    ->hiddenLabel()
                    ->live()
                    ->options(collect($permissions)
                        ->mapWithKeys(fn (SystemPermission $permission): array => [$permission->value => $permission->label()])
                        ->all())
                    ->columns(2),
            ]);
    }

    protected function permissionsFieldName(string $category): string
    {
        return 'permissions_'.Str::slug($category, '_');
    }

    /** @return array<string, list<string>> */
    protected function permissionsStateForRole(?Role $role): array
    {
        $assigned = $role?->permissions->pluck('name')->all() ?? [];

        return collect(SystemPermission::byCategory())
            ->mapWithKeys(function (array $permissions, string $category) use ($assigned): array {
                $values = collect($permissions)->map(fn (SystemPermission $permission): string => $permission->value)->all();

                return [$this->permissionsFieldName($category) => array_values(array_intersect($values, $assigned))];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function resolvePermissionsFromState(array $data): array
    {
        return collect(SystemPermission::byCategory())
            ->keys()
            ->flatMap(fn (string $category): array => $data[$this->permissionsFieldName($category)] ?? [])
            ->unique()
            ->values()
            ->all();
    }
}
