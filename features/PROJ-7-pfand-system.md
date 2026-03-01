# PROJ-7: Pfand-System

## Status: Planned
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- None (Fundament-Feature)

## Beschreibung
Rekursives Pfand-Berechnungssystem basierend auf PfandItems (atomare Pfandwerte) und PfandSets (Baum-Strukturen). **Das Pfand ist dem Gebinde zugeordnet** (nicht dem Produkt direkt). Die Kalkulation ist rein serverseitig, Integer-basiert und zyklusgeschützt. Pfand-Rücknahme erfolgt über OrderAdjustments nach Lieferung.

### Pfand & MwSt. — B2C vs. B2B (wichtige Besonderheit)

Der gespeicherte Pfandwert (`wert_milli` im PfandItem) ist der **kanonische Nennwert** (z.B. 3,42 €). Dieser wird je nach Kundentyp unterschiedlich behandelt:

| Kundentyp | `is_business` | Behandlung | Beispiel (Bier 19%) | Beispiel (Milch 7%) |
|---|---|---|---|---|
| Privatkunde (B2C) | `false` | Pfand = **Bruttobetrag** (MwSt. eingeschlossen, kein separater Ausweis) | 3,42 € brutto | 3,42 € brutto |
| Geschäftskunde (B2B) | `true` | Pfand = **Nettobetrag** + MwSt. des **Artikels** | 3,42 € netto + 19% = 4,07 € | 3,42 € netto + 7% = 3,66 € |

**Die MwSt. auf den Pfand erbt sich vom Artikel** (`product.tax_rate.rate_basis_points`).
Das Snapshot-Feld `deposit_tax_rate_basis_points` auf `order_items` speichert:
- `0` für B2C (kein separater MwSt.-Ausweis)
- `product.tax_rate.rate_basis_points` für B2B (z.B. `190_000` für 19%, `70_000` für 7%)

### Konkretes Beispiel

```
PfandItem "Glasflasche 0,7l"   wert_milli = 150_000   (0,15 €)
PfandItem "Holzkasten 12er"    wert_milli = 1_620_000  (1,62 €)

PfandSet "Kasten 12x0,7l Glas"
  └─ PfandItem "Glasflasche 0,7l"  qty=12  → 12 × 0,15 € = 1,80 €
  └─ PfandItem "Holzkasten 12er"   qty=1   →  1 × 1,62 € = 1,62 €
                                              ──────────────────────
                                   Gesamt (wert_milli)    = 3,42 €

Gebinde "12x0,7 Glas"  →  pfand_set_id → "Kasten 12x0,7l Glas"

Produkt "Elisabethenquelle Pur 12x0,7 Glas" (MwSt. 19%)
  B2C-Kunde:  3,42 € brutto  (kein separater MwSt.-Ausweis)
  B2B-Kunde:  3,42 € netto + 19% = 4,07 € brutto  (MwSt. separat)
```

## User Stories
- Als System möchte ich den Pfandbetrag für ein Produkt (via Gebinde → PfandSet-Baum) berechnen.
- Als Admin möchte ich PfandItems (atomare Werte) anlegen, bearbeiten und deaktivieren.
- Als Admin möchte ich PfandSets (Gruppen) mit beliebig verschachtelten PfandItems und PfandSets anlegen.
- Als Admin möchte ich Gebinde mit einem PfandSet verknüpfen.
- Als Mitarbeiter möchte ich nach einer Lieferung die tatsächlich zurückgegebenen Leergut-Mengen erfassen (Closeout).
- Als System soll der Pfand-Betrag beim Checkout als Snapshot auf dem OrderItem eingefroren werden.

## Acceptance Criteria
- [ ] `PfandItem`: atomarer Pfandwert mit `name` und `wert_milli` (Integer, kanonischer Nennwert)
- [ ] `PfandSet`: benannte Gruppe; Komponenten (`PfandSetComponent`) können sein:
  - Blatt: Verweis auf `pfand_item_id` + `qty`
  - Verschachteltes Set: Verweis auf `child_pfand_set_id` + `qty`
