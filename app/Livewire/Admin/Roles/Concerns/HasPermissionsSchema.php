<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Roles\Concerns;

use App\Enums\SystemPermission;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Builds a scrolling-list permissions form: one bordered row per
 * SystemPermission category (label + "select all" checkbox on the left,
 * individual permission checkboxes on the right), stacked inside a single
 * card. State is split into one `permissions_{category}` field per category
 * (rather than a single flat `permissions` field) so ticking one category's
 * checkboxes can't clobber another's selections; resolvePermissionsFromState()
 * flattens the per-category fields back into one list for saving.
 */
trait HasPermissionsSchema
{
    /** @return list<Component> */
    protected function permissionsSchema(): array
    {
        $categories = SystemPermission::byCategory();
        $lastCategory = array_key_last($categories);

        return collect($categories)
            ->map(fn (array $permissions, string $category): Grid => $this->permissionsRow($category, $permissions, $category === $lastCategory))
            ->values()
            ->all();
    }

    /** @param list<SystemPermission> $permissions */
    protected function permissionsRow(string $category, array $permissions, bool $isLast): Grid
    {
        $field = $this->permissionsFieldName($category);
        $selectAllField = "{$field}_select_all";
        $values = collect($permissions)->map(fn (SystemPermission $permission): string => $permission->value)->all();

        return Grid::make(['default' => 4])
            ->extraAttributes([
                'class' => 'pb-6 mb-6'.($isLast ? '' : ' border-b border-base-300'),
            ])
            ->schema([
                Group::make([
                    Text::make($category)->weight(FontWeight::SemiBold),

                    Checkbox::make($selectAllField)
                        ->label(__('admin.select_all'))
                        ->dehydrated(false)
                        ->live()
                        ->afterStateUpdated(fn (bool $state, Set $set) => $set($field, $state ? $values : [])),
                ])->columnSpan(1),

                CheckboxList::make($field)
                    ->hiddenLabel()
                    ->live()
                    ->afterStateUpdated(fn (?array $state, Set $set) => $set($selectAllField, count($state ?? []) === count($values)))
                    ->options(collect($permissions)
                        ->mapWithKeys(fn (SystemPermission $permission): array => [$permission->value => $permission->label()])
                        ->all())
                    ->columns(2)
                    ->columnSpan(3),
            ]);
    }

    protected function permissionsFieldName(string $category): string
    {
        return 'permissions_'.Str::slug($category, '_');
    }

    /** @return array<string, list<string>|bool> */
    protected function permissionsStateForRole(?Role $role): array
    {
        $assigned = $role?->permissions->pluck('name')->all() ?? [];

        return collect(SystemPermission::byCategory())
            ->mapWithKeys(function (array $permissions, string $category) use ($assigned): array {
                $field = $this->permissionsFieldName($category);
                $values = collect($permissions)->map(fn (SystemPermission $permission): string => $permission->value)->all();
                $selected = array_values(array_intersect($values, $assigned));

                return [
                    $field => $selected,
                    "{$field}_select_all" => count($selected) === count($values),
                ];
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
