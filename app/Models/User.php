<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Driver\CashRegister;
use App\Models\Pricing\Customer;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    // -------------------------------------------------------------------------
    // Role constants
    // -------------------------------------------------------------------------

    public const ROLE_ADMIN       = 'admin';
    public const ROLE_MITARBEITER = 'mitarbeiter';
    public const ROLE_KUNDE       = 'kunde';
    public const ROLE_SUB_USER    = 'sub_user';

    /** Roles that may access the /admin area. */
    public const ADMIN_ROLES = [self::ROLE_ADMIN, self::ROLE_MITARBEITER];

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
        'role',
        'google_id',
        'avatar_url',
        'company_id',
        'dark_mode',
        'zustaendigkeit',
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
            'password'          => 'hashed',
            'active'            => 'boolean',
            'dark_mode'         => 'boolean',
            'zustaendigkeit'    => 'array',
        ];
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * Virtual `name` attribute — concatenates first_name + last_name.
     * Keeps backward compatibility with code that reads $user->name.
     */
    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The Customer record linked to this shop user (role=kunde).
     */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class, 'user_id');
    }

    /**
     * Sub-user record (role=sub_user only).
     */
    public function subUser(): HasOne
    {
        return $this->hasOne(SubUser::class, 'user_id');
    }

    // -------------------------------------------------------------------------
    // Role helpers
    // -------------------------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isMitarbeiter(): bool
    {
        return $this->role === self::ROLE_MITARBEITER;
    }

    public function isKunde(): bool
    {
        return $this->role === self::ROLE_KUNDE;
    }

    public function isSubUser(): bool
    {
        return $this->role === self::ROLE_SUB_USER;
    }

    public function hasAdminAccess(): bool
    {
        return in_array($this->role, self::ADMIN_ROLES, true);
    }

    public function sendPasswordResetNotification(mixed $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
