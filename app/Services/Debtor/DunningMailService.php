<?php

declare(strict_types=1);

namespace App\Services\Debtor;

use App\Mail\DunningLevel1Mail;
use App\Mail\DunningPostalMail;
use App\Models\Communications\Communication;
use App\Models\Debtor\DunningRun;
use App\Models\Debtor\DunningRunItem;
use App\Models\Pricing\AppSetting;
use App\Services\Admin\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Handles the actual email dispatch for dunning run items.
 *
 * Level 1: E-Mail Mahnung sent directly to the customer.
 * Level 2: Mahnschreiben PDF sent to the postal letter service.
 *
 * In test mode: all emails are redirected to the configured test address (no real sends).
 */
class DunningMailService
{
    public function __construct(
        private readonly DunningService $dunningService,
        private readonly DebtorPdfService $pdfService,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * Execute all pending items in a dunning run.
     * Updates item statuses and marks vouchers after successful send.
     */
    public function executeRun(DunningRun $run): array
    {
        if (! $run->isDraft()) {
            throw new \RuntimeException('Mahnlauf ist nicht im Entwurfs-Status und kann nicht ausgeführt werden.');
        }

        $items    = $run->items()->where('status', DunningRunItem::STATUS_PENDING)->with('customer')->get();
        $sent     = 0;
        $failed   = 0;
        $skipped  = 0;

        foreach ($items as $item) {
            try {
                // Generate PDF before sending
                $pdfPath = $this->pdfService->generateDunningLetterPdf($item);
                $item->update(['pdf_path' => $pdfPath]);

                if ($item->channel === DunningRunItem::CHANNEL_POST) {
                    $this->sendPostal($item, $run->is_test_mode);
                    $this->sendEmail($item, $run->is_test_mode); // always also email customer a copy
                } else {
                    $this->sendEmail($item, $run->is_test_mode);
                }

                $item->update([
                    'status'  => DunningRunItem::STATUS_SENT,
                    'sent_at' => now(),
                ]);

                // Update vouchers' dunning level
                if (! $run->is_test_mode) {
                    $this->dunningService->markVouchersDunned($item);
                }

                $recipient = $item->recipient_email ?? $item->customer->billing_email ?? $item->customer->email;

                $this->audit->log('dunning.email.sent', $run, [
                    'item_id'      => $item->id,
                    'customer_id'  => $item->customer_id,
                    'customer_nr'  => $item->customer->customer_number,
                    'dunning_level'=> $item->dunning_level,
                    'channel'      => $item->channel,
                    'recipient'    => $recipient,
                    'test_mode'    => $run->is_test_mode,
                ]);

                // Log in customer communication history (unless test mode)
                if (! $run->is_test_mode) {
                    $senderEmail = AppSetting::get('dunning.sender_email', config('mail.from.address'));
                    $senderName  = AppSetting::get('dunning.sender_name', 'Kolabri Getränke');
                    $levelLabel  = $item->dunning_level === 1 ? 'Zahlungserinnerung' : 'Mahnung Stufe ' . $item->dunning_level;

                    Communication::create([
                        'source'            => Communication::SOURCE_MANUAL,
                        'direction'         => Communication::DIRECTION_OUT,
                        'from_address'      => $senderEmail,
                        'to_addresses'      => [$recipient],
                        'subject'           => $levelLabel . ' – Offene Rechnungen [' . $item->customer->customer_number . ']',
                        'snippet'           => sprintf('%s via Mahnlauf #%d (Stufe %d, %s)', $levelLabel, $run->id, $item->dunning_level, $item->channel),
                        'received_at'       => now(),
                        'status'            => Communication::STATUS_ARCHIVED,
                        'communicable_type' => 'Customer',
                        'communicable_id'   => $item->customer_id,
                        'created_by_user_id'=> Auth::id(),
                    ]);
                }

                $sent++;
            } catch (\Throwable $e) {
                Log::error('Dunning send failed', [
                    'item_id'     => $item->id,
                    'customer_id' => $item->customer_id,
                    'error'       => $e->getMessage(),
                ]);

                $this->audit->log('dunning.email.failed', $run, [
                    'item_id'         => $item->id,
                    'customer_id'     => $item->customer_id,
                    'customer_nr'     => $item->customer->customer_number,
                    'dunning_run_id'  => $run->id,
                    'dunning_level'   => $item->dunning_level,
                    'channel'         => $item->channel,
                    'test_mode'       => $run->is_test_mode,
                    'recipient_tried' => [
                        'item_override' => $item->recipient_email,
                        'billing_email' => $item->customer->billing_email,
                        'email'         => $item->customer->email,
                    ],
                    'error'           => $e->getMessage(),
                ], level: 'error');

                $item->update([
                    'status'        => DunningRunItem::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        $run->update([
            'status'  => DunningRun::STATUS_SENT,
            'sent_at' => now(),
        ]);

        return compact('sent', 'failed', 'skipped');
    }

    private function sendEmail(DunningRunItem $item, bool $testMode): void
    {
        $recipient = $testMode
            ? AppSetting::get('dunning.test_email', config('mail.from.address'))
            : ($item->recipient_email ?? $item->customer->billing_email ?? $item->customer->email);

        if (empty($recipient)) {
            throw new \RuntimeException('Kein Empfänger konfiguriert für Kunde ' . $item->customer->customer_number);
        }

        $mailable = new DunningLevel1Mail($item);

        // Add CC/BCC from settings (always, not only in test mode)
        $cc  = AppSetting::get('dunning.cc', '');
        $bcc = AppSetting::get('dunning.bcc', '');

        $send = Mail::to($recipient);
        if ($cc) {
            $send->cc($cc);
        }
        if ($bcc) {
            $send->bcc($bcc);
        }

        $send->send($mailable);
    }

    private function sendPostal(DunningRunItem $item, bool $testMode): void
    {
        $postalEmail = AppSetting::get('dunning.postal_service_email', 'auftrag@e-mailbrief.de');

        $recipient = $testMode
            ? AppSetting::get('dunning.test_email', config('mail.from.address'))
            : $postalEmail;

        if (empty($recipient)) {
            throw new \RuntimeException('Briefdienst-E-Mail-Adresse nicht konfiguriert.');
        }

        $mailable = new DunningPostalMail($item);

        $bcc = AppSetting::get('dunning.bcc', '');
        $send = Mail::to($recipient);
        if ($bcc) {
            $send->bcc($bcc);
        }

        $send->send($mailable);
    }
}
