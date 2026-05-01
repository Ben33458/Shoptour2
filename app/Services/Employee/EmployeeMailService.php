<?php

declare(strict_types=1);

namespace App\Services\Employee;

use App\Mail\EmployeeWelcomeMail;
use App\Models\Employee\Employee;
use App\Models\Employee\SentEmployeeEmail;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmployeeMailService
{
    /**
     * Send the welcome email and record it in sent_employee_emails.
     */
    public function sendWelcome(Employee $employee, string $triggeredBy = 'manual', ?int $sentByUserId = null): bool
    {
        return $this->send(
            employee: $employee,
            mailable: new EmployeeWelcomeMail($employee),
            subject: 'Willkommen im Team – ' . config('app.name'),
            type: 'welcome',
            triggeredBy: $triggeredBy,
            sentByUserId: $sentByUserId,
        );
    }

    /**
     * Core send method: dispatches a Mailable and writes an audit row.
     *
     * @param  \Illuminate\Mail\Mailable  $mailable
     */
    public function send(
        Employee $employee,
        \Illuminate\Mail\Mailable $mailable,
        string $subject,
        string $type,
        string $triggeredBy = 'manual',
        ?int $sentByUserId = null,
    ): bool {
        $toAddress = $employee->email;

        if (empty($toAddress)) {
            return false;
        }

        $status       = 'sent';
        $errorMessage = null;

        try {
            Mail::to($toAddress)->send($mailable);
        } catch (Throwable $e) {
            $status       = 'failed';
            $errorMessage = substr($e->getMessage(), 0, 1000);
        }

        // Render a short preview for the log
        $bodyPreview = null;
        try {
            $rendered    = $mailable->render();
            $bodyPreview = substr(strip_tags($rendered), 0, 500);
        } catch (Throwable) {
            // Preview is best-effort
        }

        SentEmployeeEmail::create([
            'employee_id'      => $employee->id,
            'to_address'       => $toAddress,
            'subject'          => $subject,
            'type'             => $type,
            'body_preview'     => $bodyPreview,
            'triggered_by'     => $triggeredBy,
            'sent_by_user_id'  => $sentByUserId,
            'status'           => $status,
            'error_message'    => $errorMessage,
        ]);

        return $status === 'sent';
    }
}
