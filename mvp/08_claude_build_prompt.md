# Claude Build Prompt – ShopTools 2 Mitarbeiterverwaltung MVP

Du arbeitest im bereits bestehenden Laravel-MySQL-Projekt namens ShopTour2. Setze die Mitarbeiterverwaltung als MVP sauber und produktionsnah um. Arbeite strukturiert, modular und mit gut lesbarem Code.

## Projektkontext

Das Modul soll folgende Bereiche abdecken:
- Mitarbeiter-Stammdaten
- Rollen und Berechtigungen
- Schichtplanung auf Basis eines Dienstplans
- Zeiterfassung mit gesetzlicher Pausenlogik
- Schichtberichte mit Pflichtchecklisten
- offene Aufgaben auf Basis bestehender regelmäßiger Aufgaben
- Urlaubsanträge mit Admin-Freigabe
- Hessen-Feiertage als Stammdaten
- Admin-Dashboard
- zentrale revisionssichere Logtabelle

## Wichtige fachliche Regeln

### Rollen
- Ein Mitarbeiter kann mehrere Rollen haben.
- Mitarbeiter können einem Vorgesetzten unterstellt sein.
- Beispiel: Ein Mitarbeiter kann zugleich Marktmitarbeiter, Fahrer und Schichtplaner sein.

### Schichten
- Schichten werden aus dem Dienstplan erzeugt.
- Bereiche zunächst: Marktschicht, Lieferschicht, Unterstützerschicht.
- Start und Ende reichen im MVP.
- Feiertage Hessen müssen berücksichtigt werden.
- Fahrer dürfen an Feiertagen arbeiten.

### Zeiterfassung
- Funktionen: Einstempeln, Pause starten, Pause beenden, Ausstempeln.
- Tatsächlich erfasste Pause und automatisch angerechnete Korrekturpause müssen getrennt gespeichert werden.
- Wenn zu wenig Pause genommen wurde, rechne die gesetzlich erforderliche Pause nach, aber überschreibe niemals die echte Pause.
- Kennzeichne solche Fälle als Compliance-Warnung und für Admin-Review.
- Wenn Ausstempeln vergessen wurde:
  1. automatisch zum geplanten Schichtende ausstempeln
  2. falls etwas schief läuft, technische 12h-Notbremse
- Auto-Close-Fälle für Admin deutlich markieren.
- Manuelle Zeitnachträge nur mit Pflichtbegründung und Freigabe durch berechtigte Person.
- Änderungen revisionssicher speichern.

### Schichtbericht
- pro Schicht ein Schichtbericht
- Pflichtfelder:
  - erledigte Arbeiten (Freitext)
  - besondere Vorkommnisse
  - Kassensturz gemacht ja/nein
  - Kassendifferenz ja/nein
  - falls ja: Differenzbetrag
  - Glasbruch ja/nein
- optionaler Fotobeleg
- Pflichtchecklisten je Schichttyp
- Anzeige relevanter offener Aufgaben auf derselben Seite
- Tresoreinnahmen laufen über bestehende Kassenverwaltung; keine doppelte Logik bauen

### Aufgaben
- Die regelmäßigen Aufgaben existieren bereits. Nicht neu erfinden, sondern sinnvoll anbinden.
- Verwende einen Cronjob, der offene Aufgaben aus regelmäßigen Aufgaben erzeugt/aktualisiert.
- Unterstütze zwei Logiken:
  - ab Erledigung erneut fällig
  - kalenderbasiert erneut fällig
- Offene Aufgaben müssen filterbar sein nach heute, heute zwingend, überfällig, flexibel, Bereich, Mitarbeiter, Status.

### Urlaub
- Mitarbeiter gibt nur von-bis verpflichtend ein.
- Zusätzlich ein Freitextfeld mit Hinweis auf freiwillige planungsrelevante Informationen.
- Optionales Feld: im Notfall erreichbar.
- Optional Vertreter/Ersatz abgesprochen.
- Admin kann genehmigen oder ablehnen.
- Admin soll Konflikthinweise im Zeitraum ±7 Tage sehen: andere Urlaube, Feiertage, Veranstaltungen, Lieferungen, Schichtlücken.
- Feld „in Lexoffice Lohn eingetragen“ ist nur für Admin/HR sichtbar.
- Bitte nur unproblematische Formulierungen in der UI.

### Dashboard
Admin soll sehen:
- wer gerade eingestempelt ist
- wer heute noch eingeplant ist
- welche Schichten heute unvollständig dokumentiert sind
- welche Pflichtaufgaben heute fehlen
- Stundenübersicht aktuelle/vergangene Woche
- Plus-/Minusstunden
- offene Urlaubsanträge

### UX
- keine Popups als Standardlösung
- mobile first
- lieber Inline-Hinweise, Banner, Statuschips
- klare Statusanzeige für unvollständige oder auffällige Datensätze

### Logging
- Baue eine zentrale Logtabelle für das komplette System.
- Filterbar nach Kategorie, Ereignis, Benutzer, Objekt, Zeitraum.
- Logge insbesondere Zeitänderungen, Auto-Close, Freigaben, Urlaubsentscheidungen, Aufgaben-Cronjobs.

## Technische Erwartung

Bitte liefere:
1. Migrations
2. Models + Relations
3. Enums / Konstanten für Statuswerte
4. Services für Zeiterfassung, Pausenberechnung, Auto-Close, Aufgaben-Cronjob
5. Policies / Rechteprüfung
6. Controller/Actions
7. Validierung
8. Seeders für Rollen, Bereiche, Basis-Checklisten, Hessen-Feiertage
9. einfache Blade-/Admin-Oberflächen oder die bestehende UI-Struktur passend erweitert
10. Tests für kritische Logik

## Architekturhinweise
- keine unnötige Überabstraktion
- lieber klar lesbare Services
- bestehende Module respektieren
- bestehende Kassenverwaltung für Tresoreinnahmen wiederverwenden
- bestehende regelmäßige Aufgaben vorsichtig integrieren
- Status- und Compliance-Logik zentral kapseln
- keine kritischen Werte still überschreiben

## Datenmodell
Nutze als Grundlage die beigefügte Spezifikation in den anderen Dateien dieses Pakets, insbesondere:
- `03_data_model.md`
- `04_business_rules.md`
- `07_acceptance_criteria.md`

## Vorgehensweise
Arbeite in sinnvollen Schritten:
1. Datenmodell und Migrations
2. Stammdaten und Rechte
3. Schichten und Feiertage
4. Zeiterfassung
5. Schichtbericht
6. Aufgabenintegration
7. Urlaub
8. Dashboard
9. Tests und Polishing

Wenn bestehende Tabellen oder Logik angepasst werden müssen, mache das vorsichtig, dokumentiere die Änderungen und vermeide Breaking Changes, soweit möglich.
