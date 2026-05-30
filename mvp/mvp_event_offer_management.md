# MVP: Veranstaltungs- und Angebotsverwaltung in Shoptour2

## Ziel

Shoptour2 erhält ein eigenes Modul **„Veranstaltungen & Angebote“**.

Das Modul ersetzt langfristig die bisherige Mischlösung aus:

- Ninox-Bestellungen mit Angebotsstatus
- JTL-Wawi-Angeboten
- manuellem E-Mail-Versand
- unvollständiger Veranstaltungsjahreslogik

Shoptour2 ist künftig führend. Ninox und JTL sind Übergangs- und Importquellen.

Wichtig:

- Nicht einfach bestehende Bestellungen um weitere Angebotsstatus erweitern.
- Veranstaltungen, Angebotsanfragen, Angebotsversionen, Logistik, Festbedarf und Nachkalkulation müssen fachlich getrennt modelliert werden.
- Historische Daten aus Ninox/JTL dürfen nicht überschrieben werden.
- Importierte Daten müssen quellenreferenziert bleiben.

---

## Vorhandene Quellen

### Ninox

Es gibt bereits:

- Ninox-API-Anbindung
- `ninox_import` Tabelle bzw. importierte MySQL-Tabellen in Shoptour2
- Die Daten liegen vermutlich bereits tabellarisch in MySQL vor

Relevante Ninox-Tabellen:

```text
Bestellannahme:
Bestellungen bzw. Angebotsanfragen, abhängig vom Feld Status

Veranstaltungen:
Wiederkehrende Veranstaltungen, die jedes Jahr stattfinden

Veranstaltungsjahr:
Die konkrete Veranstaltung in einem bestimmten Jahr,
verknüpft mit Veranstaltungen

Kontakte:
Kontaktdaten potenzieller Ansprechpartner

Positionen:
Für Veranstaltungen aktuell nicht vorhanden
```

Wichtige Einschränkungen:

- Angebotspositionen sind in Ninox nicht strukturiert vorhanden.
- Verknüpfungen zwischen Bestellannahme und Veranstaltung/Veranstaltungsjahr existieren nur vereinzelt.
- Veranstaltungsjahr wurde nicht konsequent genutzt.
- Einige Veranstaltungen wurden kopiert/dupliziert.
- Teilweise gibt es Vorjahresbezüge.
- Alle historischen Angebots-/Veranstaltungsdaten sollen übernommen werden.

---

### JTL Wawi

Es gibt bereits eine Synchronisation der JTL-Wawi-Daten in eine Shoptour2-Datenbank.

Annahmen:

- JTL-Tabellen sind vermutlich vollständig synchronisiert.
- Angebote könnten enthalten sein, muss geprüft werden.
- Angebotspositionen könnten enthalten sein, muss geprüft werden.
- Belegtyp sollte vorhanden sein.
- Angebotsnummer und Kundennummer sollten vorhanden sein.
- Ob Umwandlung Angebot → Auftrag erkennbar ist, muss geprüft werden.

JTL ist aktuell Quelle für:

- Angebotsnummern
- Angebotspositionen
- Preise
- PDF-Angebote, indirekt über Gmail/Gesendet
- Status „in Auftrag umgewandelt“, falls in JTL erkennbar

---

### Gmail / E-Mail

Versand der Angebote läuft aktuell aus JTL über:

```text
kolabrigetraenke@gmail.com
bzw.
getraenke@kolabri.de
```

Betreff enthält typischerweise:

```text
Angebot AN05125620
```

PDF-Dateiname enthält ebenfalls typischerweise:

```text
Angebot AN05125620
```

Wichtig:

- Aktuell werden E-Mails noch nicht vollständig importiert.
- Eventuell wird bisher nur der Posteingang importiert, nicht der Gesendet-Ordner.
- Gmail-PDF-Zuordnung soll vorbereitet werden.
- Vollautomatischer Gmail-Import ist nicht Teil von MVP 1, außer vorhandene Mailimport-Struktur kann ohne großen Zusatzaufwand erweitert werden.
- In MVP 1 muss PDF manuell hochladbar/verknüpfbar sein.

