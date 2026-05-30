# Technischer Implementierungsplan: Veranstaltungen & Angebote (PROJ-38)

Stand: 2026-05-16 | Feature-ID: PROJ-38

---

## 1. Bestandsanalyse

### 1.1 JTL-Tabellen (vorhanden in Shoptour2)

| Tabelle | Inhalt | Relevant für Angebote? |
|---|---|---|
| `wawi_auftraege` | JTL-Aufträge (nAuftragStatus, cAuftragsNr, kKunde) | Nein – nur Aufträge, keine Angebote |
| `wawi_auftragspositionen` | JTL-Auftragspositionen | Nein |
| `wawi_kunden` | JTL-Kunden (cKundenNr) | Nur zur Kundenzuordnung |
| `wawi_artikel` | JTL-Artikel (cArtNr, cName) | Nur zur Artikelzuordnung |
| `wawi_preise` | JTL-Preise | Referenz |
| `wawi_rechnungen` | JTL-Rechnungen | Nein |

**Ergebnis:** Keine JTL-Angebotsdaten (wawi_angebote) vorhanden. Der JTL-Discovery-Service zeigt einen Admin-Hinweis.

### 1.2 Ninox-Tabellen (vorhanden, lesbar)

| Tabelle | Relevante Felder | Verwendung |
|---|---|---|
| `ninox_bestellannahme` | ninox_id, kunden, status, lieferdatum, datum_der_veranstaltung, gehoert_zu_veranstaltung, anzahl_gaeste, festbedarf_warenkorb, preis, lieferschein_nr, text_mehrzeilig, liefer_anschrift, art | Event-Angebotskandidaten |
| `ninox_veranstaltung` | ninox_id, name, beschreibung, kunden, veranstaltungsjahr (json) | EventSeries-Import |
| `ninox_veranstaltungsjahr` | ninox_id, jahr, titel, beschreibung, veranstaltung, status, art_der_veranstaltung, anzahl_erwartete_personen, festbedarf_warenkorb | EventOccurrence-Import |
| `ninox_kontakte` | ninox_id, vorname, nachname, telefon, e_mail, veranstaltung, kunden, rollen | EventContact-Import |
| `ninox_fest_inventar` | ninox_id, artikelbezeichnung, artnrkehr, bestand_leiheinheiten | Festbedarf-Referenz |
| `ninox_veranstaltungstage` | ninox_id, datum, von, bis, veranstaltungsjahr | Logistiktermine |

### 1.3 Bestehende Infrastruktur

| Tabelle/Klasse | Verwendung im neuen Modul |
|---|---|
| `audit_logs` | Bereits vorhanden – Statusänderungen, PDF-Zuordnung, Angebotsannahme |
| `orders` | Zielobjekt bei Angebotsannahme, hat bereits `is_event_order`, `desired_delivery_date`, `event_location_*` |
| `customers` | Kundenstamm (customer_id in EventOccurrence, EventSeries) |
| `contacts` | Vorhandene Kontaktstruktur |
| `source_matches` | Vorhandene Quellverknüpfung (kann genutzt werden) |
| `communications` | Gmail-Anbindung vorbereitet (email_message_id in offer_versions) |

---

## 2. Neue Tabellen (PROJ-38)

| # | Tabelle | Zweck |
|---|---|---|
| 1 | `event_series` | Wiederkehrende Veranstaltungsserie |
| 2 | `event_occurrences` | Konkrete Veranstaltung pro Jahr |
| 3 | `event_offer_versions` | Angebotsversionen (ST-A-YYYY-000001 oder JTL-Nr.) |
| 4 | `event_offer_items` | Angebotspositionen |
| 5 | `event_contacts` | Ansprechpartner per Veranstaltung |
| 6 | `event_logistics_appointments` | Liefer-/Abholtermine |
| 7 | `event_rental_reservations` | Festbedarf-Reservierungen (weich/hart) |
| 8 | `event_weather_snapshots` | Wetterdaten (Prognose + Nachher) |
| 9 | `event_post_calculations` | Nachkalkulation |
| 10 | `event_tasks` | Aufgaben/Checkliste |
| 11 | `event_import_links` | Quellenreferenz-Registry |
| 12 | `offer_number_sequences` | Jahressequenz für ST-A-YYYY-000001 |

---

## 3. Services

| Service | Aufgabe |
|---|---|
| `NinoxEventImportService` | Ninox-Bestellannahme → EventOccurrence + EventOfferVersion; Ninox-Veranstaltung → EventSeries |
| `JtlOfferDiscoveryService` | Prüft JTL-Tabellen auf Angebotsdaten; gibt Admin-Report zurück |
| `OfferNumberService` | Generiert ST-A-YYYY-000001 (SELECT FOR UPDATE auf offer_number_sequences) |
| `OfferAcceptanceService` | Angebotsannahme: Status setzen, Festbedarf hart reservieren, Bestellung erzeugen |
| `EventOfferAuditService` | Schreibt in vorhandene audit_logs-Tabelle |

