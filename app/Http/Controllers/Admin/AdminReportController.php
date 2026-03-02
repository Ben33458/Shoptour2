<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Reporting\DepositReportService;
use App\Services\Reporting\MarginReportService;
use App\Services\Reporting\RevenueReportService;
use App\Services\Reporting\TourKpiService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * WP-16 - Admin reporting hub.
 *
 * GET  /admin/reports               -> index  (4-tab view: Umsatz / DB / Pfand / Touren)
 * GET  /admin/reports/export/{type} -> exportCsv (type: revenue|margin|deposit|tours)
 */
class AdminReportController extends Controller
{
    public function __construct(
        private readonly RevenueReportService $revenueService,
        private readonly MarginReportService  $marginService,
        private readonly DepositReportService $depositService,
        private readonly TourKpiService       $tourKpiService,
    ) {}

    // -------------------------------------------------------------------------

    public function index(Request $request): \Illuminate\View\View
    {
        $from      = $request->input('from', now()->startOfMonth()->toDateString());
        $to        = $request->input('to',   now()->toDateString());
        $tab       = $request->input('tab',  'revenue');

        /** @var Company|null $company */
        $company   = app('current_company');
        $companyId = $company?->id ?? 0;

        $revenue = $this->revenueService->summary($companyId, $from, $to);
        $margin  = $this->marginService->summary($companyId, $from, $to);
        $deposit = $this->depositService->summary($companyId, $from, $to);
        $tours   = $this->tourKpiService->summary($companyId, $from, $to);

        return view('admin.reports.index', compact(
            'from', 'to', 'tab',
            'revenue', 'margin', 'deposit', 'tours',
        ));
    }

    // -------------------------------------------------------------------------

    public function exportCsv(Request $request, string $type): StreamedResponse
    {
        $from      = $request->input('from', now()->startOfMonth()->toDateString());
        $to        = $request->input('to',   now()->toDateString());

        /** @var Company|null $company */
        $company   = app('current_company');
        $companyId = $company?->id ?? 0;

        [$headers, $rows, $filename] = match ($type) {
            'revenue' => $this->buildRevenueCsv($companyId, $from, $to),
            'margin'  => $this->buildMarginCsv($companyId, $from, $to),
            'deposit' => $this->buildDepositCsv($companyId, $from, $to),
            'tours'   => $this->buildToursCsv($companyId, $from, $to),
            default   => abort(404, "Unbekannter Berichtstyp: {$type}"),
        };

        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // -- CSV builders ---------------------------------------------------------

    private function buildRevenueCsv(int $companyId, string $from, string $to): array
    {
        $data = $this->revenueService->summary($companyId, $from, $to);
        $headers = ['Rechnungsnummer', 'Datum', 'Brutto (EUR)', 'Netto (EUR)', 'MwSt (EUR)', 'Pfand (EUR)'];
        $rows = array_map(static fn (array $r): array => [
            $r['invoice_number'],
            $r['finalized_at'],
            number_format($r['total_gross_milli'] / 1_000_000, 2, ',', '.'),
            number_format($r['total_net_milli']   / 1_000_000, 2, ',', '.'),
            number_format($r['total_tax_milli']   / 1_000_000, 2, ',', '.'),
            number_format($r['total_deposit_milli'] / 1_000_000, 2, ',', '.'),
        ], $data['rows']);

        return [$headers, $rows, "umsatz_{$from}_{$to}.csv"];
    }

    private function buildMarginCsv(int $companyId, string $from, string $to): array
    {
        $data = $this->marginService->summary($companyId, $from, $to);
        $headers = ['Rechnung', 'Beschreibung', 'Menge', 'Umsatz Netto (EUR)', 'EK (EUR)', 'DB (EUR)'];
        $rows = array_map(static fn (array $r): array => [
            $r['invoice_number'] ?? '',
            $r['description'],
            number_format($r['qty'], 3, ',', '.'),
            number_format($r['revenue_net_milli'] / 1_000_000, 2, ',', '.'),
            $r['cost_milli'] !== null ? number_format($r['cost_milli'] / 1_000_000, 2, ',', '.') : '',
            $r['margin_milli'] !== null ? number_format($r['margin_milli'] / 1_000_000, 2, ',', '.') : '',
        ], $data['rows']);

        return [$headers, $rows, "deckungsbeitrag_{$from}_{$to}.csv"];
    }

    private function buildDepositCsv(int $companyId, string $from, string $to): array
    {
        $data = $this->depositService->summary($companyId, $from, $to);
        $headers = ['Rechnungsnummer', 'Datum', 'Typ', 'Beschreibung', 'Betrag (EUR)'];
        $rows = array_map(static fn (array $r): array => [
            $r['invoice_number'],
            $r['finalized_at'],
            $r['type'] === 'pfand_rein' ? 'Pfand rein' : 'Pfand raus',
            $r['description'],
            number_format($r['amount_milli'] / 1_000_000, 2, ',', '.'),
        ], $data['rows']);

        return [$headers, $rows, "pfandkonto_{$from}_{$to}.csv"];
    }

    private function buildToursCsv(int $companyId, string $from, string $to): array
    {
        $data = $this->tourKpiService->summary($companyId, $from, $to);
        $headers = ['Tour-ID', 'Datum', 'Status', 'Stopps gesamt', 'Abgeschlossen', 'Uebersprungen', 'O Stopp (min)'];
        $rows = array_map(static fn (array $r): array => [
            $r['tour_id'],
            $r['tour_date'],
            $r['status'],
            $r['total_stops'],
            $r['finished_stops'],
            $r['skipped_stops'],
            $r['avg_stop_min'] !== null ? number_format($r['avg_stop_min'], 1, ',', '.') : '',
        ], $data['rows']);

        return [$headers, $rows, "touren_{$from}_{$to}.csv"];
    }
}
