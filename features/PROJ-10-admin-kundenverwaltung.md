# PROJ-10: Admin: Kundenverwaltung

## Status: Planned
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- Requires: PROJ-1 (Auth) — Kunden sind an User-Accounts geknüpft
- Requires: PROJ-6 (Preisfindung) — Kundenindividuelle Preise werden hier gepflegt

## Beschreibung
Vollständige Kundenverwaltung im Admin-Bereich: CRUD für Kunden, Adressen, Kontakte (polymorph), Kundengruppen-Zuordnung, individuelle Preise, SEPA-Mandat-Verwaltung, Unterbenutzer-Rechte. Kunden können andere Nutzer als Unter-Kunden anlegen. Kontakte können mehreren Kunden und Lieferanten zugeordnet werden.

## User Stories
- Als Admin möchte ich Kunden anlegen, suchen, bearbeiten und deaktivieren.
- Als Admin möchte ich Kunden einer Kundengruppe zuordnen und kundenindividuelle Preise hinterlegen.
- Als Admin möchte ich Adressen pro Kunden verwalten (mehrere möglich, eine als Standard).
- Als Admin möchte ich Kontaktpersonen (Ansprechpartner) einem Kunden zuordnen, mit Rolle und Kontaktdaten.
- Als Admin möchte ich das Stammsortiment eines Kunden einsehen und bearbeiten.
- Als Kunde (B2B) möchte ich Sub-User anlegen und diesen verschiedene Rechte geben (Rechnungen sehen, Bestellen, Stammsortiment ändern, etc.).
- Als Admin möchte ich ein Kundenkonto auf einen anderen User-Account umhängen.
- Als Admin möchte ich einem Kunden manuell einen Zahlungseingang verbuchen.

