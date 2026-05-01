<?php

declare(strict_types=1);

namespace App\Models\Communications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-dimension confidence scores for a communication.
 * Append-only — no updated_at.
 */
class CommunicationConfidence extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'communication_confidence';

    protected $fillable = [
        'communication_id',
        'dim_contact', 'dim_org', 'dim_role',
        'dim_category', 'dim_document', 'dim_action',
        'overall', 'rule_matches',
    ];

    protected $casts = [
        'rule_matches' => 'array',
    ];

    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class);
    }

    /** Recalculate overall as weighted average of all dimensions. */
    public function recalcOverall(): void
    {
        $dims = [
            $this->dim_contact,
            $this->dim_org,
            $this->dim_role,
            $this->dim_category,
            $this->dim_document,
            $this->dim_action,
        ];
        $nonZero = array_filter($dims, fn($v) => $v > 0);
        $this->overall = count($nonZero) > 0 ? (int) round(array_sum($dims) / 6) : 0;
    }
}
