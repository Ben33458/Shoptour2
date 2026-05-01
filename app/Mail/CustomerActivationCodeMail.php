<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Pricing\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerActivationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string   $code,
        public readonly Customer $customer,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ihr Aktivierungscode – Kolabri Getränke',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.customer-activation-code',
        );
    }
}
