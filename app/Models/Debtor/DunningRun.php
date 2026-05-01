<?php

declare(strict_types=1);

namespace App\Models\Debtor;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A dunning run (Mahnlauf) groups multiple dunning notices sent in one batch.
 *
 * @property int         $id
 * @property int|null    $company_id
 * @property int|null    $created_by_user_id
 * @property string      $status        draft|sent|cancelled
 * @property bool        $is_test_mode
 * @property string|null $notes
 * @property \Carbon\Carbon|null $sent_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class DunningRun extends Model
{
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SENT      = 'sent';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'dunning_runs';

    protected $fillable = [
        'company_id',
        'created_by_user_id',
        'status',
        'is_test_mode',
        'notes',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'is_test_mode' => 'boolean',
            'sent_at'      => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DunningRunItem::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function sentCount(): int
    {
        return $this->items()->where('status', DunningRunItem::STATUS_SENT)->count();
    }

    public function failedCount(): int
    {
        return $this->items()->where('status', DunningRunItem::STATUS_FAILED)->count();
    }
}
