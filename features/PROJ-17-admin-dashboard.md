# PROJ-17: Admin: Dashboard

## Status: Planned
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- Requires: PROJ-12 (Admin: Bestellverwaltung) — Bestelldaten für KPIs
- Requires: PROJ-13 (Admin: Rechnungen) — Rechnungsdaten für KPIs

## Beschreibung
Anpassbares Dashboard für Mitarbeiter und Admins. Zeigt KPIs (Umsatz, offene Bestellungen, offene Rechnungen, heutige Touren) als Widgets. Jeder Nutzer kann sein Dashboard individuell konfigurieren (Widgets ein-/ausblenden, anordnen). Zusätzlich gibt es spezifische Aufgaben-Widgets pro Mitarbeiter.

## User Stories
- Als Admin möchte ich auf dem Dashboard auf einen Blick die wichtigsten KPIs sehen.
- Als Mitarbeiter möchte ich mein Dashboard auf meine Aufgaben anpassen (nur relevante Widgets).
- Als Admin möchte ich heutige Touren und deren Status auf dem Dashboard sehen.
- Als Mitarbeiter möchte ich meine offenen Aufgaben direkt auf dem Dashboard sehen.
- Als Admin möchte ich die letzten Systemereignisse (Log) in einem Widget sehen.

## Acceptance Criteria
- [ ] **KPI-Widgets (Admin):**
  - Umsatz heute / diese Woche / dieser Monat (brutto)
  - Anzahl neuer Bestellungen (heute, diese Woche)
  - Offene Rechnungen: Anzahl + Gesamtbetrag
  - Überfällige Rechnungen: Anzahl + Gesamtbetrag
  - Aktive Kunden (letzten 30 Tage bestellt)
- [ ] **Touren-Widget:** heutige Touren mit Status (geplant/in Auslieferung/abgeschlossen), Anzahl Stops
- [ ] **Letzte Bestellungen:** Liste der 5 neuesten Bestellungen (klickbar)
- [ ] **Aufgaben-Widget:** offene Aufgaben des eingeloggten Nutzers (aus PROJ-26, P1)
- [ ] **Log-Widget:** letzte 10 Audit-Log-Einträge (Typ, User, Beschreibung, Zeit)
- [ ] **Anpassbarkeit:** Widgets können pro User ein-/ausgeblendet werden; Reihenfolge speicherbar
- [ ] **Responsive:** Dashboard funktioniert auf Tablet (768px) und Desktop

## Edge Cases
- Keine Daten vorhanden (neues System) → Widgets zeigen Leer-Zustände (kein Crash)
- Dashboard-Konfiguration eines gelöschten Users → Konfiguration wird ebenfalls gelöscht

## Technical Requirements
- Dashboard-Konfiguration in `user_dashboard_widgets` Tabelle (oder JSON-Feld auf `users`)
- KPI-Daten werden gecacht (5-Minuten-Cache), da sie keine Echtzeit-Präzision benötigen

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)
_To be added by /architecture_

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
