# PROJ-14: Admin: Regelmäßige Touren & Liefergebiete

## Status: Planned
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- Requires: PROJ-10 (Admin: Kundenverwaltung) — Kunden werden Touren zugeordnet

## Beschreibung
Verwaltung von wiederkehrenden Touren-Templates (RegularDeliveryTour) und deren Liefergebieten. Kunden werden Touren in einer definierten Reihenfolge zugeordnet. Touren können auf bestimmte Kundengruppen eingeschränkt werden. Diese Templates sind die Basis für die Fahrertouren-Erstellung (PROJ-15) und das Tour-Assignment beim Checkout (PROJ-4).

### Kunden-Tour-Zuordnung (Stamm-Tour)

Jeder Kunde hat **eine feste Stamm-Tour** (`CustomerTourOrder`). Diese wird vom Admin zugeordnet, nicht bei jedem Checkout neu gewählt. Die Reihenfolge der Kunden auf einer Tour (Stop-Index) wird ebenfalls vom Admin festgelegt.

Beim Checkout: Die Stamm-Tour des Kunden ist bereits gesetzt → Kunde wählt nur noch das Lieferdatum aus den verfügbaren Terminen der Tour.

**Erstregistrierung / kein Tour-Assignment:** Wenn ein neuer Kunde noch keiner Tour zugeordnet ist, kann er beim ersten Checkout über PLZ + Ortsname die passende Tour ermitteln und eine Auswahl treffen. Danach wird diese Tour als Stamm-Tour gespeichert (durch Admin bestätigt oder automatisch).

### Liefergebiet-Logik: PLZ + Ortsname (für Erst-Zuordnung)

Eine PLZ allein reicht nicht für eindeutiges Tour-Assignment, da Ortsteile mit gleicher PLZ verschiedenen Touren zugeordnet sein können.

**Beispiel:**
```
PLZ 64372  Ober-Ramstadt          → Tour "Dienstag Ober-Ramstadt"
PLZ 64372  Ober-Ramstadt-Modau    → Tour "Donnerstag Modautal"
```

`DeliveryArea` enthält daher: PLZ + optionaler Ortsname/Ortsteil (Teilstring-Match).
Wird nur zur **Erst-Zuordnung** genutzt, nicht bei jedem Checkout erneut berechnet.

## User Stories
- Als Admin möchte ich regelmäßige Touren-Templates anlegen (z.B. „Dienstag Innenstadt", „Donnerstag Nordring").
- Als Admin möchte ich für jede Tour Liefergebiete (PLZ + optionaler Ortsname) hinterlegen.
- Als Admin möchte ich eine Tour auf bestimmte Kundengruppen einschränken.
- Als Admin möchte ich Kunden einer Tour zuordnen und ihre **feste Stop-Reihenfolge** vergeben.
- Als Admin möchte ich die Reihenfolge der Kunden auf einer Tour per Drag & Drop anpassen.
- Als Admin möchte ich einen Mindestbestellwert pro Tour konfigurieren.
- Als neuer Kunde ohne Stamm-Tour möchte ich beim ersten Checkout anhand meiner PLZ + Ort die passende Tour finden und auswählen.

## Acceptance Criteria
- [ ] **RegularDeliveryTour-Liste:** Name, Wochentag/Frequenz, Anzahl zugeordneter Kunden, Mindestbestellwert
- [ ] **Tour anlegen/bearbeiten:** Name, Frequenz (täglich/wöchentlich/14-tägig), Wochentag(e), Mindestbestellwert (milli), Notiz, **erlaubte Kundengruppen** (leer = alle)
- [ ] **Liefergebiete (DeliveryArea):** pro Eintrag: PLZ (Pflicht) + Ortsname/Ortsteil (optional, Teilstring); mehrere Einträge pro Tour möglich
  - Beispiel-Einträge für eine Tour:
    ```
    PLZ: 64372  Ort: (leer)          → trifft alle mit PLZ 64372
    PLZ: 64372  Ort: Modau            → trifft nur PLZ 64372 + Ort enthält „Modau"
    PLZ: 64395  Ort: (leer)          → trifft alle mit PLZ 64395
    ```
  - Match-Logik: PLZ exakt UND (Ort-Feld leer ODER Kundenadresse enthält Ort-Teilstring)
- [ ] **Tour-Auswahl beim Checkout (PROJ-4):**
  - Genau 1 Treffer → automatisch zuordnen
  - Mehrere Treffer → Kunde wählt aus Liste der passenden Touren (Name + nächster Liefertag angezeigt)
  - Kein Treffer → `regular_delivery_tour_id = NULL`; Hinweis im Checkout + Log-Eintrag
- [ ] **Kundengruppen-Einschränkung:** `allowed_customer_group_ids` (leer = keine Einschränkung); beim Checkout werden nur Touren angezeigt, für die die Kundengruppe des Kunden erlaubt ist
- [ ] **Kunden-Zuordnung (CustomerTourOrder):** Kunden per Suche zu Tour hinzufügen; **fester Stop-Index** wird vom Admin vergeben; Drag & Drop zur Umsortierung
  - Ein Kunde ist immer genau einer Stamm-Tour zugeordnet (nicht mehreren)
  - Der Stop-Index ist eine Admin-Entscheidung, nicht vom Kunden wählbar
  - `CustomerTourOrder` hat: `customer_id`, `regular_delivery_tour_id`, `stop_index`
- [ ] Tour-Templates können deaktiviert werden (keine neuen konkreten Touren mehr, bestehende bleiben)

