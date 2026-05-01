<?php
declare(strict_types=1);
namespace App\Models\Event;
use Illuminate\Database\Eloquent\Model;

/**
 * Eventort / Veranstaltungsadresse.
 * Kann vom Kunden aus seinen Lieferadressen, dem internen Verzeichnis oder frei gewählt werden.
 */
class EventLocation extends Model
{
    protected $fillable = [
        'company_id','name','street','zip','city','country',
        'geo_lat','geo_lng','notes','active','source_type','source_id',
    ];

    protected $casts = [
        'active' => 'boolean',
        'geo_lat' => 'float',
        'geo_lng' => 'float',
    ];
}
