# Shoptour2 — Zentrale KI-Übergabedatei

> Diese Datei ist so geschrieben, dass ChatGPT oder Claude damit direkt weiterarbeiten kann.  
> Stand: April 2026 | Basis: vollständige Code-Analyse

---

## 1. Kurzbeschreibung

**Shoptour2** ist die Laravel-Backend-Plattform des Kolabri-Getränkehandels — ein regionaler Heimdienst für Privat-, Büro- und Gastronomiekunden in Deutschland. Das System digitalisiert den kompletten Betrieb:

- **Webshop** (Produktkatalog, Warenkorb, Checkout, Kundenkonto)
- **Bestellverwaltung** (Bestellungen, Positionen, Status-Workflow)
- **Tourenplanung** (Fahrtouren, Gebietszuteilung, Fahrer-Zuweisung)
- **Fahrer-PWA** (mobile PWA mit IndexedDB-Offline-Queue, Foto-Upload)
- **Personalverwaltung** (Schichten, Zeiterfassung, Urlaubsanträge)
- **Lagerverwaltung** (Bestand, Wareneingänge, MHD-Tracking)
- **Einkauf** (Bestellvorschläge, Purchase Orders, Wareneingang)
- **Rechnungsstellung & Mahnwesen** (Lexoffice-Sync, automatische Mahnungen)
- **Admin-Backend** (vollständiges Management-System für alle Bereiche)
- **Kommunikation** (Gmail-Import, Ticket-System, Newsletter)

Das System läuft als **Docker-Stack** (app, nginx, mysql, scheduler) auf einem Linux-Server.

---

## 2. Tech-Stack

| Bereich | Details |
|---------|---------|
| **Framework** | Laravel 12, PHP 8.2+ |
| **Datenbank** | MySQL 8 |
| **Frontend** | Blade-Templates + Tailwind CSS v4 + Alpine.js |
| **KEIN** | Next.js, React, Vue, Redis, Horizon, Livewire |
| **Auth** | Session (Web) + Laravel Sanctum (API) + Google OAuth (Socialite) |
| **Queue** | DB-basierte `deferred_tasks`-Tabelle (kein Redis — Shared-Hosting-Herkunft) |
| **Scheduler** | Docker-Container `shoptour2-scheduler` → `php artisan schedule:run` jede Minute |
| **PDF** | barryvdh/laravel-dompdf |
| **E-Mail** | Laravel Mail via SMTP; alle Templates mit `<x-mail::message>` Wrapper |
| **Externe APIs** | Lexoffice, JTL WaWi (Push via API), Ninox, Stripe, PayPal, Google OAuth |
| **Deployment** | Docker Compose auf Linux-Server; kein Shared-Hosting mehr aktiv |
| **CSS Build** | Vite + Tailwind CSS v4 (admin CSS unter `public/admin/admin.css`) |

---

## 3. Projektregeln — zwingend einhalten

### 3.1 Geldbeträge
Immer als **Integer in Milli-Cent**: `1 EUR = 1.000.000`. Niemals Float, niemals Decimal in der DB.
```php
// Korrekt
$preis = 1000000; // 1,00 €
// Anzeige
number_format($preis / 1_000_000, 2, ',', '.') . ' €'
```

### 3.2 Steuersätze
In **Basis-Punkten**: `1900 = 19 %`, `700 = 7 %`.

### 3.3 Import-Tabellen — NIEMALS direkt bearbeiten
`wawi_*`, `ninox_*`, `lexoffice_*`, `primeur_*` sind reine Sync-Staging-Tabellen. Sie werden bei jedem Import überschrieben. Eigene Logik immer in App-Tabellen.

### 3.4 Sub-User-Auflösung
```php
// Korrekt
if ($user->isSubUser()) {
    return $user->subUser?->parentCustomer;
}
return $user->customer;
// FALSCH: nur $user->customer oder nur $user->isKunde()
```
Betrifft: `AccountController`, `CheckoutController`, `ShopController`, `FavoriteController`, `CartController`.

### 3.5 CartService — direkt injizieren
```php
$this->cart->add($productId, $qty, $user); // korrekt
// NICHT über HTTP POST /warenkorb → würde shop.order-Middleware auslösen
```