---

## Fachliches Datenmodell

### 1. Veranstaltungsserie

Tabelle:

```text
event_series
```

Zweck:

Wiederkehrende Veranstaltung über mehrere Jahre.

Beispiele:

- Kerb Verein XY
- Sommerfest Feuerwehr
- Fastnacht Sportverein
- Weihnachtsmarktstand Kunde Z

Felder:

```text
id
customer_id nullable
name
event_type
default_location_name
default_address
default_notes
is_active
source_system nullable
source_table nullable
source_record_id nullable
created_at
updated_at
```

Wichtige Logik:

- Eine Serie kann viele konkrete Veranstaltungsjahre haben.
- Import aus Ninox-Tabelle `Veranstaltungen`.
- Wenn keine sichere Verknüpfung existiert, darf keine aggressive automatische Zusammenführung erfolgen.
- Dubletten nur markieren, nicht automatisch verschmelzen.

---

### 2. Veranstaltungsjahr / Event Occurrence

Tabelle:

```text
event_occurrences
```

Zweck:

Konkrete Veranstaltung in einem bestimmten Jahr.

Beispiele:

- Kerb Verein XY 2025
- Kerb Verein XY 2026
- Hochzeit Müller 2026

Felder:

```text
id
event_series_id nullable
customer_id
billing_customer_id
title
event_type
is_recurring
event_year
event_start_at
event_end_at
calendar_week nullable
location_name
address_line1
address_line2
postal_code
city
country
expected_guests nullable
actual_guests nullable
indoor_outdoor_type nullable
request_channel
offer_status
event_status
source_system nullable
source_table nullable
source_record_id nullable
import_confidence
needs_review boolean
internal_notes
customer_visible_notes
created_at
updated_at
```

`request_channel`:

```text
email
phone
whatsapp
in_person
shop
jtl
ninox
other
unknown
```

`event_type`:

```text
kerb
fastnacht
sommerfest
geburtstag
hochzeit
kommunion
firmenfeier
vereinsfest
weihnachtsmarkt
sonstiges
unknown
```

---

### 3. Angebotsversionen

Tabelle:

```text
event_offer_versions
```

Zweck:

Ein Event kann mehrere Angebotsversionen haben.

Felder:

```text
id
event_occurrence_id
offer_number
external_offer_number nullable
version_number
source_system
status
valid_until nullable
created_by_user_id nullable
sent_at nullable
accepted_at nullable
rejected_at nullable
converted_order_id nullable
net_total nullable
gross_total nullable
deposit_total nullable
rental_total nullable
delivery_total nullable
pdf_file_path nullable
external_pdf_reference nullable
email_message_id nullable
conversion_status nullable
conversion_error nullable
created_at
updated_at
```

`source_system`:

```text
shoptour2
jtl
ninox
gmail
manual
```

`status`:

```text
draft
external_offer_created
sent
revision_requested
accepted
rejected
expired
cancelled
converted_to_order
```

Wichtige Logik:

- Importierte JTL-Angebote behalten JTL-Angebotsnummer.
- Neue Shoptour2-Angebote erhalten eigene Angebotsnummer.
- Shoptour2-Angebotsnummern müssen nicht mit JTL synchron sein.
- Angebot darf versioniert werden.
- Alte Versionen bleiben erhalten.

---

### 4. Angebotspositionen

Tabelle:

```text
event_offer_items
```

Felder:

```text
id
event_offer_version_id
article_id nullable
external_article_number nullable
name
description nullable
quantity
unit
unit_price_net nullable
unit_price_gross nullable
tax_rate nullable
deposit_amount nullable
line_total_net nullable
line_total_gross nullable
item_type
is_optional boolean
is_alternative boolean
alternative_group nullable
is_free_text boolean
supplied_by_us boolean nullable
third_party_supply_note nullable
sort_order
created_at
updated_at
```

`item_type`:

```text
beverage
rental
delivery
pickup
deposit
discount
fee
free_text
other
```

