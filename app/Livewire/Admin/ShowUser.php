<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\SystemPermission;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ShowUser extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public User $user;

    public function mount(User $user): void
    {
        Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);

        $this->user = $user;
    }

    public function userInfolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->user)
            ->components([
                Section::make(__('admin.user_information'))
                    ->headerActions([
                        Action::make('impersonate')
                            ->label(__('admin.impersonate_user'))
                            ->icon('heroicon-o-finger-print')
                            ->color('gray')
                            ->url(fn (): string => route('users.impersonate', $this->user->id))
                            ->visible(fn (): bool => Auth::user()->canImpersonate() && $this->user->canBeImpersonated()),

                        Action::make('editProfile')
                            ->label(__('admin.edit'))
                            ->icon('heroicon-o-pencil')
                            ->fillForm(fn (User $record): array => [
                                'name' => $record->name,
                                'email' => $record->email,
                            ])
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('admin.name'))
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->label(__('admin.email'))
                                    ->email()
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->action(function (array $data, User $record): void {
                                $record->update($data);

                                Notification::make()
                                    ->title(__('admin.user_updated'))
                                    ->success()
                                    ->send();
                            })
                            ->authorize(fn (): bool => Gate::allows(SystemPermission::MANAGE_USERS->value)),
                    ])
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label(__('admin.name')),

                                TextEntry::make('email')
                                    ->label(__('admin.email'))
                                    ->icon('heroicon-o-envelope'),

                                TextEntry::make('system_role')
                                    ->label(__('admin.system_role'))
                                    ->badge()
                                    ->placeholder(__('admin.no_system_role')),

                                TextEntry::make('status')
                                    ->label(__('admin.status'))
                                    ->badge(),

                                TextEntry::make('created_at')
                                    ->label(__('admin.joined'))
                                    ->dateTime(),

                                TextEntry::make('last_login_at')
                                    ->label(__('admin.last_login'))
                                    ->dateTime()
                                    ->placeholder(__('admin.never')),
                            ]),
                    ]),
            ]);
    }

    public function render(): View
    {
        return view('livewire.admin.show-user')
            ->layout('layouts.admin');
    }
}
