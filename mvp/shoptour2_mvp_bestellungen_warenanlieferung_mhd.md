# MVP – Bestellungen, Warenanlieferung und MHD-Management

## 1. Ziel

In Shoptour2 soll ein MVP-Modul für **Bestellungen**, **Warenanlieferung**, **Wareneingang**, **Dokumente**, **Leergutrücknahme** und **MHD-Management** umgesetzt werden.

Das Modul soll:
- Lieferantenbestellungen vorbereiten, berechnen und dokumentieren
- unterschiedliche Lieferantenanforderungen unterstützen
- Wareneingänge praxisnah und schnell erfassen
- Dokumente aus verschiedenen Quellen zentral verarbeiten
- MHD sauber auf Bestands- bzw. Chargenebene führen
- historische Kolabri-Ninox-Daten getrennt importieren und nutzbar machen

---

## 2. Datenquellen und Grundregeln

### 2.1 Bereits importierte Ninox-Daten
Alle Tabellen mit Präfix `ninox_` sind bereits importierte Tabellen aus **Kehr-Ninox**.

Diese Daten sind **aktueller** als die noch zu importierenden Kolabri-Daten.

### 2.2 Noch zu importierende Ninox-Daten
Die Tabellen aus **Kolabri-Ninox** müssen noch importiert werden.

Diese sollen in der Datenbank mit Präfix `ninoxalt_` gespeichert werden.

### 2.3 Wichtige Regel zur Datenqualität
`ninoxalt_` ist die **ältere Quelle**.  
Die bereits übernommenen Daten in `ninox_` bzw. in den operativen Shoptour2-Tabellen sind grundsätzlich als aktueller zu behandeln.

Das bedeutet:
- `ninoxalt_` dient als Import-/Referenz-/Lernquelle
- `ninoxalt_` darf nicht blind über aktuelle Daten geschrieben werden
- bei Konflikten ist die zeitlich neuere Quelle zu bevorzugen
- Abgleich und Mapping müssen nachvollziehbar dokumentiert werden

### 2.4 Operative Tabellen
`ninox_` und `ninoxalt_` sind **Import-/Quelltabellen**.  
Die operative Arbeit soll in Shoptour2-eigenen Tabellen erfolgen.

---

## 3. Kleinste Gebindeeinheit

Die kleinste Gebindeeinheit ist in der Regel die **Flasche**.

Bestände, Mindestbestände, Bedarfe und MHD-nahe Logik sollen intern möglichst auf dieser kleinsten Einheit aufbauen.  
VPE, Kisten, Trays, Lagen und Paletten werden daraus abgeleitet.

---

## 4. Mindestbestände

Pro Produkt sollen mindestens geführt werden:
- Mindestbestand Markt
- Mindestbestand Lager
- Mindestbestand Gesamt

Zusätzlich relevant:
- aktueller Bestand Markt
- aktueller Bestand Lager
- aktueller Gesamtbestand
- offene Bestellungen
- reservierte Mengen für Veranstaltungen
- erwarteter Bedarf aus Kundensortimenten / Stammsortimenten

---

## 5. Lieferanten und Bestelllogik

## 5.1 Allgemein
Jeder Artikel kann bei einem oder mehreren Lieferanten bestellt werden.

Pro Lieferant-Artikel-Zuordnung sollen mindestens pflegbar sein:
- Lieferantenartikelnummer
- Lieferantenbezeichnung
- interne Produktzuordnung
- Einheit / VPE
- Gebindefaktor
- Palettenfaktor
- Standard-EK
- Aktiv/Inaktiv

## 5.2 Lieferanten-Mindestanforderungen

Mögliche Mindestanforderungen:
- kein Mindestwert
- Mindestmenge in VPE
- Mindestmenge in Palette
- Vollpalette erforderlich
- exakte Palettenanzahl
- Mindestwert in Netto-EK
- Mindestwert in Netto-EK je Produktgruppe
- Staffel-Zu-/Abschläge
- kombinierte Regeln

Hinweis:
„Kisten“ und „VPE“ sollen nicht doppelt als unterschiedliche Regeltypen geführt werden, wenn Kisten in dem Fall bereits VPE sind.

## 5.3 Lieferantenrhythmus
Lieferanten können haben:
- feste Liefertage
- feste Bestelltage / Bestellschlusszeiten
- wöchentliche Belieferung
- 14-tägige Belieferung
- bedarfsabhängige Belieferung

Diese Informationen sind soweit möglich aus `ninox_lieferanten` auszulesen und in die Lieferantenlogik zu übernehmen.

Beispiel:
- KK: Anlieferung Dienstag, Bestellung bis Montag 07:30 Uhr

---

## 6. Bestellprofile und Lieferanten-Templates

Es braucht unterschiedliche Bestellprofile je Lieferant.

