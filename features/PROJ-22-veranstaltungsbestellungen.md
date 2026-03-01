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

### Komponenten-Struktur (UI-Baum)

```
/checkout/event                          ← Event-Checkout-Pfad
│
├── Schritt 1: Veranstaltungsdetails
│   ├── Veranstaltungsname (Pflicht)
│   ├── Lieferadresse / Abholung
│   ├── Lieferdatum + Uhrzeit (Datetimepicker)
│   └── Rückgabedatum + Uhrzeit (Datetimepicker)
│
├── Schritt 2: Getränke-Auswahl
│   └── → normaler Produktkatalog mit Warenkorb
│
└── Schritt 3: Leihgeräte
    ├── Verfügbare Leihgeräte für gewählten Zeitraum
    │   ├── Bild, Name, Beschreibung
    │   ├── Tagespreis, Kaution
    │   ├── Verfügbarkeitsanzeige (X von N verfügbar)
    │   └── Menge wählen → [Hinzufügen]
    └── Zusammenfassung: Leihgeräte + Kaution gesamt

/admin/leihgeraete/                      ← Festinventar-Verwaltung
├── index       ← Leihgeräte-Liste (Name, Anzahl, Tagespreis)
└── {id}/       ← Detail/Bearbeiten
    ├── Stammdaten (Name, Beschreibung, Anzahl, Preise)
    └── Belegungskalender — welche Tage sind gebucht?

/admin/bestellungen/event/               ← Event-Bestellungen-Übersicht
└── Kalenderansicht aller Event-Bestellungen (Farbe nach Status)
```

### Datenmodell

```
rental_items  [Festinventar / Leihgeräte]
├── id, name, description
├── total_quantity      ← wie viele Stücke vorhanden
├── price_per_day_milli, deposit_milli
├── active, company_id

rental_bookings  [Buchungen eines Leihgeräts]
├── id
├── order_id    → orders
├── rental_item_id → rental_items
├── quantity    ← wie viele Stücke gebucht
├── starts_at, ends_at  (DATETIME)
├── status  ENUM: reserved | delivered | returned | partially_returned | damaged
├── condition_notes (nullable)
└── company_id

orders  [erweitert]
├── type  ENUM: standard | event   ← neu
├── event_name (nullable)
├── event_delivery_at  (DATETIME, nullable)
└── event_return_at    (DATETIME, nullable)

order_items  [erweitert]
└── item_type ENUM: product | rental   ← neu (für Leihgeräte)
```

### Verfügbarkeitsprüfung

```
Leihgerät X für Zeitraum A..B verfügbar?

  Gebuchte Menge im Zeitraum =
    SUM(quantity) WHERE starts_at < B AND ends_at > A
    (Zeitraum-Overlap)

  Verfügbar = total_quantity - gebuchte_menge

→ Prüfung zweimal:
  1. Beim Anzeigen der Leihgeräte (weiche Prüfung, zeigt verfügbare Menge)
  2. Beim Checkout-Abschluss (harte Prüfung in DB-Transaktion)
```

### Admin Kalenderansicht

```
/admin/bestellungen/event:
  FullCalendar (JS) zeigt alle rental_bookings als Balken
  Farbe nach Status: blau = reserved, grün = returned, rot = damaged
  Kollisionswarnung: wenn ein Leihgerät an einem Tag überbucht wäre
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| `rental_bookings` separiert von `order_items` | Leihgeräte brauchen Start/Ende-Zeiten und Status-Tracking über den Zeitraum |
| Zweifache Verfügbarkeitsprüfung | Soft-Check für UX, Hard-Check für Datenkonsistenz |
| `event`-Typ auf `orders` | Event-Bestellungen haben andere Felder; einfacher als separate Tabelle |
| FullCalendar für Admin-Übersicht | Bewährtes UI-Pattern für Zeitraum-Buchungen; kein Custom-Kalender nötig |

### Neue Controller / Services

```
Shop\EventCheckoutController         ← index, store (Event-Bestellabschluss)
Admin\LeihgeraetController           ← CRUD Festinventar
Admin\EventBestellungController      ← index (Kalenderansicht)
RentalAvailabilityService           ← checkAvailability(), getAvailableItems()
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
