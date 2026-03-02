# PROJ-4: Checkout

## Status: In Review
**Created:** 2026-02-28
**Last Updated:** 2026-03-02

## Dependencies
- Requires: PROJ-1 (Auth) — Checkout erfordert eingeloggten Nutzer (oder Gast-Checkout, falls aktiviert)
- Requires: PROJ-3 (Warenkorb) — Warenkorbinhalte werden in die Bestellung überführt
- Requires: PROJ-6 (Preisfindung) — Endpreise werden bei Bestellerstellung eingefroren (Snapshot)
- Requires: PROJ-7 (Pfand-System) — Pfand-Snapshot wird auf OrderItem gespeichert
- Requires: PROJ-8 (Zahlungsabwicklung) — Zahlungsmethode wird im Checkout gewählt

## Beschreibung
Mehrstufiger Checkout-Prozess: Lieferart wählen (Heimdienst oder Abholung), Lieferadresse wählen/eingeben, Zahlungsmethode wählen, Bestellung bestätigen. Nach Bestellabschluss werden alle Preise als unveränderliche Snapshots auf den OrderItems gespeichert.

## User Stories
- Als Kunde möchte ich zwischen Heimlieferung und Lager-Abholung wählen.
- Als Heimdienst-Kunde möchte ich meine Lieferadresse auswählen oder eine neue eingeben.
- Als Abholkunde möchte ich das gewünschte Lager/den gewünschten Markt auswählen.
- Als Kunde möchte ich ein Wunsch-Lieferdatum angeben (aus verfügbaren Terminen).
- Als Kunde möchte ich meine Zahlungsmethode wählen (aus für meine Kundengruppe freigeschalteten Methoden).
- Als Kunde möchte ich eine Bestellübersicht sehen, bevor ich verbindlich bestelle.
- Als Kunde möchte ich nach Bestellabschluss eine Bestätigungs-Email erhalten.
- Als System soll die korrekte `regular_delivery_tour_id` beim Checkout per PLZ-Lookup automatisch zugeordnet werden.

## Acceptance Criteria
- [ ] Schritt 1 — Lieferart: „Heimlieferung" oder „Abholung im Lager/Markt" auswählbar
- [ ] Schritt 2a (Heimlieferung): Auswahl gespeicherter Adressen oder neue Adresse eingeben; Pflichtfelder: Straße, Hausnr., PLZ, Stadt
  - **Abstellort:** Dropdown-Auswahl (Keller, EG, Garage, 1.OG, Sonstiges) — optionales Freitext-Feld bei „Sonstiges"
  - **Abstellen bei Nichtantreffen:** Checkbox „Ware darf bei Abwesenheit abgestellt werden"
  - Diese Felder werden pro Adresse gespeichert und können beim Checkout für jede Bestellung geändert werden
- [ ] Schritt 2b (Abholung): Auswahl des gewünschten Lagers/Standorts aus Liste
- [ ] Schritt 3 — Liefertermin: Kalender-Auswahl aus verfügbaren Terminen (basierend auf Tour-Planung / Öffnungszeiten)
- [ ] Schritt 4 — Zahlung: Nur für Kundengruppe freigeschaltete Zahlungsmethoden werden angezeigt
- [ ] Schritt 5 — Übersicht: Alle Positionen, Lieferart, Adresse, Termin, Zahlungsmethode, Gesamtsummen
- [ ] Bestellabschluss: `Order` und `OrderItems` werden mit Preis-Snapshots angelegt
  - `unit_price_net_milli`, `unit_price_gross_milli`, `tax_rate_basis_points`, `unit_deposit_milli` eingefroren
  - `product_name_snapshot`, `artikelnummer_snapshot` eingefroren
- [ ] `delivery_address_id` wird auf der Order gesetzt (aus gewählter Adresse)
- [ ] **Stamm-Tour bestimmt Lieferoptionen:**
  - Kunde hat eine Stamm-Tour → Tour ist voreingestellt; Kunde wählt nur das Lieferdatum aus verfügbaren Terminen der Tour
  - Neukunde ohne Stamm-Tour → PLZ + Ortsname prüfen:
    - 1 Treffer → Tour automatisch vorschlagen, nach Bestellung als Stamm-Tour speichern
    - Mehrere Treffer → Kunde wählt; Auswahl wird als Stamm-Tour gespeichert
    - Kein Treffer → Hinweis „Kein Liefergebiet gefunden"; `regular_delivery_tour_id = NULL`; Admin-Log
  - Nur für Kundengruppe des Kunden freigegebene Touren werden angezeigt
