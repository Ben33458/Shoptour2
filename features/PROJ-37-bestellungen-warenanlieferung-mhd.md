# PROJ-37: Bestellungen, Warenanlieferung und MHD-Management

## Status: In Progress
**Created:** 2026-04-13
**Last Updated:** 2026-04-13

## Dependencies
- Requires: PROJ-11 (Admin: Lieferantenverwaltung) — Lieferantenstammdaten
- Requires: PROJ-23 (Admin: Lagerverwaltung) — Bestand und Lagerorte
- Requires: PROJ-32 (Admin: Einkauf / PurchaseOrders) — Bestehende PO-Grundstruktur wird erweitert
- Extends: PROJ-9 (Admin: Stammdaten) — Produkte um Mindestbestände und MHD-Regeln

## Beschreibung

Vollständiges MVP-Modul für Lieferantenbestellungen, Warenanlieferung, Wareneingang und MHD-Management.
Baut auf bestehenden `suppliers`, `supplier_products`, `purchase_orders` und `stock_movements` auf und
erweitert diese um alle notwendigen Strukturen für praxisnahes, schnelles Arbeiten im Tagesgeschäft.

---

## Datenmodell-Übersicht

### Erweiterte bestehende Tabellen

| Tabelle | Ergänzungen |
|---|---|
| `suppliers` | `bestelltag`, `liefertag`, `bestell_art`, `lieferintervall`, `bestell_schlusszeit`, `mindestbestellwert_netto_ek_milli`, `kontrollstufe_default` |
| `supplier_products` | `lieferanten_bezeichnung`, `gebinde_faktor`, `paletten_faktor` |
| `purchase_orders` | `bestellkanal`, `order_profile_id`, `kontrollstufe_override` |
| `product_stocks` | `min_bestand_markt`, `min_bestand_lager`, `min_bestand_gesamt` |
| `stock_movements` | `mhd_batch_id`, `employee_id` |

### Neue Tabellen

| Tabelle | Zweck |
|---|---|
| `supplier_order_profiles` | Bestellprofile pro Lieferant (Kanal, Format, E-Mail-Vorlage) |
| `supplier_document_parsers` | Konfigurierbare Parser-/Mapping-Definition für Dokument-Erkennung |
| `documents` | Zentrale Tabelle für alle Dokument-Typen |
| `document_assignment_rules` | Regelbasierte automatische Dokumentzuordnung |
| `goods_receipts` | Wareneingänge (eigenständiger Lebenszyklus) |
| `goods_receipt_items` | Wareneingangspositionen mit MHD + Abweichungsgrund |
| `product_mhd_batches` | MHD-Chargen mit vollständigem Audit-Trail |
| `product_write_offs` | Aussortierungen / Bruch / rabattierte MHD-Ware |
| `leergut_returns` | Leergutrücknahmen |
| `leergut_return_items` | Positionen der Leergutrücknahme |

---

## User Stories

### Lieferantenbestellung
- Als Admin möchte ich je Lieferant konfigurieren können: Bestelltag, Liefertag, Bestellschlusszeit, Lieferintervall, Mindestbestellwert (Netto-EK), Standard-Kontrollstufe.
- Als Admin möchte ich ein Bestellprofil pro Lieferant anlegen: welcher Kanal (Portal, E-Mail-PDF, CSV, XML, Fallback), welche Pflichtfelder, welche Empfänger-E-Mail, welcher Betreff.
- Als Admin möchte ich Bestellungen über den konfigurierten Kanal des Lieferanten erzeugen und versenden.
- Als Admin möchte ich eine Beispieldatei (CSV/PDF) hochladen und daraus Feldmappings konfigurieren.

### Wareneingang
- Als Admin/Mitarbeiter möchte ich eine Lieferung mit einem Klick als „angekommen" markieren und den Wareneingang buchen (Standardfall: bestellte = gelieferte Menge).
- Als Admin/Mitarbeiter möchte ich Abweichungen zur bestellten Menge erfassen können.
- Als Admin/Mitarbeiter möchte ich je nach Lieferant eine konfigurierte Kontrollstufe durchlaufen (nur angekommen / Summenkontrolle / Positionskontrolle / mit MHD).

### Dokumente
- Als Admin möchte ich alle Dokumente (Lieferscheine, Rechnungen, Fotos, E-Mail-Anhänge) zentral verwalten.
- Als Admin möchte ich, dass Dokumente regelbasiert automatisch Lieferanten / Bestellungen / Wareneingängen zugeordnet werden.
- Als Admin möchte ich unsichere Zuordnungen in einer Prüfliste sehen, statt dass sie automatisch zugeordnet werden.

### MHD
- Als Admin/Mitarbeiter möchte ich beim Wareneingang MHDs pro Position erfassen können (Pflicht / empfohlen / optional je Produkt).
- Als Admin möchte ich sehen, welche Artikel bald oder bereits abgelaufen sind.
- Als Admin möchte ich bei einem wiederauftauchenden älteren MHD nachvollziehen können: wann angeliefert, von wem, wer verräumt hat, ob Umlagerungen stattfanden.

### Aussortierung
- Als Mitarbeiter möchte ich Ware als Bruch / abgelaufen / rabattierte MHD-Ware aussortieren können mit Ursachenbewertung.
- Der Bezug zum echten Produkt soll dabei immer erhalten bleiben.

### Leergutrücknahme
- Als Mitarbeiter möchte ich eine Leergutrücknahme erfassen (Paletten, Standard-Kästen, seltene Kästen, zwei Fotos, Kontrollzählung).

