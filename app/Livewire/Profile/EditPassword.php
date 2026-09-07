<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Livewire\Component;

/**
 * "Password" — the Password tab of the self-service account area. Two-factor
 * authentication lives on its own sibling tab ({@see TwoFactorAuthentication})
 * so neither pushes the other down on small screens.
 */
class EditPassword extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

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
        return view('livewire.profile.edit-password')
            ->layout('layouts.public');
    }
}
