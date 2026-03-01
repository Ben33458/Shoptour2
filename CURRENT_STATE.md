# CURRENT_STATE.md — Kolabri Getränkeshop
Stand: 2026-02-28 | Branch: initial-code-review | Laravel 12, PHP 8.2+, MySQL

---

## 1. Executive Summary

### Was funktioniert heute wirklich
- **Kompletter Shop-Flow:** Produktliste → Warenkorb (Session, Gast+Auth) → Checkout (Adress-Auswahl) → Bestellbestätigung
- **Kundenregistrierung:** E-Mail/Passwort + Google OAuth (Socialite), mit Adresserfassung bei Registrierung
- **Admin: Bestellverwaltung** inkl. Status, Positionen bearbeiten (WP-22), Leergut/Bruch-Closeout
- **Admin: Rechnungen** (Draft → Finalize → PDF via DomPDF) + Lexoffice-Sync (nicht-blockierend)
- **Stammdaten:** Brand, Produktlinie, Kategorie, Gebinde, Pfand-System (PfandItem, PfandSet, rekursiv)
- **Kunden & Lieferanten** CRUD mit Kontakten (polymorph) und CSV-Import
- **Produkt-Verwaltung:** Artikelstamm, LMIV-Versioning (WP-15), Produktbilder (WP-21), Barcodes
- **3-stufige Preisfindung:** Kunden-Preis → Gruppen-Preis → Basispreis ± Gruppenanpassung (ganzzahlige Integer-Arithmetik, kein Float)
- **Pfand-Kalkulation:** Rekursiver PfandSet-Baum mit Zyklusschutz, milli-cent Integer
- **Auslieferungstouren + Fahrer-PWA:** RegularDeliveryTour → konkrete Tour → TourStop, Offline-Sync via Driver-API
- **Lager/Bestandsführung:** Modelle + StockService vorhanden (kein Admin-UI)
- **Berichte + CSV-Export:** Umsatz, Marge, Pfand, Tour-KPIs (WP-16)
- **Integrations:** Lexoffice (WP-17), Stripe Checkout + Webhook (WP-17), E-Mail-Benachrichtigungen
- **CMS-Seiten:** Admin-Editor + Shop-Anzeige (Impressum, AGB etc.)
- **CSV-Imports:** Kunden, Produkte, Lieferanten, Marken, Kundengruppen, LMIV

### Was ist teilweise fertig
- **Bestellungen bearbeiten (WP-22):** Controller + View vorhanden, letzte größere Bugfixes gerade eingespielt; Vollständigkeit der Randfall-Behandlung unklar
- **POS-API:** Endpunkte (`GET /api/pos/products`, `POST /api/pos/sale`) implementiert, kein Frontend / Kassen-UI
- **Einkauf (PurchaseOrders):** Modelle, Migrationen, SupplierProduct vorhanden — kein Admin-UI, kein Workflow

### Was fehlt komplett
- **Lager-Admin-UI:** Warehouse/Stock/StockMovement haben kein Admin-Interface
- **Einkauf-Admin-UI:** PurchaseOrder hat kein UI, kein Bestellworkflow
- **Tour-Admin-UI:** RegularDeliveryTour und DeliveryArea haben kein CRUD im Admin (nur lesbar via Driver-Seite)
- **Kassen-Frontend (POS):** API bereit, UI fehlt vollständig
- **Multi-Company-UI:** company_id existiert in fast allen Tabellen, aber kein Mandanten-Verwaltungs-UI

### Größte Risiken / Blocker
1. **KRITISCH:** `githubkey` + `githubkey.pub` im Working Directory — wenn committed, sofortige Key-Rotation nötig
2. **HOCH:** `public/install.php` mit hardcoded Token + Shell-Ausführung — darf nicht in Git; muss nach Deployment gelöscht werden
3. **MITTEL:** `customers.company_id` bei Selbstregistrierung nicht gesetzt — Inkonsistenz mit company-Middleware
4. **MITTEL:** `InvoiceService.php:117` — stiller Fallback `?? 190_000` (19 % MwSt.) bei fehlendem Steuersatz
5. **MITTEL:** `SupplierProduct.active`-Filter in InvoiceService ggf. ohne Migrationsbasis

---

