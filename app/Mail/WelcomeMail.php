<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * PROJ-1 -- Welcome email sent after successful registration (email or OAuth).
 */
class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Willkommen bei Kolabri Getraenke!',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.welcome',
        );
    }
}
