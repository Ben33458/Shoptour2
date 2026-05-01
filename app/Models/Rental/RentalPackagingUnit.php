<?php
declare(strict_types=1);
namespace App\Models\Rental;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * VPE (Verpackungseinheit) für Gläser und andere VPE-pflichtige Mietartikel.
 * Gläser dürfen NUR in definierten VPE gebucht werden.
 *
 * Bruch reduziert available_packs dauerhaft.
 */
class RentalPackagingUnit extends Model
{
    protected $fillable = [
        'company_id','rental_item_id','label','pieces_per_pack','sort_order','active','available_packs',
    ];

    protected $casts = [
        'active' => 'boolean',
        'pieces_per_pack' => 'integer',
        'sort_order' => 'integer',
        'available_packs' => 'integer',
    ];

    public function rentalItem(): BelongsTo
    {
        return $this->belongsTo(RentalItem::class);
    }
}
