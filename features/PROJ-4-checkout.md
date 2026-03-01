# PROJ-4: Checkout

## Status: Planned
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- Requires: PROJ-1 (Auth) — Checkout erfordert eingeloggten Nutzer (oder Gast-Checkout, falls aktiviert)
- Requires: PROJ-3 (Warenkorb) — Warenkorbinhalte werden in die Bestellung überführt
- Requires: PROJ-6 (Preisfindung) — Endpreise werden bei Bestellerstellung eingefroren (Snapshot)
- Requires: PROJ-7 (Pfand-System) — Pfand-Snapshot wird auf OrderItem gespeichert
- Requires: PROJ-8 (Zahlungsabwicklung) — Zahlungsmethode wird im Checkout gewählt

## Beschreibung
Mehrstufiger Checkout-Prozess: Lieferart wählen (Heimdienst oder Abholung), Lieferadresse wählen/eingeben, Zahlungsmethode wählen, Bestellung bestätigen. Nach Bestellabschluss werden alle Preise als unveränderliche Snapshots auf den OrderItems gespeichert.

## User Stories
- Als Kunde möchte ich zwischen Heimlieferung und Lager-Abholung wählen.
- Als Heimdienst-Kunde möchte ich meine Lieferadresse auswählen oder eine neue eingeben.
- Als Abholkunde möchte ich das gewünschte Lager/den gewünschten Markt auswählen.
- Als Kunde möchte ich ein Wunsch-Lieferdatum angeben (aus verfügbaren Terminen).
- Als Kunde möchte ich meine Zahlungsmethode wählen (aus für meine Kundengruppe freigeschalteten Methoden).
- Als Kunde möchte ich eine Bestellübersicht sehen, bevor ich verbindlich bestelle.
- Als Kunde möchte ich nach Bestellabschluss eine Bestätigungs-Email erhalten.
- Als System soll die korrekte `regular_delivery_tour_id` beim Checkout per PLZ-Lookup automatisch zugeordnet werden.

## Acceptance Criteria
- [ ] Schritt 1 — Lieferart: „Heimlieferung" oder „Abholung im Lager/Markt" auswählbar
- [ ] Schritt 2a (Heimlieferung): Auswahl gespeicherter Adressen oder neue Adresse eingeben; Pflichtfelder: Straße, Hausnr., PLZ, Stadt
  - **Abstellort:** Dropdown-Auswahl (Keller, EG, Garage, 1.OG, Sonstiges) — optionales Freitext-Feld bei „Sonstiges"
  - **Abstellen bei Nichtantreffen:** Checkbox „Ware darf bei Abwesenheit abgestellt werden"
  - Diese Felder werden pro Adresse gespeichert und können beim Checkout für jede Bestellung geändert werden
- [ ] Schritt 2b (Abholung): Auswahl des gewünschten Lagers/Standorts aus Liste
- [ ] Schritt 3 — Liefertermin: Kalender-Auswahl aus verfügbaren Terminen (basierend auf Tour-Planung / Öffnungszeiten)
- [ ] Schritt 4 — Zahlung: Nur für Kundengruppe freigeschaltete Zahlungsmethoden werden angezeigt
- [ ] Schritt 5 — Übersicht: Alle Positionen, Lieferart, Adresse, Termin, Zahlungsmethode, Gesamtsummen
- [ ] Bestellabschluss: `Order` und `OrderItems` werden mit Preis-Snapshots angelegt
  - `unit_price_net_milli`, `unit_price_gross_milli`, `tax_rate_basis_points`, `unit_deposit_milli` eingefroren
  - `product_name_snapshot`, `artikelnummer_snapshot` eingefroren
