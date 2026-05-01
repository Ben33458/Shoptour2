<?php

declare(strict_types=1);

namespace App\Services\Reconcile;

use App\Models\Address;
use App\Models\Pricing\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Synchronisiert Kundendaten aus Import-Tabellen (Ninox, WaWi, Lexoffice) nach `customers`.
 *
 * Prinzip "neuester Wert gewinnt":
 *   - Jede verknüpfte Quelle liefert einen Timestamp (ninox_updated_at, wawi.updated_at, synced_at).
 *   - Pro Feld gewinnt der erste nicht-leere Wert der nach Timestamp desc sortierten Quellen.
 *   - Bei gleichem oder fehlendem Timestamp: Lexoffice > Ninox > WaWi.
 *
 * Regeln:
 *   - Schreibt NUR in `customers` (nie in ninox_*, wawi_*, lexoffice_*).
 *   - customer_number wird NICHT überschrieben wenn bereits gesetzt (verhindert Regression).
 *   - Null/leer/"0" aus Import-Tabellen wird als "kein Wert" behandelt.
 *
 * @return array<string, mixed>  Geänderte Felder (feldname => neuer Wert)
 */
class CustomerDataSyncService
{
    /**
     * Synchronisiert alle verfügbaren Quelldaten in den Kunden-Datensatz.
     *
     * @return array<string, mixed>  Tatsächlich geänderte Felder
     */
    public function sync(Customer $customer): array
    {
        $candidates = $this->buildCandidates($customer);

        if (empty($candidates)) {
            return [];
        }

        // Kandidaten nach Timestamp desc sortieren (NULL = ältestes)
        usort($candidates, static function (array $a, array $b): int {
            $ta = $a['timestamp'];
            $tb = $b['timestamp'];
            if ($ta === null && $tb === null) {
                return $a['priority'] - $b['priority'];
            }
            if ($ta === null) {
                return 1;
            }
            if ($tb === null) {
                return -1;
            }
            // Gleicher Timestamp → Fallback-Priorität
            if ($ta === $tb) {
                return $a['priority'] - $b['priority'];
            }
            return $ta < $tb ? 1 : -1;
        });

        $updates = [];

        // Pro Feld: erster nicht-leerer Wert gewinnt
        foreach (['company_name', 'first_name', 'last_name', 'email', 'phone',
                  'billing_email', 'notification_email'] as $field) {
            foreach ($candidates as $c) {
                $val = $c['fields'][$field] ?? null;
                if ($val !== null && $val !== '' && $val !== '0') {
                    $current = $customer->$field;
                    if (($current === null || $current === '') && $val !== $current) {
                        $updates[$field] = $val;
                    } elseif ($current !== null && $current !== '' && $val !== $current) {
                        // Nur überschreiben wenn der neue Wert "neuer" ist (erster Kandidat)
                        $updates[$field] = $val;
                    }
                    break;
                }
            }
        }

        // customer_number: NUR setzen wenn aktuell leer
        if (! $customer->customer_number) {
            foreach ($candidates as $c) {
                $val = $c['fields']['customer_number'] ?? null;
                if ($val !== null && $val !== '' && $val !== '0') {
                    // Eindeutigkeitsprüfung
                    $taken = Customer::where('customer_number', $val)
                        ->where('id', '!=', $customer->id)
                        ->exists();
                    if (! $taken) {
                        $updates['customer_number'] = $val;
                    }
                    break;
                }
            }
        }

        // Nur bei tatsächlicher Änderung speichern
        $changed = [];
        foreach ($updates as $field => $newVal) {
            if ($customer->$field !== $newVal) {
                $changed[$field] = $newVal;
            }
        }

        if (! empty($changed)) {
            $customer->update($changed);
        }

        // Adressen anlegen wenn noch keine vorhanden
        $this->syncAddresses($customer->fresh() ?? $customer);

        return $changed;
    }

    /**
     * Legt Rechnungs- und Lieferadresse aus Import-Tabellen an,
     * wenn der Kunde noch keine Adressen hat.
     *
     * Quell-Priorität: WaWi (nStandard=1) > Ninox > Lexoffice
     * Ist nur eine Adresse verfügbar, wird sie für beide Typen verwendet.
     * Vorhandene Adressen werden nicht überschrieben.
     */
    public function syncAddresses(Customer $customer): int
    {
        // Nicht anlegen wenn bereits Adressen vorhanden
        $existing = DB::table('addresses')->where('customer_id', $customer->id)->count();
        if ($existing > 0) {
            return 0;
        }

        $addr = $this->buildAddressFromSources($customer);

        if ($addr === null) {
            return 0;
        }

        $base = array_merge($addr, ['customer_id' => $customer->id, 'is_default' => true]);

        Address::create(array_merge($base, ['type' => 'billing']));
        Address::create(array_merge($base, ['type' => 'delivery']));

        return 2;
    }

