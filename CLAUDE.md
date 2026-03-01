# Kolabri Getränkeshop — AI Development Workflow

> AI-powered development workflow using specialized skills for Requirements, Architecture, Frontend, Backend, QA, and Deployment.

## Tech Stack

### Backend (Laravel)
- **Framework:** Laravel 12, PHP 8.2+
- **Database:** MySQL 8+ (KEIN Supabase, KEIN PostgreSQL)
- **Auth:** Laravel Sanctum (API) + Session-Auth (Web)
- **Authorization:** Laravel Policies + Gates
- **Validation:** Laravel FormRequest
- **Queue:** `deferred_tasks` Tabelle (kein Redis, kein Horizon — Shared-Hosting!)
- **Storage:** Laravel Storage (lokales Filesystem auf Shared-Hosting)
- **PDF:** dompdf / barryvdh/laravel-dompdf
- **Email:** Laravel Mail (SMTP)

### Frontend (Next.js)
- **Framework:** Next.js 16 (App Router), TypeScript
- **Styling:** Tailwind CSS + shadcn/ui (copy-paste components)
- **Validation:** Zod + react-hook-form
- **State:** React useState / Context API
- **API-Calls:** fetch() zu Laravel Backend-API

### Deployment
- **Backend:** Shared-Hosting bei Internetwerk (PHP 8.2+, MySQL)
- **Frontend:** Vercel (Next.js)

## Projektstruktur

```
/                          ← Next.js Frontend (dieses Repo)
  src/
    app/                   Pages (Next.js App Router)
    components/
      ui/                  shadcn/ui components (NEVER recreate these)
    hooks/                 Custom React hooks
    lib/                   Utilities (api.ts, utils.ts)
  features/                Feature specifications (PROJ-X-name.md)
    INDEX.md               Feature status overview
  docs/
    PRD.md                 Product Requirements Document
    production/            Production guides

d:\Claude_Code\Getraenkeshop\shop-tours\  ← Laravel Backend (separates Verzeichnis!)
  app/
    Http/
      Controllers/         Route-Handler (Admin/, Kasse/, etc.)
      Requests/            FormRequest-Validierung
      Middleware/          Auth, Role, CompanyScope
    Models/                Eloquent-Models
    Services/              Business-Logik (ReportService, etc.)
    Jobs/                  Deferred Tasks (kein Queue-Worker nötig)
  database/
    migrations/            Tabellenstruktur
    seeders/               Testdaten
  routes/
    web.php                Web-Routen
    api.php                API-Routen
  resources/
    views/                 Blade-Templates (Admin-UI, PDF-Vorlagen)
```

## Development Workflow

1. `/requirements` - Feature-Spec aus Idee erstellen
2. `/architecture` - Tech-Architektur entwerfen (PM-freundlich, kein Code)
3. `/frontend` - UI-Komponenten bauen (shadcn/ui first!)
4. `/backend` - Laravel Controller, Migration, Service, Policy bauen
5. `/qa` - Gegen Acceptance Criteria testen + Security Audit
6. `/deploy` - Laravel auf Shared-Hosting + Next.js auf Vercel

## Feature Tracking

Alle Features in `features/INDEX.md`. Jede Skill liest es am Anfang und aktualisiert es am Ende. Feature-Specs liegen in `features/PROJ-X-name.md`.

## Wichtige Konventionen

- **Feature IDs:** PROJ-1, PROJ-2, ... (sequenziell)
- **Commits:** `feat(PROJ-X): description`, `fix(PROJ-X): description`
- **Single Responsibility:** Eine Feature-Spec pro Datei
- **shadcn/ui first:** NIEMALS eigene Versionen installierter shadcn-Komponenten bauen
- **Human-in-the-loop:** Alle Workflows haben Nutzer-Freigabe-Checkpoints
- **Geldbeträge:** Immer als Integer in Milli-Cent (10000 = 10,00 €)
- **Multi-Tenant:** `company_id` auf ALLEN Tabellen vorbereiten
- **Keine echte Queue:** `deferred_tasks` als DB-basierte Queue (Shared-Hosting!)

## Build & Test Commands

### Frontend (Next.js)
```bash
npm run dev        # Dev-Server (localhost:3000)
npm run build      # Production-Build
npm run lint       # ESLint
npm run start      # Production-Server
```

### Backend (Laravel)
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
