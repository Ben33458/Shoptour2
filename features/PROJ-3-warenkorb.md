# PROJ-3: Warenkorb

## Status: In Review
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- Requires: PROJ-2 (Produktkatalog) — Produkte müssen vorhanden sein
- Requires: PROJ-6 (Preisfindung) — Preise im Warenkorb werden live berechnet
- Requires: PROJ-7 (Pfand-System) — Pfand wird pro Position angezeigt und summiert

## Beschreibung
Session-basierter Warenkorb für Gäste und eingeloggte Kunden. Zeigt Positionen, Mengen, Preise, Pfand und Gesamtsummen. Bei Login wird der Gast-Warenkorb mit dem Auth-Account zusammengeführt.

## User Stories
- Als Gast möchte ich Produkte in den Warenkorb legen, ohne mich registrieren zu müssen.
- Als Kunde möchte ich die Menge von Positionen ändern oder Positionen entfernen.
- Als Kunde möchte ich die Gesamtsumme inkl. Pfand auf einen Blick sehen.
- Als eingeloggter Kunde möchte ich meinen persönlichen Preis im Warenkorb sehen (nicht den Gast-Preis).
- Als Gast möchte ich nach dem Einloggen meinen Warenkorb nicht verlieren.

## Acceptance Criteria
- [ ] Produkte können aus der Produktliste und Detailseite in den Warenkorb gelegt werden (mit Mengenangabe)
- [ ] Warenkorb zeigt pro Position: Produktbild, Name, Artikelnummer, Stückpreis (netto oder brutto je Einstellung), Pfand/Stück, Menge, Zeilengesamtpreis
- [ ] Gesamtübersicht: Warenwert netto, Pfand gesamt (brutto), MwSt. aufgeschlüsselt, Gesamtbetrag brutto
- [ ] Menge kann direkt im Warenkorb geändert werden (Input-Feld oder +/- Buttons)
- [ ] Position kann aus Warenkorb entfernt werden
- [ ] „Warenkorb leeren"-Funktion
- [ ] Warenkorb-Icon im Header zeigt Anzahl der Positionen (Badge)
- [ ] Gast-Warenkorb wird in PHP-Session gespeichert
- [ ] Bei Login: Gast-Artikel werden dem Auth-Warenkorb hinzugefügt (nicht ersetzt); doppelte Produkte werden summiert
- [ ] Eingeloggte Kunden sehen ihren individuellen Preis (aus PROJ-6 Preisfindung)
- [ ] Warenkorb bleibt nach Seiten-Reload erhalten (Session-persistent)
- [ ] Mini-Warenkorb / Dropdown im Header (letzte Artikel + Gesamtsumme + Zur-Kasse-Button)
- [ ] „Weiter einkaufen"-Link zurück zum Katalog

## Edge Cases
- Produkt wird deaktiviert, während es im Warenkorb liegt → Beim nächsten Aufruf Warnung anzeigen, Position markieren, Checkout blockieren
- Menge auf 0 setzen → Position wird entfernt
- Menge übersteigt Lagerbestand (bei stock_based Produkten) → Warnung, maximale Menge setzen
- Warenkorb ist leer → Leerzustand mit Link zum Katalog
- Kundengruppe des eingeloggten Nutzers ändert sich → Preise im Warenkorb werden beim nächsten Aufruf neu berechnet
- Bundle-Produkt im Warenkorb → Bundle als eine Position mit enthaltenen Komponenten als Info

## Technical Requirements
- Warenkorb-Daten: Laravel Session (Gast) + DB-Tabelle `carts` (Auth, optional für Persistenz)
- Preisberechnung immer serverseitig (nie clientseitig, um Manipulation zu verhindern)
- CSRF-Schutz auf allen Warenkorb-Mutationen

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur (UI-Baum)

```
/warenkorb                        ← Vollseiten-Warenkorb
├── Positions-Liste
│   ├── Produktbild, Name, Artikelnummer
│   ├── Stückpreis + Pfand/Stück
│   ├── Mengeneingabe (+/- oder Direkteingabe, Alpine.js)
│   ├── Zeilenpreis (netto oder brutto)
│   └── [Entfernen]-Button
├── Aktionsleiste
│   ├── [Warenkorb leeren]
│   └── [Weiter einkaufen] → /produkte
└── Preis-Zusammenfassung
    ├── Warenwert netto
    ├── Pfand gesamt (brutto)
    ├── MwSt. aufgeschlüsselt (19% / 7% / ...)
    ├── Gesamtbetrag brutto
    └── [Zur Kasse] → /checkout

[Header: Mini-Warenkorb Dropdown] (Alpine.js)
├── Letzte 3 Artikel (Bild, Name, Preis)
├── Gesamtbetrag
└── [Zur Kasse]-Button
```

