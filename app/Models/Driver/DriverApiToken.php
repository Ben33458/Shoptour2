<?php

declare(strict_types=1);

namespace App\Models\Driver;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Driver API bearer token.
 *
 * The plain token is shown once at creation time and never stored.
 * Only the SHA-256 hash (token_hash) is persisted.
 *
 * Lifecycle:
 *   active = true  AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > now)
 *   → token is valid
 *
 * @property int              $id
 * @property int|null         $employee_id
 * @property string           $token_hash        SHA-256 of the bearer token
 * @property string|null      $label
 * @property bool             $active
 * @property \Carbon\Carbon|null $last_used_at   Updated on every successful auth
 * @property \Carbon\Carbon|null $expires_at     Hard expiry; null = never expires
 * @property \Carbon\Carbon|null $revoked_at     Set on revocation; not null = rejected
 * @property int|null         $created_by_user_id Admin who created this token
 * @property int|null         $company_id
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 */
class DriverApiToken extends Model
{
    protected $fillable = [
        'employee_id',
        'token_hash',
        'label',
        'active',
        'last_used_at',
        'expires_at',
        'revoked_at',
        'created_by_user_id',
        'company_id',
    ];

    protected $casts = [
        'active'             => 'boolean',
        'employee_id'        => 'integer',
        'created_by_user_id' => 'integer',
        'company_id'         => 'integer',
        'last_used_at'       => 'datetime',
        'expires_at'         => 'datetime',
        'revoked_at'         => 'datetime',
    ];

    // =========================================================================
    // Static helpers
    // =========================================================================

    /**
     * Find a valid (active, not revoked, not expired) token by its plain value.
     *
     * Also updates last_used_at on every successful lookup.
     *
     * @param  string $plainToken  Raw token from Authorization header
     * @return static|null
     */
    public static function findByPlainToken(string $plainToken): ?static
    {
        $hash = hash('sha256', $plainToken);

        /** @var static|null $record */
        $record = static::where('token_hash', $hash)
            ->where('active', true)
            ->whereNull('revoked_at')
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($record !== null) {
            // Touch last_used_at without triggering full model events
            static::where('id', $record->id)->update(['last_used_at' => now()]);
            $record->last_used_at = now();
        }

        return $record;
    }

    /**
     * Generate a new cryptographically random plain token + its SHA-256 hash.
     *
     * @return array{plain: string, hash: string}
     */
    public static function generateToken(): array
    {
        $plain = Str::random(64);
        return [
            'plain' => $plain,
            'hash'  => hash('sha256', $plain),
        ];
    }

    /**
     * Issue a new token and persist the record.
     * Returns both the model and the ONE-TIME plain token.
     *
     * @param  array<string, mixed> $attributes  Extra columns (label, employee_id, etc.)
     * @return array{token: static, plain: string}
     */
    public static function issue(array $attributes = []): array
    {
        ['plain' => $plain, 'hash' => $hash] = self::generateToken();

        /** @var static $token */
        $token = static::create(array_merge([
            'token_hash'         => $hash,
            'active'             => true,
            'created_by_user_id' => Auth::id(),
        ], $attributes));

        return ['token' => $token, 'plain' => $plain];
    }

    // =========================================================================
    // Domain helpers
    // =========================================================================

    /**
     * Revoke this token.
     */
    public function revoke(): void
    {
        $this->update([
            'revoked_at' => now(),
            'active'     => false,
        ]);
    }

    /**
     * True when the token is currently valid for API access.
     */
    public function isValid(): bool
    {
        if (! $this->active || $this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Human-readable status string for admin UI (German).
     */
    public function statusLabel(): string
    {
        if ($this->revoked_at !== null) {
            return 'widerrufen';
        }
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return 'abgelaufen';
        }
        if (! $this->active) {
            return 'inaktiv';
        }
        return 'aktiv';
    }
}
