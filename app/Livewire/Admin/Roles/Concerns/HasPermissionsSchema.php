<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Roles\Concerns;

use App\Models\Role;
use App\Support\Roles\RoleScope;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\GridDirection;
use Filament\Support\Enums\VerticalAlignment;
use Illuminate\Support\Str;

/**
 * Builds a scrolling-list permissions form for the component's RoleScope: one
 * bordered row per permission category (label + "select all" checkbox on the
 * left, individual permission checkboxes on the right), stacked inside a
 * single card. State is split into one `permissions_{category}` field per
 * category (rather than a single flat `permissions` field) so ticking one
 * category's checkboxes can't clobber another's selections;
 * resolvePermissionsFromState() flattens the per-category fields back into
 * one list for saving. The vocabulary comes from roleScope()->permissions(),
 * so the same form serves every registered scope.
 */
trait HasPermissionsSchema
{
    /**
     * The last reconciled selection per category field, so an update can tell an
     * addition (pull in implied permissions) from a removal (drop the ones that
     * depended on it). Keyed by category field name; persisted across Livewire
     * round-trips like any public state.
     *
     * @var array<string, list<string>>
     */
    public array $permissionSnapshots = [];

    /** The scope whose vocabulary the form edits. */
    abstract protected function roleScope(): RoleScope;

    /** @return list<Component> */
    protected function permissionsSchema(): array
    {
        return collect($this->roleScope()->permissions())
            ->map(fn (array $options, string $category): Group => $this->permissionsRow($category, $options))
            ->values()
            ->all();
    }

    /**
     * One category block, styled like a table: a shaded header band whose
     * leading checkbox selects everything beneath it (no label needed — the
     * position says it, as a table's header checkbox does) followed by the
     * eyebrow heading, then the options in an auto-fitting grid whose
     * checkboxes share the band checkbox's left edge. See filament-forms.css.
     *
     * @param  array<string, string>  $options  permission name => label
     */
    protected function permissionsRow(string $category, array $options): Group
    {
        $field = $this->permissionsFieldName($category);
        $selectAllField = "{$field}_select_all";
        $values = array_keys($options);

        return Group::make([
            Flex::make([
                Checkbox::make($selectAllField)
                    ->hiddenLabel()
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(function (bool $state, Set $set) use ($field, $values): void {
                        $selected = $state ? $values : [];
                        $set($field, $selected);
                        $this->permissionSnapshots[$field] = $selected;
                    })
                    ->extraInputAttributes(fn (Checkbox $component): array => [
                        'aria-label' => __('admin.select_all_in', ['group' => $category]),
                        'title' => __('admin.select_all'),
                        // As in a table header: a dash while the category is only partly selected.
                        // Reactive via $wire so it tracks the live checkbox list without a round trip.
                        'x-effect' => sprintf(
                            "\$el.indeterminate = (() => { const selected = \$wire.get('%s') ?? []; return selected.length > 0 && selected.length < %d })()",
                            $component->getContainer()->getStatePath().'.'.$field,
                            count($values),
                        ),
                    ])
                    ->grow(false),

                View::make('filament.schemas.components.form-group-heading')
                    ->viewData(['heading' => $category])
                    ->grow(false),
            ])
                ->verticalAlignment(VerticalAlignment::Center)
                ->extraAttributes(['class' => 'ui-option-group-header']),

            CheckboxList::make($field)
                ->hiddenLabel()
                ->live()
                // Keep implications consistent: ticking Update auto-ticks View; unticking
                // View clears the Update/Manage that required it. Then sync "select all".
                ->afterStateUpdated(function (?array $state, Set $set) use ($field, $values, $selectAllField): void {
                    $reconciled = $this->reconcilePermissions($field, $state ?? [], $values);

                    $set($field, $reconciled);
                    $set($selectAllField, count($reconciled) === count($values));
                })
                ->options($options)
                // Fill left-to-right in vocabulary order (Filament's default fills column-first, which zigzags),
                // into auto-fitting columns (.ui-option-grid) rather than a fixed count stretched across the card.
                ->gridDirection(GridDirection::Row)
                ->extraAttributes(['class' => 'ui-option-grid']),
        ])
            ->extraAttributes(['class' => 'ui-option-group']);
    }

    protected function permissionsFieldName(string $category): string
    {
        return 'permissions_'.Str::slug($category, '_');
    }

    /**
     * Expand a selected-permission set to include everything those permissions
     * imply (see RoleScope::implications()). Single level — the scopes' maps
     * don't chain.
     *
     * @param  list<string>  $selected
     * @return list<string>
     */
    protected function applyImplications(array $selected): array
    {
        $implications = $this->roleScope()->implications();
        $expanded = $selected;

        foreach ($selected as $permission) {
            foreach ($implications[$permission] ?? [] as $implied) {
                $expanded[] = $implied;
            }
        }

        return array_values(array_unique($expanded));
    }

    /**
     * Keep a category's selection internally consistent after a change. Diffs
     * against the last snapshot: a permission that was just added pulls in
     * everything it implies; a permission that was just removed drops everything
     * that implied it (so unticking "View" clears the "Update"/"Manage" that
     * required it). Updates the snapshot for the next change.
     *
     * @param  list<string>  $new
     * @param  list<string>  $values  every permission in this category, in order
     * @return list<string>
     */
    protected function reconcilePermissions(string $field, array $new, array $values): array
    {
        $implications = $this->roleScope()->implications();
        $old = $this->permissionSnapshots[$field] ?? [];
        $result = $new;

        // Added → pull in what it implies.
        foreach (array_diff($new, $old) as $permission) {
            foreach ($implications[$permission] ?? [] as $implied) {
                $result[] = $implied;
            }
        }

        // Removed → drop everything that implied it.
        foreach (array_diff($old, $new) as $permission) {
            foreach ($implications as $dependent => $implied) {
                if (in_array($permission, $implied, true)) {
                    $result = array_diff($result, [$dependent]);
                }
            }
        }

        $result = array_values(array_intersect($values, array_unique($result)));
        $this->permissionSnapshots[$field] = $result;

        return $result;
    }

    /** @return array<string, list<string>|bool> */
    protected function permissionsStateForRole(?Role $role): array
    {
        $assigned = $this->applyImplications($role?->permissions->pluck('name')->all() ?? []);
        $state = [];

        foreach ($this->roleScope()->permissions() as $category => $options) {
            $field = $this->permissionsFieldName($category);
            $values = array_keys($options);
            $selected = array_values(array_intersect($values, $assigned));

            // Seed the snapshot so the first change can be diffed correctly.
            $this->permissionSnapshots[$field] = $selected;
            $state[$field] = $selected;
            $state["{$field}_select_all"] = count($selected) === count($values);
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function resolvePermissionsFromState(array $data): array
    {
        $selected = collect($this->roleScope()->permissions())
            ->keys()
            ->flatMap(fn (string $category): array => $data[$this->permissionsFieldName($category)] ?? [])
            ->all();

        // Store the implied permissions too, so the saved role matches the form.
        return $this->applyImplications($selected);
    }
}
