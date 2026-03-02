# PROJ-2: Produktkatalog

## Status: In Review
**Created:** 2026-02-28
**Last Updated:** 2026-03-01

## Dependencies
- Requires: PROJ-6 (Preisfindung) — für korrekte Preisanzeige je Kundengruppe
- Requires: PROJ-7 (Pfand-System) — für Pfandanzeige pro Produkt

## Beschreibung
Öffentlich zugängliche Produktübersicht und Detailseite. Gäste sehen Preise einer konfigurierbaren Standard-Preisgruppe. Eingeloggte Kunden sehen ihre persönlichen Preise. Produkte können nach Kategorie, Warengruppe, Brand und Stichwort gefiltert/gesucht werden.

## User Stories
- Als Gast möchte ich alle verfügbaren Produkte und deren Preise sehen, ohne mich anmelden zu müssen.
- Als Gast möchte ich Produkte nach Kategorie, Brand oder Stichwort filtern/suchen.
- Als eingeloggter Kunde möchte ich meine individuellen Preise (Kunden- oder Gruppenpreis) sehen.
- Als Kunde möchte ich auf der Detailseite alle relevanten Produktinfos sehen (Inhalt, Gebinde, Pfand, LMIV, Bilder).
- Als Admin möchte ich die Standard-Preisgruppe für nicht angemeldete Besucher konfigurieren können.
- Als Admin möchte ich Produkte aktivieren/deaktivieren, ohne sie zu löschen.

