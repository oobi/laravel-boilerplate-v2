<?php

declare(strict_types=1);

namespace App\Livewire;

use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Livewire\Component;

/**
 * Self-service "my profile" screen — updates the logged-in user's own name/
 * email/password by delegating to the app's registered Fortify actions
 * (same validation/rules a real Fortify update-profile request would run).
 */
class EditProfile extends Component
{
    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function updateProfileInformation(UpdatesUserProfileInformation $updater): void
    {
        $updater->update(Auth::user(), [
            'name' => $this->name,
            'email' => $this->email,
        ]);

        Notification::make()
            ->title(__('admin.profile_updated'))
            ->success()
            ->send();
    }

    public function updatePassword(UpdatesUserPasswords $updater): void
    {
        $updater->update(Auth::user(), [
            'current_password' => $this->current_password,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        Notification::make()
            ->title(__('admin.password_updated'))
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('livewire.edit-profile')
            ->layout('layouts.admin');
    }
}