### Datenmodell

```
carts  [Auth-Nutzer: DB-persistiert]
├── id, user_id → users (nullable)
├── session_id (VARCHAR, nullable)  ← Gast-Identifikation
└── company_id

cart_items
├── id, cart_id → carts
├── product_id → products
├── quantity
└── company_id

Preise werden NICHT in cart_items gespeichert — immer live aus PriceResolverService berechnet.
```

### Gast vs. Auth Warenkorb

```
Gast:
  → cart_id in PHP-Session gespeichert (session('cart_id'))
  → cart.user_id = NULL, cart.session_id = session()->getId()

Nach Login (CartMergeService):
  → Gast-cart_items werden in Auth-Warenkorb übertragen
  → Gleiche Produkte: Mengen werden addiert (nicht ersetzt)
  → Gast-Cart wird danach gelöscht
```

### Preisberechnung (serverseitig)

Beim Laden des Warenkorbs werden Preise live neu berechnet:
```
für jede cart_item:
  preis = PriceResolverService::resolve($product, $customer)
  pfand = PfandCalculator::totalForGebinde($product->gebinde)
  zeilensumme = preis × quantity  (Integer-Arithmetik)
```

Bei veralteten Preisen (nach Preisänderung durch Admin) zeigt der Warenkorb automatisch die aktuellen Preise.

### Alpine.js Mini-Warenkorb

Der Header-Dropdown wird als Alpine.js-Komponente geführt:
- Beim Hinzufügen eines Artikels via `fetch(POST /warenkorb/add)` → Antwort enthält aktualisierten Badge-Count + Mini-Cart HTML
- Kein Seitenreload nötig für Badge-Update

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| Keine Preise in `cart_items` | Verhindert veraltete Preise im Warenkorb; aktuelle Preise kommen immer serverseitig |
| DB-Persistenz für Auth-Nutzer | Warenkorb bleibt auch nach Session-Ablauf erhalten |
| Session-Warenkorb für Gäste | Keine Registrierung nötig; Standard-Laravel-Session |
| `CartMergeService` | Saubere Trennung der Merge-Logik; testbar unabhängig von Controllern |

### Neue Controller / Services

```
Shop\WarenkorbController    ← index, add, update, remove, clear
CartMergeService            ← mergeGuestCart($guestCartId, $authUser)
```

## QA Test Results

**Tested:** 2026-03-02
**Tester:** QA Engineer (AI)
**Scope:** Code review + static analysis of backend (Laravel) implementation. No running server available for live testing.

### Acceptance Criteria Status

#### AC-1: Produkte koennen aus Produktliste und Detailseite in den Warenkorb gelegt werden (mit Mengenangabe)
- [x] Produktliste (`shop/index.blade.php`) hat "In den Warenkorb"-Formular mit qty-Input und POST zu `cart.add`
- [x] Produktdetailseite (`shop/product.blade.php`) hat "In den Warenkorb"-Formular mit qty-Input
- [x] Controller validiert `product_id` (exists:products) und `qty` (integer, min:1, max:999)
- [x] Deaktivierte und ausverkaufte Produkte werden beim Hinzufuegen blockiert

#### AC-2: Warenkorb zeigt pro Position: Produktbild, Name, Artikelnummer, Stueckpreis, Pfand/Stueck, Menge, Zeilengesamtpreis
- [x] `cart.blade.php` zeigt Produktbild (mainImage), Name (produktname), Artikelnummer
- [x] Stueckpreis brutto und Pfand pro Stueck werden angezeigt
- [x] Mengenfeld und Zeilengesamtpreis vorhanden
- [x] Nicht-verfuegbare Produkte werden optisch markiert (rot, durchgestrichen)

#### AC-3: Gesamtuebersicht: Warenwert netto, Pfand gesamt (brutto), MwSt. aufgeschluesselt, Gesamtbetrag brutto
- [x] Zwischensumme brutto angezeigt
- [x] Netto-Betrag angezeigt (als Unterzeile)
- [x] MwSt. nach Steuersatz aufgeschluesselt
- [x] Pfand gesamt angezeigt (nur wenn > 0)
- [x] Gesamtbetrag angezeigt

