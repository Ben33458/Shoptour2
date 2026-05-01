<?php

declare(strict_types=1);

namespace App\Models\Supplier;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bestellprofil für einen Lieferanten.
 *
 * @property int         $id
 * @property int|null    $company_id
 * @property int         $supplier_id
 * @property string      $name
 * @property bool        $ist_standard
 * @property string      $kanal             portal|email_pdf|email_csv|email_xml|upload_datei|fallback_freitext
 * @property string|null $empfaenger_email
 * @property string|null $cc_email
 * @property string|null $betreff_vorlage
 * @property string|null $text_vorlage
 * @property string|null $dateiformat
 * @property string|null $trennzeichen
 * @property bool        $mit_kopfzeile
 * @property array|null  $feldreihenfolge
 * @property array|null  $pflichtfelder
 * @property string|null $portal_url
 * @property string|null $kunden_nr_beim_lieferanten
 * @property bool        $aktiv
 */
class SupplierOrderProfile extends Model
{
    public const KANAL_PORTAL          = 'portal';
    public const KANAL_EMAIL_PDF       = 'email_pdf';
    public const KANAL_EMAIL_CSV       = 'email_csv';
    public const KANAL_EMAIL_XML       = 'email_xml';
    public const KANAL_UPLOAD_DATEI    = 'upload_datei';
    public const KANAL_FALLBACK        = 'fallback_freitext';

    protected $fillable = [
        'company_id', 'supplier_id', 'name', 'ist_standard', 'kanal',
        'empfaenger_email', 'cc_email', 'betreff_vorlage', 'text_vorlage',
        'dateiformat', 'trennzeichen', 'mit_kopfzeile',
        'feldreihenfolge', 'pflichtfelder',
        'portal_url', 'kunden_nr_beim_lieferanten', 'aktiv',
    ];

    protected $casts = [
        'ist_standard'    => 'boolean',
        'mit_kopfzeile'   => 'boolean',
        'feldreihenfolge' => 'array',
        'pflichtfelder'   => 'array',
        'aktiv'           => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
