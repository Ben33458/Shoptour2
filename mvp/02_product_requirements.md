# 02 – Produktanforderungen

## A. Mitarbeiter und Rollen

### Anforderungen
- Ein Mitarbeiter kann mehrere Rollen haben.
- Beispiele für Rollen:
  - Marktmitarbeiter
  - Fahrer
  - Unterstützer
  - Schichtplaner
  - Admin
  - HR / Lohn
- Mitarbeiter können optional einem Vorgesetzten unterstellt sein.
- Berechtigungen leiten sich aus Rollen und ggf. direkter Freigabe ab.

### MVP-Entscheidung
- Rollenmodell als n:m-Beziehung
- zusätzliche Berechtigungen optional vorbereiten, aber im MVP nicht überkomplex machen

## B. Schichtplanung

### Anforderungen
- Schichten werden aus dem Dienstplan erzeugt.
- Regelmäßige feste Schichten sollen hinterlegt werden können.
- Mindestbesetzung für Bereiche:
  - Markt: Mo–Sa, typischer Rahmen 10–22 Uhr, mindestens 1 Person
  - Lieferung: Mo–Fr, typischer Rahmen 7–14 Uhr, mindestens 1 Person
  - Unterstützer: flexibel, ohne feste Mindestbesetzung
- Überschneidungen sind erlaubt.
- Kritisch ist Unterbesetzung.
- Feiertage Hessen müssen berücksichtigt werden.
- Fahrer können an Feiertagen eingeplant sein.

### MVP-Entscheidung
- einfache Schichtdefinition mit Start, Ende, Bereich
- regelmäßige Schichtvorlagen für Mitarbeiter möglich
- Konfliktanzeige bei Lücken
- Ruhezeiten zwischen Schichten prüfen

## C. Zeiterfassung

### Anforderungen
- Mitarbeiter kann eine geplante Schicht starten
- Mitarbeiter kann Pause beginnen und beenden
- Mitarbeiter kann die Schicht beenden
- tatsächliche und gesetzlich erforderliche Pause getrennt speichern
- falls zu wenig Pause genommen wurde, wird automatisch eine Korrekturpause berechnet
- diese Korrektur darf nicht die echte Pause überschreiben
- wenn Ausstempeln vergessen wurde:
  - automatisch zum geplanten Schichtende beenden
  - falls nötig spätestens mit 12-Stunden-Notbremse technisch schließen
  - für Admin deutlich markieren
- manueller Nachtrag nur mit Begründung und Freigabe einer berechtigten Person

### Rechtliche Mindestlogik
- gesetzliche Pause überwachen
- Ruhezeiten überwachen
- Überschreitung von Arbeitszeitgrenzen markieren
- alles revisionssicher speichern

## D. Schichtbericht

### Pflichtinhalt MVP
- Freitext „Was wurde erledigt?“
- besondere Vorkommnisse
- Kassensturz gemacht ja/nein
- Kassendifferenz ja/nein
- falls ja: Differenzbetrag
- Glasbruch ja/nein
- optionaler Fotobeleg
- Pflichtcheckliste je Schichttyp
- Anzeige der offenen Aufgaben für diese Schicht / diesen Bereich

### Schichttyp-spezifische Checklisten

#### Marktanfangsschicht
- Markt aufgeschlossen
- geöffnet-Schild Status geprüft
- Ware / Außendarstellung startklar
- Grundkontrolle Markt gemacht

#### Marktendeschicht
- Kassensturz gemacht
- Einkaufswagen abgeschlossen
- Autos kontrolliert
- alle Lichter ausgeschaltet
- geöffnet-Schild Status geprüft
- Markt abgeschlossen

#### allgemeine Schicht / Unterstützerschicht
- Pflichtpunkte konfigurierbar

#### Sarah-Sonderlogik
- kein Marktöffnen als Pflicht
- Geld in Tresor eingezahlt?
- besondere Vorkommnisse?

## E. Aufgaben

### Bestehende regelmäßige Aufgaben
- bestehen bereits und müssen eingebunden werden
- werden per Cronjob auf Fälligkeit geprüft
- können zwei Logiken haben:
  1. relativ ab Erledigung
  2. fest kalenderbasiert

### Offene Aufgaben
- können manuell oder automatisch erzeugt sein
- müssen filterbar sein nach:
  - heute fällig
  - heute zwingend
  - demnächst fällig
  - überfällig
  - flexibel
  - Bereich
  - Mitarbeiter
  - Status
- Beispiele harte Pflichtaufgaben:
  - Kassensturz
  - Leergut für Nachtanlieferung bereitstellen
- Beispiele flexible Intervallaufgaben:
  - Regal bauen
  - Auto putzen
  - SUNVI-Bestellung machen

## F. Urlaub

### Mitarbeiterseite
- nur Zeitraum von-bis als Pflicht
- Freitextfeld mit Hinweis, welche freiwilligen/planungsrelevanten Infos sinnvoll sind
- Feld „im Notfall erreichbar“ optional
- Vertreter optional
- Ersatz abgesprochen ja/nein optional

### Adminseite
- genehmigen / ablehnen
- Konflikthinweise für ±7 Tage anzeigen:
  - andere Urlaube
  - Veranstaltungen
  - Lieferungen
  - Feiertage
  - erkennbare Schichtlücken
- Feld „in Lexoffice Lohn eingetragen“ nur für Admin / HR

## G. Gamification

### MVP
- nur Anzeige / Achievements
- keine geldwerte Wirkung
- keine Popups
- Anzeige bevorzugt auf Mitarbeiterprofil und Dashboard

### Ideen im MVP
- keine Kassendifferenz seit x Tagen
- kein Glasbruch seit x Tagen
- vollständige Schichtberichte-Streak
- pünktige Aufgabenquote
- erledigte Aufgaben geben Punkte

### Wichtig
- kein Anreizsystem, das zu falschen Angaben motiviert
- späteres Finetuning einplanen

## H. Dashboard

### Mitarbeiter-Dashboard
- aktuelle Schicht
- Start/Stop/Pause
- eigene offenen Aufgaben
- Schichtbericht der aktuellen/geplanten Schicht
- eigene Achievements

### Admin-Dashboard
- aktuell eingestempelte Mitarbeiter
- heute geplante Mitarbeiter
- unvollständige Schichten
- fehlende Pflichtaufgaben
- Stundenübersicht pro Mitarbeiter für aktuelle/vergangene Woche
- Plus-/Minusstunden
- offene Urlaubsanträge
- kritische Abweichungen

