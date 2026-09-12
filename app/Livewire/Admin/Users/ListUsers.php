<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Users;

use App\Actions\Users\ToggleUserActive;
use App\Enums\SystemPermission;
use App\Enums\UserAbility;
use App\Enums\UserStatus;
use App\Livewire\Concerns\ManagesTrashedRecords;
use App\Models\Role;
use App\Models\User;
use App\Support\Theme\DaisyColor;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Standalone Filament table for managing users, rendered inside the admin
 * shell layout (not a full Filament panel). Its own <x-table-header> (see
 * the Blade view) replaces Filament's built-in header/search/filter chrome,
 * which is hidden via CSS — see resources/css/theme/components/ui/filament-table.css.
 */
class ListUsers extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
    use ManagesTrashedRecords;

    private const SUPER_ADMIN_FILTER_VALUE = '__super_admin__';

    private const NO_ROLE_FILTER_VALUE = '__no_role__';

    public function mount(): void
    {
        Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->with('roles'))
            ->defaultSort('last_name')
            ->columns([
                Tables\Columns\ViewColumn::make('user_composite')
                    ->label(__('admin.user'))
                    ->view('filament.tables.columns.user-column')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('last_name', $direction);
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    }),

                Tables\Columns\TextColumn::make('role_summary')
                    ->label(__('admin.roles'))
                    ->getStateUsing(fn (User $record): array => $record->is_super_admin
                        ? [__('admin.super_admin')]
                        : $record->roles->pluck('name')->all())
                    ->placeholder(__('admin.no_roles'))
                    ->badge()
                    ->color(fn (string $state, User $record): string => $state === __('admin.super_admin')
                        ? DaisyColor::ERROR->toFilamentColor()
                        : $record->roles->firstWhere('name', $state)?->badgeColor()->toFilamentColor() ?? DaisyColor::NEUTRAL->toFilamentColor()),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge(),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label(__('admin.last_login'))
                    ->dateTime()
                    ->placeholder(__('admin.never'))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options(UserStatus::options())
                    ->modifyFormFieldUsing(fn ($field) => $field->live(debounce: '1ms'))
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            UserStatus::ACTIVE->value => $query->where('active', true)->whereNotNull('email_verified_at'),
                            UserStatus::PENDING->value => $query->where('active', true)->whereNull('email_verified_at'),
                            UserStatus::INACTIVE->value => $query->where('active', false),
                            default => $query,
                        };
                    }),

                Tables\Filters\SelectFilter::make('role')
                    ->label(__('admin.roles'))
                    ->options(fn (): array => $this->roleFilterOptions())
                    ->modifyFormFieldUsing(fn ($field) => $field->live(debounce: '1ms'))
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            null => $query,
                            self::SUPER_ADMIN_FILTER_VALUE => $query->where('is_super_admin', true),
                            self::NO_ROLE_FILTER_VALUE => $query->where('is_super_admin', false)->doesntHave('roles'),
                            default => $query->whereRelation('roles', 'name', $data['value']),
                        };
                    }),

                TrashedFilter::make(),
            ])
            ->deferFilters(false)
            ->recordActions([
                ActionGroup::make([
                    Action::make('view')
                        ->label(__('admin.view'))
                        ->icon('heroicon-o-eye')
                        ->url(fn (User $record): string => route('users.show', $record))
                        ->authorize(UserAbility::VIEW)
                        ->hidden(fn (User $record): bool => $record->trashed()),

                    Action::make('edit')
                        ->label(__('admin.edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->url(fn (User $record): string => route('users.edit', $record))
                        ->authorize(UserAbility::UPDATE)
                        ->hidden(fn (User $record): bool => $record->trashed()),

                    Action::make('impersonate')
                        ->label(__('admin.impersonate'))
                        ->icon('heroicon-o-finger-print')
                        ->color(DaisyColor::WARNING->toFilamentColor())
                        ->url(fn (User $record): string => route('users.impersonate', $record->id))
                        ->authorize(UserAbility::IMPERSONATE)
                        ->hidden(fn (User $record): bool => $record->trashed()),

                    Action::make('toggleActive')
                        ->label(fn (User $record): string => $record->active
                            ? __('admin.deactivate')
                            : __('admin.activate'))
                        ->icon(fn (User $record): string => $record->active ? 'heroicon-o-pause-circle' : 'heroicon-o-check-circle')
                        ->color(fn (User $record): string => $record->active ? DaisyColor::WARNING->toFilamentColor() : DaisyColor::SUCCESS->toFilamentColor())
                        ->requiresConfirmation()
                        ->modalDescription(fn (User $record): string => trans_choice(
                            $record->active ? 'admin.deactivate_confirm' : 'admin.activate_confirm',
                            1,
                            ['count' => 1],
                        ))
                        ->authorize(UserAbility::TOGGLE_ACTIVE)
                        ->action(fn (User $record) => app(ToggleUserActive::class)($record))
                        ->hidden(fn (User $record): bool => $record->trashed()),

                    DeleteAction::make()
                        ->modalDescription(trans_choice('admin.delete_confirm', 1, ['count' => 1]))
                        ->authorize(UserAbility::DELETE),

                    RestoreAction::make()
                        ->modalDescription(trans_choice('admin.restore_confirm', 1, ['count' => 1]))
                        ->authorize(UserAbility::RESTORE),

                    ForceDeleteAction::make()
                        ->authorize(UserAbility::FORCE_DELETE),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label(__('admin.activate'))
                        ->icon('heroicon-o-check-circle')
                        ->color(DaisyColor::SUCCESS->toFilamentColor())
                        ->requiresConfirmation()
                        ->modalHeading(__('admin.activate'))
                        ->modalDescription(fn (Collection $records): string => trans_choice('admin.activate_confirm', $records->count(), ['count' => $records->count()]))
                        ->authorizeIndividualRecords('toggleActive')
                        ->hidden(fn (): bool => $this->isViewingOnlyTrashed())
                        ->action(fn (Collection $records) => $this->setActiveForRecords($records, true))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('deactivate')
                        ->label(__('admin.deactivate'))
                        ->icon('heroicon-o-pause-circle')
                        ->color(DaisyColor::WARNING->toFilamentColor())
                        ->requiresConfirmation()
                        ->modalHeading(__('admin.deactivate'))
                        ->modalDescription(fn (Collection $records): string => trans_choice('admin.deactivate_confirm', $records->count(), ['count' => $records->count()]))
                        ->authorizeIndividualRecords('toggleActive')
                        ->hidden(fn (): bool => $this->isViewingOnlyTrashed())
                        ->action(fn (Collection $records) => $this->setActiveForRecords($records, false))
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make()
                        ->visible(fn (): bool => Gate::allows(SystemPermission::DELETE_USERS->value))
                        ->modalDescription(fn (Collection $records): string => trans_choice('admin.delete_confirm', $records->count(), ['count' => $records->count()]))
                        ->authorizeIndividualRecords('delete'),

                    RestoreBulkAction::make()
                        ->color(DaisyColor::INFO->toFilamentColor())
                        ->visible(fn (): bool => Gate::allows(SystemPermission::DELETE_USERS->value))
                        ->modalDescription(fn (Collection $records): string => trans_choice('admin.restore_confirm', $records->count(), ['count' => $records->count()]))
                        ->authorizeIndividualRecords('restore'),
                ]),
            ])
            // Super Admin and your own record cannot be included in bulk action
            ->checkIfRecordIsSelectableUsing(
                fn (User $record): bool => ! $record->isSuperAdmin() && $record->id !== Auth::id()
            )
            ->searchPlaceholder(__('admin.search_placeholder'))
            ->emptyStateHeading(__('admin.no_users_found'))
            ->paginated(config('pagination.page_sizes'))
            ->defaultPaginationPageOption(config('pagination.default_page_size'));
    }

    /** Never let "Empty Trash" force-delete the current admin's own trashed account. */
    protected function emptyTrashQuery(): Builder
    {
        return User::onlyTrashed()->where('id', '!=', Auth::id());
    }

    /**
     * True when the trashed filter is showing only-trashed records, where the
     * activate/deactivate bulk actions don't apply (mirrors the per-row
     * toggleActive action, which is hidden for trashed rows).
     */
    protected function isViewingOnlyTrashed(): bool
    {
        return ($this->tableFilters[$this->trashedFilterName()]['value'] ?? '') === '0';
    }

    /**
     * Flip the active flag for an already-authorized set of records (Filament
     * pre-filters the collection via authorizeIndividualRecords), surfacing a
     * count toast. Skips the toast entirely when nothing was authorized —
     * Filament reports the missing-authorization failure on its own.
     */
    protected function setActiveForRecords(Collection $records, bool $active): void
    {
        if ($records->isEmpty()) {
            return;
        }

        $records->each(fn (User $user) => $user->update(['active' => $active]));

        Notification::make()
            ->title($active
                ? __('admin.users_activated', ['count' => $records->count()])
                : __('admin.users_deactivated', ['count' => $records->count()]))
            ->success()
            ->send();
    }

    /** Options for the Blade view's role <x-table-filter-select>, mirroring the table filter above. */
    public function roleFilterOptions(): array
    {
        return [
            self::SUPER_ADMIN_FILTER_VALUE => __('admin.super_admin'),
            self::NO_ROLE_FILTER_VALUE => __('admin.no_roles'),
            ...Role::systemRoles()->orderBy('name')->pluck('name', 'name')->all(),
        ];
    }

    protected function emptyTrashPermission(): ?string
    {
        return SystemPermission::DELETE_USERS->value;
    }

    public function render(): View
    {
        return view('livewire.admin.users.list-users');
    }
}