## 2. Feature-Inventar (Tabelle)

| Modul | Beschreibung | Status | Einstiegspunkte | Kernklassen | DB-Tabellen | Tests |
|---|---|---|---|---|---|---|
| Shop: Produktliste | Filterbare Produktübersicht + Detailseite | **Fertig** | `GET /`, `GET /produkte/{product}` | `ShopController`, `CartService` | `products`, `brands`, `categories` | `ShopProductListTest` |
| Shop: Warenkorb | Session-basiert, Gast + Auth | **Fertig** | `GET/POST/PATCH/DELETE /warenkorb` | `CartController`, `CartService` | — (Session) | `CartTest` |
| Shop: Checkout | Auth, Adress-Auswahl, OrderService | **Fertig** | `GET/POST /kasse` | `ShopCheckoutController`, `OrderService`, `OrderPricingService` | `orders`, `order_items` | `CheckoutTest` |
| Shop: Bestellbestätigung | Erfolgsseite nach Checkout | **Fertig** | `GET /bestellung/{order}/abgeschlossen` | `ShopCheckoutController` | — | `CheckoutTest` |
| Kundenkonto | Dashboard, Bestellhistorie, Adressen | **Fertig** | `/mein-konto/*` | `AccountController` | `customers`, `addresses`, `orders` | `AccountAddressTest` |
| Registrierung E-Mail | Formular + User+Customer+Adresse anlegen | **Fertig** | `GET/POST /register` | `RegisterController` | `users`, `customers`, `addresses` | `RegisterTest` |
| Registrierung Google | OAuth via Socialite | **Fertig** | `GET /auth/google`, `/auth/google/callback` | `SocialController` | `users` (google_id) | — |
| Admin: Bestellungen | Liste, Detail, Status, Positionen bearbeiten | **Teilweise** | `GET /admin/orders`, `/admin/orders/{order}/edit` | `AdminOrderController` | `orders`, `order_items` | — |
| Admin: Leergut/Bruch | Closeout nach Lieferung | **Fertig** | `GET/POST /admin/orders/{order}/closeout` | `AdminCloseoutController` | `order_adjustments` | — |
| Admin: Rechnungen | Draft, Finalize, PDF, Download | **Fertig** | `/admin/invoices`, `/admin/orders/{order}/invoice` | `AdminInvoiceController`, `InvoiceService` | `invoices`, `invoice_items` | `InvoiceFinalizeTest` |
| Admin: Stammdaten | Brand, Produktlinie, Kat., Gebinde, Pfand | **Fertig** | `/admin/brands`, `/admin/gebinde`, `/admin/pfand-*` | `AdminBrand/Category/Gebinde/PfandItem/PfandSet`Controller | `brands`, `gebinde`, `pfand_items`, `pfand_sets`, `pfand_set_components` | `PfandSetTest`, `InlineEditBrandTest` |
| Admin: Kundengruppen | CRUD + Default setzen | **Fertig** | `/admin/customer-groups` | `AdminCustomerGroupController` | `customer_groups` | `DefaultCustomerGroupTest` |
| Admin: Kunden | CRUD + Kontakte + Adressen | **Fertig** | `/admin/customers` | `AdminCustomerController` | `customers`, `addresses`, `contacts` | `CustomerContactsTest`, `CustomerPhoneTest` |
| Admin: Lieferanten | CRUD + Kontakte + Lieferanten-Nr | **Fertig** | `/admin/suppliers` | `AdminSupplierController` | `suppliers`, `contacts` | `SupplierContactsTest` |
| Admin: Produkte | CRUD, Basis-Item, Barcodes, Bilder | **Fertig** | `/admin/products` | `AdminProductController`, `AdminProductImageController` | `products`, `product_images`, `product_barcodes` | `InlineEditProductTest`, `ProductImageTest` |
| Admin: LMIV | Versioning, EAN, Nährwerte, Allergene | **Fertig** | `/admin/products/{product}/lmiv` | `AdminLmivController`, `LmivVersioningService` | `product_lmiv_versions` | `LmivVersioningTest` |
| Admin: CSV-Imports | Kunden, Produkte, Lieferanten, Brands, Kundengruppen, LMIV | **Fertig** | `/admin/imports/*` | `Admin*ImportController`, `*CsvImporter` | — (Insert in Ziel-Tabellen) | `CustomerCsvImportTest`, `LmivCsvImportTest` |
| Preisfindung | 3-stufig: Kunde → Gruppe → Basis | **Fertig** | (intern) | `PriceResolverService`, `EloquentPricingRepository` | `customer_prices`, `customer_group_prices` | `PriceResolverServiceTest` |
| Pfand-Kalkulation | Rekursiver PfandSet-Baum, Integer | **Fertig** | (intern, via OrderPricingService) | `PfandCalculator` | `pfand_sets`, `pfand_set_components`, `pfand_items` | (via `OrderPricingServiceTest`) |
| Order-Pricing | Snapshot: Preis + Tax + Pfand eingefroren | **Fertig** | (intern) | `OrderPricingService` | `order_items` | `OrderPricingServiceTest` |
| Order-Service | Bestellung erstellen, Backorder, Bundles | **Fertig** | (via CheckoutController) | `OrderService` | `orders`, `order_items`, `order_item_components` | `OrderServiceTest` |
| Touren-Planung | RegularTour → konkrete Tour + Stops | **Fertig** | (intern) | `TourPlannerService` | `tours`, `tour_stops`, `regular_delivery_tours` | `TourPlannerServiceTest` |
| Fulfillment | Stop-Status, ItemFulfillment | **Fertig** | (intern) | `FulfillmentService` | `order_item_fulfillments`, `fulfillment_events` | `FulfillmentServiceTest` |
| Fahrer-PWA | Shell + Offline-Sync via API | **Fertig** | `GET /driver`, `/api/driver/*` | `DriverBootstrapController`, `DriverSyncController`, `DriverSyncService` | `driver_events`, `driver_uploads`, `driver_api_tokens` | `DriverSyncServiceTest`, `DriverFulfillmentTest` |
| Lager | Warehouse, Stock, Movements | **Teilweise** | — (kein Admin-UI) | `StockService` | `warehouses`, `product_stocks`, `stock_movements` | `StockServiceTest` |
| Berichte | Umsatz, Marge, Pfand, Tour-KPIs + CSV-Export | **Fertig** | `/admin/reports` | `AdminReportController`, `*ReportService`, `TourKpiService` | (JOIN über mehrere Tabellen) | `RevenueReportTest`, `MarginReportTest`, `TourKpiTest`, `ReportCsvExportTest` |
| Lexoffice | API-Client + Rechnungs-Sync | **Fertig** | `/admin/integrations/lexoffice` | `AdminIntegrationController`, `LexofficeSync`, `LexofficeClient` | `invoices` (lexoffice_voucher_id) | `LexofficeSyncTest` |
| Stripe | Checkout-Redirect + Webhook | **Fertig** | `/payments/checkout/{invoice}`, `/api/payments/webhook/stripe` | `CheckoutController`, `WebhookController`, `StripeProvider` | `payments` | `StripeWebhookTest` |
| E-Mails | Bestellbestätigung, Rechnung, Zahlungserinnerung | **Fertig** | (via Events / direkt in Services) | `InvoiceAvailable`-Mailable | — | `EmailTest` |
| CMS-Seiten | Admin-Editor + Shop-Anzeige | **Fertig** | `GET /seite/{slug}`, `/admin/pages` | `PageController`, `AdminPageController` | `pages` | — |
| Driver-Token-Verwaltung | API-Tokens für Fahrer anlegen/widerrufen | **Fertig** | `/admin/driver-tokens` | `AdminDriverTokenController` | `driver_api_tokens` | `DriverTokenSecurityTest` |
| Deployment-Helfer | Migrate, Cache, Backup via Admin-UI | **Fertig** | `/admin/deploy` | `AdminDeployController` | — | — |
| Diagnostics | Health-Checks + System-Infos | **Fertig** | `/admin/diagnostics`, `/health/db`, `/health/storage` | `AdminDiagnosticsController`, `HealthController` | — | `DiagnosticsTest` |
| Deferred Tasks | Asynchrone Aufgaben (DB-basiert) | **Fertig** | `/admin/tasks` | `AdminTasksController` | `deferred_tasks` | `DeferredTaskRunnerTest` |
| POS-API | Barcode-Suche + Sofortverkauf | **Platzhalter** | `/api/pos/products`, `/api/pos/sale` | `PosProductController`, `PosSaleController` | `orders` (immediate_payment) | `PosApiTest` |
| Einkauf | PurchaseOrders, SupplierProducts | **Platzhalter** | — (kein UI) | — | `purchase_orders`, `purchase_order_items`, `supplier_products` | `PurchaseOrderTest` |
| Multi-Company | company_id-Scoping aller Entitäten | **Platzhalter** | (Middleware `company`) | — | company_id auf allen Haupt-Tabellen | `CompanyScopingTest` |