Regel:

- Artikel aus Shoptour2-Artikelstamm bevorzugen.
- Freitextpositionen sind erlaubt.
- Freitextpositionen müssen sichtbar markiert werden.
- Optionale Positionen sind erlaubt.
- Alternativpositionen sind erlaubt.

---

### 5. Ansprechpartner

Tabelle:

```text
event_contacts
```

Felder:

```text
id
event_occurrence_id
customer_contact_id nullable
source_contact_id nullable
name
role
phone
mobile
email
whatsapp_allowed boolean nullable
is_primary boolean
notes
created_at
updated_at
```

`role`:

```text
main
billing
delivery
setup
pickup
board
cashier
other
```

Import aus Ninox-Tabelle `Kontakte`.

---

### 6. Logistiktermine

Tabelle:

```text
event_logistics_appointments
```

Felder:

```text
id
event_occurrence_id
type
status
scheduled_date
time_from nullable
time_to nullable
time_flexibility
address_snapshot
contact_name nullable
contact_phone nullable
tour_id nullable
vehicle_type_id nullable
estimated_trips nullable
actual_trips nullable
helpers_available boolean nullable
access_notes
driver_notes
created_at
updated_at
```

`type`:

```text
delivery
pickup
empty_goods_pickup
additional_delivery
setup
dismantling
inspection
other
```

`time_flexibility`:

```text
fixed
flexible
morning
afternoon
by_arrangement
unknown
```

`status`:

```text
planned
scheduled
in_progress
done
cancelled
failed
```

Wichtige Logik:

- Eine Veranstaltung kann mehrere Lieferungen/Abholungen haben.
- Ein Logistiktermin kann einer Tour zugeordnet werden.
- Ein Logistiktermin kann mehrere Fahrten benötigen.
- Fahrer-PWA soll später diese Termine anzeigen können.

---

### 7. Festbedarf-Reservierungen

Tabelle:

```text
event_rental_reservations
```

Felder:

```text
id
event_occurrence_id
article_id
quantity
reservation_type
reservation_status
date_from
date_to
source_offer_item_id nullable
notes
created_at
updated_at
```

`reservation_type`:

```text
soft
hard
```

`reservation_status`:

```text
requested
reserved
confirmed
released
cancelled
conflict
```

Regel:

- Ab Angebotsstatus `sent`: weiche Reservierung möglich.
- Ab Angebotsstatus `accepted`: harte Reservierung.
- Konflikte im Kalender anzeigen.
- Überbuchung nur mit Adminfreigabe.

---

### 8. Wetterdaten

Tabelle:

```text
event_weather_snapshots
```

Felder:

```text
id
event_occurrence_id
weather_type
source
captured_at
forecast_for_at nullable
temperature_min nullable
temperature_max nullable
temperature_avg nullable
precipitation_mm nullable
wind_speed nullable
weather_description nullable
raw_payload nullable
created_at
updated_at
```

`weather_type`:

```text
forecast
actual
manual
```

Regel:

- Vor Veranstaltung Prognose speichern.
- Nach Veranstaltung echte historische Wetterdaten speichern.
- Prognose und echte Daten nicht vermischen.

---

### 9. Nachkalkulation

Tabelle:

```text
event_post_calculations
```

Zweck:

Nach der Veranstaltung erfassen, was wirklich passiert ist.

Felder:

```text
id
event_occurrence_id
actual_guests nullable
planned_guests nullable
planned_gross_total nullable
actual_gross_total nullable
revenue_per_guest nullable
delivered_value nullable
returned_full_goods_value nullable
deposit_difference nullable
empty_goods_notes
third_party_drinks_present boolean nullable
exclusive_supplier boolean nullable
self_supplied_categories json nullable
special_events_notes
deviation_from_previous_year_notes
recommendation_next_year
created_by_user_id nullable
completed_at nullable
created_at
updated_at
```

Wichtig:

Nachkalkulation heißt:

