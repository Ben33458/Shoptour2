# PROJ-20: Stammsortiment (Schnellbestellung, Kundenbestand, Mindestbestand, Notizen)

## Status: Planned
**Created:** 2026-03-01
**Last Updated:** 2026-03-01

## Dependencies
- Requires: PROJ-2 (Produktkatalog) — Produkte müssen vorhanden sein
- Requires: PROJ-10 (Admin: Kundenverwaltung) — Stammsortiment ist kundenbezogen
- Requires: PROJ-3 (Warenkorb) — Schnellbestellung befüllt den Warenkorb

## Beschreibung
Jeder Kunde hat ein persönliches Stammsortiment: eine Liste der regelmäßig bestellten Produkte mit Sollbestand, aktuellem Bestand und Notizen. Über die Schnellbestellfunktion kann ein Kunde mit einem Klick alle Produkte unterhalb des Mindestbestands in den Warenkorb legen. Admins pflegen das Stammsortiment für B2B-Kunden.

## User Stories
- Als eingeloggter Kunde möchte ich mein Stammsortiment einsehen und schnell alle benötigten Produkte nachbestellen.
- Als Kunde möchte ich für jedes Stamm-Produkt einen Mindestbestand und meinen aktuellen Bestand pflegen, damit das System ausrechnet, was ich brauche.
- Als Kunde möchte ich einem Stamm-Produkt eine Notiz hinterlegen (z.B. „nur Pfandflaschen, kein Einweg").
- Als Admin möchte ich das Stammsortiment eines Kunden einsehen und bearbeiten.
- Als Kunde möchte ich mit einem Klick alle Produkte, bei denen der aktuelle Bestand unter dem Mindestbestand liegt, in den Warenkorb legen (Schnellbestellung).
- Als Kunde möchte ich Produkte zu meinem Stammsortiment hinzufügen oder entfernen.

## Acceptance Criteria
- [ ] **Stammsortiment-Ansicht (Kunden-Frontend):** Liste aller Stamm-Produkte mit Bild, Name, Artikelnummer, Mindestbestand, aktuellem Bestand, Fehlmenge (Mindest - Aktuell), Notiz
- [ ] **Schnellbestellung:** Button „Fehlende Mengen in Warenkorb" → legt für jedes Produkt, bei dem `aktueller_bestand < mindestbestand`, die Differenz als Menge in den Warenkorb
- [ ] **Bestand aktualisieren:** Kunde kann `aktueller_bestand` direkt in der Liste bearbeiten (Inline-Eingabe)
- [ ] **Mindestbestand setzen:** Kunde kann `mindestbestand` pro Produkt festlegen
- [ ] **Notiz:** Freitextfeld pro Produkt (max. 500 Zeichen)
- [ ] **Produkt hinzufügen:** Suche über Produktkatalog; Produkt in Stammsortiment aufnehmen
- [ ] **Produkt entfernen:** Aus Stammsortiment entfernen (keine Auswirkung auf vergangene Bestellungen)
- [ ] **Admin-Ansicht:** Admin kann das Stammsortiment eines beliebigen Kunden aufrufen und bearbeiten (unter Kundenverwaltung → Tab Stammsortiment)
- [ ] **Bestand nach Lieferung aktualisieren:** Nach erfolgreicher Lieferung wird `aktueller_bestand` automatisch um die gelieferte Menge erhöht (aus Fulfillment-Daten, PROJ-16)
- [ ] **Bestand zurücksetzen:** Kunde kann `aktueller_bestand` manuell auf 0 zurücksetzen (z.B. nach eigenem Verbrauch)

## Edge Cases
- Mindestbestand = 0 und aktueller Bestand = 0 → Keine Fehlmenge; Produkt erscheint nicht in Schnellbestellung
- Produkt wird im Katalog deaktiviert, ist aber im Stammsortiment → Produkt bleibt im Stammsortiment, wird als „nicht verfügbar" markiert; Schnellbestellung überspringt es
- Schnellbestellung: alle Produkte haben ausreichend Bestand → Hinweis „Alle Bestände ausreichend", kein Warenkorb-Update
- Bestand nach Lieferung: Teillieferung (nur 4 von 6 Kisten geliefert) → Bestand nur um tatsächlich gelieferte Menge erhöhen
- Kunde entfernt Produkt aus Stammsortiment, das gerade im Warenkorb liegt → Warenkorb bleibt unverändert; nur Stammsortiment-Eintrag wird gelöscht

## Technical Requirements
- `customer_assortment_items` Tabelle: `customer_id`, `product_id`, `min_stock`, `current_stock`, `notes`
- Unique constraint auf `(customer_id, product_id)`
- Schnellbestellung: serverseitige Berechnung der Fehlmengen; Warenkorb-Befüllung via `WarenkorbController::addBulk`
- Bestandsupdate nach Lieferung: Event-Hook aus Fahrer-PWA-Fulfillment (PROJ-16)

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur (UI-Baum)

```
/konto/stammsortiment                    ← Kunden-Frontend
│
├── Kopfbereich
│   ├── Überschrift + Produktanzahl
│   └── [Fehlende Mengen in Warenkorb] — primärer CTA (disabled wenn keine Fehlmengen)
│
├── Produkt-Suche                        ← Produkt zum Stammsortiment hinzufügen
│   └── Autocomplete-Suche → [Hinzufügen]
│
└── Stammsortiment-Tabelle
    ├── Spalten: Bild | Name/Artikelnr. | Mindestbestand | Aktueller Bestand | Fehlmenge | Notiz | Aktionen
    ├── Inline-Bearbeitung (Alpine.js): Mindestbestand + Aktueller Bestand direkt editierbar
    ├── Notiz-Feld: Klick → Textarea öffnet sich inline
    ├── Fehlmenge: farbig hervorgehoben wenn > 0 (rot)
    └── [Entfernen]-Button pro Zeile

/admin/kunden/{id}/stammsortiment        ← Admin-Ansicht (Tab in Kundenverwaltung)
└── Gleiche Tabelle wie Kunden-Frontend, aber im Admin-Kontext
    └── Admin kann für den Kunden alles bearbeiten
```

### Datenmodell

```
customer_assortment_items  [Stammsortiment-Einträge]
├── id
├── customer_id → customers
├── product_id  → products
├── min_stock   (INT, Standard: 0)      ← Soll-Bestand
├── current_stock (INT, Standard: 0)    ← Ist-Bestand (manuell gepflegt)
├── notes       (VARCHAR 500, nullable)
├── UNIQUE (customer_id, product_id)
└── company_id

Fehlmenge = min_stock - current_stock   ← berechnet, nicht gespeichert
```

### Schnellbestellung-Ablauf

```
Kunde klickt [Fehlende Mengen in Warenkorb]:
  1. Server berechnet: alle Einträge wo current_stock < min_stock
  2. Fehlmenge = min_stock - current_stock je Produkt
  3. Deaktivierte Produkte werden übersprungen
  4. CartService::addBulk([{product_id, qty}]) → befüllt Warenkorb
  5. Weiterleitung zu /warenkorb mit Erfolgs-Toast

→ Keine clientseitige Berechnung (kein Manipulation-Risiko)
```

### Automatischer Bestand-Update nach Lieferung

```
Fahrer-PWA (PROJ-16) markiert Stop als delivered:
  → Event: StopDelivered(order_id, delivered_items)
  → AssortmentStockUpdateListener:
      - Prüft: Hat Kunde Stammsortiment-Eintrag für dieses Produkt?
      - Ja → current_stock += gelieferte Menge
      - Nein → nichts tun
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| `current_stock` als manuell gepflegter Wert | Kunden kennen ihren tatsächlichen Verbrauch; automatische Berechnung wäre zu ungenau |
| Inline-Bearbeitung (kein Modal) | Schnelle Massenbearbeitung mehrerer Produkte ohne ständiges Öffnen von Dialogen |
| Schnellbestellung serverseitig | Verhindert clientseitige Manipulation der Mengen und Preise |
| Event-basierter Bestandsupdate | Entkopplung von Fahrer-PWA und Stammsortiment; kein direkter Aufruf nötig |

### Neue Controller / Services

```
Shop\StammsortimentController      ← index, store, update, destroy, quickOrder
Admin\KundeStammsortimentController← index (delegiert an gleichen Service)
AssortmentService                  ← addProduct(), removeProduct(), quickOrder()
AssortmentStockUpdateListener      ← Lauscht auf StopDelivered-Event
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
