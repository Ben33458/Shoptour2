# PROJ-1: Authentifizierung

## Status: In Review
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- None

## Beschreibung
Registrierung, Login, Logout und Passwort-Reset für Kunden. Unterstützt Email/Passwort sowie Google OAuth (Socialite). Nach dem Login werden Gast-Warenkorb und Kundenkonto zusammengeführt.

## User Stories
- Als Gast möchte ich mich per Email/Passwort registrieren, um Bestellungen aufgeben und verwalten zu können.
- Als Gast möchte ich mich mit meinem Google-Konto anmelden, um schnell ohne Passwort einzusteigen.
- Als registrierter Kunde möchte ich mich einloggen und ausloggen können.
- Als Kunde möchte ich mein Passwort zurücksetzen können, wenn ich es vergessen habe.
- Als Gast möchte ich, dass mein bestehender Warenkorb nach dem Login erhalten bleibt (Cart Merge).
- Als Admin möchte ich, dass neue Kunden automatisch einer Standard-Kundengruppe und company_id zugewiesen werden.

## Acceptance Criteria
- [ ] Registrierung erfordert: Vorname, Nachname, Email, Passwort (min. 8 Zeichen), Firmenname (optional), Lieferadresse (Pflichtfelder: Straße, Hausnr., PLZ, Stadt)
- [ ] Nach Registrierung erhält der Kunde eine Bestätigungs-Email
- [ ] Login per Email/Passwort funktioniert; falsche Credentials zeigen generische Fehlermeldung
- [ ] Google OAuth: Neuer User wird als Kunde angelegt; bestehender User wird eingeloggt
- [ ] Passwort-Reset: Email mit Link → Formular mit neuem Passwort → Bestätigung
- [ ] Nach Login wird Gast-Session-Warenkorb in den Auth-Warenkorb gemergt (keine Artikel verloren)
- [ ] Neuer Kunde erhält `company_id` aus `default_company_id` App-Setting
- [ ] Neuer Kunde erhält Standard-Kundengruppe aus `default_customer_group_id` App-Setting
- [ ] Logout beendet Session vollständig; Weiterleitung zur Startseite
- [ ] Rate Limiting auf Login-Endpunkt (5 Versuche / Minute pro IP)
- [ ] Redirect nach Login: zurück zur ursprünglich aufgerufenen Seite oder `/mein-konto`

## Edge Cases
- Google-Account mit bereits vorhandener Email → In bestehenden Account einloggen, google_id setzen
- Registrierung mit bereits vergebener Email → Fehlermeldung „Email bereits registriert"
- Passwort-Reset-Link ist abgelaufen (> 60 Min.) → Fehlermeldung mit Option „Neuen Link anfordern"
- Gast hat Artikel im Warenkorb; nach Login hat Auth-Account ebenfalls Artikel → Gast-Artikel werden hinzuaddiert (nicht ersetzt)
- Google OAuth schlägt fehl (Google nicht erreichbar) → Fehlermeldung, Fallback auf Email-Login
- Nutzer ist deaktiviert (`active = false`) → Login verweigert, Hinweis „Konto deaktiviert"

## Technical Requirements
- Backend: Laravel Auth (session-based für Shop), API-Token für Fahrer-PWA
- Google OAuth via Laravel Socialite
- Passwort-Hashing: bcrypt
- Email-Bestätigung: Optional für MVP (konfigurierbar in Admin-Einstellungen)
- `customers.company_id` MUSS bei jeder Registrierung gesetzt werden (kein NULL erlaubt)
- `customers.customer_group_id` MUSS bei jeder Registrierung gesetzt werden

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur

```
Laravel (shop-tours)
│
├── routes/web.php
│   ├── GET  /login               → Auth\LoginController@show
│   ├── POST /login               → Auth\LoginController@store
│   ├── POST /logout              → Auth\LoginController@destroy
│   ├── GET  /registrieren        → Auth\RegisterController@show
│   ├── POST /registrieren        → Auth\RegisterController@store
│   ├── GET  /passwort-vergessen  → Auth\PasswordController@forgot
│   ├── POST /passwort-vergessen  → Auth\PasswordController@sendLink
│   ├── GET  /passwort-reset/{t}  → Auth\PasswordController@reset
│   ├── POST /passwort-reset      → Auth\PasswordController@update
│   ├── GET  /auth/google/redirect→ Auth\OAuthController@redirect
│   └── GET  /auth/google/callback→ Auth\OAuthController@callback
│
├── resources/views/auth/
│   ├── login.blade.php           ← Email + Passwort + Google-Button
│   ├── register.blade.php        ← Registrierungsformular
│   ├── forgot-password.blade.php
│   └── reset-password.blade.php
│
└── Middleware
    ├── auth          → /mein-konto/* (eingeloggt)
    └── admin         → /admin/* (eingeloggt + Rolle admin)
```

