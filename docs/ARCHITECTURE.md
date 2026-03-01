# System Architecture: Kolabri Getränkeshop

> Übergreifende technische Entscheidungen, auf die alle Feature-Designs aufbauen.
> **Letzte Aktualisierung:** 2026-02-28

---

## Workspace-Übersicht

| Workspace | Pfad | Zweck |
|-----------|------|-------|
| **Planungs-Workspace** | `D:\Claude_Code\Getraenkeshop\Shoptour2` | Feature-Specs, Architektur-Docs (dieses Projekt) |
| **Implementierungs-Workspace** | `D:\Claude_Code\Getraenkeshop\shop-tours` | Laravel 12 — die tatsächliche Anwendung |

---

## 1. Stack-Übersicht

```
┌──────────────────────────────────────────────────┐
│               Browser / Smartphone               │
│                                                  │
│   Shop + Admin (Blade/Alpine)  Fahrer-PWA (JS)   │
└──────────────────┬───────────────────┬───────────┘
                   │ HTTP/Session      │ Bearer Token
                   ▼                  ▼
┌──────────────────────────────────────────────────┐
│             Laravel 12 (shop-tours)              │
│             PHP 8.2 | MySQL                      │
│                                                  │
│  Blade Templates + Tailwind CSS + Alpine.js      │
│  Laravel Auth + Sanctum + Socialite              │
│  DomPDF (Rechnungen, Berichte)                   │
│  Vite (Asset-Build — nur zur Compile-Zeit)       │
│                                                  │
│  ┌────────────────────────────────────────────┐  │
│  │           Kern-Services                    │  │
│  │  PricingService · PfandCalculator          │  │
│  │  TourAssignmentService                     │  │
│  │  InvoiceService · KassenbuchService        │  │
│  │  ShiftReportService · MinBreakService      │  │
│  └────────────────────────────────────────────┘  │
│                                                  │
│  MySQL + Laravel Storage (Dateien)               │
│  Laravel Scheduler via Cron (kein Queue-Worker)  │
└──────────────────────────────────────────────────┘
           ↕                      ↕
    Lexoffice API            Stripe / PayPal
```

---

## 2. Bereits installierte Pakete (shop-tours)

| Paket | Version | Zweck |
|-------|---------|-------|
| `laravel/framework` | ^12.0 | Core |
| `laravel/socialite` | ^5.24 | Google OAuth |
| `barryvdh/laravel-dompdf` | ^3.1 | PDF-Generierung |
| Tailwind CSS | ^4.0 | Styling |
| Vite | ^7 | Asset-Build |

**Noch zu installieren:**
| Paket | Zweck |
|-------|-------|
| `laravel/sanctum` | In Laravel 12 enthalten, aktivieren |
| `socialiteproviders/google` | Google-Provider für Socialite |

---

## 3. Frontend-Ansatz: Blade + Tailwind + Alpine.js

Kein separates JavaScript-Framework. Kein Node.js-Runtime auf dem Server.

```
resources/
├── views/
│   ├── shop/         ← Produktkatalog, Warenkorb, Checkout (Kunden-Bereich)
│   ├── auth/         ← Login, Registrierung, Passwort-Reset
│   ├── admin/        ← Kompletter Admin-Bereich
│   ├── driver/       ← Fahrer-Bereich (PWA-Einstieg)
│   ├── emails/       ← Email-Vorlagen
│   └── pdf/          ← PDF-Vorlagen (DomPDF)
├── css/
│   └── app.css       ← Tailwind-Einstieg
└── js/
    ├── app.js        ← Alpine.js + globale Initialisierungen
    └── driver-pwa/   ← Service Worker + Offline-Logik (vanilla JS)
```

**Wann Alpine.js, wann Blade?**
- **Blade:** Statische Seiten, Listen, Formulare, Admin-CRUD
- **Alpine.js:** Dropdowns, Modals, Tab-Wechsel, Live-Validierung, kleine reaktive Bereiche
- **Vanilla JS:** Fahrer-PWA (Service Worker, IndexedDB, Offline-Sync)

---

## 4. Routing-Struktur

