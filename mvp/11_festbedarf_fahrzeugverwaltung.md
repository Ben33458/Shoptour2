# Shoptour2 MVP – Modul Festbedarf, Eventlogik und Fahrzeugverwaltung

Stand: 2026-03-31

## Ziel
Dieses MVP beschreibt die funktionale und technische Erweiterung von Shoptour2 um:

1. Festbedarf / Mietartikel im Event-Kontext
2. Event-spezifische Buchungs-, Rückgabe- und Abrechnungslogik
3. Vollgut-Rückgaben bei normalen Lieferungen und Eventbelieferungen
4. Fahrzeugverwaltung
5. Gemeinsame Mängel-/Defektlogik für Fahrzeuge und Festbedarf

Wichtig:
- Wawi- und Ninox-Tabellen dienen nur zum erstmaligen Befüllen bzw. zur Synchronisation.
- Operativ führend sind eigene Shoptour2-Tabellen.
- Festbedarf darf nur im Kontext eines Eventauftrags gebucht werden, nicht in normalen Standardbestellungen.
---

## 1. Fachliche Grundsätze

### 1.1 Festbedarf nur bei Eventaufträgen
Festbedarf darf nur dann buchbar sein, wenn ein Auftrag als Event / Veranstaltung angelegt ist.

### 1.2 Mietartikel werden pro Mietzeitraum berechnet
Es gibt keine Tagesabrechnung mehr. Alle bisherigen abweichenden Logiken sind auf „pro Mietzeitraum“ umzustellen.

### 1.3 Mietzeitmodelle sind admin-pflegbar
Mietzeitmodelle müssen im Admin-Bereich gepflegt werden können, z. B.:
- Wochenende
- Woche
- Werktage
- Verlängerung

Die Preislogik darf nicht fest im Artikel verdrahtet sein.

### 1.4 Wawi/Ninox nicht operativ führend
- Wawi-Artikel und Ninox-Daten werden importiert / synchronisiert
- operative Nutzung erfolgt ausschließlich über eigene Shoptour2-Tabellen
- Verfügbarkeit, Zustände, Schäden, Rückgaben und Preisregeln werden in Shoptour2 gepflegt

---

## 2. Festbedarf / Mietartikel

### 2.1 Unterstützte Mietartikel
Beispiele:
- Kühlanhänger / Kühlwagen
- Festzeltgarnituren
- Biertische
- Bierbänke
- Stehtische
- Sonnenschirme
- Kühlschränke
- Kühltruhen Getränke
- Tiefkühltruhen
- Zapfanlagen
- Zubehör für Zapfanlagen
- Spülen
- Gläser in VPE
- weitere Leihartikel aus dem Bestand

### 2.2 Erkennung aus Bestandssystemen
Aus Wawi-Artikeln können bestehende Leihartikel initial übernommen werden.
Hinweis:
- bisherige Kennzeichnung oft über „Leihweise“ im Artikelnamen
- Preisnotationen gibt es nur noch „pro Mietzeitraum“, nicht mehr „pro Tag“. wenn es noch im artikel so steht, muss es geändert werden
- Ziel ist ein sauberes, strukturiertes Mietartikelmodell in Shoptour2

---

## 3. Datenmodell Festbedarf

### 3.1 rental_items
Katalog der buchbaren Mietartikel.

Felder:
- id
- article_number / externe Referenz
- name
- slug
- description
- category_id
- active
- visible_in_shop
- requires_event_order (immer true für Festbedarf)
- billing_mode = per_rental_period
- inventory_mode = unit_based | quantity_based | component_based | packaging_based
- transport_class = small | normal | truck
- allow_overbooking (default false, admin übersteuerbar)
- damage_tariff_group_id (optional)
- cleaning_fee_rule_id (optional)
- deposit_rule_id (optional)
- preferred_time_model_id (optional)

### 3.2 rental_item_categories
Beispiele:
- Kühlung
- Mobiliar
- Ausschank
- Gläser
- Zubehör
- Hygiene / Spüle

### 3.3 rental_inventory_units
Für individuell identifizierbare Einheiten.

Beispiele:
- Kühlanhänger 1
- Kühlanhänger 2
- Zapfanlage 2L-01
- Kühlschrank 03

