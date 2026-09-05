<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Roles;

use App\Support\Theme\DaisyColor;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/** Defines what a role can do at all — see .ai/rules/policies.md and AppServiceProvider's 'manage roles' gate. */
class ListRoles extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function mount(): void
    {
        Gate::authorize('manage roles');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Role::query()->withCount('permissions'))
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.role_name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label(__('admin.permissions'))
                    ->badge(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('edit')
                        ->label(__('admin.edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->url(fn (Role $record): string => route('roles.edit', $record)),

                    Action::make('delete')
                        ->label(__('admin.delete'))
                        ->icon('heroicon-o-trash')
                        ->color(DaisyColor::ERROR->toFilamentColor())
                        ->requiresConfirmation()
                        ->action(function (Role $record): void {
                            $record->delete();

                            Notification::make()
                                ->title(__('admin.role_deleted'))
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->searchPlaceholder(__('admin.search_placeholder'))
            ->emptyStateHeading(__('admin.no_roles_found'))
            ->paginated(config('pagination.page_sizes'))
            ->defaultPaginationPageOption(config('pagination.default_page_size'));
    }

    public function render(): View
    {
        return view('livewire.admin.roles.list-roles');
    }
}
