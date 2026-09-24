<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

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

        $this->purgeDatabaseSessions($user);

        $user->forceFill(['remember_token' => null])->saveQuietly();
    }

    /**
     * Permanent deletion removes the row but not the uploaded file, so clean the
     * profile photo from storage here — the one place both individual force-delete
     * and bulk "empty trash" (which force-deletes each record so this fires) share.
     * Failure is reported, never thrown: one unreadable file must not abort a purge.
     */
    public function forceDeleted(User $user): void
    {
        try {
            $user->deleteProfilePhotoFile();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Only the database session driver keeps sessions somewhere we can sweep;
     * file/redis/array installs rely on request-time middleware instead.
     */
    private function purgeDatabaseSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->delete();
    }
}
