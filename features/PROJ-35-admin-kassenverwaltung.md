# PROJ-35: Admin: Kassenverwaltung

## Status: Planned
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- Requires: PROJ-18 (Admin: Benutzer & Rollen) — Berechtigungen für Kassenzugriff
- Required by: PROJ-16 (Fahrer-PWA) — Fahrer brauchen Zielkassen für Einzahlungen

## Beschreibung
Verwaltung aller Kassen und des zentralen Kassenbuchs. Das System kennt verschiedene Kassentypen (physische Kasse im Büro, Tresor, Bankkonto, Liefergeldbeutel der Fahrer). Alle Geldbewegungen (Einzahlungen von Fahrern, Transfers zwischen Kassen, Abhebungen für Einkäufe) werden als unveränderliche Kassenbuch-Einträge protokolliert. Jeder Eintrag kann von einem berechtigten Mitarbeiter als „kontrolliert" markiert werden.

### Kassentypen

| Typ | Beschreibung | Beispiele |
|-----|-------------|----------|
| `driver_wallet` | Virtueller Liefergeldbeutel pro Fahrer | „Max Mustermann Beutel" |
| `cash_register` | Physische Kasse im Büro | „Kolabri Kasse 1", „Kolabri Kasse 2" |
| `safe` | Tresor | „Tresor Kolabri", „Tresor Außenlager" |
| `bank` | Bankkonto | „Bank" |

### Kassenbuch-Eintragstypen

| Typ | Bedeutung |
|-----|-----------|
| `cash_received` | Fahrer nimmt Bargeld vom Kunden entgegen (→ Liefergeldbeutel) |
| `cash_deposit` | Fahrer zahlt in Zielkasse ein (Beutel → Tresor/Kasse) |
| `transfer` | Admin-Transfer zwischen zwei Kassen (z.B. Tresor → Bank) |
| `ec_received` | EC-Zahlung (informativer Eintrag, kein Kassenbestand) |
| `adjustment` | Manuelle Korrektur (Differenz nach Nachzählung) |
| `withdrawal` | Barauszahlung (z.B. Einkauf) |

### Kontrolliert-Workflow

Nach der Fahrer-Einzahlung sieht der Admin den Betrag im Kassenbuch. Nach physischer Nachzählung setzt der Admin „Kontrolliert" + optional einen korrigierten Ist-Betrag. Abweichungen werden protokolliert.

## User Stories
- Als Admin möchte ich Kassen anlegen, umbenennen und (de)aktivieren.
- Als Admin möchte ich jedem Fahrer eine Kasse vom Typ `driver_wallet` zuordnen.
- Als Admin möchte ich konfigurieren, welche Zielkassen einem Fahrer beim Tourende zur Auswahl stehen.
- Als Admin möchte ich das Kassenbuch aller Kassen einsehen (gefiltert nach Kasse, Datum, Typ).
- Als Admin möchte ich den aktuellen Kassenstand jeder Kasse sehen.
- Als Admin möchte ich einen manuellen Transfer zwischen zwei Kassen buchen (z.B. Tresor → Bank).
- Als Admin möchte ich Kassenbuch-Einträge als „kontrolliert" markieren und bei Abweichung einen korrigierten Betrag eintragen.
- Als Admin möchte ich Berichte über Tages-/Wochen-/Monats-Umsätze pro Kasse sehen.
- Als Admin möchte ich festlegen, welche Mitarbeiter-Rollen welche Kassen sehen/bearbeiten dürfen.

## Acceptance Criteria

### Kassen-Verwaltung
- [ ] **Kassen-Liste:** Name, Typ, aktueller Kassenstand (Soll), Status (aktiv/inaktiv)
- [ ] **Kasse anlegen/bearbeiten:** Name, Typ (`driver_wallet` / `cash_register` / `safe` / `bank`), Beschreibung, aktiv
- [ ] **Fahrer-Wallet:** Wird automatisch erstellt, wenn ein Fahrer-Account angelegt wird (PROJ-15); verknüpft mit `user_id`
- [ ] **Zielkassen-Konfiguration:** Pro Kasse (Typ `driver_wallet`) festlegen, welche Zielkassen zur Einzahlung erlaubt sind; wird im PWA-Bootstrap übermittelt
- [ ] **Deaktivieren:** Inaktive Kassen können keine neuen Buchungen empfangen; bestehende Einträge bleiben erhalten
- [ ] **Löschschutz:** Kassen mit Kassenbuch-Einträgen können nicht gelöscht werden

