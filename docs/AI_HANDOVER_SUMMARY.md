# Shoptour2 – KI-Übergabe-Kurzfassung

**Ziel dieser Datei:** Direkt in ChatGPT oder Claude einfügen, um sofort mit dem Projekt weiterarbeiten zu können.

---

## Was ist Shoptour2?

Shoptour2 ist die Laravel-Backend-Plattform des Kolabri-Getränkehandels (regionaler Heimdienst, Markt, Gastronomie). Das System umfasst: Webshop, Bestellverwaltung, Tourenplanung, Fahrer-PWA, Personalverwaltung (Schicht, Urlaub, Zeiterfassung), Lagerverwaltung (Bestand, Bestandsaufnahme, MHD), Einkauf (Bestellvorschläge, Wareneingänge), Mahnwesen (Lexoffice-Sync), Kommunikation (Gmail-Import) und ein umfassendes Admin-System. Es läuft als Docker-Stack (app, nginx, MySQL, scheduler) auf einem Linux-Server.

---

## Technischer Stack

| Bereich | Details |
|---------|---------|
| Framework | Laravel 12, PHP 8.2+ |
| Datenbank | MySQL 8 |
| Frontend | Blade-Templates + Tailwind CSS v4 + Alpine.js (KEIN Next.js, KEIN React) |
| Auth | Session (Web) + Laravel Sanctum (API) + Google OAuth (Socialite) |
| Queue | DB-basierte `deferred_tasks`-Tabelle (KEIN Redis, KEIN Horizon — Shared-Hosting) |
| Scheduler | Docker-Container führt `php artisan schedule:run` jede Minute aus |
| PDF | barryvdh/laravel-dompdf |
| E-Mail | Laravel Mail via SMTP; alle Templates mit `<x-mail::message>` Wrapper |
| Externe APIs | Lexoffice, JTL WaWi (Push via API), Ninox, Stripe, PayPal, Google OAuth |

---

## Die wichtigsten Projektregeln – zwingend einhalten

1. **Geldbeträge** werden IMMER als `Integer` in **Milli-Cent** gespeichert: 1 EUR = 1.000.000. Feldname-Suffix: `_milli` (z.B. `total_gross_milli`). Anzeige: `number_format($milli / 1_000_000, 2, ',', '.') . ' €'`.

2. **Steuersätze** in Basis-Punkten (Skala `10.000 = 100 %`): `1.900 = 19 %`, `700 = 7 %`. **Achtung:** Preisanpassungen in CustomerGroup nutzen eine ANDERE Skala (`1.000.000 = 100 %`). Diese zwei Skalen dürfen nicht verwechselt werden.

3. **Import-Tabellen** (`wawi_*`, `ninox_*`, `lexoffice_*`, `primeur_*`) sind **reine Sync-Staging-Tabellen** — niemals direkt bearbeiten, niemals als Masterdaten behandeln. Sie werden bei jedem Import überschrieben. Eigene Logik in App-Tabellen.

4. **Sub-User** (Rolle `sub_user`) müssen über `$user->subUser?->parentCustomer` aufgelöst werden, NICHT über `$user->customer`. Sonst werden Sub-User stillschweigend abgelehnt.

5. **CartService** immer direkt injizieren und nutzen — NICHT über HTTP `POST /warenkorb`, das würde die `shop.order`-Middleware auslösen.

6. **E-Mail-Templates** immer mit `<x-mail::message>` als äußerem Wrapper erstellen (Branding, Logo, Footer automatisch).

7. **Feldnamen im Product-Model:** `produktname` (nicht `name`), `artikelnummer` (nicht `sku`).

8. **Preis-Anzeige-Modus:** Reihenfolge Kunde → Kundengruppe → Fallback Brutto.

9. **Änderungen im ChangeTracker** dokumentieren: `POST http://localhost:8700/api/changes` (Projekt: `shoptour2`).

10. **Keine Migrations ausführen, keine Produktivdaten anfassen** ohne explizite Freigabe.

---

## Aktueller Entwicklungsstand

**37 Features geplant** (Stand April 2026):
- **In Review (nahezu fertig):** Auth (PROJ-1), Produktkatalog (PROJ-2), Warenkorb (PROJ-3), Checkout (PROJ-4), Veranstaltungen/Rental (PROJ-22), Mahnwesen (PROJ-31), Einkauf (PROJ-32)
- **In Progress (teilweise umgesetzt):** Preisfindung, Pfand, Stammdaten-Admin, Kunden, Lieferanten, Bestellverwaltung, Rechnungen, Touren, Lagerverwaltung, Berichte, Kommunikation, Schichtplanung, uvm.
- **Planned (nicht begonnen):** Fahrer-PWA vollständig (PROJ-16), Dashboard (PROJ-17), Rollen/Berechtigungen (PROJ-18), Einstellungen (PROJ-19), Kassenverwaltung (PROJ-35), Schichtplanung (PROJ-36)

