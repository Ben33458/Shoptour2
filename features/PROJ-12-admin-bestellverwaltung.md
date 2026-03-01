# PROJ-12: Admin: Bestellverwaltung

## Status: Planned
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- Requires: PROJ-4 (Checkout) — Bestellungen werden über den Checkout angelegt
- Requires: PROJ-6 (Preisfindung) — Preise auf Bestellungen sind Snapshots
- Requires: PROJ-7 (Pfand-System) — Pfand auf Bestellpositionen

## Beschreibung
Vollständige Bestellverwaltung für Mitarbeiter und Admins: Bestellliste mit Filtern, Detailansicht, Positionen bearbeiten (Menge, Preis, neue Positionen hinzufügen), Status-Management, Leergut/Bruch-Erfassung nach Lieferung (Closeout), Backorder-Handling.

## User Stories
- Als Mitarbeiter möchte ich alle Bestellungen mit Filtern (Status, Datum, Kunde, Tour) sehen.
- Als Mitarbeiter möchte ich eine Bestellung öffnen und alle Details sehen.
- Als Mitarbeiter möchte ich Bestellpositionen bearbeiten (Menge, Preis korrigieren) oder neue hinzufügen.
- Als Mitarbeiter möchte ich den Status einer Bestellung ändern (Neu → Bestätigt → In Lieferung → Abgeschlossen).
- Als Mitarbeiter möchte ich nach der Lieferung Leergut-Rücknahmen und Bruch erfassen (Closeout).
- Als Admin möchte ich eine Bestellung stornieren.
- Als System soll bei Bestelländerungen ein Audit-Log-Eintrag erstellt werden.

## Acceptance Criteria
- [ ] Bestellliste: Bestellnummer, Datum, Kunde, Status, Liefertermin, Tour-Zuordnung, Gesamtbetrag; sortierbar und filterbar (Status, Datum-Range, Kunde, Tour, Lieferart)
- [ ] Bestelldetail: Kopfdaten (Kunde, Adressen, Zahlungsmethode, Tour), alle Positionen mit Snapshots, Gesamtsummen
- [ ] Positionen bearbeiten:
  - Menge ändern (mit Neuberechnung der Zeilensummen)
  - Preis korrigieren (Overwrite des Snapshots, mit Begründungsfeld)
  - Position hinzufügen (Produkt suchen, Preis wird frisch berechnet und als Snapshot gesetzt)
  - Position löschen
- [ ] Backorder-Flag auf Positionen setzen/entfernen
- [ ] Status-Workflow: `new` → `confirmed` → `in_delivery` → `completed` → (`cancelled`)
  - Stornierung nur mit Begründung; stornierte Bestellungen sind unveränderlich
- [ ] Closeout (nach Lieferung): für jede Position: gelieferte Menge, nicht-gelieferte Menge, Grund für Abweichung
- [ ] Leergut-Rücknahme (Closeout): negative OrderAdjustments; Typ: Leergut/Bruch, Betrag, Menge
- [ ] Preis-Override auf Position: Audit-Log-Eintrag mit altem Preis, neuem Preis, User, Zeitstempel
- [ ] Export: Bestellliste als CSV
- [ ] Admin-Notiz-Feld auf Bestellung (intern, nicht für Kunde sichtbar)

## Edge Cases
- Bestellung bereits in Rechnung finalisiert → Bearbeitung nicht mehr möglich (read-only)
- Bestellung ist Teil einer aktiven Tour → Änderungen werden in der Fahrer-App reflektiert (oder Tour muss neu generiert werden → Warnung)
- Preis-Override ergibt Gesamtbetrag = 0 → Erlaubt (Kulanzlieferung), aber Bestätigung nötig
- Neue Position hinzufügen: Produkt deaktiviert → Warnung, trotzdem hinzufügen erlaubt (Auslieferung läuft noch)
- Closeout: Mehr Leergut zurückgegeben als geliefert → Validierungsfehler

## Technical Requirements
- Alle Bestelländerungen in `audit_logs` protokollieren
- Integer-Arithmetik für alle Betragsberechnungen
- Bestelländerungen nach Rechnungsfinalisierung: nur lesend

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur (UI-Baum)

```
/admin/bestellungen/
│
├── index           ← Bestellliste (Filter: Status, Datum-Range, Kunde, Tour, Lieferart)
└── {id}/           ← Bestelldetail
    ├── Kopfdaten   — Kunde, Adressen, Zahlungsmethode, Tour, Datum
    ├── Positionen-Tabelle (editierbar wenn nicht finalisiert/storniert)
    │   ├── [Menge ändern] → inline (Alpine.js)
    │   ├── [Preis korrigieren] → Modal (Begründungsfeld Pflicht)
    │   ├── [+ Neue Position] → Produkt-Autocomplete
    │   └── [Position löschen]
    ├── [Backorder-Flags setzen/entfernen]
    ├── Status-Steuerung
    │   ├── [Bestätigen] / [In Lieferung] / [Abschließen]
    │   └── [Stornieren] → Modal (Begründungsfeld Pflicht)
    ├── Closeout-Bereich (nach Status "in_delivery")
    │   ├── Gelieferte / nicht-gelieferte Menge pro Position
    │   └── Leergut-Rücknahmen (negative OrderAdjustments)
    └── Admin-Notiz-Feld (intern, jederzeit editierbar)
```

### Status-Workflow

```
new → confirmed → in_delivery → completed
               ↘               ↘
            cancelled        cancelled (mit Begründung)

Nach completed: read-only (außer Admin-Notiz)
Nach Rechnungsfinalisierung (PROJ-13): absolut read-only
```

### Preis-Override + Audit-Log

```
Admin korrigiert Preis auf OrderItem:
1. Neuer Preis eingeben + Begründung (Pflicht)
2. Altes unit_price_net_milli → audit_logs
3. OrderItem mit neuem Preis aktualisieren
4. Eintrag: user_id, action='price_override', entity='order_item',
   entity_id, old_value, new_value, reason, company_id
```

### Closeout-Workflow

```
Nach Status "in_delivery" → Admin erfasst pro OrderItem:
  fulfilled_quantity  (≤ original quantity)
  backorder_quantity  (= quantity - fulfilled_quantity)

Leergut-Rücknahme → neue OrderAdjustment:
  type = 'deposit_return'
  unit_value_milli = -(unit_deposit_milli des OrderItem)
  quantity = Anzahl zurückgegebener Gebinde
  tax_rate_basis_points = deposit_tax_rate_basis_points des OrderItem
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| Audit-Log für alle Preisänderungen | Rechtliche Anforderung; Nachvollziehbarkeit bei Streitigkeiten |
| Read-only nach Rechnungsfinalisierung | Rechnungen sind Rechtsdokumente |
| Negative OrderAdjustments für Leergut | `InvoiceService` kann sie direkt als Rechnungszeilen einlesen |

### Neue Controller

```
Admin\BestellungController           ← index, show, update (Status), cancel
Admin\BestellungPositionController   ← store, update, destroy
Admin\BestellungCloseoutController   ← store
Admin\BestellungAdjustmentController ← store, destroy
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
