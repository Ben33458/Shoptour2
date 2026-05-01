<?php
declare(strict_types=1);
namespace App\Models\Rental;
use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Position in einer Rücknahme.
 *
 * Vollgut-Kästen: quantity negativ, generated_fee_article = Artikel 58610
 * Vollgut-Fässer: quantity negativ, generated_fee_article = Artikel 58611
 *
 * best_before_date: Pflicht bei Vollgut-Rückgaben.
 * is_restockable: bei Vollgut immer true.
 */
class DeliveryReturnItem extends Model
{
    protected $fillable = [
        'delivery_return_id','article_id','quantity','packaging_id','return_reason',
        'best_before_date','is_restockable','generated_fee_article_id','generated_fee_quantity','notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'is_restockable' => 'boolean',
        'generated_fee_quantity' => 'integer',
        'best_before_date' => 'date',
    ];

    public function deliveryReturn(): BelongsTo
    {
        return $this->belongsTo(DeliveryReturn::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'article_id');
    }

    public function generatedFeeArticle(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'generated_fee_article_id');
    }
}