### 3.6 E-Mail-Templates
Immer `<x-mail::message>` als äußeren Wrapper (Branding, Logo, Footer automatisch).  
Niemals rohe `<!DOCTYPE html>`-Templates.

### 3.7 Feldnamen im Product-Model
| Korrekt | Falsch |
|---------|--------|
| `produktname` | `name` |
| `artikelnummer` | `sku` |
| `Storage::url($image->path)` | `$image->url` |

### 3.8 Preis-Anzeige-Modus
```php
$priceDisplayMode = $customer?->price_display_mode
    ?: ($customerGroup?->price_display_mode ?? CustomerGroup::DISPLAY_BRUTTO);
```

### 3.9 ChangeTracker
Alle Dateiänderungen via `POST http://localhost:8700/api/changes` (Projekt: `shoptour2`).  
Hook (`/srv/changetracker/hook.sh`) erfasst Edit/Write/MultiEdit automatisch.

### 3.10 Keine Migrations ausführen ohne Freigabe
Niemals `php artisan migrate` ohne explizite Bestätigung.

---

## 4. Modulübersicht

| Modul | Controller (Pfad) | Services | Status |
|-------|------------------|---------|--------|
| **Webshop – Katalog** | `Shop/ShopController` | `PriceService`, `PfandCalculator` | In Review |
| **Webshop – Warenkorb** | `Shop/CartController` | `CartService` | In Review |
| **Webshop – Checkout** | `Shop/CheckoutController` | `OrderPricingService`, `OrderService` | In Review |
| **Webshop – Kundenkonto** | `Shop/AccountController` | `InvoiceService` | In Progress |
| **Preisfindung** | (Service) | `PriceService`, `PricingCalculator` | In Review |
| **Pfand** | (Service) | `PfandCalculator`, `PfandSetService` | In Review |
| **Admin – Produkte** | `Admin/ProductController` | `ProductService` | In Progress |
| **Admin – Kunden** | `Admin/CustomerController` | `CustomerService` | In Progress |
| **Admin – Bestellungen** | `Admin/OrderController` | `OrderService`, `OrderPricingService` | In Progress |
| **Admin – Rechnungen** | `Admin/InvoiceController` | `InvoiceService`, `LexofficeService` | In Progress |
| **Admin – Touren** | `Admin/TourController` | `TourService` | In Progress |
| **Fahrer-PWA** | `Api/DriverController` | `DriverService` | Planned |
| **Admin – Dashboard** | `Admin/AdminDashboardController` | `PosStatisticsService` | In Progress |
| **Admin – Schichtplanung** | `Admin/ShiftController` | `ShiftService` | In Progress |
| **Admin – Lagerverwaltung** | `Admin/WarehouseController` | `StockService` | In Progress |
| **Admin – Einkauf** | `Admin/PurchaseOrderController` | `BestellvorschlagService` | In Review |
| **Mahnwesen** | `Admin/DunningController` | `DunningService`, `LexofficeService` | In Review |
| **Kommunikation** | `Admin/CommunicationController` | `GmailImportService`, `RuleEngineService` | In Progress |
| **Admin – Berichte** | `Admin/Statistics/*Controller` | `PosStatisticsService` | In Progress |
| **Admin – Kassenverwaltung** | `Admin/CashRegisterController` | — | Planned |
| **Admin – Stammdaten** | `Admin/CategoryController` etc. | — | In Progress |
| **Veranstaltungen/Rental** | `Admin/RentalController`, `Shop/EventController` | `RentalService` | In Review |
| **Newsletter** | `Admin/NewsletterController` | — | Planned |
| **Primeur** | `Admin/Primeur/*Controller` | — | Archiv (unklar ob aktiv) |
| **WaWi-Sync** | `Api/WawiSyncController` | `WawiSyncService` | In Review |
| **Lexoffice-Sync** | (Command) | `LexofficeService` | In Review |
| **POS-Statistiken** | `Admin/Statistics/PosController` | `PosStatisticsService` | In Progress |

---

## 5. Datenbankübersicht

**238 Tabellen gesamt** (258 Migrationsdateien)