### Datenmodell

```
users
├── id, email (unique), password (bcrypt, nullable bei OAuth)
├── google_id (nullable), email_verified_at, active
└── company_id

customers  [1:1 zu users, nur für Kunden]
├── id, user_id → users
├── customer_number (auto, unveränderlich)
├── company_name (optional), first_name, last_name
├── customer_group_id, is_business, is_deposit_exempt
├── price_display_mode (netto/brutto), lexoffice_contact_id
└── company_id

addresses  [n:1 zu customers]
├── id, customer_id → customers
├── Anschrift: street, house_number, postal_code, city, country
├── delivery_note, deposit_location, allow_unattended_delivery
├── is_default
└── company_id

password_reset_tokens  [Laravel Standard]
└── email, token, created_at
```

**Warum `users` und `customers` getrennt?** Admin-Mitarbeiter sind ebenfalls `users` (Auth), haben aber kein `customer`-Profil. Saubere Trennung der Rollen.

### Auth-Flows

**Email/Passwort:**
`POST /login` → Laravel Auth → PHP-Session-Cookie → Redirect `/mein-konto`; optional Cart-Merge

**Google OAuth:**
`/auth/google/redirect` → Google-Dialog → `/auth/google/callback` (Laravel Socialite: erstellt/findet User, setzt Session) → Redirect `/mein-konto`

**Registrierung:**
`POST /registrieren` → User + Customer (default_company_id + default_customer_group_id aus app_settings) + Adresse → Welcome-Email via `deferred_tasks` → direkt eingeloggt → Redirect `/mein-konto`

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| Laravel Session-Auth (kein Sanctum für Browser) | Standard-Laravel-Auth mit PHP-Sessions — einfacher, kein Token-Handling im Frontend nötig. Sanctum nur für Fahrer-API (Bearer Token). |
| Google OAuth serverseitig (Socialite) | Bereits installiert. Client Secret bleibt serverseitig. |
| `customers` separat von `users` | Admin-Mitarbeiter = `users` ohne `customer`-Profil; Fahrer = `users` ohne `customer`-Profil. |
| deferred_tasks für Welcome-Email | Email-Versand blockiert sonst den Request. Scheduler verarbeitet async. |

### Neue Pakete

| Paket | Status |
|---|---|
| `laravel/socialite` | Bereits installiert ✓ |
| `socialiteproviders/google` | Noch zu installieren |

## QA Test Results (Re-Test #2)

**Tested:** 2026-03-01 (Re-test after bug fixes)
**Previous Test:** 2026-03-01 (initial)
**App URL:** Laravel shop-tours (Blade-rendered, session-based)
**Tester:** QA Engineer (AI) -- Code Review + Static Analysis
**Method:** Full code review of controllers, models, views, routes, middleware, and services

---

### Previously Reported Bugs -- Fix Verification

