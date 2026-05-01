<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Admin\AuditLog;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

/**
 * Writes append-only audit log entries for admin and driver actions.
 *
 * Automatically resolves:
 *   user_id    – from the authenticated Laravel user (web session)
 *   company_id – from the IoC-bound 'current_company' (set by CompanyMiddleware)
 *   token_id   – passed explicitly when the action originates from a driver API call
 *
 * Usage:
 *   app(AuditLogService::class)->log('invoice.finalized', $invoice);
 *   app(AuditLogService::class)->log('po.created', $po, ['items' => 3], tokenId: 5);
 */
class AuditLogService
{
    /**
     * Record an action in the audit log.
     *
     * @param  string            $action   Short action key, e.g. "invoice.finalized"
     * @param  object|null       $subject  Eloquent model that is the subject
     * @param  array<string, mixed> $meta  Additional context (counts, filenames, etc.)
     * @param  int|null          $tokenId  Driver API token ID (for driver-originated actions)
     */
    public function log(
        string  $action,
        ?object $subject  = null,
        array   $meta     = [],
        ?int    $tokenId  = null,
        string  $level    = 'info',
    ): AuditLog {
        // Resolve company from IoC (set by CompanyMiddleware; null on CLI/tests)
        $company = null;
        try {
            $company = App::bound('current_company') ? App::make('current_company') : null;
        } catch (\Throwable) {
            // Silently ignore — audit log should never break the main flow
        }

        return AuditLog::create([
            'user_id'      => Auth::id(),
            'company_id'   => $company?->id,
            'token_id'     => $tokenId,
            'action'       => $action,
            'level'        => $level,
            'subject_type' => $subject !== null ? class_basename($subject) : null,
            'subject_id'   => $subject !== null && property_exists($subject, 'id') ? $subject->id : null,
            'meta_json'    => ! empty($meta) ? $meta : null,
        ]);
    }
}
