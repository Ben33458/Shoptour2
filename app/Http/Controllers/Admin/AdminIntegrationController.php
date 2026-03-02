<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Invoice;
use App\Models\Pricing\AppSetting;
use App\Services\Integrations\LexofficeSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminIntegrationController extends Controller
{
    /**
     * Show the Lexoffice integration settings page.
     */
    public function lexofficeIndex(): View
    {
        $settings = [
            'enabled' => AppSetting::get('lexoffice.enabled', '0') === '1',
            'api_key' => AppSetting::get('lexoffice.api_key', ''),
        ];

        // Show the 10 most recently synced/failed invoices for status overview
        $recentInvoices = Invoice::whereNotNull('lexoffice_synced_at')
            ->orWhereNotNull('lexoffice_sync_error')
            ->orderByDesc('finalized_at')
            ->limit(10)
            ->get();

        return view('admin.integrations.lexoffice', compact('settings', 'recentInvoices'));
    }

    /**
     * Save Lexoffice integration settings.
     */
    public function lexofficeUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'api_key' => ['nullable', 'string', 'max:500'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        AppSetting::set('lexoffice.enabled', $request->boolean('enabled') ? '1' : '0');
        AppSetting::set('lexoffice.api_key', $validated['api_key'] ?? '');

        return redirect()
            ->route('admin.integrations.lexoffice')
            ->with('success', 'Lexoffice-Einstellungen gespeichert.');
    }

    /**
     * Manually re-sync a single invoice to Lexoffice.
     */
    public function lexofficeSync(Request $request, Invoice $invoice): RedirectResponse
    {
        try {
            app(LexofficeSync::class)->syncInvoice($invoice);
            $message = "Rechnung #{$invoice->invoice_number} erfolgreich synchronisiert.";
        } catch (\Throwable $e) {
            $message = "Sync fehlgeschlagen: {$e->getMessage()}";
            return redirect()
                ->route('admin.integrations.lexoffice')
                ->with('error', $message);
        }

        return redirect()
            ->route('admin.integrations.lexoffice')
            ->with('success', $message);
    }
}
