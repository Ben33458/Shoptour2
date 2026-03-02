<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Describes the composition of a composite Gebinde (packaging unit).
 *
 * Example: A "24er Kasten" Gebinde may contain 24× "0,33l Flasche" Gebinde.
 *
 * The unique index on (parent_gebinde_id, child_gebinde_id) ensures each
 * child type appears at most once per parent; qty handles the count.
 *
 * @property int    $id
 * @property int    $parent_gebinde_id
 * @property int    $child_gebinde_id
 * @property int    $qty
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Gebinde $parentGebinde
 * @property-read Gebinde $childGebinde
 */
class GebindeComponent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'parent_gebinde_id',
        'child_gebinde_id',
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
     * The composite packaging unit (e.g., a crate).
     */
    public function parentGebinde(): BelongsTo
    {
        return $this->belongsTo(Gebinde::class, 'parent_gebinde_id');
    }

    /**
     * The sub-unit contained within the parent (e.g., a bottle).
     */
    public function childGebinde(): BelongsTo
    {
        return $this->belongsTo(Gebinde::class, 'child_gebinde_id');
    }
}
