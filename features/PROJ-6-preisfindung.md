# PROJ-6: Preisfindung

## Status: In Review
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

**Tester:** QA Engineer Agent (Independent Review)
**Datum:** 2026-03-02
**Status:** Failed -- 9 bugs found (1 Critical, 3 High, 3 Medium, 2 Low)

### Files Reviewed

| File | Path |
|------|------|
| PriceResolverService | `d:\Claude_Code\Getraenkeshop\shop-tours\app\Services\Pricing\PriceResolverService.php` |
| EloquentPricingRepository | `d:\Claude_Code\Getraenkeshop\shop-tours\app\Services\Pricing\EloquentPricingRepository.php` |
| PricingRepositoryInterface | `d:\Claude_Code\Getraenkeshop\shop-tours\app\Services\Pricing\PricingRepositoryInterface.php` |
| PriceResult DTO | `d:\Claude_Code\Getraenkeshop\shop-tours\app\DTOs\Pricing\PriceResult.php` |
| CustomerGroup Model | `d:\Claude_Code\Getraenkeshop\shop-tours\app\Models\Pricing\CustomerGroup.php` |
| CustomerPrice Model | `d:\Claude_Code\Getraenkeshop\shop-tours\app\Models\Pricing\CustomerPrice.php` |
| CustomerGroupPrice Model | `d:\Claude_Code\Getraenkeshop\shop-tours\app\Models\Pricing\CustomerGroupPrice.php` |
| TaxRate Model | `d:\Claude_Code\Getraenkeshop\shop-tours\app\Models\Pricing\TaxRate.php` |
| AppSetting Model | `d:\Claude_Code\Getraenkeshop\shop-tours\app\Models\Pricing\AppSetting.php` |
| Customer Model | `d:\Claude_Code\Getraenkeshop\shop-tours\app\Models\Pricing\Customer.php` |
| Migration: tax_rates | `d:\Claude_Code\Getraenkeshop\shop-tours\database\migrations\2024_01_19_000001_create_tax_rates_table.php` |
| Migration: customer_groups | `d:\Claude_Code\Getraenkeshop\shop-tours\database\migrations\2024_01_02_000002_create_customer_groups_table.php` |
| Migration: customer_group_prices | `d:\Claude_Code\Getraenkeshop\shop-tours\database\migrations\2024_01_02_000004_create_customer_group_prices_table.php` |
| Migration: customer_prices | `d:\Claude_Code\Getraenkeshop\shop-tours\database\migrations\2024_01_02_000005_create_customer_prices_table.php` |
| TaxRateSeeder | `d:\Claude_Code\Getraenkeshop\shop-tours\database\seeders\TaxRateSeeder.php` |
| Unit Tests | `d:\Claude_Code\Getraenkeshop\shop-tours\tests\Unit\Pricing\PriceResolverServiceTest.php` |

### Unit Test Execution

35 unit tests in `PriceResolverServiceTest.php` with 59 assertions.
Tests cover: guest resolution, customer price override, group price fallback, fixed/percent adjustments, net-to-gross rounding (including float-drift boundary cases, negative values, exact halves), DTO immutability, and source constant accuracy.

**IMPORTANT CAVEAT:** All tests mock `PricingRepositoryInterface`. The mocks pass `190_000` as the tax rate basis points. The real DB contains `1900`. This means BUG-1 (the most critical bug) is completely invisible in the unit test suite.

### Acceptance Criteria Checklist

- [x] AC-1: Priorisierung: Kundenpreis (valid) -> Gruppenpreis (valid) -> Basispreis +/- Anpassung
- [ ] AC-2: Kundenpreise: customer_prices Tabelle mit valid_from, valid_to, price_net_milli (BUG-3: validity not enforced)
- [ ] AC-3: Gruppenpreise: customer_group_prices Tabelle analog (BUG-3: validity not enforced)
- [x] AC-4: Gruppenanpassung auf Basispreis: Typen none, fixed, percent
- [x] AC-5: Alle Betraege als Integer in milli-cents (1 EUR = 1_000_000 milli-cents)
- [ ] AC-6: MwSt.-Berechnung: aus tax_rates.rate_basis_points (190_000 = 19%) (BUG-1: scale mismatch)
- [x] AC-7: Brutto = Netto x (1 + rate_basis_points / 1_000_000) -- formula correct, input wrong (BUG-1)
- [x] AC-8: Kundengruppe is_deposit_exempt = true (column exists, PROJ-7 enforces)
- [ ] AC-9: Gast-Preisgruppe in app_settings konfigurierbar (BUG-4: naming mismatch with spec)
- [x] AC-10: PriceResolverService ist stateless und unit-testbar
- [x] AC-11: Keine Preisberechnung clientseitig

