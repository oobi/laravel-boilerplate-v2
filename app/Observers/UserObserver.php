<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Notifications\Auth\EmailAddressChanged;
use App\Support\Audit\Audit;
use App\Support\Audit\AuditCategory;
use App\Support\UserDeletionGuards;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

class UserObserver
{
    /** A user can start out as a super admin (bp:make-admin). */
    public function created(User $user): void
    {
        if ($user->is_super_admin) {
            Audit::record('user.super_admin_granted', $user, ['before' => false, 'after' => true], category: AuditCategory::ACCESS);
        }
    }

    /**
     * Refuse a deletion an add-on blocks (UserDeletionGuards), whatever the
     * path: the Users screen, a bulk action, Empty trash or code.
     *
     * @throws AuthorizationException
     */
    public function deleting(User $user): void
    {
        $reason = UserDeletionGuards::reasonFor($user, $user->isForceDeleting());

        if ($reason !== null) {
            throw new AuthorizationException($reason);
        }
    }

    /**
     * Force a logout everywhere the moment a user is deactivated: their
     * database sessions are destroyed (ends any already-open browser tab on
     * its next request) and their remember-me token is cleared (stops a
     * "remember me" cookie from silently re-authenticating them).
     */
    public function updated(User $user): void
    {
        if ($user->wasChanged('email')) {
            $this->tellPreviousAddress($user);
        }

        $this->recordAccessChanges($user);

        if (! $user->wasChanged('active') || $user->active) {
            return;
        }

        $this->purgeDatabaseSessions($user);

        $user->forceFill(['remember_token' => null])->saveQuietly();
    }

    /** Named entries, here so no path that changes these can skip them. They commit with the change. */
    private function recordAccessChanges(User $user): void
    {
        if ($user->wasChanged('is_super_admin')) {
            Audit::record(
                $user->is_super_admin ? 'user.super_admin_granted' : 'user.super_admin_revoked',
                $user,
                ['before' => ! $user->is_super_admin, 'after' => $user->is_super_admin],
                category: AuditCategory::ACCESS,
            );
        }

        if ($user->wasChanged('active')) {
            Audit::record(
                $user->active ? 'user.reactivated' : 'user.deactivated',
                $user,
                ['before' => ! $user->active, 'after' => $user->active],
                category: AuditCategory::ACCESS,
            );
        }
    }

    /**
     * Every email change, by the user or an admin, is told to the address it
     * replaced (#49); here so no path that changes the email can skip it.
     */
    private function tellPreviousAddress(User $user): void
    {
        $previous = $user->getOriginal('email');

        if (is_string($previous) && $previous !== '') {
            Notification::route('mail', $previous)->notify(new EmailAddressChanged($user->email));
        }
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
