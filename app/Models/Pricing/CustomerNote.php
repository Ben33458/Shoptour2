<?php

declare(strict_types=1);

namespace App\Models\Pricing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only customer note / event log entry.
 *
 * Types:
 *   'lexoffice_diff'  — field mismatch detected during Lexoffice sync
 *   'manual'          — note created manually by an admin user
 *   'system'          — system-generated informational note
 *
 * @property int         $id
 * @property int|null    $company_id
 * @property int         $customer_id
 * @property string      $type               'lexoffice_diff' | 'manual' | 'system'
 * @property string      $subject
 * @property string|null $body
 * @property array|null  $meta_json
 * @property \Carbon\Carbon|null $reviewed_at
 * @property int|null    $reviewed_by_user_id
 * @property int|null    $created_by_user_id
 * @property \Carbon\Carbon $created_at
 */
class CustomerNote extends Model
{
    public const TYPE_LEXOFFICE_DIFF = 'lexoffice_diff';
    public const TYPE_MANUAL         = 'manual';
    public const TYPE_SYSTEM         = 'system';

    public const UPDATED_AT = null; // append-only

    protected $fillable = [
        'company_id',
        'customer_id',
        'type',
        'subject',
        'body',
        'meta_json',
        'reviewed_at',
        'reviewed_by_user_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'meta_json'   => 'array',
        'reviewed_at' => 'datetime',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /** Notes that have not yet been reviewed. */
    public function scopeUnreviewed($query): void
    {
        $query->whereNull('reviewed_at');
    }

    /** Only notes of a given type. */
    public function scopeOfType($query, string $type): void
    {
        $query->where('type', $type);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function isReviewed(): bool
    {
        return $this->reviewed_at !== null;
    }

    /** Human-readable label for the type. */
    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_LEXOFFICE_DIFF => 'Lexoffice-Abweichung',
            self::TYPE_MANUAL         => 'Manuelle Notiz',
            self::TYPE_SYSTEM         => 'System',
            default                   => $this->type,
        };
    }
}
