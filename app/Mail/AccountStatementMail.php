<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Admin\LexofficeVoucher;
use App\Models\Pricing\Customer;
use App\Services\Integrations\LexofficeVoucherPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AccountStatementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Customer $customer,
        public readonly string $pdfPath,
        public readonly Collection $vouchers = new Collection(),
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ihre aktuellen offenen Posten – ' . $this->customer->customer_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.account_statement',
            with: ['customer' => $this->customer],
        );
    }

    public function attachments(): array
    {
        $attachments = [
            Attachment::fromStorageDisk('local', $this->pdfPath)
                ->as('Offene-Posten-' . $this->customer->customer_number . '.pdf')
                ->withMime('application/pdf'),
        ];

        $voucherPdfService = app(LexofficeVoucherPdfService::class);
        foreach ($this->vouchers as $voucher) {
            try {
                $path = $voucherPdfService->getOrFetch($voucher);
                if (Storage::disk('local')->exists($path)) {
                    $attachments[] = Attachment::fromStorageDisk('local', $path)
                        ->as(($voucher->voucher_number ?? 'Rechnung') . '.pdf')
                        ->withMime('application/pdf');
                }
            } catch (\Throwable $e) {
                Log::warning('AccountStatementMail: Rechnungs-PDF konnte nicht angehängt werden', [
                    'voucher_id' => $voucher->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $attachments;
    }
}
