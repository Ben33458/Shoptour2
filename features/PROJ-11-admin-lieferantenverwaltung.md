# PROJ-11: Admin: Lieferantenverwaltung

## Status: Planned
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- Requires: PROJ-9 (Admin: Stammdaten) — Produkte müssen existieren für Lieferanten-Produkt-Zuordnung

## Beschreibung
Verwaltung von Lieferanten inkl. Kontaktpersonen, Lieferanten-Produktsortiment (Einkaufspreise, Lieferzeiten, Mindestmengen). Lieferanten-Produkte werden für die Margenberechnung in Rechnungen genutzt.

## User Stories
- Als Admin möchte ich Lieferanten anlegen, suchen, bearbeiten und deaktivieren.
- Als Admin möchte ich Kontaktpersonen einem Lieferanten zuordnen (mit Rollen).
- Als Admin möchte ich das Sortiment eines Lieferanten hinterlegen: welche Produkte er liefert, zu welchem Einkaufspreis, mit welcher Lieferzeit und Mindestmenge.
- Als Admin möchte ich die Einkaufspreise pro Lieferant/Produkt pflegen, damit die Marge in Rechnungen berechnet werden kann.
- Als Admin möchte ich Lieferanten eine eigene Lieferantennummer zuweisen.

## Acceptance Criteria
- [ ] Lieferantenliste: Lieferanten-Nr., Name, Email, Telefon, Status; Suche und Filter
- [ ] Lieferantendetail: Stammdaten, Kontakte, Lieferanten-Produktliste
- [ ] Lieferantenstamm: Lieferanten-Nr., Name, Kontakt-Name (veraltet, besser via Kontakte), Email, Telefon, Adresse, Währung, aktiv, Lexoffice-/ERP-ID (optional)
- [ ] Kontakte: analog zu Kundenkontakten (polymorphe Beziehung, Rollen)
- [ ] Lieferanten-Produkte: Produkt aus Katalog suchen + zuordnen, Felder: Lieferanten-Artikelnummer, Einkaufspreis (netto, milli), Lieferzeit (Tage), Mindestbestellmenge, aktiv
- [ ] Mehrere Lieferanten pro Produkt möglich; neuester aktiver Eintrag wird für Margenberechnung verwendet
- [ ] `supplier_products.active` Feld muss vorhanden und korrekt in DB-Migration sein (Bugfix aus Issue Log)
- [ ] CSV-Import für Lieferanten-Produkte (Lieferant, Artikelnummer, Einkaufspreis, Lieferzeit)
- [ ] Deaktivierung eines Lieferanten blockiert keine historischen Rechnungen

## Edge Cases
- Lieferant wird deaktiviert, hat aber noch aktive Lieferanten-Produkte → Warnung, Produkte werden ebenfalls deaktiviert
- Zwei aktive Lieferanten-Produkte für dasselbe Produkt/Lieferant → neuester nach ID gewinnt (kein Fehler)
- Einkaufspreis = 0 → Erlaubt (Konsignationsware), aber Warnung im UI
- Lieferanten-Nr. bereits vergeben → Fehlermeldung

## Technical Requirements
- `supplier_products.active` muss in Migration vorhanden sein (Default: `true`)
- `cost_milli` in `invoice_items` wird aus aktuellem Lieferanten-Produkt-Einkaufspreis befüllt
- Polymorphe Kontakte-Beziehung analog zu Kunden

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur (UI-Baum)

```
/admin/lieferanten/
│
├── index           ← Lieferantenliste (Suche, Filter: aktiv/inaktiv)
└── {id}/           ← Lieferantendetail (Tab-Layout)
    ├── [Tab] Stammdaten     — Name, Nr., Adresse, Kontaktdaten
    ├── [Tab] Kontakte       — Ansprechpartner (analog PROJ-10, polymorphe Beziehung)
    └── [Tab] Sortiment      — Lieferanten-Produkte (Einkaufspreise, Lieferzeiten)
```

### Datenmodell

```
suppliers
├── id, supplier_number (unique)
├── name, email, phone
├── street, house_number, postal_code, city, country
├── currency (DEFAULT 'EUR')
├── lexoffice_contact_id (nullable)
├── active
└── company_id

supplier_products
├── id, supplier_id → suppliers
├── product_id → products
├── supplier_article_number (nullable)
├── cost_milli           ← Einkaufspreis netto (milli-cents)
├── delivery_days        ← Lieferzeit in Werktagen
├── min_order_quantity   ← Mindestbestellmenge
├── active               ← DEFAULT true (PFLICHT in Migration — Bugfix aus Issue Log)
└── company_id

Polymorphe Kontakte (identisch zu Kundenkontakten aus PROJ-10):
  contacts + contactables (contactable_type = "Supplier")
```

### Margen-Nutzung (für PROJ-13 Rechnungen)

```
Bei Rechnungserstellung:
  invoice_items.cost_milli = supplier_products.cost_milli
    WHERE supplier_products.product_id = order_item.product_id
      AND supplier_products.active = true
    ORDER BY id DESC LIMIT 1
  → Neuester aktiver Einkaufspreis gewinnt
  → Bei mehreren aktiven Lieferanten: niedrigster Einkaufspreis (günstigster Lieferant)
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| Analog zu Kundenverwaltung | Gleiche Muster (polymorphe Kontakte, Tab-Layout); kein abweichendes Pattern |
| `active`-Feld als Pflicht in Migration | Bekannter Bugfix aus Vorprojekt; nie ohne Default anlegen |
| Neuester aktiver Preis für Marge | Preisaktualisierungen beim Lieferanten wirken sofort auf neue Rechnungen; historische Snapshots bleiben auf `invoice_items` |

### Neue Controller

```
Admin\LieferantController            ← index, show, create, store, update, destroy
Admin\LieferantKontaktController     ← attach, update, detach
Admin\LieferantProduktController     ← store, update, destroy
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
