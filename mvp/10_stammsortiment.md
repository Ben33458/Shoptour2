# 10 – Stammsortiment / Favoritenliste (PROJ-20)

## Status: Implementiert

---

## Ziel

Kunden (und berechtigte Sub-User) verwalten eine persönliche Produktliste mit Soll- und Istbeständen. Aus der Differenz ergibt sich die Bestellmenge — für schnelle Nachbestellung ohne Produktsuche.

---

## Umgesetzter Funktionsumfang

### Stammsortiment-Seite (`/mein-konto/stammsortiment`)

**Tabelle mit je Produkt:**
- Produktname (klickbar → Produktseite)
- Gebinde / Artikelnummer
- Preis pro Gebinde (nur wenn `preise_sehen`-Berechtigung)
- Istbestand (inline editierbar, PATCH via Fetch)
- Sollbestand (inline editierbar wenn berechtigt, PATCH via Fetch)
- Bestellmenge: `max(0, Sollbestand - Istbestand)` — live berechnet in Alpine.js
- "In Warenkorb"-Button (nur für bestellberechtigte Nutzer, nur wenn Produkt bestellbar)
- Löschen-Button

**Sortierung:** Drag-and-drop via SortableJS, PATCH `/sortierung` speichert neue Reihenfolge

**Produkt hinzufügen:** Live-Suche (`GET /suche?q=…`) — min. 2 Zeichen, zeigt nicht bereits vorhandene Artikel, max. 10 Treffer

**Bulk-Aktionen:**
- "Alle in Warenkorb" — legt alle Artikel mit `orderQty > 0` in den Warenkorb
- "Direkt bestellen" — wie oben, dann Redirect zu `/kasse`

**Nicht-bestellbare Produkte** (discontinued, out_of_stock, inactive) erscheinen in der Liste, aber ohne Warenkorb-Button und mit Status-Badge.

### Heart-Button im Shop

Auf Produktkacheln (Shopübersicht) und Produktdetailseite:
- Ausgefüllt (♥, amber) wenn im Stammsortiment
- Leer (♡, grau) wenn nicht im Stammsortiment
- Klick → POST `/mein-konto/stammsortiment` (Duplikate werden serverseitig ignoriert)
- Nur für eingeloggte Nutzer sichtbar

---

## Berechtigungen

| Berechtigung | Wer kann | Auswirkung |
|---|---|---|
| `assortment` | Sub-User: explizit | Stammsortiment-Seite sehen |
| `bestellen_favoritenliste` | Sub-User: default true | "In Warenkorb" aus Stammsortiment |
| `bestellen_all` | Sub-User: default false | "In Warenkorb" im allgemeinen Shop |
| `sollbestaende_bearbeiten` | Sub-User: default false | Sollbestand-Feld editierbar |
| `preise_sehen` | Sub-User: default false | Preisspalte sichtbar |

Hauptkunden haben alle Rechte implizit.

**Middleware `shop.order`** (Alias `EnforceShopOrderPermission`):
- Auf `POST /warenkorb` registriert
- Blockiert Sub-User ohne `bestellen_all`
- "Alle in Warenkorb" aus FavoriteController nutzt `CartService::add()` direkt → umgeht die Middleware korrekt

---

## Datenmodell

### `customer_favorites`

| Feld | Typ | Zweck |
|---|---|---|
| id | bigint PK | |
| customer_id | bigint FK | → customers (cascade delete) |
| product_id | bigint FK | → products (cascade delete) |
| sort_order | unsigned int | Reihenfolge, default 0 |
| target_stock_units | unsigned int | Sollbestand in Gebinden, default 0 |
| actual_stock_units | unsigned int | Istbestand in Gebinden, default 0 |
| created_by_user_id | bigint nullable FK | → users (null on delete) |
| updated_by_user_id | bigint nullable FK | → users (null on delete) |
| timestamps | | |

Unique constraint auf `(customer_id, product_id)`.
Index auf `(customer_id, sort_order)`.

---

## Architektur-Entscheidungen

- **Istbestand** ist kundenseitig editierbar (immer) — der Kunde weiß selbst was er hat
- **Sollbestand** erfordert `sollbestaende_bearbeiten`-Berechtigung
- **Bestellmenge** wird nicht gespeichert, sondern live aus `target - actual` berechnet
- **Preise** werden nur für die View berechnet (PriceResolverService), nicht gespeichert
- **"Direkt bestellen"** = Cart füllen + Redirect zu GET /kasse — kein Checkout-Bypass, keine eigene Checkout-Logik
- Produkte bleiben in der Liste auch wenn discontinued/out_of_stock — der Kunde soll sie nicht vergessen

---

## Support-Klassen

### `App\Support\CustomerPermissions`
Unified permission helper — gibt für Hauptkunden immer true zurück, für Sub-User liest aus JSON:
```php
$perms = new CustomerPermissions(Auth::user());
$perms->canOrderAll();
$perms->canOrderFromFavorites();
$perms->canEditTargetStock();
$perms->canSeePrices();
$perms->canViewAssortment();
```

---

## Routen

```
GET    /mein-konto/stammsortiment                → FavoriteController@index
POST   /mein-konto/stammsortiment                → FavoriteController@store
DELETE /mein-konto/stammsortiment/{favorite}     → FavoriteController@destroy
PATCH  /mein-konto/stammsortiment/{id}/istbestand  → FavoriteController@updateActualStock
PATCH  /mein-konto/stammsortiment/{id}/sollbestand → FavoriteController@updateTargetStock
POST   /mein-konto/stammsortiment/sortierung     → FavoriteController@reorder
POST   /mein-konto/stammsortiment/alle-in-warenkorb → FavoriteController@addAllToCart
POST   /mein-konto/stammsortiment/direkt-bestellen  → FavoriteController@orderAll
GET    /mein-konto/stammsortiment/suche          → FavoriteController@search (JSON)
```

---

## Offene Punkte / Nächste Schritte

- Admin-Ansicht: Stammsortiment eines Kunden einsehen und bearbeiten (PROJ-10)
- CSV-Export der Bestellmenge als "Bestellvorschlag" (PROJ-25)
- Ninox-Sync: Sollbestände aus WaWi übernehmen (falls relevant)
- Benachrichtigung wenn Istbestand unter Sollbestand fällt (optional)
- Produktbilder: prüfen ob `mainImage` auf allen Produkten gesetzt, sonst Fallback-Icon