---

## Wichtigste Dateien und Verzeichnisse

| Pfad | Beschreibung |
|------|-------------|
| `app/Http/Controllers/Admin/` | Alle Admin-Controller (Bestellungen, Kunden, Produkte, ...) |
| `app/Http/Controllers/Shop/` | Shop-Frontend Controller |
| `app/Models/` | 167 Eloquent-Models (unterteilt in Namespaces) |
| `app/Services/` | 92 Service-Klassen (Kernlogik) |
| `app/Console/Commands/` | 25 Artisan-Commands |
| `routes/web.php` | Web-Routen (Admin, Shop, Auth, Mitarbeiter) |
| `routes/api.php` | API-Routen (Driver PWA, WaWi-Sync, Zahlungen) |
| `routes/console.php` | Scheduler-Definitionen |
| `database/migrations/` | 258 Migrationsdateien |
| `resources/views/admin/` | 170+ Admin-Blade-Templates |
| `features/INDEX.md` | Feature-Status-Übersicht |
| `docs/PROJECT_STATE_FOR_CHATGPT.md` | Vollständige Projektübersicht |

---

## Wichtigste Tabellen

| Tabelle | Typ | Beschreibung |
|---------|-----|-------------|
| `products` | master | Produkt-Stammdaten |
| `customers` | master | Kunden-Stammdaten |
| `customer_groups` | master | Preisgruppen |
| `orders` | transactional | Bestellungen |
| `order_items` | transactional | Bestellpositionen (mit Preis-Snapshot) |
| `invoices` | transactional | Rechnungen |
| `tours` | transactional | Fahrtouren |
| `shifts` | transactional | Schichten |
| `deferred_tasks` | system | DB-Queue-Ersatz |
| `wawi_dbo_pos_bon` | import-only | POS-Kassenbons (JTL) |
| `wawi_dbo_pos_bonposition` | import-only | Kassenpositionen (JTL) |
| `stats_pos_daily` | aggregated | Tägliche POS-Statistiken (re-aggregiert via `stats:refresh-pos`) |
| `lexoffice_vouchers` | sync | Belege aus Lexoffice (Rechnungen, Gutschriften) |
| `ninox_marktbestand` | import-only | Lagerbestand aus Ninox |

---

## Bekannte Risiken (stand April 2026)

| Risiko | Betroffene Dateien | Sofortmaßnahme |
|--------|-------------------|----------------|
| **company_id-Filter fehlt** in Bestellliste und Rechnungsliste | `AdminOrderController`, `AdminInvoiceController` | `where('company_id', app('current_company')?->id)` ergänzen |
| **Keine Laravel Policies** (`app/Policies/` leer) | Alle Admin-Controller | `make:policy` für Order, Invoice, Customer |
| **WaWi BonPosition-Sync** ggf. veraltet | `stats_pos_daily`, `wawi_dbo_pos_bonposition` | `GET /api/sync/state` abfragen; bei `last_ts` > 24 h Alert |

---

## Was auf keinen Fall kaputt gehen darf

1. **Preis-Snapshot beim Bestellanlegen** (`OrderPricingService`) — Falsche Snapshots = Rechnungsfehler
2. **Pfand-Berechnung** (`PfandCalculator`) — Rekursive Komponenten-Auflösung für Gebinde
3. **Lexoffice-Sync** — Rechnungen und Zahlungen müssen synchron bleiben
4. **Deferred Task Queue** — `kolabri:tasks:run` alle 5 Min — Ausfall = keine E-Mails, kein Invoice-Export
5. **WaWi-Sync API** (`POST /api/sync`) — Eingehende WaWi-Daten; Authentifizierung via `WAWI_SYNC_TOKEN`
6. **Sub-User-Auflösung** — Fehler hier blockiert alle Büro-Accounts

---

## Nächste sinnvolle Aufgaben (priorisiert)

1. **PROJ-5 fertigstellen** — Kundenkonto (Dashboard, Bestellhistorie, Rechnungen) — Abhängigkeit: PROJ-4 fertig
2. **PROJ-8 umsetzen** — Zahlungsabwicklung (Stripe, PayPal) — Kern-Revenue-Feature
3. **PROJ-16 fertigstellen** — Fahrer-PWA (PWA-Grundgerüst vorhanden, fehlt: vollständige Offline-Sync-Implementierung)
4. **PROJ-17 umsetzen** — Admin-Dashboard (KPIs, Touren-Übersicht) — Teilweise vorhanden
5. **Lexoffice-Zahlungsabgleich stabilisieren** — `lexoffice:import-payments` läuft alle 5 Min, gelegentliche Timeouts
6. **POS_BonPosition-Sync** — JTL-seitig sicherstellen, dass BonPosition zuverlässig gepusht wird

---

*Erstellt: 2026-04-28 | Projekt: shoptour2 | Analysiert: read-only*
