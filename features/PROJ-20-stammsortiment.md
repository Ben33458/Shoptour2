# PROJ-20: Stammsortiment (Schnellbestellung, Kundenbestand, Mindestbestand, Notizen)

## Status: Planned
**Created:** 2026-03-01
**Last Updated:** 2026-03-01

## Dependencies
- Requires: PROJ-2 (Produktkatalog) — Produkte müssen vorhanden sein
- Requires: PROJ-10 (Admin: Kundenverwaltung) — Stammsortiment ist kundenbezogen
- Requires: PROJ-3 (Warenkorb) — Schnellbestellung befüllt den Warenkorb

## Beschreibung
Jeder Kunde hat ein persönliches Stammsortiment: eine Liste der regelmäßig bestellten Produkte mit Sollbestand, aktuellem Bestand und Notizen. Über die Schnellbestellfunktion kann ein Kunde mit einem Klick alle Produkte unterhalb des Mindestbestands in den Warenkorb legen. Admins pflegen das Stammsortiment für B2B-Kunden.

## User Stories
- Als eingeloggter Kunde möchte ich mein Stammsortiment einsehen und schnell alle benötigten Produkte nachbestellen.
- Als Kunde möchte ich für jedes Stamm-Produkt einen Mindestbestand und meinen aktuellen Bestand pflegen, damit das System ausrechnet, was ich brauche.
- Als Kunde möchte ich einem Stamm-Produkt eine Notiz hinterlegen (z.B. „nur Pfandflaschen, kein Einweg").
- Als Admin möchte ich das Stammsortiment eines Kunden einsehen und bearbeiten.
- Als Kunde möchte ich mit einem Klick alle Produkte, bei denen der aktuelle Bestand unter dem Mindestbestand liegt, in den Warenkorb legen (Schnellbestellung).
- Als Kunde möchte ich Produkte zu meinem Stammsortiment hinzufügen oder entfernen.

## Acceptance Criteria
- [ ] **Stammsortiment-Ansicht (Kunden-Frontend):** Liste aller Stamm-Produkte mit Bild, Name, Artikelnummer, Mindestbestand, aktuellem Bestand, Fehlmenge (Mindest - Aktuell), Notiz
- [ ] **Schnellbestellung:** Button „Fehlende Mengen in Warenkorb" → legt für jedes Produkt, bei dem `aktueller_bestand < mindestbestand`, die Differenz als Menge in den Warenkorb
- [ ] **Bestand aktualisieren:** Kunde kann `aktueller_bestand` direkt in der Liste bearbeiten (Inline-Eingabe)
- [ ] **Mindestbestand setzen:** Kunde kann `mindestbestand` pro Produkt festlegen
- [ ] **Notiz:** Freitextfeld pro Produkt (max. 500 Zeichen)
- [ ] **Produkt hinzufügen:** Suche über Produktkatalog; Produkt in Stammsortiment aufnehmen
- [ ] **Produkt entfernen:** Aus Stammsortiment entfernen (keine Auswirkung auf vergangene Bestellungen)
- [ ] **Admin-Ansicht:** Admin kann das Stammsortiment eines beliebigen Kunden aufrufen und bearbeiten (unter Kundenverwaltung → Tab Stammsortiment)
- [ ] **Bestand nach Lieferung aktualisieren:** Nach erfolgreicher Lieferung wird `aktueller_bestand` automatisch um die gelieferte Menge erhöht (aus Fulfillment-Daten, PROJ-16)
- [ ] **Bestand zurücksetzen:** Kunde kann `aktueller_bestand` manuell auf 0 zurücksetzen (z.B. nach eigenem Verbrauch)

## Edge Cases
- Mindestbestand = 0 und aktueller Bestand = 0 → Keine Fehlmenge; Produkt erscheint nicht in Schnellbestellung
- Produkt wird im Katalog deaktiviert, ist aber im Stammsortiment → Produkt bleibt im Stammsortiment, wird als „nicht verfügbar" markiert; Schnellbestellung überspringt es
- Schnellbestellung: alle Produkte haben ausreichend Bestand → Hinweis „Alle Bestände ausreichend", kein Warenkorb-Update
- Bestand nach Lieferung: Teillieferung (nur 4 von 6 Kisten geliefert) → Bestand nur um tatsächlich gelieferte Menge erhöhen
- Kunde entfernt Produkt aus Stammsortiment, das gerade im Warenkorb liegt → Warenkorb bleibt unverändert; nur Stammsortiment-Eintrag wird gelöscht

## Technical Requirements
- `customer_assortment_items` Tabelle: `customer_id`, `product_id`, `min_stock`, `current_stock`, `notes`
- Unique constraint auf `(customer_id, product_id)`
- Schnellbestellung: serverseitige Berechnung der Fehlmengen; Warenkorb-Befüllung via `WarenkorbController::addBulk`
- Bestandsupdate nach Lieferung: Event-Hook aus Fahrer-PWA-Fulfillment (PROJ-16)

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)
_To be added by /architecture_

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
