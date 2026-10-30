<?php

namespace App\Models;

use App\Enums\SocialProvider;
use App\Enums\SystemPermission;
use App\Enums\UserStatus;
use App\Models\Concerns\HasProfilePhoto;
use App\Models\Concerns\HasSuperAdminFlag;
use App\Notifications\Auth\QueuedResetPassword;
use App\Notifications\Auth\QueuedVerifyEmail;
use App\Support\Roles\Coverage;
use App\Support\Roles\SystemPermissionDefinition;
// teams:start
use Concise\Teams\Concerns\HasTeams;
// teams:end
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    // teams:start — teams composes HasTeams alongside HasRoles here to resolve the
    // teams() name clash (spatie's HasRoles also declares an introspection-only teams()).
    // On uninstall bp:remove-teams replaces this whole block with the canonical line below:
    // teams:canonical: use HasFactory, HasProfilePhoto, HasRoles, HasSuperAdminFlag, Impersonate, Notifiable, SoftDeletes, TwoFactorAuthenticatable;
    use HasFactory, HasProfilePhoto, HasRoles, HasSuperAdminFlag, HasTeams, Impersonate, Notifiable, SoftDeletes, TwoFactorAuthenticatable {
        HasTeams::teams insteadof HasRoles;
    }
    // teams:end

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'is_super_admin' => 'boolean',
            'last_login_at' => 'datetime',
            'two_factor_grace_started_at' => 'datetime',
        ];
    }

    /**
     * Emails are always stored trimmed and lowercased. Fortify canonicalizes the
     * login/reset identifier the same way (`fortify.lowercase_usernames`), so any
     * write path that preserved casing would create an account that can never log
     * in on a case-sensitive connection. Normalizing at the model makes that
     * impossible to get wrong from a form, a console command, a seeder or a factory.
     */
    protected function email(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => self::normalizeEmail($value));
    }

    /**
     * The canonical form of an email address. Use this before validating
     * uniqueness so the check runs against the value that will actually be
     * stored — see the `unique` rules on the user forms and Fortify actions.
     */
    public static function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        return Str::lower(trim($email));
    }

    /**
     * User name attribute (alias fullName)
     */
    public function getNameAttribute(): string
    {
        return $this->full_name;
    }

    /**
     * User full name (as appears in the admin user list, e.g. "John Doe")
     */
    public function getFullNameAttribute(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

    /** The name as lists show it, "DOE, John" ($user->list_name). */
    protected function listName(): Attribute
    {
        return Attribute::get(fn (): string => strtoupper($this->last_name).', '.$this->first_name);
    }

    /**
     * Derived, read-only status (active + email verification). Not a stored
     * column and never mass assignable/writable — there is no admin action
     * that sets a user to "pending", it is purely inferred.
     */
    public function getStatusAttribute(): UserStatus
    {
        if (! $this->active) {
            return UserStatus::INACTIVE;
        }

        if (! $this->hasVerifiedEmail()) {
            return UserStatus::PENDING;
        }

        return UserStatus::ACTIVE;
    }

    /** ShowUser resolves soft-deleted users too, to restore them; EditUser 404s on them. */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->withTrashed()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /**
     * Determine if the user can impersonate other users. Required by
     * lab404/laravel-impersonate's own controller/Blade directives (called
     * directly, not via our Gate/Policy layer) — see UserPolicy::impersonate()
     * for the app-facing wrapper used elsewhere in the Users admin area.
     * Nested impersonation is disallowed (hides controls while already impersonating).
     */
    public function canImpersonate(): bool
    {
        if ($this->isImpersonated()) {
            return false;
        }

        return $this->isSuperAdmin() || $this->hasSystemPermission(SystemPermission::IMPERSONATE_USERS);
    }

    /**
     * Whether the user holds an application-wide permission, asked through the
     * Gate rather than spatie directly so every hook that qualifies the answer
     * applies, the super-admin bypass above all. Takes core's SystemPermission
     * or an add-on's registered enum case. Use this, not checkPermissionTo(),
     * for any system permission check on a User.
     */
    public function hasSystemPermission(SystemPermissionDefinition $permission): bool
    {
        return Gate::forUser($this)->allows($permission->value);
    }

    /**
     * Determine if this user can be impersonated by the given actor (the
     * currently authenticated user if omitted — required for the vendor
     * package's own no-argument calls). Rules: must be logged in, can't
     * impersonate yourself, and unverified, inactive or trashed users and super
     * admins cannot be impersonated. An unverified user could only be shown the
     * verify-email page, whose Resend button would mail them on the
     * impersonator's behalf.
     */
    public function canBeImpersonated(?self $actor = null): bool
    {
        // Like the `verified` middleware, only a MustVerifyEmail model is held to it.
        return (! $this instanceof MustVerifyEmail || $this->hasVerifiedEmail())
            && $this->canBeImpersonatedOnceVerified($actor);
    }

    /**
     * Every impersonation rule except a verified email, so a trigger can explain
     * that one gap instead of hiding itself. Never authorizes on its own: the
     * write boundary goes through canBeImpersonated().
     */
    public function canBeImpersonatedOnceVerified(?self $actor = null): bool
    {
        $actor ??= Auth::user();

        if (! $actor || ! $this->active || $this->trashed()) {
            return false;
        }

        if ($actor->id === $this->id) {
            return false;
        }

        // Never a super admin, and never someone with access the actor lacks:
        // impersonating them would be a way to borrow it.
        return ! $this->isSuperAdmin() && Coverage::coversUser($actor, $this);
    }

    /**
     * The Google account this user can sign in with, if any (see docs/boilerplate/authentication.md).
     *
     * @return HasOne<SocialAccount, $this>
     */
    public function googleAccount(): HasOne
    {
        return $this->hasOne(SocialAccount::class)->where('provider', SocialProvider::GOOGLE);
    }

    /** Queued, so a mail outage can't make sign-up or an email change 500 (#46). */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new QueuedVerifyEmail);
    }

    /** Queued, like verification (#46). */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new QueuedResetPassword($token));
    }
}
