<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Admin\LexofficeVoucher;
use App\Models\Debtor\DunningRunItem;
use App\Models\Pricing\AppSetting;
use App\Models\Pricing\Customer;
use App\Services\Integrations\LexofficeVoucherPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Dunning Level 1 — E-Mail reminder with open items list and invoice attachments.
 */
class DunningLevel1Mail extends Mailable
{
    use Queueable;

    public Customer $customer;
    public Collection $vouchers;
    public DunningRunItem $item;
    public string $senderName;
    public string $replyToAddress;

    public function __construct(DunningRunItem $item)
    {
        $this->item          = $item;
        $this->customer      = $item->customer;
        $this->vouchers      = LexofficeVoucher::whereIn('id', $item->voucher_ids ?? [])->get();
        $this->senderName    = AppSetting::get('dunning.sender_name', 'Kolabri Getränke');
        $this->replyToAddress = AppSetting::get('dunning.reply_to', '');
    }

    public function envelope(): Envelope
    {
        $senderEmail = AppSetting::get('dunning.sender_email', config('mail.from.address'));
        $cc          = AppSetting::get('dunning.cc', '');
        $bcc         = AppSetting::get('dunning.bcc', '');

        $envelope = new Envelope(
            from:    new \Illuminate\Mail\Mailables\Address($senderEmail, $this->senderName),
            subject: 'Zahlungserinnerung – Offene Rechnungen [' . $this->customer->customer_number . ']',
        );

        if ($this->replyToAddress) {
            $envelope = new Envelope(
                from:    new \Illuminate\Mail\Mailables\Address($senderEmail, $this->senderName),
                replyTo: [new \Illuminate\Mail\Mailables\Address($this->replyToAddress)],
                subject: 'Zahlungserinnerung – Offene Rechnungen [' . $this->customer->customer_number . ']',
            );
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.dunning.level1',
            with: [
                'customer'   => $this->customer,
                'vouchers'   => $this->vouchers,
                'item'       => $this->item,
                'senderName' => $this->senderName,
            ],
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        // Attach generated dunning letter PDF
        if ($this->item->pdf_path && Storage::disk('local')->exists($this->item->pdf_path)) {
            $attachments[] = Attachment::fromStorageDisk(
                'local',
                $this->item->pdf_path
            )->as('Mahnung_' . $this->customer->customer_number . '.pdf')
             ->withMime('application/pdf');
        }

        // Attach open invoice PDFs from Lexoffice (fetched on demand)
        $voucherPdfService = app(LexofficeVoucherPdfService::class);
        $vouchers = LexofficeVoucher::whereIn('id', $this->item->voucher_ids ?? [])->get();
        $attachedVoucherCount = 0;

        foreach ($vouchers as $voucher) {
            try {
                $path = $voucherPdfService->getOrFetch($voucher);
                if (Storage::disk('local')->exists($path)) {
                    $attachments[] = Attachment::fromStorageDisk('local', $path)
                        ->as(($voucher->voucher_number ?? 'Rechnung') . '.pdf')
                        ->withMime('application/pdf');
                    $attachedVoucherCount++;
                }
            } catch (\Throwable $e) {
                // Log but don't fail the whole email if one PDF can't be fetched
                Log::warning('Could not attach voucher PDF to dunning mail', [
                    'voucher_id' => $voucher->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $attachments;
    }
}
