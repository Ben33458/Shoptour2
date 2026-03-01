# PROJ-3: Warenkorb

## Status: Planned
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- Requires: PROJ-2 (Produktkatalog) — Produkte müssen vorhanden sein
- Requires: PROJ-6 (Preisfindung) — Preise im Warenkorb werden live berechnet
- Requires: PROJ-7 (Pfand-System) — Pfand wird pro Position angezeigt und summiert

## Beschreibung
Session-basierter Warenkorb für Gäste und eingeloggte Kunden. Zeigt Positionen, Mengen, Preise, Pfand und Gesamtsummen. Bei Login wird der Gast-Warenkorb mit dem Auth-Account zusammengeführt.

## User Stories
- Als Gast möchte ich Produkte in den Warenkorb legen, ohne mich registrieren zu müssen.
- Als Kunde möchte ich die Menge von Positionen ändern oder Positionen entfernen.
- Als Kunde möchte ich die Gesamtsumme inkl. Pfand auf einen Blick sehen.
- Als eingeloggter Kunde möchte ich meinen persönlichen Preis im Warenkorb sehen (nicht den Gast-Preis).
- Als Gast möchte ich nach dem Einloggen meinen Warenkorb nicht verlieren.

## Acceptance Criteria
- [ ] Produkte können aus der Produktliste und Detailseite in den Warenkorb gelegt werden (mit Mengenangabe)
- [ ] Warenkorb zeigt pro Position: Produktbild, Name, Artikelnummer, Stückpreis (netto oder brutto je Einstellung), Pfand/Stück, Menge, Zeilengesamtpreis
- [ ] Gesamtübersicht: Warenwert netto, Pfand gesamt (brutto), MwSt. aufgeschlüsselt, Gesamtbetrag brutto
- [ ] Menge kann direkt im Warenkorb geändert werden (Input-Feld oder +/- Buttons)
- [ ] Position kann aus Warenkorb entfernt werden
- [ ] „Warenkorb leeren"-Funktion
- [ ] Warenkorb-Icon im Header zeigt Anzahl der Positionen (Badge)
- [ ] Gast-Warenkorb wird in PHP-Session gespeichert
- [ ] Bei Login: Gast-Artikel werden dem Auth-Warenkorb hinzugefügt (nicht ersetzt); doppelte Produkte werden summiert
- [ ] Eingeloggte Kunden sehen ihren individuellen Preis (aus PROJ-6 Preisfindung)
- [ ] Warenkorb bleibt nach Seiten-Reload erhalten (Session-persistent)
- [ ] Mini-Warenkorb / Dropdown im Header (letzte Artikel + Gesamtsumme + Zur-Kasse-Button)
- [ ] „Weiter einkaufen"-Link zurück zum Katalog

## Edge Cases
- Produkt wird deaktiviert, während es im Warenkorb liegt → Beim nächsten Aufruf Warnung anzeigen, Position markieren, Checkout blockieren
- Menge auf 0 setzen → Position wird entfernt
- Menge übersteigt Lagerbestand (bei stock_based Produkten) → Warnung, maximale Menge setzen
- Warenkorb ist leer → Leerzustand mit Link zum Katalog
- Kundengruppe des eingeloggten Nutzers ändert sich → Preise im Warenkorb werden beim nächsten Aufruf neu berechnet
- Bundle-Produkt im Warenkorb → Bundle als eine Position mit enthaltenen Komponenten als Info

## Technical Requirements
- Warenkorb-Daten: Laravel Session (Gast) + DB-Tabelle `carts` (Auth, optional für Persistenz)
- Preisberechnung immer serverseitig (nie clientseitig, um Manipulation zu verhindern)
- CSRF-Schutz auf allen Warenkorb-Mutationen

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur (UI-Baum)

```
/warenkorb                        ← Vollseiten-Warenkorb
├── Positions-Liste
│   ├── Produktbild, Name, Artikelnummer
│   ├── Stückpreis + Pfand/Stück
│   ├── Mengeneingabe (+/- oder Direkteingabe, Alpine.js)
│   ├── Zeilenpreis (netto oder brutto)
│   └── [Entfernen]-Button
├── Aktionsleiste
│   ├── [Warenkorb leeren]
│   └── [Weiter einkaufen] → /produkte
└── Preis-Zusammenfassung
    ├── Warenwert netto
    ├── Pfand gesamt (brutto)
    ├── MwSt. aufgeschlüsselt (19% / 7% / ...)
    ├── Gesamtbetrag brutto
    └── [Zur Kasse] → /checkout

[Header: Mini-Warenkorb Dropdown] (Alpine.js)
├── Letzte 3 Artikel (Bild, Name, Preis)
├── Gesamtbetrag
└── [Zur Kasse]-Button
```

### Datenmodell

```
carts  [Auth-Nutzer: DB-persistiert]
├── id, user_id → users (nullable)
├── session_id (VARCHAR, nullable)  ← Gast-Identifikation
└── company_id

cart_items
├── id, cart_id → carts
├── product_id → products
├── quantity
└── company_id

Preise werden NICHT in cart_items gespeichert — immer live aus PriceResolverService berechnet.
```

### Gast vs. Auth Warenkorb

```
Gast:
  → cart_id in PHP-Session gespeichert (session('cart_id'))
  → cart.user_id = NULL, cart.session_id = session()->getId()

Nach Login (CartMergeService):
  → Gast-cart_items werden in Auth-Warenkorb übertragen
  → Gleiche Produkte: Mengen werden addiert (nicht ersetzt)
  → Gast-Cart wird danach gelöscht
```

### Preisberechnung (serverseitig)

Beim Laden des Warenkorbs werden Preise live neu berechnet:
```
für jede cart_item:
  preis = PriceResolverService::resolve($product, $customer)
  pfand = PfandCalculator::totalForGebinde($product->gebinde)
  zeilensumme = preis × quantity  (Integer-Arithmetik)
```

Bei veralteten Preisen (nach Preisänderung durch Admin) zeigt der Warenkorb automatisch die aktuellen Preise.

### Alpine.js Mini-Warenkorb

Der Header-Dropdown wird als Alpine.js-Komponente geführt:
- Beim Hinzufügen eines Artikels via `fetch(POST /warenkorb/add)` → Antwort enthält aktualisierten Badge-Count + Mini-Cart HTML
- Kein Seitenreload nötig für Badge-Update

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| Keine Preise in `cart_items` | Verhindert veraltete Preise im Warenkorb; aktuelle Preise kommen immer serverseitig |
| DB-Persistenz für Auth-Nutzer | Warenkorb bleibt auch nach Session-Ablauf erhalten |
| Session-Warenkorb für Gäste | Keine Registrierung nötig; Standard-Laravel-Session |
| `CartMergeService` | Saubere Trennung der Merge-Logik; testbar unabhängig von Controllern |

### Neue Controller / Services

```
Shop\WarenkorbController    ← index, add, update, remove, clear
CartMergeService            ← mergeGuestCart($guestCartId, $authUser)
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
