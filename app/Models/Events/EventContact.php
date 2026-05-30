<?php

declare(strict_types=1);

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A contact person associated with an EventOccurrence.
 *
 * @property int         $id
 * @property int         $event_occurrence_id
 * @property int|null    $customer_contact_id
 * @property string|null $source_contact_id
 * @property string      $name
 * @property string      $role
 * @property string|null $phone
 * @property string|null $mobile
 * @property string|null $email
 * @property bool|null   $whatsapp_allowed
 * @property bool        $is_primary
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EventContact extends Model
{
    protected $table = 'event_contacts';

    protected $fillable = [
        'event_occurrence_id',
        'customer_contact_id',
        'source_contact_id',
        'name',
        'role',
        'phone',
        'mobile',
        'email',
        'whatsapp_allowed',
        'is_primary',
        'notes',
    ];

    protected $casts = [
        'whatsapp_allowed' => 'boolean',
        'is_primary'       => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }
}
