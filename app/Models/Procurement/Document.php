<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Models\Supplier\Supplier;
use App\Models\Supplier\SupplierDocumentParser;
use App\Models\Supplier\PurchaseOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zentrales Dokument — Lieferscheine, Rechnungen, Fotos, E-Mail-Anhänge etc.
 *
 * Duplikate werden über datei_hash erkannt, aber NICHT automatisch gelöscht.
 * Unsichere Zuordnungen landen in zuordnungs_status = 'pruefliste'.
 *
 * @property int         $id
 * @property int|null    $company_id
 * @property string      $typ
 * @property string      $quelle
 * @property string|null $dateiname
 * @property string|null $pfad
 * @property string|null $mime_type
 * @property int|null    $datei_groesse
 * @property string|null $datei_hash
 * @property string|null $ocr_text
 * @property int|null    $erkannter_lieferant_id
 * @property int|null    $erkannte_bestellung_id
 * @property int|null    $erkannter_wareneingang_id
 * @property string|null $externe_belegnummer
 * @property \Carbon\Carbon|null $belegdatum
 * @property float|null  $erkennungs_konfidenz
 * @property int|null    $verwendeter_parser_id
 * @property string      $dubletten_status
 * @property int|null    $duplikat_von_document_id
 * @property string      $zuordnungs_status
 * @property array|null  $metadaten
 */
class Document extends Model
{
    // Dokumenttypen
    public const TYP_LIEFERSCHEIN    = 'lieferschein';
    public const TYP_RECHNUNG        = 'rechnung';
    public const TYP_BESTELL_PDF     = 'bestell_pdf';
    public const TYP_BESTELL_CSV     = 'bestell_csv';
    public const TYP_EMAIL_ANHANG    = 'email_anhang';
    public const TYP_FOTO_LS         = 'foto_lieferschein';
    public const TYP_FOTO_PALETTE    = 'foto_palette';
    public const TYP_FOTO_SONSTIG    = 'foto_sonstig';
    public const TYP_SONSTIG         = 'sonstig';

    // Zuordnungs-Status
    public const STATUS_NICHT_ZUGEORDNET  = 'nicht_zugeordnet';
    public const STATUS_AUTO_ZUGEORDNET   = 'auto_zugeordnet';
    public const STATUS_MANUELL           = 'manuell_zugeordnet';
    public const STATUS_PRUEFLISTE        = 'pruefliste';
    public const STATUS_IGNORIERT         = 'ignoriert';

    protected $fillable = [
        'company_id', 'typ', 'quelle',
        'dateiname', 'pfad', 'mime_type', 'datei_groesse', 'datei_hash',
        'ocr_text',
        'erkannter_lieferant_id', 'erkannte_bestellung_id', 'erkannter_wareneingang_id',
        'externe_belegnummer', 'belegdatum',
        'erkennungs_konfidenz', 'verwendeter_parser_id',
        'dubletten_status', 'duplikat_von_document_id',
        'zuordnungs_status',
        'hochgeladen_by_user_id', 'zugeordnet_by_user_id', 'zugeordnet_at',
        'metadaten',
    ];

    protected $casts = [
        'belegdatum'           => 'date',
        'zugeordnet_at'        => 'datetime',
        'erkennungs_konfidenz' => 'float',
        'datei_groesse'        => 'integer',
        'metadaten'            => 'array',
    ];

    public function lieferant(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'erkannter_lieferant_id');
    }

    public function bestellung(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'erkannte_bestellung_id');
    }

    public function wareneingang(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'erkannter_wareneingang_id');
    }

    public function parser(): BelongsTo
    {
        return $this->belongsTo(SupplierDocumentParser::class, 'verwendeter_parser_id');
    }

    public function isDuplicate(): bool
    {
        return $this->dubletten_status === 'duplikat';
    }

    public function isInPruefliste(): bool
    {
        return $this->zuordnungs_status === self::STATUS_PRUEFLISTE;
    }
}