Felder:
- id
- rental_item_id
- inventory_number
- serial_number / Kennzeichen optional
- title
- status = available | reserved | in_use | maintenance | defective | retired
- condition_notes
- location
- preferred_for_booking
- sync_source
- sync_source_id

### 3.4 rental_packaging_units
Für VPE-pflichtige Mietartikel, insbesondere Gläser.

Felder:
- id
- rental_item_id
- label
- pieces_per_pack
- sort_order
- active

Beispiele:
- Weinglas 24er
- Weinglas 6er
- Bierglas 40er

### 3.5 rental_time_models
Admin-pflegbare Mietzeitmodelle.

Felder:
- id
- name
- description
- active
- sort_order
- rule_type
- min_duration_hours optional
- default_for_events
- metadata / rule config

Empfohlene Startmodelle:
- Wochenende
- Woche
- Werktage
- Verlängerung

### 3.6 rental_price_rules
Preisregeln je Mietartikel / Mietzeitmodell / Menge.

Felder:
- id
- rental_item_id
- rental_time_model_id
- packaging_unit_id optional
- min_quantity
- max_quantity optional
- price_type = per_item | per_pack | per_set | flat
- price_net
- valid_from optional
- valid_until optional
- customer_group_id optional
- requires_drink_order optional

### 3.7 rental_components
Für Set-/Bundle-Logik.

Beispiel:
1 Festzeltgarnitur = 1 Tisch + 2 Bänke

Felder:
- id
- parent_rental_item_id
- component_rental_item_id
- quantity

### 3.8 rental_booking_items
Mietpositionen innerhalb eines Eventauftrags.

Felder:
- id
- event_order_id
- rental_item_id
- packaging_unit_id optional
- rental_time_model_id
- quantity
- pieces_per_pack optional
- total_pieces optional
- unit_price_net
- total_price_net
- desired_specific_inventory_unit_id optional
- fixed_inventory_unit_id optional
- status

### 3.9 rental_booking_allocations
Zuordnung von Buchungspositionen auf konkrete Inventareinheiten.

Felder:
- id
- rental_booking_item_id
- rental_inventory_unit_id
- allocated_from
- allocated_until
- status

---

## 4. Verfügbarkeitslogik

### 4.1 Typen
Es gibt vier Verfügbarkeitslogiken:

#### a) unit_based
Für einzelne identifizierbare Geräte.
Beispiele:
- Kühlanhänger
- Zapfanlagen
- Kühlschränke
- Kühltruhen
- Tiefkühltruhen
- Spülen

Logik:
- konkrete Einheit darf im Zeitraum nur einmal reserviert sein
- Wunschgerät ist verbindlich buchbar, wenn verfügbar

#### b) quantity_based
Für Mengenbestand ohne Einzelidentität.
Beispiele:
- Stehtische
- Sonnenschirme
- einzelne Bänke
- einzelne Tische

Logik:
- verfügbar = Gesamtbestand minus Reservierungen minus Defekte / Sperren

#### c) packaging_based
Für Gläser / VPE-gebundene Mietartikel.

Logik:
- buchbar nur in definierten VPE
- keine freie Stückzahl
- Bruch reduziert dauerhaft den verfügbaren Bestand

#### d) component_based
Für Sets aus Komponenten.
Beispiel:
- 1 Garnitur = 1 Tisch + 2 Bänke

Logik:
- Setverfügbarkeit ergibt sich aus freien Komponenten

### 4.2 Überbuchung
Default:
- keine Überbuchung erlaubt

Ausnahme:
- pro Mietartikel kann Admin `allow_overbooking` aktivieren

### 4.3 Reservierungsstatus
Reservierungen blockieren sofort, auch vor finaler Prüfung.
Dafür werden getrennte Status benötigt:
- reserviert 
- ungeprüft
- bestätigt
- abgelehnt
- storniert
- abgelaufen

Empfehlung:
- ungeprüfte Reservierungen müssen automatisch ablaufen können, falls sie nicht bearbeitet werden

---

## 5. Gläser

### 5.1 Buchung nur in VPE
Gläser dürfen nur in den jeweils definierten Verpackungseinheiten gebucht werden.
Beispiele:
- 24er
- 40er
- 6er

Manche Glasarten können mehrere VPE haben, z. B. Weingläser.

