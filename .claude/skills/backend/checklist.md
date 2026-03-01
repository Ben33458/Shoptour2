# Backend Implementation Checklist (Laravel / MySQL)

## Vor dem Start
- [ ] Existierende Controller geprüft (kein Doppelwerk): `find app/Http/Controllers -name "*.php"`
- [ ] Existierende Migrations geprüft: `ls database/migrations/`
- [ ] Existierende Models geprüft: `ls app/Models/`

## Datenbank
- [ ] Laravel Migration erstellt
- [ ] `company_id` auf ALLEN neuen Tabellen vorhanden
- [ ] Geldbeträge als `unsignedBigInteger` (Milli-Cent, kein DECIMAL)
- [ ] Indexes auf WHERE/JOIN/ORDER BY Spalten gesetzt
- [ ] Foreign Keys mit korrekter onDelete-Regel definiert
- [ ] Migration erfolgreich ausgeführt: `php artisan migrate`

## Eloquent Model
- [ ] Model erstellt mit `php artisan make:model`
- [ ] `$fillable` Array vollständig definiert
- [ ] Relationships korrekt definiert (hasMany, belongsTo, etc.)
- [ ] Casts für ENUM und spezielle Felder gesetzt

## Authorization
- [ ] Laravel Policy erstellt: `php artisan make:policy`
- [ ] `company_id`-Check in ALLEN Policy-Methoden vorhanden
- [ ] Policy in `AuthServiceProvider` registriert
- [ ] `$this->authorize()` in ALLEN Controller-Actions aufgerufen

## Validierung
- [ ] FormRequest für Store erstellt
- [ ] FormRequest für Update erstellt
- [ ] `$request->validated()` wird im Controller verwendet (nicht `$request->all()`)

## Controller & Routes
- [ ] Controller erstellt mit korrekter Namespace-Struktur
- [ ] Alle geplanten Routes implementiert
- [ ] Auth-Middleware auf allen Routes gesetzt
- [ ] Korrekte HTTP-Status-Codes (200, 201, 422, 403, 404, etc.)
- [ ] Thin Controller — Geschäftslogik in Services

## Service (falls benötigt)
- [ ] Service in `app/Services/` erstellt
- [ ] Keine HTTP-Objekte im Service (nur Business-Logik)
- [ ] Service per Dependency Injection im Controller injiziert

## Sicherheit
- [ ] Keine Secrets im Code
- [ ] CSRF-Schutz aktiv für Web-Routen
- [ ] Mass-Assignment-Schutz: `$fillable` gesetzt
- [ ] Raw SQL auf SQL-Injection geprüft (falls verwendet)

## Verifikation
- [ ] `php artisan test` läuft ohne Fehler
- [ ] Alle Acceptance Criteria aus Feature-Spec in den Endpunkten abgedeckt
- [ ] API manuell getestet (Postman, curl oder Browser)
- [ ] `features/INDEX.md` Status auf "In Progress" gesetzt
- [ ] Code committed

## Performance
- [ ] Eager Loading (`with()`) für bekannte Relationships verwendet
- [ ] Keine N+1-Queries
- [ ] Listen paginiert: `->paginate(25)`
- [ ] Langsame Queries gecacht (optional für MVP): `Cache::remember()`
