# Kolabri Getränkeshop — AI Development Workflow

> AI-powered development workflow using specialized skills for Requirements, Architecture, Backend, QA, and Deployment.

## Tech Stack

### Backend (Laravel) — dieses Repo
- **Framework:** Laravel 12, PHP 8.2+
- **Database:** MySQL 8+ (KEIN Supabase, KEIN PostgreSQL)
- **Auth:** Laravel Sanctum (API) + Session-Auth (Web)
- **Authorization:** Laravel Policies + Gates
- **Validation:** Laravel FormRequest
- **Queue:** `deferred_tasks` Tabelle (kein Redis, kein Horizon — Shared-Hosting!)
- **Storage:** Laravel Storage (lokales Filesystem auf Shared-Hosting)
- **PDF:** dompdf / barryvdh/laravel-dompdf
- **Email:** Laravel Mail (SMTP)

### Deployment
- **Backend:** Shared-Hosting bei Internetwerk (PHP 8.2+, MySQL)

## Referenz-Projekt

> `d:\Claude_Code\Getraenkeshop\shop-tours\` ist das ALTE Referenz-Projekt.
> Es wird NICHT verändert — nur als Vorlage/Referenz gelesen.

## Projektstruktur

```
d:\Claude_Code\Getraenkeshop\Shoptour2\   ← Dieses Repo (Laravel Backend NEU)
  app/
    Http/
      Controllers/         Route-Handler (Admin/, Shop/, etc.)
      Requests/            FormRequest-Validierung
      Middleware/          Auth, Role, CompanyScope
    Models/                Eloquent-Models
    Services/              Business-Logik
    DTOs/                  Value Objects
  database/
    migrations/            Tabellenstruktur
    seeders/               Testdaten
  routes/
    web.php                Web-Routen
    api.php                API-Routen
  resources/
    views/                 Blade-Templates (Admin-UI, PDF-Vorlagen)
  features/                Feature specifications (PROJ-X-name.md)
    INDEX.md               Feature status overview
  docs/
    PRD.md                 Product Requirements Document
```

## Development Workflow

1. `/requirements` - Feature-Spec aus Idee erstellen
2. `/architecture` - Tech-Architektur entwerfen (PM-freundlich, kein Code)
3. `/backend` - Laravel Controller, Migration, Service, Policy bauen
4. `/qa` - Gegen Acceptance Criteria testen + Security Audit
5. `/deploy` - Laravel auf Shared-Hosting deployen

## Feature Tracking

Alle Features in `features/INDEX.md`. Jede Skill liest es am Anfang und aktualisiert es am Ende. Feature-Specs liegen in `features/PROJ-X-name.md`.

## Wichtige Konventionen

- **Feature IDs:** PROJ-1, PROJ-2, ... (sequenziell)
- **Commits:** `feat(PROJ-X): description`, `fix(PROJ-X): description`
- **Single Responsibility:** Eine Feature-Spec pro Datei
- **Human-in-the-loop:** Alle Workflows haben Nutzer-Freigabe-Checkpoints
- **Geldbeträge:** Immer als Integer in Milli-Cent (1_000_000 = 1,00 €)
- **Multi-Tenant:** `company_id` auf ALLEN Tabellen vorbereiten
- **Keine echte Queue:** `deferred_tasks` als DB-basierte Queue (Shared-Hosting!)
- **Referenz lesen, nicht ändern:** shop-tours nur lesen, nie committen

## Build & Test Commands

```bash
php artisan serve              # Dev-Server (localhost:8000)
php artisan migrate            # Migrationen ausführen
php artisan migrate:rollback   # Letzte Migration rückgängig
php artisan make:migration     # Neue Migration erstellen
php artisan make:model         # Neues Eloquent-Model
php artisan make:controller    # Neuen Controller
php artisan make:policy        # Neue Policy
php artisan make:request       # Neues FormRequest
php artisan test               # Tests ausführen
composer install               # Dependencies installieren
```

## Product Context

@docs/PRD.md

## Feature Overview

@features/INDEX.md