```text
geplant vs. tatsächlich:
- Gäste
- gelieferte Mengen
- zurückgegebene Mengen
- Umsatz
- Wetter
- Besonderheiten
- Empfehlung fürs nächste Jahr
```

---

### 10. Aufgaben / Checklisten

Tabelle:

```text
event_tasks
```

Felder:

```text
id
event_occurrence_id
title
description nullable
status
due_at nullable
assigned_user_id nullable
created_by_user_id nullable
task_type
created_at
updated_at
```

`task_type`:

```text
clarification
offer
delivery
pickup
rental_check
payment
post_calculation
customer_followup
other
```

Beispiele:

- Rückfrage Gästezahl
- Vorjahreskalkulation prüfen
- Kühlwagen reservieren
- Angebot nachfassen
- Bestellung erzeugt prüfen
- Nachkalkulation eintragen

---

### 11. Import-Links / Quellenreferenz

Tabelle:

```text
event_import_links
```

Felder:

```text
id
entity_type
entity_id
source_system
source_table
source_record_id
external_number nullable
raw_payload_hash nullable
imported_at
last_seen_at
created_at
updated_at
```

Zweck:

Jede importierte Information muss nachvollziehbar bleiben.

---

### 12. Auditlog

Falls es bereits ein Auditlog gibt, verwenden. Sonst eigene Tabelle:

```text
event_audit_logs
```

Felder:

```text
id
entity_type
entity_id
action
old_values json nullable
new_values json nullable
user_id nullable
source_system nullable
created_at
```

Regel:

- Statusänderungen historisieren
- Preisänderungen historisieren
- Angebotsannahmen historisieren
- PDF-Zuordnungen historisieren
- Importänderungen historisieren

---

## Statusmodell

### Angebotsstatus

```text
requested              Angebot angefragt
needs_clarification    Rückfragen offen
draft                  Angebot in Bearbeitung
external_offer_created Angebot extern in JTL erstellt
sent                   Angebot gesendet
revision_requested     Korrektur notwendig
accepted               Angebot angenommen
rejected               Angebot abgelehnt
expired                Angebot abgelaufen
cancelled              storniert
converted_to_order     in Bestellung umgewandelt
```

### Veranstaltungsstatus

```text
planned                geplant
confirmed              bestätigt
delivery_planned       Lieferung geplant
partially_delivered    teilweise geliefert
delivered              geliefert
during_event           Veranstaltung läuft
pickup_planned         Abholung geplant
picked_up              abgeholt
post_calculation_open  Nachkalkulation offen
closed                 abgeschlossen
```

---

## Mapping Ninox-Status

Aus Ninox-Tabelle `Bestellannahme`, Feld `Status`:

```text
Angebot schreiben    → requested oder needs_clarification
Angebot geschrieben  → sent
Angebot abgelehnt    → rejected
bestellt             → accepted / converted_to_order
```

Importregel:

- Status nicht blind überschreiben, wenn Shoptour2 bereits führend bearbeitet wurde.
- Bei Unsicherheit `needs_review = true`.
- Importkonflikte sichtbar machen.

---

## Erkennung von Veranstaltungsdaten aus Ninox

Ein Ninox-Datensatz aus `Bestellannahme` ist ein Event-/Angebotskandidat, wenn mindestens eines zutrifft:

```text
- Status ist Angebot schreiben
- Status ist Angebot geschrieben
- Status ist Angebot abgelehnt
- Status ist bestellt und Verknüpfung zu Veranstaltung/Veranstaltungsjahr vorhanden
- Textfelder enthalten Veranstaltungshinweise
- Liefer-/Abholdatum vorhanden
- Festbedarf oder Veranstaltungsnotizen vorhanden
```

Da Positionen in Ninox für Veranstaltungen fehlen, werden keine Angebotspositionen aus Ninox erwartet.

---

## JTL-Importprüfung

Claude soll zuerst prüfen, welche JTL-Tabellen in Shoptour2 vorhanden sind.

Zu prüfen:

