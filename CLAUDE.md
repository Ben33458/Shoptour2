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
- **Email:** Laravel Mail (SMTP) — **immer `<x-mail::message>` verwenden** (siehe unten)

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

Alle Tabellen mit Pr�fix `wawi_` oder `ninox_` sind reine Sync-Importtabellen aus externen Systemen. Sie sind nicht die Stelle f�r eigene Projektlogik. Diese Tabellen k�nnen jederzeit durch einen neuen Import �berschrieben werden. Deshalb dort keine manuellen �nderungen, keine internen Status, keine zus�tzlichen Gesch�ftslogiken und keine projektkritischen Erweiterungen einbauen. Alles, was intern gebraucht wird, muss in separaten Projekttabellen liegen.

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

## Modell-Feldnamen (Achtung: nicht-standard)

| Modell | Korrekt | NICHT |
|---|---|---|
| `Product` | `produktname` | `name` |
| `Product` | `artikelnummer` | `sku` |
| `ProductImage` | `Storage::url($image->path)` | `$image->url` |

## Sub-User Auflösungsmuster

In **allen** Shop-Controllern muss `requireCustomer()` / `resolveCustomer()` Sub-User korrekt auflösen:

```php
if ($user->isSubUser()) {
    return $user->subUser?->parentCustomer;
}
return $user->customer; // nur für role=kunde
```

Niemals nur `$user->isKunde()` prüfen — Sub-User (role=`sub_user`) werden dabei stillschweigend abgelehnt.

Betroffene Controller: `AccountController`, `CheckoutController`, `ShopController`, `FavoriteController`, `CartController`.

## Preisanzeige-Modus

Reihenfolge: Kunde-eigene Einstellung → Kundengruppe → Fallback Brutto.

```php
$priceDisplayMode = $customer?->price_display_mode
    ?: ($customerGroup?->price_display_mode ?? CustomerGroup::DISPLAY_BRUTTO);
```

Kunden können ihren Modus selbst unter `/mein-konto/profil` ändern (`customers.price_display_mode`).

## CartService — direkt nutzen, kein HTTP

Für programmatisches Hinzufügen zum Warenkorb (z. B. aus FavoriteController):

```php
$this->cart->add($productId, $qty, $user); // CartService direkt
```

NICHT über HTTP `POST /warenkorb` — das würde die `shop.order`-Middleware auslösen.

## Middleware `shop.order`

Alias für `EnforceShopOrderPermission`. Auf `POST /warenkorb` aktiv.
Blockiert Sub-User, die nur `bestellen_favoritenliste` (nicht `bestellen_all`) haben.
Direktbestellungen aus dem Stammsortiment gehen am Warenkorb-Route vorbei → kein Problem.

## Sub-User Permissions — neue Felder erweitern

Wenn neue Berechtigungen hinzukommen, müssen **beide** Stellen aktualisiert werden:
1. `SubUser::defaultPermissions()` — neues Feld + Defaultwert
2. `SubUserController::invite()` und `updatePermissions()` — Validierung + Zuweisung
3. `CustomerPermissions` (Support-Klasse) — neue Methode
4. `sub-users/index.blade.php` — Checkbox + Badge + `openPermModal()` JS

## E-Mail-Templates — Pflichtkonvention

**Jede neue E-Mail MUSS `<x-mail::message>` als äußeren Wrapper verwenden.**

Das Layout (`resources/views/vendor/mail/html/`) enthält Logo, Branding und Footer automatisch.

### Mailable-Klasse
```php
return new Content(
    markdown: 'emails.mein-template',  // NICHT view:
);
```

### Template (`resources/views/emails/mein-template.blade.php`)
```blade
<x-mail::message>

Hallo {{ $name }},

Dein Inhalt hier. Markdown funktioniert.

<x-mail::button :url="$url">
Button-Text
</x-mail::button>

<x-mail::table>
| Spalte 1 | Spalte 2 |
|:---|---:|
| Wert | 1,00 € |
</x-mail::table>

Mit freundlichen Grüßen,
{{ config('app.name') }}

</x-mail::message>
```

**Niemals** rohe `<!DOCTYPE html>`-Templates ohne `<x-mail::message>` anlegen — diese umgehen das Branding komplett.

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
