# PROJ-9: Admin: Stammdaten

## Status: Planned
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- Requires: PROJ-6 (Preisfindung) — Preisstrukturen werden hier konfiguriert
- Requires: PROJ-7 (Pfand-System) — PfandItems und PfandSets werden hier verwaltet

## Beschreibung
Verwaltung aller Produkt-Stammdaten im Admin-Bereich.

### Produktstruktur (kanonisch)
Ein Produkt setzt sich zusammen aus:
```
Marke (Brand)  +  Art (Produktlinie)  +  Gebinde
────────────────────────────────────────────────────
Elisabethenquelle    Pur                12x0,7 Glas
Schmucker            Pils               24x0,33 Glas
```
- **Marke (Brand):** Hersteller / Dachmarke
- **Art (Produktlinie):** Sorte / Variante innerhalb der Marke
- **Gebinde:** Verpackungseinheit (Anzahl × Inhalt × Material)
- **Pfand ist am Gebinde hinterlegt** — nicht am Produkt selbst. Gleiches Gebinde → gleicher Pfandwert.

Außerdem verwaltet: Kategorien (verschachtelt), Warengruppen (verschachtelt), PfandItems + PfandSets, Kundengruppen, MwSt.-Sätze. CSV-Bulk-Upload für Produkte. Alle Entitäten können aktiviert/deaktiviert werden.

## User Stories
- Als Admin möchte ich Produkte anlegen, bearbeiten, deaktivieren und Produktbilder verwalten.
- Als Admin möchte ich Brands, Produktlinien, Kategorien und Warengruppen (verschachtelt) verwalten.
- Als Admin möchte ich Gebinde mit Pfand-Verknüpfung anlegen und bearbeiten.
- Als Admin möchte ich PfandItems und PfandSets (Baum) verwalten.
- Als Admin möchte ich Kundengruppen mit Preisanpassungen und Zahlungsmethod-Freischaltungen verwalten.
- Als Admin möchte ich MwSt.-Sätze verwalten.
- Als Admin möchte ich LMIV-Daten (Nährwerte, Allergene) versioniert pro Produkt hinterlegen.
- Als Admin möchte ich Produktbilder hochladen und sortieren.
- Als Admin möchte ich Barcodes/EAN pro Produkt mit Gültigkeitszeitraum verwalten.
- Als Admin möchte ich Produkt-Preise (Kundengruppen-Preise) direkt auf der Produktdetailseite pflegen.