```text
- Gibt es JTL-Angebote?
- Gibt es Angebotspositionen?
- Gibt es Belegtyp Angebot/Auftrag/Rechnung?
- Gibt es Angebotsnummer?
- Gibt es Kundennummer?
- Gibt es Belegstatus?
- Gibt es Hinweis auf Umwandlung Angebot → Auftrag?
- Gibt es PDF-Referenzen?
```

Wenn Angebotsdaten vorhanden:

- als `event_offer_versions` importieren
- Positionen als `event_offer_items` importieren
- Kundennummer mit Shoptour2-Kunde verknüpfen
- Angebotsnummer als `external_offer_number` speichern
- Quelle `jtl`

Wenn keine Angebotsdaten vorhanden:

- Admin-Hinweis anzeigen:
  - „JTL-Angebotstabellen nicht gefunden“
  - „Ameise/API/SQL-Mapping erforderlich“

---

## Gmail-PDF-Zuordnung

Nicht vollautomatisch in MVP 1 erzwingen.

Aber Datenmodell vorbereiten:

```text
event_offer_versions.email_message_id
event_offer_versions.pdf_file_path
event_offer_versions.external_pdf_reference
```

MVP 1:

- PDF manuell hochladen
- PDF manuell einem Angebot zuordnen
- optional: Dateiname auswerten, wenn `AN...` enthalten ist

Später:

- Gesendet-Ordner importieren
- Betreff `Angebot AN05125620` erkennen
- PDF-Anhang erkennen
- Angebotsnummer extrahieren
- automatisch mit Angebotsversion verknüpfen

---

## Eigene Shoptour2-Angebotsnummern

Neue Angebote aus Shoptour2 bekommen eigene Angebotsnummern.

Vorschlag Format:

```text
ST-A-YYYY-000001
```

Beispiel:

```text
ST-A-2026-000001
```

Regeln:

- JTL-Angebote behalten ihre JTL-Nummer.
- Shoptour2-Angebote bekommen eigene Nummer.
- Keine Pflicht zur Synchronität mit JTL.
- Angebotsnummer darf nach Erstellung nicht mehr geändert werden.

---

## Angebotsannahme

Wenn ein Angebot angenommen wird:

1. Status Angebotsversion → `accepted`
2. Status Veranstaltung → `confirmed`
3. Festbedarf wird hart reserviert
4. Bestellung wird direkt erzeugt
5. Angebotsversion erhält `converted_order_id`
6. Status Angebotsversion → `converted_to_order`
7. Logistiktermine werden vorbereitet bzw. in Tourenplanung sichtbar
8. Bestätigungsmail wird vorbereitet/versendet, falls Mailmodul vorhanden

Wichtig:

- Da automatische Bestellungserzeugung gewünscht ist, soll die Bestellung direkt erzeugt werden.
- Trotzdem muss ein Fehler-/Review-Mechanismus rein:
  - fehlender Kunde
  - fehlende Artikelverknüpfung
  - Freitextpositionen
  - ungültige Preise
  - Festbedarfkonflikt
  - fehlendes Lieferdatum

Bei solchen Fehlern:

```text
Angebot bleibt accepted,
aber Bestellungserzeugung bekommt Status conversion_failed / needs_review.
```

---

## Admin-Oberfläche MVP 1

### Hauptmenü

Neuer Punkt:

```text
Veranstaltungen & Angebote
```

Unterpunkte:

```text
Übersicht
Kalender
Offene Anfragen
Angebote
Importprüfung
Wiederkehrende Veranstaltungen
Nachkalkulation offen
```

---

### Listenansicht

Spalten:

```text
Datum
Titel
Kunde
Veranstaltungsart
Gäste erwartet
Angebotsstatus
Veranstaltungsstatus
Quelle
Angebotsnummer
Lieferdatum
Abholdatum
Review nötig
```

Filter:

```text
Status
Zeitraum
Kunde
Veranstaltungsart
Quelle
Review nötig
Wiederkehrend
Nachkalkulation offen
```

---

### Detailseite Veranstaltung

Tabs:

```text
Übersicht
Angebote
Positionen
Ansprechpartner
Logistik
Festbedarf
Vorjahr
Wetter
Nachkalkulation
Aufgaben
Importdaten
Änderungsverlauf
```

---

### Kalenderansicht

Anzeigen:

- Veranstaltungstag
- Lieferung
- Abholung
- Nachlieferung
- Leergutabholung
- Festbedarf-Reservierung

Farbliche Trennung:

```text
Anfrage
Angebot gesendet
Angenommen
Geliefert
Abholung offen
Abgeschlossen
Konflikt
```

---

## Rechte

### Mitarbeiter

Dürfen:

- Veranstaltungen anlegen
- Angebotsanfragen erfassen
- Angebote bearbeiten
- Angebote senden
- Angebote annehmen/ablehnen
- Logistiktermine bearbeiten
- Ansprechpartner bearbeiten
- Aufgaben bearbeiten
- Nachkalkulation erfassen

### Admin

Zusätzlich:

- Preise ändern
- Angebotsvorlagen ändern
- Import-Mapping ändern
- Konflikte übersteuern
- Festbedarfüberbuchung freigeben
- Status hart zurücksetzen
- Automatisierungen konfigurieren

---

## Nicht-Ziele für MVP 1

Nicht in MVP 1 erzwingen:

```text
- vollständiger Gmail-Gesendet-Import
- vollständiger PDF-Autoabgleich
- Kundenportal mit Angebotsannahmelink
- perfekte Wetter-API-Integration
- automatische KI-Kalkulation
- vollständige JTL-Ersetzung
- vollständige Fahrer-PWA-Integration
```

Aber das Datenmodell muss darauf vorbereitet sein.

---

## Akzeptanzkriterien MVP 1

Claude soll erst fertig sein, wenn Folgendes funktioniert:

```text
1. Adminmodul „Veranstaltungen & Angebote“ existiert.

2. Ninox-Daten aus Bestellannahme können als Angebots-/Veranstaltungskandidaten erkannt werden.

3. Ninox-Veranstaltungen und Veranstaltungsjahr können importiert/verknüpft werden.

4. Ninox-Kontakte können als Event-Ansprechpartner importiert werden.

5. Unklare Importfälle werden mit needs_review markiert.

6. JTL-Angebotstabellen werden automatisch gesucht/geprüft.

7. Falls JTL-Angebote vorhanden sind, werden sie angezeigt.

8. Angebotsnummern aus JTL werden gespeichert.

9. PDF kann manuell einem Angebot zugeordnet werden.

10. Neue Shoptour2-Angebote bekommen eigene Angebotsnummer.

11. Veranstaltung kann mehrere Ansprechpartner haben.

12. Veranstaltung kann mehrere Logistiktermine haben.

13. Festbedarf kann weich und hart reserviert werden.

14. Angebots- und Veranstaltungsstatus sind getrennt.

15. Angebot kann angenommen werden.

16. Bei Annahme wird automatisch eine Bestellung erzeugt oder ein sauberer Fehlerstatus gesetzt.

17. Nachkalkulation kann manuell erfasst werden.

18. Vorjahresbezug kann manuell gesetzt werden.

19. Wiederkehrende Veranstaltungen können aus Vorjahr kopiert werden.

20. Änderungen werden historisiert.
```

---

## Claude-Code-Prompt

Diesen Prompt an Claude geben:

```text
Du arbeitest im bestehenden Laravel/MySQL-Projekt Shoptour2.

Bitte implementiere das MVP „Veranstaltungen & Angebote“ gemäß der Datei:

/srv/shoptour2/docs/mvp_event_offer_management.md

Wichtige Grundregeln:

1. Baue kein simples Angebotsstatus-Feld an bestehende Bestellungen.
   Es muss ein eigenes Modul für Veranstaltungen, Angebotsanfragen, Angebotsversionen, Logistik, Festbedarf, Nachkalkulation und Vorjahresbezug entstehen.

2. Shoptour2 ist künftig führend.
   Ninox und JTL sind Import-/Übergangsquellen.

3. Bestehende Importdaten dürfen nicht überschrieben oder mutiert werden.
   Nutze Import-/Stagingdaten nur lesend und verknüpfe sie über neue Tabellen.

4. Historische Angebotsdaten müssen nachvollziehbar bleiben.
   Jede importierte Information braucht Quellenreferenz:
   source_system, source_table, source_record_id.

5. Bitte prüfe zuerst die vorhandene Datenbankstruktur:
   - vorhandene ninox_import / Ninox-Tabellen
   - vorhandene wawi_ / JTL-Tabellen
   - vorhandene Kunden-, Artikel-, Bestell-, Touren- und Festbedarfstabellen
   - vorhandene Auditlog-/Berechtigungsstruktur

6. Erstelle danach einen kurzen technischen Umsetzungsplan als Markdown-Datei:
   /srv/shoptour2/docs/event_offer_management_implementation_plan.md

7. Implementiere dann MVP 1:
   - Datenbankmigrationen
   - Modelle
   - Beziehungen
   - Import-/Mapping-Service für Ninox
   - JTL-Angebots-Discovery-Service
   - Admin-Controller
   - Admin-Views
   - Listenansicht
   - Detailansicht
   - Kalender-Grundansicht
   - manuelle PDF-Zuordnung
   - Statuslogik
   - Angebotsannahme mit automatischer Bestellungserzeugung
   - Fehler-/Review-Status bei unvollständiger Konvertierung
   - Auditlog, falls möglich über bestehende Struktur

8. Keine vollständige Gmail-Automation in MVP 1 bauen.
   Nur Datenmodell und manuelle PDF-Zuordnung vorbereiten.
   Optional darf eine einfache Dateinamen-Erkennung für „Angebot AN...“ eingebaut werden.

9. Neue Shoptour2-Angebote bekommen eigene Angebotsnummern im Format:
   ST-A-YYYY-000001

10. JTL-Angebote behalten ihre externe JTL-Angebotsnummer.

11. Angebotsstatus und Veranstaltungsstatus müssen getrennt sein.

12. Bei angenommenem Angebot:
   - Angebotsstatus accepted
   - Veranstaltung confirmed
   - Festbedarf hart reservieren
   - Bestellung automatisch erzeugen
   - bei Fehlern conversion_failed / needs_review setzen

13. Baue Tests für:
   - Ninox-Statusmapping
   - Angebotsnummerngenerierung
   - Angebotsannahme
   - Bestellungserzeugung
   - Import-Linking
   - needs_review-Fälle
   - Vorjahresverknüpfung

14. Bitte keine bestehenden produktiven Tabellen destruktiv ändern.
   Neue Tabellen bevorzugen.
   Bestehende Tabellen nur erweitern, wenn eindeutig sinnvoll und migrationssicher.

15. Nach Abschluss bitte ausgeben:
   - welche Tabellen erstellt wurden
   - welche bestehenden Tabellen verwendet wurden
   - welche JTL-Tabellen gefunden wurden
   - welche Ninox-Tabellen/Felder gemappt wurden
   - welche Punkte noch manuell geprüft werden müssen
   - welche Tests erfolgreich waren
```

---

## Kritischer Umsetzungshinweis

Die automatische Bestellungserzeugung bei Angebotsannahme ist fachlich gewünscht, aber technisch riskant.

Saubere Regel:

```text
Wenn alle Pflichtdaten vorhanden:
→ Bestellung automatisch erzeugen.

Wenn Daten fehlen:
→ Angebot accepted lassen,
→ conversion_failed / needs_review setzen,
→ Aufgabe für Mitarbeiter erzeugen.
```

Typische Fehlerfälle:

- Kunde fehlt
- Artikel kann nicht gemappt werden
- Freitextposition nicht bestellfähig
- Preis fehlt
- Lieferdatum fehlt
- Festbedarf ist überbucht
- Pfandartikel fehlen
- Steuer-/Preislogik unklar

Keine halbfertige Bestellung erzeugen. Stille Fehler in Lieferung, Pfand oder Festbedarf sind teurer als ein sauberer Review-Status.
