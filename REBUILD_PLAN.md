# REBUILD_PLAN.md — Kolabri Getränkeshop
Stand: 2026-02-28 | Branch: initial-code-review

> **Wichtig:** Das Projekt hat eine solide Architektur — kein vollständiger Rebuild nötig.
> Die bestehende Codebasis ist qualitativ hochwertig (strict_types, Integer-Arithmetik, DTOs,
> saubere Service-Schicht, gute Test-Abdeckung). Der Plan fokussiert sich auf:
> 1. Sicherheitslücken schließen (sofort)
> 2. Fehlende Admin-UIs ergänzen (mittelfristig)
> 3. Architektur-Inkonsistenzen beheben (mittelfristig)
> 4. Produktions-Härtung (parallel zur Weiterentwicklung)

---

## 1. Zielbild (Soll-Architektur)

### Module / Bounded Contexts

```
┌─────────────────────────────────────────────────────────────────┐
│  Shop (Public)                                                   │
│  Catalog · Cart · Checkout · Account · CMS                      │
├─────────────────────────────────────────────────────────────────┤
│  Admin (Internal)                                                │
│  Orders · Invoices · Stammdaten · Customers · Suppliers ·       │
│  Products · Inventory · Tours · Reports · Integrations · Ops    │
├─────────────────────────────────────────────────────────────────┤
│  Domain Logic (stateless Services)                               │
│  PriceResolverService · PfandCalculator · OrderPricingService · │
│  OrderService · InvoiceService · FulfillmentService ·           │
│  TourPlannerService · StockService                              │
├─────────────────────────────────────────────────────────────────┤
│  Driver PWA (Mobile API)                                        │
│  Bootstrap · Sync · Upload                                      │
├─────────────────────────────────────────────────────────────────┤
│  Integrations                                                   │
│  Lexoffice · Stripe · Google OAuth · Paypal                     │
├─────────────────────────────────────────────────────────────────┤
│  Infrastructure                                                 │
│  Migrations · CSV Importers · PDF (DomPDF) · Mail · Queue       │
└─────────────────────────────────────────────────────────────────┘
```

### Schichten / Abhängigkeiten (Domain → Services → Adapters)

```
Models (Eloquent)
    ↑
Services (stateless, PricingRepository-Interface für Testbarkeit)
    ↑
Controllers (thin, delegieren an Services)
    ↑
Routes (Laravel Router)
```

- **DTOs** zwischen Services und Controllers (`OrderItemPricingSnapshot`, `PriceResult`)
- **Interfaces** wo externe Abhängigkeiten getauscht werden könnten (`PricingRepositoryInterface`)
- **Integer-Arithmetik** für alle Geldbeträge (milli-cents, nie float)
- **Snapshots** für alle preisrelevanten Felder auf OrderItem (immutabel nach Erstellung)

### Datenmodell-Strategie (Ist bereits gut — Ergänzungen)

- `customers.company_id` überall korrekt befüllen (→ RegisterController-Fix)
- `orders.delivery_address_id` tatsächlich befüllen (→ OrderService erweitern)
- `orders.regular_delivery_tour_id` beim Checkout per PLZ-Lookup setzen
- `supplier_products.active` Migrationsstatus klären

---

## 2. Reihenfolge (Roadmap)

