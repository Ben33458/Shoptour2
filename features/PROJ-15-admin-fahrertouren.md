# PROJ-15: Admin: Fahrertouren-Planung

## Status: Planned
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- Requires: PROJ-14 (Admin: Regelmäßige Touren) — konkrete Touren entstehen aus Templates
- Requires: PROJ-12 (Admin: Bestellverwaltung) — Bestellungen werden Touren-Stops zugeordnet

## Beschreibung
Erstellung konkreter Fahrertouren aus regulären Tour-Templates, Zuweisung von Fahrern, flexible Aufteilung (Split) einer geplanten Tour in mehrere Fahrertouren. Stops können einzeln zwischen Touren verschoben werden. Nach Erstellung werden Fahrer-Tokens für die PWA-Offline-Nutzung generiert.

## User Stories
- Als Admin/Disponent möchte ich eine konkrete Tour für ein bestimmtes Datum aus einem Template generieren.
- Als Admin möchte ich einer Tour einen Fahrer zuweisen.
- Als Admin möchte ich eine geplante Tour aufteilen:
  - Alle Kunden ab Kunde X → neue Tour
  - Alle Kunden vor Kunde X → neue Tour
  - Einzelne Kunden zwischen Touren verschieben
- Als Admin möchte ich TourStops auf einer Tour umsortieren.
- Als Admin möchte ich die Übersicht aller geplanten und abgeschlossenen Touren sehen.
- Als System soll nach Touren-Erstellung automatisch ein Fahrer-API-Token verknüpft (oder neu erstellt) werden.

## Acceptance Criteria
- [ ] **Tour generieren:** aus RegularDeliveryTour-Template + Datum → erzeugt `Tour`-Datensatz mit `TourStop`s für alle Kunden mit Bestellungen auf diesem Datum
- [ ] **Tour-Status:** `planned` → `in_progress` (Fahrer startet) → `done` / `cancelled`
- [ ] **Touren-Liste:** Datum, Tour-Name, Fahrer, Anzahl Stops, Status; Filter nach Datum, Status, Fahrer
- [ ] **Tour-Detail:** alle TourStops in Reihenfolge, je Stop: Kundenname, Adresse, Bestellsumme, Status, Fulfillment-Status
- [ ] **Tour-Aufteilung:**
  - „Alle Stops ab Stop X in neue Tour" → neue Tour mit Stops ab X; ursprüngliche Tour behält Stops 1..X-1
  - „Alle Stops bis Stop X in neue Tour" → neue Tour mit Stops 1..X; ursprüngliche Tour behält Stops X+1..N
  - „Stop X in andere Tour verschieben" → einzelner Stop-Transfer mit Auswahl der Ziel-Tour
- [ ] **Umsortierung:** Drag & Drop der TourStops innerhalb einer Tour; `stop_index` wird neu vergeben
- [ ] **Fahrer-Zuweisung:** Fahrer (aus User-Liste mit Rolle Fahrer/Mitarbeiter) einer Tour zuweisen
- [ ] **Fahrer-API-Token:** Wenn Fahrer zugewiesen → prüfen ob aktives Token vorhanden, ggf. neues Token für diesen Fahrer anlegen
- [ ] **Tour löschen:** Nur bei Status `planned`; mit Bestätigung; TourStops werden gelöscht

## Edge Cases
- Tour-Template hat keine Kunden mit Bestellung für das gewählte Datum → Leere Tour (0 Stops), Warnung
- Tour bereits `in_progress`, Split wird versucht → Verweigern
- Stop wird in eine Tour verschoben, die bereits `done` ist → Verweigern
- Fahrer hat mehrere Touren am selben Tag → Warnung im UI, aber erlaubt
- Tour-Aufteilung ergibt Tour mit 0 Stops → Verweigern

## Technical Requirements
- `TourPlannerService` aus bestehender Codebasis übernehmen
- `stop_index`-Neuberechnung bei jeder Reihenfolgeänderung (keine Lücken)
- Split-Operationen in DB-Transaktionen (atomar)

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur (UI-Baum)