### Kassenbuch
- [ ] **Kassenbuch-Übersicht:** Liste aller Einträge mit Filtern: Kasse, Datumsbereich, Typ, Fahrer, Status (kontrolliert/unkontrolliert)
- [ ] **Eintrag-Details:** Datum/Zeit, Typ, Betrag, Quell-Kasse, Ziel-Kasse, Fahrer, Bestellung/Stop (verlinkt), Notiz, kontrolliert (Bool), Ist-Betrag (bei Abweichung), kontrolliert-von (User)
- [ ] **Kassenstand:** Aggregierter Saldo pro Kasse (Summe aller Einträge); Live-Berechnung
- [ ] **EC-Einträge** sind informativer Art und fließen nicht in den Kassenstand ein

### Manuelle Buchungen (Admin)
- [ ] **Transfer buchen:** Quell-Kasse, Ziel-Kasse, Betrag, Notiz, Datum → erzeugt zwei Einträge (Abgang + Zugang)
- [ ] **Abhebung buchen:** Kasse, Betrag, Zweck/Notiz → Kassenstand reduziert sich
- [ ] **Anpassung (Adjustment):** Kasse, Betrag (positiv/negativ), Grund → für Differenzausgleich nach Nachzählung

### Kontrolliert-Workflow
- [ ] **„Kontrolliert" setzen:** Einzeln oder Massenmarkierung; nur durch berechtigte Mitarbeiter
- [ ] **Ist-Betrag:** Optional; wenn abweichend vom Soll-Betrag → Differenz-Warnung und automatischer Adjustment-Eintrag
- [ ] **Audit:** Wer hat kontrolliert, wann (Timestamp + User)

### Berechtigungen
- [ ] Kassen-Sichtbarkeit per Rolle konfigurierbar (z.B. Fahrer sieht nur eigenen Beutel; Kassierer sieht nur Kasse 1)
- [ ] Nur Admins/berechtigte Rollen können Transfers und Anpassungen buchen
- [ ] „Kontrolliert" setzen ist separate Berechtigung

### Reporting
- [ ] **Tages-/Wochen-/Monatsübersicht** pro Kasse: Anfangsbestand, Einnahmen, Ausgaben, Endbestand
- [ ] **Fahrer-Report:** Pro Fahrer und Zeitraum: eingenommenes Bargeld, eingezahltes Bargeld, Differenzen

## Edge Cases
- Fahrer zahlt weniger ein als erwartet → Kassenbucheintrag mit Soll- und Ist-Betrag; Admin sieht Warnung; kein Blockieren
- Fahrer zahlt mehr ein als erwartet → Überschuss wird protokolliert; kein Fehler
- Transfer auf inaktive Zielkasse → Validierungsfehler; Admin muss aktive Kasse wählen
- Kassenstand wird negativ → Warnung, aber kein Blockieren (Korrekturbuchung nötig)
- Eintrag nachträglich löschen → nicht erlaubt; stattdessen Storno-/Korrekturbuchung (Adjustment)
- Gleichzeitige Buchungen auf gleiche Kasse (Race Condition) → DB-Lock auf Kassenstand-Update
- Fahrer-Wallet-Kasse wird deaktiviert während Fahrer auf Tour → bestehende Tour kann weiter gebucht werden; Warnung für Admin

## Technical Requirements
- `kassen` Tabelle: `id`, `name`, `type` (Enum), `description`, `user_id` (nullable, für `driver_wallet`), `active`, `company_id`
- `kassenbuch_eintraege` Tabelle: `id`, `kasse_id` (Ziel), `source_kasse_id` (nullable), `betrag_milli`, `type` (Enum), `tour_id` (nullable), `tour_stop_id` (nullable), `order_id` (nullable), `note`, `checked` (Bool), `checked_by_user_id`, `checked_at`, `ist_betrag_milli` (nullable), `created_by_user_id`, `company_id`
- `kassenbuch_eintraege` sind **immutable** — kein UPDATE; Korrekturen nur via neue Adjustment-Einträge
- Kassenstand = aggregierte Summe der Einträge (kein denormalisierter Saldo-Counter — zu fehleranfällig)
- Alternativ: Materialized View oder gecachte Saldo-Tabelle mit Invalidierung bei neuem Eintrag
- Zugriffsschutz: `kassen`-Sichtbarkeit per `kassen_user_permissions` Pivot oder Policy-basiert

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)
_To be added by /architecture_

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
