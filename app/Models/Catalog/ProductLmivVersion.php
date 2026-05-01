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

    // ── Property accessors (used by shop/product.blade.php) ───────────────────
    // These map view-facing names to the actual data_json keys.

    public function getZutatenAttribute(): ?string
    {
        return $this->dataGet('zutaten');
    }

    public function getAllergeneAttribute(): ?string
    {
        return $this->dataGet('allergene');
    }

    public function getHerkunftslandAttribute(): ?string
    {
        return $this->dataGet('herkunftsland');
    }

    /** Mapped from data_json key "hersteller" */
    public function getHerstellerNameAttribute(): ?string
    {
        return $this->dataGet('hersteller');
    }

    /** Mapped from data_json key "herstelleranschrift" */
    public function getHerstellerAnschriftAttribute(): ?string
    {
        return $this->dataGet('herstelleranschrift');
    }

    public function getNaehrwertEnergieKjAttribute(): int|float|null
    {
        $v = $this->dataGet('nw_energie_kj') ?? $this->dataGet('nutrition.energy_kj');
        return $v !== null ? (float) $v : null;
    }

    public function getNaehrwertEnergieKcalAttribute(): int|float|null
    {
        $v = $this->dataGet('nw_energie_kcal') ?? $this->dataGet('nutrition.energy_kcal');
        return $v !== null ? (float) $v : null;
    }

    public function getNaehrwertFettAttribute(): int|float|null
    {
        $v = $this->dataGet('nw_fett') ?? $this->dataGet('nutrition.fat');
        return $v !== null ? (float) $v : null;
    }

    public function getNaehrwertGesaettigteFettsaeurenAttribute(): int|float|null
    {
        $v = $this->dataGet('nw_fett_gesaettigt') ?? $this->dataGet('nutrition.fat_saturated');
        return $v !== null ? (float) $v : null;
    }

    public function getNaehrwertKohlenhydrateAttribute(): int|float|null
    {
        $v = $this->dataGet('nw_kohlenhydrate') ?? $this->dataGet('nutrition.carbohydrates');
        return $v !== null ? (float) $v : null;
    }

    public function getNaehrwertZuckerAttribute(): int|float|null
    {
        $v = $this->dataGet('nw_zucker') ?? $this->dataGet('nutrition.sugar');
        return $v !== null ? (float) $v : null;
    }

    public function getNaehrwertEiweissAttribute(): int|float|null
    {
        $v = $this->dataGet('nw_eiweiss') ?? $this->dataGet('nutrition.protein');
        return $v !== null ? (float) $v : null;
    }

    public function getNaehrwertSalzAttribute(): int|float|null
    {
        $v = $this->dataGet('nw_salz') ?? $this->dataGet('nutrition.salt');
        return $v !== null ? (float) $v : null;
    }

    public function getNaehrwertNatriumAttribute(): int|float|null
    {
        $v = $this->dataGet('nw_natrium') ?? $this->dataGet('nutrition.sodium');
        return $v !== null ? (float) $v : null;
    }

    public function getNaehrwertCalciumAttribute(): int|float|null
    {
        $v = $this->dataGet('nw_calcium');
        return $v !== null ? (float) $v : null;
    }

    public function getNaehrwertMagnesiumAttribute(): int|float|null
    {
        $v = $this->dataGet('nw_magnesium');
        return $v !== null ? (float) $v : null;
    }

    public function getNaehrwertHydrogencarbonatAttribute(): int|float|null
    {
        $v = $this->dataGet('nw_hydrogencarbonat');
        return $v !== null ? (float) $v : null;
    }

    public function getNaehrwertKaliumAttribute(): int|float|null
    {
        $v = $this->dataGet('nw_kalium');
        return $v !== null ? (float) $v : null;
    }

    public function getNaehrwertChloridAttribute(): int|float|null
    {
        $v = $this->dataGet('nw_chlorid');
        return $v !== null ? (float) $v : null;
    }

    public function getNaehrwertSulfatAttribute(): int|float|null
    {
        $v = $this->dataGet('nw_sulfat');
        return $v !== null ? (float) $v : null;
    }

    public function getNaehrwertFluoridAttribute(): int|float|null
    {
        $v = $this->dataGet('nw_fluorid');
        return $v !== null ? (float) $v : null;
    }

    public function getNaehrwertKieselsaeureAttribute(): int|float|null
    {
        $v = $this->dataGet('nw_kieselsaeure');
        return $v !== null ? (float) $v : null;
    }

    /**
     * Alkoholgehalt in % vol. (LMIV-Pflichtangabe für Getränke mit > 1,2 % vol.)
     * Gespeichert als decimal, z.B. 4.8 für 4,8 % vol.
     */
    public function getAlkoholgehaltAttribute(): ?float
    {
        $v = $this->dataGet('alkoholgehalt');
        return $v !== null ? (float) $v : null;
    }
}
