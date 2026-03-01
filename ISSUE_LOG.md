# ISSUE_LOG.md — Kolabri Getränkeshop
Stand: 2026-02-28 | Branch: initial-code-review

---

## 1. Blocker

| Prio | Problem | Symptom | Ursache (vermutet/belegt) | Fundstelle | Fix-Idee |
|---|---|---|---|---|---|
| P0 | **SSH-Keys im Working Directory** | `githubkey` + `githubkey.pub` sind untracked in git status — wenn committed, sofortige Key-Kompromittierung | Schlüssel liegen im Projekt-Root statt in `~/.ssh/` | `githubkey`, `githubkey.pub` (git status) | Sofort in `.gitignore` eintragen; Keys aus Repository-History entfernen falls bereits committed; betroffene Keys rotieren |
| P0 | **`public/install.php` mit hardcoded Token + Shell-Ausführung** | Datei ist über Browser erreichbar; führt beliebige Artisan-Befehle und Shell-Kommandos aus (composer, migrate) | Einmaliges Deployment-Hilfsskript, das nicht gelöscht wurde | `public/install.php:12` (INSTALL_TOKEN = '66470cfb...') | Datei nach Deployment löschen; in `.gitignore` aufnehmen; Token als wertlos betrachten (bereits im Code) |

---

## 2. High

| Prio | Problem | Symptom | Ursache (vermutet/belegt) | Fundstelle | Fix-Idee |
|---|---|---|---|---|---|
| P1 | **`customers.company_id` bei Selbstregistrierung nicht gesetzt** | Neuer Kunde über `/register` hat `company_id = NULL`; Admin-Middleware `company` nutzt `company_id`-Scoping → Kunde kann in Admin-Kontext nicht korrekt zugeordnet werden | `RegisterController::store()` setzt kein `company_id` beim Customer-Anlegen | `app/Http/Controllers/Auth/RegisterController.php:78-86` | `company_id` aus `AppSetting::get('default_company_id')` oder aus erstem Company-Eintrag ableiten und beim Customer-Anlegen setzen; analog für SocialController |
| P1 | **InvoiceService: stiller Tax-Fallback auf 19%** | Bei fehlendem `tax_rate_basis_points` auf OrderItem wird `?? 190_000` (19%) angenommen — falsche Steuerausweisung möglich (z.B. bei 7%-Produkten) | InvoiceService liest `$orderItem->tax_rate_basis_points` mit Null-Coalescing auf 190_000 | `app/Services/Admin/InvoiceService.php:117` | Fallback entfernen; stattdessen Exception oder explizite Validierung vor Draft-Generierung; sicherstellen, dass alle OrderItems einen tax_rate_basis_points-Wert haben (OrderPricingService wirft bereits Exception, aber ältere Orders?) |
| P1 | **`SupplierProduct.active`-Filter ohne Migration** | `InvoiceService.php:82` filtert `where('active', true)` auf `supplier_products`; SupplierProduct-Modell hat `active` nicht in `$fillable` und die Migration legt dieses Feld möglicherweise nicht an | Feld `active` fehlt in Supplier-Product-Schema oder ist immer `NULL` → alle SupplierProducts werden gefiltert → `cost_milli` immer NULL | `app/Services/Admin/InvoiceService.php:82` | Migration prüfen; falls Feld fehlt: Migration hinzufügen + Default `true` setzen; alternativ Filter entfernen und neuestes Eintrag über `orderByDesc('id')` allein auswählen |

---

## 3. Medium