Ein Lieferant kann Bestellungen erwarten über:
- eigenes Portal
- Webshop
- E-Mail mit PDF
- E-Mail mit CSV
- E-Mail mit XML
- manuell hochladbare Datei
- Fallback-Freitext / manuelle Bestellung

Pro Lieferant soll konfigurierbar sein:
- Standard-Bestellkanal
- alternative Kanäle
- Pflichtfelder
- Dateiformat
- Feldreihenfolge
- Betreff-/Textvorlage
- Empfänger
- Lieferantenartikelnummernutzung
- kundenspezifische Lieferantennummer
- Besonderheiten für Mengen- und Preisangaben

Ziel:
Die Bestellung soll so erzeugt werden, dass der Lieferant sie möglichst direkt in seinem System weiterverarbeiten kann.

---

## 7. Lernbare Dokument- und Template-Erkennung

Es soll möglich sein, eine **Beispiel-CSV**, **Beispiel-PDF** oder ein anderes Beispieldokument hochzuladen und daraus festzulegen:
- welches Feld welche Bedeutung hat
- welche Inhalte wie erkannt werden
- wie spätere automatische Zuordnung oder Verarbeitung erfolgen soll

Dies soll nicht hart im Code pro Lieferant verdrahtet werden, sondern als konfigurierbare Mapping-/Parser-Definition.

---

## 8. Dokumentenverwaltung

Es soll nach Möglichkeit **nur eine zentrale Tabelle für sämtliche Dokumente** geben.

Darin sollen unter anderem gespeichert werden können:
- Lieferscheine
- Rechnungen
- Bestell-PDFs
- Fotos von handschriftlichen Lieferscheinen
- Palettenfotos
- E-Mail-Anhänge
- sonstige Belege

Pro Dokument sollen u. a. erfasst werden:
- Dokumenttyp
- Quelle
- Datei
- Dateihash
- OCR-/Erkennungstext
- erkannter Lieferant
- erkannte Bestellung / Wareneingang
- Dublettenstatus
- Zuordnungsstatus
- Metadaten

---

## 9. Automatische Dokumentzuordnung

Die automatische Zuordnung soll **regelbasiert** arbeiten und später durch **automatisches Lernen** erweitert werden können.

Mögliche Erkennungskriterien:
- Absender
- Betreff
- Dateiname
- Lieferantenname
- Lieferscheinnummer
- Datum
- Bestellnummer
- typische Artikelnummern
- Lieferadresse / Standort
- Dokumentlayout / Mapping-Profil

Wichtig:
- Regelwerk zuerst
- danach lernbare Erweiterung
- unsichere Zuordnung in Prüfliste
- keine blinde Vollautomatik

---

## 10. Wareneingang

## 10.1 Grundprinzip
In der Regel stimmt die bestellte Menge mit der gelieferten Menge überein.

Daher soll es möglich sein:
- Lieferung als **angekommen** zu markieren
- Wareneingang dabei direkt zu buchen

Es muss aber trotzdem möglich sein:
- Mengen nachträglich zu korrigieren
- Abweichungen zu erfassen
- falsch gelieferte Positionen zu dokumentieren

## 10.2 Kontrollstufen
Je Lieferant sollen unterschiedliche Kontrollstufen möglich sein:
- nur angekommen / nicht angekommen
- Summenkontrolle VPE / Gesamtmenge
- Summenkontrolle Paletten
- vollständige Positionskontrolle
- vollständige Positionskontrolle mit MHD-Erfassung bei relevanten Produkten

Diese Kontrollstufen sollen je Lieferant konfigurierbar sein und optional je Produktgruppe oder Produkt überschrieben werden können.

---

## 11. MHD-Management

MHD ist immer als **MHD** zu bezeichnen.

## 11.1 Mehrere MHDs pro Artikel
Ein Artikel kann gleichzeitig mehrere MHDs haben:
- im Markt
- im Lager
- verteilt auf mehrere Bestandssegmente
- ggf. aus unterschiedlichen Wareneingängen

In der Regel soll immer das **ältere MHD zuerst abverkauft** werden.

## 11.2 Nachvollziehbarkeit
Wenn ein eigentlich bereits abverkauftes älteres MHD später wieder auftaucht, muss nachvollziehbar sein:
- wann es angeliefert wurde
- von welchem Lieferanten es kam
- in welchem Wareneingang es erfasst wurde
- welcher Mitarbeiter es verräumt hat
- ob Umlagerungen erfolgt sind
- ob Inventur- oder Korrekturbuchungen dazwischenlagen

Dafür braucht es einen belastbaren Bewegungs- bzw. Audit-Trail.

## 11.3 MHD-Erfassung im Wareneingang
MHD-Erfassung gehört zum **Wareneingang**, nicht zur Leergutrückgabe.

