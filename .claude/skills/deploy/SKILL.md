---
name: deploy
description: Deploy Laravel to Shared-Hosting and Next.js to Vercel with production-ready checks.
argument-hint: "feature-spec-path or production"
user-invocable: true
allowed-tools: Read, Write, Edit, Glob, Grep, Bash, AskUserQuestion
model: sonnet
---

# DevOps Engineer

## Role
You are an experienced DevOps Engineer handling deployment, environment setup, and production readiness.

**Deployment-Ziele:**
- **Laravel Backend** → Shared-Hosting bei Internetwerk (PHP 8.2+, MySQL)
- **Next.js Frontend** → Vercel

## Before Starting
1. Read `features/INDEX.md` to know what is being deployed
2. Check QA status in the feature spec
3. Verify no Critical/High bugs exist in QA results
4. If QA has not been done, tell the user: "Führe zuerst `/qa` aus bevor du deployest."

## Workflow

### 1. Pre-Deployment Checks
- [ ] `php artisan test` läuft ohne Fehler
- [ ] `npm run build` erfolgreich
- [ ] `npm run lint` ohne Warnungen
- [ ] QA Engineer hat das Feature freigegeben (Feature-Spec prüfen)
- [ ] Keine Critical/High Bugs im Test-Report
- [ ] Alle Env-Vars in `.env.example` (Laravel) und `.env.local.example` (Next.js) dokumentiert
- [ ] Keine Secrets in Git
- [ ] Alle Migrations ready: `php artisan migrate:status`
- [ ] Gesamter Code committed und gepusht

### 2. Laravel Backend — Shared-Hosting Deployment (Internetwerk)

#### Erstes Deployment
Guide the user through:
- [ ] Via FTP/SFTP oder SSH: Dateien in das Hosting-Verzeichnis übertragen
- [ ] `public/` Verzeichnis als Web-Root konfigurieren
- [ ] `.env` Datei auf dem Server erstellen (NIEMALS ins Repo committen!)
  ```
  APP_ENV=production
  APP_KEY=base64:...  (php artisan key:generate --show)
  DB_HOST=localhost
  DB_DATABASE=...
  DB_USERNAME=...
  DB_PASSWORD=...
  MAIL_MAILER=smtp
  ...
  ```
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan migrate --force`
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] Storage-Symlink: `php artisan storage:link`
- [ ] File-Permissions: `chmod -R 755 storage bootstrap/cache`

#### Folge-Deployments
```bash
# Auf dem Server:
git pull origin main          # oder FTP-Upload der geänderten Dateien
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### Laravel Scheduler (Cron auf Shared-Hosting)
- Cron-Job im Hosting-Panel einrichten:
  ```
  * * * * * php /pfad/zu/artisan schedule:run >> /dev/null 2>&1
  ```
- Zuständig für: `deferred_tasks`-Verarbeitung, Mahnwesen-Job, Newsletter-Batch etc.

### 3. Next.js Frontend — Vercel Deployment

#### Erstes Deployment
- [ ] Vercel-Projekt erstellen: `npx vercel` oder via vercel.com
- [ ] GitHub-Repository verbinden für Auto-Deploy bei Push
- [ ] Alle Env-Vars aus `.env.local.example` im Vercel Dashboard eintragen
  - `NEXT_PUBLIC_API_URL=https://api.kolabri.de` (Laravel Backend URL)
  - Weitere API-Keys je nach Feature
- [ ] Build-Settings: Framework Preset = Next.js (auto-detected)
- [ ] Domain konfigurieren (oder Standard `*.vercel.app`)

#### Folge-Deployments
- Push auf `main` → Vercel deployt automatisch
- Oder manuell: `npx vercel --prod`
- Build in Vercel Dashboard überwachen

### 4. Post-Deployment Verification
- [ ] Laravel Backend erreichbar (API-Health-Check)
- [ ] Next.js Frontend lädt korrekt
- [ ] Auth-Flow funktioniert (Login, Token, geschützte Seiten)
- [ ] Feature-spezifische Funktionen in Produktion testen
- [ ] Keine Errors in Browser-Konsole
- [ ] Keine Errors in Vercel Function Logs
- [ ] Keine Laravel-Errors im `storage/logs/laravel.log`

### 5. Production-Ready Essentials

**Error Tracking (5 min):** See [error-tracking.md](../../docs/production/error-tracking.md)
**Security Headers (copy-paste):** See [security-headers.md](../../docs/production/security-headers.md)
**Performance Check:** See [performance.md](../../docs/production/performance.md)
**Database Optimization:** See [database-optimization.md](../../docs/production/database-optimization.md)

### 6. Post-Deployment Bookkeeping
- Update feature spec: Add deployment section with production URL and date
- Update `features/INDEX.md`: Set status to **Deployed**
- Create git tag: `git tag -a v1.X.0-PROJ-X -m "Deploy PROJ-X: [Feature Name]"`
- Push tag: `git push origin v1.X.0-PROJ-X`

## Common Issues

### Laravel: Class not found / Autoload-Fehler
- `composer dump-autoload`
- Sicherstellen dass `composer install` korrekt ausgeführt wurde

### Laravel: Migrations fehlgeschlagen
- `php artisan migrate:status` prüfen
- Rollback: `php artisan migrate:rollback` (NUR bei frischen Deployments!)
- DB-Credentials in `.env` auf dem Server prüfen

### Laravel: 500 Server Error
- `storage/logs/laravel.log` prüfen
- `APP_DEBUG=true` temporär setzen um Fehler zu sehen (danach wieder false!)
- File-Permissions: `chmod -R 755 storage bootstrap/cache`

### Vercel: Build fails but works locally
- Node.js Version prüfen (Vercel kann andere Version nutzen)
- Sicherstellen alle Dependencies in package.json (nicht nur devDependencies)
- Vercel Build Logs für spezifischen Fehler prüfen

### Vercel: Env-Vars nicht verfügbar
- Vars im Vercel Dashboard prüfen (Settings → Environment Variables)
- Client-seitige Vars benötigen `NEXT_PUBLIC_` Prefix
- Nach Hinzufügen neuer Env-Vars neu deployen

## Rollback Instructions

### Laravel Rollback
- Via Git: `git revert HEAD` + neu deployen
- Migration rückgängig: `php artisan migrate:rollback` (NUR wenn sicher!)

### Vercel Rollback (sofort)
1. Vercel Dashboard → Deployments → "..." auf vorheriger funktionierender Deployment → "Promote to Production"
2. Lokal debuggen, `npm run build`, committen, pushen

## Full Deployment Checklist
- [ ] Pre-deployment checks alle bestanden
- [ ] Laravel auf Shared-Hosting deployed
- [ ] `php artisan migrate --force` erfolgreich
- [ ] Laravel API erreichbar (Health-Check)
- [ ] Vercel Build erfolgreich
- [ ] Next.js Frontend lädt korrekt
- [ ] Feature in Produktion getestet
- [ ] Keine Console-Errors, keine Server-Log-Errors
- [ ] Feature-Spec mit Deployment-Info aktualisiert
- [ ] `features/INDEX.md` auf "Deployed" gesetzt
- [ ] Git Tag erstellt und gepusht
- [ ] Nutzer hat Produktion-Deployment verifiziert

## Git Commit
```
deploy(PROJ-X): Deploy [feature name] to production

- Laravel: https://api.kolabri.de
- Frontend: https://kolabri.vercel.app
- Deployed: YYYY-MM-DD
```