### 5.1 Kategorien
| Kategorie | Anzahl Tabellen | Präfix |
|-----------|----------------|--------|
| App-Kerntabellen | ~146 | (keiner) |
| WaWi-Import | ~18 | `wawi_` |
| Ninox-Import | ~46 | `ninox_` |
| Lexoffice-Sync | ~11 | `lexoffice_` |
| Primeur-Archiv | ~10 | `primeur_` |
| System | ~7 | `jobs_`, `cache_`, etc. |

### 5.2 Wichtigste App-Tabellen
| Tabelle | Typ | Beschreibung |
|---------|-----|-------------|
| `products` | master | Produkt-Stammdaten (`produktname`, `artikelnummer`) |
| `product_images` | master | Produktfotos (path, sort_order) |
| `product_prices` | master | Listenpreise pro Produkt |
| `customer_prices` | master | Individuelle Kundenpreise |
| `categories` | master | Produktkategorien |
| `brands` | master | Marken/Hersteller |
| `customers` | master | Kunden-Stammdaten |
| `customer_groups` | master | Preisgruppen |
| `sub_users` | master | Unter-Benutzer von Büro-Kunden |
| `addresses` | master | Adressen (polymorphisch) |
| `orders` | transactional | Bestellungen |
| `order_items` | transactional | Bestellpositionen (Preis-Snapshot!) |
| `invoices` | transactional | Rechnungen |
| `invoice_items` | transactional | Rechnungspositionen |
| `tours` | transactional | Fahrtouren |
| `tour_stops` | transactional | Haltepunkte einer Tour |
| `shifts` | transactional | Mitarbeiter-Schichten |
| `time_entries` | transactional | Zeiterfassung |
| `vacation_requests` | transactional | Urlaubsanträge |
| `purchase_orders` | transactional | Einkaufsbestellungen |
| `purchase_order_items` | transactional | Einkaufspositionen |
| `stock_movements` | transactional | Lagerbewegungen |
| `cash_registers` | transactional | Kassenregister |
| `cash_transactions` | transactional | Kassenbuchungen |
| `deferred_tasks` | system | DB-Queue (kein Redis) |
| `communications` | operational | E-Mail-Tickets |
| `communication_rules` | operational | Auto-Klassifizierungsregeln |
| `stats_pos_daily` | aggregated | Tägliche POS-Statistiken (re-aggregiert) |
| `settings` | config | App-Einstellungen (key/value) |
| `pages` | config | CMS-Seiten (Impressum, AGB) |
| `pfand_sets` | master | Pfand-Sets (Gebinde-Pfand) |
| `rental_items` | master | Leihartikel (Veranstaltungen) |
| `warehouses` | master | Lagerorte |

### 5.3 Import-Tabellen (nur lesen)
| Präfix | Beispiele |
|--------|-----------|
| `wawi_dbo_` | `wawi_dbo_pos_bon`, `wawi_dbo_pos_bonposition`, `wawi_dbo_artikel` |
| `ninox_` | `ninox_marktbestand`, `ninox_kunden` |
| `lexoffice_` | `lexoffice_vouchers`, `lexoffice_contacts` |
| `primeur_` | `primeur_products`, `primeur_orders` |

> **Wichtig:** `wawi_dbo_*`-Tabellen werden **NICHT** durch Migrations angelegt, sondern zur Laufzeit von `DynamicSyncService::tableNameFor()` beim ersten WaWi-Sync erstellt. Sie sind **nicht** in der 238-Tabellen-Gesamtzahl enthalten. Namensschema: `"dbo.POS_BonPosition"` → `"wawi_dbo_pos_bonposition"`.

### 5.4 Tabellen ohne Eloquent-Model (bekannt)
- `stats_pos_daily` → kein Eloquent-Model; direkte Aggregation via `PosStatisticsService`
- `order_number_sequences` → kein Model (Hilfstabelle für Nummerngenerierung)

> Hinweis: `categories` → `app/Models/Catalog/Category.php`, `companies` → `app/Models/Company.php`, `product_mhd_batches` → `app/Models/Procurement/ProductMhdBatch.php`, `rental_item_categories` → `app/Models/Rental/RentalItemCategory.php` — alle haben Models.

