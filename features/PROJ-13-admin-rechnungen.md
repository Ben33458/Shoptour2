# PROJ-13: Admin: Rechnungen

## Status: Planned
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- Requires: PROJ-12 (Admin: Bestellverwaltung) — Rechnungen basieren auf Bestellungen
- Requires: PROJ-8 (Zahlungsabwicklung) — Zahlungen werden auf Rechnungen verbucht

## Beschreibung
Vollständiges Rechnungs-System: Draft-Generierung aus Bestellungen (inkl. Fulfillment-Daten und Leergut-Adjustments), Finalisierung (unveränderlich, Rechnungsnummer), PDF-Generierung (DomPDF), Email-Versand, Lexoffice-Synchronisation, Zahlungserfassung, Mahnstatus.

## User Stories
- Als Mitarbeiter möchte ich für eine abgeschlossene Bestellung einen Rechnungs-Draft erstellen.
- Als Mitarbeiter möchte ich einen Draft prüfen und finalisieren (macht die Rechnung unveränderlich).
- Als System soll nach Finalisierung automatisch eine PDF erstellt und per Email versendet werden.
- Als Admin möchte ich eine finalisierte Rechnung mit Lexoffice synchronisieren.
- Als Mitarbeiter möchte ich manuelle Zahlungen auf eine Rechnung verbuchen.
- Als System soll der Rechnungsstatus (offen/bezahlt/überfällig) automatisch aktualisiert werden.
- Als Admin möchte ich eine Rechnungsliste mit Zahlungsstatus und Filtermöglichkeiten sehen.

## Acceptance Criteria
- [ ] **Draft-Erstellung:** aus Bestelldetail; übernimmt alle `order_items` als `invoice_items` (TYPE_PRODUCT); übernimmt `total_pfand_brutto_milli` als TYPE_DEPOSIT-Zeile; übernimmt alle `order_adjustments` als TYPE_ADJUSTMENT-Zeilen
- [ ] **Draft-Felder:** Rechnungsdatum (editierbar im Draft), Zahlungsziel (Tage, aus Einstellungen), Notiz
- [ ] **Finalisierung:** Rechnungsnummer wird vergeben (sequential, kein Race Condition: DB-Lock oder Sequence-Tabelle); Status → `finalized`; Datum der Finalisierung gespeichert; unveränderlich danach
- [ ] **Rechnungsnummer-Format:** konfigurierbar (z.B. `RE-2024-0001`)
- [ ] **PDF-Generierung:** DomPDF; Felder: Firmendaten, Kundendaten, Rechnungsnummer, Datum, Zahlungsziel, Positionen (Produkt, Menge, Einzelpreis netto, MwSt.-Satz, Zeilenpreis), MwSt.-Aufstellung, Gesamtbetrag, Bankdaten, Fußzeile
- [ ] **Email-Versand:** PDF als Anhang; wenn keine Email beim Kunden: Fallback-Email (`auftrag@...`, konfigurierbar in Einstellungen)
- [ ] **Lexoffice-Sync:** nicht-blockierend (try/catch, Fehler wird geloggt); `lexoffice_voucher_id` wird auf Rechnung gesetzt; erneutes Senden möglich
- [ ] **Zahlungserfassung (manuell):** Betrag, Datum, Methode, Notiz; Status aktualisiert sich automatisch
- [ ] **Rechnungsstatus:** `draft` → `finalized` → (Zahlungsstatus: offen/teilweise/bezahlt/überzahlt/überfällig)
- [ ] **Rechnungsliste:** Rechnungsnummer, Kunde, Datum, Betrag, Status; Filter nach Status, Datum, Kunde; CSV-Export
- [ ] **`tax_rate_basis_points` auf invoice_items:** Pflichtfeld, kein Fallback auf 19% (Bugfix aus Issue Log)
- [ ] **`cost_milli` auf invoice_items:** aus aktuellem aktiven Lieferanten-Produkt-Einkaufspreis (für Margenberechnung)

## Edge Cases
- `tax_rate_basis_points = NULL` auf einem OrderItem → Fehler werfen, Draft-Erstellung abbrechen (kein stiller Fallback!)
- Zwei parallele Finalisierungen gleichzeitig → Nur eine bekommt die Rechnungsnummer (DB-Lock)
- Rechnung nach Lexoffice-Fehler manuell neu senden → Erneuter Sync-Versuch
- Email-Versand schlägt fehl → Fehler wird geloggt, Rechnung bleibt finalisiert; manueller Resend möglich
- Rechnung für stornierte Bestellung → Storno-Rechnung (negativ) kann erstellt werden
- Gutschrift erstellen (negative Rechnung) → Als eigener Typ erkennbar

