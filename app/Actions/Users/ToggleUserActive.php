<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\UserAbility;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

/**
 * Flip a user's active flag, authorizing at the write boundary and surfacing a
 * confirmation toast. Shared by the Users list row action and the Show page's
 * Actions panel so the rule (and its wording) lives in exactly one place.
 */
class ToggleUserActive
{
    public function __invoke(User $user): void
    {
        Gate::authorize(UserAbility::TOGGLE_ACTIVE, $user);

        $user->update(['active' => ! $user->active]);

        Notification::make()
            ->title($user->active ? __('admin.user_activated') : __('admin.user_deactivated'))
            ->success()
            ->send();
    }
}