### 5.5 Kritische Constraints
- `order_items.unit_price_net_milli` / `unit_price_gross_milli` — Preis wird beim Anlegen gespeichert, danach immutable
- `order_items.unit_deposit_milli` — Pfand je Einheit (immutable nach Anlegen)
- `company_id` — auf allen App-Tabellen vorhanden (Multi-Tenant vorbereitet)
- Milli-Cent auf allen Geldfeldern: `_milli`-Suffix (z.B. `total_gross_milli`, `unit_price_net_milli`, `unit_deposit_milli`)

---

## 6. Routen / UI-Übersicht

### 6.1 Admin (`/admin/...`)
| Bereich | Route-Prefix | Beschreibung |
|---------|-------------|-------------|
| Dashboard | `/admin/` | KPIs, Schnellzugriff, POS-Widget |
| Produkte | `/admin/products/` | CRUD, Bilder, Preise, LMIV |
| Kunden | `/admin/customers/` | CRUD, Adressen, Sub-User, Preise |
| Bestellungen | `/admin/orders/` | Liste, Detail, Status, PDF |
| Rechnungen | `/admin/invoices/` | Erstellen, Finalisieren, PDF, Lexoffice |
| Touren | `/admin/tours/` | Planung, Zuweisung, Fahrer |
| Mitarbeiter | `/admin/employees/` | CRUD, Schichten, Urlaub, Zeiterfassung |
| Schichten | `/admin/shifts/` | Wochenplan, Schichttausch |
| Lagerverwaltung | `/admin/warehouse/` | Bestand, Wareneingänge, MHD |
| Einkauf | `/admin/purchase-orders/` | PO-Erstellung, Wareneingänge |
| Mahnwesen | `/admin/dunning/` | Mahnliste, Zahlungsabgleich |
| Kommunikation | `/admin/communications/` | Gmail-Tickets, Regeln |
| Berichte | `/admin/statistics/` | POS, Artikel, MHD, Pfand |
| Kassenverwaltung | `/admin/cash-registers/` | Kassenbuch, Abschluss |
| Stammdaten | `/admin/categories/`, `/admin/brands/` etc. | Kategorien, Marken, Lieferanten |
| Einstellungen | `/admin/settings/` | System, E-Mail, API-Keys |
| Veranstaltungen | `/admin/events/`, `/admin/rentals/` | Event-Bestellungen, Leihinventar |

### 6.2 Shop (`/...`)
| Bereich | Route | Beschreibung |
|---------|-------|-------------|
| Startseite | `/` | Produktkatalog (Browse) |
| Produktdetail | `/produkt/{slug}` | Produktseite |
| Warenkorb | `/warenkorb` | Cart (Session) |
| Checkout | `/checkout/` | Adresse, Zahlung, Bestätigung |
| Kundenkonto | `/mein-konto/` | Dashboard, Bestellhistorie, Adressen |
| Auth | `/login`, `/register` | E-Mail + Google OAuth |

### 6.3 API (`/api/...`)
| Endpoint | Beschreibung |
|---------|-------------|
| `POST /api/sync` | WaWi-Push (Token: `WAWI_SYNC_TOKEN`) |
| `POST /api/lexoffice/webhook` | Lexoffice-Webhooks |
| `/api/driver/...` | Fahrer-PWA (Bearer-Token via Sanctum) |
| `/api/payments/...` | Stripe/PayPal Webhooks |

---

## 7. Feature-Status (Stand April 2026)

**37 Features geplant** | **Features-Verzeichnis:** `features/PROJ-X-name.md`

### In Review (nahezu fertig)
| ID | Feature |
|----|---------|
| PROJ-1 | Authentifizierung (E-Mail + Google OAuth) |
| PROJ-2 | Produktkatalog (Browse, Filter, Suche) |
| PROJ-3 | Warenkorb (Gast + Auth) |
| PROJ-4 | Checkout (Heimdienst, Abholung, Bestätigung) |
| PROJ-22 | Veranstaltungen & Rental (Leihinventar) |
| PROJ-31 | Mahnwesen (automatisch, Lexoffice-Sync) |
| PROJ-32 | Einkauf (PurchaseOrders, Workflow) |

