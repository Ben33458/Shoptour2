# MVP – Shoptour2 Modul Bestandsaufnahme

## Ziel
Es soll ein eigenständiges Modul **Bestandsaufnahme** in Shoptour2 entstehen, mit dem Bestände je Lager schnell erfasst, sofort verbucht, vollständig protokolliert und für Bestellung, MHD-Beobachtung und Ladenhüter-Steuerung ausgewertet werden können.

Das Modul soll die bestehende Praxis aus der ODS-Datei **bestellzettel_Mindestbestand_Lieferanten_GUT_HMI_VENTENusw.ods** fachlich übernehmen, aber technisch sauber in Datenmodell, UI, Import und Historie überführen.

---

## Fachliche Grundregeln

### 1. Basisartikel und Basiseinheit
- Jedes Produkt hat einen **Basisartikel** mit **Kolabri ArtNr.**
- Bestände werden in der Datenbank **immer in Basiseinheit** gespeichert.
- Mindestbestände aus der bisherigen Excel/ODS-Logik liegen oft in **VPE** vor und müssen beim Import in **Basiseinheit** umgerechnet werden.
- Für Bestandsaufnahme und Bestellvorschläge darf das System VPE-Werte anzeigen und erfassen, intern wird aber auf Basiseinheit normalisiert.

### 2. Verpackungseinheiten / Gebinde
Ein Basisartikel kann mehrere Verpackungseinheiten haben, z. B.:
- 24x0,33
- 12x0,33
- 6x1,0
- Einzelflasche 0,33

Regeln:
- Jede Verpackungseinheit hat einen **Umrechnungsfaktor zur Basiseinheit**
- Mehrere Eingabefelder pro Artikel sind erlaubt und gewünscht
- Beispiel: Bionade Holunder 0,33 → Eingabefelder für 24er, 12er und Einzelflaschen
- Speicherung immer als Summe in Basiseinheit

### 3. Lieferantenlogik
- Ein Basisartikel kann bei mehreren Lieferanten bestellbar sein
- Pro Basisartikel gibt es genau **einen Standard-Lieferanten**
- Es können je Lieferant mehrere bestellbare VPEs existieren
- Lieferanten-spezifische Bestellnummern, Bestellmengenlogiken und Hinweise müssen gepflegt werden können
- Standardansicht der Bestandsaufnahme startet **nach Lieferant**

### 4. Mindestbestände
- Mindestbestände sind **lagerbezogen**
- Ursprung aus ODS ist teilweise marktbezogen und in VPE
- Speicherung in DB erfolgt **lagerbezogen in Basiseinheit**
- Anzeige für Nutzer darf in VPE erfolgen

### 5. Lager
- Die Lager sind bereits vorhanden und werden verwendet
- Bestandsaufnahme muss je Lager einzeln möglich sein
- Parallelzählung mehrerer Nutzer ist zulässig
- Laufende Sessions dürfen pausiert und später fortgesetzt werden
- Pro Produkt/Lager soll sichtbar sein, wann es zuletzt gezählt wurde

### 6. Sofortbuchung
- Bestandskorrekturen werden **sofort verbucht**
- Negativbestände sind erlaubt
- Jede Änderung muss vollständig dokumentiert werden

### 7. MHD-Logik
MHD-Regeln werden mit folgender Priorität ausgewertet:

1. Artikel
2. Lager
3. Kategorie
4. Warengruppe
5. Default

MHD-Modi:
- nie
- optional
- pflichtig

Zusätzlich:
- Mehrere Chargen/MHDs pro Artikel/Lager sind möglich
- Abverkaufslogik grundsätzlich **FEFO** (first expire, first out)
- Das älteste MHD soll im Normalfall zuerst abverkauft werden

### 8. Ladenhüter
Im UI und Dashboard als **Ladenhüter** bezeichnen.

Initiale Standardlogik, adminseitig konfigurierbar:
Ein Artikel gilt als Ladenhüter, wenn mindestens eine Bedingung erfüllt ist:
- kein Verkauf seit 90 Tagen
- Lagerdauer > 180 Tage
- Bestandsreichweite > 180 Tage auf Basis Durchschnittsabverkauf

Diese Werte müssen im Adminbereich konfigurierbar sein.

### 9. Korrekturgründe
Bei manuellen Bestandsänderungen müssen feste Gründe auswählbar sein:
- Zählfehler
- Bruch
- Schwund
- MHD-Abschreibung
- Umlagerung
- Wareneingangsabweichung
- Bestandsbereinigung
- Sonstiges

Freitext-Kommentar zusätzlich optional möglich.

### 10. Ladenhüter-Aktionen
Mögliche Aktionen:
- beobachten
- Nachbestellung blocken
- Abverkauf fördern
- Preisaktion prüfen
- manuell ignorieren

---

## Ziel-UX der Bestandsaufnahme

## A. Einstieg
Neue Bestandsaufnahme:
- Lager wählen
- Standardfilter setzen
- Session starten oder offene Session fortsetzen

## B. Standard-Sortierung / Filter
Default-Sortierung:
- nach Lieferant

