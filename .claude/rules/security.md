# Security Rules (Laravel / Next.js)

## Secrets Management
- NIEMALS Secrets, API-Keys oder Credentials in Git committen
- Laravel: `.env` Datei für alle Credentials (bereits in .gitignore)
- Next.js: `.env.local` für lokale Entwicklung
- `NEXT_PUBLIC_` Prefix NUR für Werte, die sicher im Browser exponiert werden dürfen
- Alle benötigten Env-Vars in `.env.example` (Laravel) / `.env.local.example` (Next.js) dokumentieren

## Input Validation
- Alle User-Inputs SERVER-seitig validieren (Laravel FormRequest oder `$request->validate()`)
- Client-seitiger Validierung (Zod, HTML5) NIEMALS allein vertrauen
- Daten vor Datenbankoperation sanitisieren

## Authentication
- Alle API-Endpunkte hinter `middleware('auth:sanctum')` oder `middleware('auth')` schützen
- Laravel Policies als zweite Verteidigungslinie (company_id-Scope!)
- Rate Limiting auf Auth-Endpunkte: `throttle:6,1` (6 Versuche pro Minute)
- Session-Tokens haben Ablaufzeit; sensible Aktionen erfordern re-auth

## Authorization
- Laravel Policies für alle Model-Operationen (viewAny, view, create, update, delete)
- company_id-Check auf ALLEN Datenbankabfragen — nie vergessen!
- Admins haben erweiterte Rechte, aber auch diese werden in Policies geprüft
- Keine "Security by obscurity" — alle Endpunkte explizit schützen

## Security Headers (Next.js next.config)
- X-Frame-Options: DENY
- X-Content-Type-Options: nosniff
- Referrer-Policy: origin-when-cross-origin
- Strict-Transport-Security mit includeSubDomains

## Security Headers (Laravel)
- CORS korrekt konfigurieren in `config/cors.php`
- CSP-Header via Middleware für Admin-Bereich

## Code Review Triggers
- Änderungen an Laravel Policies → explizite Nutzer-Freigabe erforderlich
- Änderungen am Auth-Flow → explizite Nutzer-Freigabe erforderlich
- Neue Env-Variablen → in `.env.example` dokumentieren
- Raw SQL Queries → Code Review, auf Injection prüfen

## Mass Assignment
- `$fillable` auf ALLEN Eloquent-Models definieren
- Niemals `$guarded = []` in Production
- Request-Daten immer über `$request->validated()` holen (nicht `$request->all()`)
