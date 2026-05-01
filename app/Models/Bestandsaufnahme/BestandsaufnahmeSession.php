<?php

declare(strict_types=1);

namespace App\Models\Bestandsaufnahme;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int         $id
 * @property int         $warehouse_id
 * @property string|null $titel
 * @property string      $status   offen|pausiert|abgeschlossen
 * @property int         $gestartet_von
 * @property \Carbon\Carbon $gestartet_am
 * @property \Carbon\Carbon|null $abgeschlossen_am
 * @property string|null $notiz
 */
class BestandsaufnahmeSession extends Model
{
    protected $table = 'bestandsaufnahme_sessions';

    protected $fillable = [
        'company_id',
        'warehouse_id',
        'titel',
        'status',
        'gestartet_von',
        'gestartet_am',
        'abgeschlossen_am',
        'notiz',
    ];

    protected $casts = [
        'gestartet_am'    => 'datetime',
        'abgeschlossen_am' => 'datetime',
    ];

    public const STATUS_OFFEN        = 'offen';
    public const STATUS_PAUSIERT     = 'pausiert';
    public const STATUS_ABGESCHLOSSEN = 'abgeschlossen';

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function gestartetVon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gestartet_von');
    }

    public function positionen(): HasMany
    {
        return $this->hasMany(BestandsaufnahmePosition::class, 'session_id');
    }

    public function isOffen(): bool
    {
        return $this->status === self::STATUS_OFFEN;
    }

    public function isPausiert(): bool
    {
        return $this->status === self::STATUS_PAUSIERT;
    }

    public function isAbgeschlossen(): bool
    {
        return $this->status === self::STATUS_ABGESCHLOSSEN;
    }
}