Sinnvolle Filter:
- nur aktive Artikel
- nur sortimentsrelevante Artikel
- nur Artikel mit Fehlbestand
- nur negative Bestände
- nur MHD-pflichtige Artikel
- nur Artikel mit knappem MHD
- nur Ladenhüter
- nur heute korrigierte Artikel
- nur Artikel mit Konflikten aus ODS-Import
- Suche nach Name / Kolabri ArtNr. / Lieferanten-ArtNr.

## C. Eingabemaske
Je Artikel anzeigen:
- Kolabri ArtNr.
- Produktname
- Lager
- Standard-Lieferant
- alternative Lieferanten
- aktueller Soll-/Ist-Kontext
- Mindestbestand
- letzte Zählung
- MHD-Hinweis
- Eingabefelder je vorhandener Verpackungseinheit

Beispiel:
- 24x0,33
- 12x0,33
- 0,33 einzeln

Verhalten:
- Eingaben werden live in Basiseinheit umgerechnet
- Ergebnisbestand sichtbar
- sofort speicherbar
- MHD-Felder nur anzeigen oder erzwingen, wenn laut Regel nötig

## D. Chargen/MHD-Erfassung
Falls für einen Artikel MHD relevant ist:
- mehrere Chargen pro Artikel/Lager erfassbar
- pro Charge mindestens:
  - MHD
  - Menge in Basiseinheit
  - optional Herkunft / Notiz

---

## Dashboard / Beobachtung
Im Dashboard und in Bestands-Widgets anzeigen:
- Fehlbestände
- negative Bestände
- knappes MHD
- kritisches MHD
- Ladenhüter
- auffällige Korrekturen
- Artikel ohne klare Lieferantenzuordnung
- Artikel mit ODS/DB-Konflikten
- Artikel mit mehreren Gebindevarianten und kritischem Bestand

Initiale MHD-Schwellenwerte:
- Warnung bei <= 30 Tagen
- kritisch bei <= 14 Tagen

Adminseitig konfigurierbar.

---

## Datenmodell – Mindestumfang

### 1. Artikel / vorhandene Tabellen erweitern
Vorhandene Artikelstruktur nutzen und nur erweitern, wenn nötig:
- Basisartikel über Kolabri ArtNr.
- Warengruppe
- Kategorie
- Standard-Lieferant
- Ladenhüter-Ausnahmen / Flags optional

### 2. artikel_verpackungseinheiten
Für alle zähl- und bestellrelevanten Einheiten eines Basisartikels:
- id
- artikel_id
- bezeichnung
- faktor_basiseinheit
- ist_bestellbar
- ist_zaehlbar
- aktiv
- sortierung

### 3. artikel_lieferanten
Lieferantenfähigkeit je Basisartikel bzw. Verpackungseinheit:
- id
- artikel_id
- verpackungseinheit_id nullable
- lieferant_id
- lieferanten_artnr
- ist_standard_lieferant
- mindestbestellmenge
- bestellhinweis
- aktiv

### 4. artikel_mindestbestaende
- id
- artikel_id
- lager_id
- mindestbestand_basiseinheit
- quelle (manuell/import)
- quelle_datei / quelle_tabellenblatt optional
- konflikt_flag
- konflikt_details
- updated_by
- updated_at

### 5. bestandsaufnahme_sessions
- id
- lager_id
- titel optional
- status (offen, pausiert, abgeschlossen)
- gestartet_von
- gestartet_am
- abgeschlossen_am nullable
- notiz

### 6. bestandsaufnahme_positionen
- id
- session_id
- artikel_id
- lager_id
- letzter_gespeicherter_bestand_basiseinheit
- gezählter_bestand_basiseinheit
- differenz_basiseinheit
- mhd_erforderlich_modus
- gezählt_von
- gezählt_am
- korrekturgrund_id nullable
- kommentar nullable

### 7. bestandsaufnahme_position_eingaben
Detailzeilen je Eingabefeld / Verpackungseinheit:
- id
- bestandsaufnahme_position_id
- verpackungseinheit_id
- menge_vpe
- faktor_basiseinheit
- menge_basiseinheit

### 8. lager_charge_bestaende
Chargen-/MHD-Bestände:
- id
- lager_id
- artikel_id
- mhd
- menge_basiseinheit
- referenz_typ optional
- referenz_id optional
- notiz

### 9. bestandsbewegungen
Vollständiges Journal:
- id
- artikel_id
- lager_id
- bewegungstyp
- grund_code
- vorher_bestand_basiseinheit
- delta_basiseinheit
- nachher_bestand_basiseinheit
- session_id nullable
- benutzer_id
- kommentar
- created_at

### 10. mhd_regeln
- id
- bezug_typ (artikel, lager, kategorie, warengruppe, default)
- bezug_id nullable
- modus (nie, optional, pflichtig)
- prioritaet

### 11. ladenhueter_regeln
Admin-konfigurierbare Werte:
- tage_ohne_verkauf
- max_lagerdauer_tage
- max_bestandsreichweite_tage
- aktiv