- [ ] **Pfand ist am Gebinde hinterlegt** — Produkte erben Pfand über ihr Gebinde (`gebinde.pfand_set_id`)
- [ ] `PfandCalculator::totalForGebinde(Gebinde)` traversiert PfandSet-Baum rekursiv, summiert alle Werte; Ergebnis = kanonischer Nennwert (milli-cents)
- [ ] Zyklusschutz: `$visited`-Array verhindert infinite loops bei fehlerhafter Konfiguration
- [ ] `OrderPricingService::resolvePfandSnapshot(Product, Customer)` gibt zurück:
  - `pfand_set_id` (für Rückverfolgung)
  - `unit_deposit_milli` (kanonischer Nennwert)
  - `deposit_tax_rate_basis_points`:
    - `0` wenn `is_business = false` (B2C, kein separater MwSt.-Ausweis)
    - `product.tax_rate.rate_basis_points` wenn `is_business = true` (B2B, MwSt. erbt sich vom Artikel)
- [ ] Snapshot auf `order_items`: `unit_deposit_milli` + `deposit_tax_rate_basis_points` (beide eingefroren)
- [ ] **Rechnungs-Deposit-Zeile:**
  - B2C (`deposit_tax_rate_basis_points = 0`): Betrag = `unit_deposit_milli` brutto, kein separater MwSt.-Ausweis
  - B2B (`deposit_tax_rate_basis_points > 0`): Betrag = `unit_deposit_milli` netto, MwSt. (Satz des Artikels) wird separat ausgewiesen
- [ ] Kunden mit `is_deposit_exempt = true` → `unit_deposit_milli = 0`, `deposit_tax_rate_basis_points = 0`
- [ ] Leergut-Rücknahme (Closeout): negative `OrderAdjustment` nach Lieferung; geht in Rechnungs-Draft ein; MwSt.-Behandlung analog zum Original-Deposit des Kunden
- [ ] Admin-UI für PfandItems und PfandSets: CRUD-Formulare (Teil von PROJ-9 Stammdaten)

## Edge Cases
- PfandSet ohne Komponenten → Pfandbetrag = 0 (kein Fehler)
- Produkt ohne Gebinde → kein Pfand (Unit-Deposit = 0)
- Gebinde ohne PfandSet → kein Pfand (Unit-Deposit = 0)
- Zyklushafte PfandSet-Struktur (A → B → A) → Zyklusschutz greift, Fehler in Logs, Betrag = 0
- `is_deposit_exempt`-Status des Kunden ändert sich nach Bestellung → bestehende Bestellungen behalten ihren Snapshot
- `is_business`-Status des Kunden ändert sich nach Bestellung → bestehende Bestellungen behalten ihren `deposit_tax_rate_basis_points`-Snapshot
- Pfandwert eines PfandItems wird nachträglich geändert → bestehende Bestellungen behalten ihren Snapshot (unveränderlich)
- Leergut-Closeout für B2B-Kunden → Rücknahme-Adjustment muss ebenfalls netto + MwSt. des Artikels ausweisen (Storno der ursprünglichen Deposit-Zeile, gleicher `deposit_tax_rate_basis_points`-Wert)

## Technical Requirements
- `PfandCalculator` ist stateless und vollständig unit-testbar (aus bestehender Codebasis übernehmen)
- Alle Pfandwerte als Integer in milli-cents
- Kein float je in Pfandberechnungen

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur

```
Laravel (shop-tours)
│
├── app/Services/
│   ├── PfandCalculator              ← Rekursive Traversierung (stateless)
│   └── OrderPricingService          ← Deposit-Snapshot (nutzt PfandCalculator)
│
└── database/migrations/
    ├── create_pfand_items           ← Atomare Pfandwerte
    ├── create_pfand_sets            ← Benannte Gruppen
    ├── create_pfand_set_components  ← Baum-Knoten
    ├── add_pfand_set_id_to_gebinde  ← Gebinde-Verknüpfung (in PROJ-9 Stammdaten-Migration)
    └── order_items (PROJ-3/4)
        ├── pfand_set_id                  ← Snapshot: Rückverfolgung
        ├── unit_deposit_milli            ← Snapshot: kanonischer Nennwert
        └── deposit_tax_rate_basis_points ← Snapshot: 0 (B2C) | MwSt.-Satz (B2B)
```

### Datenmodell

