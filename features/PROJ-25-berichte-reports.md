# PROJ-25: Admin: Berichte & Reports (Umsatz, Marge, Pfand, Tour-KPIs)

## Status: Planned
**Created:** 2026-03-01
**Last Updated:** 2026-03-01

## Dependencies
- Requires: PROJ-12 (Admin: Bestellverwaltung) — Bestelldaten als Datenbasis
- Requires: PROJ-13 (Admin: Rechnungen) — Umsatz- und Zahlungsdaten
- Requires: PROJ-7 (Pfand-System) — Pfandmengen und Rückgaben
- Requires: PROJ-15 (Admin: Fahrertouren) — Tour-KPIs

## Beschreibung
Auswertungs- und Berichts-Modul für Admin und Management. Bietet vordefinierte Berichte (Umsatz, Marge, Pfand, Tour-KPIs) mit Zeitraum-Filter und CSV-Export. Keine Live-Dashboards (Performance-Grund), sondern On-Demand-Reports.

## User Stories
- Als Admin möchte ich den Umsatz nach Zeitraum, Kundengruppe und Produkt einsehen.
- Als Admin möchte ich die Marge (Umsatz minus Einkaufspreis) pro Produkt und Zeitraum sehen.
- Als Admin möchte ich Pfandmengen (ausgegebenes Pfand vs. zurückgegebenes Pfand) auswerten.
- Als Admin möchte ich Tour-KPIs sehen (Stops pro Tour, Ausfallquote, Durchschnittsumsatz pro Stop).
- Als Admin möchte ich alle Berichte als CSV exportieren.
- Als Admin möchte ich Berichte nach Zeitraum (Tag, Woche, Monat, benutzerdefiniert) filtern.

## Acceptance Criteria
- [ ] **Umsatzbericht:** Zeitraum, Gesamtumsatz netto/brutto, aufgeschlüsselt nach: Monat, Kundengruppe, Warengruppe, Top-10-Produkte; CSV-Export
- [ ] **Margenbericht:** Umsatz netto minus Einkaufspreise (aus `invoice_items.cost_milli`) = Rohertrag; Marge in % pro Produkt und Warengruppe; CSV-Export
- [ ] **Pfandbericht:** Ausgegebene Pfandmengen (aus Bestellungen) vs. zurückgenommene Pfandmengen (aus Fulfillment-Adjustments); Saldo pro Zeitraum und Produkt; CSV-Export
- [ ] **Tour-KPI-Bericht:** Pro Tour: Anzahl Stops, erledigte Stops, ausgefallene Stops (failed/skipped), Durchschnittsumsatz pro Stop, Fahrer, Datum; CSV-Export
- [ ] **Zahlungseingang-Bericht:** Offene Rechnungen, bezahlte Rechnungen, überfällige Rechnungen; Zahlungsmittel-Aufschlüsselung (Bar, Überweisung, Stripe, etc.); CSV-Export
- [ ] **Zeitraum-Filter:** Voreinstellungen: Heute, Diese Woche, Dieser Monat, Letzter Monat, Dieses Jahr; oder benutzerdefinierter Zeitraum
- [ ] **Report-Seite:** Auswahl des Berichtstyps → Parameter eingeben → „Bericht erstellen" → Ergebnis als Tabelle; CSV-Button
- [ ] **Ladezeit:** Berichte werden On-Demand berechnet; keine Echtzeit-Datenbank-Live-Abfragen mit Timeout-Risiko; bei großen Zeiträumen (>6 Monate) Hinweis auf Wartezeit

## Edge Cases
- Zeitraum ohne Daten → Leere Tabelle mit Hinweis, kein Fehler
- Margenbericht: Produkt ohne Einkaufspreis (cost_milli = NULL) → In Margenbericht als „kein EK vorhanden" markiert, nicht in Gesamtmarge eingerechnet
- CSV-Export: Umlaute korrekt encodiert (UTF-8 mit BOM für Excel-Kompatibilität)
- Sehr großer Zeitraum (mehrere Jahre) → Bericht wird als Hintergrundprozess erstellt; Download-Link per Email

## Technical Requirements
- Keine gesonderten Reporting-Tabellen; direkte Abfragen auf Bestell-/Rechnungsdaten
- `ReportService` mit dedizierten Methoden pro Berichtstyp
- CSV-Export: `League\Csv`-Bibliothek; UTF-8 mit BOM
- Große Reports: `deferred_tasks` Queue + Email-Benachrichtigung mit Download-Link

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)
_To be added by /architecture_

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
