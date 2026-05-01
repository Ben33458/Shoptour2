<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Pricing\Customer;
use App\Models\SubUserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubUserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $acceptUrl;

    public function __construct(
        public readonly SubUserInvitation $invitation,
        string $plainToken,
        public readonly Customer $parentCustomer,
    ) {
        $this->acceptUrl = route('sub-users.invitation.accept', ['token' => $plainToken]);
    }

    public function envelope(): Envelope
    {
        $companyName = $this->parentCustomer->company_name
            ?: trim(($this->parentCustomer->first_name ?? '') . ' ' . ($this->parentCustomer->last_name ?? ''));

        return new Envelope(
            subject: "Einladung zum Kundenkonto: {$companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.sub-user-invitation',
        );
    }
}