    /**
     * Ermittelt die beste verfügbare Adresse aus den verknüpften Quellen.
     * Gibt null zurück wenn keine verwertbare Adresse gefunden wurde.
     *
     * @return array<string,mixed>|null
     */
    private function buildAddressFromSources(Customer $customer): ?array
    {
        // ── WaWi (bevorzugt, vollständigste Daten) ────────────────────────────
        if ($customer->wawi_kunden_id) {
            $row = DB::table('wawi_dbo_tadresse')
                ->where('kKunde', $customer->wawi_kunden_id)
                ->where('nStandard', 1)
                ->first();

            if ($row && $this->clean($row->cStrasse ?? null) && $this->clean($row->cPLZ ?? null)) {
                [$street, $houseNr] = $this->splitStreet((string) $row->cStrasse);
                return [
                    'company'      => $this->clean($row->cFirma ?? null),
                    'first_name'   => $this->clean($row->cVorname ?? null),
                    'last_name'    => $this->clean($row->cName ?? null),
                    'street'       => $street,
                    'house_number' => $houseNr,
                    'zip'          => $this->clean($row->cPLZ ?? null),
                    'city'         => $this->clean($row->cOrt ?? null),
                    'country_code' => $this->clean($row->cISO ?? null) ?? 'DE',
                    'phone'        => $this->clean($row->cTel ?? null),
                ];
            }
        }

        // ── Ninox ─────────────────────────────────────────────────────────────
        if ($customer->ninox_kunden_id) {
            $row = DB::table('ninox_kunden')
                ->where('ninox_id', $customer->ninox_kunden_id)
                ->first();

            if ($row && $this->clean($row->strasse_hausnummer ?? null) && $this->clean($row->plz ?? null)) {
                [$street, $houseNr] = $this->splitStreet((string) $row->strasse_hausnummer);
                return [
                    'company'      => $this->clean($row->firmenname ?? null),
                    'first_name'   => $this->clean($row->vorname ?? null),
                    'last_name'    => $this->clean($row->nachname ?? null),
                    'street'       => $street,
                    'house_number' => $houseNr,
                    'zip'          => $this->clean($row->plz ?? null),
                    'city'         => $this->clean($row->ort ?? null),
                    'country_code' => 'DE',
                    'phone'        => $this->clean($row->telefon ?? null),
                ];
            }
        }

        // Lexoffice enthält keine vollständigen Adressdaten (nur country_code)
        return null;
    }

    /**
     * Trennt "Musterstraße 12a" in ["Musterstraße", "12a"].
     * Gibt [$full, null] zurück wenn kein Hausnummern-Muster erkannt wird.
     *
     * @return array{0:string, 1:string|null}
     */
    private function splitStreet(string $full): array
    {
        $full = trim($full);
        // Hausnummer am Ende: Zahl ggf. mit Buchstabe/Zusatz (12, 12a, 12-14, 12 b)
        if (preg_match('/^(.+?)\s+(\d+\s*[a-zA-Z]?(?:\s*[-\/]\s*\d+\s*[a-zA-Z]?)*)\s*$/', $full, $m)) {
            return [trim($m[1]), trim($m[2])];
        }
        return [$full, null];
    }