#### AC-4: Menge kann direkt im Warenkorb geaendert werden (Input-Feld oder +/- Buttons)
- [x] Input-Feld vom Typ `number` mit min=0, max=999
- [x] "Aktualisieren"-Button sendet PATCH-Request
- [ ] BUG: Keine +/- Buttons vorhanden, nur manuelles Input-Feld mit separatem "Aktualisieren"-Klick (kein inline-Update)

#### AC-5: Position kann aus Warenkorb entfernt werden
- [x] "Entfernen"-Button pro Position vorhanden (DELETE-Request)
- [x] Backend-Logik entfernt korrekt aus DB (Auth) bzw. Session (Gast)

#### AC-6: "Warenkorb leeren"-Funktion
- [x] "Warenkorb leeren"-Button vorhanden mit JavaScript-Confirm
- [x] DELETE /warenkorb/alle Route registriert
- [x] CartService::clear() loescht alle Items

#### AC-7: Warenkorb-Icon im Header zeigt Anzahl der Positionen (Badge)
- [x] Layout hat Cart-Icon mit Badge in der Navbar
- [ ] BUG: `totalItems()` wird ohne User-Parameter aufgerufen -- zeigt fuer eingeloggte Nutzer immer 0 (siehe BUG-1)

#### AC-8: Gast-Warenkorb wird in PHP-Session gespeichert
- [x] Session-basierter Cart mit Key `shop_cart` (array product_id => qty)
- [x] Session::put() / Session::get() korrekt verwendet

#### AC-9: Bei Login: Gast-Artikel werden dem Auth-Warenkorb hinzugefuegt (nicht ersetzt); doppelte Produkte werden summiert
- [x] CartMergeService existiert und wird in LoginController aufgerufen
- [x] Gast-Session-ID wird vor `session()->regenerate()` gespeichert
- [x] Merge-Logik: existierende Produkte werden per `increment()` summiert, neue per `create()`
- [x] Auch in SocialController und RegisterController integriert

#### AC-10: Eingeloggte Kunden sehen ihren individuellen Preis (aus PROJ-6 Preisfindung)
- [x] `CartService::calculate()` ruft `PriceResolverService::resolveForCustomer()` fuer Auth-Nutzer auf
- [x] Fuer Gaeste wird `resolveForGuest()` verwendet

#### AC-11: Warenkorb bleibt nach Seiten-Reload erhalten (Session-persistent)
- [x] Gast: Session-basiert (PHP Session, Standard-Laravel-Verhalten)
- [x] Auth: DB-basiert (carts + cart_items Tabellen)

#### AC-12: Mini-Warenkorb / Dropdown im Header (letzte Artikel + Gesamtsumme + Zur-Kasse-Button)
- [ ] BUG: Mini-Warenkorb-Dropdown ist NICHT implementiert im Layout (siehe BUG-2)
- [x] Backend-Endpunkt `GET /warenkorb/mini` existiert und liefert JSON
- [ ] Kein Alpine.js-Dropdown im Header-Template vorhanden

#### AC-13: "Weiter einkaufen"-Link zurueck zum Katalog
- [x] Link "Weiter einkaufen" verweist auf `route('shop.index')`
- [x] Sowohl im leeren Warenkorb als auch in der Aktionsleiste vorhanden

### Edge Cases Status

#### EC-1: Produkt wird deaktiviert waehrend es im Warenkorb liegt
- [x] `CartService::calculate()` prueft `active`, `show_in_shop`, `availability_mode`
- [x] `unavailable` Flag wird pro Item gesetzt
- [x] Rotes Banner "Achtung: Einige Produkte sind nicht mehr verfuegbar" wird angezeigt
- [x] Checkout-Button wird deaktiviert wenn `hasUnavailable` true

#### EC-2: Menge auf 0 setzen entfernt Position
- [x] `CartService::update()` mit qty <= 0 ruft `remove()` auf
- [x] Controller-Validation erlaubt min:0 fuer PATCH

#### EC-3: Menge uebersteigt Lagerbestand (stock_based Produkte)
- [x] `CartService::update()` prueft `isStockBased()` und `currentStock()`
- [x] Menge wird auf verfuegbaren Bestand gekappt
- [x] Warning-Message wird zurueckgegeben und in der View angezeigt

#### EC-4: Warenkorb ist leer
- [x] Leerzustand mit Icon, Text "Dein Warenkorb ist leer" und "Weiter einkaufen"-Button
- [x] Separate Darstellung via `@if(empty($items))`

