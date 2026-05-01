<?php

declare(strict_types=1);

namespace App\Models\Primeur;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrimeurOrderItem extends Model
{
    protected $table = 'primeur_order_items';

    protected $fillable = [
        'primeur_id', 'order_id', 'kunden_id', 'artikel_id',
        'artikelnummer', 'artikeleinheit', 'artikel_bezeichnung',
        'bestellmenge', 'liefermenge', 'fehlmenge',
        'vk_preis_regulaer', 'vk_preis_tatsaechlich', 'vk_preis_aktion',
        'listen_ek', 'effektiver_ek', 'pfandbetrag', 'storno',
    ];

    protected $casts = [
        'storno' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PrimeurOrder::class, 'order_id', 'primeur_id');
    }
}
