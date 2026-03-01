# PROJ-2: Produktkatalog

## Status: Planned
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- Requires: PROJ-6 (Preisfindung) — für korrekte Preisanzeige je Kundengruppe
- Requires: PROJ-7 (Pfand-System) — für Pfandanzeige pro Produkt

## Beschreibung
Öffentlich zugängliche Produktübersicht und Detailseite. Gäste sehen Preise einer konfigurierbaren Standard-Preisgruppe. Eingeloggte Kunden sehen ihre persönlichen Preise. Produkte können nach Kategorie, Warengruppe, Brand und Stichwort gefiltert/gesucht werden.

## User Stories
- Als Gast möchte ich alle verfügbaren Produkte und deren Preise sehen, ohne mich anmelden zu müssen.
- Als Gast möchte ich Produkte nach Kategorie, Brand oder Stichwort filtern/suchen.
- Als eingeloggter Kunde möchte ich meine individuellen Preise (Kunden- oder Gruppenpreis) sehen.
- Als Kunde möchte ich auf der Detailseite alle relevanten Produktinfos sehen (Inhalt, Gebinde, Pfand, LMIV, Bilder).
- Als Admin möchte ich die Standard-Preisgruppe für nicht angemeldete Besucher konfigurieren können.
- Als Admin möchte ich Produkte aktivieren/deaktivieren, ohne sie zu löschen.

