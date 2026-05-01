<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Employee\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to an employee when their email address is first added to their record
 * (e.g. via Ninox sync).  Contains a brief welcome and a link to the
 * employee self-service / onboarding portal.
 */
class EmployeeWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Employee $employee,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Willkommen im Team – ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.employee.welcome',
        );
    }
}