### Edge Cases Checklist

- [ ] EC-1: Kein gueltiger Preis auf keiner Stufe -> null (BUG-5: no null-return path)
- [ ] EC-2: Mehrere gueltige Kundenpreise -> neuester valid_from (BUG-3: validity ignored)
- [ ] EC-3: Gruppenanpassung ergibt negativen Preis -> Mindestwert 0 (BUG-2: no clamp)
- [x] EC-4: tax_rate_id auf Produkt ist NULL -> Fehler werfen (RuntimeException confirmed)
- [x] EC-5: Preise fuer deaktivierte Produkte -> werden berechnet (no active-flag check)

### Gefundene Bugs

#### BUG-1: tax_rates.rate_basis_points Skalenmismatch zwischen DB und Service
- **Severity:** Critical
- **Datei:** `d:\Claude_Code\Getraenkeshop\shop-tours\database\migrations\2024_01_19_000001_create_tax_rates_table.php:27`, `d:\Claude_Code\Getraenkeshop\shop-tours\database\seeders\TaxRateSeeder.php:21`, `d:\Claude_Code\Getraenkeshop\shop-tours\app\Services\Pricing\PriceResolverService.php:145`
- **Problem:** Die Migration definiert `rate_basis_points` als `unsignedSmallInteger` (max 65535). Der Seeder schreibt `1900` fuer 19% und `700` fuer 7%. Die Konvention in der Migration ist "100 bp = 1%". Aber `PriceResolverService::resolveNetToGross()` rechnet mit der Formel `net * (1_000_000 + taxBp) / 1_000_000`, was die Konvention "1_000_000 bp = 100%" voraussetzt. Die TaxRate-Model-Docblock und die Feature-Spec sagen ebenfalls "190_000 = 19%".
- **Expected:** DB speichert `190_000` fuer 19%. Service errechnet: `1_000_000 * (1_000_000 + 190_000) / 1_000_000 = 1_190_000` brutto (1,19 EUR).
- **Actual:** DB speichert `1900` fuer 19%. Service errechnet: `1_000_000 * (1_000_000 + 1_900) / 1_000_000 = 1_001_900` brutto (1,0019 EUR). Effektiv 0,19% MwSt. statt 19%.
- **Zusaetzliches Problem:** `unsignedSmallInteger` (max 65535) kann 190_000 gar nicht speichern. Die Spalte muss zu `unsignedInteger` oder `unsignedMediumInteger` geaendert werden.
- **Root Cause:** Unit-Tests mocken das Repository und uebergeben `190_000` direkt -- der echte DB-Wert `1900` wird nie getestet. Daher sind alle 35 Tests gruen, obwohl der Produktionscode falsch rechnen wird.
- **Priority:** MUSS vor Deployment gefixt werden. BLOCKIERT ALLE PREISE.

#### BUG-2: Fehlende max(0, ...) Klammerung bei Gruppenanpassungen (negativer Preis moeglich)
- **Severity:** High
- **Datei:** `d:\Claude_Code\Getraenkeshop\shop-tours\app\Services\Pricing\PriceResolverService.php:198-211` (Methode `applyGroupAdjustment`)
- **Problem:** Die Methode `applyGroupAdjustment()` hat keinen `max(0, ...)` Guard. Bei `fixed` wird `$baseNetMilli + $group->price_adjustment_fixed_milli` direkt zurueckgegeben. Bei `percent` wird das Ergebnis der Multiplikation direkt zurueckgegeben.
- **Expected:** `max(0, base + adjustment)` wie in Spec und Tech Design definiert: "Gruppenanpassung ergibt negativen Preis -> Mindestwert 0 (kein negativer Verkaufspreis)". Tech Design: `return max(0, base + adjustment_value_milli)`.
- **Actual:** `base_price_net_milli = 1_000_000`, `adjustment_fixed_milli = -2_000_000` ergibt `-1_000_000` (negativer Preis). Dieser negative Wert geht weiter in die Brutto-Berechnung und erzeugt einen negativen Brutto-Preis.
- **Priority:** Fix vor Deployment

