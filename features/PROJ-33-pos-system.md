# PROJ-33: POS-System / Kasse (Barcode, Sofortverkauf)

## Status: Planned
**Created:** 2026-03-01
**Last Updated:** 2026-03-01

## Dependencies
- Requires: PROJ-9 (Admin: Stammdaten) — Produkte müssen vorhanden sein (mit EAN/Barcode)
- Requires: PROJ-6 (Preisfindung) — Preise werden live berechnet
- Requires: PROJ-7 (Pfand-System) — Pfand wird an der Kasse berechnet
- Requires: PROJ-35 (Admin: Kassenverwaltung) — Kasse muss geöffnet sein; Kassenbuch-Integration
- Requires: PROJ-13 (Admin: Rechnungen) — Kassenbeleg = vereinfachte Rechnung

## Beschreibung
POS-Kassensystem für den Lagerverkauf / Abholung. Mitarbeiter scannt Barcodes oder sucht Produkte, legt sie in den Kassenbon, wählt Zahlungsmittel und druckt einen Bon. Unterstützt Barcode-Scanner, Leergut-Rücknahme (Pfand) und verschiedene Zahlungsmittel (Bar, EC). Kein vollständiges Fiskalsystem (TSE) in MVP.

## User Stories
- Als Kassierer möchte ich Produkte per Barcode-Scanner oder Suche zur aktuellen Kassentransaktion hinzufügen.
- Als Kassierer möchte ich Leergut zurücknehmen und als Gutschrift auf den Kassenbon verrechnen.
- Als Kassierer möchte ich den Gesamtbetrag sehen und das Zahlungsmittel wählen (Bar / EC).
- Als Kassierer möchte ich bei Barzahlung das Wechselgeld angezeigt bekommen.
- Als Kassierer möchte ich einen Kassenbon ausdrucken (oder als PDF speichern).
- Als Admin möchte ich alle Kassentransaktionen im Kassenbuch (PROJ-35) sehen.

## Acceptance Criteria
- [ ] **Kasse öffnen:** Vor Nutzung: Kasse aus PROJ-35 auswählen; Tages-Kassenöffnung mit Startbetrag (Wechselgeld)
- [ ] **Produkt hinzufügen:** Barcode-Scan (USB-Scanner = Tastatureingabe) ODER Textsuche (Name, Artikelnummer, EAN); Menge editierbar
- [ ] **Bon-Anzeige:** Positionen mit Stückpreis, Menge, Zeilenpreis; Pfand je Position; Gesamtbetrag brutto; MwSt.-Aufschlüsselung
- [ ] **Leergut-Rücknahme:** Produkt aus Pfand-Katalog wählen (oder Barcode scannen); Menge; Pfandbetrag wird als Gutschrift (negativer Posten) auf Bon gesetzt
- [ ] **Rabatt:** Prozentualer oder absoluter Rabatt auf Gesamtbon oder einzelne Position
- [ ] **Zahlungsmittel:** Bar (Wechselgeld-Berechnung), EC-Karte (manuelle Bestätigung durch Kassierer), Rechnung (für bekannte Kunden → verknüpft mit Kundenkonto)
- [ ] **Kunden verknüpfen:** Optional: Kunden-Suche und Verknüpfung mit Bon (für Rechnungskauf und Kundenprei)
- [ ] **Bon abschließen:** Kassentransaktion wird abgeschlossen; Kassenbuch-Eintrag wird automatisch erstellt; Bon-Nummer vergeben
- [ ] **Bon drucken / PDF:** Bondrucker (ESC/POS) ODER PDF-Download; Inhalt: Firmenname, Datum, Bon-Nr., Positionen, Gesamt, Zahlungsmittel, MwSt.
- [ ] **Transaktion stornieren:** Nur vor Abschluss möglich; nach Abschluss: Storno-Bon erstellen
- [ ] **Offline-Fähigkeit:** Wenn Netzwerk kurz ausfällt → Produkte aus lokalem Browser-Cache (IndexedDB); Transaktion wird bei Wiederherstellung synchronisiert

## Edge Cases
- Barcode nicht gefunden → Fehlermeldung; manuelle Suche öffnet sich
- Pfandrücknahme übersteigt Gesamtbon → Negativer Betrag; Kassierer gibt Differenz bar heraus
- Kasse nicht geöffnet (kein Startbetrag) → POS zeigt Hinweis, Kasse muss zuerst geöffnet werden
- EC-Zahlung schlägt fehl (Karte abgelehnt) → Kassierer kann auf Bar umschalten
- TSE (Fiskalisierung) nicht im MVP → Hinweis im System; TSE-Integration als späteres Upgrade

## Technical Requirements
- `pos_transactions` Tabelle: `id`, `cash_register_id` (PROJ-35), `customer_id` (nullable), `bon_number`, `status ENUM(open|completed|cancelled)`, `payment_method`, `total_milli`, `paid_amount_milli`, `change_milli`, `company_id`
- `pos_transaction_items`: `transaction_id`, `product_id`, `quantity`, `unit_price_milli`, `is_deposit_return BOOL`
- Bon-Nummer: eigene Sequence-Tabelle `pos_sequences`
- Offline: Service Worker + IndexedDB für Produktcache; Sync via Background-Sync API
- ESC/POS-Druck: `mike42/escpos-php`-Bibliothek oder direkter Druckaufruf über Browser-Print-API

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur (UI-Baum)

