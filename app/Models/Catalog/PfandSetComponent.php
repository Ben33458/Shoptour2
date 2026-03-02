<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line item inside a PfandSet.
 *
 * Business rule (MUST be enforced before saving):
 *   EXACTLY ONE of pfand_item_id or child_pfand_set_id must be non-null.
 *   A component is either:
 *     (a) a leaf reference  → pfand_item_id set, child_pfand_set_id null
 *     (b) a nested set ref  → child_pfand_set_id set, pfand_item_id null
 *   Having both set, or neither set, is invalid.
 *
 * Example: A "24er Kasten" PfandSet may contain:
 *   - 1× child_pfand_set_id → "Kasten-Pfand-Set" (which itself contains the crate item)
 *   - 24× pfand_item_id     → single bottle PfandItem
 *
 * @property int      $id
 * @property int      $pfand_set_id
 * @property int|null $pfand_item_id
 * @property int|null $child_pfand_set_id
 * @property int      $qty
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read PfandSet       $pfandSet
 * @property-read PfandItem|null $pfandItem
 * @property-read PfandSet|null  $childPfandSet
 */
class PfandSetComponent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'pfand_set_id',
        'pfand_item_id',
        'child_pfand_set_id',
        'qty',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'qty' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The parent PfandSet this component belongs to.
     */
    public function pfandSet(): BelongsTo
    {
        return $this->belongsTo(PfandSet::class);
    }

    /**
     * The leaf PfandItem referenced by this component (mutually exclusive with childPfandSet).
     */
    public function pfandItem(): BelongsTo
    {
        return $this->belongsTo(PfandItem::class);
    }

    /**
     * The nested child PfandSet referenced by this component (mutually exclusive with pfandItem).
     */
    public function childPfandSet(): BelongsTo
    {
        return $this->belongsTo(PfandSet::class, 'child_pfand_set_id');
    }

    // -------------------------------------------------------------------------
    // Domain helpers
    // -------------------------------------------------------------------------

    /**
     * Returns true when this component points to a leaf PfandItem.
     */
    public function isLeaf(): bool
    {
        return $this->pfand_item_id !== null;
    }

    /**
     * Returns true when this component nests another PfandSet.
     */
    public function isNestedSet(): bool
    {
        return $this->child_pfand_set_id !== null;
    }
}
