<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use App\Models\Orders\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * WP-15 – LMIV version for a base-item product.
 *
 * @property int                             $id
 * @property int                             $product_id
 * @property int                             $version_number
 * @property string|null                     $ean
 * @property string                          $status          draft|active|archived
 * @property array<string, mixed>|null       $data_json
 * @property string|null                     $change_reason
 * @property \Illuminate\Support\Carbon|null $effective_from
 * @property \Illuminate\Support\Carbon|null $effective_to
 * @property int|null                        $created_by_user_id
 * @property \Illuminate\Support\Carbon      $created_at
 * @property \Illuminate\Support\Carbon      $updated_at
 *
 * @property-read Product  $product
 * @property-read User|null $createdBy
 */
class ProductLmivVersion extends Model
{
    // ── Status constants ──────────────────────────────────────────────────────

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_ARCHIVED];

    // ─────────────────────────────────────────────────────────────────────────

    protected $table = 'product_lmiv_versions';

    protected $fillable = [
        'product_id',
        'version_number',
        'ean',
        'status',
        'data_json',
        'change_reason',
        'effective_from',
        'effective_to',
        'created_by_user_id',
    ];

    protected $casts = [
        'version_number'     => 'integer',
        'data_json'          => 'array',
        'effective_from'     => 'datetime',
        'effective_to'       => 'datetime',
        'created_by_user_id' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    /** @return BelongsTo<Product, self> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    /** @return BelongsTo<User, self> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<OrderItem> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'lmiv_version_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /** @param Builder<self> $query */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /**
     * A short label for display: "v3 (active)" etc.
     */
    public function label(): string
    {
        return "v{$this->version_number} ({$this->status})";
    }

    /**
     * Human-readable status for UI.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE   => 'Aktiv',
            self::STATUS_DRAFT    => 'Entwurf',
            self::STATUS_ARCHIVED => 'Archiviert',
            default               => ucfirst($this->status),
        };
    }

    /**
     * Return a single LMIV field value by dot-notation key.
     *
     * @param mixed $default
     * @return mixed
     */
    public function dataGet(string $key, mixed $default = null): mixed
    {
        return data_get($this->data_json ?? [], $key, $default);
    }
}