| Bug ID | Issue | Status | Notes |
|--------|-------|--------|-------|
| BUG-1 | Hausnummer nicht als Pflichtfeld | FIXED | RegisterController Zeile 58: `'address.house_number' => ['required', ...]` + custom German message |
| BUG-2 | Welcome-Email fehlt | FIXED | WelcomeMail class + `Mail::to()->send()` in RegisterController Zeile 139 und SocialController Zeile 171 |
| BUG-3 | OAuth erstellt keine Adresse | FIXED | SocialController erstellt Placeholder-Adresse (Zeilen 136-147) |
| BUG-4 | OAuth erlaubt NULL customer_group_id | FIXED | SocialController hat jetzt identische Fallback-Logik + wirft RuntimeException wenn keine Gruppe existiert |
| BUG-5 | Login-Redirect zu Shop-Index statt /mein-konto | FIXED | LoginController Zeile 74: `'/mein-konto'` fuer Kunden |
| BUG-6 | Fehlermeldung bei doppelter Email auf Englisch | FIXED | `lang/de/validation.php` Zeile 175: `'email.unique' => 'Diese E-Mail-Adresse ist bereits registriert.'` |
| BUG-7 | OAuth Routen ohne guest Middleware | FIXED | web.php Zeilen 85-86: `middleware(['guest', 'throttle:oauth'])` |
| BUG-8 | role und active in User::$fillable | FIXED | User::$fillable enthaelt nur: first_name, last_name, email, password, google_id, avatar_url, company_id. role/active werden via direktes Property-Assignment gesetzt. |
| BUG-9 | OAuth ohne Rate Limiting | FIXED | `throttle:oauth` Middleware (10/min per IP) auf beiden OAuth-Routen |
| BUG-10 | HSTS Header fehlt | FIXED | SecurityHeaders.php Zeile 43: `'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains'` |
| BUG-11 | Register-Formular nicht responsive bei 375px | FIXED | register.blade.php verwendet `grid-cols-1 sm:grid-cols-2` und `grid-cols-1 sm:grid-cols-[1fr_6rem]` mit responsive Breakpoints |

**Alle 11 zuvor gemeldeten Bugs sind behoben.**

---

### Acceptance Criteria Status (Re-Test)

#### AC-1: Registrierung erfordert Pflichtfelder
- [x] Vorname (required, max:100) -- validated in RegisterController
- [x] Nachname (required, max:100) -- validated in RegisterController
- [x] Email (required, email, unique:users) -- validated in RegisterController
- [x] Passwort (required, confirmed, min 8 Zeichen) -- Password::min(8) used
- [x] Firmenname (optional) -- nullable validation, stored on customer record
- [x] Lieferadresse: Strasse (required) -- validated as address.street
- [x] Hausnummer (required, max:20) -- validated as address.house_number + custom German error message
- [x] PLZ (required) -- validated as address.zip
- [x] Stadt (required) -- validated as address.city
- **Result: PASS**

#### AC-2: Bestaetigungs-Email nach Registrierung
- [x] WelcomeMail Mailable-Klasse implementiert mit Markdown-Template (emails.welcome)
- [x] Email-Betreff: "Willkommen bei Kolabri Getraenke!"
- [x] RegisterController sendet Email nach erfolgreicher Registrierung (Zeile 139)
- [x] SocialController sendet Email bei neuen OAuth-Usern via wasRecentlyCreated (Zeile 170-172)
- [ ] **BUG-12:** Welcome-Email wird synchron gesendet (Mail::to()->send()), nicht via deferred_tasks. Bei langsamer Mailserver-Verbindung blockiert der HTTP-Response. Tech-Design spezifiziert "Welcome-Email via deferred_tasks" fuer asynchrone Verarbeitung. Funktionell korrekt, aber Performance-Risiko.
- **Result: PASS (funktionell korrekt, Performance-Hinweis)**

#### AC-3: Login per Email/Passwort mit generischer Fehlermeldung
- [x] Login-Route POST /anmelden validiert email + password
- [x] Falsche Credentials zeigen "E-Mail oder Passwort falsch." -- generisch, korrekt
- [x] Deaktivierter Account zeigt separate Meldung "Ihr Konto ist deaktiviert..."
- [x] Session wird regeneriert nach Login
- [x] @csrf Token im Formular vorhanden
- **Result: PASS**

#### AC-4: Google OAuth (Neuer User = Kunde, bestehender User = Login)
- [x] Redirect zu Google via Socialite implementiert
- [x] Callback: Bestehender User mit google_id wird eingeloggt
- [x] Callback: Bestehender User mit gleicher Email wird verknuepft (google_id gesetzt)
- [x] Callback: Neuer User wird als Kunde (role=kunde) angelegt
- [x] Neuer OAuth-User erhaelt company_id und customer_group_id aus AppSettings
- [x] OAuth-Registrierung erstellt Placeholder-Adresse (leer, muss vom Kunden vervollstaendigt werden)
- [x] Keine customer_group = NULL mehr moeglich: Fallback auf erste aktive Gruppe oder RuntimeException
- **Result: PASS**

