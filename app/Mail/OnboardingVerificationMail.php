<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Employee\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OnboardingVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $verifyUrl;

    public function __construct(
        public readonly Employee $employee,
        string $rawToken,
        public readonly string $code,
    ) {
        $this->verifyUrl = route('onboarding.verify.link', ['token' => $rawToken]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Dein Onboarding-Code – Kolabri',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.onboarding-verification',
        );
    }
}
