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

### Komponenten-Struktur (UI-Baum)

```
/admin/lager/
│
├── index                   ← Bestandsübersicht
│   ├── Filter: Lagerort, Warengruppe, [Nur Unterbestand]
│   ├── Tabelle: Produkt | Lagerort | Bestand | Mindestbestand | Differenz
│   │   └── Unterbestand-Zeilen rot hervorgehoben
│   └── Dashboard-Widget: Anzahl kritischer Bestände (Link hierher)
│
├── lagerorte/              ← Lagerort-Verwaltung
│   ├── index               ← Liste (Name, Anzahl Produkte)
│   └── create / edit       ← Name, Adresse, Notiz
│
├── eingang/create          ← Wareneingang buchen
│   ├── Produkt wählen (Suche)
│   ├── Lagerort wählen
│   ├── Menge, Lieferant (optional), Referenz-Nr., Datum
│   └── [Buchen] → stock_movement (inbound)
│
├── korrektur/create        ← Bestandskorrektur / Inventur
│   ├── Produkt + Lagerort wählen
│   ├── Neuer Ist-Bestand eingeben
│   └── Differenz wird angezeigt → [Korrektur buchen]
│
└── bewegungen/             ← Bewegungshistorie
    ├── Filter: Produkt, Lagerort, Typ, Datum
    └── Tabelle: Datum | Typ | Produkt | Menge | Referenz | Benutzer
```

### Datenmodell

```
warehouses  [Lagerorte]
├── id, name, address (nullable), notes (nullable)
└── company_id

product_stock  [Aktueller Bestand je Produkt+Lager]
├── product_id → products
├── warehouse_id → warehouses
├── current_stock  INT (gecacht, atomar aktualisiert)
├── min_stock      INT nullable
└── UNIQUE (product_id, warehouse_id)

stock_movements  [Bewegungsprotokoll — append-only]
├── id
├── product_id, warehouse_id
├── type  ENUM: inbound | outbound | adjustment
├── quantity  INT  (positiv = Eingang, negativ = Ausgang/Korrektur)
├── reference  VARCHAR nullable  (Lieferschein-Nr., Bestell-ID, etc.)
├── notes, user_id, created_at
└── company_id
```

### Bestandsführungs-Prinzip

```
current_stock in product_stock ist der „Live-Bestand":
  → Bei jeder Buchung: product_stock.current_stock += quantity (atomar)
  → stock_movements ist das unveränderliche Protokoll

KEINE Neuberechnung über SUM(movements) im Betrieb
→ Schnelle Abfragen; kein Performance-Problem bei großem Protokoll

Inventur-Buchung:
  Neuer Bestand = 50, alter Bestand = 43
  → adjustment quantity = +7
  → current_stock = 43 + 7 = 50 ✓
```

### Automatischer Warenausgang (aus Lieferung)

```
Fahrer-PWA (PROJ-16) markiert Stop als delivered:
  → Event: StopDelivered(order_id, items_delivered)
  → StockOutboundListener:
      - Je gelieferter Position: stock_movement (outbound, quantity negativ)
      - Lagerort: Standard-Lager der Firma (aus Einstellungen)
      - current_stock wird reduziert
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| `current_stock` als gecachter Wert | Schnelle Bestandsabfragen ohne SUM über Bewegungsprotokoll |
| Atomare Bestandsänderung | DB-Level Increment/Decrement verhindert Race Conditions bei gleichzeitigen Buchungen |
| `stock_movements` append-only | Vollständiges Audit-Trail; keine nachträglichen Korrekturen möglich |
| Negativbestand erlaubt | Reale Lager haben manchmal Negativbestand (Buchungsfehler); System warnt, blockiert nicht |

### Neue Controller / Services

```
Admin\LagerController           ← index (Bestandsübersicht)
Admin\LagerortController        ← CRUD Lagerorte
Admin\WareneingangController    ← store (Eingang buchen)
Admin\BestandskorrekturController← store (Inventur/Korrektur)
Admin\LagerbewegungController   ← index (Bewegungshistorie)
StockService                   ← bookMovement(), adjustStock()
StockOutboundListener          ← reagiert auf StopDelivered-Event
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