---

## 3. Datenmodell

### 3.1 Tabellenübersicht

| Tabelle | Zweck | Wichtige Felder | Beziehungen | Auffälligkeiten |
|---|---|---|---|---|
| `users` | Auth-Benutzer | id, email, password, role (admin/mitarbeiter/kunde), google_id, avatar_url | HasOne: Customer | Roles direkt im Model, kein Permissions-System |
| `customers` | Kundenstamm | id, user_id, customer_group_id, customer_number, lexoffice_contact_id, price_display_mode, first_name, last_name, email, phone, active, company_id | BelongsTo: User, CustomerGroup; HasMany: Address, Order | company_id bei Selbstregistrierung nicht gesetzt! |
| `customer_groups` | Preisgruppen | id, name, price_adjustment_type (none/fixed/percent), price_adjustment_fixed_milli, price_adjustment_percent_basis_points, is_business, is_deposit_exempt, active | HasMany: Customer, CustomerGroupPrice | is_deposit_exempt steuert Pfand-Berechnung |
| `addresses` | Lieferadressen | id, customer_id, type, first_name, street, house_number, zip, city, country_code, phone, is_default | BelongsTo: Customer | Ersetzt altes address-Feld auf customers-Tabelle |
| `products` | Produktstamm | id, brand_id, product_line_id, category_id, gebinde_id, tax_rate_id, artikelnummer, produktname, base_price_net_milli, base_price_gross_milli, is_bundle, availability_mode, active, is_base_item, base_item_product_id | BelongsTo: Brand, ProductLine, Category, Gebinde, TaxRate; HasMany: ProductImage, ProductBarcode, ProductLmivVersion | availability_mode: 'always_available' oder 'stock_based' |
| `brands` | Markenstamm | id, name | HasMany: ProductLine, Product | — |
| `product_lines` | Produktlinien | id, brand_id, name, gebinde_pfand_id, gebinde_gebinde_id | BelongsTo: Brand | Pfand- und Gebinde-Defaults pro Linie |
| `categories` | Kategorien | id, name | HasMany: Product | — |
| `gebinde` | Verpackungseinheiten | id, name, qty, is_standardisiert, pfand_set_id | HasMany: GebindeComponent, Product | Rekursiv über gebinde_components |
| `pfand_items` | Einzelne Pfandwerte | id, name, wert_brutto_milli | — | Basis-Element des Pfand-Baums |
| `pfand_sets` | Pfand-Gruppierungen | id, name | HasMany: PfandSetComponent | Rekursiver Baum via PfandSetComponent |
| `pfand_set_components` | Pfand-Baum-Verbindungen | id, pfand_set_id, pfand_item_id (nullable), child_pfand_set_id (nullable), qty | BelongsTo: PfandSet, PfandItem, childPfandSet | isLeaf() vs isNestedSet() |
| `tax_rates` | MwSt.-Sätze | id, rate_basis_points, name, active | HasMany: Product | 190_000 = 19%, 70_000 = 7% |
| `orders` | Bestellköpfe | id, customer_id, customer_group_id_snapshot, status, delivery_date, warehouse_id, regular_delivery_tour_id, delivery_address_id, has_backorder, total_net_milli, total_gross_milli, total_pfand_brutto_milli, company_id | BelongsTo: Customer; HasMany: OrderItem; HasOne: TourStop | Totals = Summe der Items; Snapshot des Preises bei Bestellung |
| `order_items` | Bestellpositionen | id, order_id, product_id, unit_price_net_milli, unit_price_gross_milli, price_source, tax_rate_id, tax_rate_basis_points, pfand_set_id, unit_deposit_milli, qty, is_backorder, product_name_snapshot, artikelnummer_snapshot, lmiv_version_id | BelongsTo: Order, Product | Alle Preise eingefroren (Snapshot) |
| `order_item_components` | Bundle-Bestandteile | id, order_item_id, component_product_id, component_product_name_snapshot, component_artikelnummer_snapshot, qty_per_bundle, qty_total | BelongsTo: OrderItem | Aufgelöste Bundle-Hierarchie |
| `order_adjustments` | Leergut/Bruch-Anpassungen | id, order_id, adjustment_type, amount_milli, qty, reference_label, note | BelongsTo: Order | Eingang in Rechnungs-Draft als TYPE_ADJUSTMENT |
| `invoices` | Rechnungsköpfe | id, order_id, company_id, invoice_number, status (draft/finalized), total_net_milli, total_gross_milli, total_tax_milli, total_adjustments_milli, total_deposit_milli, pdf_path, lexoffice_voucher_id, finalized_at | BelongsTo: Order; HasMany: InvoiceItem, Payment | Unveränderlich nach Finalize |
| `invoice_items` | Rechnungspositionen | id, invoice_id, order_item_id, adjustment_id, line_type (product/deposit/adjustment), description, qty, unit_price_net_milli, unit_price_gross_milli, tax_rate_basis_points, line_total_net_milli, line_total_gross_milli, cost_milli | — | cost_milli für Margen-Reporting |
| `payments` | Zahlungen | id, invoice_id, amount_milli, payment_date, provider (stripe/manual), provider_transaction_id | BelongsTo: Invoice | — |
| `regular_delivery_tours` | Wiederkehrende Touren-Templates | id, company_id, name, frequency, postal_code_range, min_order_value_milli | HasMany: Tour, DeliveryArea, CustomerTourOrder | — |
| `tours` | Konkrete Touren-Instanzen | id, tour_date, regular_delivery_tour_id, driver_employee_id, status (planned/in_progress/done/cancelled) | BelongsTo: RegularDeliveryTour; HasMany: TourStop | — |
| `tour_stops` | Haltepunkte einer Tour | id, tour_id, order_id, stop_index, status | BelongsTo: Tour, Order; HasMany: OrderItemFulfillment | — |
| `order_item_fulfillments` | Ausgelieferte Mengen | id, tour_stop_id, order_item_id, delivered_qty, not_delivered_qty, reason | BelongsTo: TourStop, OrderItem | Basis für Rechnungs-Draft |
| `driver_api_tokens` | Fahrer-API-Tokens | id, token_hash, driver_name, is_active, last_used_at | — | Keine Benutzer-Bindung |
| `driver_events` | Offline-Sync-Events | id, driver_api_token_id, tour_id, event_type, event_data (JSON) | BelongsTo: DriverApiToken | — |
| `warehouses` | Lagerstamm | id, company_id, name, location | HasMany: ProductStock | Kein Admin-UI |
| `product_stocks` | Aktuelle Bestände | id, product_id, warehouse_id, qty, reorder_point | BelongsTo: Product, Warehouse | Kein Admin-UI |
| `stock_movements` | Lagerbewegungsjournal | id, product_id, warehouse_id, qty_change, movement_type, reference_type, reference_id | — | Kein Admin-UI |
| `suppliers` | Lieferantenstamm | id, company_id, lieferanten_nr, name, contact_name, email, phone, address, currency, active | HasMany: SupplierProduct, PurchaseOrder | — |
| `supplier_products` | Lieferanten-Sortiment | id, supplier_id, product_id, supplier_article_number, purchase_price_milli, lead_days, moq | — | Wird in InvoiceService für cost_milli genutzt |
| `purchase_orders` | Einkaufsbestellungen | id, supplier_id, po_number, status, total_milli, delivery_date | HasMany: PurchaseOrderItem | Kein Admin-UI |
| `customer_prices` | Kundenspezifische Preise | id, customer_id, product_id, valid_from, valid_to, price_net_milli | — | Höchste Priorität in Preisfindung |
| `customer_group_prices` | Gruppenpreise | id, customer_group_id, product_id, valid_from, valid_to, price_net_milli | — | Zweite Priorität |
| `app_settings` | Key-Value Konfiguration | id, key, value | — | default_customer_group_id, lexoffice.enabled |
| `audit_logs` | Audit-Trail | id, user_id, model_type, model_id, action, changes (JSON) | — | — |
| `deferred_tasks` | Async-Aufgaben | id, status, payload, error_message, scheduled_at, completed_at | — | DB-basierte Queue |
| `product_lmiv_versions` | LMIV-Versionen (Lebensmittelinformation) | id, product_id, version_number, status (draft/active), energy_kcal, energy_kj, nutrients (JSON), allergens (JSON) | BelongsTo: Product | Versioning-System mit draft/active |
| `product_images` | Produktbilder | id, product_id, file_path, sort_order, alt_text | BelongsTo: Product | — |
| `product_barcodes` | EAN/Barcodes | id, product_id, barcode_value, barcode_type, valid_from, valid_to | BelongsTo: Product | Zeitlich gültig |
| `pages` | CMS-Seiten | id, slug, title, content (HTML) | — | Neu (Feb 2026) |
| `contacts` | Ansprechpartner | id, contactable_type, contactable_id, name, email, phone, sort_order | Polymorph: Customer, Supplier | — |
| `companies` | Mandanten | id, name, email, phone, address | — | Multi-Tenancy-Basis |