#### BUG-3: valid_from / valid_to werden nie ausgewertet (Zeitfenster-Logik fehlt)
- **Severity:** High
- **Datei:** `d:\Claude_Code\Getraenkeshop\shop-tours\app\Services\Pricing\EloquentPricingRepository.php:32-45`
- **Problem:** `findValidCustomerPrice()` und `findValidGroupPrice()` filtern nicht nach `valid_from`/`valid_to`. Sie rufen `->first()` ohne Date-Filter oder Sortierung auf. Der Code-Kommentar sagt explizit "valid_from / valid_to columns exist in the schema but are not evaluated here" und die Model-Docblocks markieren sie als "deprecated/ignored".
- **Expected:** (laut Spec und Tech Design) `WHERE (valid_from IS NULL OR valid_from <= NOW()) AND (valid_to IS NULL OR valid_to >= NOW())`, sortiert nach `valid_from DESC` (neuester gewinnt).
- **Actual:** Keine Datumsfilterung. Abgelaufene Preise werden zurueckgegeben. Bei mehreren Rows pro Customer+Product mit unterschiedlichen `valid_from` wird die Zeile mit der niedrigsten ID zurueckgegeben (DB-Engine default), nicht die aktuellste.
- **Zusaetzlich:** Das UNIQUE-Index auf `(customer_id, product_id, valid_from)` erlaubt mehrere Rows pro Customer+Product. Die Interface-Docblock sagt aber "Exactly one row per (customer_id, product_id) is guaranteed by the UNIQUE index" -- das stimmt NUR wenn es keine zeitlich verschiedenen Eintraege gibt.
- **Priority:** Fix vor Deployment (blockiert zeitlich begrenzte Sonderpreise)

#### BUG-4: Spec sagt guest_customer_group_id, Implementation nutzt default_customer_group_id
- **Severity:** Low
- **Datei:** `d:\Claude_Code\Getraenkeshop\shop-tours\app\Services\Pricing\PriceResolverService.php:65`
- **Problem:** Die Feature-Spec (AC-9) und das Tech Design referenzieren `guest_customer_group_id`. Die Implementierung verwendet durchgaengig `default_customer_group_id` (Service, Tests, Controller).
- **Expected:** Konsistente Benennung zwischen Spec und Code.
- **Actual:** Namensinkonsistenz. Die Implementation ist intern konsistent, nur die Spec weicht ab.
- **Priority:** Dokumentations-Fix (niedrig)

#### BUG-5: Kein null-Rueckgabepfad wenn kein Preis auf keiner Stufe existiert
- **Severity:** Medium
- **Datei:** `d:\Claude_Code\Getraenkeshop\shop-tours\app\Services\Pricing\PriceResolverService.php:155-186` (Methode `resolveForGroup`)
- **Problem:** Die Methode `resolveForGroup()` faellt immer auf Stufe 3 zurueck: `$product->base_price_net_milli`. Es gibt keinen Code-Pfad der `null` zurueckgibt. Wenn `base_price_net_milli` null ist (z.B. neues Produkt ohne Preis), wird PHP `null` als `int 0` behandeln (wegen `declare(strict_types=1)` koennte das sogar einen TypeError werfen, wenn das Model den Wert nicht castet).
- **Expected:** (laut Spec EC-1 und Tech Design Schritt 4): `null` zurueckgeben wenn kein Preis auf keiner Stufe vorhanden. UI zeigt "Preis auf Anfrage".
- **Actual:** Immer ein PriceResult mit numerischem Wert. Produkte ohne Preis zeigen 0,00 EUR.
- **Priority:** Fix vor Deployment

#### BUG-6: Fehlende company_id auf allen Pricing-Tabellen (Multi-Tenant-Verletzung)
- **Severity:** High
- **Datei:** Alle vier Pricing-Migrations + `d:\Claude_Code\Getraenkeshop\shop-tours\app\Services\Pricing\EloquentPricingRepository.php`
- **Problem:** Die Tabellen `customer_groups`, `customer_group_prices`, `customer_prices` und `tax_rates` haben keine `company_id` Spalte. Die Repository-Queries filtern nicht nach `company_id`. Das Spec-Datenmodell listet `company_id` explizit auf allen vier Tabellen. Die Projekt-Regeln (`.claude/rules/backend.md` und `.claude/rules/security.md`) fordern: "company_id auf ALLEN Tabellen" und "company_id-Check auf ALLEN Datenbankabfragen".
- **Expected:** `company_id` Spalte + Foreign Key auf allen Tabellen, GlobalScope oder explizite WHERE-Klausel im Repository.
- **Actual:** Keine company_id auf diesen Tabellen. `CustomerGroup::find($id)` hat keinen Scope.
- **Impact:** MVP ist single-tenant, daher kein unmittelbares Sicherheitsrisiko. Aber: Spec-Verletzung, Projekt-Regel-Verletzung, und spaetere Multi-Tenant-Migration wird aufwendig.
- **Priority:** Fix im naechsten Sprint

