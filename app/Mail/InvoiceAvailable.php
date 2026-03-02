<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Admin\Invoice;
use App\Models\Pricing\AppSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceAvailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->companyFrom($this->invoice->company_id),
            subject: 'Ihre Rechnung ' . ($this->invoice->invoice_number ?? ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice_available',
        );
    }

    private function companyFrom(?int $companyId): Address
    {
        $addr = AppSetting::get("company.{$companyId}.mail.from_address")
            ?? config('mail.from.address', 'noreply@example.com');
        $name = AppSetting::get("company.{$companyId}.mail.from_name")
            ?? config('mail.from.name', 'Shop');

        return new Address((string) $addr, (string) $name);
    }
}
