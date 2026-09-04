<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserObserver
{
    /**
     * Force a logout everywhere the moment a user is deactivated: their
     * database sessions are destroyed (ends any already-open browser tab on
     * its next request) and their remember-me token is cleared (stops a
     * "remember me" cookie from silently re-authenticating them).
     */
    public function updated(User $user): void
    {
        if (! $user->wasChanged('active') || $user->active) {
            return;
        }

        DB::table('sessions')->where('user_id', $user->id)->delete();

        $user->forceFill(['remember_token' => null])->saveQuietly();
    }
}
