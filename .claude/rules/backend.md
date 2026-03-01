# Backend Development Rules (Laravel 12 / MySQL)

## Datenbankschema (Laravel Migrations)
- Migrations mit `php artisan make:migration create_X_table` erstellen
- Alle Tabellen bekommen `company_id` (Multi-Tenant vorbereitung)
- Indexes auf alle WHERE-, ORDER BY- und JOIN-Spalten setzen
- Foreign Keys mit `onDelete('cascade')` oder `onDelete('restrict')` je nach Kontext
- Geldbeträge IMMER als `unsignedBigInteger` in Milli-Cent speichern (kein DECIMAL!)
- ENUM-Spalten bei stabilen Status-Werten, sonst VARCHAR

## Eloquent ORM
- Immer über Eloquent-Models arbeiten (kein raw SQL außer für komplexe Reports)
- Relationships korrekt definieren: `hasMany`, `belongsTo`, `belongsToMany`
- `with()` für eager loading nutzen (N+1-Queries vermeiden!)
- `.limit()` / `->take(N)` auf alle Listenabfragen anwenden
- `company_id` auf alle Queries scopen — entweder via GlobalScope oder explizit

## Authorization (Laravel Policies)
- Für jedes Model eine Policy erstellen: `php artisan make:policy XxxPolicy --model=Xxx`
- Policy-Methoden: `viewAny`, `view`, `create`, `update`, `delete`
- Policies im Controller via `$this->authorize()` anwenden
- NIEMALS Datenbankabfragen ohne company_id-Check!
- Admin-only Aktionen via Middleware absichern (z.B. `middleware('role:admin')`)

## Controller & Routes
- Resource Controller für CRUD: `php artisan make:controller Admin/XxxController --resource`
- API-Routen in `routes/api.php`, Web-Routen in `routes/web.php`
- Routes mit `middleware('auth:sanctum')` oder `middleware('auth')` schützen
- Route-Gruppen für Admin-Bereich: `prefix('admin')->middleware(['auth', 'role:admin'])`
- Thin Controllers — Geschäftslogik gehört in Services

## Services
- Komplexe Geschäftslogik in `app/Services/` auslagern
- Service-Klassen haben keine HTTP-Abhängigkeiten (kein Request-Objekt!)
- Services werden per Dependency Injection in Controller injiziert

## Validierung (FormRequest)
- Für jeden POST/PUT-Endpunkt ein FormRequest erstellen: `php artisan make:request StoreXxxRequest`
- Validierungsregeln in `rules()` definieren
- Nie Client-seitige Validierung allein vertrauen

## Deferred Tasks (kein Queue-Worker!)
- Shared-Hosting: KEIN Redis, KEIN Horizon, KEIN `queue:work` Daemon
- Hintergrundaufgaben über `deferred_tasks`-Tabelle implementieren
- Täglich per Cron (sofern verfügbar) oder manuell triggerbar

## Security
- Secrets niemals im Code — in `.env` Datei
- CSRF-Schutz für alle Web-Routen (standardmäßig aktiv in Laravel)
- Mass-Assignment: `$fillable` auf ALLEN Models definieren
- SQL-Injection: Eloquent/QueryBuilder verwenden (parametrisierte Queries)
- XSS: Blade-Templates escapen automatisch (immer `{{ }}` statt `{!! !!}`)

## Performance
- Eager Loading für bekannte Relationships (`with()`)
- Keine N+1-Queries
- Häufig gelesene, selten geänderte Daten cachen: `Cache::remember()`
- Große Listen immer paginieren: `->paginate(25)`