### 3.2 Pfand/Gebinde-Logik (Ist)

**Definitionen:**
- `PfandItem`: atomarer Pfandwert (z.B. "Kasten-Pfand" = 1,50 EUR = 1_500_000 milli-cents, brutto)
- `PfandSet`: benannte Gruppe von PfandItems und/oder verschachtelten PfandSets
- `PfandSetComponent`: Verbindung mit `qty`; entweder `pfand_item_id` gesetzt (Blatt) oder `child_pfand_set_id` (verschachteltes Set)
- `Gebinde`: Verpackungseinheit verknüpft mit einem PfandSet via `pfand_set_id`

**Berechnungsstellen:**
1. `PfandCalculator::totalForGebinde(Gebinde)` → rekursiv über PfandSet-Baum, gibt milli-cents (brutto) zurück; Zyklusschutz via `$visited`-Array
2. `OrderPricingService::resolvePfandSnapshot(Product)` → liest Gebinde, ruft PfandCalculator auf; gibt `[$pfandSetId, $unitDepositMilli]` zurück
3. `OrderService::createOrder()` → akkumuliert `total_pfand_brutto_milli` auf dem Order-Header
4. `InvoiceService::generateDraft()` → liest `order.total_pfand_brutto_milli`, fügt Zeile TYPE_DEPOSIT ein (ohne MwSt.)
5. `AdminCloseoutController` / `OrderAdjustment` → Leergut-Rücknahme als negative Anpassung