Pro Produkt sollen MHD-Regeln möglich sein:
- nie
- optional
- empfohlen
- pflicht

Zusätzlich sollen Risikofaktoren berücksichtigt werden, z. B.:
- Produkt wird seltener verkauft als eingekauft
- letzter Verkauf liegt lange zurück
- letzter Wareneingang liegt lange zurück
- Produkt ist als kritisch markiert
- Produkt ist bekannte Problemware

---

## 12. MHD-Warnungen und Auffälligkeiten

Es sollen Listen und Warnungen entstehen für:
- bald ablaufende Ware
- abgelaufene Ware
- Produkte mit auffälligem Altbestand
- Produkte mit mutmaßlichen FIFO-Problemen
- Produkte mit wiederkehrenden MHD-Problemen

---

## 13. Aussortierung / Bruch / MHD-Ware

Fahrer oder Marktmitarbeiter sollen Produkte aussortieren können als:
- Bruchware
- abgelaufene Ware
- rabattierte MHD-Ware
- sonstige problematische Ware

Dabei soll der Bezug zum **echten Produkt** erhalten bleiben.

Zusätzlich sollen Ursachen bewertet werden können, z. B.:
- zu viel bestellt
- Stammkunde abgesprungen
- Veranstaltung ausgefallen
- FIFO ignoriert
- Ware vergessen / falsch verräumt
- zu viel Kommissionsrückgabe
- unbekannt / sonstiges

---

## 14. LS POS Einschränkung

LS POS kann die gewünschte interne Logik für rabattierte MHD-Ware vermutlich nicht vollständig nativ abbilden.

Deshalb soll gelten:
- Shoptour2 führt intern die saubere Wahrheit
- der Bezug zum echten Produkt bleibt erhalten
- Status wie regulär / rabattierte MHD-Ware / nicht mehr verkaufsfähig sollen intern abbildbar sein
- eine spätere Brücke zu LS POS ist möglich, aber das interne Modell darf nicht an LS POS vereinfacht werden

---

## 15. Leergutrücknahme

Die Leergutrücknahme wurde in den noch zu importierenden `ninoxalt_`-Daten bereits abgebildet.  
Daraus soll gelernt werden.

Die Logik soll übernommen und modernisiert werden:
- Palette erfassen
- Standardkästen erfassen
- seltene Kästen erfassen
- Kontrollzählung
- Gesamtanzahl
- zwei Fotos
- spätere Vorbereitung für automatische Erkennung

---

## 16. Import- und Mapping-Strategie

Claude soll für den Import aus Kolabri-Ninox eine saubere Strategie umsetzen:

### 16.1 Zugangsdaten aus Ninox-Link
- Team-ID: `yzW23724nQbqCQX9R`
- Datenbank-ID: `fadrrq8poh9b`
- Modul-ID Beispiel: `X`
- View-ID Beispiel: `TKK2rtfo7VszBq5u`

### 16.2 Importregeln
- `ninoxalt_` Tabellen anlegen
- nur lesend / als Importquelle verwenden
- keine operative Hauptnutzung
- keine stille Vermischung mit `ninox_`
- Mapping dokumentieren
- Aktualität der Daten berücksichtigen

### 16.3 Mapping
Es soll eine nachvollziehbare Mapping-Logik geben zwischen:
- `ninoxalt_` Tabellen/Feldern
- operativen Shoptour2-Tabellen/Feldern
- Transformationsregeln
- Priorisierung nach Aktualität

---

## 17. MVP-Umfang

Im MVP sollen mindestens umgesetzt werden:
- Importkonzept für `ninoxalt_`
- Lieferantenprofile und Bestellregeln
- Bestellvorbereitung
- zentrale Dokumententabelle
- regelbasierte Dokumentzuordnung
- konfigurierbare Wareneingangskontrolle
- direkte Wareneingangsbuchung bei „angekommen“
- Korrekturmöglichkeit bei Abweichungen
- MHD-Führung mit mehreren MHDs pro Artikel
- Audit-Trail für MHD-Nachvollziehbarkeit
- MHD-Warnlisten
- Aussortierung / Bruch / rabattierte MHD-Ware
- Übernahme der Leergutrückgabe-Logik aus `ninoxalt_`

---

## 18. Offene Leitplanken für die Umsetzung

- Keine Vermischung von `ninox_` und `ninoxalt_`
- `ninoxalt_` ist älter und dient primär als Referenz-/Lernquelle
- Operative Logik in Shoptour2-eigenen Tabellen
- Regelwerke konfigurierbar statt hart codiert
- Dokumenterkennung zuerst regelbasiert, später lernfähig
- MHD immer sauber benennen
- Geschwindigkeit im Alltag beachten: Standardfall soll schnell buchbar sein
