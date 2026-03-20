<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_REGIONAL_DIRECTOR = 'regional_director';
    public const ROLE_UNIT_SUPERVISOR = 'unit_supervisor';
    public const ROLE_ORGANIZER = 'organizer';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'approval_status',
        'is_admin',
        'role',
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
            'is_admin' => 'boolean',
        ];
    }

    public static function roles(): array
    {
        return [
            self::ROLE_REGIONAL_DIRECTOR,
            self::ROLE_UNIT_SUPERVISOR,
            self::ROLE_ORGANIZER,
        ];
    }

    public static function endorserRoles(): array
    {
        return [
            self::ROLE_UNIT_SUPERVISOR,
            self::ROLE_ORGANIZER,
        ];
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isRegionalDirector(): bool
    {
        return $this->hasRole(self::ROLE_REGIONAL_DIRECTOR) || (bool) $this->is_admin;
    }
}