### 5.2 Rückgabe und Bruch
Bei Rückgabe müssen erfasst werden:
- Anzahl zurückgegebener VPE
- ggf. Bruch / Fehlmenge
- Sauberkeit
- Schäden

Wichtig:
- Bruch reduziert den permanenten verfügbaren Bestand
- Nachkauf erhöht ihn später wieder

### 5.3 Abrechnung
Bruch und Reinigung müssen separat nachberechnet werden können.

---

## 6. Eventorte / Veranstaltungsadressen

### 6.1 Event-Ort Auswahl
Im Eventauftrag muss der Ort strukturiert ausgewählt werden können aus:
- den gespeicherten Lieferadressen des Kunden
- öffentliche Eventadresse aus internem Verzeichnis
- freie neue Eventadresse

### 6.2 event_locations
Pflegbare Eventadressen, optional per externer API aktualisierbar.

Felder:
- id
- name
- street
- zip
- city
- country
- geo_lat optional
- geo_lng optional
- notes
- active
- source_type
- source_id

### 6.3 Zusätzliche Event-Ort-Felder
Im Auftrag / Event:
- Ansprechpartner vor Ort
- Telefonnummer vor Ort
- Zufahrtshinweise
- Aufbauhinweise
- Strom vorhanden
- Stellfläche geeignet
- weitere interne Hinweise

---

## 7. Lieferung, Abholung und Zeitfenster

### 7.1 Wunsch-Zeitfenster
Für Lieferung und Abholung:
- Kunde gibt Wunsch-Zeitfenster an
- Mindestbreite 2 Stunden
- Admin kann das final anpassen / bestätigen

Felder je Auftrag:
- desired_delivery_date
- desired_delivery_time_from
- desired_delivery_time_to
- desired_pickup_date
- desired_pickup_time_from
- desired_pickup_time_to
- confirmed_delivery_time_from optional
- confirmed_delivery_time_to optional
- confirmed_pickup_time_from optional
- confirmed_pickup_time_to optional

### 7.2 Selbstabholung / Selbstrückgabe
Möglichkeiten:
- Lieferung durch euch
- Selbstabholung
- Abholung durch euch
- Selbstrückgabe

Regeln:
- Selbstabholung = keine Lieferpauschale
- Selbstrückgabe = keine Abholpauschale

### 7.3 Anfahrtspauschalen
Die Anfahrt wird nicht über Artikelpreise der Mietartikel abgedeckt, sondern separat.

Automatische Logistikklassen:
- small = 10 €
- normal = 20 €
- truck = 30 €

Regel:
- pro Strecke
- Lieferung und Abholung getrennt berechenbar

### 7.4 Logistikklasse
Jeder Mietartikel trägt eine Transportklasse:
- small
- normal
- truck

Automatische Auftragsermittlung:
- enthält mindestens ein truck-Artikel => truck
- sonst enthält mindestens ein normal-Artikel => normal
- sonst small

Optional:
- mengenabhängige Hochstufung (z. B. viele Stehtische oder viele Garnituren)

### 7.5 Liefergebiet und Entfernungsaufschlag
Lager-Referenzpunkt:  hier kann man ein Lager im Adminmenu auswählen. das existierende  Lager Industriestr.13  64280 Roßdorf als standard wählen

Regel:
- Pauschale gilt nur im Liefergebiet bis 8 km
- außerhalb 8 km: zusätzlicher Entfernungsaufschlag

Der Aufschlag wird über Artikel 59200 berechnet:
- Artikelnummer 59200
- Name: Entfernungsaufschlag (pro km)

Berechnung:
- Distanz Lager -> Eventadresse
- einfacher Weg
- bis einschließlich 8 km kein km-Aufschlag
- über 8 km: Zusatz-km = Distanz - 8
- Aufschlag = Zusatz-km * Menge auf Artikel 59200

### 7.6 Zuschläge
Zusätzlich mögliche Zuschläge:
- Wochenende
- außerhalb Lieferzeiten
- Feiertag

Diese müssen als separate Service-/Kostenpositionen abbildbar sein.
sind jeweils als Artikel angelegt in der Warenwirtschaft.

---

## 8. Aufbau / Anschluss / Personal

### 8.1 Optionale Serviceleistung
Kunden können kostenpflichtig wählen:
- Anschließen durch uns
- Aufbauen durch uns
- Abbauen durch uns
- Einweisung / Inbetriebnahme

