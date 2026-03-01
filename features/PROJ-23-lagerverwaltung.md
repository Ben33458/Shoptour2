# PROJ-23: Admin: Lagerverwaltung (Warehouses, Stock, Bewegungen)

## Status: Planned
**Created:** 2026-03-01
**Last Updated:** 2026-03-01

## Dependencies
- Requires: PROJ-9 (Admin: Stammdaten) — Produkte müssen vorhanden sein
- Requires: PROJ-11 (Admin: Lieferantenverwaltung) — Wareneingänge kommen von Lieferanten
- Requires: PROJ-12 (Admin: Bestellverwaltung) — Lieferungen reduzieren Lagerbestand

## Beschreibung
Verwaltung von Lagerorten (Warehouses), Produktbeständen pro Lager und Lagerbewegungen (Wareneingang, Warenausgang durch Lieferung, manuelle Korrekturen, Inventur). Das System ermöglicht eine einfache Bestandsübersicht und Warnung bei Unterschreitung von Mindestbeständen.

## User Stories
- Als Admin möchte ich mehrere Lagerorte (z.B. Hauptlager, Kühlhaus, Lager 2) anlegen und verwalten.
- Als Admin möchte ich den aktuellen Bestand jedes Produkts pro Lager einsehen.
- Als Admin möchte ich Wareneingänge (aus Lieferantenbestellungen) buchen.
- Als Admin möchte ich manuelle Bestandskorrekturen (Inventur, Schwund, Bruch) erfassen.
- Als Admin möchte ich bei Bestandsunterschreitung eines Mindestbestands eine Warnung sehen (Dashboard/Liste).
- Als Admin möchte ich alle Lagerbewegungen eines Produkts nachverfolgen können.
- Als Mitarbeiter möchte ich beim Kommissionieren einer Bestellung den Warenausgang buchen.

## Acceptance Criteria
- [ ] **Lagerverwaltung:** CRUD für Lagerorte; Felder: Name, Adresse (optional), Notiz
- [ ] **Bestandsübersicht:** Tabelle: Produkt, Lagerort, aktueller Bestand, Mindestbestand, Differenz; Filter nach Lagerort, Warengruppe, Unterbestand (nur Artikel unter Mindestbestand)
- [ ] **Wareneingang buchen:** Produkt auswählen, Lagerort, Menge, Lieferant (optional), Referenz (Lieferschein-Nr.), Datum → erstellt `stock_movement` vom Typ `inbound`
- [ ] **Warenausgang manuell:** Produkt, Lagerort, Menge, Grund (Lieferung, Bruch, Schwund, Rückgabe), Notiz → `stock_movement` Typ `outbound`
- [ ] **Bestandskorrektur (Inventur):** Ist-Bestand direkt setzen → System berechnet Differenz und erstellt `stock_movement` Typ `adjustment`
- [ ] **Bestandswarnung:** Produkte mit `current_stock < min_stock` werden rot markiert; Dashboard-Widget zeigt Anzahl kritischer Bestände
- [ ] **Bewegungshistorie:** Pro Produkt: Liste aller Bewegungen mit Datum, Typ, Menge, Referenz, Benutzer
- [ ] **Bestand nach Lieferung:** Wenn Fahrer eine Lieferung als `delivered` markiert (PROJ-16), wird Warenausgang automatisch gebucht
- [ ] **Mindestbestand setzen:** Pro Produkt + Lagerort konfigurierbar

## Edge Cases
- Bestand würde nach Buchung negativ werden → Warnung anzeigen, aber erlauben (Negativbestand möglich, da Buchung korrekt sein muss)
- Lagerort wird gelöscht, hat aber noch Bestand → Verweigern; erst Bestand auf 0 oder in anderes Lager umbuchen
- Wareneingang für deaktiviertes Produkt → Warnung, aber erlauben
- Gleichzeitige Bestandsbuchungen auf dasselbe Produkt → DB-Transaction, kein Race Condition
- Produkt hat keinen Lagerbestand-Eintrag für einen Lagerort → Bestand gilt als 0

## Technical Requirements
- `warehouses`: `id`, `name`, `address`, `notes`, `company_id`
- `product_stock`: `product_id`, `warehouse_id`, `current_stock` (INT), `min_stock` (INT, nullable), UNIQUE `(product_id, warehouse_id)`
- `stock_movements`: `id`, `product_id`, `warehouse_id`, `type ENUM(inbound|outbound|adjustment)`, `quantity` (INT, positiv = Eingang, negativ = Ausgang), `reference` (nullable), `notes`, `user_id`, `created_at`
- Bestandsberechnung: `current_stock` als gecachter Wert in `product_stock`, bei jeder Bewegung atomisch aktualisiert (kein `SUM(movements)`)

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)
_To be added by /architecture_

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