**Offene Fragen / Risiken:**
- Pfand-Rückgabe ist manuell (OrderAdjustment) — kein automatischer Abgleich mit ursprünglicher Pfandmenge pro Tour
- `CustomerGroup.is_deposit_exempt`: Exempt-Kunden bekommen keinen Pfand; wenn Kunde nachträglich auf exempt gesetzt wird, sind bestehende Rechnungen nicht angepasst
- Deposit-Zeile auf Rechnung hat `tax_rate_basis_points = 0` — korrekt für Pfand in DE (kein separater MwSt.-Ausweis)

---

## 4. APIs / Schnittstellen

| Endpoint/Route | Auth | Zweck | Request/Response | Owner-Klassen | Probleme |
|---|---|---|---|---|---|
| `GET /api/driver/bootstrap` | DriverApiToken (Bearer) | Tour-Daten + Stops für Fahrer-PWA herunterladen | Response: Tour + Stops JSON | `DriverBootstrapController` | Rate-Limit: 120/min |
| `POST /api/driver/sync` | DriverApiToken | Offline-Events (arrived/finished/skipped/note) einreichen | Body: `{events: [...]}` | `DriverSyncController`, `DriverSyncService` | Rate-Limit: 60/min |
| `POST /api/driver/upload` | DriverApiToken | Proof-of-Delivery-Foto hochladen | Multipart | `DriverUploadController` | Rate-Limit: 60/min |
| `GET /api/products/{id}` | Keine | Produkt-Detail + aktive LMIV-Version | Response: Produkt-JSON | `ShopProductController` | — |
| `GET /api/pos/products` | Keine (!) | Barcode-Suche für Kassen-System | `?barcode=` oder `?q=` | `PosProductController` | Kein Auth — sollte langfristig API-Key |
| `POST /api/pos/sale` | Keine (!) | Sofortverkauf (Order + sofortige Zahlung) | Body: `{items, payment}` | `PosSaleController` | Kein Auth — MVP-Zustand |
| `POST /api/payments/webhook/stripe` | Stripe-Signatur (HMAC) | Stripe-Zahlung bestätigen | Stripe Event Payload | `WebhookController`, `StripeProvider` | Signatur-Validation implementiert |