| Prio | Problem | Symptom | Ursache | Fundstelle | Fix-Idee |
|---|---|---|---|---|---|
| P2 | **`start-server.bat` im Repository** | Entwicklungs-Hilfsdatei in git untracked; enthält Windows-spezifische Dev-Server-Konfiguration | Convenience-Skript wurde nicht gitignored | `start-server.bat` (git status) | In `.gitignore` aufnehmen |
| P2 | **Lager hat kein Admin-UI** | Bestände können nicht im Admin gepflegt werden; `StockService` und Modelle funktionieren, aber es gibt keine CRUD-Views für Warehouses, ProductStocks | Admin-UI wurde für Inventory nie gebaut | Kein Controller in `Admin/` für Inventory | Admin-CRUD für Warehouses + Stock-Übersicht + manuelle Stock-Buchungen erstellen |
| P2 | **Tour-Admin-UI fehlt** | `RegularDeliveryTour` und `DeliveryArea` haben kein CRUD im Admin; Touren-Zuordnung für Kunden (`CustomerTourOrder`) ebenfalls nicht editierbar | Feature nie implementiert | Kein `AdminRegularTourController` | CRUD für RegularDeliveryTour + DeliveryArea + Kunden-Zuordnung |
| P2 | **`admin`-Middleware prüft keine company_id bei Shop-Kunden** | Shop-Routen `/mein-konto/*` haben keine company-Middleware → Kunden verschiedener Companies könnten auf Daten anderer Companies zugreifen (falls Multi-Company aktiv) | Shop ist als Single-Tenant entwickelt | `routes/web.php:76-95` (auth-Gruppe ohne company-Middleware) | Für Multi-Tenant: company-Scoping auch auf Kunden-Routen; für Single-Tenant: dokumentieren, dass Multi-Company nicht für Shop gilt |
| P2 | **Bestellungen bearbeiten (WP-22) ohne Tests** | `AdminOrderController::edit()`, `updateItems()`, `addItem()` haben keine Feature-Tests; letzte größere Änderung ohne Testabdeckung | Tests bei WP-22-Implementierung nicht geschrieben | `tests/Feature/Admin/` — keine Datei für Order-Edit | `AdminOrderEditTest` schreiben: Item-Menge ändern, Item hinzufügen, Preissnaphot-Verhalten |
| P2 | **POS-API ohne Authentifizierung** | `/api/pos/products` und `/api/pos/sale` sind komplett ohne Auth; jeder kann Verkäufe anlegen | MVP-Zustand explizit dokumentiert | `routes/api.php` (POS-Gruppe ohne Auth) | API-Key-Middleware für POS-Endpunkte implementieren |

---

## 4. Low

| Prio | Problem | Symptom | Ursache | Fundstelle | Fix-Idee |
|---|---|---|---|---|---|
| P3 | **Keine Tests für CMS-Seiten** | `PageController`, `AdminPageController` haben keine Tests | Feature kürzlich hinzugefügt (Feb 2026) | Kein Test in `tests/Feature/` für Pages | `PagesTest` schreiben: Seite anzeigen, Admin-Edit |
| P3 | **Keine Tests für Google OAuth** | `SocialController` hat keine Tests | Schwierig zu mocken (externe Bibliothek) | `app/Http/Controllers/Auth/SocialController.php` | Socialite-Facade mocken; Happy-Path und "neuer User" vs. "bestehender User" testen |
| P3 | **Invoice-Nummer: Race Condition möglich** | Sequenz-Vergabe in `InvoiceService::finalizeInvoice()` via `count()+1` ohne DB-Lock → zwei gleichzeitige Finalizes könnten gleiche Nummer erhalten | `COUNT(*) + 1` ohne `SELECT FOR UPDATE` | `app/Services/Admin/InvoiceService.php:206-210` | DB-Lock (`lockForUpdate()`) oder `invoice_numbers`-Sequenztabelle mit Atomic-Insert |
| P3 | **`delivery_address_id` auf Orders nicht befüllt** | `orders.delivery_address_id` existiert in Schema, aber `OrderService::createOrder()` setzt diesen Wert nicht — Adresse wird über `customer.addresses.is_default` aufgelöst | `OrderService` nimmt keine Adresse als Parameter entgegen | `app/Services/Orders/OrderService.php:111-121` | `createOrder()` um `?Address $deliveryAddress` erweitern; `ShopCheckoutController` übergibt gewählte Adresse |
| P3 | **`regular_delivery_tour_id` auf Orders nicht automatisch gesetzt** | Bestellungen haben kein automatisches Tour-Assignment beim Checkout | Shop-Checkout kennt keine Tour-Logik | `app/Http/Controllers/Shop/CheckoutController.php` | Beim Checkout PLZ des Kunden prüfen und passende RegularDeliveryTour zuordnen |
| P3 | **E-Mail-Versand synchron in `finalizeInvoice()`** | Bei Mail-Fehler wird Exception geloggt, aber keine Retry-Mechanik; auf Shared-Hosting können SMTP-Fehler häufig sein | Mail-Dispatch direkt in Service ohne Queue | `app/Services/Admin/InvoiceService.php:254-263` | Mail in `deferred_tasks` auslagern oder Laravel-Queue nutzen sobald verfügbar |
| P3 | **`githubkey` / `githubkey.pub` sollten nicht im Projekt-Root liegen** | Bereits unter Blocker (P0) gelistet — zusätzlich: aus UX-Sicht verwirrend, wo Schlüssel hingehören | Schlüssel versehentlich in Projekt-Root abgelegt | `githubkey`, `githubkey.pub` | Nach `~/.ssh/` oder Passwort-Manager verschieben; `.gitignore` ergänzen |
| P3 | **Kein Laravel Scheduler konfiguriert** | `deferred-tasks:run` und Cleanup-Befehle müssen manuell/extern getriggert werden | Shared-Hosting ohne Cron-Zugang | `docs/deployment_internetwerk.md` | Cron-Möglichkeiten beim Hoster prüfen; ggf. externen Cron-Service (cron-job.org) nutzen |