#### AC-5: Passwort-Reset Flow
- [x] GET /passwort-vergessen zeigt Formular
- [x] POST /passwort-vergessen sendet Reset-Link via Laravel Password Broker
- [x] GET /passwort-reset/{token} zeigt neues-Passwort-Formular
- [x] POST /passwort-reset validiert Token + neues Passwort (min 8 Zeichen, confirmed)
- [x] Nach Reset: Redirect zu Login mit Erfolgsmeldung (deutsche Nachricht)
- [x] Abgelaufener Token: "Der Link zum Zuruecksetzen ist ungueltig oder abgelaufen. Bitte fordern Sie einen neuen Link an."
- [x] Rate Limiting auf POST /passwort-vergessen (throttle:5,1)
- **Result: PASS**

#### AC-6: Cart Merge nach Login
- [x] CartMergeService implementiert
- [x] Guest Session ID wird VOR session()->regenerate() erfasst
- [x] Merge-Strategie: Quantities werden addiert (nicht ersetzt) -- korrekt
- [x] Alte Guest-Session wird nach Merge geloescht
- [x] Cart Merge wird bei Login, Registrierung UND OAuth ausgefuehrt
- **Result: PASS**

#### AC-7: Neuer Kunde erhaelt company_id aus AppSetting
- [x] RegisterController: `AppSetting::getInt('default_company_id', 1)` -- Fallback auf 1
- [x] SocialController: `AppSetting::getInt('default_company_id', 1)` -- Fallback auf 1
- [x] company_id wird auf User UND Customer gesetzt
- **Result: PASS**

#### AC-8: Neuer Kunde erhaelt Standard-Kundengruppe
- [x] RegisterController: Liest `default_customer_group_id`, Fallback auf erste aktive Gruppe, Fehler wenn keine existiert
- [x] SocialController: Identische Logik -- Fallback auf erste aktive Gruppe, RuntimeException wenn keine existiert
- [x] Konsistentes Verhalten zwischen Email- und OAuth-Registrierung
- **Result: PASS**

#### AC-9: Logout beendet Session, Redirect zur Startseite
- [x] Auth::logout() aufgerufen
- [x] session()->invalidate() aufgerufen
- [x] session()->regenerateToken() aufgerufen (CSRF-Token Erneuerung)
- [x] Redirect zu '/' (Startseite)
- **Result: PASS**

#### AC-10: Rate Limiting auf Login (5 Versuche/Minute pro IP)
- [x] Custom RateLimiter 'login' definiert in AppServiceProvider
- [x] 5 Versuche pro Minute pro IP
- [x] Zusaetzlich 5 Versuche pro Minute pro Email (Anti-Credential-Stuffing) -- sogar besser als Spec
- [x] Route POST /anmelden hat middleware 'throttle:login'
- **Result: PASS**

#### AC-11: Redirect nach Login zur urspruenglichen Seite oder /mein-konto
- [x] `redirect()->intended()` wird verwendet -- Laravel-Standard fuer Redirect-after-Login
- [x] Default-Redirect fuer Kunden ist `/mein-konto` (LoginController Zeile 74)
- [x] Admin-User werden zu `admin.orders.index` weitergeleitet
- [ ] **BUG-13:** SocialController (Zeile 174-176) redirected Kunden zu `route('shop.index')` statt `/mein-konto`. Inkonsistenz: LoginController redirected korrekt zu /mein-konto, aber OAuth-Login redirected zur Startseite.
- **Result: PARTIAL PASS (nur Email-Login korrekt, OAuth-Login inkonsistent)**

---

### Edge Cases Status (Re-Test)

#### EC-1: Google-Account mit bereits vorhandener Email
- [x] SocialController prueft erst google_id, dann email. Bei Email-Match wird google_id auf bestehendem User gesetzt und eingeloggt. Korrekt implementiert.

#### EC-2: Registrierung mit bereits vergebener Email
- [x] Validation Rule `unique:users,email` vorhanden
- [x] Deutsche Fehlermeldung: "Diese E-Mail-Adresse ist bereits registriert." (lang/de/validation.php custom rule)

#### EC-3: Passwort-Reset-Link abgelaufen
- [x] Laravel Password Broker gibt `INVALID_TOKEN` zurueck bei abgelaufenem Token
- [x] Deutsche Meldung mit Hinweis neuen Link anzufordern
- [x] Link zurueck zur Anmeldung vorhanden

