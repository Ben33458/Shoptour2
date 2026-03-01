# PROJ-6: Preisfindung

## Status: Planned
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- None (Fundament-Feature)

## Beschreibung
Dreistufige Preisfindung: (1) Kundenindividueller Preis, (2) Kundengruppen-Preis, (3) Basispreis ± Gruppenanpassung. Alle Beträge in Integer-Arithmetik (milli-cents, nie float). Preise können netto oder brutto-basiert konfiguriert sein.

## User Stories
- Als System möchte ich für ein Produkt und einen Kunden den korrekten Preis ermitteln (höchste Priorität zuerst).
- Als Admin möchte ich kundenindividuelle Preise mit Gültigkeitszeitraum hinterlegen.
- Als Admin möchte ich Gruppenpreise für Kundengruppen mit Gültigkeitszeitraum hinterlegen.
- Als Admin möchte ich Basispreise auf Produkten hinterlegen und Kundengruppen-Anpassungen (Aufschlag/Abschlag, fix oder prozentual) konfigurieren.
- Als Admin möchte ich für Gäste eine Standard-Kundengruppe für die Preisanzeige festlegen.

## Acceptance Criteria
- [ ] Priorisierung: Kundenpreis (valid) → Gruppenpreis (valid) → Basispreis ± Gruppenanpassung
- [ ] Kundenpreise: `customer_prices` Tabelle mit `valid_from`, `valid_to` (NULL = unbegrenzt), `price_net_milli`
- [ ] Gruppenpreise: `customer_group_prices` Tabelle analog zu Kundenpreisen
- [ ] Gruppenanpassung auf Basispreis: Typen `none`, `fixed` (±milli), `percent` (basis_points)
- [ ] Alle Beträge als Integer in milli-cents (1€ = 1_000_000 milli-cents)
- [ ] MwSt.-Berechnung: aus `tax_rates.rate_basis_points` (190_000 = 19%, 70_000 = 7%)
- [ ] Brutto = Netto × (1 + rate_basis_points / 1_000_000) — Integer-Division ohne Rundungsfehler
- [ ] Kundengruppe `is_deposit_exempt = true` → Pfand wird nicht berechnet (PROJ-7)
- [ ] Gast-Preisgruppe ist in `app_settings` konfigurierbar (`guest_customer_group_id`)
- [ ] `PriceResolverService` ist stateless und vollständig unit-testbar
- [ ] Keine Preisberechnung clientseitig (immer serverseitig)

## Edge Cases
- Kein gültiger Preis auf keiner Stufe vorhanden → `null` zurückgeben; UI zeigt „Preis auf Anfrage"
- Mehrere gültige Kundenpreise für dasselbe Produkt/Kunde → niedrigster Preis gewinnt (oder neuester? → TBD: neuester nach `valid_from`)
- Gruppenanpassung ergibt negativen Preis → Mindestwert 0 (kein negativer Verkaufspreis)
- `tax_rate_id` auf Produkt ist NULL → Fehler werfen, nicht silent auf 19% fallen (kein `?? 190_000`)
- Preise für deaktivierte Produkte → werden trotzdem berechnet (für Rechnungs-Backfills)

## Technical Requirements
- Implementierung: `PriceResolverService` (stateless, aus bestehender Laravel-Codebasis übernehmen)
- Interface `PricingRepositoryInterface` für Testbarkeit
- Kein float je in Preisberechnungen — ausschließlich PHP int
- Performance: Preise für Produktliste per Batch-Query auflösen (kein N+1)

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur

```
Laravel (shop-tours)
│
├── app/Services/
│   ├── PriceResolverService        ← Dreistufige Preisfindung (stateless)
│   └── PricingRepository           ← DB-Abfragen (entkoppelt für Tests)
│
├── database/migrations/
│   ├── create_tax_rates            ← MwSt.-Sätze
│   ├── create_customer_groups      ← Kundengruppen mit Basispreis-Anpassung
│   ├── create_customer_group_prices← Gruppenpreise (zeitlich begrenzt)
│   └── create_customer_prices      ← Kundenindividuelle Preise
│
└── app_settings (via PROJ-19)
    └── guest_customer_group_id     ← Gast-Preisgruppe
```