## Acceptance Criteria
- [ ] **Produkte:** CRUD mit Feldern: Artikelnummer, **Marke (Brand)**, **Art (Produktlinie)**, **Gebinde** (mit zugehörigem Pfand), Kategorie, Warengruppe, MwSt.-Satz, Basispreis (netto, milli), Verfügbarkeitsmodus (immer/lagerbasiert), Bundle-Flag, aktiv
  - **Produktname:** wird automatisch aus `Brand + Produktlinie + Gebinde` generiert (z.B. „Elisabethenquelle Pur 12x0,7 Glas"); kann manuell überschrieben werden (`name_override`-Feld)
  - Wenn `name_override` leer → Anzeigename = auto-generiert; wenn gesetzt → `name_override` wird verwendet
  - **MwSt.-Satz gilt auch für den Pfand** (bei B2B-Kunden erbt der Pfand die MwSt. des Artikels)
- [ ] **Produktbilder:** Upload (max. 10 MB / JPG/PNG), Reihenfolge per Drag & Drop, Alt-Text, Löschen
- [ ] **Barcodes/EAN:** Mehrere Barcodes pro Produkt mit Typ (EAN13, EAN8, etc.) und Gültigkeitszeitraum
- [ ] **LMIV:** Versioniertes System (draft/active) mit Feldern: Energie (kcal/kJ), Nährstoffe (JSON), Allergene (JSON); Versionswechsel erfordert explizite Aktivierung
- [ ] **Brands:** CRUD (Name); Inline-Bearbeitung direkt in der Liste
- [ ] **Produktlinien:** CRUD (Name, Brand, Standard-Gebinde-Pfand)
- [ ] **Kategorien:** CRUD, verschachtelt (Eltern-Kind-Beziehung, beliebige Tiefe)
- [ ] **Warengruppen:** CRUD, verschachtelt analog zu Kategorien
- [ ] **Gebinde:** CRUD (Name, Anzahl, Inhalt, Material/Typ, standardisiert?, **PfandSet-Verknüpfung**)
  - Beispiele: `12x0,7 Glas`, `24x0,33 Glas`, `6x1,0 PET`
  - Pfand kommt aus dem verknüpften PfandSet (nicht am Produkt, sondern am Gebinde)
- [ ] **PfandItems:** CRUD (Name, kanonischer Nennwert `wert_milli`; wird je nach Kundengruppe als brutto/netto interpretiert)
- [ ] **PfandSets:** CRUD; Komponenten per UI hinzufügen/entfernen (PfandItem oder verschachteltes PfandSet + qty)
- [ ] **Kundengruppen:** CRUD (Name, Preisanpassungstyp/-wert, is_business, is_deposit_exempt, aktiv, verfügbare Zahlungsmethoden)
- [ ] **MwSt.-Sätze:** CRUD (Name, rate_basis_points, aktiv)
- [ ] Alle Listen: Sortierung, Suche/Filter, Pagination
- [ ] Inline-Bearbeitung (Produktname, Markenname etc.) direkt in der Listenansicht via AJAX
- [ ] Deaktivieren statt Löschen für alle Entitäten (Soft-Deactivation)
- [ ] Löschschutz: Entitäten mit aktiven Verknüpfungen können nicht gelöscht werden (Fehlermeldung)

## Edge Cases
- Kategorie wird gelöscht, die noch Produkte enthält → Löschen verweigern, Produkte zuerst umhängen
- PfandSet wird gelöscht, das noch in Gebinden verwendet wird → Löschen verweigern
- LMIV-Version aktivieren während eine andere aktiv ist → alte wird automatisch archiviert
- Produktbild-Upload mit falscher Dateigröße/-typ → Validierungsfehler
- Zyklische Kategorie-Verschachtelung (A ist Kind von B, B ist Kind von A) → Validierung verhindert dies
- Basispreis = 0 → Erlaubt (Gratisartikel), aber Warnung im UI

## Technical Requirements
- Bilderspeicherung: Laravel Storage (local disk oder S3-kompatibel)
- Alle Beträge als Integer in milli-cents
- LMIV-Daten als JSON in DB gespeichert (keine separate Normalisierung für Nährstoffe)
- Inline-Bearbeitung via AJAX/Livewire oder Inertia

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur (UI-Baum)

```
/admin/stammdaten/
│
├── produkte/
│   ├── index          ← Produktliste (Filter: Marke, Kategorie, Status; Suche)
│   ├── create         ← Neues Produkt anlegen
│   └── {id}/edit      ← Produkt bearbeiten (Tabs: Stammdaten | Bilder | Barcodes | LMIV | Preise)
│       ├── [Tab] Stammdaten  — Felder, Verknüpfungen
│       ├── [Tab] Bilder      — Upload + Reihenfolge (Drag & Drop)
│       ├── [Tab] Barcodes    — EAN-Liste mit Gültigkeitszeitraum
│       ├── [Tab] LMIV        — Versionsliste (draft/aktiv/archiviert) + Editor
│       └── [Tab] Preise      — Gruppenpreise direkt editieren
│
├── marken/            ← Liste mit Inline-Bearbeitung (Name, direkt im Tabellenfeld)
├── produktlinien/     ← Liste (Marke, Name, Gebinde-Standard)
├── kategorien/        ← Baumansicht (verschachtelt); Anlegen/Verschieben
├── warengruppen/      ← Baumansicht analog zu Kategorien
├── gebinde/           ← Liste (Name, Qty, Material, PfandSet-Verknüpfung)
│
├── pfand/
│   ├── items/         ← Atomare Pfandwerte (Name, wert_milli)
│   └── sets/
│       ├── index      ← PfandSet-Liste
│       └── {id}/edit  ← Set-Editor: Komponenten hinzufügen/entfernen/ordnen
│
├── kundengruppen/     ← Preisanpassung, is_business, is_deposit_exempt, Zahlungsmethoden
└── steuersaetze/      ← MwSt.-Sätze (Name, Prozentsatz)
```

### Datenmodell

```
brands
├── id, name, active, company_id

product_lines  [Produktlinie = Sorte innerhalb einer Marke]
├── id, brand_id → brands
├── name, active, company_id

gebinde  [Verpackungseinheit]
├── id, name ("12x0,7 Glas")
├── qty (12), volume_ml (700), material_type ("Glas"|"PET"|"Dose"|...)
├── pfand_set_id (nullable) → pfand_sets
├── is_standard, active, company_id

products
├── id, article_number (unique)
├── brand_id → brands
├── product_line_id → product_lines
├── gebinde_id → gebinde
├── name_override (nullable)  ← wenn NULL: Auto "Brand + Linie + Gebinde"
├── category_id → categories
├── warengruppe_id → warengruppen
├── tax_rate_id → tax_rates
├── base_price_net_milli
├── availability_mode  ENUM: always | stock_based
├── is_bundle, active, company_id

categories  [verschachtelt, beliebige Tiefe]
├── id, parent_id (nullable, self-referential) → categories
├── name, sort_order, active, company_id

warengruppen  [analog zu categories]
├── id, parent_id (nullable) → warengruppen
├── name, sort_order, active, company_id

product_images
├── id, product_id → products
├── storage_path, alt_text, sort_order, company_id

product_barcodes
├── id, product_id → products
├── barcode_type  ENUM: EAN13 | EAN8 | CODE128 | QR
├── barcode_value, valid_from (nullable), valid_to (nullable), company_id

lmiv_versions  [versionierte Nährwertangaben]
├── id, product_id → products
├── status  ENUM: draft | active | archived
├── energy_kcal, energy_kj
├── nutrients (JSON)   ← Eiweiß, Kohlenhydrate, Fett, ...
├── allergens (JSON)   ← Liste nach EU-Kennzeichnung
├── activated_at, activated_by → users
└── company_id
```

### Inline-Bearbeitung (Alpine.js + AJAX)

Kein Livewire und kein React. Die Inline-Bearbeitung wird direkt mit **Alpine.js** realisiert:

```
[Listenzeile]  "Vöslauer"   [Stift-Icon]
     ↓ Klick auf Stift
[Listenzeile]  [  Vöslauer  ] [✓ Speichern] [✗ Abbrechen]
     ↓ Klick auf ✓
  PATCH /admin/stammdaten/marken/{id}  (JSON)
  → Alpine-State zeigt neuen Namen ohne Seitenreload
```

Dasselbe Muster für alle Inline-Felder (Markenname, Sortiernummer, Status-Toggle etc.).

### PfandSet-Editor (Alpine.js Baum-Manager)

```
PfandSet "Kasten 12x0,7l Glas"  [Komponenten]
  ┌────────────────────────────────────────┐
  │ [+ PfandItem hinzufügen ▼]            │
  │ [+ Untergeordnetes PfandSet ▼]        │
  ├────────────────────────────────────────┤
  │ ⠿  Glasflasche 0,7l      qty: [12] ✗ │
  │ ⠿  Holzkasten 12er       qty: [ 1] ✗ │
  └────────────────────────────────────────┘
  Berechneter Pfandwert: 3,42 €
```

Alpine.js verwaltet den Komponentenbaum im Browser; POST bei Speichern überträgt alles in einer Anfrage.

### Kategorie-/Warengruppen-Baum

Kategorien werden als **verschachtelter Baum** angezeigt. Beim Anlegen einer Kategorie wählt der Admin eine optionale Elternkategorie (Dropdown mit allen vorhandenen Kategorien). Zirkuläre Verschachtelung wird serverseitig geprüft.

### Produktname-Generierung

```
Anzeigeregel:
  wenn name_override ausgefüllt: → name_override anzeigen
  sonst:                         → "Brand + Produktlinie + Gebinde" (dynamisch aus FKs)

Beispiel: brand="Elisabethenquelle" + line="Pur" + gebinde="12x0,7 Glas"
         → "Elisabethenquelle Pur 12x0,7 Glas"
```

### LMIV-Versionierung

```
Zustands-Workflow:
  draft → active   (Aktivierung: archiviert bisherige active-Version)
  active → archived (nur wenn neue Version aktiviert wird)

Zu jedem Zeitpunkt: max. eine Version im Status "active" pro Produkt.
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| Keine Livewire/Inertia | Nur Alpine.js + AJAX — keine zusätzliche Infrastruktur; reicht für Admin-CRUD vollständig |
| Produktname aus FKs statt dupliziert | Namensänderung an Brand/Linie/Gebinde wirkt sofort auf alle Produkte; kein inkonsistenter Datenbestand |
| LMIV als JSON (nicht normalisiert) | Nährstoff-Schema variiert je nach Produktkategorie; JSON ist flexibler und ausreichend für Anzeige/Druck |
| Soft-Deactivation (kein Delete) | Historische Rechnungen und Bestellungen referenzieren Produkte; hart-löschen würde Daten brechen |
| Löschschutz per FK-Check | Sicherheitsnetz vor versehentlichem Löschen verlinkter Entities |

### Neue Controller

```
Admin\Stammdaten\ProduktController
Admin\Stammdaten\MarkeController
Admin\Stammdaten\ProduktlinieController
Admin\Stammdaten\KategorieController
Admin\Stammdaten\WarengruppeController
Admin\Stammdaten\GebindeController
Admin\Stammdaten\PfandItemController
Admin\Stammdaten\PfandSetController
Admin\Stammdaten\KundengruppeController
Admin\Stammdaten\SteuersatzController
```

Alle unter `admin`-Middleware (Auth + Rolle).

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