---

## 4. Ninox-Status-Mapping

```
Ninox status="Angebot schreiben"   → offer_status=requested        | needs_review wenn Felder fehlen
Ninox status="Angebot geschrieben" → offer_status=sent
Ninox status="Angebot abgelehnt"   → offer_status=rejected
Ninox status=leer/sonstig          → offer_status=draft             | needs_review=true
ninox_bestellannahme.gehoert_zu_veranstaltung vorhanden → event_series_id verknüpfen
```

---

## 5. Angebotsnummern-Format

```
Shoptour2-Angebote: ST-A-YYYY-000001  (z.B. ST-A-2026-000001)
JTL-Angebote:       externe Nummer aus wawi_auftraege.cAuftragsNr (als external_offer_number)
Ninox-Import:       keine eigene ST-Nummer → external_offer_number = ninox_id
```

---

## 6. Angebotsannahme-Logik

```
1. Prüfung Pflichtdaten:
   - customer_id vorhanden?
   - event_start_at vorhanden?
   - offer items vorhanden?
   - Alle Artikel gemappt (kein is_free_text=true ohne article_id)?
   - Preise vollständig?
   - Festbedarfkonflikt?

2a. Alles OK → Bestellung erzeugen:
    - orders.is_event_order = true
    - orders.desired_delivery_date = Liefertermin aus Logistik
    - event_offer_versions.converted_order_id = neue Order-ID
    - event_offer_versions.status = converted_to_order
    - event_occurrences.event_status = confirmed
    - Festbedarf: event_rental_reservations.reservation_type = hard
    - AuditLog schreiben

2b. Daten fehlen → needs_review:
    - event_offer_versions.conversion_status = needs_review
    - event_offer_versions.conversion_error = JSON mit Fehlerliste
    - event_offer_versions.status = accepted (Angebot bleibt angenommen)
    - EventTask erzeugen: "Bestellungserzeugung manuell prüfen"
    - AuditLog schreiben
```

---

## 7. Admin-Routen (PROJ-38)

```
/admin/veranstaltungen                          → Übersicht (Liste)
/admin/veranstaltungen/kalender                 → Kalenderansicht
/admin/veranstaltungen/anfragen                 → Offene Anfragen
/admin/veranstaltungen/import                   → Importprüfung (Ninox + JTL)
/admin/veranstaltungen/serien                   → Wiederkehrende Veranstaltungen
/admin/veranstaltungen/{occurrence}             → Detailseite
/admin/veranstaltungen/{occurrence}/angebote    → Angebotsverwaltung
/admin/veranstaltungen/{occurrence}/angebot/{version}/annehmen → Angebotsannahme
/admin/veranstaltungen/{occurrence}/angebot/{version}/pdf      → PDF-Upload
```

---

## 8. Tests

| Testklasse | Prüft |
|---|---|
| `NinoxStatusMappingTest` | Alle 4 Status-Mapping-Fälle inkl. needs_review |
| `OfferNumberGenerationTest` | ST-A-YYYY-000001 Format, Jahreswechsel, Concurrent-Safety |
| `OfferAcceptanceTest` | Erfolgreiche Annahme + needs_review Fälle |
| `OrderCreationFromOfferTest` | Bestellung korrekt erzeugt mit allen Feldern |
| `ImportLinkingTest` | event_import_links korrekt gesetzt |
| `NeedsReviewTest` | Alle 8 Fehlerfälle lösen needs_review aus |
| `PreviousYearLinkTest` | Vorjahresverknüpfung über event_series_id |

---

## 9. Nicht-Ziele MVP 1

- Vollautomatischer Gmail-Gesendet-Import
- Vollständiger PDF-Autoabgleich
- Kundenportal-Angebotslink
- Wetter-API-Integration
- KI-Kalkulation
- Vollständige Fahrer-PWA-Integration

---

## 10. Offene Punkte / Manuelle Prüfung

1. **JTL-Angebote**: wawi_angebote-Tabelle fehlt. JTL-Ameise-Export oder API prüfen.
2. **Ninox-Bestellannahme Status-Werte**: Tatsächliche Status-Werte in Produktion prüfen (SELECT DISTINCT status FROM ninox_bestellannahme).
3. **Ninox-Veranstaltungsjahr-Verknüpfung**: Feld `gehoert_zu_veranstaltung` in ninox_bestellannahme prüfen – verweist auf ninox_veranstaltungsjahr oder ninox_veranstaltung?
4. **Festbedarf-Artikel-Mapping**: ninox_fest_inventar zu Shoptour2-Artikel (products) manuell mappen.
5. **PDF-Speicherpfad**: Storage-Konfiguration für PDF-Uploads prüfen.
6. **Gmail-Integration**: Gesendet-Ordner für PDF-Angebote konfigurieren (MVP 2).