- [ ] `delivery_address_id` wird auf der Order gesetzt (aus gewählter Adresse)
- [ ] **Stamm-Tour bestimmt Lieferoptionen:**
  - Kunde hat eine Stamm-Tour → Tour ist voreingestellt; Kunde wählt nur das Lieferdatum aus verfügbaren Terminen der Tour
  - Neukunde ohne Stamm-Tour → PLZ + Ortsname prüfen:
    - 1 Treffer → Tour automatisch vorschlagen, nach Bestellung als Stamm-Tour speichern
    - Mehrere Treffer → Kunde wählt; Auswahl wird als Stamm-Tour gespeichert
    - Kein Treffer → Hinweis „Kein Liefergebiet gefunden"; `regular_delivery_tour_id = NULL`; Admin-Log
  - Nur für Kundengruppe des Kunden freigegebene Touren werden angezeigt
- [ ] Bestätigungs-Email wird nach Bestellabschluss versendet
- [ ] Weiterleitung zur Bestellbestätigungs-Seite mit Bestellnummer
- [ ] Warenkorb wird nach erfolgreichem Checkout geleert
- [ ] Bestellung ist auch ohne Kundenkonto möglich (Gast-Checkout), wenn in Einstellungen aktiviert

## Edge Cases
- Warenkorb ist beim Checkout-Aufruf leer → Weiterleitung zum Warenkorb mit Hinweis
- Produkt wird zwischen Warenkorbanlage und Checkout deaktiviert → Warnung, Checkout blockieren
- Kunde hat keine Adresse gespeichert → Neue Adresse direkt im Checkout eingeben
- PLZ liegt in keiner konfigurierten Tour-Zone → `regular_delivery_tour_id = NULL`, Admin wird benachrichtigt
- Zahlungsmethode Stripe → Weiterleitung zu Stripe Checkout, nach Rückkehr Bestellung anlegen
- Zahlungsmethode PayPal → Weiterleitung zu PayPal, nach Rückkehr Bestellung anlegen
- Stripe/PayPal Zahlung schlägt fehl → Bestellung NICHT anlegen, zurück zur Zahlungsauswahl
- Benutzer verlässt Stripe/PayPal und kommt zurück → Offene Zahlung abbrechen, erneut versuchen
- Mindestbestellwert unterschritten (konfigurierbar pro Tour) → Hinweis im Checkout

## Technical Requirements
- Alle Preise werden serverseitig berechnet und als Integer (milli-cents) gespeichert (nie float)
- Snapshots sind nach Bestellanlage unveränderlich
- Checkout-Session mit CSRF-Schutz
- Stripe-Integration: Redirect-Checkout (hosted) + Webhook-Bestätigung
- PayPal-Integration: Redirect + IPN/Webhook

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur (UI-Baum)

```
/checkout                         ← Mehrstufiger Wizard (Blade, Alpine.js für Step-Switching)
│
├── Schritt 1: Lieferart
│   ├── [Heimlieferung]
│   └── [Abholung im Lager]
│
├── Schritt 2a: Lieferadresse (bei Heimlieferung)
│   ├── Gespeicherte Adressen des Kunden (Radio-Liste)
│   ├── [+ Neue Adresse eingeben] (inline)
│   │   ├── Straße, Hausnr., PLZ, Ort
│   │   ├── Abstellort (Dropdown: Keller/EG/Garage/1.OG/Sonstiges)
│   │   └── Abstellen bei Nichtantreffen (Checkbox)
│   └── Tour-Zuordnung:
│       ├── Stamm-Tour vorhanden → automatisch + nächste Termine anzeigen
│       └── Kein Stamm-Tour → PLZ-Lookup → Treffer zur Auswahl
│
├── Schritt 2b: Abholort (bei Abholung)
│   └── Liste der Standorte/Lager
│
├── Schritt 3: Liefertermin
│   └── Kalender (nur erlaubte Termine der Stamm-Tour)
│
├── Schritt 4: Zahlungsmethode
│   └── Nur freigeschaltete Methoden der Kundengruppe (PROJ-8)
│
└── Schritt 5: Zusammenfassung + [Verbindlich bestellen]
    ├── Alle Positionen (Produktname-Snapshot, Menge, Preis, Pfand)
    ├── Lieferdetails, Zahlungsmethode
    └── Gesamtsummen (netto, Pfand, MwSt., brutto)
```