```
/kasse/{register_id}/                   ← POS-Interface (Vollbild, 1 Seite)
│
├── Linke Spalte: Bon-Ansicht
│   ├── Aktuelle Bon-Positionen
│   │   └── Produkt | Menge | Preis | [×] entfernen
│   ├── Kunden-Verknüpfung (optional, Suche)
│   └── Preis-Zusammenfassung
│       ├── Zwischensumme netto
│       ├── Pfand gesamt
│       ├── MwSt. aufgeschlüsselt
│       └── Gesamtbetrag brutto
│
├── Mittlere Spalte: Produkt-Eingabe
│   ├── Barcode-Scan-Feld (Auto-Focus, sofort scanbar)
│   ├── Produktsuche (Fallback für kein Barcode)
│   ├── Leergut-Rücknahme-Button → Pfand-Auswahl
│   └── Rabatt-Button (% oder € auf Gesamtbon)
│
└── Rechte Spalte: Zahlungsabschluss
    ├── [Bar] → Nummernpad (Eingabe: Gegeben; Ausgabe: Wechselgeld)
    ├── [EC-Karte] → Kassierer bestätigt manuell
    ├── [Rechnung] → Kunden-Zuordnung Pflicht
    ├── [Bon drucken / PDF]
    └── [Transaktion abschließen]

/admin/kassen/                          ← Kassen-Auswahl vor POS-Start
└── Welche Kasse öffnen? + Startbetrag
```

### Datenmodell

```
pos_transactions
├── id, bon_number (aus pos_sequences)
├── cash_register_id → cash_registers (PROJ-35)
├── customer_id → customers (nullable)
├── cashier_user_id → users
├── status  ENUM: open | completed | cancelled
├── payment_method ENUM: cash | ec | invoice
├── total_brutto_milli, paid_amount_milli, change_milli
└── company_id

pos_transaction_items
├── id, pos_transaction_id
├── product_id → products
├── quantity (INT)
├── unit_price_brutto_milli  ← Snapshot zum Kassierzeitpunkt
├── is_deposit_return BOOL   ← Leergut-Rücknahme (Minusbetrag)
└── company_id

pos_sequences  [Bon-Nummernvergabe]
├── id (= 1), last_number, prefix (z.B. „BON")
└── company_id
```

### Barcode-Scanner-Integration

```
USB-Barcode-Scanner = Tastatur-Emulation:
  → Barcode-Feld hat Auto-Focus
  → Scanner gibt EAN ein + Enter
  → JS-Event: fetch(POST /kasse/produkt/lookup?ean=...)
  → Antwort: Produktdaten + Preis
  → Position wird zum Bon hinzugefügt
  → Feld wird geleert + Auto-Focus wieder gesetzt

Kein spezieller Scanner-Treiber nötig
```

### Offline-Strategie (Service Worker)

```
Browser-Cache (IndexedDB) enthält:
  - Produktliste (EAN, Name, Preis) — täglich synchronisiert
  - Pfand-Artikel-Liste

Bei Netzwerkausfall:
  - Barcode-Lookup greift auf IndexedDB zurück
  - Transaktion wird lokal gespeichert (pending)
  - Bei Netzwerk-Wiederherstellung: Background-Sync → Server
  - Admin sieht pending Transaktionen mit Hinweis
```

### Kassenbuch-Integration (PROJ-35)

```
Bei Transaktions-Abschluss:
  → CashRegisterService::bookTransaction($transaction)
  → Kassenbuch-Eintrag: Betrag, Zahlungsmittel, Zeitstempel
  → Kassensaldo wird aktualisiert
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| Single-Page POS-Interface | Kassiervorgang muss flüssig sein; kein Seitenreload beim Hinzufügen |
| Barcode = Tastatureingabe | Alle handelsüblichen USB-Scanner funktionieren ohne Treiber |
| Preis-Snapshot auf Bon-Position | Preis zum Kaufzeitpunkt; spätere Preisänderungen beeinflussen alte Bons nicht |
| IndexedDB für Offline | Kein komplettes Offline-System; nur Produktcache; kritische Daten kommen beim Sync |
| Kein TSE in MVP | TSE-Integration ist komplex und teuer; wird als separates Upgrade (PROJ-33 Phase 2) geplant |

### Neue Controller / Services

```
Kasse\PosController              ← index (Kassen-Auswahl), show (POS-Interface)
Kasse\PosTransaktionController   ← store, update (Abschluss), destroy (Storno)
Kasse\PosProduktLookupController ← show (EAN → Produktdaten)
Kasse\PosBonController           ← show (PDF-Download)
PosService                      ← addItem(), closeTransaction(), generateBon()
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
