<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerActivationMultipleMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $email,
        public readonly \Illuminate\Support\Collection $customers,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Aktivierung] Mehrfachtreffer – manuelle Prüfung erforderlich',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.customer-activation-multiple',
        );
    }
}
