# PROJ-34: Umsatzmeldungen (pro Hersteller/Lieferant, Rhythmus, Email)

## Status: Planned
**Created:** 2026-03-01
**Last Updated:** 2026-03-01

## Dependencies
- Requires: PROJ-11 (Admin: Lieferantenverwaltung) — Umsätze werden nach Lieferant aufgeschlüsselt
- Requires: PROJ-13 (Admin: Rechnungen) — Rechnungsdaten als Umsatzbasis
- Requires: PROJ-19 (Admin: Einstellungen) — Email-Versand-Konfiguration

## Beschreibung
Manche Lieferanten/Hersteller verlangen regelmäßige Umsatzmeldungen (z.B. monatliche Verkaufszahlen pro Artikel). Das System erstellt diese Berichte automatisch nach konfigurierbarem Rhythmus (monatlich, quartalsweise) und versendet sie per Email an den Lieferanten. Format: CSV-Anhang.

## User Stories
- Als Admin möchte ich für einen Lieferanten konfigurieren, dass er monatlich eine Umsatzmeldung erhält.
- Als System möchte ich die Umsatzmeldung automatisch am Monatsende erstellen und per Email versenden.
- Als Admin möchte ich eine Umsatzmeldung manuell für einen beliebigen Zeitraum erstellen und herunterladen.
- Als Admin möchte ich den Versandstatus aller Umsatzmeldungen einsehen (versandt / fehlgeschlagen).
- Als Admin möchte ich eine versendete Umsatzmeldung erneut senden.

## Acceptance Criteria
- [ ] **Konfiguration pro Lieferant:** In Lieferantenstammdaten: Umsatzmeldung aktiv/inaktiv; Rhythmus (monatlich / quartalsweise / jährlich); Empfänger-Email(s); Berichtsbeginn (ab welchem Datum)
- [ ] **Inhalt der Meldung:** Pro finalisierter Rechnung im Zeitraum: Produkte des Lieferanten mit Verkaufsmenge, Einheit, Nettopreis je Stück, Nettoumsatz; gruppiert nach Produkt; Gesamtumsatz für diesen Lieferanten
- [ ] **Automatischer Versand:** Am 1. des Folgemonats/-quartals via `deferred_tasks`: CSV-Bericht erstellen + per Email versenden
- [ ] **CSV-Format:** Spalten: EAN, Artikelnummer, Produktname, Verkaufte Menge, Einheit, Nettopreis, Gesamtnettoumsatz; Kopfzeile; UTF-8 mit BOM
- [ ] **Email:** An konfigurierte Lieferanten-Email(s); Betreff: „Umsatzmeldung [Zeitraum] — [Firmenname]"; CSV als Anhang
- [ ] **Manueller Bericht:** Admin wählt Lieferant + Zeitraum → CSV-Download; keine Email
- [ ] **Versandprotokoll:** Pro Lieferant: alle versendeten Meldungen mit Zeitraum, Datum, Status, Empfänger-Email; Meldung erneut senden möglich
- [ ] **Fehlschlag-Handling:** Bei Email-Fehler: Fehlerstatus im Protokoll; Admin sieht Hinweis; manueller Resend möglich

## Edge Cases
- Lieferant hatte im Zeitraum keinen Umsatz → Bericht wird trotzdem erstellt (leerer Bericht mit Hinweis „Kein Umsatz im Zeitraum"); Email wird versandt
- Lieferant hat keine Email hinterlegt, aber Umsatzmeldung aktiv → Warnung im Protokoll; CSV wird nur gespeichert, keine Email
- Produkt hat mehrere Lieferanten (Haupt- + Nebenlieferant) → Umsatz erscheint in der Meldung des Lieferanten, dem das Produkt primär zugeordnet ist
- Zeitraum überschneidet sich mit Datenmigration (Altdaten fehlen) → Bericht zeigt nur verfügbare Daten; Hinweis in der Fußzeile

## Technical Requirements
- `supplier_sales_reports` Tabelle: `id`, `supplier_id`, `period_start`, `period_end`, `status ENUM(pending|sent|failed)`, `sent_at`, `recipient_emails JSON`, `csv_path`, `company_id`
- `deferred_tasks`-Job: Monatsanfang prüft, für welche Lieferanten Berichte fällig sind
- CSV-Generierung: `ReportService::generateSupplierSalesReport($supplierId, $from, $to)`
- Basisdaten: `invoice_items` JOIN `products` JOIN `product_suppliers` für den jeweiligen Lieferanten und Zeitraum

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)
_To be added by /architecture_

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
