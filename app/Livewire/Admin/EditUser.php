<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class EditUser extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public User $user;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(User $user): void
    {
        Gate::authorize(SystemPermission::MANAGE_USERS->value);

        $this->user = $user;

        $this->form->fill([
            'name' => $this->user->name,
            'email' => $this->user->email,
            'system_role' => $this->user->system_role?->value,
            'active' => $this->user->active,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $isSelf = $this->user->id === Auth::id();

        return $schema
            ->components([
                Section::make(__('admin.user_information'))
                    ->columns(2)
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

                        Forms\Components\Select::make('system_role')
                            ->label(__('admin.system_role'))
                            ->options(SystemRole::options())
                            ->placeholder(__('admin.no_system_role'))
                            ->native(false)
                            ->disabled($isSelf),

                        Forms\Components\Toggle::make('active')
                            ->label(__('admin.active'))
                            ->disabled($isSelf),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        Gate::authorize(SystemPermission::MANAGE_USERS->value);

        $data = $this->form->getState();
        $isSelf = $this->user->id === Auth::id();

        $update = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        // Nobody can change their own role/active state from this form, even a super admin.
        if (! $isSelf) {
            $update['system_role'] = $data['system_role'] ?: null;
            $update['active'] = $data['active'];
        }

        $this->user->update($update);

        Notification::make()
            ->title(__('admin.user_updated'))
            ->success()
            ->send();
    }

    public function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')
            ->label(__('admin.reset_password'))
            ->icon('heroicon-o-key')
            ->schema([
                Forms\Components\TextInput::make('password')
                    ->label(__('admin.new_password'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8)
                    ->confirmed(),
                Forms\Components\TextInput::make('password_confirmation')
                    ->label(__('admin.confirm_password'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->dehydrated(false),
            ])
            ->action(function (array $data): void {
                Gate::authorize(SystemPermission::MANAGE_USERS->value);

                $this->user->update(['password' => Hash::make($data['password'])]);

                Notification::make()
                    ->title(__('admin.password_reset_success'))
                    ->success()
                    ->send();
            });
    }

    public function render(): View
    {
        return view('livewire.admin.edit-user')
            ->layout('layouts.admin');
    }
}
