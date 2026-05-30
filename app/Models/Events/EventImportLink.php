<?php

declare(strict_types=1);

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Tracks the link between a local entity and an external source record.
 * Prevents duplicate imports.
 *
 * @property int         $id
 * @property string      $entity_type
 * @property int         $entity_id
 * @property string      $source_system
 * @property string|null $source_table
 * @property string      $source_record_id
 * @property string|null $external_number
 * @property string|null $raw_payload_hash
 * @property \Carbon\Carbon $imported_at
 * @property \Carbon\Carbon|null $last_seen_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EventImportLink extends Model
{
    protected $table = 'event_import_links';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'source_system',
        'source_table',
        'source_record_id',
        'external_number',
        'raw_payload_hash',
        'imported_at',
        'last_seen_at',
    ];

    protected $casts = [
        'imported_at'  => 'datetime',
        'last_seen_at' => 'datetime',
    ];
}
