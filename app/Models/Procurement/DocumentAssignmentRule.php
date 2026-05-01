<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Regelwerk für die automatische Dokumentzuordnung.
 *
 * Regeln werden nach `prioritaet` (aufsteigend) abgearbeitet.
 * Bei einem Match wird das Dokument dem Ziel-Lieferanten / der Bestellung zugeordnet,
 * sofern die Konfidenz den Schwellenwert des Parsers überschreitet.
 *
 * @property int         $id
 * @property int|null    $company_id
 * @property string      $name
 * @property int         $prioritaet
 * @property array       $bedingungen
 * @property string      $ziel_typ          supplier|bestellung|wareneingang
 * @property int|null    $ziel_supplier_id
 * @property float       $konfidenz_gewicht
 * @property bool        $aktiv
 * @property string|null $notiz
 */
class DocumentAssignmentRule extends Model
{
    protected $fillable = [
        'company_id', 'name', 'prioritaet',
        'bedingungen', 'ziel_typ',
        'ziel_supplier_id', 'konfidenz_gewicht',
        'aktiv', 'notiz',
    ];

    protected $casts = [
        'bedingungen'       => 'array',
        'prioritaet'        => 'integer',
        'konfidenz_gewicht' => 'float',
        'aktiv'             => 'boolean',
    ];

    public function zielSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'ziel_supplier_id');
    }
}