```
pfand_items
├── id, name ("Glasflasche 0,7l")
├── wert_milli    — z.B. 150_000 (0,15 €)  — kanonischer Nennwert
├── active
└── company_id

pfand_sets
├── id, name ("Kasten 12x0,7l Glas")
├── active
└── company_id

pfand_set_components  [Baum-Knoten]
├── id
├── pfand_set_id         → pfand_sets  (der parent)
├── component_type       — ENUM: 'item' | 'set'
├── pfand_item_id        (nullable) → pfand_items    [wenn type = item]
├── child_pfand_set_id   (nullable) → pfand_sets     [wenn type = set]
├── qty                  — Multiplikator (z.B. 12 Flaschen pro Kasten)
└── company_id
```

### Pfand-Traversierung (PfandCalculator)

```
PfandCalculator::totalForGebinde(Gebinde $g): int

  → wenn $g.pfand_set_id === null:  return 0  (kein Pfand)
  → return traverseSet($g.pfand_set_id, $visited = [])

traverseSet(int $setId, array &$visited): int

  → wenn $setId IN $visited:
      Log::error("PfandSet-Zyklus erkannt: $setId")
      return 0   ← Zyklusschutz

  → $visited[] = $setId
  → $total = 0
  → für jede Komponente des Sets:
      wenn type = 'item':  $total += component.pfand_item.wert_milli × component.qty
      wenn type = 'set':   $total += traverseSet(component.child_pfand_set_id, $visited) × component.qty
  → return $total
```

**Konkretes Beispiel:**
```
PfandSet "Kasten 12x0,7l Glas":
  → item "Glasflasche 0,7l"  × 12  =  150_000 × 12  = 1_800_000
  → item "Holzkasten 12er"   × 1   =  1_620_000 × 1 = 1_620_000
  → Gesamt: 3_420_000 milli-cents (3,42 €)
```

### Deposit-Snapshot (OrderPricingService)

```
resolvePfandSnapshot(Product $p, Customer $c): array

  → wenn $c.is_deposit_exempt:
      return [unit_deposit_milli: 0, deposit_tax_rate_basis_points: 0, pfand_set_id: null]

  → unit_deposit_milli = PfandCalculator::totalForGebinde($p.gebinde)

  → wenn $c.is_business:
      deposit_tax_rate_basis_points = $p.tax_rate.rate_basis_points  ← MwSt. erbt sich vom Artikel
    sonst:
      deposit_tax_rate_basis_points = 0   ← B2C: Bruttobetrag, kein separater MwSt.-Ausweis

  → return {pfand_set_id, unit_deposit_milli, deposit_tax_rate_basis_points}
```

### Rechnungs-Deposit-Zeile

| Kundentyp | `deposit_tax_rate_basis_points` | Rechnungsausweis |
|---|---|---|
| B2C (`is_business = false`) | `0` | Einzeiliger Bruttobetrag: „Pfand 3,42 €" |
| B2B (`is_business = true`) | z.B. `190_000` (19%) | Netto 3,42 € + MwSt. 19% = 4,07 € |

### Leergut-Closeout (OrderAdjustment)

```
Nach Lieferung erstellt Fahrer/Admin einen OrderAdjustment:
  type:             'deposit_return'
  menge:            z.B. -10 (10 Kisten zurück)
  unit_value_milli: unit_deposit_milli des Original-OrderItems
  tax_rate:         deposit_tax_rate_basis_points des Original-OrderItems
  → Negatives Delta: reduziert Rechnungsbetrag (oder Gutschrift)
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| Pfand am Gebinde (nicht am Produkt) | Ein Gebinde (z.B. „12x0,7l Glas") gilt für viele Produkte; Pfand nicht pro Artikel duplizieren |
| PfandSet-Baum (rekursiv) | Flexibel für alle Kombinationen (Flasche im Kasten im Palette); praxiserprobt |
| `$visited`-Zyklusschutz | Fehlkonfigurationen sind möglich; kein Stack Overflow — graceful degradation |
| Snapshot auf order_items | Preisänderungen am Pfand-Set dürfen bestehende Rechnungen nicht verändern |
| B2C = Brutto, B2B = Netto + MwSt. | Rechtliche Anforderung (Umsatzsteuer-Pflicht für Geschäftskunden); MwSt.-Satz erbt sich vom Artikel, da Pfand steuerlich dem begleiteten Produkt folgt |

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