```
routes/
├── web.php
│   ├── /                          ← Startseite / Produktkatalog
│   ├── /produkte/{slug}           ← Produktdetail
│   ├── /warenkorb                 ← Warenkorb
│   ├── /checkout                  ← Checkout-Flow
│   ├── /mein-konto/*              ← Kundenkonto (auth)
│   ├── /login, /registrieren, ... ← Auth-Seiten
│   ├── /admin/*                   ← Admin-Bereich (Middleware: admin)
│   ├── /fahrer/*                  ← Fahrer-Bereich (Middleware: driver)
│   └── /auth/google/*             ← Socialite OAuth
│
└── api.php
    └── /api/v1/driver/*           ← Fahrer-PWA API (Bearer Token, stateless)
```

---

## 5. Authentifizierung

| Benutzertyp | Methode | Session |
|-------------|---------|---------|
| Kunde / Admin | Laravel Auth (Email+Passwort) | PHP-Session-Cookie |
| Kunde | Google OAuth (Socialite) | PHP-Session-Cookie |
| Fahrer (PWA) | Bearer Token (`driver_api_tokens`) | Stateless, kein Cookie |

**Middleware-Gruppen:**
- `auth` → Kunden-Bereich (`/mein-konto`)
- `admin` → Admin-Bereich (`/admin`): auth + Rolle `admin`
- `driver_token` → Fahrer-API: Bearer-Token-Validierung

---

## 6. Datenbank

**Motor:** MySQL 8.0 (auf praktisch jedem Webspace verfügbar)

### Universelle Konventionen

| Konvention | Umsetzung |
|-----------|-----------|
| Multi-Tenant-Vorbereitung | `company_id` auf jeder Tabelle |
| Soft Delete | `deleted_at` (Laravel SoftDeletes) |
| Zeitstempel | `created_at`, `updated_at` |
| Geldbeträge | **Ausnahmslos Integer in Milli-Cents** (1 € = 1.000.000) |
| Enums | VARCHAR mit App-seitiger Validierung (kein MySQL ENUM) |
| Primärschlüssel | Auto-Increment Integer (keine UUIDs) |

### Kein Float in Berechnungen
Alle Preise, Pfandbeträge, Steuern als Integer (Milli-Cents). Division nur am Anzeigeort, immer gerundet.

### Audit-Log
Kritische Änderungen (Bestellstatus, Rechnungen, Preise, Einstellungen, Kassenbuch) werden in `audit_logs` geschrieben.

### Rechnungsnummern
Dedizierte `invoice_sequences`-Tabelle mit DB-Lock — kein `MAX(id)+1`.

---

## 7. Kern-Services (alle stateless, unit-testbar)

| Service | Aufgabe |
|---------|---------|
| `PricingService` | 3-stufig: Kundenpreis → Gruppenpreis → Basispreis ± Anpassung |
| `PfandCalculator` | Rekursiv PfandSet-Baum; Zyklusschutz via `$visited` |
| `TourAssignmentService` | PLZ + city_match; gibt Liste passender Touren zurück |
| `InvoiceService` | Rechnungs-Generierung + sequentielle Nummern (DB-Lock) |
| `KassenbuchService` | Immutable Ledger; Transfer zwischen Kassen |
| `ShiftReportService` | Auto-Bericht beim Ausstempeln (Touren + Aufgaben) |
| `DriverSyncService` | Idempotente Event-Verarbeitung aus PWA-Sync |
| `MinBreakService` | ArbZG §4 Mindestpausen-Berechnung |

---

## 8. Asynchrone Aufgaben (kein Server-Cron)

Kein Cron-Job auf dem Webspace verfügbar. Drei Ansätze je nach Aufgabentyp:

### A) Synchron (Standard für die meisten Tasks)
Aufgaben werden direkt beim Request ausgeführt — kein Delay.

| Aufgabe | Synchron möglich? | Hinweis |
|---------|-----------------|---------|
| Email-Versand (Bestellung, Rechnung) | ✓ | Mit Mailgun/Postmark API < 200ms |
| PDF-Generierung | ✓ | On-Demand beim Aufrufen der PDF-URL |
| Lexoffice-Sync | ✓ | Beim Admin-Speichern, mit Timeout-Schutz |
| Fahrer-Sync-Verarbeitung | ✓ | In-Request (stateless, schnell) |