#### EC-5: Kundengruppe aendert sich
- [x] Preise werden NICHT in cart_items gespeichert (nur als Snapshot)
- [x] Bei jedem Warenkorb-Aufruf werden Preise live ueber PriceResolverService berechnet
- [ ] BUG: Widerspruch zur Tech-Design-Spec -- Spec sagt "keine Preise in cart_items", aber Code speichert `unit_price_gross_milli` und `pfand_milli` als Snapshots (siehe BUG-3)

#### EC-6: Bundle-Produkt im Warenkorb
- [ ] NICHT GETESTET: Keine Bundle-Logik in CartService oder cart.blade.php erkennbar
- [ ] Bundles werden wie normale Produkte behandelt -- keine Komponentenanzeige

### Security Audit Results

#### Authentication
- [x] Warenkorb-Lese-Endpunkte (GET /warenkorb, GET /warenkorb/mini) sind fuer Gaeste zugaenglich (gewuenscht)
- [x] Warenkorb-Mutations-Endpunkte (POST, PATCH, DELETE) sind fuer Gaeste zugaenglich (gewuenscht -- Gast-Warenkorb)
- [x] Checkout-Button erfordert Login (view-seitig geprueft)

#### CSRF-Schutz
- [x] Alle Formulare in cart.blade.php enthalten `@csrf`
- [x] PATCH und DELETE verwenden `@method('PATCH')` / `@method('DELETE')`
- [x] Web-Routen sind automatisch CSRF-geschuetzt durch Laravels VerifyCsrfToken-Middleware

#### Authorization / company_id Isolation
- [x] `getOrCreateDbCart()` filtert nach `user_id` -- Nutzer A kann nicht auf Nutzer Bs Cart zugreifen
- [ ] BUG: Kein `company_id`-Scope auf Cart-Queries (siehe BUG-4)
- [x] Cart-Items erben `company_id` vom User bei Erstellung

#### Mass Assignment
- [x] `Cart` Model hat explizites `$fillable` Array
- [x] `CartItem` Model hat explizites `$fillable` Array
- [ ] BUG: `CartItem.$fillable` enthaelt `company_id` -- ein Angreifer koennte theoretisch ueber Mass Assignment company_id manipulieren (siehe BUG-5)

#### Input Validation
- [x] `product_id` wird mit `exists:products,id` validiert
- [x] `qty` wird als Integer mit min/max validiert
- [x] Blade-Templates verwenden `{{ }}` (auto-escaped) -- kein XSS-Risiko
- [ ] BUG: `productId` Route-Parameter in update()/remove() wird nicht validiert -- koennte beliebige Integer annehmen (kein exists-Check) (siehe BUG-6)

#### Rate Limiting
- [ ] BUG: Keine Rate-Limiting-Middleware auf Warenkorb-Routen -- ein Angreifer koennte massenhaft POST /warenkorb Requests senden (siehe BUG-7)

#### Secrets Exposure
- [x] Keine API-Keys oder DB-Credentials in Responses
- [x] JSON-Responses enthalten nur Cart-Daten, keine internen IDs ausser product_id

#### Session Security
- [x] Session-Regeneration nach Login implementiert
- [ ] BUG: `readGuestCartFromStore()` deserialisiert Session-Payload mit `unserialize()` -- potentielles PHP Object Injection Risiko, obwohl durch `base64_decode` und `@`-Operator teilweise entschaerft (siehe BUG-8)

### Bugs Found

#### BUG-1: Header Cart Badge zeigt fuer eingeloggte Nutzer immer 0
- **Severity:** High
- **Location:** `d:\Claude_Code\Getraenkeshop\shop-tours\resources\views\shop\layout.blade.php` Zeile 41
- **Steps to Reproduce:**
  1. Als Kunde einloggen
  2. Produkt zum Warenkorb hinzufuegen
  3. Irgendeine Seite aufrufen
  4. Expected: Badge zeigt Anzahl der Warenkorb-Positionen
  5. Actual: Badge zeigt 0, weil `totalItems()` ohne `Auth::user()` aufgerufen wird und somit immer den Session-Cart (leer nach Login) prueft statt den DB-Cart
- **Root Cause:** `app(\App\Services\Shop\CartService::class)->totalItems()` wird ohne User-Parameter aufgerufen. Die Methode `totalItems(?User $user = null)` faellt auf den Session-Cart zurueck wenn `$user` null ist.
- **Priority:** Fix before deployment