| Schritt | Scope | Ergebnis / DoD | Risiken | Tests |
|---|---|---|---|---|
| **0 — SOFORT: Security-Fixes** | `.gitignore` für SSH-Keys + install.php; install.php löschen | Keys nicht committet; install.php weg | Key-Rotation falls bereits committed | — |
| **1 — company_id-Fix bei Registrierung** | `RegisterController` + `SocialController` | Neue Kunden haben company_id; CompanyScopingTest grün | Bestehende Kunden mit NULL company_id brauchen Migration | `CompanyScopingTest` erweitern |
| **2 — InvoiceService Tax-Fallback entfernen** | `InvoiceService.php:117` | Keine stillen Fallbacks; Validierung vor Draft | Ältere Orders ohne tax_rate_basis_points | Migration + Backfill für bestehende OrderItems |
| **3 — SupplierProduct.active klären** | Migration prüfen/ergänzen | Feld vorhanden + Default true | — | `InvoiceFinalizeTest` erweitern um Marge |
| **4 — delivery_address_id auf Orders befüllen** | `OrderService` + `ShopCheckoutController` | Gewählte Adresse wird auf Order gespeichert | Checkout-Flow muss Adress-Auswahl übergeben | `CheckoutTest` erweitern |
| **5 — WP-22 Order-Edit Tests** | `AdminOrderEditTest` schreiben | Vollständige Test-Abdeckung für Order-Bearbeitung | Keine | Neuer Test |
| **6 — Lager Admin-UI** | `AdminWarehouseController`, `AdminStockController` + Views | Admin kann Warehouses + Bestände einsehen und buchen | Komplexer Stock-Bewegungsflow | `StockAdminTest` |
| **7 — Tour Admin-UI** | `AdminRegularTourController`, `AdminDeliveryAreaController` + Views | Admin kann Touren-Templates + Gebiete + Kunden-Zuordnung verwalten | — | `TourAdminTest` |
| **8 — PLZ-basiertes Tour-Assignment** | `CheckoutController` + neuer `TourAssignmentService` | Beim Checkout wird korrekte RegularDeliveryTour gesetzt | Kunde ohne passende Tour-Zone | `CheckoutTest` erweitern |
| **9 — Invoice Race Condition** | `InvoiceService::finalizeInvoice()` | `SELECT FOR UPDATE` oder Sequenz-Tabelle | — | Parallelitäts-Test |
| **10 — POS-API Auth** | API-Key-Middleware für POS-Routen | Kein unauthentifizierter Zugang zu Verkauf-API | Breaking Change für bestehende POS-Clients | `PosApiTest` erweitern |
| **11 — E-Mail Queue** | Mail-Dispatch in deferred_tasks oder Laravel Queue | Mail-Fehler crashen keine Finalize-Transaktion | Shared-Hosting Queue-Verfügbarkeit | `EmailTest` erweitern |
| **12 — CMS-Pages Tests** | `PageControllerTest`, `AdminPageTest` | Vollständige Test-Abdeckung | — | Neue Tests |

---

## 3. Wiederverwendung vs. Neu

### Wiederverwenden (alles OK, nicht anfassen ohne Grund)
- `PriceResolverService` — mathematisch korrekt, gut getestet, stateless
- `PfandCalculator` — rekursive Logik mit Zyklusschutz, korrekt
- `OrderPricingService` / `OrderService` — saubere Verantwortungsaufteilung, gut getestet
- `FulfillmentService` / `TourPlannerService` — komplex aber getestet
- `DriverSyncService` + Driver-API — vollständig implementiert und getestet
- Alle CSV-Importer — pragmatisch und funktionsfähig
- `InvoiceService` — solide, Fixes in Schritt 2-3 sind punktuell
- Admin-CRUD-Controller (Stammdaten, Kunden, Lieferanten, Produkte) — funktionieren
- Alle Migrations — behalten; ergänzen wo nötig (kein Rückbau!)

### Neu schreiben / Ergänzen
- `AdminWarehouseController` + Views — fehlt komplett
- `AdminStockController` + Views — fehlt komplett
- `AdminRegularTourController` + Views — fehlt komplett
- `AdminDeliveryAreaController` — fehlt komplett
- `TourAssignmentService` — PLZ-basiertes Tour-Assignment
- `AdminOrderEditTest` — fehlt
- `PageControllerTest`, `AdminPageTest` — fehlt
- `GoogleOAuthTest` — fehlt

