<?php
declare(strict_types=1);
namespace App\Models\Rental;
use Illuminate\Database\Eloquent\Model;

/**
 * Reinigungskostenregel.
 * Reinigung wird NICHT pauschal berechnet — nur bei Bedarf.
 * amount_net_milli in Milli-Cent.
 */
class CleaningFeeRule extends Model
{
    protected $fillable = [
        'company_id','name','applies_to_type','applies_to_id','fee_type','amount_net_milli','active','notes',
    ];

    protected $casts = [
        'amount_net_milli' => 'integer',
        'active' => 'boolean',
    ];
}
