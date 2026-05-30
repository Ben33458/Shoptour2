<?php

declare(strict_types=1);

namespace App\Models\Events;

use App\Models\Pricing\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a recurring event series (e.g. "Kerb Musterstadt", "Hochzeit Müller").
 *
 * @property int         $id
 * @property int|null    $company_id
 * @property int|null    $customer_id
 * @property string      $name
 * @property string      $event_type
 * @property string|null $default_location_name
 * @property string|null $default_address
 * @property string|null $default_notes
 * @property bool        $is_active
 * @property bool        $is_duplicate_suspect
 * @property string|null $source_system
 * @property string|null $source_table
 * @property string|null $source_record_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EventSeries extends Model
{
    protected $table = 'event_series';

    protected $fillable = [
        'company_id',
        'customer_id',
        'name',
        'event_type',
        'default_location_name',
        'default_address',
        'default_notes',
        'is_active',
        'is_duplicate_suspect',
        'source_system',
        'source_table',
        'source_record_id',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'is_duplicate_suspect' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(EventOccurrence::class, 'event_series_id');
    }
}
