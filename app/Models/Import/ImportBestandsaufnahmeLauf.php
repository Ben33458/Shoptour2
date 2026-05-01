<?php

declare(strict_types=1);

namespace App\Models\Import;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int         $id
 * @property string      $dateiname
 * @property string      $status   verarbeitung|abgeschlossen|fehler
 * @property int         $anzahl_blaetter
 * @property int         $anzahl_rohzeilen
 * @property int         $anzahl_konflikte
 * @property string|null $fehler_log
 * @property int|null    $importiert_von
 */
class ImportBestandsaufnahmeLauf extends Model
{
    protected $table = 'import_bestandsaufnahme_laeufe';

    protected $fillable = [
        'company_id',
        'dateiname',
        'status',
        'anzahl_blaetter',
        'anzahl_rohzeilen',
        'anzahl_konflikte',
        'fehler_log',
        'importiert_von',
    ];

    public function importiertVon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'importiert_von');
    }

    public function rohzeilen(): HasMany
    {
        return $this->hasMany(ImportBestandsaufnahmeRohzeile::class, 'importlauf_id');
    }
}