### B) Externer Cron-Service (für zeitgesteuerte Aufgaben)
**[cron-job.org](https://cron-job.org)** — kostenlos, pingt jede Minute eine URL an.

```
URL: https://deine-domain.de/cron/run?token=GEHEIM
Intervall: jede Minute
```

Laravel verarbeitet beim Aufruf dieser URL die offene `deferred_tasks`-Queue.

| Aufgabe | Braucht Cron? |
|---------|-------------|
| Mahnwesen (automatische Zahlungserinnerungen) | **Ja** — ohne Cron nur manuell |
| Newsletter-Versand | Ja |
| Wiederkehrende Aufgaben (PROJ-26) | Ja |
| Tour-Erstellung aus Templates | Ja |

### C) Manuell (Admin-Trigger)
Wenn Cron-Service nicht gewünscht: Mahnwesen und ähnliches per Admin-Button auslösen.

### `deferred_tasks`-Tabelle (bleibt als Struktur, Verarbeitung via B oder C)
```
deferred_tasks:
  type, payload (JSON), status (pending/processing/done/failed)
  available_at, attempts, last_error
```

> **Empfehlung:** cron-job.org einrichten — 5 Minuten Setup, kostenlos, zuverlässig.
> Ohne Cron funktioniert alles außer automatischem Mahnwesen.

---

## 9. Fahrer-PWA (Offline-First)

Der Fahrer-Bereich ist eine **Progressive Web App** direkt im Laravel-Projekt:

```
resources/js/driver-pwa/
├── app.js              ← PWA-Einstieg, lädt SW
├── service-worker.js   ← Offline-Caching (Workbox oder manuell)
├── db.js               ← IndexedDB-Wrapper
├── sync.js             ← Event-Queue + Sync-Logik
└── ui.js               ← DOM-Manipulation für Fahrer-UI
```

Die HTML-Grundstruktur kommt aus Blade (`resources/views/driver/`). Das JS übernimmt die Offline-Logik. Kein React nötig.

---

## 10. Datei-Storage

- **Produktbilder, Lieferfotos, PDF-Dokumente:** Laravel Storage (`storage/app/public`)
- Öffentliche URLs via `storage:link` (symbolischer Link auf `public/storage`)
- **Upgrade-Pfad auf S3:** Nur `.env`-Änderung, kein Code-Umbau

---

## 11. Security

| Thema | Umsetzung |
|-------|-----------|
| CSRF | Laravel CSRF-Token auf allen Formularen (automatisch) |
| XSS | Blade `{{ }}` escapt standardmäßig |
| SQL-Injection | Eloquent / Query Builder (parametrisiert) |
| Secrets | API-Keys verschlüsselt via `Crypt::encryptString()` |
| Rate Limiting | Laravel `throttle` Middleware (Login: 5/min/IP) |
| Uploads | Typ- + Größen-Validierung; keine ausführbaren Dateien |

---

## 12. Hosting-Anforderungen (beliebiger Webspace)

| Anforderung | Mindest-Version |
|-------------|----------------|
| PHP | 8.2+ |
| MySQL | 5.7+ (empfohlen: 8.0) |
| Cron-Jobs | Mindestens 1 Cron-Eintrag (Laravel Scheduler) |
| Composer | Auf Server oder via FTP-Upload des `vendor/`-Ordners |
| Node.js | **Nur zum Build** (`npm run build` lokal) — nicht auf Server |
| `.htaccess` / URL-Rewrite | Für Apache-Server (Standard-Webspace); NGINX-Config für VPS |

---

## 13. Empfohlene Build-Reihenfolge

```
Phase 1: Fundament
  PROJ-6  Preisfindung (PricingService + Migrations)
  PROJ-7  Pfand-System (PfandCalculator + Migrations)
  PROJ-1  Auth (Login, Register, Google OAuth)

Phase 2: Stammdaten & Katalog
  PROJ-9  Admin Stammdaten (Produkte, Brands, Gebinde, Pfand-UI)
  PROJ-2  Produktkatalog (Shop-Frontend)
  PROJ-10 Kundenverwaltung

Phase 3: Bestellprozess
  PROJ-3  Warenkorb
  PROJ-4  Checkout
  PROJ-5  Kundenkonto
  PROJ-8  Zahlungsabwicklung

Phase 4: Admin-Kern
  PROJ-12 Bestellverwaltung
  PROJ-13 Rechnungen
  PROJ-14 Regelmäßige Touren

Phase 5: Touren & Fahrer
  PROJ-15 Fahrertouren-Planung
  PROJ-16 Fahrer-PWA
  PROJ-35 Kassenverwaltung

Phase 6: Erweiterungen
  PROJ-11, PROJ-17–19, PROJ-36, ...
```