---

## Acceptance Criteria

### Lieferantenprofile
- [ ] `suppliers` hat Felder: `bestelltag` (TEXT/Wochentag), `liefertag`, `bestell_art` (Kanal), `lieferintervall` (ENUM: wöchentlich/14-tägig/nach_bedarf), `bestell_schlusszeit` (TIME), `mindestbestellwert_netto_ek_milli` (BIGINT), `kontrollstufe_default` (ENUM)
- [ ] `supplier_products` hat Felder: `lieferanten_bezeichnung`, `gebinde_faktor` (decimal), `paletten_faktor` (decimal)
- [ ] Tabelle `supplier_order_profiles` existiert mit: `supplier_id`, `kanal` (ENUM), `ist_standard` (bool), `empfaenger_email`, `betreff_vorlage`, `text_vorlage`, `dateiformat`, `pflichtfelder` (JSON), `feldreihenfolge` (JSON)
- [ ] Tabelle `supplier_document_parsers` existiert mit: `supplier_id`, `beispiel_datei_pfad`, `feld_mapping` (JSON), `erkennungsregeln` (JSON), `parser_typ`

### Dokumente
- [ ] Tabelle `documents` existiert mit: `typ`, `quelle`, `pfad`, `datei_hash`, `ocr_text`, `erkannter_lieferant_id`, `erkannte_bestellung_id`, `erkannter_wareneingang_id`, `dubletten_status`, `zuordnungs_status`, `metadaten` (JSON)
- [ ] Tabelle `document_assignment_rules` existiert mit: `name`, `prioritaet`, `bedingungen` (JSON), `ziel_typ`, `aktiv`

### Wareneingang
- [ ] Tabelle `goods_receipts` existiert mit: `purchase_order_id`, `supplier_id`, `warehouse_id`, `kontrollstufe`, `status`, `arrived_at`, `gebucht_by_employee_id`, `notiz`
- [ ] Tabelle `goods_receipt_items` existiert mit: `goods_receipt_id`, `product_id`, `bestellte_menge`, `gelieferte_menge`, `abweichungs_grund`, `mhd` (date nullable), `mhd_pflicht` (bool)
- [ ] Direktes Buchen bei „angekommen" erstellt `stock_movements` mit Referenz auf `goods_receipt`

### MHD
- [ ] Tabelle `product_mhd_batches` existiert mit: `product_id`, `warehouse_id`, `mhd` (date), `menge` (decimal), `goods_receipt_item_id`, `eingeraeumt_by_employee_id`, `eingeraeumt_at`, `notes`
- [ ] `stock_movements` hat Felder: `mhd_batch_id` (nullable FK), `employee_id` (nullable FK)
- [ ] MHD-Batches geben Auskunft über Herkunft: Lieferant, Bestellung, Wareneingang, Mitarbeiter

### Aussortierung
- [ ] Tabelle `product_write_offs` existiert mit: `product_id`, `mhd_batch_id`, `warehouse_id`, `menge`, `typ` (ENUM: bruch/abgelaufen/mhd_rabatt/sonstig), `ursache` (ENUM), `erfasst_by_employee_id`, `notes`

### Leergutrücknahme
- [ ] Tabellen `leergut_returns` und `leergut_return_items` existieren
- [ ] Felder für Paletten, Standard-Kästen, seltene Kästen, Kontrollzählung, zwei Foto-Pfade

---

## Architekturentscheidungen

### Goods Receipts vs. PurchaseOrders
`goods_receipts` ist eine eigene Tabelle, nicht nur ein Status-Update an `purchase_orders`.
Begründung: Eine Lieferung kann mehrere Teillieferungen haben; der Wareneingangs-Workflow
(Kontrollstufen, MHD-Erfassung, Mitarbeiter) hat einen eigenen Lebenszyklus unabhängig von
der Bestellverwaltung.

### Zentrale documents-Tabelle
Alle Dokumenttypen in einer Tabelle mit polymorphem `documentable`-Bezug (nullable).
Duplikate werden über `datei_hash` erkannt aber nicht automatisch gelöscht.

### MHD auf Charge-Ebene
`product_mhd_batches` hält den aktuellen Bestand je MHD+Produkt+Lager.
`stock_movements` referenziert optional eine MHD-Batch (bei MHD-pflichtigen Produkten).
Dadurch ist FIFO-Tracking möglich und das Wiederauftauchen alter MHDs nachvollziehbar.

### Mindestbestellwert immer als Netto-EK
Alle Preisgrenzen für Mindestbestellwerte sind in `milli`-Cent (Integer) als Netto-EK.
Term im System: immer „Netto-EK", nie nur „netto".

### Kontrollstufen
ENUM: `nur_angekommen`, `summenkontrolle_vpe`, `summenkontrolle_palette`, `positionskontrolle`, `positionskontrolle_mit_mhd`
Default am Lieferanten, überschreibbar an der Bestellung, optional pro Produkt/Warengruppe.

---

## Technical Notes

- Alle neuen Tabellen haben `company_id` (Multi-Tenant-Vorbereitung)
- `ninoxalt_`-Tabellen werden separat für Leergutrücknahme-Logik importiert (späteres Feature)
- Dokument-Parser-Konfiguration ist JSON-basiert (kein Hard-Code pro Lieferant)
- LS POS: internes Datenmodell wird NICHT an LS POS vereinfacht; Brücke kann später gebaut werden