## Technical Requirements
- Rechnungsnummer-Vergabe: `SELECT FOR UPDATE` auf `invoice_numbers`-Sequence-Tabelle oder `LAST_INSERT_ID()` Pattern (kein `COUNT(*)+1`!)
- Email in `deferred_tasks` auslagern (kein synchroner Mail-Versand in `finalizeInvoice()`)
- PDF-Dateipfad in `invoices.pdf_path` gespeichert (kein inline PDF)
- Lexoffice-Sync ebenfalls in `deferred_tasks` (nicht-blockierend)

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur (UI-Baum)

```
/admin/rechnungen/
│
├── index           ← Rechnungsliste (Filter: Status, Datum, Kunde; CSV-Export)
└── {id}/           ← Rechnungsdetail
    ├── Draft-Ansicht (editierbar)
    │   ├── Rechnungsdatum, Zahlungsziel, Notiz
    │   └── [Finalisieren] → Bestätigungs-Modal
    └── Finalisiert (read-only)
        ├── Rechnungsnummer, Positionen, Gesamtsummen
        ├── [PDF herunterladen]
        ├── [Per Email senden]
        ├── [Lexoffice synchronisieren]
        └── [Zahlung erfassen] → Modal
```

### Datenmodell

```
invoices
├── id, invoice_number (nullable → bei Finalisierung vergeben)
├── order_id → orders, customer_id → customers
├── status  ENUM: draft | finalized
├── invoice_date, due_date
├── notes (nullable), pdf_path (nullable)
├── lexoffice_voucher_id (nullable), finalized_at (nullable)
└── company_id

invoice_items
├── id, invoice_id → invoices
├── type  ENUM: product | deposit | adjustment
├── description (Produktname-Snapshot oder Beschreibung)
├── quantity, unit_price_net_milli
├── tax_rate_basis_points  ← PFLICHTFELD, kein NULL
├── cost_milli (nullable)  ← Einkaufspreis (Marge)
└── company_id

invoice_payments
├── id, invoice_id → invoices
├── amount_milli, payment_date
├── payment_method  ENUM: cash | bank_transfer | stripe | paypal | sepa
└── company_id

invoice_sequences  [Race-Condition-freie Nummernvergabe]
├── id (always = 1), last_number (INT), prefix (VARCHAR "RE")
└── company_id
```

### Rechnungsnummer-Vergabe (kein Race Condition)

```
InvoiceService::finalizeInvoice($id):

DB-Transaktion:
  1. SELECT last_number FROM invoice_sequences FOR UPDATE
  2. next = last_number + 1
  3. UPDATE invoice_sequences SET last_number = next
  4. Format: "RE-2024-0001"
  5. UPDATE invoices SET invoice_number=..., status='finalized', finalized_at=NOW()

→ Gleichzeitige Anfragen erhalten unterschiedliche Nummern (kein Deadlock)
```

### Draft-Erstellung (InvoiceService::createDraft)

```
1. invoice_items (type='product'):
   → je OrderItem: name_snapshot, qty, unit_price, tax_rate
   → cost_milli = neuester aktiver supplier_product.cost_milli

2. invoice_item (type='deposit'):
   → Summe unit_deposit_milli × qty
   → tax_rate = deposit_tax_rate_basis_points

3. invoice_items (type='adjustment'):
   → für jedes OrderAdjustment (Leergut, Bruch, Korrekturen)
```

### Zahlungsstatus (berechnet, nicht gespeichert)

```
offener_betrag = invoice_total - SUM(payments.amount_milli)
  = 0:           "bezahlt"
  > 0, fällig:   "offen"
  > 0, überfällig: "überfällig"
  < 0:           "überzahlt"
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| `invoice_sequences` mit `FOR UPDATE` | Einzig sicherer Weg für lückenlose Nummernfolge; kein `MAX()+1` |
| `tax_rate_basis_points` Pflichtfeld | Rechtssicherheit; falscher Steuersatz ist Compliance-Problem |
| Email + Lexoffice via `deferred_tasks` | Finalisierung bleibt schnell und synchron |
| PDF im Storage (nicht inline) | Einmal generiert, beliebig oft abrufbar |

### Neue Services / Controller

```
InvoiceService                  ← createDraft(), finalize(), generatePdf(), sendEmail()
Admin\RechnungController        ← index, show, store, finalize, download, send, syncLexoffice
Admin\RechnungZahlungController ← store, destroy
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