### In Progress (teilweise)
| ID | Feature |
|----|---------|
| PROJ-5 | Kundenkonto (Dashboard, Bestellhistorie, Rechnungen) |
| PROJ-6 | Preisfindung (3-stufig) |
| PROJ-7 | Pfand-System |
| PROJ-9 | Admin: Stammdaten |
| PROJ-10 | Admin: Kundenverwaltung |
| PROJ-11 | Admin: Lieferanten |
| PROJ-12 | Admin: Bestellverwaltung |
| PROJ-13 | Admin: Rechnungen |
| PROJ-14 | Admin: Touren & Liefergebiete |
| PROJ-15 | Admin: Fahrertouren-Planung |
| PROJ-17 | Admin: Dashboard |
| PROJ-23 | Admin: Lagerverwaltung |
| PROJ-25 | Admin: Berichte & Reports |
| PROJ-27 | Admin: Newsletter |
| PROJ-29 | Admin: E-Mails & Support |

### Planned (nicht begonnen)
| ID | Feature |
|----|---------|
| PROJ-8 | Zahlungsabwicklung (Stripe, PayPal vollständig) |
| PROJ-16 | Fahrer-PWA (vollständig, Offline-Sync) |
| PROJ-18 | Admin: Benutzer & Rollen-UI |
| PROJ-19 | Admin: Einstellungen-UI |
| PROJ-35 | Admin: Kassenverwaltung (vollständig) |
| PROJ-36 | Admin: Schichtplanung-UI |

---

## 8. Baustellen & Risiken

### Kritisch
| Problem | Auswirkung | Empfehlung |
|---------|-----------|------------|
| **Keine Policies** (`app/Policies/` leer) | Keine modell-seitige Authorization; `AdminOrderController` und `AdminInvoiceController` zeigen Daten **aller Mandanten** ohne `company_id`-Filter — nur `AdminCustomerController` filtert korrekt via `app('current_company')` | `make:policy` für Order, Invoice, Customer, Product; `where('company_id', $company->id)` in den betroffenen Controllern ergänzen |
| **WaWi BonPosition-Sync** unzuverlässig | `stats_pos_daily` veraltet, Dashboard zeigt falschen Umsatz; Monitoring via `GET /api/sync/state` (Entity-Key: `"dbo.POS_BonPosition"`) | Differenz `last_ts` zu `now()` > 24 h → Alert; WaWi-seitig Push-Script prüfen |
| **Lexoffice API-Timeouts** | `lexoffice:import-payments` schlägt gelegentlich fehl | Retry-Logik in `LexofficeService` ausbauen |

### Mittel
| Problem | Details |
|---------|---------|
| **Fehlende Models** | `stats_pos_daily`, `order_number_sequences` — kein Eloquent-Model; Raw DB via Services |
| **Artikelnummern-Duplikate** | 12 Produkte mit falschen `N{ninox_id}`-Nummern blockieren WaWi-UPDATE |
| **Sub-User-Muster** | Nicht überall konsequent — in neuen Controllern leicht vergessen |
| **CompanyScope fehlt** | `company_id` überall vorhanden, aber kein GlobalScope aktiv |

### Niedrig
| Problem | Details |
|---------|---------|
| **Primeur-Modul unklar** | Archivdaten migriert, Views vorhanden — aktiv oder deprecated? |
| **POS-Daten nur JTL** | Webshop-Bestellungen nicht in POS-Statistiken |
| **deferred_tasks** | Keine persistente Queue — Ausfall des Schedulers = keine E-Mails |

---

## 9. TODOs nach Priorität

### P0 — Sofort (laufender Betrieb)
- [ ] WaWi: BonPosition-Sync monitoring einrichten (Alert bei Lücke)
- [ ] Lexoffice: Retry-Logik für Timeouts (`LexofficeService::importPayments()`)
- [ ] Artikelnummern-Duplikate bereinigen (12 Produkte, SQL vorhanden)