#### EC-4: Gast + Auth haben beide Artikel im Warenkorb
- [x] CartMergeService addiert Quantities korrekt
- [x] Bestehende Auth-Cart-Artikel bleiben erhalten

#### EC-5: Google OAuth fehlgeschlagen (Google nicht erreichbar)
- [x] try/catch in SocialController::callback() faengt Throwable
- [x] Redirect zu Login-Seite mit deutscher Fehlermeldung
- [x] Login-Seite zeigt weiterhin Email-Login-Formular als Fallback

#### EC-6: Deaktivierter Nutzer (active = false)
- [x] LoginController: nur aktive User koennen sich einloggen
- [x] LoginController: Spezifische deutsche Meldung fuer deaktivierte Accounts
- [x] SocialController: Prueft active-Flag, wirft RuntimeException, zeigt spezifische Meldung

---

### Security Audit Results (Re-Test)

#### Auth Bypass
- [x] Login-Route (POST /anmelden) hat `guest` Middleware
- [x] Register-Route hat `guest` Middleware
- [x] Passwort-Reset-Routen haben `guest` Middleware
- [x] Google OAuth Routen haben `guest` + `throttle:oauth` Middleware
- [x] Geschuetzte Routen (/mein-konto, /kasse) haben `auth` Middleware
- [x] Admin-Routen haben `admin` + `company` Middleware

#### company_id Isolation
- [x] RegisterController setzt company_id auf User und Customer aus AppSetting (serverseitig)
- [x] SocialController setzt company_id auf User und Customer aus AppSetting (serverseitig)
- [x] company_id nicht aus User-Input -- nicht manipulierbar

#### Mass Assignment
- [x] User::$fillable enthaelt NUR: first_name, last_name, email, password, google_id, avatar_url, company_id
- [x] `role` und `active` sind NICHT in $fillable -- werden via direktes Property-Assignment gesetzt
- [x] RegisterController: `$user->role = User::ROLE_KUNDE; $user->active = true; $user->save();`
- [x] SocialController: Identisches Pattern
- [x] Customer Model hat explizite $fillable
- [x] Address Model hat explizite $fillable

#### Input Injection (XSS / SQL)
- [x] Alle Inputs werden via Laravel Validation validiert
- [x] Blade Templates verwenden `{{ }}` (escaped output) -- kein XSS
- [x] Eloquent ORM mit parameterisierten Queries -- kein SQL Injection
- [x] old() Werte in Formularen sind escaped durch Blade

#### CSRF Protection
- [x] Alle POST-Formulare haben @csrf Directive
- [x] Laravel VerifyCsrfToken Middleware ist global aktiv (Standard)

#### Rate Limiting
- [x] Login: 5/min per IP + 5/min per Email
- [x] Registrierung: throttle:10,1 (10/min)
- [x] Passwort-Reset-Link-Anfrage: throttle:5,1
- [x] Google OAuth: throttle:oauth (10/min per IP)

#### Security Headers
- [x] X-Content-Type-Options: nosniff
- [x] X-Frame-Options: SAMEORIGIN
- [x] Referrer-Policy: strict-origin-when-cross-origin
- [x] Content-Security-Policy mit frame-ancestors 'none'
- [x] Strict-Transport-Security: max-age=31536000; includeSubDomains

#### Secrets Exposure
- [x] User::$hidden enthaelt 'password' und 'remember_token'
- [x] Google Client Secret ist in .env (nicht im Code)
- [x] Keine API-Keys in Views oder Responses sichtbar

#### Session Security
- [x] Session wird nach Login regeneriert (session fixation prevention)
- [x] Session wird nach Logout invalidiert + CSRF-Token regeneriert
- [x] Session wird nach Registrierung regeneriert

#### NEW: CSP Audit
- [ ] **BUG-14:** Content-Security-Policy enthaelt `'unsafe-inline'` und `'unsafe-eval'` in script-src. Dies schwaecht den XSS-Schutz erheblich. Fuer die Auth-Seiten (einfache HTML-Formulare) waere eine striktere CSP ohne unsafe-inline/unsafe-eval moeglich. Aktuell ist das vermutlich fuer den Admin-Bereich noetig, sollte aber per-Route konfiguriert werden.

---