#### BUG-7: resolveBatch() Methode nicht implementiert (N+1-Risiko)
- **Severity:** Medium
- **Datei:** `d:\Claude_Code\Getraenkeshop\shop-tours\app\Services\Pricing\PriceResolverService.php`
- **Problem:** Das Tech Design spezifiziert `PriceResolverService::resolveBatch(Collection $products, Customer|null $customer): array`. Diese Methode existiert nicht. Produktlisten-Seiten muessen `resolveForCustomer()` oder `resolveForGuest()` pro Produkt aufrufen. Jeder Aufruf erzeugt separate DB-Queries (customer_price, group_price, tax_rate).
- **Expected:** Batch-Query: Alle Kundenpreise fuer $productIds in EINER Query, alle Gruppenpreise in EINER Query.
- **Actual:** N Produkte = 3N Queries (N customer_prices + N group_prices + N tax_rates).
- **Priority:** Fix vor Deployment (Performance bei Katalogseiten)

#### BUG-8: Schema/Code-Inkonsistenz -- price_gross_milli in DB aber nicht im Service verwendet
- **Severity:** Low
- **Datei:** `d:\Claude_Code\Getraenkeshop\shop-tours\database\migrations\2024_01_02_000004_create_customer_group_prices_table.php:23`, `d:\Claude_Code\Getraenkeshop\shop-tours\database\migrations\2024_01_02_000005_create_customer_prices_table.php:23`
- **Problem:** Beide Migrations fuer `customer_group_prices` und `customer_prices` definieren eine `price_gross_milli` Spalte. Die Models (`CustomerPrice`, `CustomerGroupPrice`) haben `price_gross_milli` in `$fillable` und `$casts`. Der Service-Kommentar sagt aber explizit: "Neither customer_prices nor customer_group_prices store a gross column" (Zeile 33 in PriceResolverService). Die Spec sagt ebenfalls nur `price_net_milli`. Der Service ignoriert die Spalte und berechnet brutto immer aus netto + Steuersatz.
- **Expected:** Entweder die Spalte entfernen (wenn brutto immer berechnet wird) oder sie konsistent nutzen.
- **Actual:** Die Spalte existiert, kann beschrieben werden (fillable), wird aber nie gelesen. Das kann zu Verwirrung fuehren wenn ein Admin die DB inspiziert und dort veraltete/inkonsistente Brutto-Werte sieht.
- **Priority:** Niedrig (Cleanup)

#### BUG-9: customer_groups.price_adjustment_type ist VARCHAR statt ENUM -- keine DB-Constraint
- **Severity:** Medium
- **Datei:** `d:\Claude_Code\Getraenkeshop\shop-tours\database\migrations\2024_01_02_000002_create_customer_groups_table.php:28`
- **Problem:** Die Spec sagt "ENUM-Spalten bei stabilen Status-Werten" (`.claude/rules/backend.md`). Die Migration definiert `price_adjustment_type` als `$table->string('price_adjustment_type')`. Der Service nutzt einen `match`-Block mit `default => $baseNetMilli` (Zeile 210). Ein ungueltiger Wert wie 'foobar' wuerde stillschweigend den Basispreis ohne Anpassung zurueckgeben -- kein Fehler, kein Log.
- **Expected:** Entweder ENUM in der DB (MySQL unterstuetzt das nativ) oder eine Validierung im Service die bei unbekannten Typen einen Fehler wirft.
- **Actual:** Jeder String-Wert wird akzeptiert. Der `default`-Branch im `match` schluckt ungueltige Werte still.
- **Priority:** Medium (kann zu schwer auffindbaren Bugs fuehren wenn ein Admin einen Tippfehler macht)

### Security Audit (Red-Team Perspective)

#### Authentication / Authorization
- [x] PriceResolverService ist ein reiner Backend-Service, nicht direkt als Route exponiert
- [x] Preisabfragen laufen ueber ShopController mit Auth-Middleware
- [x] Kein direkter User-Input fliesst in Pricing-Queries (customer_id kommt aus Auth-Session)

#### SQL Injection
- [x] Alle Queries nutzen Eloquent ORM / Query Builder (parametrisiert)
- [x] `DB::table('products')->join(...)` in Repository nutzt parametrisierte Joins
- [x] Kein Raw SQL

#### Mass Assignment
- [x] Alle Models definieren `$fillable` (kein `$guarded = []`)
- [x] CustomerGroup: `$fillable` enthaelt `allowed_payment_methods` -- koennte ein Angriffspunkt sein wenn ein Admin-Formular `$request->all()` statt `$request->validated()` nutzt. Zu pruefen im Controller.

