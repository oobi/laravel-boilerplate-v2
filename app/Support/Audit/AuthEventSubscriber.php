<?php

declare(strict_types=1);

namespace App\Support\Audit;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEvent;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Fortify;

/**
 * Signing in and account security, from the events the framework already fires.
 * An audit failure never stops any of them (Audit::attempt()), so an outage can't
 * lock anyone out.
 */
class AuthEventSubscriber
{
    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            Login::class => 'onLogin',
            Logout::class => 'onLogout',
            Failed::class => 'onFailedLogin',
            Lockout::class => 'onLockout',
            Registered::class => 'onRegistered',
            Verified::class => 'onVerified',
            PasswordReset::class => 'onPasswordReset',
            TwoFactorAuthenticationEnabled::class => 'onTwoFactorEvent',
            TwoFactorAuthenticationConfirmed::class => 'onTwoFactorEvent',
            TwoFactorAuthenticationDisabled::class => 'onTwoFactorEvent',
            TwoFactorAuthenticationFailed::class => 'onTwoFactorEvent',
            RecoveryCodesGenerated::class => 'onTwoFactorEvent',
            RecoveryCodeReplaced::class => 'onTwoFactorEvent',
        ];
    }

    public function onLogin(Login $event): void
    {
        $user = $this->modelFrom($event->user);

        Audit::attempt('auth.login', $user, ['remember' => $event->remember], AuditCategory::AUTH, by: $user);
    }

    public function onLogout(Logout $event): void
    {
        $user = $this->modelFrom($event->user);

        Audit::attempt('auth.logout', $user, category: AuditCategory::AUTH, by: $user);
    }

    /** Never the credentials as a whole: they hold the password. */
    public function onFailedLogin(Failed $event): void
    {
        Audit::attempt(
            'auth.login_failed',
            $this->modelFrom($event->user),
            $this->identifier($event->credentials[Fortify::username()] ?? null),
            AuditCategory::AUTH,
        );
    }

    public function onLockout(Lockout $event): void
    {
        Audit::attempt('auth.lockout', properties: $this->identifier($event->request->input(Fortify::username())), category: AuditCategory::AUTH);
    }

    public function onRegistered(Registered $event): void
    {
        Audit::attempt('auth.registered', $this->modelFrom($event->user), category: AuditCategory::AUTH);
    }

    public function onVerified(Verified $event): void
    {
        Audit::attempt('auth.email_verified', $this->modelFrom($event->user), category: AuditCategory::AUTH);
    }

    public function onPasswordReset(PasswordReset $event): void
    {
        Audit::attempt('user.password_reset', $this->modelFrom($event->user), category: AuditCategory::SECURITY);
    }

    /** RecoveryCodeReplaced also carries the code used, which is never read. */
    public function onTwoFactorEvent(TwoFactorAuthenticationEvent|RecoveryCodesGenerated|RecoveryCodeReplaced $event): void
    {
        $name = match ($event::class) {
            TwoFactorAuthenticationEnabled::class => 'two_factor.enabled',
            TwoFactorAuthenticationConfirmed::class => 'two_factor.confirmed',
            TwoFactorAuthenticationDisabled::class => 'two_factor.disabled',
            TwoFactorAuthenticationFailed::class => 'two_factor.failed',
            RecoveryCodesGenerated::class => 'two_factor.recovery_codes_generated',
            RecoveryCodeReplaced::class => 'two_factor.recovery_code_used',
        };

        Audit::attempt($name, $this->modelFrom($event->user), category: AuditCategory::SECURITY);
    }

    private function modelFrom(mixed $user): ?Model
    {
        return $user instanceof Model ? $user : null;
    }

    /**
     * What someone typed as their login, kept only if it looks like an email:
     * people paste passwords into the wrong field.
     *
     * @return array<string, string>
     */
    private function identifier(mixed $value): array
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
            ? ['identifier' => Str::lower($value)]
            : [];
    }
}
