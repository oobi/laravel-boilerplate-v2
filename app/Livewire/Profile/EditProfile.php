<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Livewire\Concerns\RequiresFullSession;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * "My profile" — the Profile tab of the self-service account area. Updates the
 * logged-in user's own name/email/photo by delegating to the app's registered
 * Fortify actions (same validation/rules a real Fortify update-profile request
 * would run). Password and two-factor management live on their own sibling tabs
 * ({@see EditPassword}, {@see TwoFactorAuthentication}). Open to any signed-in,
 * verified user — not admin-gated — so it renders in the neutral account
 * layout rather than the default admin shell.
 */
#[Layout('layouts.account')]
class EditProfile extends Component
{
    use RequiresFullSession;
    use WithFileUploads;

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    /** Asked for only when the email changes. */
    public string $current_password = '';

    public $photo = null;

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
            'current_password' => $this->current_password,
            'photo' => $this->photo,
        ], fn ($value): bool => ! is_null($value)));

        $this->reset('photo', 'current_password');

        Notification::make()
            ->title(__('admin.profile_updated'))
            ->success()
            ->send();
    }

    /** Whether the email field differs from the saved address, compared as the action stores it. */
    #[Computed]
    public function emailIsChanging(): bool
    {
        return User::normalizeEmail($this->email) !== Auth::user()->email;
    }

    public function removeProfilePhoto(): void
    {
        Auth::user()->deleteProfilePhoto();

        Notification::make()
            ->title(__('admin.profile_updated'))
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('livewire.profile.edit-profile');
    }
}
