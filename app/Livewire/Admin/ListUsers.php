<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\UserStatus;
use App\Livewire\Concerns\ManagesTrashedRecords;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
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

    public function mount(): void
    {
        Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query())
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

                Tables\Columns\TextColumn::make('system_role')
                    ->label(__('admin.system_role'))
                    ->badge()
                    ->placeholder(__('admin.no_system_role'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label(__('admin.last_login'))
                    ->dateTime()
                    ->placeholder(__('admin.never'))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('system_role')
                    ->label(__('admin.system_role'))
                    ->options(SystemRole::options())
                    ->modifyFormFieldUsing(fn ($field) => $field->live(debounce: '1ms')),

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

                TrashedFilter::make(),
            ])
            ->deferFilters(false)
            ->recordActions([
                ActionGroup::make([
                    Action::make('view')
                        ->label(__('admin.view'))
                        ->icon('heroicon-o-eye')
                        ->url(fn (User $record): string => route('users.show', $record))
                        ->hidden(fn (User $record): bool => $record->trashed()),

                    Action::make('edit')
                        ->label(__('admin.edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->url(fn (User $record): string => route('users.edit', $record))
                        ->hidden(fn (User $record): bool => $record->trashed()),

                    Action::make('toggleActive')
                        ->label(fn (User $record): string => $record->active
                            ? __('admin.deactivate')
                            : __('admin.activate'))
                        ->icon(fn (User $record): string => $record->active ? 'heroicon-o-pause-circle' : 'heroicon-o-check-circle')
                        ->color(fn (User $record): string => $record->active ? 'warning' : 'success')
                        ->requiresConfirmation()
                        ->action(function (User $record): void {
                            Gate::authorize(SystemPermission::SUSPEND_USERS->value);

                            abort_if($record->id === Auth::id(), 403);

                            $record->update(['active' => ! $record->active]);

                            Notification::make()
                                ->title($record->active
                                    ? __('admin.user_activated')
                                    : __('admin.user_deactivated'))
                                ->success()
                                ->send();
                        })
                        ->hidden(fn (User $record): bool => $record->trashed() || $record->id === Auth::id()),

                    DeleteAction::make()
                        ->authorize(fn (): bool => Gate::allows(SystemPermission::MANAGE_USERS->value))
                        ->hidden(fn (User $record): bool => $record->id === Auth::id()),

                    RestoreAction::make()
                        ->authorize(fn (): bool => Gate::allows(SystemPermission::MANAGE_USERS->value)),

                    ForceDeleteAction::make()
                        ->authorize(fn (): bool => Gate::allows(SystemPermission::MANAGE_USERS->value)),
                ]),
            ])
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

    protected function emptyTrashPermission(): ?string
    {
        return SystemPermission::MANAGE_USERS->value;
    }

    public function render(): View
    {
        return view('livewire.admin.list-users')
            ->layout('layouts.admin');
    }
}