## Acceptance Criteria
- [ ] Produktliste zeigt: Produktbild, **Marke + Art + Gebinde** (z.B. „Elisabethenquelle Pur 12x0,7 Glas"), Preis (netto oder brutto je Kundengruppen-Setting), Pfandbetrag
- [ ] Preise für Gäste werden aus der konfigurierten Gast-Kundengruppe (Admin-Einstellung) berechnet
- [ ] Eingeloggte Kunden sehen ihren individuellen Preis (PROJ-6 Preisfindung)
- [ ] **Pfandanzeige je Kundengruppe:**
  - B2C-Kunden / Gäste: Pfand als Bruttobetrag (z.B. „zzgl. 3,42 € Pfand")
  - B2B-Kunden: Pfand als Nettobetrag (z.B. „zzgl. 3,42 € Pfand (netto, zzgl. MwSt.)")
- [ ] Filterung nach: Kategorie, Warengruppe, Gebindegröße, Brand (URL-Parameter, kombinierbar)
- [ ] Volltextsuche über Produktname, Artikelnummer, Barcode
- [ ] Produkt-Detailseite zeigt: alle Bilder (Galerie), vollständige Beschreibung, LMIV-Informationen (Nährwerte, Allergene), Pfanddetails, Verfügbarkeitsstatus
- [ ] Pagination oder Infinite Scroll auf der Produktliste
- [ ] Deaktivierte Produkte (`active = false`) sind nicht sichtbar
- [ ] Produkte mit `availability_mode = 'stock_based'` und Bestand = 0 werden als „Nicht verfügbar" angezeigt
- [ ] „In den Warenkorb"-Button direkt in der Listenansicht (mit Mengenauswahl)
- [ ] Responsive: Mobile (375px), Tablet (768px), Desktop (1440px)
- [ ] Ladezeit < 2s für Produktliste (max. 50 Produkte per Page)

## Edge Cases
- Produkt ohne Bild → Platzhalter-Bild anzeigen
- Produkt ohne aktuellen Preis für die Gast-Gruppe → Preis ausblenden, „Preis auf Anfrage" anzeigen
- Suche ergibt keine Treffer → Leerzustand mit Hinweis und „Filter zurücksetzen"-Button
- Produkt ist Bundle → Bundle-Komponenten auf Detailseite als Liste anzeigen
- Produkt hat mehrere Barcodes/EAN → alle auf Detailseite sichtbar
- Kategorie-Hierarchie: verschachtelte Kategorien müssen korrekt gefiltert werden (Unterkategorie ⊂ Oberkategorie)

## Technical Requirements
- Performance: Produktliste muss gecacht werden (Next.js `unstable_cache` oder Laravel-Cache)
- Bilder: Optimierte Bilder (Next.js `<Image>` Komponente)
- SEO: Produktseiten haben `<title>`, `<meta description>`, strukturierte Daten (Schema.org Product)
- URLs: `/produkte`, `/produkte/{slug}` (Slug aus Produktname generiert)

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur (UI-Baum)

```
/produkte/                       ← Produktliste (öffentlich)
├── Filter-Sidebar (Alpine.js)
│   ├── Kategorie-Baum (verschachtelt)
│   ├── Warengruppe-Filter
│   ├── Brand-Filter
│   └── Gebindegröße-Filter
├── Produktraster (Karten)
│   ├── Produktbild (Platzhalter wenn kein Bild)
│   ├── Name (Brand + Linie + Gebinde)
│   ├── Preis (netto oder brutto je Einstellung)
│   ├── Pfand-Hinweis (B2C: Brutto / B2B: netto zzgl. MwSt.)
│   └── [In den Warenkorb] + Mengenauswahl
└── Pagination

/produkte/{slug}                 ← Produktdetail
├── Bildergalerie (Alpine.js Slider)
├── Produktname, Artikelnummer
├── Preis + Pfand
├── Mengenauswahl + [In den Warenkorb]
├── Beschreibung / Inhalt / Gebinde-Info
├── LMIV-Block (Nährwerte, Allergene — aus aktiver lmiv_version)
└── Bundle-Komponenten (wenn is_bundle)
```

### Datenmodell (Erweiterungen)

```
products (bereits in PROJ-9)
└── + slug (VARCHAR unique)  ← URL-freundlicher Name, generiert aus Brand+Linie+Gebinde

Kein eigenes Datenmodell — Katalog liest aus:
  products → product_lines → brands → gebinde → pfand_sets
           → categories → warengruppen
           → product_images, lmiv_versions
           → PriceResolverService (PROJ-6)
           → PfandCalculator (PROJ-7)
```

### Slug-Generierung

```
"Elisabethenquelle Pur 12x0,7 Glas"
  → "elisabethenquelle-pur-12x07-glas"

Bei Namenskollision: Artikelnummer angehängt:
  → "elisabethenquelle-pur-12x07-glas-1234"
```

### Preisanzeige-Logik

```
Gast-Besucher:
  → Gruppe = app_settings['guest_customer_group_id']
  → PriceResolverService::resolve() mit Gast-Gruppe
  → price_display_mode der Gast-Gruppe (netto/brutto)

Eingeloggter Kunde:
  → PriceResolverService::resolve() mit echtem Kunden
  → price_display_mode des Kunden

Pfand-Anzeige:
  → is_business = false: "zzgl. 3,42 € Pfand"
  → is_business = true:  "zzgl. 3,42 € Pfand (netto, zzgl. MwSt.)"
```

### Performance

Produktliste wird mit `Laravel Cache::remember()` gecacht:
- Cache-Key: `products.{group_id}.{filter_hash}.page{n}`
- TTL: 5 Minuten
- Cache-Invalidierung: beim Admin-Speichern von Produkten

Filter-URL-Parameter: `/produkte?kategorie=3&brand=7&gebinde=kasten` — kein JavaScript-Routing nötig (Standard-GET-Formulare).

### SEO

Jede Produktseite erhält:
- `<title>`: Produktname + Shopname
- `<meta name="description">`: Kurztext
- `<link rel="canonical">`: kanonische URL (kein doppelter Inhalt bei Filtern)
- Schema.org `Product`-Markup (Blade-Template, server-seitig)

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| Slug auf Produkt (nicht UUID in URL) | Lesbare, SEO-freundliche URLs ohne zusätzlichen Redirect |
| Server-seitiges Caching (Laravel Cache) | Keine Redis nötig; File-Cache reicht für Webspace |
| Alpine.js für Filter-Sidebar | Kein Seitenreload bei Filteränderung nötig — Alpine sendet GET-Request via `fetch()` und ersetzt Produktraster |
| Preisberechnung serverseitig | Manipulation-sicher; kein Preis kommt aus dem Browser |

### Neue Controller

```
Shop\ProduktController   ← index (Liste), show (Detail)
```

## QA Test Results

**Tested:** 2026-03-01 (Erstpruefung), 2026-03-01 (Re-Test nach Bugfixes)
**App URL:** https://kolabri.de (Production) / http://localhost:8000 (Local)
**Tester:** QA Engineer (AI) -- Code-Level Review + Verifizierung gegen Quellcode
**Scope:** Statische Codeanalyse von ShopController, CartController, CartService, Blade Templates (index, product, layout), Product Model, PriceResolverService, Routing (web.php)

### Fixed Bugs (verifiziert in Re-Test 2026-03-01)

| Bug | Beschreibung | Fix-Beleg |
|-----|-------------|-----------|
| BUG-1 | Mobile Filter-Navigation fehlte | Mobile Filter-Button (index.blade.php Z.9-13), Backdrop (Z.16-25), Slide-in Sidebar mit Alpine.js `filterOpen` State implementiert |
| BUG-2 | N+1 Query fuer Stock-Check | `stocks` jetzt in eager-load Liste (ShopController Z.78) |
| BUG-3 | Stock-basierte Produkte konnten in Warenkorb | `$isDisabled` enthaelt jetzt `\|\| !$stockAvailable` (index.blade.php Z.254-256) |
| BUG-10 | out_of_stock ohne Server-Side Guard | CartController prueft jetzt `AVAILABILITY_OUT_OF_STOCK` (Z.116-121) |
| BUG-11 | CartController prueft keine Verfuegbarkeit | CartController prueft jetzt `active`, `show_in_shop`, `discontinued`, `out_of_stock`, `stock_based` mit Bestand 0 (Z.100-129) |
| BUG-14 | Stored XSS via JSON-LD | `JSON_HEX_TAG` Flag hinzugefuegt (product.blade.php Z.11) |

### Acceptance Criteria Status

#### AC-1: Produktliste zeigt Produktbild, Marke + Art + Gebinde, Preis, Pfandbetrag
- [x] Produktbild wird angezeigt (mit lazy loading) -- `index.blade.php` Z.194-198
- [ ] **FAIL (BUG-5):** Produktname zeigt nur `produktname` Feld, nicht das zusammengesetzte Format "Marke + Art + Gebinde" wie spezifiziert. Marke wird separat als kleiner Text angezeigt (Z.215-217), Gebinde-Info fehlt in der Listenansicht komplett.
- [x] Preis wird korrekt nach `priceDisplayMode` (netto/brutto) angezeigt -- Z.228-232
- [x] Pfandbetrag wird angezeigt -- Z.233-239

#### AC-2: Preise fuer Gaeste aus konfigurierter Gast-Kundengruppe
- [x] PASS: Gast-Preisgruppe wird aus `AppSetting::getInt('default_customer_group_id')` geladen -- ShopController Z.286-293
- [x] PASS: `PriceResolverService::resolveForGuest()` wird korrekt aufgerufen -- ShopController Z.142

#### AC-3: Eingeloggte Kunden sehen individuellen Preis
- [x] PASS: `PriceResolverService::resolveForCustomer()` wird bei eingeloggten Nutzern aufgerufen -- ShopController Z.140-141
- [x] PASS: 3-stufige Preisfindung (Kunde -> Gruppe -> Basis) korrekt in PriceResolverService

#### AC-4: Pfandanzeige je Kundengruppe (B2C vs B2B)
- [x] PASS: B2C/Gaeste: "+ X,XX EUR Pfand" -- `index.blade.php` Z.237
- [x] PASS: B2B: "+ X,XX EUR Pfand (netto, zzgl. MwSt.)" -- Z.235
- [x] PASS: Gleiches Pattern auf Detailseite -- `product.blade.php` Z.108-113

#### AC-5: Filterung nach Kategorie, Warengruppe, Gebindegroesse, Brand (URL-Parameter, kombinierbar)
- [x] PASS: Alle 4 Filter implementiert als URL-Parameter (`kategorie`, `warengruppe`, `gebinde`, `brand`)
- [x] PASS: Filter sind kombinierbar (ShopController Z.84-103)
- [x] PASS: Filter-Sidebar zeigt aktive Filter mit Badges an (index.blade.php Z.159-172)
- [x] PASS: "Filter zuruecksetzen" Button vorhanden (Z.168)

#### AC-6: Volltextsuche ueber Produktname, Artikelnummer, Barcode
- [x] PASS: Suche ueber `produktname`, `artikelnummer`, und `barcodes` Relation implementiert -- ShopController Z.106-113
- [x] PASS: Suchfeld in Navbar Desktop (layout.blade.php Z.25-33) und Mobile-Search (index.blade.php Z.148-156)

#### AC-7: Produkt-Detailseite (Bilder, Beschreibung, LMIV, Pfand, Verfuegbarkeit)
- [x] PASS: Bildergalerie mit Thumbnail-Navigation -- `product.blade.php` Z.38-59
- [x] PASS: LMIV-Block mit Naehrwerten, Allergenen, Hersteller -- Z.199-271
- [x] PASS: Pfanddetails angezeigt -- Z.106-116
- [ ] **FAIL (BUG-16):** Detailseite zeigt stock_based Produkte mit Bestand 0 als "Verfuegbar" (Z.71-73). Die `show()` Methode laedt die `stocks` Relation nicht und berechnet keinen `$stockAvailable` Wert. Warenkorb-Button ist ebenfalls nicht disabled.
- [ ] **FAIL (BUG-13):** Keine "vollstaendige Beschreibung" (Langtext). Nur `sales_unit_note` wird angezeigt (Z.130-132). Das Product Model hat kein `description` Feld.

#### AC-8: Pagination oder Infinite Scroll
- [x] PASS: Pagination mit Laravel `paginate(24)` implementiert -- ShopController Z.124
- [x] PASS: Pagination-Links korrekt mit `withQueryString()` -- Filter bleiben bei Seitenwechsel erhalten

#### AC-9: Deaktivierte Produkte nicht sichtbar
- [x] PASS: Liste: `where('active', true)` UND `where('show_in_shop', true)` Filter -- ShopController Z.80-81
- [x] PASS: Detail: `abort(404)` wenn `!$product->active || !$product->show_in_shop` -- Z.191

#### AC-10: Stock-based Produkte mit Bestand 0 als "Nicht verfuegbar"
- [x] PASS (Liste): Stock-Check implementiert -- ShopController Z.152-157
- [x] PASS (Liste): "Nicht verfuegbar" Badge auf Produktkarte -- `index.blade.php` Z.206-210
- [x] PASS (Liste): stocks in eager-load enthalten -- ShopController Z.78
- [ ] **FAIL (BUG-16):** Detailseite zeigt stock_based Produkte pauschal als "Verfuegbar" (product.blade.php Z.71-73), kein Stock-Check auf Detailseite

#### AC-11: "In den Warenkorb"-Button mit Mengenauswahl in Listenansicht
- [x] PASS: Formular mit quantity-Input und Submit-Button -- `index.blade.php` Z.258-278
- [x] PASS: Disabled fuer discontinued, out_of_stock UND !stockAvailable (Z.253-257)

#### AC-12: Responsive (375px, 768px, 1440px)
- [x] PASS: Grid responsive: `grid-cols-2 md:grid-cols-3 xl:grid-cols-4` -- Z.182
- [x] PASS: Mobile Filter-Button (FAB) sichtbar auf < 1024px mit Slide-in Drawer -- Z.9-13, Z.28-30
- [x] PASS: Backdrop und Close-Button fuer Mobile-Filter vorhanden -- Z.16-25, Z.33-37
- [x] PASS: Detail-Seite: `grid md:grid-cols-2` fuer responsive Layout

#### AC-13: Ladezeit < 2s
- [x] PASS: Caching mit `Cache::remember()` und 300s TTL implementiert -- ShopController Z.68
- [ ] **FAIL (BUG-4):** Preis-/Pfand-Berechnungsloop (Z.138-165) laeuft AUSSERHALB des Cache. 24 PriceResolver-Aufrufe + 24 PfandCalculator-Aufrufe pro Seitenaufruf. Zusaetzlich: Filter-Sidebar-Daten (Z.128-131) werden ebenfalls nicht gecacht (4 weitere Queries pro Request).

### Edge Cases Status

#### EC-1: Produkt ohne Bild -> Platzhalter
- [x] PASS: Platzhalter-SVG-Icon in Liste (index.blade.php Z.200-202) und Detail (product.blade.php Z.56-58)

#### EC-2: Produkt ohne Preis -> "Preis auf Anfrage"
- [x] PASS: "Preis auf Anfrage" bei `$price === null` -- index.blade.php Z.241, product.blade.php Z.119-121

#### EC-3: Suche ohne Treffer -> Leerzustand
- [x] PASS: Leerzustand mit Hinweis und "Alle Produkte anzeigen" Link -- index.blade.php Z.174-179

#### EC-4: Bundle -> Komponenten auf Detailseite
- [x] PASS: Bundle-Komponenten werden als Liste angezeigt -- product.blade.php Z.169-196
- [x] PASS: Rekursive Aufloesung mit Cycle-Protection implementiert -- Product Model

#### EC-5: Mehrere Barcodes/EAN auf Detailseite
- [x] PASS: Alle Barcodes mit Typ-Anzeige sichtbar -- product.blade.php Z.135-149

#### EC-6: Kategorie-Hierarchie korrekt filtern
- [x] Unterkategorien eine Ebene tief werden einbezogen -- ShopController Z.86-89
- [ ] **FAIL (BUG-6):** Nur ONE-LEVEL-DEEP Hierarchie. Bei 3+ Ebenen tief verschachtelten Kategorien werden Produkte der tieferen Ebenen nicht gefunden.

### Security Audit Results

#### Auth & Authorization
- [x] PASS: Shop-Seiten (`/produkte`, `/produkte/{slug}`) sind korrekt oeffentlich zugaenglich
- [x] PASS: Preisberechnung erfolgt serverseitig -- kein Preis kommt aus dem Browser
- [x] PASS: Admin-Routen sind durch `admin` + `company` Middleware geschuetzt

#### company_id Isolation
- [ ] INFO (MVP-akzeptabel): Product Model hat KEIN `company_id` Feld. Fuer MVP single-tenant laut PRD akzeptabel, aber Risiko bei Multi-Tenant-Aktivierung.

#### SQL Injection
- [x] PASS: LIKE-Queries sind sicher via PDO-Parameter (ShopController Z.108-111)
- [x] PASS: `orderByRaw()` auf Z.120 verwendet parametrisierte Werte
- [ ] **INFO (BUG-7):** LIKE-Wildcard-Zeichen (`%`, `_`) im Suchbegriff werden NICHT escaped. Kein Sicherheitsrisiko, aber unerwartetes Verhalten.

#### XSS (Cross-Site Scripting)
- [x] PASS: Blade Templates verwenden `{{ }}` (escaped output) durchgehend
- [x] PASS (FIXED): Schema.org JSON-LD verwendet jetzt `JSON_HEX_TAG` Flag (product.blade.php Z.11) -- `<` und `>` werden zu `\u003C` und `\u003E` escaped

#### CSRF Protection
- [x] PASS: Alle Formulare verwenden `@csrf` Token
- [x] PASS: Cart-POST-Route durch Laravel CSRF Middleware geschuetzt

#### Mass Assignment
- [x] PASS: Product Model hat korrekt definiertes `$fillable` Array

#### Cart Server-Side Validation
- [x] PASS (FIXED): CartController::add() prueft jetzt `active`, `show_in_shop`, `discontinued`, `out_of_stock`, `stock_based` mit Bestand (Z.100-129)
- [ ] **INFO (BUG-12):** CartService::add() speichert ohne eigene Validation, aber dies ist akzeptabel da CartController als einziger Caller die Validation uebernimmt

#### Supply Chain / CDN
- [ ] **BUG (BUG-17):** Alpine.js wird ueber CDN mit looser Version-Range geladen (`alpinejs@3.x.x` in layout.blade.php Z.10). Kein SRI-Hash (Subresource Integrity). Ein kompromittierter CDN koennte beliebigen JavaScript-Code einschleusen.
- **Bewertung:** Low (Standard-Praxis fuer viele Projekte, aber Best Practice waere gepinnnte Version + SRI-Hash)

#### Rate Limiting
- [ ] INFO: Keine Rate-Limiting auf Shop-GET-Routen. Cache ist die Verteidigung gegen Scraping.
- [x] PASS: Cart-POST hat CSRF-Token der automatisierte Angriffe von extern verhindert

#### Information Disclosure
- [x] PASS: Keine API-Keys oder Credentials in Responses
- [x] PASS: Fehler werden mit try/catch abgefangen, Preis auf `null` gesetzt

#### Secrets Exposure
- [x] PASS: Kein API Key oder Secret in Templates oder Controller

### Cross-Browser / Responsive

#### Chrome / Firefox / Safari (Code-Level)
- [x] PASS: Keine browser-spezifischen CSS-Features verwendet
- [x] PASS: Standard Tailwind CSS Klassen -- breite Browser-Unterstuetzung
- [x] PASS: SVG Icons inline -- keine externen Icon-Fonts
- [ ] INFO: `loading="lazy"` auf `<img>` hat keine Unterstuetzung in aelteren Safari-Versionen (vor iOS 15.4), aber graceful degradation

#### Responsive Breakpoints
- [x] PASS: 375px (Mobile): 2-Spalten Grid, FAB Filter-Button, Slide-in Sidebar, Mobile-Search
- [x] PASS: 768px (Tablet): 3-Spalten Grid, FAB Filter-Button, Slide-in Sidebar
- [x] PASS: 1440px (Desktop): 4-Spalten Grid, sichtbare Sidebar

### Bugs Found (offene Bugs nach Re-Test)

#### BUG-4: Preis-/Pfand-Berechnung + Filter-Sidebar nicht gecacht (Performance) [MEDIUM]
- **Severity:** Medium
- **Steps to Reproduce:**
  1. Oeffne `/produkte` mit vielen Produkten
  2. Expected: Gesamte Seite gecacht oder Preise gecacht
  3. Actual: Nur die Produkt-Query ist gecacht (`Cache::remember`). Die Preis- und Pfand-Berechnung (Z.138-165) UND die Filter-Sidebar-Queries (Z.128-131, 4 Queries) laufen bei JEDEM Request.
- **File:** `app/Http/Controllers/Shop/ShopController.php` Z.128-165
- **Priority:** Fix in next sprint

#### BUG-5: Gebinde-Info fehlt im Produktkarten-Titel [LOW]
- **Severity:** Low
- **Steps to Reproduce:**
  1. Oeffne `/produkte`
  2. Expected: Produktname als "Marke + Art + Gebinde" (z.B. "Elisabethenquelle Pur 12x0,7 Glas")
  3. Actual: Marke separat als kleiner Text, Produktname ohne Gebinde-Info
- **File:** `resources/views/shop/index.blade.php` Z.215-223
- **Priority:** Nice to have

#### BUG-6: Kategorie-Hierarchie nur eine Ebene tief [MEDIUM]
- **Severity:** Medium
- **Steps to Reproduce:**
  1. Erstelle 3-Ebenen-Kategorien: "Bier" > "Helles" > "Maerzen"
  2. Filtere nach "Bier" auf `/produkte?kategorie=1`
  3. Expected: Produkte aus allen Unterebenen
  4. Actual: Nur Produkte aus "Bier" und "Helles" werden gefunden
- **File:** `app/Http/Controllers/Shop/ShopController.php` Z.86-89
- **Fix:** Rekursive Category-ID Sammlung implementieren
- **Priority:** Fix in next sprint

#### BUG-7: LIKE-Wildcard-Zeichen nicht escaped in Suche [LOW]
- **Severity:** Low
- **Steps to Reproduce:**
  1. Oeffne `/produkte?suche=%25` (URL-encoded `%`)
  2. Expected: Suche nach dem Zeichen "%"
  3. Actual: `%` wird als SQL-LIKE-Wildcard interpretiert und matcht alle Produkte
- **File:** `app/Http/Controllers/Shop/ShopController.php` Z.108
- **Priority:** Nice to have

#### BUG-8: Canonical URL fehlt auf Produktliste [LOW]
- **Severity:** Low
- **Steps to Reproduce:**
  1. Oeffne `/produkte?kategorie=3&brand=7`
  2. Expected: `<link rel="canonical">` zeigt auf `/produkte`
  3. Actual: Kein canonical Tag auf der Produktliste
- **File:** `resources/views/shop/index.blade.php` (fehlend)
- **Priority:** Fix in next sprint (SEO)

#### BUG-9: SEO meta description fehlt auf Produktliste [LOW]
- **Severity:** Low
- **Steps to Reproduce:**
  1. Oeffne Quelltext von `/produkte`
  2. Expected: `<meta name="description">` Tag vorhanden
  3. Actual: Kein meta description auf der Produktliste
- **File:** `resources/views/shop/index.blade.php` (fehlend)
- **Priority:** Fix in next sprint (SEO)

#### BUG-12: CartService speichert ohne Produkt-Validierung [LOW]
- **Severity:** Low (herabgestuft, da CartController jetzt validiert)
- **Steps to Reproduce:**
  1. CartService::add() akzeptiert beliebige productId + qty
  2. CartService::items() filtert spaeter `where('active', true)` beim Lesen, aber zwischen Schreiben und Lesen inkonsistenter State
- **File:** `app/Services/Shop/CartService.php` Z.33-38
- **Priority:** Nice to have (Defense-in-Depth, CartController schuetzt bereits)

#### BUG-13: Fehlende vollstaendige Produktbeschreibung auf Detailseite [LOW]
- **Severity:** Low
- **Steps to Reproduce:**
  1. Oeffne eine Produktdetailseite
  2. Expected: Vollstaendige Produktbeschreibung (Langtext)
  3. Actual: Nur `sales_unit_note` wird angezeigt
- **File:** Product Model + `resources/views/shop/product.blade.php`
- **Priority:** Nice to have

#### BUG-15: Schema.org markiert stock_based Produkte pauschal als InStock [LOW]
- **Severity:** Low
- **Steps to Reproduce:**
  1. Erstelle Produkt mit `availability_mode = 'stock_based'` und Bestand = 0
  2. Schema.org zeigt `InStock` statt `OutOfStock`
- **File:** `app/Http/Controllers/Shop/ShopController.php` Z.326-327
- **Priority:** Fix in next sprint (SEO-Impact)

#### BUG-16: Detailseite zeigt stock_based Produkte mit Bestand 0 als "Verfuegbar" [HIGH] -- NEU
- **Severity:** High
- **Steps to Reproduce:**
  1. Erstelle ein Produkt mit `availability_mode = 'stock_based'` und Bestand = 0
  2. Oeffne die Produktdetailseite `/produkte/{slug}`
  3. Expected: "Nicht verfuegbar" Badge, Warenkorb-Button disabled
  4. Actual: Zeigt "Verfuegbar" (product.blade.php Z.71-73 behandelt `stock_based` wie `available`). Die `show()` Methode (ShopController Z.189-243) laedt `stocks` NICHT und berechnet keinen `$stockAvailable`. Der Warenkorb-Button ist aktiv (Z.159 prueft nur `out_of_stock`).
  5. HINWEIS: Der CartController (Z.124) BLOCKIERT den POST serverseitig korrekt -- aber die UI ist irrefuehrend.
- **File:** `app/Http/Controllers/Shop/ShopController.php` show() + `resources/views/shop/product.blade.php` Z.71, Z.159
- **Fix:** In show() die `stocks` Relation laden, `$stockAvailable` berechnen und an View uebergeben. In product.blade.php Stock-Check fuer Badge und Button-Disable einbauen.
- **Priority:** Fix before deployment

#### BUG-17: Alpine.js CDN ohne SRI-Hash und loose Version-Range [LOW]
- **Severity:** Low
- **Steps to Reproduce:**
  1. Oeffne `resources/views/shop/layout.blade.php` Z.10
  2. Alpine.js wird von `cdn.jsdelivr.net` mit `@3.x.x` geladen -- keine Versionspinnung, kein SRI-Hash
  3. Ein kompromittierter CDN koennte beliebigen JS-Code einschleusen
- **File:** `resources/views/shop/layout.blade.php` Z.10
- **Fix:** Alpine.js auf exakte Version pinnen (z.B. `@3.14.3`) und SRI `integrity` Attribut hinzufuegen, oder Alpine.js als npm-Dependency bundlen
- **Priority:** Fix in next sprint

### Regression Test

- [x] PROJ-1 (Auth): Login/Logout-Flow nicht betroffen -- ShopController nutzt `Auth::check()` read-only
- [x] PROJ-6 (Preisfindung): PriceResolverService wird korrekt aufgerufen mit korrekter 3-stufiger Logik
- [x] PROJ-7 (Pfand): PfandCalculator wird korrekt aufgerufen fuer Gebinde-basierte Pfandberechnung
- [x] PROJ-3 (Warenkorb): Cart-Formular verweist auf `route('cart.add')` -- Warenkorb-Integration intakt. CartController Server-Side Validation jetzt korrekt.
- [x] PROJ-9 (Admin Stammdaten): Admin-Produkt-Routen verwenden `{product:id}` Binding, keine Kollision mit Shop-`{product}` (slug) Binding
- [x] Layout (Navbar): Cart-Counter wird korrekt via CartService angezeigt (layout.blade.php Z.41-44)
- [x] `/p/{id}` Legacy-Redirect zu Slug-URL funktioniert (ShopController Z.250-254, web.php Z.57)

### Summary

| Metrik | Ergebnis |
|---|---|
| **Acceptance Criteria** | 10/13 passed (3 mit Bugs: AC-1 teilweise, AC-7 teilweise, AC-13) |
| **Edge Cases** | 5/6 passed (1 Bug bei Kategorie-Hierarchie) |
| **Bugs zuvor gemeldet** | 15 |
| **Bugs davon behoben** | 6 (BUG-1, BUG-2, BUG-3, BUG-10, BUG-11, BUG-14) |
| **Bugs offen (verbleibend)** | 9 + 2 neue = 11 |
| **Neue Bugs gefunden** | 2 (BUG-16 High, BUG-17 Low) |
| **Bugs Total offen** | 11 (0 critical, 1 high, 2 medium, 8 low) |
| **Security Issues offen** | 1 Low (CDN ohne SRI), 1 Low (CartService ohne eigene Validation) |
| **Production Ready** | **NEIN** -- 1 High Bug (BUG-16) muss noch behoben werden |

**Bugs die vor Deployment behoben werden MUESSEN (Blocker):**

| Bug | Severity | Zusammenfassung |
|-----|----------|-----------------|
| BUG-16 | High | Detailseite: stock_based Produkte mit Bestand 0 zeigen "Verfuegbar" + Button aktiv |

**Bugs die im naechsten Sprint behoben werden sollten:**

| Bug | Severity | Zusammenfassung |
|-----|----------|-----------------|
| BUG-4 | Medium | Preis-/Pfand-Berechnung + Filter-Sidebar nicht gecacht |
| BUG-6 | Medium | Kategorie-Hierarchie nur eine Ebene tief |

**Nice-to-have / Low Priority:**

| Bug | Severity | Zusammenfassung |
|-----|----------|-----------------|
| BUG-5 | Low | Gebinde-Info fehlt im Produktkarten-Titel |
| BUG-7 | Low | LIKE-Wildcard-Zeichen nicht escaped |
| BUG-8 | Low | Canonical URL fehlt auf Produktliste |
| BUG-9 | Low | SEO meta description fehlt auf Produktliste |
| BUG-12 | Low | CartService speichert ohne eigene Validierung |
| BUG-13 | Low | Fehlende vollstaendige Produktbeschreibung |
| BUG-15 | Low | Schema.org stock_based immer InStock |
| BUG-17 | Low | Alpine.js CDN ohne SRI-Hash |

## Deployment
_To be added by /deploy_
