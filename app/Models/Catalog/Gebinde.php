<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a physical packaging unit (Gebinde).
 *
 * Examples:
 *   - "24er Kasten"  (gebinde_type: "Kasten", material: "Glas")
 *   - "0,33l Flasche" (gebinde_type: "Flasche", material: "Glas")
 *   - "0,5l PET"     (gebinde_type: "Flasche", material: "PET")
 *   - "6er Träger"   (gebinde_type: "Traeger", material: "Karton")
 *
 * Each Gebinde carries exactly one PfandSet which defines its deposit obligations.
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $gebinde_type
 * @property int         $pfand_set_id
 * @property string|null $material
 * @property bool        $active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read PfandSet                                                           $pfandSet
 * @property-read \Illuminate\Database\Eloquent\Collection<int, GebindeComponent>   $childComponents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, GebindeComponent>   $parentComponents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Product>            $products
 */
class Gebinde extends Model
{
    /**
     * The actual database table name.
     * Overrides Laravel's default pluralisation (would produce "gebindes").
     */
    protected $table = 'gebinde';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'gebinde_type',
        'pfand_set_id',
        'material',
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
     * The deposit set associated with this packaging unit.
     */
    public function pfandSet(): BelongsTo
    {
        return $this->belongsTo(PfandSet::class);
    }

    /**
     * Sub-units contained within this Gebinde (when this is a composite unit, e.g. a crate).
     */
    public function childComponents(): HasMany
    {
        return $this->hasMany(GebindeComponent::class, 'parent_gebinde_id');
    }

    /**
     * Parent units that contain this Gebinde (when this is a sub-unit, e.g. a bottle inside a crate).
     */
    public function parentComponents(): HasMany
    {
        return $this->hasMany(GebindeComponent::class, 'child_gebinde_id');
    }

    /**
     * All products that use this packaging unit.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
