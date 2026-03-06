<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a deposit group (Pfand-Set) composed of one or more PfandItems
 * or nested PfandSets.
 *
 * A PfandSet is attached to a Gebinde (packaging unit) and defines the total
 * deposit obligation for that unit.
 *
 * Example:
 *   "24er-Kasten Pfand-Set"
 *     → 1× PfandSetComponent { child_pfand_set_id: "Kasten-Set", qty: 1 }
 *     → 24× PfandSetComponent { pfand_item_id: "0,33l Flasche Pfand", qty: 24 }
 *
 * @property int         $id
 * @property string      $name
 * @property bool        $active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PfandSetComponent> $components
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Gebinde>           $gebinde
 */
class PfandSet extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'beschreibung',
        'active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'active' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * All components (leaf items or nested sets) that make up this PfandSet.
     */
    public function components(): HasMany
    {
        return $this->hasMany(PfandSetComponent::class);
    }

    /**
     * All Gebinde units that use this PfandSet.
     */
    public function gebinde(): HasMany
    {
        return $this->hasMany(Gebinde::class);
    }
}
