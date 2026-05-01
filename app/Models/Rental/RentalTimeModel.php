<?php
declare(strict_types=1);
namespace App\Models\Rental;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Admin-pflegbare Mietzeitmodelle.
 * Startmodelle: Wochenende, Woche, Werktage, Verlängerung
 *
 * Keine Tagesabrechnung mehr — nur pro Mietzeitraum.
 */
class RentalTimeModel extends Model
{
    protected $fillable = [
        'company_id','name','description','active','sort_order','rule_type',
        'min_duration_hours','default_for_events','metadata',
    ];

    protected $casts = [
        'active' => 'boolean',
        'default_for_events' => 'boolean',
        'min_duration_hours' => 'integer',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    public function priceRules(): HasMany
    {
        return $this->hasMany(RentalPriceRule::class);
    }
}
