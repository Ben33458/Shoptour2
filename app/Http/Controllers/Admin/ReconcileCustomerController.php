<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pricing\Customer;
use App\Models\Pricing\CustomerGroup;
use App\Models\SourceMatch;
use App\Services\Ninox\NinoxContactSyncService;
use App\Services\Reconcile\CustomerDataSyncService;
use App\Services\Reconcile\CustomerReconcileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReconcileCustomerController extends Controller
{
    public function __construct(
        private readonly CustomerReconcileService $service,
        private readonly NinoxContactSyncService  $contactSync,
    ) {}

    /**
     * Show all Ninox/WaWi/Lexoffice customers with match proposals.
     * Supports ?source=ninox|wawi|lexoffice and ?filter=unmatched|auto|confirmed|ignored
     */
    public function index(Request $request): View
    {
        $source = $request->input('source', 'ninox');
        $filter = $request->input('filter', 'unmatched');
        $sort   = $request->input('sort', 'confidence');
        $dir    = $request->input('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $search = trim((string) $request->input('search', ''));

        $stats = $this->service->stats($source);

        $filters = match ($filter) {
            'unmatched' => ['unmatched_only' => true],
            'auto'      => ['status' => 'auto'],
            'confirmed' => ['status' => 'confirmed'],
            'ignored'   => ['status' => 'ignored'],
            default     => [],
        };

        if ($filter === 'all') {
            $proposals = array_slice($this->service->proposeMatches($source), 0, 200);
            $truncated = true;
        } else {
            $proposals = $this->service->proposeMatches($source, $filters);
            $truncated = false;
        }

        if ($search !== '') {
            $q         = mb_strtolower($search);
            $proposals = array_values(array_filter($proposals, function (array $p) use ($q, $source): bool {
                $d = $p['source_data'];

                if ($source === 'ninox') {
                    $name = ($d['firmenname'] ?? '') ?: (($d['vorname'] ?? '') . ' ' . ($d['nachname'] ?? ''));
                    $mail = $d['e_mail'] ?? '';
                    $knr  = $d['kundennummer'] ?? '';
                } elseif ($source === 'wawi') {
                    $name = ($d['cFirma'] ?? '') ?: (($d['cVorname'] ?? '') . ' ' . ($d['cNachname'] ?? ''));
                    $mail = $d['cMail'] ?? '';
                    $knr  = $d['cKundenNr'] ?? '';
                } else {
                    // lexoffice
                    $name = $d['company_name'] ?? '';
                    $mail = $d['primary_email'] ?? '';
                    $knr  = $this->service->extractLexofficeCustomerNumber($name) ?? '';
                }

                $name = mb_strtolower($name);
                $mail = mb_strtolower($mail);
                $knr  = mb_strtolower($knr);

                return str_contains($name, $q) || str_contains($mail, $q) || str_contains($knr, $q);
            }));
        }

        usort($proposals, function (array $a, array $b) use ($sort, $dir, $source): int {
            $valA = $this->sortValue($sort, $source, $a['source_data']);
            $valB = $this->sortValue($sort, $source, $b['source_data']);

            if ($sort === 'confidence') {
                $valA = $a['confidence'];
                $valB = $b['confidence'];
            }

            if ($valA === $valB) {
                return 0;
            }
            $cmp = $valA <=> $valB;
            return $dir === 'asc' ? $cmp : -$cmp;
        });

        return view('admin.reconcile.customers', compact(
            'proposals', 'source', 'stats', 'filter', 'truncated', 'sort', 'dir', 'search'
        ));
    }

    /**
     * Bestätigt alle Auto-Matches deren Konfidenz >= min_confidence ist (Standard 95 %).
     */
    public function confirmAllAbove(Request $request): RedirectResponse
    {
        $request->validate([
            'source'         => 'required|in:ninox,wawi,lexoffice',
            'min_confidence' => 'nullable|integer|min:50|max:100',
        ]);

        $threshold = (int) $request->input('min_confidence', 95);
        $count     = $this->service->confirmAllAbove($request->source, $request->user()->id, $threshold);

        if ($request->source === 'ninox' && $count > 0) {
            $this->contactSync->relinkUnresolved();
        }

        return back()->with('success', sprintf(
            '%d Match%s mit Konfidenz ≥ %d %% bestätigt.',
            $count,
            $count === 1 ? '' : 'es',
            $threshold,
        ));
    }

    /** @deprecated Route-Kompatibilität — leitet weiter an confirmAllAbove mit 100 % */
    public function confirmAll100(Request $request): RedirectResponse
    {
        $request->merge(['min_confidence' => 100]);
        return $this->confirmAllAbove($request);
    }

    /**
     * Auto-match all external records with confidence >= threshold.
     */
    public function autoMatch(Request $request): RedirectResponse
    {
        $request->validate([
            'source'         => 'required|in:ninox,wawi,lexoffice',
            'min_confidence' => 'nullable|integer|min:50|max:100',
        ]);

        $result = $this->service->autoMatchAll(
            $request->source,
            (int) $request->input('min_confidence', 90),
        );

        if ($request->source === 'ninox' && $result['auto_matched'] > 0) {
            $this->contactSync->relinkUnresolved();
        }

        return back()->with('success', sprintf(
            'Auto-Abgleich: %d verknüpft, %d zu unsicher (< %d %%), %d bereits erledigt.',
            $result['auto_matched'],
            $result['skipped'],
            $request->input('min_confidence', 90),
            $result['already_done'],
        ));
    }

    /**
     * Confirm a single match.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validate([
            'source'      => 'required|in:ninox,wawi,lexoffice',
            'source_id'   => 'required|string',
            'customer_id' => 'required|integer|exists:customers,id',
        ]);

        try {
            $this->service->confirm(
                $request->source,
                $request->source_id,
                (int) $request->customer_id,
                $request->user()->id,
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($request->source === 'ninox') {
            $this->contactSync->relinkUnresolved();
        }

        return back()->with('success', 'Verknüpfung bestätigt und Kundendaten aktualisiert.');
    }

    /**
     * Create a new local customer from an external record and confirm the match.
     * Uses the source's own customer_number when available and unique; generates K-number otherwise.
     */
    public function createFrom(Request $request): RedirectResponse
    {
        $request->validate([
            'source'    => 'required|in:ninox,wawi,lexoffice',
            'source_id' => 'required|string',
        ]);

        $source   = $request->source;
        $sourceId = $request->source_id;

        $proposals = $this->service->proposeMatches($source);
        $proposal  = collect($proposals)->firstWhere('source_id', $sourceId);

        if (! $proposal) {
            return back()->with('error', 'Quelldatensatz nicht gefunden.');
        }

        $data         = $proposal['source_data'];
        $defaultGroup = CustomerGroup::first();

        // Kundennummer aus Quelle verwenden, falls vorhanden und eindeutig
        $customerNumber = $this->resolveCustomerNumber($source, $data);

        if ($source === 'ninox') {
            $customerData = [
                'customer_group_id'  => $defaultGroup?->id ?? 1,
                'customer_number'    => $customerNumber,
                'company_name'       => $data['firmenname'] ?? null,
                'first_name'         => $data['vorname'] ?? null,
                'last_name'          => $data['nachname'] ?? null,
                'email'              => $data['e_mail'] ?? null,
                'phone'              => $data['telefon'] ?? null,
                'active'             => true,
                'price_display_mode' => 'gross',
            ];
        } elseif ($source === 'wawi') {
            $customerData = [
                'customer_group_id'  => $defaultGroup?->id ?? 1,
                'customer_number'    => $customerNumber,
                'company_name'       => $data['cFirma'] ?? null,
                'first_name'         => $data['cVorname'] ?? null,
                'last_name'          => $data['cNachname'] ?? null,
                'email'              => $data['cMail'] ?? null,
                'phone'              => $data['cTel'] ?? null,
                'active'             => true,
                'price_display_mode' => 'gross',
            ];
        } else {
            // lexoffice
            $cleanName = $this->service->extractLexofficeCustomerNumber($data['company_name'] ?? '')
                ? preg_replace('/\s+(K\d{4,6}|\d{5,6})\s*$/i', '', $data['company_name'] ?? '')
                : ($data['company_name'] ?? null);

            $customerData = [
                'customer_group_id'  => $defaultGroup?->id ?? 1,
                'customer_number'    => $customerNumber,
                'company_name'       => trim((string) $cleanName) ?: null,
                'first_name'         => $data['first_name'] ?? null,
                'last_name'          => $data['last_name'] ?? null,
                'email'              => $data['primary_email'] ?? null,
                'phone'              => $data['primary_phone'] ?? null,
                'active'             => true,
                'price_display_mode' => 'gross',
            ];
        }

        $customer = Customer::create($customerData);
        $this->service->confirm($source, $sourceId, $customer->id, $request->user()->id);

        if ($source === 'ninox') {
            $this->contactSync->relinkUnresolved();
        }

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', 'Neuer Kunde angelegt und verknüpft.');
    }

    /**
     * Datensatz ablehnen (kein Import).
     */
    public function ignore(Request $request): RedirectResponse
    {
        $request->validate([
            'source'    => 'required|in:ninox,wawi,lexoffice',
            'source_id' => 'required|string',
        ]);

        $this->service->ignore($request->source, $request->source_id, $request->user()->id);

        return back()->with('success', 'Datensatz abgelehnt.');
    }

    /**
     * Synchronisiert Felder (Name, E-Mail, Telefon) aller verknüpften Kunden
     * aus den Import-Tabellen in die customers-Tabelle.
     * Neuester Wert gewinnt pro Feld. Bestehende Werte werden überschrieben.
     */
    public function syncAll(Request $request): RedirectResponse
    {
        $syncer = app(CustomerDataSyncService::class);

        $customers = Customer::where(function ($q): void {
            $q->whereNotNull('ninox_kunden_id')
              ->orWhereNotNull('wawi_kunden_id')
              ->orWhereNotNull('lexoffice_contact_id');
        })->get();

        $synced  = 0;
        $changed = 0;

        foreach ($customers as $customer) {
            $diff = $syncer->sync($customer);
            $synced++;
            if (! empty($diff)) {
                $changed++;
            }
        }

        return back()->with('success', sprintf(
            'Sync abgeschlossen: %d Kunden geprüft, bei %d Kunden Felder aktualisiert.',
            $synced,
            $changed,
        ));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Ermittelt die Kundennummer aus dem Quelldatensatz.
     * Verwendet die Quell-Nummer wenn vorhanden und eindeutig, generiert sonst K-Nummer.
     */
    private function resolveCustomerNumber(string $source, array $data): string
    {
        $sourceNumber = match ($source) {
            'ninox'     => $this->cleanSourceNumber($data['kundennummer'] ?? null),
            'wawi'      => $this->cleanSourceNumber($data['cKundenNr'] ?? null),
            'lexoffice' => $this->service->extractLexofficeCustomerNumber($data['company_name'] ?? ''),
            default     => null,
        };

        if ($sourceNumber && Customer::where('customer_number', $sourceNumber)->doesntExist()) {
            return $sourceNumber;
        }

        return $this->generateCustomerNumber();
    }

    /**
     * Bereinigt eine rohe Kundennummer — gibt null zurück für leere, "0", "0.0".
     */
    private function cleanSourceNumber(mixed $val): ?string
    {
        if ($val === null) {
            return null;
        }
        $s = trim((string) $val);
        return ($s === '' || $s === '0' || $s === '0.0') ? null : $s;
    }

    private function generateCustomerNumber(): string
    {
        $max = Customer::max('customer_number');

        if (! $max || ! preg_match('/K(\d+)/', (string) $max, $m)) {
            return 'K0001';
        }

        return 'K' . str_pad((string) ((int) $m[1] + 1), 4, '0', STR_PAD_LEFT);
    }

    private function sortValue(string $sort, string $source, array $data): mixed
    {
        if ($sort === 'email') {
            return mb_strtolower((string) match ($source) {
                'ninox'     => $data['e_mail'] ?? '',
                'wawi'      => $data['cMail'] ?? '',
                'lexoffice' => $data['primary_email'] ?? '',
                default     => '',
            });
        }

        if ($sort === 'name') {
            $raw = match ($source) {
                'ninox'     => ($data['firmenname'] ?? '') ?: (($data['vorname'] ?? '') . ' ' . ($data['nachname'] ?? '')),
                'wawi'      => ($data['cFirma'] ?? '') ?: (($data['cVorname'] ?? '') . ' ' . ($data['cNachname'] ?? '')),
                'lexoffice' => $data['company_name'] ?? '',
                default     => '',
            };
            return mb_strtolower($raw);
        }

        return 0; // confidence is handled in the caller
    }
}