---

## 5. Hintergrundverarbeitung

**Jobs / Queues:**
- Kein reguläres Laravel-Queue-System aktiv (Shared-Hosting-Einschränkung)
- DB-basierte `deferred_tasks`-Tabelle als eigenes Async-System (WP-18)
- Lexoffice-Sync läuft synchron in `InvoiceService::finalizeInvoice()` — nicht-blockierend per try/catch

**Events / Listeners:**
- Kein Event/Listener-System in Verwendung (direkte Service-Aufrufe)

**Cron / Tasks:**
- `php artisan deferred-tasks:run` — verarbeitet ausstehende deferred_tasks
- `php artisan cleanup:*` — Cleanup-Befehle (WP-18)
- `php artisan doctor` — System-Diagnose (WP-18)
- Kein Laravel Scheduler konfiguriert (Shared-Hosting: kein Cron-Zugang dokumentiert)

---

## 6. Tests

**Überblick:** ~50 Test-Dateien, ca. 200+ Tests

| Bereich | Dateien | Abdeckung |
|---|---|---|
| Unit: Pricing | `PriceResolverServiceTest` | PriceResolverService vollständig |
| Feature: Orders | `OrderServiceTest`, `OrderPricingServiceTest` | Order-Erstellung, Pricing-Snapshot |
| Feature: Delivery | `FulfillmentServiceTest`, `TourPlannerServiceTest` | Tour-Generierung, Fulfillment-Workflow |
| Feature: Driver | `DriverSyncServiceTest`, `DriverFulfillmentTest`, `DriverUploadControllerTest`, `SeedDemoTourCommandTest` | Driver-API vollständig |
| Feature: Admin | `AdminAccessTest`, `InvoiceFinalizeTest`, `CustomerCsvImportTest`, `InlineEditBrandTest/ProductTest`, `CustomerContactsTest`, `SupplierContactsTest`, `CustomerPhoneTest`, `DefaultCustomerGroupTest`, `PfandSetTest`, `ProductImageTest` | Admin-Kern gut abgedeckt |
| Feature: WP12 | `CompanyScopingTest`, `PurchaseOrderTest`, `LexofficeExportTest`, `PosApiTest` | Multi-Company, POS-API |
| Feature: WP13 | `DriverTokenSecurityTest`, `SecurityHeadersTest`, `AdminDriverTokenUITest` | Security |
| Feature: WP15 | `LmivVersioningTest`, `LmivCsvImportTest` | LMIV |
| Feature: WP16 | `RevenueReportTest`, `MarginReportTest`, `TourKpiTest`, `ReportCsvExportTest` | Reports vollständig |
| Feature: WP17 | `StripeWebhookTest`, `LexofficeSyncTest`, `EmailTest` | Integrations |
| Feature: WP18 | `DeferredTaskRunnerTest`, `DiagnosticsTest`, `DoctorCommandTest`, `CleanupCommandsTest` | Ops-Tools |
| Feature: Shop | `RegisterTest`, `CartTest`, `CheckoutTest`, `ShopProductListTest`, `AccountAddressTest` | Shop-Flow abgedeckt |
| Feature: Inventory | `StockServiceTest` | StockService |

**Kritische Lücken:**
- Kein Test für Admin Order-Edit (WP-22) — letzte größere Feature-Iteration ohne Tests
- Kein Test für CMS-Seiten (Pages)
- Kein Test für Google OAuth (SocialController)
- Kein Test für Pfand-Kalkulation als Unit-Test direkt (nur indirekt via OrderPricingServiceTest)
- Keine Browser/E2E-Tests (Playwright, Dusk) für JS-lastige UI
