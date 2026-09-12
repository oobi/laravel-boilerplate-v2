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
use Illuminate\Support\HtmlString;
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
 *
 * An option another ticked option carries with it (RoleScope::implications())
 * is ticked along with it, disabled while the implier is on, and says so on
 * hover ("Included with …") — the constraint is explained, never a silent
 * block. A permission that doesn't apply in this environment
 * (RoleScope::unavailable()) isn't offered at all; if the role already holds
 * one, the save keeps it rather than stripping what it couldn't show.
 */
trait HasPermissionsSchema
{
    /** The scope whose vocabulary the form edits. */
    abstract protected function roleScope(): RoleScope;

    /** @return list<Component> */
    protected function permissionsSchema(): array
    {
        return collect($this->offeredPermissions())
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
        $implications = $this->roleScope()->implications();

        return Group::make([
            Flex::make([
                Checkbox::make($selectAllField)
                    ->hiddenLabel()
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(fn (bool $state, Set $set) => $set($field, $state ? $values : []))
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
                // Ticking Manage also ticks the View it carries; a tick that arrives
                // without its implied option (only possible by bypassing the disabled
                // control) is completed the same way. Then sync "select all".
                ->afterStateUpdated(function (?array $state, Set $set) use ($field, $values, $selectAllField): void {
                    $completed = array_values(array_intersect($values, $this->applyImplications($state ?? [])));

                    $set($field, $completed);
                    $set($selectAllField, count($completed) === count($values));
                })
                ->options($this->optionLabels($options))
                ->allowHtml()
                // Validate against the whole category, not just the enabled options:
                // an implied option is disabled on screen but legitimately held.
                ->in($values)
                ->disableOptionWhen(function (string $value, ?array $state) use ($implications): bool {
                    foreach ($state ?? [] as $selected) {
                        if (in_array($value, $implications[$selected] ?? [], true)) {
                            return true;
                        }
                    }

                    return false;
                })
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
     * The scope's vocabulary minus what doesn't apply here — what the form
     * offers. A category left empty by the filter is dropped.
     *
     * @return array<string, array<string, string>>
     */
    protected function offeredPermissions(): array
    {
        $unavailable = $this->roleScope()->unavailable();

        return collect($this->roleScope()->permissions())
            ->map(fn (array $options): array => array_diff_key($options, array_flip($unavailable)))
            ->filter()
            ->all();
    }

    /**
     * The option labels, with an option another option carries with it given a
     * hover title saying so ("Included with …") — the explanation is there on
     * demand without adding a line of text under the checkbox. Rendered as HTML
     * (the list is allowHtml()), so every label is escaped here.
     *
     * @param  array<string, string>  $options  permission name => label
     * @return array<string, HtmlString>
     */
    protected function optionLabels(array $options): array
    {
        $scope = $this->roleScope();
        $labels = collect($scope->permissions())->collapse();
        $includedWith = [];

        // An implier that isn't offered here can't be ticked, so it isn't a reason.
        $implications = array_diff_key($scope->implications(), array_flip($scope->unavailable()));

        foreach ($implications as $implier => $implied) {
            foreach (array_intersect($implied, array_keys($options)) as $permission) {
                $includedWith[$permission][] = $labels[$implier] ?? $implier;
            }
        }

        return collect($options)
            ->map(function (string $label, string $value) use ($includedWith): HtmlString {
                if (! isset($includedWith[$value])) {
                    return new HtmlString(e($label));
                }

                $title = __('admin.included_with', ['permissions' => implode(', ', $includedWith[$value])]);

                return new HtmlString(sprintf('<span title="%s">%s</span>', e($title), e($label)));
            })
            ->all();
    }

    /**
     * Expand a selection to include everything the selected permissions carry
     * with them (RoleScope::implications()). Single level — the maps don't chain.
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

    /** @return array<string, list<string>|bool> */
    protected function permissionsStateForRole(?Role $role): array
    {
        // A held Manage shows its View ticked too, whether or not the row was stored.
        $assigned = $this->applyImplications($role?->permissions->pluck('name')->all() ?? []);
        $state = [];

        foreach ($this->offeredPermissions() as $category => $options) {
            $field = $this->permissionsFieldName($category);
            $values = array_keys($options);
            $selected = array_values(array_intersect($values, $assigned));

            $state[$field] = $selected;
            $state["{$field}_select_all"] = $selected !== [] && count($selected) === count($values);
        }

        return $state;
    }

    /**
     * What the form shows, flattened — implied options are in the state, ticked
     * alongside their implier, and so are stored — plus anything the role holds
     * that the form couldn't offer (RoleScope::unavailable()), so a save never
     * strips a grant it didn't show.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function resolvePermissionsFromState(array $data, ?Role $role = null): array
    {
        $shown = collect($this->offeredPermissions())
            ->keys()
            ->flatMap(fn (string $category): array => $data[$this->permissionsFieldName($category)] ?? []);

        $kept = collect($role?->permissions->pluck('name') ?? [])
            ->intersect($this->roleScope()->unavailable());

        return $shown->merge($kept)->unique()->values()->all();
    }
}
