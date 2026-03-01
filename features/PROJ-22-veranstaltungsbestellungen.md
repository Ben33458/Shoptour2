# PROJ-22: Veranstaltungsbestellungen + Festinventar (Leihgeräte, Zeitfenster)

## Status: Planned
**Created:** 2026-03-01
**Last Updated:** 2026-03-01

## Dependencies
- Requires: PROJ-3 (Warenkorb) — Veranstaltungsbestellung nutzt erweiterten Warenkorb
- Requires: PROJ-4 (Checkout) — eigener Checkout-Pfad für Events
- Requires: PROJ-12 (Admin: Bestellverwaltung) — Event-Bestellungen erscheinen in Bestellliste
- Requires: PROJ-23 (Lagerverwaltung) — Festinventar (Leihgeräte) wird als eigene Inventar-Kategorie geführt

## Beschreibung
Studentische Organisationen und Veranstalter können Getränke plus Leihgeräte (Zapfanlage, Biertischgarnituren, Kühler, etc.) für Veranstaltungen bestellen. Jede Event-Bestellung hat einen Liefer-/Abholtermin und einen Rückgabetermin. Das Festinventar (Leihgeräte) wird separat verwaltet und auf Verfügbarkeit geprüft.

## User Stories
- Als Veranstalter möchte ich Getränke und Leihgeräte in einer gemeinsamen Bestellung aufgeben.
- Als Veranstalter möchte ich Liefer- und Rückgabedatum angeben, damit das System Verfügbarkeit prüft.
- Als Veranstalter möchte ich sehen, welche Leihgeräte zum gewünschten Termin verfügbar sind.
- Als Admin möchte ich das Festinventar (Leihgeräte-Bestand) verwalten.
- Als Admin möchte ich eine Übersicht aller Event-Bestellungen mit Terminen sehen (Kalenderansicht).
- Als Admin möchte ich Konflikte bei Leihgeräte-Buchungen sofort erkennen.

## Acceptance Criteria
- [ ] **Event-Bestelltyp:** Bestellung hat Typ `event`; Checkout-Formular zeigt zusätzliche Felder: Veranstaltungsname, Lieferdatum + Uhrzeit, Rückgabedatum + Uhrzeit, Veranstaltungsort (Adresse)
- [ ] **Leihgeräte-Katalog:** Im Checkout/Warenkorb gibt es eine eigene Sektion „Leihgeräte"; Artikel aus dem Festinventar mit Tagespreisen; Verfügbarkeit wird für den gewählten Zeitraum geprüft
- [ ] **Verfügbarkeitsprüfung:** Ein Leihgerät ist verfügbar, wenn kein Stück zum gleichen Zeitraum bereits gebucht ist (Überschneidung = Lieferdatum1 < Rückgabedatum2 UND Rückgabedatum1 > Lieferdatum2)
- [ ] **Festinventar-Verwaltung (Admin):** Leihgeräte anlegen/bearbeiten: Name, Beschreibung, Anzahl verfügbarer Stücke, Tagesmietpreis, Kaution; Bild
- [ ] **Kalenderübersicht (Admin):** Alle Event-Bestellungen auf Kalender; farblich nach Status; Kollisionswarnung wenn zwei Buchungen Ressourcen teilen
- [ ] **Kaution:** Kautionsbetrag wird bei Event-Bestellungen separat ausgewiesen (in Rechnung als eigene Position)
- [ ] **Rückgabe-Protokoll:** Admin kann Rückgabe bestätigen und Zustand vermerken (OK / beschädigt); bei Beschädigung kann Schadensbetrag auf Kaution angerechnet werden
- [ ] **Status-Flow:** `pending` → `confirmed` → `delivered` → `returned` / `partially_returned`

## Edge Cases
- Leihgerät in gewünschtem Zeitraum nicht mehr verfügbar, Kunde hat es in den Warenkorb gelegt → Beim Checkout wird Verfügbarkeit erneut geprüft; bei Nicht-Verfügbarkeit Fehlermeldung + Alternativtermine anzeigen
- Rückgabedatum < Lieferdatum → Validierungsfehler
- Bestellung wird storniert, Leihgerät war gebucht → Leihgerät-Buchung wird freigegeben; Zeitraum wieder verfügbar
- Leihgerät wird als beschädigt zurückgegeben → Admin kann Schadensbetrag erfassen; Rechnung wird um Schadensposition ergänzt (manuelle Anpassung)
- Veranstalter bucht mehr Leihgeräte als Stücke vorhanden → System zeigt „nur noch X verfügbar"

## Technical Requirements
- `rental_items` Tabelle: Name, Beschreibung, Menge, Tagesmietpreis, Kautionsbetrag, `company_id`
- `rental_bookings` Tabelle: `order_id`, `rental_item_id`, Menge, Start/Ende, Status, Zustandsnotiz
- `orders.type` ENUM-Erweiterung: `standard` | `event`
- Verfügbarkeitsabfrage: `rental_bookings`-Tabelle mit Zeitraum-Overlap-Prüfung
- `order_items`: Leihgeräte erscheinen als normale Positionen mit `item_type = 'rental'`

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)
_To be added by /architecture_

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
