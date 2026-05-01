<?php
declare(strict_types=1);
namespace App\Models\Rental;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Komponenten-Logik für Sets/Bundles.
 * Beispiel: 1 Festzeltgarnitur = 1 Tisch + 2 Bänke
 */
class RentalComponent extends Model
{
    protected $fillable = [
        'company_id','parent_rental_item_id','component_rental_item_id','quantity',
    ];

    protected $casts = ['quantity' => 'integer'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(RentalItem::class, 'parent_rental_item_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(RentalItem::class, 'component_rental_item_id');
    }
}