### 12. ladenhueter_aktionen / status
Optional als eigene Tabelle oder als strukturierte Flags:
- artikel_id
- lager_id nullable
- status
- aktion
- notiz
- gesetzt_von
- gesetzt_am

---

## Geschäftslogik

### 1. Umrechnung
- Alle Eingaben je Verpackungseinheit in Basiseinheit umrechnen
- Summenbestand als gezählter Bestand speichern
- Differenz zum bisherigen Lagerbestand berechnen
- Differenz sofort buchen
- Journal sofort schreiben

### 2. Parallelzählung
- Mehrere Nutzer dürfen parallel zählen
- Pro Artikel/Lager zählt der zuletzt gespeicherte Stand
- Konflikte nicht blockierend, aber nachvollziehbar dokumentieren
- Anzeige „zuletzt gezählt von / am“

### 3. Konfliktverhalten
- Keine Sperrlogik für MVP
- Letzte Buchung gewinnt operativ
- Historie muss so vollständig sein, dass spätere Prüfung möglich ist

### 4. FEFO
- Bei MHD-Artikeln älteste Charge zuerst verwenden
- Für MVP reicht Erfassung und Sichtbarkeit; harte Auslagerungsautomatik ist nicht zwingend

---

## Import aus ODS-Datei

## Ausgangsdatei
Datei: `bestellzettel_Mindestbestand_Lieferanten_GUT_HMI_VENTENusw.ods`

Beobachtete Tabellenblätter u. a.:
- Fritz
- Winkels-GUT-Trinks
- TSI
- Venten
- Eders
- Pfungstädter
- Hassia
- Darmstädter
- Wostok
- Doelp
- Denecke
- magicdrinks
- HBI_Neu
- HBI_alt
- Maruhn
- Heil
- Faust
- Schmucker
- Herrnbräu
- Krämer
- piranja
- Metro
- Kelterei Krämer
- GabiGräf
- Mio
- Tabelle28
- Außenlager_Roßdorf
- Selgros

Die Tabellen sind **nicht einheitlich aufgebaut**. Deshalb kein Blindimport.

## Importregeln
- Rohimport pro Tabellenblatt
- Mapping pro Tabellenblatt / Lieferant
- DB ist führend
- Bei Konflikt zwischen ODS und DB:
  - DB-Wert behalten
  - ODS-Wert protokollieren
  - Datensatz als prüfbedürftig markieren

### Zu importierende Informationen, soweit sauber erkennbar
- Kolabri ArtNr.
- Lieferanten-ArtNr.
- Produktname
- Lieferant
- Mindestbestand
- Gebinde / VPE
- besondere Bestellhinweise
- MHD-Hinweise, falls vorhanden

### Nicht blind überschreiben
- vorhandene Artikelstammdaten
- vorhandene Zuordnungen
- bestehende Mindestbestände
- Standard-Lieferant ohne Plausibilitätsprüfung

---

## Adminbereich

Es braucht einen Adminbereich für:
- Ladenhüter-Regeln
- MHD-Regeln
- feste Korrekturgründe
- Importübersicht
- Konfliktprüfung ODS vs. DB
- Verpackungseinheiten je Artikel
- Lieferanten-Zuordnung je Artikel
- Mindestbestände je Lager
- Dashboard-Kacheln / Beobachtungslisten

---

## Nicht-Ziele des MVP
Diese Punkte nicht unnötig groß ziehen:
- automatische optimale Bestellmengenlogik für alle Sonderfälle
- harte Reservierungs-/Sperrlogik bei Parallelzählung
- vollständige FEFO-Kommissionierungsautomatik
- automatische Preisaktionen für Ladenhüter
- perfekte OCR oder vollautomatisches Interpretieren chaotischer Tabellenblätter

---

## Akzeptanzkriterien MVP
Das MVP ist fertig, wenn mindestens Folgendes funktioniert:

1. Bestandsaufnahme pro Lager starten, pausieren und fortsetzen
2. Artikel nach Lieferant filtern und sortieren
3. Mehrere Eingabefelder je Artikel/Verpackungseinheit
4. Speicherung in Basiseinheit
5. Sofortige Bestandsbuchung
6. Vollständige Journalisierung
7. MHD-Regeln nach Priorität Artikel > Lager > Kategorie > Warengruppe > Default
8. Mehrere Chargen/MHDs erfassbar
9. Mindestbestände je Lager in Basiseinheit speicherbar
10. ODS-Rohimport mit Mapping je Tabellenblatt
11. Konflikte ODS vs. DB sichtbar markieren
12. Dashboard-Listen für Fehlbestand, negatives Lager, knappes MHD, Ladenhüter, auffällige Korrekturen
13. Feste auswählbare Korrekturgründe
14. Ladenhüter-Regeln im Admin einstellbar
15. Aktionen für Ladenhüter speicherbar

---

## Umsetzungsstil
- vorhandene Projektstruktur respektieren
- nur gezielt erweitern, nichts wild duplizieren
- DB-Migrationen sauber und nachvollziehbar
- bestehende Lager-, Artikel- und Lieferantenstrukturen wiederverwenden
- jede relevante Änderung im Change-Tracker dokumentieren