## Edge Cases
- PLZ liegt in keiner Tour-Zone → `NULL`; Checkout zeigt Hinweis „Kein Liefergebiet gefunden, bitte Kontakt aufnehmen"; Log-Eintrag
- PLZ liegt in mehreren Touren, aber Kundengruppe ist nur für eine erlaubt → automatisch zuordnen (kein Dialog)
- PLZ liegt in mehreren Touren, Kundengruppe für alle erlaubt → Kunde wählt aktiv aus
- Ort-Teilstring matcht auf mehrere Touren (z.B. „Modau" in „Modau" und „Modaubach") → alle Treffer werden zur Auswahl angeboten
- Kunde gibt beim Checkout eine neue Adresse ein (Ort-Feld leer) → PLZ-Match ohne Ortsteil; bei Mehrfach-Treffern Auswahl-Dialog
- Kunde wird aus Tour entfernt → stop_index der restlichen Kunden wird neu nummeriert
- Kundengruppe einer Tour wird nachträglich eingeschränkt, Kunden sind bereits zugeordnet → bestehende Zuordnungen bleiben; neuer Checkout für nicht-erlaubte Gruppe zeigt Tour nicht mehr an
- Tour-Template wird deaktiviert, hat aber noch zukünftige konkrete Touren → Warnung

## Technical Requirements
- `DeliveryArea`: Felder `postal_code` (VARCHAR, exakt), `city_match` (VARCHAR nullable, Teilstring-Match mit LIKE oder ILIKE)
- Match-Abfrage: `WHERE postal_code = ? AND (city_match IS NULL OR :city ILIKE CONCAT('%', city_match, '%'))`
- `RegularDeliveryTour`: `allowed_customer_group_ids` als JSON-Array oder Pivot-Tabelle `regular_delivery_tour_customer_groups`
- `CustomerTourOrder`: `customer_id`, `regular_delivery_tour_id`, `stop_index` (unique per Tour)
- `TourAssignmentService` als stateless Service (wiederverwendbar in Checkout); gibt Liste passender Touren zurück (nicht nur erste)

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur (UI-Baum)

```
/admin/touren/regelmaessig/
│
├── index           ← Tour-Templates-Liste (Name, Frequenz, Anzahl Kunden, Status)
└── {id}/           ← Tour-Template-Detail (Tab-Layout)
    ├── [Tab] Stammdaten       — Name, Frequenz, Wochentage, Mindestbestellwert
    │                            Erlaubte Kundengruppen (Multiselect)
    ├── [Tab] Liefergebiete    — PLZ + optionaler Ortsname; Inline-Bearbeitung
    └── [Tab] Kundenzuordnung  — Kundenliste mit Stop-Index; Drag & Drop Reihenfolge
```

### Datenmodell

```
regular_delivery_tours
├── id, name ("Dienstag Innenstadt")
├── frequency  ENUM: daily | weekly | biweekly
├── weekdays   JSON: [1,2] = Montag+Dienstag (ISO-Wochentage)
├── min_order_value_milli  (nullable)
├── notes (nullable), active
└── company_id

regular_delivery_tour_customer_groups  [Pivot: erlaubte Kundengruppen]
├── regular_delivery_tour_id → regular_delivery_tours
└── customer_group_id → customer_groups
  (leer = alle Kundengruppen erlaubt)

delivery_areas  [Liefergebiete]
├── id, regular_delivery_tour_id → regular_delivery_tours
├── postal_code  (VARCHAR, exakt)
├── city_match   (VARCHAR nullable, Teilstring-Match)
└── company_id

customer_tour_orders  [Stamm-Tour-Zuordnung]
├── customer_id → customers (unique — ein Kunde = eine Stamm-Tour)
├── regular_delivery_tour_id → regular_delivery_tours
├── stop_index  (INT, einmalig pro Tour)
└── company_id
```

### TourAssignmentService

```
resolveTours(string $postalCode, string $city, int $customerGroupId): array

1. SELECT tours WHERE:
   delivery_areas.postal_code = $postalCode
   AND (delivery_areas.city_match IS NULL
        OR $city LIKE CONCAT('%', city_match, '%'))

2. Filtern: nur Touren wo Kundengruppe erlaubt ist
   (leere allowed_groups = alle erlaubt)

3. Return: sortierte Liste passender RegularDeliveryTour-Objekte

Verwendung: im Checkout (PROJ-4) + Admin-Neuzuordnung
```

### Drag & Drop (Stop-Reihenfolge)

Alpine.js auf der Kundenzuordnungs-Liste:
```
[⠿] Müller GmbH      Stop: 1
[⠿] Bäckerei Schmitt Stop: 2   ← ziehbar
[⠿] Hotel Adler      Stop: 3

→ Nach Drop: PATCH /admin/touren/regelmaessig/{id}/kunden/reorder
  Payload: [{customer_id: 5, stop_index: 1}, ...]
  → Alle stop_index-Werte werden neu vergeben (keine Lücken)
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| PLZ + Ortsname (Teilstring) | PLZ allein reicht nicht (Ortsteile mit gleicher PLZ, verschiedene Touren) |
| `customer_tour_orders` mit unique `customer_id` | Ein Kunde hat immer genau eine Stamm-Tour; kein Chaos beim Checkout |
| Pivot-Tabelle für Kundengruppen | Flexibler als JSON-Array in Spalte; einfachere Abfragen |
| `stop_index` immer lückenlos neu berechnet | Vermeidet Sortier-Probleme bei Fahrertour-Erstellung (PROJ-15) |

### Neue Controller / Services

```
Admin\RegularTourController            ← index, show, create, store, update, destroy
Admin\RegularTourDeliveryAreaController← store, update, destroy
Admin\RegularTourKundeController       ← attach, reorder, detach
TourAssignmentService                  ← resolveTours()
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
