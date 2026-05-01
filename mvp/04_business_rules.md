# 04 – Business Rules und rechtliche Leitplanken

## 1. Zeiterfassung und Pause

### Reale vs. automatische Pause
Es müssen getrennt gespeichert werden:
- tatsächlich erfasste Pausenzeit
- gesetzlich erforderliche Pausenzeit
- automatisch angerechnete Korrekturpause

Die Korrekturpause darf die echte Pausenzeit nicht überschreiben.

### Mindestpause
MVP-Regelwerk:
- mehr als 6 bis einschließlich 9 Stunden Arbeit -> mindestens 30 Minuten Pause
- mehr als 9 Stunden Arbeit -> mindestens 45 Minuten Pause
- Pausenabschnitte unter 15 Minuten zählen nicht als gesetzliche Pause

### Compliance-Kennzeichnung
Falls zu wenig echte Pause genommen wurde, muss die Schicht markiert werden, z. B.:
- warning
- violation
- requires_admin_review = true

## 2. Ruhezeit
- Zwischen zwei Schichten soll eine gesetzliche Ruhezeit geprüft werden.
- Bei Konflikt darf die Planung nicht unbemerkt bleiben.
- Im MVP reicht zunächst eine deutliche Warnung statt harter Sperre.

## 3. Vergessenes Ausstempeln

### Ziel
Vergessenes Ausstempeln soll nicht zu künstlichen Überstunden führen.

### Logik
1. Wenn geplante Schicht endet und kein clock_out vorhanden ist:
   - automatisch zum geplanten Schichtende ausstempeln
2. Falls Datensatz weiterhin problematisch/offen ist:
   - technische Notbremse bei 12 Stunden
3. Beide Fälle kennzeichnen:
   - auto_closed_by_system = true
   - requires_admin_review = true
   - clock_out_source entsprechend setzen

## 4. Manuelle Nachträge
- nur mit Pflicht-Begründung
- nur nach Freigabe durch Admin oder berechtigte Person
- Freigabeentscheidung muss protokolliert werden
- Originalzustand und Änderungswunsch revisionssicher speichern

## 5. Schichtbericht-Vollständigkeit
- Schichtbericht kann Status `incomplete` haben
- fehlende Pflichtfelder und fehlende Pflichtchecklisten müssen sichtbar sein
- keine Popups, sondern Inline-Hinweise / Banner / Statusboxen
- Admin sieht unvollständige Schichten im Dashboard

## 6. Aufgabenlogik

### Regelmäßige Aufgaben
- bestehen bereits
- werden per Cronjob auf Fälligkeit geprüft
- offene Aufgaben werden erzeugt, wenn fällig und nicht bereits vorhanden/abgedeckt

### Arten offener Aufgaben
- harte Pflicht heute
- flexible Intervallaufgabe
- einmalige Aufgabe

### Filterung
Mindestens folgende Filter:
- heute
- heute zwingend
- überfällig
- offen
- erledigt
- Bereich
- Mitarbeiter
- flexible Aufgaben

## 7. Urlaub
- Pflicht: Zeitraum von-bis
- optionaler Freitext mit Hinweis auf planungsrelevante Infos
- optional: im Notfall erreichbar
- optional: Vertreter / Ersatz abgesprochen
- Admin entscheidet
- Konflikthinweise in ±7 Tagen
- Feld `entered_in_lexoffice_payroll` nur für Admin/HR

## 8. Feiertage
- Hessen-Feiertage werden importiert und in Tabelle gespeichert
- diese Tabelle dient auch anderen Modulen, z. B. Öffnungszeitenlogik
- Feiertag bedeutet nicht automatisch Schichtverbot
- je Bereich auswerten:
  - Markt standardmäßig geschlossen
  - Lieferung standardmäßig möglich

## 9. Rollen und Sichtbarkeit
- Mitarbeiter sieht primär eigene Daten
- Vorgesetzter sieht unterstellte Mitarbeiter
- Schichtplaner sieht Planungsdaten
- Admin sieht alles
- HR/Admin sieht Lexoffice-Lohn-Kennzeichen
- Mehrfachrollen müssen unterstützt werden

## 10. Logging
Jede relevante Aktion muss logbar sein, insbesondere:
- Zeitanlage / Zeitänderung
- Pausenbuchung
- Auto-Close
- Freigabe oder Ablehnung von Nachträgen
- Urlaubsantrag / Entscheidung
- Schichtbericht-Statuswechsel
- Aufgaben-Erzeugung durch Cronjob
- Aufgaben-Erledigung
- Rollen-/Rechteänderungen

