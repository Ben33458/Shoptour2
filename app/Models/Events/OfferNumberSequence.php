<?php

declare(strict_types=1);

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Tracks the per-year offer number sequence for ST-A-YYYY-NNNNNN numbers.
 *
 * @property int $id
 * @property int $year
 * @property int $last_sequence
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class OfferNumberSequence extends Model
{
    protected $table = 'offer_number_sequences';

    protected $fillable = [
        'year',
        'last_sequence',
    ];

    protected $casts = [
        'year'          => 'integer',
        'last_sequence' => 'integer',
    ];
}
