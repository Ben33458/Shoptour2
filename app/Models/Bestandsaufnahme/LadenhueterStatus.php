<?php

declare(strict_types=1);

namespace App\Models\Bestandsaufnahme;

use App\Models\Catalog\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $product_id
 * @property int|null    $warehouse_id
 * @property string      $status
 * @property string|null $notiz
 * @property int|null    $gesetzt_von
 * @property \Carbon\Carbon|null $gesetzt_am
 */
class LadenhueterStatus extends Model
{
    protected $table = 'ladenhueter_status';

    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'status',
        'notiz',
        'gesetzt_von',
        'gesetzt_am',
    ];

    protected $casts = [
        'gesetzt_am' => 'datetime',
    ];

    public const AKTIONEN = [
        'beobachten'              => 'Beobachten',
        'nachbestellung_blockiert' => 'Nachbestellung blockiert',
        'abverkauf_foerdern'      => 'Abverkauf fördern',
        'preisaktion_pruefen'     => 'Preisaktion prüfen',
        'ignoriert'               => 'Ignoriert',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function gesetztVon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gesetzt_von');
    }
}