### 8.2 Abrechnung
Abrechnung erfolgt pro 15 Minuten.

Empfehlung:
- eigene Servicepositionen im Auftrag
- geplante Minuten und tatsächliche Minuten speicherbar
- Anzahl Mitarbeiter optional
- manuelle Korrektur durch Admin möglich

---

## 9. Vorkasse / Zahlungslogik für Events

### 9.1 Vorkasse
Für Eventkunden gilt standardmäßig:
- 50 % Vorkasse auf:
  - Getränke
  - Miete
  - Service
  - Liefer-/Abholkosten

Nicht Teil der Vorkasse:
- Pfand
Sie können aber auch auswählen, direkt 100% zu zahlen und 100% inkl.pfand.

### 9.2 Pfand
Pfand wird separat behandelt und ist nicht Teil der Vorkasse.

### 9.3 Zahlungsfrist
Die erforderliche Vorkasse muss spätestens 7 Tage vor Event eingegangen sein.

### 9.4 Freigabelogik
Reservierung kann vorher blockieren, aber:
- finale Freigabe / Bearbeitung kann von Zahlung abhängig gemacht werden
- Admin muss manuell übersteuern können

---

## 10. Kautionen

### 10.1 Grundsatz
Kautionen sind aktuell nicht Standard, sollen aber im System unterstützt werden.

### 10.2 deposit_rules
Unterstützte Modelle:
- keine Kaution
- feste Kaution je Artikel
- Kaution nur für Privatkunden
- Kaution nur ab bestimmter Risikoklasse
- manuell im Auftrag übersteuerbar

### 10.3 Typische Zielartikel
Insbesondere geeignet für:
- Privatkunden
- Kühlanhänger
- Zapfanlagen
- hochwertige / knappe Geräte

---

## 11. Reinigung

### 11.1 Reinigung nur bei Bedarf
Reinigung wird nicht pauschal immer berechnet.

Wenn Artikel verschmutzt zurückkommen:
- Reinigung kann nachberechnet werden

### 11.2 cleaning_fee_rules
Mögliche Regeln:
- fester Betrag je Artikel
- fester Betrag je Kategorie
- pro VPE / Set / Einheit
- manuell anpassbar

---

## 12. Rückgabeschein für Leihartikel

### 12.1 Pflicht
Für alle Leihartikel muss es einen Rückgabeschein geben.

### 12.2 Nutzung im Fahrer-Tool
Der Fahrer muss buchen können:
- zurückgegeben ja/nein
- vollständig ja/nein
- sauber ja/nein
- defekt ja/nein
- Schadenstyp
- Notiz
- Foto optional

### 12.3 rental_return_slips
Felder:
- id
- event_order_id
- driver_user_id
- returned_at
- location
- status = open | partial | complete | reviewed | charged
- notes

### 12.4 rental_return_slip_items
Felder:
- id
- rental_return_slip_id
- rental_booking_item_id
- returned_quantity
- clean_status = clean | dirty
- damage_status = none | damaged | not_rentable | damaged_but_still_rentable
- damage_tariff_id optional
- suggested_extra_charge
- manual_extra_charge optional
- notes
- photo_path optional

### 12.5 Schadenstypen
Unterstützte Zustände:
- defekt
- nicht verleihbar
- defekt (Kleinigkeit), weiterhin verleihbar

Zusätzlich:
- Nachbelastungsvorschlag
- manuelle Korrektur
- Bestandswirkung / Sperrung je nach Status

---

## 13. Schadenstarife

### 13.1 damage_tariffs
Felder:
- id
- applies_to_type = rental_item | category | packaging_unit
- applies_to_id
- name
- amount_net
- active
- notes

### 13.2 Anpassbarkeit
Feste Schadenstarife sind Standard.
Admin und Fahrer dürfen den vorgeschlagenen Betrag manuell anpassen, sofern berechtigt.

---

## 14. Vollgut-Rückgaben und Pfandrückgaben

### 14.1 Geltungsbereich
Gilt für:
- normale Lieferungen
- Eventbelieferungen

Der Fahrer muss im Fahrer-Tool erfassen können:
- Pfandrückgaben
- volle ungeöffnete Kästen zurück
- volle Fässer zurück