    /**
     * Baut die Liste der Kandidaten (Datenquellen) für den Kunden auf.
     *
     * @return list<array{timestamp:string|null, priority:int, fields:array<string,mixed>}>
     */
    private function buildCandidates(Customer $customer): array
    {
        $candidates = [];

        // ── Ninox ────────────────────────────────────────────────────────────
        if ($customer->ninox_kunden_id) {
            $row = DB::table('ninox_kunden')
                ->where('ninox_id', $customer->ninox_kunden_id)
                ->first();

            if ($row) {
                $candidates[] = [
                    'timestamp' => $row->ninox_updated_at ?? null,
                    'priority'  => 2, // Ninox = mittlere Priorität
                    'fields'    => [
                        'company_name'       => $this->clean($row->firmenname ?? null),
                        'first_name'         => $this->clean($row->vorname ?? null),
                        'last_name'          => $this->clean($row->nachname ?? null),
                        'email'              => $this->clean($row->e_mail ?? null),
                        'phone'              => $this->clean($row->telefon ?? null),
                        'customer_number'    => $this->cleanNumber($row->kundennummer ?? null),
                        'billing_email'      => $this->clean($row->email_fuer_rechnungen ?? null),
                        'notification_email' => $this->clean($row->email_fuer_lieferbenachrichtigung ?? null),
                        'kunde_von'          => match(strtolower(trim($row->kunde_von ?? ''))) {
                            'kehr'    => 'kehr',
                            'kolabri' => 'kolabri',
                            default   => null,
                        },
                    ],
                ];
            }
        }

        // ── WaWi ─────────────────────────────────────────────────────────────
        if ($customer->wawi_kunden_id) {
            $row = DB::table('wawi_kunden as wk')
                ->leftJoin(
                    DB::raw(
                        '(SELECT kKunde,
                                 MIN(cFirma)       AS cFirma,
                                 MIN(cVorname)     AS cVorname,
                                 MIN(cName)        AS cNachname,
                                 MIN(cMail)        AS cMail,
                                 MIN(cTel)         AS cTel
                          FROM wawi_dbo_tadresse
                          WHERE nStandard = 1
                          GROUP BY kKunde) AS a'
                    ),
                    'a.kKunde',
                    '=',
                    'wk.kKunde'
                )
                ->select('wk.kKunde', 'wk.cKundenNr', 'wk.updated_at', 'a.cFirma', 'a.cVorname', 'a.cNachname', 'a.cMail', 'a.cTel')
                ->where('wk.kKunde', $customer->wawi_kunden_id)
                ->first();

            if ($row) {
                $candidates[] = [
                    'timestamp' => $row->updated_at ?? null,
                    'priority'  => 3, // WaWi = niedrigste Priorität
                    'fields'    => [
                        'company_name'    => $this->clean($row->cFirma ?? null),
                        'first_name'      => $this->clean($row->cVorname ?? null),
                        'last_name'       => $this->clean($row->cNachname ?? null),
                        'email'           => $this->clean($row->cMail ?? null),
                        'phone'           => $this->clean($row->cTel ?? null),
                        'customer_number' => $this->cleanNumber($row->cKundenNr ?? null),
                    ],
                ];
            }
        }

        // ── Lexoffice ────────────────────────────────────────────────────────
        if ($customer->lexoffice_contact_id) {
            $row = DB::table('lexoffice_contacts')
                ->where('lexoffice_uuid', $customer->lexoffice_contact_id)
                ->first();

            if ($row) {
                // Kundennummer aus company_name extrahieren
                $lexKnr = $this->extractLexofficeCustomerNumber($row->company_name ?? '');

                // Bereinigter Firmenname (ohne trailing Kundennummer)
                $cleanName = $this->cleanLexofficeCompanyName($row->company_name ?? '');

                $candidates[] = [
                    'timestamp' => $row->synced_at ?? null,
                    'priority'  => 1, // Lexoffice = höchste Priorität
                    'fields'    => [
                        'company_name'    => $cleanName ?: null,
                        'first_name'      => $this->clean($row->first_name ?? null),
                        'last_name'       => $this->clean($row->last_name ?? null),
                        'email'           => $this->clean($row->primary_email ?? null),
                        'phone'           => $this->clean($row->primary_phone ?? null),
                        'customer_number' => $lexKnr,
                    ],
                ];
            }
        }

        return $candidates;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Trimmt und gibt null zurück wenn leer oder "0". */
    private function clean(mixed $val): ?string
    {
        if ($val === null) {
            return null;
        }
        $s = trim((string) $val);
        return ($s === '' || $s === '0') ? null : $s;
    }

    /**
     * Wie clean(), aber prüft zusätzlich auf "0.0" und rein numerisch "0".
     * Gibt null zurück wenn der Wert keine sinnvolle Kundennummer darstellt.
     */
    private function cleanNumber(mixed $val): ?string
    {
        $s = $this->clean($val);
        if ($s === null) {
            return null;
        }
        return ($s === '0.0') ? null : $s;
    }

    private function extractLexofficeCustomerNumber(string $name): ?string
    {
        if (preg_match('/\s+(K\d{4,6})\s*$/i', $name, $m)) {
            return strtoupper(trim($m[1]));
        }
        if (preg_match('/\s+(\d{5,6})\s*$/', $name, $m)) {
            return $m[1];
        }
        return null;
    }

    private function cleanLexofficeCompanyName(string $name): string
    {
        $name = preg_replace('/\s+K\d{4,6}\s*$/i', '', $name);
        $name = preg_replace('/\s+\d{5,6}\s*$/', '', $name);
        return trim($name);
    }
}
