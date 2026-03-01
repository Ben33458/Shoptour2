# PROJ-32: Admin: Einkauf (PurchaseOrders, Workflow)

## Status: Planned
**Created:** 2026-03-01
**Last Updated:** 2026-03-01

## Dependencies
- Requires: PROJ-11 (Admin: Lieferantenverwaltung) — Bestellungen gehen an Lieferanten
- Requires: PROJ-9 (Admin: Stammdaten) — Produkte werden eingekauft
- Requires: PROJ-23 (Admin: Lagerverwaltung) — Wareneingang bucht Bestand ein

## Beschreibung
Verwaltung von Einkaufsbestellungen (Purchase Orders) bei Lieferanten. Admin erstellt Bestellungen, verschickt diese per Email an Lieferanten, bucht Wareneingänge (ggf. Teillieferungen) und verfolgt den Status der Bestellung. Dient der strukturierten Warenbeschaffung.

## User Stories
- Als Admin möchte ich eine Einkaufsbestellung bei einem Lieferanten erstellen mit Positionen (Produkt, Menge, vereinbarter Preis).
- Als Admin möchte ich die Bestellung als PDF generieren und per Email an den Lieferanten senden.
- Als Admin möchte ich den Wareneingang buchen (vollständig oder als Teillieferung).
- Als Admin möchte ich den Status aller Bestellungen auf einen Blick sehen.
- Als Admin möchte ich Produkte vorschlagen lassen, die unter dem Mindestbestand liegen (automatische Bestellempfehlung).

## Acceptance Criteria
- [ ] **PurchaseOrder erstellen:** Lieferant auswählen, Bestelldatum, Erwartetes Lieferdatum, Positionen (Produkt, Menge, vereinbarter EK-Preis je Stück), Notiz
- [ ] **Status-Flow:** `draft` → `ordered` (abgeschickt) → `partially_received` → `received` / `cancelled`
- [ ] **PDF-Generierung:** Bestellformular als PDF: Firmendaten, Lieferantendaten, Bestellnummer, Positionen, Gesamtbetrag, Lieferanschrift
- [ ] **Email an Lieferant:** PDF als Anhang; an Lieferanten-Email; Betreff und Text konfigurierbar in Einstellungen
- [ ] **Wareneingang buchen:** Pro Position: gelieferte Menge eingeben; Lagerort wählen; → erstellt `stock_movement (inbound)` in PROJ-23; verbleibende Menge bleibt offen bis `received` oder manuell als vollständig markiert
- [ ] **Bestellübersicht:** Alle Purchase Orders; Filter nach Status, Lieferant, Datum; sortiert nach erwartetem Lieferdatum
- [ ] **Bestellnummer:** Automatisch vergeben (ähnlich Rechnungsnummer); Format konfigurierbar (z.B. `EK-2024-0001`)
- [ ] **Bestellempfehlung:** Button „Bestellvorschläge" → zeigt alle Produkte, bei denen `current_stock < min_stock`, mit Vorschlag-Menge (`min_stock - current_stock`), zugeordnetem Lieferanten; Auswahl → direkt in neue PurchaseOrder übernehmen
- [ ] **Bestellung stornieren:** Nur bei Status `draft` oder `ordered` und noch kein Wareneingang gebucht; mit Bestätigung

## Edge Cases
- Lieferant hat keine Email → PDF wird generiert; Email-Button deaktiviert; Admin muss manuell versenden
- Teillieferung überschreitet bestellte Menge (Lieferant schickt mehr) → Warnung; trotzdem buchbar; Status geht auf `received`
- Bestellung wird storniert nach Teillieferung → Nur zukünftige Lieferungen abbrechen; gebuchter Wareneingang bleibt
- Produkt auf PurchaseOrder ist nicht mehr im Stammdaten vorhanden → Zeile bleibt als historische Position; kein Wareneingang möglich

## Technical Requirements
- `purchase_orders` Tabelle: `id`, `po_number`, `supplier_id`, `order_date`, `expected_date`, `status ENUM`, `notes`, `company_id`
- `purchase_order_items` Tabelle: `po_id`, `product_id`, `quantity_ordered`, `quantity_received`, `unit_cost_milli`, `notes`
- Wareneingang: Update `quantity_received`; wenn alle Positionen `quantity_received >= quantity_ordered` → Status `received`
- `po_sequences`-Tabelle für Race-Condition-freie Nummernvergabe (analog zu `invoice_sequences`)

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)
_To be added by /architecture_

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
