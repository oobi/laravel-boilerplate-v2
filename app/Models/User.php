<?php

namespace App\Models;

use App\Enums\SystemRole;
use App\Models\Concerns\HasSystemRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasSystemRole, Notifiable, SoftDeletes;

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
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * User list name (as appears in lists, e.g. "DOE, John")
     */
    public function getListNameAttribute(): string
    {
        return strtoupper($this->last_name) . ', ' . $this->first_name;
    }

    /** Admin screens (ShowUser/EditUser) need to resolve soft-deleted users too. */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->withTrashed()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }
}
