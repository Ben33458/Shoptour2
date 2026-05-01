<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Pricing\AppSetting;
use App\Models\Supplier\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * PROJ-32: Email sent to supplier with PO PDF attached.
 */
class PurchaseOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly PurchaseOrder $purchaseOrder,
        public readonly string $pdfContent,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->companyFrom($this->purchaseOrder->company_id),
            subject: 'Bestellung ' . ($this->purchaseOrder->po_number ?? ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.purchase-order',
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => $this->pdfContent,
                ($this->purchaseOrder->po_number ?? 'Bestellung') . '.pdf'
            )->withMime('application/pdf'),
        ];
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
