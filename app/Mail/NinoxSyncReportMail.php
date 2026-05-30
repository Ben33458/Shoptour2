<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NinoxSyncReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        /** @var array<string, mixed> */
        public readonly array $report,
    ) {}

    public function envelope(): Envelope
    {
        $date  = now()->format('d.m.Y');
        $time  = now()->format('H:i');
        return new Envelope(
            subject: "Ninox Sync-Bericht {$date} {$time} Uhr",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.ninox-sync-report',
        );
    }
}