- [ ] Bestätigungs-Email wird nach Bestellabschluss versendet
- [ ] Weiterleitung zur Bestellbestätigungs-Seite mit Bestellnummer
- [ ] Warenkorb wird nach erfolgreichem Checkout geleert
- [ ] Bestellung ist auch ohne Kundenkonto möglich (Gast-Checkout), wenn in Einstellungen aktiviert

## Edge Cases
- Warenkorb ist beim Checkout-Aufruf leer → Weiterleitung zum Warenkorb mit Hinweis
- Produkt wird zwischen Warenkorbanlage und Checkout deaktiviert → Warnung, Checkout blockieren
- Kunde hat keine Adresse gespeichert → Neue Adresse direkt im Checkout eingeben
- PLZ liegt in keiner konfigurierten Tour-Zone → `regular_delivery_tour_id = NULL`, Admin wird benachrichtigt
- Zahlungsmethode Stripe → Weiterleitung zu Stripe Checkout, nach Rückkehr Bestellung anlegen
- Zahlungsmethode PayPal → Weiterleitung zu PayPal, nach Rückkehr Bestellung anlegen
- Stripe/PayPal Zahlung schlägt fehl → Bestellung NICHT anlegen, zurück zur Zahlungsauswahl
- Benutzer verlässt Stripe/PayPal und kommt zurück → Offene Zahlung abbrechen, erneut versuchen
- Mindestbestellwert unterschritten (konfigurierbar pro Tour) → Hinweis im Checkout

## Technical Requirements
- Alle Preise werden serverseitig berechnet und als Integer (milli-cents) gespeichert (nie float)
- Snapshots sind nach Bestellanlage unveränderlich
- Checkout-Session mit CSRF-Schutz
- Stripe-Integration: Redirect-Checkout (hosted) + Webhook-Bestätigung
- PayPal-Integration: Redirect + IPN/Webhook

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur (UI-Baum)

```
/checkout                         ← Mehrstufiger Wizard (Blade, Alpine.js für Step-Switching)
│
├── Schritt 1: Lieferart
│   ├── [Heimlieferung]
│   └── [Abholung im Lager]
│
├── Schritt 2a: Lieferadresse (bei Heimlieferung)
│   ├── Gespeicherte Adressen des Kunden (Radio-Liste)
│   ├── [+ Neue Adresse eingeben] (inline)
│   │   ├── Straße, Hausnr., PLZ, Ort
│   │   ├── Abstellort (Dropdown: Keller/EG/Garage/1.OG/Sonstiges)
│   │   └── Abstellen bei Nichtantreffen (Checkbox)
│   └── Tour-Zuordnung:
│       ├── Stamm-Tour vorhanden → automatisch + nächste Termine anzeigen
│       └── Kein Stamm-Tour → PLZ-Lookup → Treffer zur Auswahl
│
├── Schritt 2b: Abholort (bei Abholung)
│   └── Liste der Standorte/Lager
│
├── Schritt 3: Liefertermin
│   └── Kalender (nur erlaubte Termine der Stamm-Tour)
│
├── Schritt 4: Zahlungsmethode
│   └── Nur freigeschaltete Methoden der Kundengruppe (PROJ-8)
│
└── Schritt 5: Zusammenfassung + [Verbindlich bestellen]
    ├── Alle Positionen (Produktname-Snapshot, Menge, Preis, Pfand)
    ├── Lieferdetails, Zahlungsmethode
    └── Gesamtsummen (netto, Pfand, MwSt., brutto)
```

### Datenmodell

```
orders
├── id, order_number (unique, auto)
├── customer_id → customers
├── delivery_type  ENUM: home_delivery | pickup
├── delivery_address_id → addresses (nullable, bei Heimlieferung)
├── pickup_location_id → pickup_locations (nullable, bei Abholung)
├── regular_delivery_tour_id → regular_delivery_tours (nullable)
├── delivery_date (DATE)
├── payment_method  ENUM: stripe | paypal | sepa | invoice | cash | ec
├── status  ENUM: new | confirmed | in_delivery | completed | cancelled
├── notes (nullable)  ← interner Admin-Kommentar
├── customer_notes (nullable)  ← Kundennotiz
└── company_id

order_items  [Snapshots — UNVERÄNDERLICH nach Anlage]
├── id, order_id → orders
├── product_id → products  (zur Referenz, nicht zur Preisberechnung)
├── product_name_snapshot    ← Produktname zum Zeitpunkt der Bestellung
├── article_number_snapshot  ← Artikelnummer-Snapshot
├── quantity
├── unit_price_net_milli     ← Snapshot
├── unit_price_gross_milli   ← Snapshot
├── tax_rate_basis_points    ← Snapshot (PFLICHTFELD, kein NULL)
├── unit_deposit_milli       ← Snapshot
├── deposit_tax_rate_basis_points ← Snapshot
├── pfand_set_id (nullable)  ← Snapshot: Rückverfolgung
├── is_backorder (bool)
└── company_id

order_adjustments  [Leergut, Bruch, Korrekturen]
├── id, order_id → orders
├── type  ENUM: deposit_return | breakage | price_correction | other
├── description
├── quantity (nullable)
├── unit_value_milli   (negativ = Gutschrift)
├── tax_rate_basis_points
└── company_id
```

