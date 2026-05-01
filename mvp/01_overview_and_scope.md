# 01 – Überblick und MVP-Umfang

## Ziel

ShopTools 2 soll um eine Mitarbeiterverwaltung erweitert werden. Das MVP soll den operativen Alltag im Markt- und Lieferbetrieb abdecken, ohne schon alle denkbaren Sonderfälle bis ins letzte Detail zu lösen.

## Kernmodule des MVP

### 1. Mitarbeiterverwaltung
- Mitarbeiter-Stammdaten
- Zuordnung von Rollen
- Zuordnung eines optionalen Vorgesetzten
- Status aktiv/inaktiv

### 2. Schichtplanung
- Schichten werden aus dem Dienstplan erzeugt
- Bereiche zunächst:
  - Marktschicht
  - Lieferschicht
  - Unterstützerschicht
- Schichtstart und Schichtende
- Mindestbesetzung je Bereich prüfbar
- Feiertage Hessen berücksichtigen
- Fahrer dürfen auch an Feiertagen arbeiten

### 3. Zeiterfassung
- Kommen
- Gehen
- Pause starten
- Pause beenden
- Berechnung von Netto-/Bruttozeit
- Trennung von tatsächlich erfasster Pause und automatisch angerechneter Korrekturpause
- automatische Beendigung bei vergessenem Ausstempeln

### 4. Schichtbericht
- Freitext: Was wurde erledigt?
- besondere Vorkommnisse
- Kassensturz durchgeführt ja/nein
- Kassendifferenz ja/nein
- Kassendifferenzbetrag
- Glasbruch ja/nein
- optionaler Fotobeleg
- Pflichtchecklisten je Schichttyp
- Anzeige und Abarbeitung offener Aufgaben
- Übergabe an bestehende Kassenverwaltung für Tresoreinnahmen

### 5. Aufgabenmodul
- bestehende regelmäßige Aufgaben bleiben erhalten
- offene Aufgaben werden per Cronjob erzeugt/aktualisiert
- Filter für offene Aufgaben
- Unterscheidung zwischen harten Pflichtaufgaben und flexiblen Intervallaufgaben

### 6. Urlaubsverwaltung
- Antrag von-bis
- optionales Freitextfeld mit Planungshinweis
- optionales Feld „im Notfall erreichbar“
- optionaler Vertreter / Ersatz abgesprochen
- Admin entscheidet über Genehmigung/Ablehnung
- im Admin-Kontext Hinweise auf Konflikte in ±7 Tagen
- Admin-Feld: in Lexoffice Lohn eingetragen

### 7. Admin-Dashboard
- wer ist aktuell eingestempelt
- wer ist heute eingeplant
- unvollständig dokumentierte Schichten
- fehlende Pflichtaufgaben heute
- Stundenübersicht letzte/aktuelle Woche
- Plus-/Minusstunden
- offene Urlaubsanträge
- offene und überfällige Aufgaben

### 8. Logging
- zentrale systemweite Logtabelle
- filterbar nach Kategorie, Ereignis, Benutzer, Objekt, Zeitraum
- revisionssicher

## Nicht Teil des MVP
- komplexe Lohnabrechnung
- automatische Lexoffice-Synchronisation
- Gamification mit echten Belohnungen
- fein granularer Bereichswechsel innerhalb einer Schicht
- vollständige Arbeitsvertragslogik mit allen Sondermodellen
- umfassendes Reporting für mehrere Jahre