#### BUG-2: Mini-Warenkorb Dropdown fehlt komplett
- **Severity:** Medium
- **Location:** `d:\Claude_Code\Getraenkeshop\shop-tours\resources\views\shop\layout.blade.php`
- **Steps to Reproduce:**
  1. Irgendeine Shop-Seite aufrufen
  2. Hover oder Klick auf Warenkorb-Icon im Header
  3. Expected: Dropdown mit letzten 3 Artikeln, Gesamtsumme und "Zur Kasse"-Button
  4. Actual: Direkter Link zur Warenkorb-Vollseite, kein Dropdown
- **Root Cause:** Die Alpine.js-Komponente fuer den Mini-Warenkorb wurde nicht implementiert. Der Backend-Endpunkt `GET /warenkorb/mini` existiert, aber es gibt kein Frontend dafuer.
- **Priority:** Fix before deployment (Acceptance Criterion AC-12)

#### BUG-3: Tech-Design-Widerspruch -- Preise werden in cart_items gespeichert
- **Severity:** Low
- **Location:** `d:\Claude_Code\Getraenkeshop\shop-tours\app\Services\Shop\CartService.php` Zeile 61-70
- **Description:** Das Tech-Design sagt explizit "Preise werden NICHT in cart_items gespeichert". Der Code speichert jedoch `unit_price_gross_milli` und `pfand_milli` als Snapshots in `cart_items`. Die Preise werden zwar live neu berechnet beim Anzeigen (korrekt), aber die Snapshots existieren trotzdem in der DB. Die Migration enthaelt diese Spalten auch.
- **Impact:** Kein funktionales Problem -- Snapshots koennen fuer Analytics nuetzlich sein. Aber die Dokumentation ist inkonsistent.
- **Priority:** Nice to have (Dokumentation aktualisieren)

#### BUG-4: Kein company_id-Scope auf Cart-Queries
- **Severity:** Medium (Critical in Multi-Tenant Betrieb)
- **Location:** `d:\Claude_Code\Getraenkeshop\shop-tours\app\Services\Shop\CartService.php` Zeilen 398-423
- **Steps to Reproduce:**
  1. In Multi-Tenant-Setup: User von Company A loggt sich ein
  2. Cart wird nur nach `user_id` gefiltert, nicht nach `company_id`
  3. Expected: Cart-Queries enthalten `where('company_id', $user->company_id)`
  4. Actual: `getOrCreateDbCart()` und `findActiveDbCart()` filtern nur nach `user_id` und `status`
- **Impact:** Im aktuellen Single-Tenant MVP kein Problem. Wird kritisch bei Multi-Tenant Aktivierung.
- **Priority:** Fix in next sprint

#### BUG-5: Mass Assignment auf company_id in CartItem moeglich
- **Severity:** Medium
- **Location:** `d:\Claude_Code\Getraenkeshop\shop-tours\app\Models\Shop\CartItem.php` Zeile 29-36
- **Description:** `company_id` ist in `$fillable` enthalten. Ein Angreifer koennte theoretisch ueber manipulierte POST-Requests versuchen, `company_id` zu setzen. Allerdings wird im aktuellen Code `company_id` nur intern gesetzt (aus `$user->company_id`), nicht aus Request-Daten. Das Risiko ist gering, solange `$request->validated()` oder explizite Zuweisung verwendet wird.
- **Priority:** Fix in next sprint (defensive Programmierung: `company_id` aus `$fillable` entfernen und stattdessen explizit setzen)

#### BUG-6: Fehlende Validierung des productId Route-Parameters
- **Severity:** Low
- **Location:** `d:\Claude_Code\Getraenkeshop\shop-tours\app\Http\Controllers\Shop\CartController.php` Zeilen 133, 171
- **Description:** `update()` und `remove()` nehmen `int $productId` direkt aus der URL ohne zu pruefen, ob das Produkt existiert. Wenn ein nicht-existierendes Produkt angegeben wird, passiert nichts Schlimmes (WHERE-Clause matched einfach nicht), aber es waere sauberer, die Existenz zu validieren.
- **Priority:** Nice to have

#### BUG-7: Kein Rate Limiting auf Warenkorb-Endpunkten
- **Severity:** Medium
- **Location:** `d:\Claude_Code\Getraenkeshop\shop-tours\routes\web.php` Zeilen 62-68
- **Description:** Die Warenkorb-Routen haben keine `throttle`-Middleware. Ein Angreifer koennte massenhaft POST /warenkorb Requests senden, um Session-Daten aufzublaehen oder die Datenbank mit Cart-Eintraegen zu fuellem.
- **Priority:** Fix in next sprint

