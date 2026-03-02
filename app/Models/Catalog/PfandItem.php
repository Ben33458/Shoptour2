<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents an individual deposit (Pfand) item.
 *
 * Business rules:
 * - pfand_typ must be one of "Einweg" (disposable) or "Mehrweg" (reusable/returnable).
 * - All monetary values are stored as milli-cents (integer × 1/1000 cent) to avoid
 *   floating-point rounding errors. Convert by dividing by 1_000_000 to get EUR.
 *
 * @property int         $id
 * @property string      $pfand_typ              "Einweg"|"Mehrweg"
 * @property string      $bezeichnung            Human-readable label, e.g. "0,33l Flasche"
 * @property int         $wert_netto_milli
 * @property int         $wert_brutto_milli
 * @property int         $wiederverkaeufer_wert_netto_milli
 * @property int         $wiederverkaeufer_wert_brutto_milli
 * @property bool        $active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PfandSetComponent> $pfandSetComponents
 */
class PfandItem extends Model
{
    /** Allowed values for pfand_typ */
    public const TYP_EINWEG  = 'Einweg';
    public const TYP_MEHRWEG = 'Mehrweg';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pfand_typ',
        'bezeichnung',
        'wert_netto_milli',
        'wert_brutto_milli',
        'wiederverkaeufer_wert_netto_milli',
        'wiederverkaeufer_wert_brutto_milli',
        'active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'active'                              => 'boolean',
        'wert_netto_milli'                    => 'integer',
        'wert_brutto_milli'                   => 'integer',
        'wiederverkaeufer_wert_netto_milli'   => 'integer',
        'wiederverkaeufer_wert_brutto_milli'  => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * All pfand_set_components that reference this leaf item.
     */
    public function pfandSetComponents(): HasMany
    {
        return $this->hasMany(PfandSetComponent::class);
    }
}