### Datenmodell

```
tax_rates
├── id, name ("MwSt. 19%")
├── rate_basis_points  — 190_000 = 19%, 70_000 = 7%
└── company_id

customer_groups
├── id, name
├── adjustment_type    — ENUM: none | fixed | percent
├── adjustment_value_milli  — Aufschlag/Abschlag
│     none:    ignoriert
│     fixed:   ± milli-cents auf Basispreis
│     percent: basis_points (100_000 = 10%)
├── is_deposit_exempt  — Pfand wird nicht berechnet (→ PROJ-7)
└── company_id

customer_group_prices
├── id, customer_group_id → customer_groups
├── product_id → products
├── price_net_milli      — kanonischer Netto-Preis
├── valid_from (nullable), valid_to (nullable)
└── company_id

customer_prices
├── id, customer_id → customers
├── product_id → products
├── price_net_milli
├── valid_from (nullable), valid_to (nullable)
└── company_id
```

### Preisfindungs-Logik (PriceResolverService)

```
resolve(Product $p, Customer|null $c): ?int

  1. Kundenpreis — wenn $c vorhanden:
     customer_prices WHERE customer_id = $c.id
                     AND product_id = $p.id
                     AND (valid_from IS NULL OR valid_from <= NOW())
                     AND (valid_to   IS NULL OR valid_to   >= NOW())
     → mehrere gültige: neuester (höchste valid_from) gewinnt
     → gefunden: return price_net_milli

  2. Gruppenpreis — Gruppe des Kunden (oder Gast-Gruppe):
     $groupId = $c?.customer_group_id ?? app_settings['guest_customer_group_id']
     customer_group_prices WHERE customer_group_id = $groupId
                             AND product_id = $p.id
                             AND gültig
     → gefunden: return price_net_milli

  3. Basispreis ± Gruppenanpassung:
     base = products.base_price_net_milli
     → adjustment_type = none:    return base
     → adjustment_type = fixed:   return max(0, base + adjustment_value_milli)
     → adjustment_type = percent: return max(0, base × (1_000_000 + adj) / 1_000_000)

  4. Kein Preis gefunden → return null ("Preis auf Anfrage")
```

### MwSt.-Berechnung (Integer-Arithmetik)

```
netto_milli = resolve(...)
brutto_milli = netto_milli * (1_000_000 + rate_basis_points) / 1_000_000
              └── PHP integer division (intdiv), kein float

Beispiel (19%): 5_000_000 netto × 1_190_000 / 1_000_000 = 5_950_000 brutto (5,95 €)
```

### Batch-Loading für Produktlisten (kein N+1)

```
PriceResolverService::resolveBatch(
    Collection $products,
    Customer|null $customer
): array  // [product_id => price_net_milli|null]

→ Schritt 1: Alle Kundenpreise für $customer + $productIds in EINER Query
→ Schritt 2: Alle Gruppenpreise für $groupId + $productIds in EINER Query
→ Schritt 3: Basispreise bereits auf Produkten geladen (eager load)
→ Merge: pro Produkt höchste Priorität anwenden
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| Stateless Service (kein Singleton-State) | Unit-testbar ohne DB; einfaches Mocking |
| `PricingRepository`-Trennung | DB-Queries austauschbar; in Tests durch Arrays ersetzbar |
| `max(0, ...)` bei Anpassungen | Negativer Verkaufspreis ist technisch und kaufmännisch ungültig |
| Neuester Kundenpreis gewinnt | Explizit entschieden (nicht niedrigster), da zeitlich limitierte Sonderpreise gezielte Kundenangebote sind |
| NULL-Rückgabe statt Exception | Kein Preis = legitimer Zustand ("Preis auf Anfrage"); Exception nur bei fehlendem tax_rate |

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
