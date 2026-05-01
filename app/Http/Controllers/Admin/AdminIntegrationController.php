<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Invoice;
use App\Models\Admin\LexofficeVoucher;
use App\Models\Pricing\AppSetting;
use App\Models\Pricing\Customer;
use App\Models\Supplier\Supplier;
use App\Models\Integrations\LexofficeImportRun;
use App\Services\Integrations\LexofficeImport;
use App\Services\Integrations\LexofficePull;
use App\Services\Integrations\LexofficeSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminIntegrationController extends Controller
{
    /**
     * Show the Lexoffice integration settings page.
     */
    public function lexofficeIndex(): View
    {
        // Show the 10 most recently synced/failed invoices for status overview
        $recentInvoices = Invoice::whereNotNull('lexoffice_synced_at')
            ->orWhereNotNull('lexoffice_sync_error')
            ->orderByDesc('finalized_at')
            ->limit(10)
            ->get();

        $lastImportRun = LexofficeImportRun::latest('created_at')->first();

        return view('admin.integrations.lexoffice', compact('recentInvoices', 'lastImportRun'));
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

    /**
     * Pull all customer contacts from Lexoffice and match them to local customers.
     */
    public function lexofficePullCustomers(): RedirectResponse
    {
        set_time_limit(300); // allow up to 5 min for large contact lists

        $companyId = App::make('current_company')?->id;

        try {
            $stats = app(LexofficePull::class)->pullCustomers($companyId);
            $msg = "Kunden-Import abgeschlossen: {$stats['total_lexoffice']} Lexoffice-Kontakte — "
                 . "{$stats['matched']} zugeordnet ({$stats['contact_id_assigned']} neu verknüpft), "
                 . "{$stats['created']} neu angelegt.";
            return redirect()->route('admin.integrations.lexoffice')->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()->route('admin.integrations.lexoffice')
                ->with('error', 'Kunden-Import fehlgeschlagen: ' . $e->getMessage());
        }
    }

    /**
     * Pull all vendor contacts from Lexoffice and match them to local suppliers.
     */
    public function lexofficePullSuppliers(): RedirectResponse
    {
        set_time_limit(300);

        $companyId = App::make('current_company')?->id;

        try {
            $stats = app(LexofficePull::class)->pullSuppliers($companyId);
            $msg = "Lieferanten-Import abgeschlossen: {$stats['total_lexoffice']} Lexoffice-Kontakte — "
                 . "{$stats['matched']} zugeordnet ({$stats['contact_id_assigned']} neu verknüpft), "
                 . "{$stats['created']} neu angelegt.";
            return redirect()->route('admin.integrations.lexoffice')->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()->route('admin.integrations.lexoffice')
                ->with('error', 'Lieferanten-Import fehlgeschlagen: ' . $e->getMessage());
        }
    }

    /**
     * Run the same incremental sync as the hourly cron job (contacts + vouchers).
     * Fires the artisan command in the background to avoid gateway timeouts.
     */
    public function lexofficeRunSync(): RedirectResponse
    {
        $artisan = base_path('artisan');
        $log     = storage_path('logs/lexoffice-sync-manual.log');

        exec("php {$artisan} lexoffice:sync >> {$log} 2>&1 &");

        return back()->with('success',
            'Sync gestartet — läuft im Hintergrund. Ergebnis in storage/logs/lexoffice-sync-manual.log.'
        );
    }

    /**
     * Pull all vouchers from Lexoffice.
     */
    public function lexofficePullVouchers(): RedirectResponse
    {
        set_time_limit(300);
        ignore_user_abort(true); // keep running even if the gateway proxy times out

        $companyId = App::make('current_company')?->id;

        try {
            $stats = app(LexofficePull::class)->pullVouchers($companyId);
            $msg = "Belege importiert: {$stats['total_lexoffice']} Lexoffice-Belege — "
                 . "{$stats['created']} neu, {$stats['updated']} aktualisiert"
                 . ($stats['errors'] > 0 ? ", {$stats['errors']} Fehler" : '') . ".";
            return redirect()->route('admin.integrations.lexoffice')->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()->route('admin.integrations.lexoffice')
                ->with('error', 'Belege-Import fehlgeschlagen: ' . $e->getMessage());
        }
    }

    /**
     * Delete all Lexoffice-imported customers, suppliers, vouchers and blocks
     * so a clean re-import can be performed.
     */
    /**
     * POST /admin/integrations/lexoffice/import-all
     * Imports ALL Lexoffice data into lexoffice_* tables only.
     * Does NOT modify Shoptour domain tables.
     */
    public function lexofficeImportAll(): RedirectResponse
    {
        set_time_limit(300);

        $run = LexofficeImportRun::create([
            'status'     => LexofficeImportRun::STATUS_RUNNING,
            'started_at' => now(),
            'created_at' => now(),
        ]);

        try {
            $stats = app(LexofficeImport::class)->importAll(null);

            $run->update([
                'status'      => LexofficeImportRun::STATUS_DONE,
                'finished_at' => now(),
                'result_json' => $stats,
            ]);

            $c   = $stats['contacts'];
            $v   = $stats['vouchers'];
            $a   = $stats['articles'];
            $msg = sprintf(
                'Import abgeschlossen — Kontakte: %d neu, %d aktualisiert | Belege: %d neu, %d aktualisiert | Artikel: %d neu, %d aktualisiert | weitere Ressourcen importiert.',
                $c['created'], $c['updated'],
                $v['created'], $v['updated'],
                $a['created'], $a['updated'],
            );
        } catch (\Throwable $e) {
            $run->update([
                'status'        => LexofficeImportRun::STATUS_FAILED,
                'finished_at'   => now(),
                'error_message' => $e->getMessage(),
            ]);
            $msg = 'Import fehlgeschlagen: ' . $e->getMessage();
        }

        return back()->with('success', $msg);
    }

    /**
     * POST /admin/integrations/lexoffice/reconcile
     * Links lexoffice_contacts to local customers/suppliers.
     * Creates new records for unmatched contacts.
     */
    public function lexofficeReconcile(): RedirectResponse
    {
        set_time_limit(300);
        $stats = app(LexofficeImport::class)->reconcileContacts(null, createMissing: true);

        $msg = sprintf(
            'Abgleich abgeschlossen — Kunden: %d verknüpft, %d neu erstellt | Lieferanten: %d verknüpft, %d neu erstellt.',
            $stats['matched_customers'],
            $stats['created_customers'],
            $stats['matched_suppliers'],
            $stats['created_suppliers'],
        );

        return back()->with('success', $msg);
    }

    public function lexofficeResetImported(): RedirectResponse
    {
        DB::table('customer_notes')
            ->whereIn('customer_id', Customer::whereNotNull('lexoffice_contact_id')->select('id'))
            ->delete();
        DB::table('contacts')
            ->where('contactable_type', 'App\\Models\\Pricing\\Customer')
            ->whereIn('contactable_id', Customer::whereNotNull('lexoffice_contact_id')->select('id'))
            ->delete();
        DB::table('contacts')
            ->where('contactable_type', 'App\\Models\\Supplier\\Supplier')
            ->whereIn('contactable_id', Supplier::whereNotNull('lexoffice_contact_id')->select('id'))
            ->delete();

        $customers = Customer::whereNotNull('lexoffice_contact_id')->count();
        $suppliers = Supplier::whereNotNull('lexoffice_contact_id')->count();

        Customer::whereNotNull('lexoffice_contact_id')->delete();
        Supplier::whereNotNull('lexoffice_contact_id')->delete();
        LexofficeVoucher::truncate();
        DB::table('lexoffice_contact_blocks')->delete();

        $msg = "Zurückgesetzt: {$customers} Kunden, {$suppliers} Lieferanten und alle Belege gelöscht. Bereit für neuen Import.";

        return redirect()->route('admin.integrations.lexoffice')->with('success', $msg);
    }

    /**
     * POST /admin/integrations/lexoffice/import-payments
     * Fetches payment history for all non-draft/non-voided vouchers from Lexoffice
     * and stores individual payment records in lexoffice_payments.
     */
    public function lexofficeImportPayments(): RedirectResponse
    {
        set_time_limit(120);

        // Process 30 vouchers per click (~18 s) to stay within gateway timeout.
        // Re-click until remaining = 0. For a full run use: php artisan lexoffice:import-payments
        $stats = app(LexofficeImport::class)->importPayments(null, limit: 30);

        $msg = sprintf(
            'Zahlungen: %d verarbeitet — %d neu, %d aktualisiert, %d ohne Zahlung, %d Fehler.',
            $stats['processed'],
            $stats['created'],
            $stats['updated'],
            $stats['skipped'],
            $stats['errors'],
        );

        if ($stats['remaining'] > 0) {
            $msg .= sprintf(' Noch %d Belege ausstehend — Button erneut klicken.', $stats['remaining']);
        } else {
            $msg .= ' Alle Belege abgerufen.';
        }

        return back()->with('success', $msg);
    }

    /**
     * Pull payment status for all synced invoices from Lexoffice.
     */
    public function lexofficePullPayments(): RedirectResponse
    {
        set_time_limit(300);

        try {
            $stats = app(LexofficePull::class)->pullPaymentStatus();
            $msg = "Zahlungsstatus aktualisiert: {$stats['updated']} aktualisiert, "
                 . "{$stats['already_up_to_date']} bereits aktuell, "
                 . "{$stats['not_found']} nicht gefunden, "
                 . "{$stats['errors']} Fehler.";
            return redirect()->route('admin.integrations.lexoffice')->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()->route('admin.integrations.lexoffice')
                ->with('error', 'Zahlungsstatus-Abruf fehlgeschlagen: ' . $e->getMessage());
        }
    }
}