### Checkout-Flow (serverseitig)

```
POST /checkout/bestaetigen

1. Warenkorb validieren (Produkte aktiv, Preise verfügbar)
2. Preis-Snapshots berechnen (PriceResolverService + PfandCalculator)
3. Order + OrderItems in DB-Transaktion anlegen
4. Warenkorb leeren
5. Zahlung initiieren:
   → Stripe/PayPal: Redirect zu Payment Provider (Order bleibt 'new')
   → Andere: Order direkt auf 'confirmed' setzen
6. Bestätigungs-Email in deferred_tasks einreihen
7. Weiterleitung zur Bestätigungsseite
```

### Tour-Zuordnung (TourAssignmentService)

```
resolveTours(string $postalCode, string $city, int $customerGroupId): array

→ Sucht DeliveryAreas: PLZ exakt + (city_match IS NULL OR city LIKE '%city_match%')
→ Filtert: nur Touren für die Kundengruppe erlaubt
→ Gibt sortierte Liste passender RegularDeliveryTours zurück
→ 0 Treffer: return []  → Checkout zeigt "Kein Liefergebiet"
→ 1 Treffer: automatisch zuordnen
→ n Treffer: Kundenauswahl anzeigen
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| Blade-Wizard (kein SPA) | Kein JavaScript-State über Schritte hinweg; Back-Button funktioniert korrekt; CSRF automatisch |
| Snapshot-Felder auf `order_items` | Preisänderungen dürfen bestehende Bestellungen nicht retroaktiv ändern |
| `tax_rate_basis_points` als Pflichtfeld (kein NULL) | Rechtssicherheit; Fehler beim Checkout ist besser als stille 0% MwSt. |
| Separate `order_adjustments` | Leergut/Bruch/Korrekturen als eigene Zeilen; Rechnungs-Draft kann sie direkt einlesen |
| Bestätigungs-Email via `deferred_tasks` | Email-Versand blockiert keinen Bestellabschluss |

### Neue Controller / Services

```
Shop\CheckoutController          ← index, lieferart, adresse, termin, zahlung, bestaetigen
TourAssignmentService            ← resolveTours(), automatische Stamm-Tour-Zuordnung
```

## QA Test Results

**Tested:** 2026-03-02
**App URL:** http://localhost:8000
**Tester:** QA Engineer (AI) -- Code Review + Static Analysis

---

### Acceptance Criteria Status

#### AC-1: Schritt 1 -- Lieferart
- [x] "Heimlieferung" und "Abholung im Lager/Markt" sind als Radio-Buttons im Blade-Template vorhanden (checkout.blade.php, Zeile 48-65)
- [x] Alpine.js x-model bindet korrekt an `deliveryType`
- [x] "Weiter"-Button ist disabled, wenn keine Lieferart gewaehlt ist

#### AC-2: Schritt 2a -- Lieferadresse (Heimlieferung)
- [x] Gespeicherte Adressen werden als Radio-Liste angezeigt (Zeile 85-105)
- [x] Neue Adresse inline eingebbar mit Pflichtfeldern: Strasse, PLZ, Stadt (Zeile 116-155)
- [x] Abstellort als Dropdown vorhanden: Keller, Einfahrt, EG, Garage, 1.OG, Sonstiges (Zeile 160-166)
- [x] Freitext bei "Sonstiges" wird korrekt ein-/ausgeblendet (Zeile 168-171)
- [x] Checkbox "Ware darf bei Abwesenheit abgestellt werden" vorhanden (Zeile 173-176)
- [x] Abstellort-Felder werden auf Address-Model korrekt gespeichert (CheckoutController Zeile 314-331, 344-350)
- [ ] BUG-1: Hausnr. ist laut Spec ein Pflichtfeld ("Strasse, Hausnr., PLZ, Stadt"), aber in der Validierung und im Formular als optional behandelt

#### AC-3: Schritt 2b -- Abholort (Abholung)
- [x] Auswahl der Standorte/Lager aus Liste (Zeile 182-199)
- [x] Warehouses mit `is_pickup_location = true` und `active = true` werden geladen (Controller Zeile 83-86)

#### AC-4: Schritt 3 -- Liefertermin
- [x] Datums-Input vorhanden mit min-Datum von morgen (Zeile 237-244)
- [x] Tour-Auswahl Dropdown wird angezeigt, wenn Touren verfuegbar (Zeile 221-234)
- [ ] BUG-2: Es wird kein richtiger Kalender mit nur verfuegbaren Tour-Terminen angezeigt. Stattdessen ist es ein freies date-Input, das jedes beliebige Datum in der Zukunft akzeptiert. Die Spec verlangt "Kalender-Auswahl aus verfuegbaren Terminen (basierend auf Tour-Planung / Oeffnungszeiten)"

#### AC-5: Schritt 4 -- Zahlung
- [x] Nur fuer Kundengruppe freigeschaltete Methoden werden angezeigt (Controller Zeile 80, Template Zeile 278-289)
- [x] Server-seitige Validierung, dass gewaehlte Methode erlaubt ist (Controller Zeile 142-147)

#### AC-6: Schritt 5 -- Uebersicht
- [x] Alle Positionen mit Produktname, Menge, Preis und Pfand angezeigt (Zeile 340-354)
- [x] Lieferart, Zahlungsmethode und Liefertermin in Zusammenfassung (Zeile 311-326)
- [x] Gesamtsummen (brutto, Pfand, MwSt.-Aufschluesselung) werden angezeigt (Zeile 358-379)
- [x] "Jetzt verbindlich bestellen"-Button mit Submitting-State (Zeile 388-392)
- [x] AGB- und Datenschutz-Links vorhanden (Zeile 396-399)
- [ ] BUG-3: Lieferadresse wird in der Zusammenfassung (Schritt 5) nicht angezeigt. Die Spec verlangt "Adresse" in der Uebersicht, aber das Template zeigt nur Lieferart, Termin und Zahlungsmethode an

#### AC-7: Bestellabschluss -- Preis-Snapshots
- [x] OrderService erstellt Order + OrderItems in DB-Transaktion (OrderService Zeile 104-191)
- [x] `unit_price_net_milli`, `unit_price_gross_milli`, `tax_rate_basis_points`, `unit_deposit_milli` werden eingefroren (OrderItem $fillable + OrderPricingService)
- [x] `product_name_snapshot`, `artikelnummer_snapshot` werden eingefroren (OrderService Zeile 164-165)
- [ ] BUG-4: `deposit_tax_rate_basis_points` ist im Tech Design als Snapshot-Feld gelistet, existiert aber nicht im OrderItem-Model und nicht in der Datenbank. Grep ueber das gesamte Backend liefert null Treffer

#### AC-8: delivery_address_id
- [x] Wird auf der Order gesetzt (Controller Zeile 194)
- [x] Nur eigene Adressen koennen gewaehlt werden -- Ownership-Check vorhanden (Controller Zeile 335-340)

#### AC-9: Stamm-Tour Zuordnung
- [x] TourAssignmentService vorhanden mit PLZ-Lookup (TourAssignmentService)
- [x] Korrekte Logik: 0 Treffer = leere Collection, 1 Treffer = automatisch, N Treffer = Kundenauswahl
- [ ] BUG-5: Spec verlangt "Nur fuer Kundengruppe des Kunden freigegebene Touren werden angezeigt". Der TourAssignmentService filtert NICHT nach customerGroupId -- der dritte Parameter aus dem Tech Design (`int $customerGroupId`) fehlt in der Signatur komplett
- [ ] BUG-6: Spec verlangt "Neukunde ohne Stamm-Tour -- nach Bestellung als Stamm-Tour speichern". Der CheckoutController speichert die tour_id nur auf der Order, aber aktualisiert NICHT den Kunden-Record (keine Stamm-Tour-Persistenz fuer zukuenftige Bestellungen). Der Code prueft nur vorherige Orders als Workaround (Controller Zeile 103-109), was fragil ist
- [ ] BUG-7: Spec verlangt "Kein Treffer: Hinweis 'Kein Liefergebiet gefunden' + Admin-Log". Der Admin-Log bei "kein Treffer" fehlt im Controller komplett

#### AC-10: Bestaetigungs-Email
- [x] Email wird via `deferred_tasks` eingereiht (Controller Zeile 437-449)
- [x] Wird nach Bestellabschluss aufgerufen (Controller Zeile 371, 273)

#### AC-11: Weiterleitung zur Bestellbestaetigungsseite
- [x] Redirect zu `checkout.success` Route (Controller Zeile 372)
- [x] Bestellnummer wird auf Success-Seite angezeigt (checkout-success.blade.php Zeile 10)

#### AC-12: Warenkorb wird geleert
- [x] `$this->cart->clear($user)` nach erfolgreicher Order-Erstellung (Controller Zeile 217)

#### AC-13: Gast-Checkout
- [ ] BUG-8: Spec verlangt "Bestellung ist auch ohne Kundenkonto moeglich (Gast-Checkout), wenn in Einstellungen aktiviert". Dies ist NICHT implementiert. Die Route hat `middleware('auth')` und der Controller prueft `requireCustomer()`. Es gibt keine Einstellung und keinen alternativen Flow fuer Gaeste

---

### Edge Cases Status

#### EC-1: Warenkorb leer beim Checkout-Aufruf
- [x] Korrekt gehandhabt: Redirect zum Warenkorb mit Info-Meldung (Controller Zeile 63-64)

#### EC-2: Produkt deaktiviert zwischen Warenkorb und Checkout
- [x] `has_unavailable` Check vorhanden, blockiert Checkout (Controller Zeile 71-74)

#### EC-3: Keine gespeicherte Adresse
- [x] "Neue Adresse eingeben" Option immer verfuegbar (Template Zeile 107-113)

#### EC-4: PLZ ausserhalb Liefergebiet
- [x] TourAssignmentService gibt leere Collection zurueck
- [ ] BUG-7 (wiederholt): Admin-Log bei "kein Treffer" fehlt

#### EC-5: Stripe-Zahlung
- [x] Redirect zu Stripe Checkout, Order bleibt 'pending' (Controller Zeile 379-403)
- [ ] BUG-9: Wenn Stripe Session-Erstellung fehlschlaegt, wird die Order trotzdem auf 'confirmed' gesetzt und eine Bestaetigungs-Email verschickt (Controller Zeile 397-401). Der Kunde bekommt eine Bestaetigung, obwohl keine Zahlung stattgefunden hat -- dies ist ein stiller Fallback ohne Zustimmung des Kunden

#### EC-6: PayPal-Zahlung
- [x] Redirect zu PayPal, nach Rueckkehr Order erfassen (Controller Zeile 241-276)
- [x] PayPal-Cancel setzt Order auf cancelled (Controller Zeile 284-293)

#### EC-7: Stripe/PayPal schlaegt fehl
- [x] PayPal: Bei fehlgeschlagenem Capture wird Order cancelled (Controller Zeile 265-268)
- [ ] BUG-9 (wiederholt): Stripe-Fallback setzt auf 'confirmed' statt Error

#### EC-8: Benutzer verlaeaesst Stripe/PayPal
- [x] PayPal-Cancel-Route vorhanden (Controller Zeile 281-294)
- [ ] BUG-10: Fuer Stripe gibt es keinen expliziten Cancel-Callback. Der Cancel-URL zeigt auf `/kasse?stripe=cancelled`, aber es gibt keine Logik, die diesen Query-Parameter verarbeitet und die offene Order aufraeumt. Die Order bleibt im Status 'pending' haengen

#### EC-9: Mindestbestellwert unterschritten
- [ ] BUG-11: Komplett nicht implementiert. Die Spec verlangt "Mindestbestellwert unterschritten (konfigurierbar pro Tour) --> Hinweis im Checkout". Es gibt keine Pruefung dafuer

---

### Security Audit Results

#### Auth Bypass
- [x] Route `/kasse` ist hinter `middleware('auth')` geschuetzt (web.php Zeile 100-104)
- [x] Success-Page prueft `$order->customer_id !== $customer->id` (Controller Zeile 229)
- [x] Test vorhanden: `customer_cannot_see_other_customers_order_success` (CheckoutTest)

#### Authorization / IDOR
- [x] Delivery Address: Ownership-Check `where('customer_id', $customer->id)` vorhanden (Controller Zeile 335-340)
- [ ] BUG-12 (CRITICAL): **PayPal-Cancel IDOR** -- Die `paypalCancel()` Methode (Controller Zeile 281-294) prueft NICHT den Besitz der Order. Jeder authentifizierte User, der den PayPal-Token kennt, kann die Bestellung eines anderen Kunden stornieren (`$order->update(['status' => Order::STATUS_CANCELLED])`). Es gibt keinen `$order->customer_id === $customer->id` Check.

#### company_id Isolation
- [ ] BUG-13 (CRITICAL): **Fehlende company_id auf Order und OrderItem** -- Weder im CheckoutController noch im OrderService wird `company_id` gesetzt. Das Order::create() in OrderService Zeile 111 hat kein `company_id` Feld. Dies verstoeaat gegen die Architektur-Regel "company_id auf ALLEN Tabellen vorbereiten". Bei Multi-Tenant-Betrieb waeren alle Bestellungen ohne Mandant-Zuordnung.
- [ ] BUG-14: Die Address::create() im CheckoutController (Zeile 314-329) setzt ebenfalls keine `company_id`.
- [ ] BUG-15: Die DeferredTask::create() (Zeile 439-448) setzt keine `company_id`.

#### Mass Assignment
- [x] Order-Model hat `$fillable` korrekt definiert -- keine sensitiven Felder (status, etc.) ungeschuetzt
- [x] Address-Model hat `$fillable` korrekt definiert
- [x] StoreCheckoutRequest nutzt `$request->validated()` (Controller Zeile 139)
- [x] OrderItem-Model hat `$fillable` korrekt definiert

#### Input Injection (XSS)
- [x] Blade-Templates nutzen `{{ }}` (auto-escaped) fuer alle Ausgaben
- [x] customer_notes werden via `{{ $order->customer_notes }}` escaped auf der Success-Seite
- [x] drop_off_location_custom wird nicht in einem unsafe Kontext ausgegeben

#### SQL Injection
- [x] Alle Queries nutzen Eloquent/QueryBuilder (parametrisierte Queries)
- [ ] BUG-16 (MEDIUM): TourAssignmentService Zeile 46 verwendet `'LIKE', '%' . $city . '%'` -- der `$city`-Wert wird direkt in den LIKE-Pattern eingesetzt. Obwohl Eloquent dies parametrisiert (kein SQL-Injection-Risiko), werden LIKE-Sonderzeichen (`%`, `_`) im User-Input nicht escaped, was unerwartete Suchergebnisse liefern kann.

#### CSRF
- [x] Blade-Form nutzt `@csrf` Directive (checkout.blade.php Zeile 38)
- [x] Laravel CSRF-Middleware ist fuer alle Web-Routen standardmaessig aktiv

#### Rate Limiting
- [ ] BUG-17 (HIGH): **Kein Rate-Limiting auf POST /kasse** -- Die Checkout-Route `Route::post('/kasse', ...)` hat kein `throttle` Middleware. Ein Angreifer koennte rapid-fire Bestellungen erstellen. Cart-Routen haben `throttle:cart`, Login hat `throttle:login`, aber der Checkout-Endpunkt ist ungeschuetzt.

#### Secrets Exposure
- [x] Keine API-Keys oder DB-Credentials in Responses
- [x] Stripe/PayPal-Tokens werden nicht an den Client exponiert

#### Validation -- delivery_address_id
- [ ] BUG-18 (HIGH): **Validation Conflict** -- `delivery_address_id` hat die Regel `['required_if:delivery_type,home_delivery', 'nullable', 'integer']` aber das Formular sendet den String-Wert `"new"` wenn eine neue Adresse eingegeben wird. Der `integer` Validator wuerde `"new"` ablehnen. Die `required_if:delivery_address_id,new` Regeln fuer `new_address.*` Felder wuerden dann nie greifen, weil der Wert bereits in der Validierung scheitert. Dies blockiert das Anlegen neuer Adressen beim Checkout komplett.

---

### Bugs Found

#### BUG-1: Hausnummer nicht als Pflichtfeld
- **Severity:** Low
- **Steps to Reproduce:**
  1. Gehe zum Checkout, Schritt 2a
  2. Waehle "Neue Adresse eingeben"
  3. Lasse Hausnummer leer, fuelle Rest aus
  4. Expected: Validierungsfehler (Spec sagt Pflichtfeld)
  5. Actual: Adresse wird ohne Hausnummer gespeichert
- **Priority:** Fix in next sprint

#### BUG-2: Freies Datumsfeld statt Tour-basierter Kalender
- **Severity:** Medium
- **Steps to Reproduce:**
  1. Gehe zum Checkout, Schritt 3
  2. Expected: Kalender zeigt nur verfuegbare Tour-Termine
  3. Actual: Standard HTML date-Input akzeptiert jedes Zukunftsdatum
- **Priority:** Fix before deployment

#### BUG-3: Lieferadresse fehlt in Zusammenfassung (Schritt 5)
- **Severity:** Medium
- **Steps to Reproduce:**
  1. Waehle Heimlieferung und eine Adresse
  2. Gehe weiter zu Schritt 5
  3. Expected: Gewaehlte Adresse wird in der Uebersicht angezeigt
  4. Actual: Nur Lieferart, Termin und Zahlungsmethode sichtbar
- **Priority:** Fix before deployment

#### BUG-4: deposit_tax_rate_basis_points Snapshot fehlt
- **Severity:** Medium
- **Steps to Reproduce:**
  1. Erstelle eine Bestellung mit Pfand-Produkten
  2. Expected: deposit_tax_rate_basis_points wird auf OrderItem gespeichert
  3. Actual: Feld existiert nicht im Model, nicht in Migration, nicht in DB
- **Priority:** Fix before deployment (Rechnungsrelevanz, Steuersatz fuer Pfand)

#### BUG-5: TourAssignmentService filtert nicht nach Kundengruppe
- **Severity:** High
- **Steps to Reproduce:**
  1. Konfiguriere Touren, die nur fuer bestimmte Kundengruppen gelten
  2. Logge als Kunde einer anderen Gruppe ein
  3. Expected: Nur fuer seine Gruppe freigegebene Touren sichtbar
  4. Actual: Alle aktiven Touren im Liefergebiet werden angezeigt
- **Priority:** Fix before deployment

#### BUG-6: Stamm-Tour wird nicht persistent gespeichert
- **Severity:** High
- **Steps to Reproduce:**
  1. Logge als Neukunde ohne Stamm-Tour ein
  2. Bestelle und waehle eine Tour
  3. Expected: Tour wird als Stamm-Tour auf dem Kunden gespeichert
  4. Actual: Tour wird nur auf der Order gespeichert, nicht am Kunden-Record
- **Priority:** Fix before deployment

#### BUG-7: Admin-Log bei "kein Liefergebiet" fehlt
- **Severity:** Low
- **Steps to Reproduce:**
  1. Gib eine PLZ ein, die in keiner Tour konfiguriert ist
  2. Expected: Admin-Log-Eintrag wird erstellt
  3. Actual: Kein Logging
- **Priority:** Fix in next sprint

#### BUG-8: Gast-Checkout nicht implementiert
- **Severity:** Medium
- **Steps to Reproduce:**
  1. Rufe /kasse als nicht-eingeloggter Nutzer auf
  2. Expected: Gast-Checkout moeglich (wenn in Einstellungen aktiviert)
  3. Actual: Redirect zum Login
- **Priority:** Fix in next sprint (Feature-Gap, kein Security-Issue)

#### BUG-9: Stripe-Fallback setzt Order auf 'confirmed' ohne Zahlung
- **Severity:** High
- **Steps to Reproduce:**
  1. Waehle Stripe als Zahlungsmethode
  2. Stripe API ist nicht erreichbar / Keys ungueltig
  3. Expected: Checkout schlaegt fehl, Kunde wird informiert
  4. Actual: Order wird auf 'confirmed' gesetzt, Bestaetigungs-Email verschickt, Kunde sieht Success-Seite mit Warnung
- **Priority:** Fix before deployment

#### BUG-10: Stripe-Cancel hinterlaeaaesst verwaiste Order
- **Severity:** Medium
- **Steps to Reproduce:**
  1. Waehle Stripe, werde weitergeleitet
  2. Breche auf der Stripe-Seite ab
  3. Expected: Order wird cancelled, Warenkorb wiederhergestellt
  4. Actual: Order bleibt im Status 'pending', Warenkorb ist bereits geleert
- **Priority:** Fix before deployment

#### BUG-11: Mindestbestellwert nicht implementiert
- **Severity:** Medium
- **Steps to Reproduce:**
  1. Lege einen Artikel unter Mindestbestellwert in den Warenkorb
  2. Gehe zum Checkout
  3. Expected: Hinweis "Mindestbestellwert nicht erreicht"
  4. Actual: Bestellung geht normal durch
- **Priority:** Fix in next sprint

#### BUG-12: PayPal-Cancel IDOR (CRITICAL)
- **Severity:** Critical
- **Steps to Reproduce:**
  1. Nutzer A erstellt eine PayPal-Bestellung (Order bekommt payment_reference)
  2. Nutzer B ruft GET /kasse/paypal/cancel?token=<PayPal-Token> auf
  3. Expected: Nur der Besteller kann seine eigene Bestellung stornieren
  4. Actual: Jeder authentifizierte Nutzer kann die Order von Nutzer A canceln
- **Priority:** Fix before deployment (CRITICAL Security)

#### BUG-13: company_id nicht gesetzt auf Orders (CRITICAL)
- **Severity:** Critical
- **Steps to Reproduce:**
  1. Erstelle eine Bestellung
  2. Pruefe `orders.company_id` in der Datenbank
  3. Expected: company_id ist gesetzt (Multi-Tenant-Vorbereitung)
  4. Actual: company_id bleibt NULL
- **Priority:** Fix before deployment (Architektur-Verletzung)

#### BUG-14: company_id nicht gesetzt auf neuen Adressen
- **Severity:** Medium
- **Steps to Reproduce:**
  1. Erstelle eine neue Adresse im Checkout
  2. Pruefe `addresses.company_id`
  3. Expected: company_id gesetzt
  4. Actual: NULL (falls Spalte existiert -- Address-Schema hat moeglicherweise kein company_id)
- **Priority:** Fix in next sprint

#### BUG-15: company_id nicht gesetzt auf DeferredTask
- **Severity:** Low
- **Steps to Reproduce:**
  1. Bestelle, pruefe deferred_tasks.company_id
  2. Expected: Gesetzt
  3. Actual: NULL
- **Priority:** Fix in next sprint

#### BUG-16: LIKE-Wildcards nicht escaped in TourAssignment
- **Severity:** Low
- **Steps to Reproduce:**
  1. Gib als Ort "%" oder "_test" ein
  2. Expected: Suche nach Literal-Zeichen
  3. Actual: LIKE-Muster wird beeinflusst, unerwartete Ergebnisse
- **Priority:** Nice to have

#### BUG-17: Kein Rate-Limiting auf POST /kasse
- **Severity:** High
- **Steps to Reproduce:**
  1. Sende 100 POST-Requests an /kasse in schneller Folge
  2. Expected: Rate-Limiting greift nach wenigen Requests
  3. Actual: Alle 100 Bestellungen werden angelegt
- **Priority:** Fix before deployment

#### BUG-18: Validation-Conflict bei delivery_address_id = "new"
- **Severity:** High
- **Steps to Reproduce:**
  1. Waehle Heimlieferung im Checkout
  2. Klicke "Neue Adresse eingeben" (sendet delivery_address_id = "new")
  3. Fuelle alle Pflichtfelder aus und bestelle
  4. Expected: Adresse wird angelegt, Bestellung durchgefuehrt
  5. Actual: Validierung schlaegt fehl -- "new" ist kein Integer. Der Controller behandelt "new" korrekt (Zeile 307), aber die FormRequest-Validierung blockt vorher
- **Priority:** Fix before deployment (BLOCKING)

---

### Cross-Browser & Responsive Notes

Da es sich um ein Blade-Template mit Alpine.js handelt (serverseitig gerendert), gelten die folgenden Einschaetzungen basierend auf Code-Review:

- **Chrome/Firefox/Safari:** Alpine.js und standard HTML-Inputs sind cross-browser kompatibel. Kein Framework-spezifisches Risiko.
- **375px (Mobile):** `grid-cols-2` und `grid-cols-3` Layouts in der neuen Adresse (Zeile 117, 131, 141) haben KEINE responsive Breakpoints. Auf 375px werden die 3-Spalten-Layouts sehr eng. Die Step-Indicator-Leiste hat `overflow-x-auto` (korrekt).
- **768px (Tablet):** `md:grid-cols-2` nur in der Zusammenfassung (Zeile 312). Sonst kaum Responsive-Anpassungen.
- **1440px (Desktop):** `max-w-4xl mx-auto` zentriert den Inhalt korrekt.

---

### Summary
- **Acceptance Criteria:** 8/13 passed (5 failed)
- **Bugs Found:** 18 total (2 critical, 4 high, 6 medium, 6 low)
- **Security:** FAIL -- Critical IDOR in PayPal cancel, missing company_id, missing rate limiting
- **Production Ready:** NO
- **Recommendation:** Fix BUG-12 (IDOR), BUG-13 (company_id), BUG-17 (rate limiting), BUG-18 (validation blocker) and BUG-9 (Stripe fallback) BEFORE deployment. BUG-18 is likely a blocking defect that prevents new address creation entirely.

## Deployment
_To be added by /deploy_