### Datenmodell

```
orders
├── id, order_number (unique, auto)
├── customer_id → customers
├── delivery_type  ENUM: home_delivery | pickup
├── delivery_address_id → addresses (nullable, bei Heimlieferung)
├── pickup_location_id → pickup_locations (nullable, bei Abholung)
├── regular_delivery_tour_id → regular_delivery_tours (nullable)
├── delivery_date (DATE)
├── payment_method  ENUM: stripe | paypal | sepa | invoice | cash | ec
├── status  ENUM: new | confirmed | in_delivery | completed | cancelled
├── notes (nullable)  ← interner Admin-Kommentar
├── customer_notes (nullable)  ← Kundennotiz
└── company_id

order_items  [Snapshots — UNVERÄNDERLICH nach Anlage]
├── id, order_id → orders
├── product_id → products  (zur Referenz, nicht zur Preisberechnung)
├── product_name_snapshot    ← Produktname zum Zeitpunkt der Bestellung
├── article_number_snapshot  ← Artikelnummer-Snapshot
├── quantity
├── unit_price_net_milli     ← Snapshot
├── unit_price_gross_milli   ← Snapshot
├── tax_rate_basis_points    ← Snapshot (PFLICHTFELD, kein NULL)
├── unit_deposit_milli       ← Snapshot
├── deposit_tax_rate_basis_points ← Snapshot
├── pfand_set_id (nullable)  ← Snapshot: Rückverfolgung
├── is_backorder (bool)
└── company_id

order_adjustments  [Leergut, Bruch, Korrekturen]
├── id, order_id → orders
├── type  ENUM: deposit_return | breakage | price_correction | other
├── description
├── quantity (nullable)
├── unit_value_milli   (negativ = Gutschrift)
├── tax_rate_basis_points
└── company_id
```

### Checkout-Flow (serverseitig)

```
POST /checkout/bestaetigen

1. Warenkorb validieren (Produkte aktiv, Preise verfügbar)
2. Preis-Snapshots berechnen (PriceResolverService + PfandCalculator)
3. Order + OrderItems in DB-Transaktion anlegen
4. Warenkorb leeren
5. Zahlung initiieren:
   → Stripe/PayPal: Redirect zu Payment Provider (Order bleibt 'new')
   → Andere: Order direkt auf 'confirmed' setzen
6. Bestätigungs-Email in deferred_tasks einreihen
7. Weiterleitung zur Bestätigungsseite
```

### Tour-Zuordnung (TourAssignmentService)

```
resolveTours(string $postalCode, string $city, int $customerGroupId): array

→ Sucht DeliveryAreas: PLZ exakt + (city_match IS NULL OR city LIKE '%city_match%')
→ Filtert: nur Touren für die Kundengruppe erlaubt
→ Gibt sortierte Liste passender RegularDeliveryTours zurück
→ 0 Treffer: return []  → Checkout zeigt "Kein Liefergebiet"
→ 1 Treffer: automatisch zuordnen
→ n Treffer: Kundenauswahl anzeigen
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| Blade-Wizard (kein SPA) | Kein JavaScript-State über Schritte hinweg; Back-Button funktioniert korrekt; CSRF automatisch |
| Snapshot-Felder auf `order_items` | Preisänderungen dürfen bestehende Bestellungen nicht retroaktiv ändern |
| `tax_rate_basis_points` als Pflichtfeld (kein NULL) | Rechtssicherheit; Fehler beim Checkout ist besser als stille 0% MwSt. |
| Separate `order_adjustments` | Leergut/Bruch/Korrekturen als eigene Zeilen; Rechnungs-Draft kann sie direkt einlesen |
| Bestätigungs-Email via `deferred_tasks` | Email-Versand blockiert keinen Bestellabschluss |

### Neue Controller / Services

```
Shop\CheckoutController          ← index, lieferart, adresse, termin, zahlung, bestaetigen
TourAssignmentService            ← resolveTours(), automatische Stamm-Tour-Zuordnung
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
