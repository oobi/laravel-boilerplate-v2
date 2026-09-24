<?php

declare(strict_types=1);

namespace App\Support\TwoFactor;

use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * The rules for mandatory two-factor authentication, in one place so the login
 * refusal, the remember-me guard, the nag banner and the admin Security panel
 * always agree.
 *
 * A user is subject (nagged) when they lack confirmed 2FA, have a verified
 * email, and hold a role flagged `requires_two_factor`, or are a super admin
 * while any role is flagged. The grace clock starts at their first login while
 * subject; once it runs out a subject user is refused at login until an admin
 * resets it. Super admins are never refused, so someone can always get in to
 * reset everyone else.
 */
final class GracePeriod
{
    private const REQUIRED_ROLES_CACHE_KEY = 'two_factor.required_roles';

    /**
     * Names of the roles whose members must set up 2FA. Cached because the
     * banner asks on every page; Role forgets it on every save and delete.
     *
     * @return list<string>
     */
    public static function requiredRoleNames(): array
    {
        return Cache::rememberForever(
            self::REQUIRED_ROLES_CACHE_KEY,
            fn (): array => Role::query()->requiringTwoFactor()->pluck('name')->all(),
        );
    }

    public static function forgetRequiredRoleNames(): void
    {
        Cache::forget(self::REQUIRED_ROLES_CACHE_KEY);
    }

    /** No flagged role means the feature is dormant for everyone, super admins included. */
    public static function subjectByRole(User $user): bool
    {
        $required = self::requiredRoleNames();

        if ($required === []) {
            return false;
        }

        return $user->isSuperAdmin() || $user->getRoleNames()->intersect($required)->isNotEmpty();
    }

    /** Whether the user is subject to the mandate at all, and so sees the nag. */
    public static function applies(User $user): bool
    {
        return config()->boolean('auth.two_factor.enforcement_enabled')
            && $user->two_factor_confirmed_at === null
            && $user->hasVerifiedEmail()
            && self::subjectByRole($user);
    }

    /** Subject users other than super admins, who are nagged but never refused. */
    public static function canBeLockedOut(User $user): bool
    {
        return ! $user->isSuperAdmin() && self::applies($user);
    }

    /** Whether login must be refused: the one check the login paths use. */
    public static function locksOut(User $user): bool
    {
        return self::canBeLockedOut($user) && self::expired($user);
    }

    /** Null until the clock has started (at the user's first login while subject). */
    public static function deadlineFor(User $user): ?CarbonImmutable
    {
        return $user->two_factor_grace_started_at?->toImmutable()->addDays(self::graceDays());
    }

    public static function expired(User $user): bool
    {
        $deadline = self::deadlineFor($user);

        return $deadline !== null && now()->greaterThanOrEqualTo($deadline);
    }

    /** Whole days left, rounded up (any part of a day counts); the full window if not started. */
    public static function daysRemaining(User $user): int
    {
        $deadline = self::deadlineFor($user);

        if ($deadline === null) {
            return self::graceDays();
        }

        return max(0, (int) ceil(now()->diffInDays($deadline, false)));
    }

    /** Start the clock; a no-op once started, so the runway is never extended by logging in. */
    public static function start(User $user): void
    {
        if ($user->two_factor_grace_started_at !== null) {
            return;
        }

        $user->forceFill(['two_factor_grace_started_at' => now()])->save();
    }

    /** Admin reset: a full fresh window from now, which also lifts a lockout. */
    public static function reset(User $user): void
    {
        $user->forceFill(['two_factor_grace_started_at' => now()])->save();
    }

    public static function graceDays(): int
    {
        return config()->integer('auth.two_factor.grace_days');
    }
}
