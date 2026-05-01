<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Models\Communications\Communication;
use App\Models\Communications\CommunicationAudit;

class CommunicationAuditService
{
    public function log(
        Communication $communication,
        string $eventType,
        array $details = [],
        ?int $userId = null
    ): CommunicationAudit {
        return CommunicationAudit::create([
            'communication_id' => $communication->id,
            'event_type'       => $eventType,
            'details_json'     => $details ?: null,
            'user_id'          => $userId,
        ]);
    }
}