### P1 — Kurzfristig (1–4 Wochen)
- [ ] **PROJ-5**: Kundenkonto fertigstellen (Dashboard, Bestellhistorie, Rechnungen)
- [ ] **PROJ-8**: Zahlungsabwicklung (Stripe Webhook-Handler, Payment-Status-Sync)
- [ ] Policies erstellen: `Order`, `Invoice`, `Customer`, `Product`
- [ ] `AdminOrderController` + `AdminInvoiceController`: `where('company_id', ...)` ergänzen

### P2 — Mittelfristig (1–2 Monate)
- [ ] **PROJ-17**: Admin-Dashboard KPI-Tiles (Umsatz, offene Rechnungen, Tour-Übersicht)
- [ ] **PROJ-16**: Fahrer-PWA vollständig (Offline-Sync, PoD-Foto stabil, Kassenentnahme)
- [ ] **PROJ-36**: Schichtplanung-UI (Wochensicht, Schichttausch)
- [ ] **PROJ-35**: Kassenverwaltung (Kassenbuch-Export, Tagesabschluss)
- [ ] **PROJ-18**: Rollen & Berechtigungen UI

### P3 — Langfristig (2–6 Monate)
- [ ] Multi-Tenant aktivieren (CompanyScope als GlobalScope)
- [ ] Primeur-Modul: Entscheidung (aktiv weiterführen oder deprecated markieren)
- [ ] Bestellvorschläge automatisieren (wöchentlich per E-Mail)
- [ ] KI-Kommunikationszuordnung (RuleEngineService + ML)

---

## 10. Folgeprompts für KI-Assistenten

### Neues Feature implementieren
```
Ich arbeite an Shoptour2 (Laravel 12, PHP 8.2, MySQL).
Lies zuerst CLAUDE.md und docs/AI_HANDOVER_SUMMARY.md.
Aufgabe: [Feature beschreiben]
Betroffene Dateien: [Pfade]
Beachte: Milli-Cent, Sub-User-Auflösung, keine Policies-Lücken.
```

### Bug fixen
```
Shoptour2-Bug in [Datei:Zeile]:
[Fehlerbeschreibung]
Kontext: Geldbeträge in Milli-Cent, company_id auf allen Tabellen.
Zeige den Fix direkt ohne Umstrukturierung.
```

### Admin-Seite erstellen
```
Erstelle eine neue Admin-Seite für [Funktion] in Shoptour2.
Stack: Blade + Tailwind v4 + Alpine.js. Layout: @extends('admin.layout').
Kein React, kein Livewire. Pattern: bestehende Seiten in resources/views/admin/ folgen.
```

### Dashboard-Widget
```
Füge ein Dashboard-Widget in Shoptour2 hinzu.
Datei: AdminDashboardController.php + resources/views/admin/dashboard.blade.php.
Daten aus: [Tabelle/Service].
Stil: Sparkline mit inline CSS-Balken (kein Chart.js).
```

---

## 11. Fragen an Projektinhaber

Diese Fragen sind für Entscheidungen offen, die den weiteren Entwicklungsplan beeinflussen:

1. **Primeur-Modul**: Wird das Primeur-Weinsortiment aktiv weitergeführt, oder kann das Modul als deprecated markiert werden?

2. **Multi-Tenant**: Wann soll der zweite Mandant (zweite `company_id`) live gehen? Das beeinflusst, wann CompanyScope aktiviert werden muss.

3. **Zahlungsarten**: Welche Zahlungsarten sollen im Webshop-Checkout prioritär sein? Stripe + PayPal stehen bereit — soll SEPA-Lastschrift auch über Stripe laufen?

4. **Fahrer-PWA**: Soll die PWA auf iOS Safari unterstützt werden? Das schränkt IndexedDB-Verhalten und SW-Lifecycle ein.

5. **POS-Statistiken**: Sollen Webshop-Bestellungen in die POS-Statistiken einfließen, oder bleiben POS (JTL) und Webshop strikt getrennt?

6. **Newsletter**: Eigener Versand über Laravel Mail, oder Integration mit Mailchimp/Brevo?

7. **Lagerverwaltung**: Wird Ninox weiterhin als Lager-Master genutzt, oder soll Shoptour2 die Lagerhaltung vollständig übernehmen?

---

*Stand: 2026-04-28 | Projekt: shoptour2 | Basis: vollständige Code-Analyse*