### 14.2 Vollgut-Rückgabe Kästen
Regeln:
- nur volle, ungeöffnete, wieder einlagerbare Ware
- MHD ist Pflichtfeld
- Rückgabe wird über negative Mengen auf den ursprünglichen Artikel erfasst
- zusätzlich pro zurückgegebenem Kasten wird Artikel 58610 berechnet

Artikel:
- 58610 = Volle Kasten-Rückgabe

### 14.3 Vollgut-Rückgabe Fässer
Regeln:
- Fässer können nur voll zurückgegeben werden
- angebrochenes Fass zählt nicht als Vollgut-Rückgabe
- angebrochen = nur Leergut/Pfand
- Rückgabe über negative Mengen auf Originalartikel
- zusätzlich pro Fass wird Artikel 58611 berechnet

Artikel:
- 58611 = Volle Fass-Rückgabe

### 14.4 Pfandrückgaben
Pfand- / Leergutrückgabe muss wie bisher im Fahrer-Tool erfasst werden, aber mit derselben sauberen Rücknahme-Logik.

### 14.5 delivery_returns
Eigene Rücknahmetabellen für Nachvollziehbarkeit.

#### delivery_returns
- id
- order_id
- customer_id
- driver_user_id
- returned_at
- return_type = deposit | full_goods
- notes

#### delivery_return_items
- id
- delivery_return_id
- article_id
- quantity
- packaging_id optional
- return_reason
- best_before_date optional
- is_restockable (bei Vollgut immer true)
- generated_fee_article_id optional
- generated_fee_quantity optional
- notes

### 14.6 Systemlogik Vollgut
Beispiel Kästen:
- Originalartikel Menge -4
- Artikel 58610 Menge +4

Beispiel Fässer:
- Originalartikel Menge -2
- Artikel 58611 Menge +2

### 14.7 Vollgut ist immer wieder einlagerbar
Wenn Ware nicht wieder einlagerbar ist, ist sie keine Vollgut-Rückgabe.
Daher:
- Vollgut-Rückgabe nur bei wieder einlagerbarer Ware
- separate Zustandsstufen hierfür nicht nötig
- MHD bleibt Pflicht

---

## 15. Fahrzeugverwaltung

### 15.1 Ziel
Eigene Verwaltung für betriebliche Fahrzeuge.

### 15.2 vehicles
Felder:
- id
- internal_name
- plate_number
- manufacturer
- model
- vehicle_type
- vin optional
- first_registration optional
- year optional
- active
- location
- notes

### 15.3 Technische und operative Daten
- gross_vehicle_weight optional
- empty_weight optional
- payload_weight
- load_volume optional
- max_vpe_without_hand_truck
- max_vpe_with_hand_truck
- load_length optional
- load_width optional
- load_height optional
- seats optional
- trailer_hitch
- max_trailer_load optional
- cooling_unit optional
- required_license_class optional

### 15.4 Dokumente
- Fahrzeugschein
- Prüfberichte
- Versicherungsnachweise
- weitere Anhänge

### 15.5 Fristen
- tüv_due_date
- inspection_due_date optional
- oil_service_due_date optional
- next_service_km optional
- current_mileage

### 15.6 Einsätze / Historie
Optional bzw. vorbereiten:
- wer fährt welches Fahrzeug
- Tourbezug
- km Start / Ende
- Verbrauch pro 100 km (im fahrzeug ablesbar)
- Schäden nach Einsatz
- Kostenhistorie

---

## 16. Gemeinsames Mängel- / Asset-Modul

### 16.1 Ziel
Festbedarf und Fahrzeuge sollen eine gemeinsame Mängelverwaltung nutzen.

### 16.2 assets
Abstrakte technische Ressourcen.
Typen:
- vehicle
- rental_inventory_unit
- ggf. später weitere

### 16.3 asset_issues
Felder:
- id
- asset_type
- asset_id
- title
- description
- priority
- status = open | scheduled | in_progress | resolved | closed
- severity
- blocks_usage
- blocks_rental
- estimated_cost optional
- workshop_name optional
- due_date optional
- created_by
- assigned_to optional

### 16.4 Typische Anwendungsfälle
- Kühlanhänger defekt
- Kühlschrank nicht verleihbar
- Fahrzeug Reparatur fällig
- TÜV-bezogene Mängel
- kleinere Schäden ohne Nutzungssperre

