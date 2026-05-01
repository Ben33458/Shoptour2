<?php

declare(strict_types=1);

namespace App\Models\Primeur;

use Illuminate\Database\Eloquent\Model;

class PrimeurArticle extends Model
{
    protected $table = 'primeur_articles';

    protected $fillable = [
        'primeur_id', 'artikelnummer', 'kurzbezeichnung', 'bezeichnung', 'zusatz',
        'warengruppe', 'warenuntergruppe', 'artikelgruppe',
        'inhalt', 'masseinheit', 'vk_bezug', 'hersteller',
        'aktiv', 'anleg_time', 'update_time',
    ];

    protected $casts = [
        'aktiv' => 'boolean',
        'anleg_time' => 'datetime',
        'update_time' => 'datetime',
    ];
}