```
/admin/touren/fahrer/
│
├── index           ← Touren-Übersicht (Filter: Datum, Status, Fahrer)
├── create          ← Tour generieren (Template + Datum wählen)
└── {id}/           ← Tour-Detail
    ├── Kopfdaten   — Template-Name, Datum, Fahrer (zuweisbar), Status
    ├── Stops-Liste (sortierbar per Drag & Drop)
    │   ├── Stop-Nr., Kundenname, Adresse, Bestellsumme, Status
    │   └── [Stop verschieben] → Dropdown andere Touren
    ├── Aktionsleiste
    │   ├── [Tour aufteilen] → Modal (ab Stop X in neue Tour)
    │   └── [Tour löschen] → nur wenn Status "planned"
    └── Fahrer-API-Token (automatisch verknüpft/erstellt)
```

### Datenmodell

```
driver_tours  [konkrete Tour — instanz eines Templates]
├── id, regular_delivery_tour_id → regular_delivery_tours (nullable)
├── tour_date (DATE)
├── driver_user_id → users (nullable)
├── status  ENUM: planned | in_progress | done | cancelled
├── name (VARCHAR)  ← kopiert vom Template + Datum
└── company_id

driver_tour_stops
├── id, driver_tour_id → driver_tours
├── customer_id → customers
├── order_id → orders (nullable)
├── delivery_address_id → addresses
├── stop_index  (INT, lückenlos, neu berechnet bei jeder Änderung)
├── status  ENUM: pending | delivered | failed | skipped
├── notes (nullable)
└── company_id

driver_api_tokens  [Fahrer-PWA-Authentifizierung]
├── id, user_id → users
├── token (VARCHAR, hashed)
├── active
└── company_id
```

### Tour-Generierung (TourPlannerService)

```
TourPlannerService::generate(RegularDeliveryTour $template, Date $date):

1. Hole alle CustomerTourOrders für dieses Template (sortiert nach stop_index)
2. Filtere: nur Kunden mit Bestellung für $date
3. Erstelle driver_tour (name = template.name + " " + date)
4. Erstelle driver_tour_stops (stop_index = 1..N, lückenlos)
5. Verknüpfe driver_user_id = NULL (Fahrer noch nicht zugewiesen)

→ Warnung wenn 0 Kunden mit Bestellung (Tour wird trotzdem angelegt)
```

### Tour-Aufteilung (Split — DB-Transaktion)

```
Split-Typen:
  A) "Alle Stops ab Stop X in neue Tour"
     → Neue driver_tour erstellen
     → Stops X..N aus Original lösen, stop_index in neuer Tour neu vergeben (1..M)
     → Original behält Stops 1..X-1

  B) "Einzelnen Stop in andere Tour verschieben"
     → Stop aus Quell-Tour entfernen
     → Stop in Ziel-Tour hinzufügen (am Ende)
     → Beide Touren: stop_index neu berechnen

Alles in einer DB-Transaktion (atomar).
```

### Fahrer-Token-Management

```
Bei Fahrer-Zuweisung zu Tour:
  1. Suche aktives driver_api_token für user_id
  2. Existiert → gleichen Token behalten (kein neues)
  3. Existiert nicht → neuen Token generieren + speichern
  4. Token wird dem Fahrer über Admin-UI angezeigt (einmalig)
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| `stop_index` immer neu vergeben | Keine Lücken — Fahrer-App zeigt immer korrekte Reihenfolge |
| Split in DB-Transaktion | Atomarität verhindert inkonsistente Tour-Zustände |
| Fahrer-Token pro User (nicht pro Tour) | Fahrer hat einen permanenten Token für alle seine Touren |
| `in_progress`-Schutz bei Split | Laufende Tour darf nicht aufgeteilt werden — Fahrer ist bereits unterwegs |

### Neue Services / Controller

```
TourPlannerService                   ← generate(), split(), reorderStops()
Admin\FahrertourController           ← index, create, store, show, update (Fahrer), destroy
Admin\FahrertourStopController       ← reorder, move (zu anderer Tour)
Admin\FahrertourSplitController      ← store (Split-Operation)
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