## Acceptance Criteria
- [ ] Kundenliste: Kundennummer, **Anzeigename** (Firmenname wenn gesetzt, sonst „Vorname Nachname"), Kundengruppe, Email, Telefon, Status (aktiv/inaktiv), Suchfeld, Filter nach Gruppe
- [ ] Kundendetail: alle Stammdaten, Adressen, Kontakte, individuelle Preise, Bestellhistorie-Link, Kontostand
- [ ] Kundenstamm: Kundennummer (auto), **Firmenname** (optional — leer bei Privatkunden), **Vorname + Nachname** (optional — Inhaber bei Einzelunternehmen, Ansprechpartner bei GmbH/AG), Email, Telefon, Kundengruppe, Preisanzeigemodus (netto/brutto), Lexoffice-Kontakt-ID, aktiv
  - **Rechnungsanschrift-Logik:**
    - Nur Firmenname: `Muster GmbH` → nur Firmenname
    - Firmenname + Person: `Muster Getränke\nMax Mustermann` → Einzelunternehmen
    - Nur Person (kein Firmenname): `Max Mustermann` → Privatkunde
  - `Firma` ist Pflichtfeld wenn `is_business = true`; optional wenn `is_business = false`
- [ ] Adressen: Mehrere pro Kunde; Felder:
  - Vorname, Nachname, Straße, Hausnr., PLZ, Stadt, Land, Telefon
  - Lieferhinweis (Freitext, z.B. „Bitte klingeln bei Müller")
  - **Abstellort:** `deposit_location` — Enum: `keller`, `eg`, `garage`, `1og`, `sonstiges`; bei `sonstiges`: Freitext `deposit_location_note`
  - **Abstellen bei Nichtantreffen:** `allow_unattended_delivery` (Boolean)
  - Standard-Flag (`is_default`)
- [ ] Kontakte (Ansprechpartner): Name, Email, Telefon, Rollen (Vertrieb, Buchhaltung, Besteller, Warenannahme, Chef, Sonstiges); ein Kontakt kann mehreren Kunden/Lieferanten zugeordnet sein
- [ ] Kundenindividuelle Preise: Produkt suchen, Preis (netto, milli) + Gültigkeitszeitraum eingeben; Liste bestehender Preise editierbar
- [ ] Unterbenutzer: Kunde kann Sub-Accounts anlegen und ihnen Rechte geben: Rechnungen sehen, Lieferscheine sehen, Bestellungen aufgeben, Stammsortiment ändern
- [ ] Stammsortiment im Admin einsehbar und editierbar (Produkt hinzufügen/entfernen, Mindestbestand, Notiz)
- [ ] SEPA-Mandat: Status anzeigen, Mandat widerrufen
- [ ] Deaktivierung: Kunde kann deaktiviert werden; Login wird verweigert; bestehende Bestellungen/Rechnungen bleiben erhalten
- [ ] Kundennummer ist unveränderlich nach Anlage
- [ ] CSV-Export der Kundenliste (für externe Auswertungen)

## Edge Cases
- Kunde mit offenen Rechnungen wird deaktiviert → Warnung, aber Deaktivierung möglich
- Kontaktperson wird mehreren Kunden zugeordnet → Kontaktänderung wirkt sich auf alle aus (Bestätigung nötig)
- Sub-User versucht Aktion ohne Berechtigung → 403-Fehler mit klarer Meldung
- Kundennummer bereits vergeben (bei manuellem Import) → Fehlermeldung
- Kunde hat noch aktiven Warenkorb → bleibt erhalten, aber Login wird blockiert wenn deaktiviert

## Technical Requirements
- company_id auf jedem Kunden-Datensatz (Multi-Tenant-Vorbereitung)
- Kontakte: polymorphe Beziehung (`contactable_type`, `contactable_id`)
- Keine Kundennummern-Wiederverwendung nach Löschung

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur (UI-Baum)

```
/admin/kunden/
│
├── index                  ← Kundenliste
│   ├── Suche (Name, Kundennummer, Email)
│   ├── Filter (Kundengruppe, Status aktiv/inaktiv)
│   └── Anzeigename: Firmenname wenn gesetzt, sonst "Vorname Nachname"
│
└── {id}/                  ← Kundendetail (Tab-Layout)
    ├── [Tab] Stammdaten   — Firmenname, Person, Kundengruppe, Preisanzeige, Status
    ├── [Tab] Adressen     — Liste + Formular (Abstellort, Lieferhinweis, is_default)
    ├── [Tab] Kontakte     — Ansprechpartner mit Rollen (polymorph)
    ├── [Tab] Preise       — Kundenindividuelle Preise (Produkt + Preis + Zeitraum)
    ├── [Tab] Bestellungen — Link zur gefilterten Bestellliste
    ├── [Tab] Stammsortiment — Produkt-Liste mit Mindestbestand + Notiz
    └── [Tab] SEPA         — Mandat-Status, widerrufen
```

### Datenmodell

```
users  [Auth — gemeinsam mit Admin/Fahrer]
├── id, email (unique), password (nullable bei OAuth)
├── google_id (nullable), active
└── company_id

customers  [1:1 zu users — nur für Kunden]
├── id, user_id → users
├── customer_number  (auto, unveränderlich, kein NULL)
├── company_name (nullable)  ← leer bei Privatkunden
├── first_name (nullable)    ← Inhaber (Einzelunternehmen) oder Ansprechpartner
├── last_name (nullable)
├── customer_group_id → customer_groups
├── is_business (bool)       ← steuert Netto/Brutto-Rechnungsausweis
├── is_deposit_exempt (bool) ← kein Pfand berechnen
├── price_display_mode  ENUM: netto | brutto
├── lexoffice_contact_id (nullable)
├── notes (text, nullable)
└── company_id

addresses  [n:1 zu customers]
├── id, customer_id → customers
├── first_name, last_name
├── street, house_number, postal_code, city, country
├── phone (nullable)
├── delivery_note (nullable)   ← "Bitte klingeln bei Müller"
├── deposit_location  ENUM: keller | eg | garage | 1og | sonstiges
├── deposit_location_note (nullable)  ← nur wenn sonstiges
├── allow_unattended_delivery (bool)
├── is_default (bool)
└── company_id

contacts  [polymorph — Ansprechpartner für Kunden UND Lieferanten]
├── id, name, email (nullable), phone (nullable)
└── company_id

contactables  [Pivot — verbindet Kontakt mit Kunden/Lieferanten]
├── contact_id → contacts
├── contactable_type  ("App\Models\Customer" | "App\Models\Supplier")
├── contactable_id
└── role  ENUM: vertrieb | buchhaltung | besteller | warenannahme | chef | sonstiges

customer_assortments  [Stammsortiment pro Kunde]
├── id, customer_id → customers
├── product_id → products
├── min_quantity (nullable)
├── notes (nullable)
└── company_id
```

### Rechnungsanschrift-Logik

```
Nur Firmenname gesetzt:          "Muster GmbH"
Firmenname + Person:             "Muster Getränke"  +  "Max Mustermann"
Nur Person (kein Firmenname):    "Max Mustermann"

Regeln:
  is_business = true  →  company_name Pflichtfeld
  is_business = false →  company_name optional
```

### Kontakte (polymorph)

Ein Kontakt (z.B. "Maria Müller, Buchhaltung") kann mehreren Kunden **und** Lieferanten zugeordnet sein — ohne Datenduplikat:

```
contacts:       id=5  "Maria Müller"  maria@example.com
contactables:   → Customer id=12  rolle=buchhaltung
                → Customer id=88  rolle=buchhaltung
                → Supplier id=3   rolle=buchhaltung
```

Wenn ein Kontakt bearbeitet wird, zeigt das UI: "Dieser Kontakt ist X weiteren Entitäten zugeordnet."

### Kundenindividuelle Preise (Tab Preise)

Direkte Bearbeitung der `customer_prices`-Einträge aus PROJ-6:

```
[Produktsuche Autocomplete]  [Preis netto €]  [Gültig von]  [Gültig bis]  [+]

Vöslauer Pur 12x0,7    4,20 €    01.01.2026  31.12.2026   [✏] [✗]
Schmucker Pils 24er   12,50 €    —           —            [✏] [✗]
```

### SEPA-Mandat

Mandat wird in PROJ-8 (Zahlungsabwicklung) erstellt. Im Kundenprofil nur:
- Status anzeigen (aktiv / kein Mandat)
- Widerruf (Statuswechsel + Eintrag in `audit_logs`)

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| `users` + `customers` getrennt | Admin-Mitarbeiter und Fahrer sind ebenfalls `users` ohne `customer`-Profil |
| Polymorphe Kontakte | Ein Ansprechpartner kann für mehrere Kunden/Lieferanten zuständig sein; kein Datenduplikat |
| `customer_number` unveränderlich | Wird in Rechnungen, Belegen und externen Systemen referenziert; darf sich nie ändern |
| Anzeigename aus `company_name` / Person | Konsistente Darstellung; keine duplizierte `display_name`-Spalte |
| Stammsortiment als eigene Tabelle | Erweiterbar (Mindestbestand, Notiz) ohne die Produkttabelle aufzublasen |

### Neue Controller

```
Admin\KundeController             ← index, show, create, store, update, destroy
Admin\KundeAdresseController      ← store, update, destroy, setDefault
Admin\KundeKontaktController      ← attach, update, detach
Admin\KundeSortimentController    ← store, update, destroy
Admin\KundePreisController        ← store, update, destroy
Admin\KundeSepaMandatController   ← revoke
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
