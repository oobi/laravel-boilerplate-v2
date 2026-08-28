<?php

namespace App\Models;

use App\Enums\SystemRole;
use App\Enums\UserStatus;
use App\Models\Concerns\HasProfilePhoto;
use App\Models\Concerns\HasSystemRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasProfilePhoto, HasSystemRole, Impersonate, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

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
        'system_role',
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
            'system_role' => SystemRole::class,
            'last_login_at' => 'datetime',
        ];
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

    /**
     * User list name (as appears in lists, e.g. "DOE, John")
     */
    public function getListNameAttribute(): string
    {
        return strtoupper($this->last_name).', '.$this->first_name;
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

    /** Admin screens (ShowUser/EditUser) need to resolve soft-deleted users too. */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->withTrashed()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /**
     * Determine if the user can impersonate other users.
     * Only super admins and support staff can impersonate, and nested
     * impersonation is disallowed (hides controls while already impersonating).
     */
    public function canImpersonate(): bool
    {
        if ($this->isImpersonated()) {
            return false;
        }

        return $this->isSuperAdmin() || $this->isSupport();
    }

    /**
     * Determine if this user can be impersonated by the currently authenticated user.
     * Rules: must be logged in, can't impersonate yourself, super admins can
     * never be impersonated, and support staff can't impersonate other support staff.
     */
    public function canBeImpersonated(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        if (Auth::id() === $this->id) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return false;
        }

        if (Auth::user()->isSupport() && $this->isSupport()) {
            return false;
        }

        return true;
    }
}
