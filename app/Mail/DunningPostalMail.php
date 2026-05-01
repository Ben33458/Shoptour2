<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Debtor\DunningRunItem;
use App\Models\Pricing\AppSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Storage;

/**
 * Dunning Level 2 — Email to postal letter service (e-mailbrief.de).
 *
 * The email is sent to the configured postal service address with the dunning
 * letter PDF as an attachment. The postal service then prints and mails it.
 */
class DunningPostalMail extends Mailable
{
    use Queueable;

    public DunningRunItem $item;
    public string $postalEmail;

    public function __construct(DunningRunItem $item)
    {
        $this->item        = $item;
        $this->postalEmail = AppSetting::get('dunning.postal_service_email', 'auftrag@e-mailbrief.de');
    }

    public function envelope(): Envelope
    {
        $senderEmail = AppSetting::get('dunning.sender_email', config('mail.from.address'));
        $senderName  = AppSetting::get('dunning.sender_name', 'Kolabri Getränke');

        return new Envelope(
            from:    new \Illuminate\Mail\Mailables\Address($senderEmail, $senderName),
            subject: '2. Mahnung – Kunde ' . $this->item->customer->customer_number . ' / ' . $this->item->recipient_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.dunning.postal',
            with: [
                'item'     => $this->item,
                'customer' => $this->item->customer,
            ],
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        if ($this->item->pdf_path && Storage::disk('local')->exists($this->item->pdf_path)) {
            $attachments[] = Attachment::fromStorageDisk(
                'local',
                $this->item->pdf_path
            )->as('Mahnung_Stufe2_' . $this->item->customer->customer_number . '.pdf')
             ->withMime('application/pdf');
        }

        return $attachments;
    }
}
