<?php

declare(strict_types=1);

namespace App\Services\Debtor;

use App\Models\Admin\LexofficeVoucher;
use App\Models\Debtor\DunningRunItem;
use App\Models\Pricing\Customer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

/**
 * Generates PDF documents for the debt management workflow using dompdf.
 *
 * Produces:
 *   a) Offene-Posten-Übersicht PDF   (pdf.open_items)
 *   b) Mahnschreiben PDF             (pdf.dunning_letter)
 */
class DebtorPdfService
{
    private readonly DunningInterestService $interestService;

    public function __construct(DunningInterestService $interestService)
    {
        $this->interestService = $interestService;
    }

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * Generate and store an Offene-Posten-Übersicht PDF.
     * Returns the storage path.
     */
    public function generateOpenItemsPdf(Customer $customer, Collection $vouchers): string
    {
        $address       = $customer->defaultBillingAddress;
        $recipientZip  = $address?->zip ?? '';
        $recipientCity = $address?->city ?? '';

        $html = View::make('pdf.open_items', [
            'customer'         => $customer,
            'vouchers'         => $vouchers,
            'date'             => now()->format('d.m.Y'),
            'logoBase64'       => $this->logoBase64(),
            'recipientName'    => $customer->displayName(),
            'customerNumber'   => $customer->customer_number,
            'recipientStreet'  => $address?->street ?? '',
            'recipientZipCity' => trim($recipientZip . ' ' . $recipientCity),
        ])->render();

        $pdf  = $this->renderHtml($html);
        $path = 'debtor/op-uebersicht/' . $customer->id . '_' . now()->format('Ymd_His') . '.pdf';
        Storage::disk('local')->put($path, $pdf);

        return $path;
    }

    /**
     * Generate a Mahnschreiben PDF for the given dunning run item.
     * Returns the storage path.
     */
    public function generateDunningLetterPdf(DunningRunItem $item): string
    {
        $customer = $item->customer;
        $vouchers = LexofficeVoucher::whereIn('id', $item->voucher_ids ?? [])->get();
        $level    = $item->dunning_level;

        $subject = $level >= 2
            ? '2. Mahnung – Begleichen Sie Ihre offenen Rechnungen'
            : 'Zahlungserinnerung – Offene Rechnungen';

        $address      = $customer->defaultBillingAddress;
        $recipientZip = $address?->zip ?? '';
        $recipientCity = $address?->city ?? '';

        // Determine interest rate label
        $annualBps      = $this->interestService->annualRateBps($customer);
        $interestRatePct = number_format($annualBps / 100, 2, ',', '.') . ' %';

        // Try to find date of first dunning (level 1) for level 2 body text
        $firstDunningDate = null;
        if ($level >= 2) {
            $firstDunnedAt = $vouchers->map(fn ($v) => $v->last_dunned_at)->filter()->sort()->first();
            $firstDunningDate = $firstDunnedAt?->format('d.m.Y');
        }

        $html = View::make('pdf.dunning_letter', [
            'item'             => $item,
            'customer'         => $customer,
            'vouchers'         => $vouchers,
            'level'            => $level,
            'subject'          => $subject,
            'date'             => now()->format('d.m.Y'),
            'deadline'         => Carbon::now()->addDays(7)->format('d.m.Y'),
            'recipientName'    => $customer->displayName(),
            'customerNumber'   => $customer->customer_number,
            'recipientStreet'  => $address?->street ?? '',
            'recipientZipCity' => trim($recipientZip . ' ' . $recipientCity),
            'interestRatePct'  => $interestRatePct,
            'firstDunningDate' => $firstDunningDate,
            'logoBase64'       => $this->logoBase64($customer),
            'isKehr'           => $customer->isKehr(),
        ])->render();

        $pdf  = $this->renderHtml($html);
        $path = 'debtor/mahnschreiben/run_' . $item->dunning_run_id . '_customer_' . $item->customer_id . '.pdf';
        Storage::disk('local')->put($path, $pdf);

        return $path;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function renderHtml(string $html): string
    {
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = app()->make('dompdf.wrapper');
        $pdf->loadHTML($html);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    private function logoBase64(?Customer $customer = null): ?string
    {
        $path = ($customer?->isKehr())
            ? public_path('images/kehr_logo.png')
            : public_path('images/kolabri_logo.png');

        if (! file_exists($path)) {
            return null;
        }

        return base64_encode(file_get_contents($path));
    }
}
