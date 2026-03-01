# PROJ-1: Authentifizierung

## Status: Planned
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

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