## Acceptance Criteria
- [ ] Produktliste zeigt: Produktbild, **Marke + Art + Gebinde** (z.B. „Elisabethenquelle Pur 12x0,7 Glas"), Preis (netto oder brutto je Kundengruppen-Setting), Pfandbetrag
- [ ] Preise für Gäste werden aus der konfigurierten Gast-Kundengruppe (Admin-Einstellung) berechnet
- [ ] Eingeloggte Kunden sehen ihren individuellen Preis (PROJ-6 Preisfindung)
- [ ] **Pfandanzeige je Kundengruppe:**
  - B2C-Kunden / Gäste: Pfand als Bruttobetrag (z.B. „zzgl. 3,42 € Pfand")
  - B2B-Kunden: Pfand als Nettobetrag (z.B. „zzgl. 3,42 € Pfand (netto, zzgl. MwSt.)")
- [ ] Filterung nach: Kategorie, Warengruppe, Gebindegröße, Brand (URL-Parameter, kombinierbar)
- [ ] Volltextsuche über Produktname, Artikelnummer, Barcode
- [ ] Produkt-Detailseite zeigt: alle Bilder (Galerie), vollständige Beschreibung, LMIV-Informationen (Nährwerte, Allergene), Pfanddetails, Verfügbarkeitsstatus
- [ ] Pagination oder Infinite Scroll auf der Produktliste
- [ ] Deaktivierte Produkte (`active = false`) sind nicht sichtbar
- [ ] Produkte mit `availability_mode = 'stock_based'` und Bestand = 0 werden als „Nicht verfügbar" angezeigt
- [ ] „In den Warenkorb"-Button direkt in der Listenansicht (mit Mengenauswahl)
- [ ] Responsive: Mobile (375px), Tablet (768px), Desktop (1440px)
- [ ] Ladezeit < 2s für Produktliste (max. 50 Produkte per Page)

## Edge Cases
- Produkt ohne Bild → Platzhalter-Bild anzeigen
- Produkt ohne aktuellen Preis für die Gast-Gruppe → Preis ausblenden, „Preis auf Anfrage" anzeigen
- Suche ergibt keine Treffer → Leerzustand mit Hinweis und „Filter zurücksetzen"-Button
- Produkt ist Bundle → Bundle-Komponenten auf Detailseite als Liste anzeigen
- Produkt hat mehrere Barcodes/EAN → alle auf Detailseite sichtbar
- Kategorie-Hierarchie: verschachtelte Kategorien müssen korrekt gefiltert werden (Unterkategorie ⊂ Oberkategorie)

## Technical Requirements
- Performance: Produktliste muss gecacht werden (Next.js `unstable_cache` oder Laravel-Cache)
- Bilder: Optimierte Bilder (Next.js `<Image>` Komponente)
- SEO: Produktseiten haben `<title>`, `<meta description>`, strukturierte Daten (Schema.org Product)
- URLs: `/produkte`, `/produkte/{slug}` (Slug aus Produktname generiert)

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur (UI-Baum)

```
/produkte/                       ← Produktliste (öffentlich)
├── Filter-Sidebar (Alpine.js)
│   ├── Kategorie-Baum (verschachtelt)
│   ├── Warengruppe-Filter
│   ├── Brand-Filter
│   └── Gebindegröße-Filter
├── Produktraster (Karten)
│   ├── Produktbild (Platzhalter wenn kein Bild)
│   ├── Name (Brand + Linie + Gebinde)
│   ├── Preis (netto oder brutto je Einstellung)
│   ├── Pfand-Hinweis (B2C: Brutto / B2B: netto zzgl. MwSt.)
│   └── [In den Warenkorb] + Mengenauswahl
└── Pagination

/produkte/{slug}                 ← Produktdetail
├── Bildergalerie (Alpine.js Slider)
├── Produktname, Artikelnummer
├── Preis + Pfand
├── Mengenauswahl + [In den Warenkorb]
├── Beschreibung / Inhalt / Gebinde-Info
├── LMIV-Block (Nährwerte, Allergene — aus aktiver lmiv_version)
└── Bundle-Komponenten (wenn is_bundle)
```

### Datenmodell (Erweiterungen)

```
products (bereits in PROJ-9)
└── + slug (VARCHAR unique)  ← URL-freundlicher Name, generiert aus Brand+Linie+Gebinde

Kein eigenes Datenmodell — Katalog liest aus:
  products → product_lines → brands → gebinde → pfand_sets
           → categories → warengruppen
           → product_images, lmiv_versions
           → PriceResolverService (PROJ-6)
           → PfandCalculator (PROJ-7)
```

### Slug-Generierung

```
"Elisabethenquelle Pur 12x0,7 Glas"
  → "elisabethenquelle-pur-12x07-glas"

Bei Namenskollision: Artikelnummer angehängt:
  → "elisabethenquelle-pur-12x07-glas-1234"
```

### Preisanzeige-Logik

```
Gast-Besucher:
  → Gruppe = app_settings['guest_customer_group_id']
  → PriceResolverService::resolve() mit Gast-Gruppe
  → price_display_mode der Gast-Gruppe (netto/brutto)

Eingeloggter Kunde:
  → PriceResolverService::resolve() mit echtem Kunden
  → price_display_mode des Kunden

Pfand-Anzeige:
  → is_business = false: "zzgl. 3,42 € Pfand"
  → is_business = true:  "zzgl. 3,42 € Pfand (netto, zzgl. MwSt.)"
```

### Performance

Produktliste wird mit `Laravel Cache::remember()` gecacht:
- Cache-Key: `products.{group_id}.{filter_hash}.page{n}`
- TTL: 5 Minuten
- Cache-Invalidierung: beim Admin-Speichern von Produkten

Filter-URL-Parameter: `/produkte?kategorie=3&brand=7&gebinde=kasten` — kein JavaScript-Routing nötig (Standard-GET-Formulare).

### SEO

Jede Produktseite erhält:
- `<title>`: Produktname + Shopname
- `<meta name="description">`: Kurztext
- `<link rel="canonical">`: kanonische URL (kein doppelter Inhalt bei Filtern)
- Schema.org `Product`-Markup (Blade-Template, server-seitig)

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| Slug auf Produkt (nicht UUID in URL) | Lesbare, SEO-freundliche URLs ohne zusätzlichen Redirect |
| Server-seitiges Caching (Laravel Cache) | Keine Redis nötig; File-Cache reicht für Webspace |
| Alpine.js für Filter-Sidebar | Kein Seitenreload bei Filteränderung nötig — Alpine sendet GET-Request via `fetch()` und ersetzt Produktraster |
| Preisberechnung serverseitig | Manipulation-sicher; kein Preis kommt aus dem Browser |

### Neue Controller

```
Shop\ProduktController   ← index (Liste), show (Detail)
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
