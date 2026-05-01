# Ergänzung – Import- und Mapping-Spezifikation für Bestandsaufnahme

## Zweck
Diese Ergänzung beschreibt nur den Import der ODS-Datei und die fachliche Normalisierung für das Modul Bestandsaufnahme.

Datei:
`bestellzettel_Mindestbestand_Lieferanten_GUT_HMI_VENTENusw.ods`

---

## Grundsatz
Die ODS-Datei ist **fachlich wertvoll, technisch aber heterogen**. Daraus folgt:

- kein pauschaler Einheitsimport
- stattdessen **Rohimport + Blatt-Mapping + Prüfprotokoll**
- DB bleibt führend
- unklare Werte markieren statt stillschweigend überschreiben

---

## Beobachtete Blatt-Typen

### Typ A – einfache Lieferantenlisten
Typische Spalten:
- Produkt
- aktueller Bestand
- Mindest-Bestand
- Bestell-Menge
- optional ArtNr.

Beispiele:
- Venten
- Doelp
- Denecke
- Schmucker
- Metro
- Mio
- magicdrinks

### Typ B – Listen mit Kolabri ArtNr.
Typische Spalten:
- Kolabri ArtNr.
- Produkt
- Lieferanten-ArtNr.
- Mindest-Bestand
- aktueller Bestand
- Bestell-Menge

Beispiele:
- Winkels-GUT-Trinks
- Kelterei Krämer
- Faust
- Eders teilweise
- Hassia
- HBI_Neu / HBI_alt

### Typ C – Sonderblätter mit Zusatzlogik
Beispiele:
- Hassia → Paletten-/Stückelungslogik
- Eders → Mindestbestellung Palette
- Außenlager_Roßdorf → MHD-Spalte
- Herrnbräu → Alt-/Neu-Bestände und Bestellvergleich
- Wostok → vorgeschlagene Bestellmengen in Flaschen/Kästen
- Selgros → Umverpackungslogik / doppelte Bereiche
- Tabelle28 → mehrere potenzielle Lieferanten in einem Blatt

---

## Import-Ziele
Beim Import sollen – soweit eindeutig erkennbar – normalisierte Datensätze erzeugt werden für:

1. Lieferant
2. Basisartikel
3. Lieferanten-Zuordnung
4. Verpackungseinheit
5. Mindestbestand je Lager
6. Importkonflikt / Prüfhinweis

---

## Rohimport
Für jedes Tabellenblatt:
- Dateiname speichern
- Tabellenblattname speichern
- Rohzeilen strukturiert ablegen
- erkannte Kopfzeile markieren
- Mapping-Status speichern

Empfohlene Hilfstabelle:
`import_bestandsaufnahme_rohzeilen`

Felder:
- id
- importlauf_id
- dateiname
- tabellenblatt
- zeilennummer
- roh_payload_json
- erkannt_status
- mapping_hinweis
- created_at

---

## Mapping-Strategie
Pro Tabellenblatt bzw. Lieferant wird ein Mapping definiert:

- spalte_kolabri_artnr
- spalte_lieferanten_artnr
- spalte_produktname
- spalte_mindestbestand
- spalte_bestand
- spalte_bestellmenge
- spalte_mhd
- spalte_vpe_hinweis
- spalte_bestellhinweis
- lager_id_standard
- lieferant_id_standard

Empfohlene Hilfstabelle:
`import_bestandsaufnahme_mappings`

---

## Erkennungslogik
Das System darf heuristisch unterstützen, aber nicht blind entscheiden.

### Erkennungshilfen
Mögliche Header-Indikatoren:
- Kol.-ArtNr / KolabriArtNr / ArtNrKolabri / Artikelnummer
- Produkt (+ Artikelnummer bei Lieferant)
- aktuellerBestand / Bestand / Geliefert
- Mindest-Bestand / Mindestbestand
- Bestell-Menge / VPEs bestellen / Bestellen
- MHD
- EK_preis
- packsize

### Wenn unklar
- Datensatz nicht automatisch produktiv übernehmen
- als prüfbedürftig markieren
- in Importübersicht anzeigen

---

## Normalisierung

### 1. Artikel-Matching
Reihenfolge:
1. Match über Kolabri ArtNr.
2. wenn nicht vorhanden: Match über bestehende Lieferanten-Zuordnung
3. sonst nur Importvorschlag erzeugen

### 2. Verpackungseinheit extrahieren
Aus Produktnamen nach Möglichkeit VPE erkennen, z. B.:
- 24x0,33
- 20x0,5
- 12x0,7
- 6x1,0
- 0,04
- 0,1

Ergebnis:
- erkannte VPE als Verpackungseinheit vorschlagen oder vorhandener Einheit zuordnen
- wenn keine sichere Erkennung möglich: Prüfhinweis

### 3. Mindestbestand umrechnen
- ODS-Wert ist häufig in VPE
- DB soll Basiseinheit speichern
- daher: `mindestbestand_vpe * faktor_basiseinheit = mindestbestand_basiseinheit`

### 4. Lagerzuordnung
- Standardmäßig konfigurierbar pro Blatt/Mapping
- Sonderfall Außenlager_Roßdorf explizit auf entsprechendes Lager mappen
- keine stillen Annahmen ohne Mapping

---

## Konfliktregeln

### DB gewinnt
Wenn in DB bereits strukturierte Daten vorhanden sind:
- bestehende DB-Werte nicht blind überschreiben
- importierten ODS-Wert als Vergleichswert speichern
- Konflikt markieren

### Konfliktarten
- abweichender Mindestbestand
- abweichender Standard-Lieferant
- unklare Verpackungseinheit
- Produkt ohne Match
- mehrere mögliche Matches
- fehlende Kolabri ArtNr.
- widersprüchliche Lieferanten-ArtNr.

Empfohlene Konflikttabelle:
`import_bestandsaufnahme_konflikte`

---

## UI für Importprüfung
Es braucht eine einfache Prüfansicht mit:
- Importlauf
- Tabellenblatt
- Rohzeile
- erkannte Felder
- vorgeschlagene Zuordnung
- Konfliktstatus
- Aktion:
  - übernehmen
  - verwerfen
  - manuell zuordnen
  - als Referenz nur speichern

---

## Mindestanforderung MVP
Für das MVP genügt:
- ODS-Datei einlesen
- Tabellenblätter einzeln verarbeiten
- Mapping-Konfiguration je Blatt
- Kolabri ArtNr. sauber übernehmen, wenn vorhanden
- Mindestbestände in Basiseinheit speichern
- Konflikte protokollieren
- Außenlager_Roßdorf als Sonderblatt sauber unterstützen
- Winkels-GUT-Trinks und Hassia als wichtige Sonderfälle sauber unterstützen
- kein stilles Überschreiben produktiver Stammdaten
