<?php

declare(strict_types=1);

namespace App\Models\Communications;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit log for communication events.
 * Append-only: no updated_at.
 */
class CommunicationAudit extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'communication_audit';

    public const EVENT_IMPORTED          = 'imported';
    public const EVENT_RULE_MATCHED      = 'rule_matched';
    public const EVENT_ASSIGNED          = 'assigned';
    public const EVENT_REVIEWED          = 'reviewed';
    public const EVENT_ARCHIVED          = 'archived';
    public const EVENT_ATTACHMENT_STORED = 'attachment_stored';
    public const EVENT_MANUAL_NOTE       = 'manual_note';

    protected $fillable = [
        'communication_id',
        'event_type',
        'details_json',
        'user_id',
    ];

    protected $casts = [
        'details_json' => 'array',
    ];

    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventLabel(): string
    {
        return match ($this->event_type) {
            self::EVENT_IMPORTED          => 'Importiert',
            self::EVENT_RULE_MATCHED      => 'Regel angewendet',
            self::EVENT_ASSIGNED          => 'Zugeordnet',
            self::EVENT_REVIEWED          => 'Geprüft',
            self::EVENT_ARCHIVED          => 'Archiviert',
            self::EVENT_ATTACHMENT_STORED => 'Anhang gespeichert',
            self::EVENT_MANUAL_NOTE       => 'Manuelle Notiz',
            default                       => $this->event_type,
        };
    }
}
