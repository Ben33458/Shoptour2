<?php

declare(strict_types=1);

namespace App\Models\Primeur;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrimeurOrder extends Model
{
    protected $table = 'primeur_orders';

    protected $fillable = [
        'primeur_id', 'kunden_id', 'beleg_nr', 'auftragsart', 'rechnungsart',
        'belegdatum', 'lieferdatum', 'rechnungsdatum',
        'tour', 'sachbearbeiter', 'status', 'storno',
        'zahlungsart', 'warenwert_gesamt', 'gesamt_netto',
        'mehrwertsteuer', 'endbetrag', 'skonto', 'waehrung', 'anleg_time',
    ];

    protected $casts = [
        'belegdatum' => 'date',
        'lieferdatum' => 'date',
        'rechnungsdatum' => 'date',
        'storno' => 'boolean',
        'anleg_time' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PrimeurCustomer::class, 'kunden_id', 'primeur_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrimeurOrderItem::class, 'order_id', 'primeur_id');
    }
}