#### Multi-Tenant Isolation (company_id)
- [ ] FAIL: Keine company_id auf pricing Tabellen (BUG-6)
- [ ] FAIL: EloquentPricingRepository hat keine company_id WHERE-Klauseln
- [ ] FAIL: `CustomerGroup::find($id)` hat keinen GlobalScope
- **Risiko fuer MVP:** Gering (single-tenant). **Risiko bei Multi-Tenant:** Hoch (Datenleck zwischen Mandanten)

#### Information Disclosure
- [x] PriceResult DTO exponiert nur: net_milli, gross_milli, source, is_guest, customer_group_id
- [ ] HINWEIS: `customer_group_id` in der API-Response koennte Information Disclosure sein. Ein Angreifer koennte Gruppen-IDs enumerieren und daraus Rueckschluesse auf die Kundenstruktur ziehen. Kein akutes Risiko, aber zu beachten.

#### CSRF / Token
- [x] Laravel CSRF aktiv auf Web-Routes
- [x] API-Routes mit Sanctum Token geschuetzt

### Cross-Browser / Responsive Testing

Nicht anwendbar. PROJ-6 ist ein reiner Backend-Service ohne Frontend-Komponenten. Die Preisdarstellung in der Shop-UI wird von PROJ-2 (Produktkatalog) uebernommen.

### Regression Testing

- [x] PROJ-1 (Auth): `default_customer_group_id` in RegisterController und SocialController -- konsistent
- [x] PROJ-2 (Produktkatalog): ShopController nutzt `PriceResolverService` ueber `resolveForGuest()` und `resolveForCustomer()` -- konsistent
- [x] PROJ-7 (Pfand-System): `is_deposit_exempt` Flag auf CustomerGroup Model vorhanden
- [x] PROJ-4 (Checkout): Nutzt Pricing-Service ueber ShopController -- kein Regression-Risiko

### Test-Abdeckungs-Luecken

1. **Kein Integrationstest:** Es gibt nur Unit-Tests mit gemocktem Repository. Ein Integrationstest der den echten `EloquentPricingRepository` mit einer Test-DB nutzt wuerde BUG-1 sofort aufdecken.
2. **Kein Test fuer negativen Preis:** Kein Test prueft ob `applyGroupAdjustment()` bei negativem Ergebnis 0 zurueckgibt (BUG-2).
3. **Kein Test fuer null-Rueckgabe:** Kein Test prueft das Verhalten wenn `base_price_net_milli` null ist (BUG-5).
4. **Kein Test fuer ungueltige adjustment_type:** Kein Test prueft was passiert wenn `price_adjustment_type` einen unbekannten Wert hat (BUG-9).
5. **Kein Test fuer validity windows:** Kein Test prueft die Datumsfilterung (BUG-3).

### Zusammenfassung

| Kategorie | Ergebnis |
|-----------|----------|
| Acceptance Criteria | 7/11 bestanden, 3 teilweise, 1 fehlgeschlagen |
| Edge Cases | 2/5 bestanden, 3/5 fehlgeschlagen |
| Bugs gefunden | 9 total (1 Critical, 3 High, 3 Medium, 2 Low) |
| Unit Tests | 35/35 gruen (aber mocks verbergen BUG-1) |
| Security | company_id Multi-Tenant-Isolation fehlt auf allen Pricing-Tabellen |
| Produktionsreif | NEIN |

### Priorisierte Fix-Empfehlung

1. **SOFORT (P0):** BUG-1 -- tax_rate Skalenmismatch. BLOCKIERT ALLE PREISE. Entweder DB-Schema+Seeder auf 190_000-Skala anpassen ODER Service-Formel auf 10_000-Skala anpassen. Empfehlung: DB anpassen (Spec-konform).
2. **Vor Deployment (P0):** BUG-2 -- `max(0, ...)` Guard einbauen.
3. **Vor Deployment (P0):** BUG-3 -- valid_from/valid_to Filterung implementieren ODER Spalten entfernen und Spec anpassen.
4. **Vor Deployment (P1):** BUG-5 -- null-Rueckgabepfad implementieren.
5. **Vor Deployment (P1):** BUG-7 -- resolveBatch() fuer Performance.
6. **Vor Deployment (P1):** BUG-9 -- Validation oder ENUM fuer adjustment_type.
7. **Naechster Sprint (P2):** BUG-6 -- company_id auf alle Tabellen.
8. **Naechster Sprint (P2):** BUG-8 -- price_gross_milli Spalte bereinigen.
9. **Nice-to-have (P3):** BUG-4 -- Spec-Naming anpassen.

**Naechster Schritt:** Backend-Entwickler soll BUG-1, BUG-2, BUG-3 fixen. Danach erneuter QA-Durchlauf.

## Deployment
_To be added by /deploy_
