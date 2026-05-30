<?php

declare(strict_types=1);

namespace App\Services\Events;

use App\Models\Admin\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes event offer actions to the shared audit_logs table.
 */
class EventOfferAuditService
{
    /**
     * Log an event offer action to the audit trail.
     *
     * @param string   $action   e.g. 'event.offer.accepted'
     * @param Model    $subject  The model being acted upon
     * @param array    $meta     Additional metadata
     * @param int|null $userId   Overrides auth()->id() when provided
     */
    public function log(string $action, Model $subject, array $meta = [], ?int $userId = null): void
    {
        AuditLog::create([
            'user_id'      => $userId ?? auth()->id(),
            'action'       => $action,
            'subject_type' => get_class($subject),
            'subject_id'   => $subject->id,
            'meta_json'    => $meta,
            'level'        => 'info',
        ]);
    }
}
