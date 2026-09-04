<?php

declare(strict_types=1);

namespace App\Livewire;

use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Self-service "my profile" screen — updates the logged-in user's own name/
 * email/password/photo by delegating to the app's registered Fortify actions
 * (same validation/rules a real Fortify update-profile request would run).
 */
class EditProfile extends Component
{
    use WithFileUploads;

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public $photo = null;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->first_name = $user->first_name;
        $this->last_name = $user->last_name;
        $this->email = $user->email;
    }

    public function updateProfileInformation(UpdatesUserProfileInformation $updater): void
    {
        $updater->update(Auth::user(), array_filter([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'photo' => $this->photo,
        ], fn ($value): bool => ! is_null($value)));

        $this->reset('photo');

        Notification::make()
            ->title(__('admin.profile_updated'))
            ->success()
            ->send();
    }

    public function removeProfilePhoto(): void
    {
        Auth::user()->deleteProfilePhoto();

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
            ->layout('layouts.public');
    }
}
