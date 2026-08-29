<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\SystemPermission;
use App\Models\User;
use App\Support\Panels\PanelRegistry;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Notifications\Notification;
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
        $components = PanelRegistry::formSections('users.edit', $this->user, Auth::user())
            ->flatMap(fn ($section): array => $section->components($this->user))
            ->all();

        return $schema
            ->components($components)
            ->statePath('data');
    }

    public function save(): void
    {
        Gate::authorize(SystemPermission::MANAGE_USERS->value);

        // Disabled fields (e.g. a self-edit's role/active toggle) are excluded from
        // getState() by Filament, so this trusts whatever the registered panels expose.
        $this->user->update($this->form->getState());

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