#### BUG-8: Potentielles PHP Object Injection in readGuestCartFromStore()
- **Severity:** High
- **Location:** `d:\Claude_Code\Getraenkeshop\shop-tours\app\Services\Shop\CartService.php` Zeile 539
- **Description:** Die Methode `readGuestCartFromStore()` verwendet `unserialize(base64_decode($record))` auf Session-Payload aus der `sessions`-Tabelle. Obwohl die Sessions-Tabelle normalerweise nur von Laravel beschrieben wird, ist `unserialize()` generell unsicher, wenn die Datenquelle nicht vollstaendig vertrauenswuerdig ist. Bei einem SQL-Injection-Angriff auf die sessions-Tabelle koennte ein Angreifer manipulierte serialisierte Objekte einschleusen.
- **Mitigation:** Der `@`-Operator unterdrueckt Fehler, und der Rueckgabewert wird auf `is_array()` geprueft. Das reduziert das Risiko, eliminiert es aber nicht vollstaendig.
- **Priority:** Fix before deployment (Alternative: `json_decode` oder Laravel's eigene Session-Deserialisierung verwenden)

#### BUG-9: Tests verwenden nicht-existierende Methode rawCart()
- **Severity:** High
- **Location:** `d:\Claude_Code\Getraenkeshop\shop-tours\tests\Feature\Shop\CartTest.php` Zeilen 50, 63, 74, 110, 124, 136
- **Steps to Reproduce:**
  1. Tests ausfuehren: `php artisan test --filter=CartTest`
  2. Expected: Tests laufen durch
  3. Actual: Fehler -- `rawCart()` existiert nicht auf CartService. Die Methode heisst `rawSessionCart()`.
- **Impact:** Alle Unit-Tests und HTTP-Tests fuer den Warenkorb schlagen fehl.
- **Priority:** Fix before deployment

#### BUG-10: formatMilli() im Controller dupliziert MoneyHelper-Logik
- **Severity:** Low
- **Location:** `d:\Claude_Code\Getraenkeshop\shop-tours\app\Http\Controllers\Shop\CartController.php` Zeilen 241-245
- **Description:** Der Controller hat eine private `formatMilli()`-Methode, die die gleiche Logik wie der globale `milli_to_eur()` Helper implementiert, aber ein leicht anderes Format produziert ("EUR" mit Unicode-Symbol vs. "EUR" im Helper). Das fuehrt zu Inkonsistenzen: HTML-Views nutzen `milli_to_eur()` (gibt "1,00 EUR" zurueck), JSON-Responses nutzen `formatMilli()` (gibt "1,00 [Euro-Zeichen]" zurueck). Beide verwenden das Euro-Zeichen, aber die Controller-Methode ist ueberfluessig.
- **Priority:** Nice to have (Refactoring -- globalen Helper nutzen)

### Cross-Browser / Responsive Testing
- **Hinweis:** Keine laufende Applikation verfuegbar fuer visuelles Testing. Bewertung basiert auf Code-Analyse.
- [x] Blade-Template nutzt Tailwind responsive Klassen (`lg:grid-cols-3`, `sm:block`, `sm:inline`)
- [x] Warenkorb-Layout ist responsive: Items in voller Breite auf Mobile, 2/3 + 1/3 Sidebar auf Desktop
- [ ] Mini-Cart Dropdown fehlt komplett (BUG-2) -- daher nicht testbar
- [x] Cart-Badge im Header ist auch auf Mobile sichtbar
- [ ] BUG: Kein Mobile-Menu/Hamburger sichtbar -- Suchleiste ist `hidden sm:block`, auf Mobile nicht zugaenglich (betrifft Layout, nicht direkt PROJ-3)

### Summary
- **Acceptance Criteria:** 11/13 passed (AC-7 teilweise, AC-12 nicht bestanden)
- **Edge Cases:** 4/6 bestanden (EC-5 Dokumentations-Widerspruch, EC-6 Bundles nicht implementiert)
- **Bugs Found:** 10 total (0 critical, 3 high, 3 medium, 4 low)
- **Security:** Mehrere Findings (company_id Scope, Rate Limiting, unserialize, Mass Assignment)
- **Production Ready:** NEIN -- 3 High-Bugs muessen behoben werden (BUG-1, BUG-8, BUG-9)
- **Recommendation:** High-Priority Bugs beheben, dann erneut testen. Mini-Cart (BUG-2) ist ein fehlendes Feature und sollte vor Deployment implementiert werden.

## Deployment
_To be added by /deploy_
