<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\SystemPermission;
use App\Enums\SystemRole;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Standalone Filament table for managing users, rendered inside the admin
 * shell layout (not a full Filament panel). Its own <x-table-header> (see
 * the Blade view) replaces Filament's built-in header/search/filter chrome,
 * which is hidden via CSS — see resources/css/theme/components/ui/admin-table.css.
 */
class ListUsers extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

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
                Tables\Columns\TextColumn::make('list_name')
                    ->label(__('admin.name'))
                    ->description(fn (User $record): string => $record->email)
                    ->searchable(['first_name', 'last_name', 'email']),

                Tables\Columns\TextColumn::make('system_role')
                    ->label(__('admin.system_role'))
                    ->badge()
                    ->placeholder(__('admin.no_system_role')),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->getStateUsing(fn (User $record): string => self::statusFor($record))
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'info',
                        'inactive' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.joined'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('system_role')
                    ->label(__('admin.system_role'))
                    ->options(SystemRole::options())
                    ->modifyFormFieldUsing(fn ($field) => $field->live(debounce: '1ms')),

                Tables\Filters\SelectFilter::make('active')
                    ->label(__('admin.status'))
                    ->options([
                        '1' => __('admin.active'),
                        '0' => __('admin.inactive'),
                    ])
                    ->modifyFormFieldUsing(fn ($field) => $field->live(debounce: '1ms')),

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

    /** Shared with ShowUser's infolist so both screens agree on the derived status. */
    public static function statusFor(User $record): string
    {
        if (! $record->active) {
            return 'inactive';
        }

        if (! $record->hasVerifiedEmail()) {
            return 'pending';
        }

        return 'active';
    }

    public function activeUsersCount(): int
    {
        return User::query()->count();
    }

    public function trashedUsersCount(): int
    {
        return User::onlyTrashed()->count();
    }

    public function render(): View
    {
        return view('livewire.admin.list-users')
            ->layout('layouts.admin');
    }
}