### Wegwerfen / Bereinigen
- `public/install.php` — nach einmaliger Verwendung löschen + gitignore
- `githubkey`, `githubkey.pub` — aus Projekt-Root entfernen, gitignore
- `start-server.bat` — gitignore (oder in `/scripts/` mit klarer Doku)
- Hardcoded `?? 190_000` Fallback in InvoiceService — entfernen

---

## 4. Migration / Übergang

### Datenmigration

| Was | Wie | Risiko |
|---|---|---|
| Bestehende `customers` mit `company_id = NULL` | One-time Migration: Alle auf erste/default Company setzen | Niedrig — falls nur 1 Company existiert |
| Bestehende `order_items` mit `tax_rate_basis_points = NULL` | Backfill aus `products.tax_rate_id → tax_rates.rate_basis_points`; bei Produkten ohne Steuersatz auf 19% setzen + loggen | Mittel — manuell prüfen welche betroffen |
| `supplier_products` ohne `active`-Feld | Migration: Spalte ergänzen, Default `true` | Niedrig |
| Bestehende `orders` ohne `delivery_address_id` | Nicht backfillbar (historisch); ab Schritt 4 nur für neue Orders | Keine Migration nötig — Legacy-Handling in Views |

### Parallelbetrieb (falls nötig)
- Kein Parallelbetrieb nötig — alle Fixes sind rückwärtskompatibel
- Admin-UI-Ergänzungen (Lager, Touren) sind additive Features ohne Breaking Changes
- Tour-Assignment beim Checkout: Feld `regular_delivery_tour_id` war bereits `NULL`-tolerant

### Cutover-Plan (für Security-Fixes — sofort)

```bash
# 1. SSH-Keys gitignoren
echo "githubkey" >> .gitignore
echo "githubkey.pub" >> .gitignore
echo "public/install.php" >> .gitignore
echo "start-server.bat" >> .gitignore

# 2. install.php auf dem Server löschen (nicht committen)
# → manuell auf Server via SSH/FTP

# 3. SSH-Keys rotieren falls unsicher ob bereits committed
git log --all --full-history -- githubkey  # Prüfen ob je committed
# Falls ja: git filter-branch oder BFG Repo Cleaner + force-push
```

Alle Daten sollen im Adminbereich per csv- upload einzutragen sein, bzw. wenn bereits vorhanden geupdatet werden.
---

Der Kunde soll die Möglichkeit haben normal zu bestellen (Heimdienst, Büro, Gastro, etc.) oder für eine Veranstaltung,
dann kann er auch Festinventar reservieren und wählte einen Liefertermin und Abholtermin (Zeitfenster)



## 5. Admin-Menuaufbau 
Der Adminbereich hat ein anpassbares Dashboard
| Bereich für Mitarbeiter
Emails &  Support
Aufgaben (Aufgaben können wiederholbar sein (X Tage ab erfüllung oder bestimmter tag in der Woche/monat/quartal), einen standarverantworlichen haben, bei diesem werden sie dann in seinem Dashboard angezeigt; )
Lieferanten
Kunden
Kunden können ein Stammsortiment hinterlegen, um schneller bestellen zu können.
Hier können sie auch den Mindestbestand und den aktuellen bestand pflegen und notizen pro Produkt hinterlegen.

(Kunden können andere Nutzer als Unter-Kunden anlegen. diese können Sie verschiedene Rechte geben:
Rechnungen sehen, lieferscheine sehen, bestellung machen, Stammsortiment ändern)
Kontakte (ein oder mehrere Kontake können einem oder mehreren Kunden, Lieferanten zugeordnet werden, jeweils mit ein oder mehreren Rollen (Vertrieb, Buchhaltung, Besteller, Warenannahme, Chef, Sonstiges))
Bestellungen
geplante Touren
Tourenplanung (fahrertouren)
Festinventar (wir bieten auch Festinventar zum Verleihen an, dies kann ein Kunde auch auswählen)