---

## 17. Fahrer-Tool Erweiterungen

### 17.1 Fahrer muss bei normalen Lieferungen und Events erfassen können
- Pfandrückgabe
- Vollgut-Rückgabe Kästen
- Vollgut-Rückgabe Fässer
- Leihartikel-Rückgabe
- Sauberkeit
- Defekte / Schäden
- Fotos optional

### 17.2 Für Event-Leihartikel
- Rückgabeschein erzeugen
- Positionen abhaken
- Mengen prüfen
- Sauberkeit prüfen
- Schadenstatus setzen
- Nachberechnung vorschlagen

### 17.3 Für Vollgut
- negative Mengen buchen
- MHD erfassen
- Zusatzartikel 58610 / 58611 automatisch hinzufügen

---

## 18. Admin-Oberflächen

### 18.1 Benötigte Bereiche
- Mietartikel
- Mietzeitmodelle
- Preisregeln
- Verpackungseinheiten / VPE
- Inventareinheiten
- Eventorte
- Kautionsregeln
- Reinigungskostenregeln
- Schadenstarife
- Rückgabescheine
- Vollgut-Rückgaben
- Fahrzeuge
- Asset-Mängel

### 18.2 Kalender / Übersicht
Mindestens sinnvoll für:
- Kühlanhänger
- Zapfanlagen
- knappe Geräte
- größere Eventaufträge

---

## 19. MVP-Umfang

### 19.1 Muss im MVP enthalten sein
- Festbedarf nur im Eventauftrag
- Mietartikel-Katalog
- Mietzeitmodelle
- Preisregeln
- Logistikklassen
- Anfahrtspauschalen und km-Aufschlag über Artikel 59200
- Selbstabholung / Selbstrückgabe
- Rückgabeschein
- Schadenstarife
- Reinigung bei Bedarf
- VPE-Logik für Gläser
- Komponentenlogik für Garnitur
- konkrete Inventareinheiten für Kühlanhänger / Geräte
- Vollgut-Rückgaben im Fahrer-Tool
- Artikel 58610 / 58611 Logik
- Fahrzeugverwaltung
- gemeinsames Asset-/Mängelmodul

### 19.2 Kann nach MVP erweitert werden
- komplexere Preisstaffeln
- automatische Ablaufregeln für Reservierungen
- weitergehende Kalenderansichten
- Fotos / Unterschriften / Prüfprotokolle
- erweiterte Fahrzeugeinsatzplanung
- externe API-Synchronisation für öffentliche Eventorte

---

## 20. Wichtige harte Regeln

1. Festbedarf nur bei Eventauftrag
2. Mietartikel nur pro Mietzeitraum
3. Wawi/Ninox nur Import und Sync, nicht operativ führend
4. Reservierungen blockieren sofort
5. Standard: keine Überbuchung
6. Wunsch-Kühlwagen verbindlich buchbar, wenn verfügbar
7. Gläser nur in VPE
8. Rückgabeschein für alle Leihartikel Pflicht
9. Schadenstatus:
   - defekt
   - nicht verleihbar
   - defekt (Kleinigkeit), weiterhin verleihbar
10. Reinigung nur bei Bedarf nachberechnen
11. 50 % Vorkasse für Eventaufträge, ohne Pfand
12. Zahlungsfrist 7 Tage vor Event
13. Liefer-/Abhol-Wunschzeitfenster mind. 2 Stunden
14. Selbstabholung und Selbstrückgabe ohne Fahrtpauschale
15. Automatische Logistikklasse small / normal / truck
16. Anfahrtspauschale je Strecke: 10 / 20 / 30 €
17. Liefergebiet 8 km um Lager Roßdorf
18. darüber Entfernungsaufschlag via Artikel 59200 mit 0,50 € pro km, einfacher Weg
19. Vollgut-Rückgaben bei Kästen und Fässern im Fahrer-Tool erfassbar
20. Kästen: Zusatzartikel 58610 pro zurückgegebenem Kasten
21. Fässer: Zusatzartikel 58611 pro zurückgegebenem Fass
22. Vollgut nur bei wieder einlagerbarer Ware
23. Fässer nur voll rückgabefähig, angebrochen = Leergut/Pfand