### Cross-Browser Testing
*Hinweis: Blade Templates sind Server-gerendert, Standard-HTML-Formulare. Kein JavaScript-Framework involviert.*

- [x] Chrome: Standard-HTML-Formulare, keine Browser-spezifischen Features
- [x] Firefox: Kompatibel (Standard-HTML, Tailwind CSS)
- [x] Safari: Kompatibel (Standard-HTML, Tailwind CSS)
- Alle Auth-Views verwenden nur Standard-HTML-Elemente (input, form, button, a) mit Tailwind-Klassen. Keine JS-Abhaengigkeiten fuer die Kernfunktionalitaet.

### Responsive Testing
*Alle 4 Auth-Views verwenden `max-w-md` Container mit `px-4` Padding.*

- [x] 375px (Mobile): Register-Formular nutzt `grid-cols-1 sm:grid-cols-2` -- Felder stacken sich korrekt auf kleinen Screens
- [x] 768px (Tablet): Guter Platz, max-w-md zentriert, 2-spaltige Felder nebeneinander
- [x] 1440px (Desktop): Zentriert, angemessene Breite

---

### New Bugs Found (Re-Test)

#### BUG-12: Welcome-Email synchron statt via deferred_tasks
- **Severity:** Low
- **Steps to Reproduce:**
  1. Registriere dich unter /registrieren
  2. Wenn Mailserver langsam ist: HTTP-Response blockiert bis Email gesendet
  3. Erwartung (laut Tech-Design): Email wird async via deferred_tasks verarbeitet
  4. Tatsaechlich: `Mail::to()->send()` blockiert synchron
- **Betroffene Datei:** `app/Http/Controllers/Auth/RegisterController.php` Zeile 139, `app/Http/Controllers/Auth/SocialController.php` Zeile 171
- **Priority:** Nice to have (funktionell korrekt, nur Performance-Optimierung)

#### BUG-13: OAuth-Login Redirect inkonsistent mit Email-Login
- **Severity:** Low
- **Steps to Reproduce:**
  1. Melde dich erstmals oder erneut via Google OAuth an (als Kunde, nicht Admin)
  2. Erwartung: Redirect zu /mein-konto (wie bei Email-Login)
  3. Tatsaechlich: Redirect zu route('shop.index') (Startseite)
- **Betroffene Datei:** `app/Http/Controllers/Auth/SocialController.php` Zeile 174-176
- **Priority:** Fix in next sprint

#### BUG-14: CSP mit unsafe-inline/unsafe-eval in script-src
- **Severity:** Low
- **Steps to Reproduce:**
  1. Pruefe Response-Headers auf einer Auth-Seite
  2. Content-Security-Policy script-src enthaelt 'unsafe-inline' und 'unsafe-eval'
  3. Erwartung: Strikte CSP fuer einfache HTML-Formulare
  4. Tatsaechlich: Globale CSP ist zu permissiv fuer Auth-Seiten
- **Betroffene Datei:** `app/Http/Middleware/SecurityHeaders.php` Zeile 46
- **Priority:** Nice to have (per-Route CSP fuer verschiedene Bereiche)

---

### Regression Check
- Keine Features mit Status "Deployed" in INDEX.md vorhanden -- kein Regressionstest erforderlich.
- Cart-Funktionalitaet (PROJ-3, Status: In Progress) wird durch CartMergeService beruehrt -- Integration muss bei PROJ-3 QA erneut geprueft werden.

---

### Summary (Re-Test)
- **Previously Reported Bugs:** 11/11 FIXED
- **Acceptance Criteria:** 10/11 bestanden, 1 teilweise bestanden (AC-11: OAuth-Redirect inkonsistent)
- **Edge Cases:** 6/6 korrekt behandelt
- **New Bugs Found:** 3 total (0 critical, 0 high, 0 medium, 3 low)
- **Security:** Solide. Alle zuvor gemeldeten Sicherheitsluecken behoben. Rate Limiting, HSTS, Mass-Assignment-Schutz alle korrekt.
- **Production Ready:** **JA**
- **Empfehlung:** Alle kritischen und hohen Bugs sind behoben. Die 3 verbleibenden Low-Bugs (synchrone Email, OAuth-Redirect-Inkonsistenz, CSP-Feintuning) sind nicht deployment-blockierend und koennen im naechsten Sprint adressiert werden.

## Deployment
_To be added by /deploy_