| Bereich für Admin
Zahlungsvrogänge
CMS (Seiten)
regelmäßige Touren
Umsatzmeldungen machen (pro Hersteller, pro Lieferant)
Newsletter
Es können verschiedene Newsletter-Gruppen angelegt werden, von denen sich die Benutzer auch per Klick abmelden können, bzw. im usermenu selbst einstellen können.
Log (alles wichtige, was passiert, mit Filtermöglichkeien und Dashboard-Ansicht)

| Dangerzone
Kassen bearbeiten
Marken bearbeiten
Hersteller (Umsatzmeldungen ja/nein, in welchem Rhytmus, an welche Emailadresse)
Lager
Gebinde
Pfand
Kategorien (Kategorien können verschachtelt sein)
Warengruppen (warengruppen können verschachtelt sein)
Kundengruppen
Dokumente (Lieferscheine, Rechnungen, Mahnungen, )
regelmäßige Touren
| More Danger Zone
Einstellungen & Config:
- APIs & Authentifications
- Emailadresse für Post-Versand
- Mahnwesen: Ab wieviel Tage drüber wird gesendet, wird automatisch gesendet, welche Zahlungserrinerungs-Templates werden wann genutzt


Benutzerrechte (hier können auch neue Benutzer angelegt werden, Rechte und Rollen geändert )
Vorlagen (Email und PDF)

Bei Mahnungen werden die Rechnungen inkl. einer Kontoübersicht gesendet.
Im 

Tourenplanung:
Es gibt die Möglichkeit, eine geplante Tour in mehrere Fahrertouren aufzuteilen:
-> Alle Kunden ab Kunde X in neue Tour
-> Alle Kunden vor Kunde X in neue Tour
-> Einzeln verschieben

Emailversand:
Wenn keine Emailadresse beim Kunden hinterlegt: Email mit PDF an auftrag@emailbrief.de senden.
Diese Emailadresse kann im Admin-Bereich angepasst werden


Kunden können:
Ihre Bestellungen und deren Status sehen
Ihre Rechnungen und deren Zahlungsstatus
Ihre Zahlungen und ihren aktuellen Kontostand.
Überzahlungen können Sie als Trinkgeld verbuchen.
Standart-Zahlungsart hinterlegen und auch online SEPA-Abbuchung zustimmen.



## 6. Benutzerrechte
Es gibt Rollen:
Admin - alles
Mitarbeiter - Mitarbeiterbereich
Kunden - nur Kundenbereich

und es gibt Rechte:
Hier kann man alles einstellen, was die einzelnen Nutzer dürfen (sehen/bearbeiten/löschen)






## 7. Technische Schulden (Inventar)

| Schuld | Schwere | Aufwand | Aktion |
|---|---|---|---|
| SSH-Keys im Projekt-Root | Kritisch | 30 min | Sofort (Schritt 0) |
| install.php nicht gitignored | Hoch | 15 min | Sofort (Schritt 0) |
| company_id bei Registrierung fehlt | Hoch | 2h | Schritt 1 |
| Tax-Fallback in InvoiceService | Hoch | 2h + Backfill | Schritt 2 |
| SupplierProduct.active Migration | Mittel | 1h | Schritt 3 |
| delivery_address_id nicht befüllt | Mittel | 3h | Schritt 4 |
| Lager-UI fehlt | Mittel | 2-3 Tage | Schritt 6 |
| Tour-Admin fehlt | Mittel | 2-3 Tage | Schritt 7 |
| PLZ-Tour-Assignment fehlt | Mittel | 1 Tag | Schritt 8 |
| Invoice Race Condition | Niedrig | 2h | Schritt 9 |
| POS-API ohne Auth | Niedrig (kein Internet-Exposure) | 4h | Schritt 10 |
| Mail synchron in finalizeInvoice | Niedrig | 2h | Schritt 11 |
| Fehlende Tests (Order-Edit, Pages, OAuth) | Niedrig–Mittel | 1 Tag | Schritte 5, 12 |
| Kein Laravel Scheduler konfiguriert | Niedrig | 2h + Hoster-Check | Parallel |
