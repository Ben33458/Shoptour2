# PROJ-36: Schichtplanung, Zeiterfassung & Urlaubsverwaltung

## Status: Planned
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- Requires: PROJ-18 (Admin: Benutzer & Rollen) — Mitarbeiter-Accounts müssen existieren
- Integrates with: PROJ-15 (Admin: Fahrertouren) — abgeschlossene Touren fließen in Schichtberichte
- Integrates with: PROJ-26 (Admin: Aufgabensystem) — erledigte Aufgaben fließen in Schichtberichte

## Beschreibung
Verwaltung von Mitarbeiterschichten, Zeiterfassung (Stempeluhr) und Urlaubsplanung. Beim Abschluss einer Schicht wird automatisch ein Schichtbericht erstellt: Erledigte Touren (aus PROJ-16) und abgehakte Aufgaben (aus PROJ-26) werden automatisch als Tätigkeiten eingetragen. Mitarbeiter können den Bericht mit manuellen Einträgen ergänzen. Urlaubsanträge durchlaufen einen einfachen Genehmigungsworkflow und werden im Schichtplan berücksichtigt.

### Schicht-Status-Workflow

```
Geplant → Aktiv (eingecheckt) → Abgeschlossen
                                      ↓
                               Bericht erstellt
```

### Automatischer Schichtbericht

Beim Abschluss einer Schicht (Ausstempeln) wird ein Bericht automatisch befüllt:

| Quelle | Inhalt |
|--------|--------|
| Fahrertouren (PROJ-16) | Tourname, Anzahl Stops, Start-/Endzeit, Gesamtkilometer (falls erfasst) |
| Aufgaben (PROJ-26) | Abgehakte Aufgaben mit Erledigt-Zeitstempel |
| Zeiterfassung | Soll-Zeit, Ist-Zeit, Differenz |

Der Mitarbeiter kann nach Abschluss manuelle Einträge ergänzen (Freitext). Admin kann den Bericht kommentieren und abnehmen (`reviewed_by`).

## User Stories
- Als Admin möchte ich Schichten für Mitarbeiter planen (Datum, Soll-Startzeit, Soll-Endzeit, Mitarbeiter).
- Als Admin möchte ich eine Wochenübersicht aller geplanten Schichten sehen.
- Als Mitarbeiter möchte ich mich zu Beginn meiner Schicht einstempeln (Zeiterfassung startet).
- Als Mitarbeiter möchte ich mich am Ende meiner Schicht ausstempeln; ein Schichtbericht wird automatisch erstellt.
- Als Mitarbeiter möchte ich den automatisch erstellten Schichtbericht mit manuellen Tätigkeitseinträgen ergänzen.
- Als Admin möchte ich Schichtberichte aller Mitarbeiter einsehen und abnehmen (reviewen).
- Als Admin möchte ich Schichtberichte einsehen und als PDF exportieren (ohne Unterschrift-Feld).
- Als Admin möchte ich die Schwellenwerte für gesetzliche Mindestpausen konfigurieren.
- Als Mitarbeiter möchte ich einen Urlaubsantrag stellen (Zeitraum, Urlaubsart).
- Als Admin möchte ich Urlaubsanträge genehmigen oder ablehnen.
- Als Admin möchte ich das Urlaubskonto pro Mitarbeiter und Jahr verwalten (Gesamttage, genommen, Resturlaub).
- Als Admin möchte ich im Schichtplan sehen, wer Urlaub/Krankheit hat.
- Als Admin möchte ich Krankmeldungen nachträglich eintragen (auch rückwirkend).

## Acceptance Criteria

### Schichtplanung
- [ ] **Schicht anlegen:** Mitarbeiter, Datum, Soll-Start, Soll-Ende, optionale Notiz; Status: `geplant`
- [ ] **Wochenansicht:** Alle Mitarbeiter in einer Kalendermatrix; Schichten, Urlaub und Krankheit farblich unterschieden
- [ ] **Übersicht pro Mitarbeiter:** Alle Schichten im Monat mit Soll/Ist-Stunden, Differenz, Status
- [ ] **Schicht bearbeiten:** Solange Status `geplant`; nach `aktiv` nur noch Admin
- [ ] **Schicht löschen:** Nur wenn Status `geplant` und kein Bericht vorhanden

### Zeiterfassung (Stempeluhr)
- [ ] **Einstempeln (POST /api/shift/clock-in):** Schicht-ID oder automatisch passende Schicht des Tages ermitteln; `actual_start` wird gesetzt; Status → `aktiv`
- [ ] **Ausstempeln (POST /api/shift/clock-out):** `actual_end` wird gesetzt; Mindestpausen-Prüfung läuft; Status → `abgeschlossen`; Schichtbericht wird automatisch generiert
- [ ] **Pausen (manuell):** Mitarbeiter kann Pausenbeginn/Pausenende stempeln; mehrere Pausen pro Schicht möglich; Summe wird von Arbeitszeit abgezogen
- [ ] **Automatischer Mindestpausen-Abzug (ArbZG §4):**
  - Wenn erfasste Pause < gesetzliches Minimum → System zieht automatisch die Differenz ab
  - Basis-Schwellenwerte (konfigurierbar in Admin → Einstellungen → Arbeitszeitgesetz):

    | Arbeitszeit (brutto) | Mindestpause | Konfigurierbar |
    |---|---|---|
    | < 6 Stunden | 0 Minuten | ja |
    | 6–9 Stunden | 30 Minuten | ja |
    | > 9 Stunden | 45 Minuten | ja |

  - Der automatisch abgezogene Betrag wird im Schichtbericht transparent ausgewiesen: „Gesetzliche Mindestpause automatisch abgezogen: 30 min"
  - Die Funktion kann pro Company deaktiviert werden (für Unternehmen mit anderer Regelung)
- [ ] **Manuelle Korrektur:** Admin kann `actual_start`/`actual_end` und `break_minutes` nachträglich korrigieren (mit Begründung, Audit-Log); Mindestpausen-Abzug wird neu berechnet
- [ ] **Überstunden-Anzeige:** Differenz Ist-Zeit (nach Pausen-Abzug) vs. Soll-Zeit; kumuliert pro Monat/Jahr (informativ, kein Ausgleich im System)

### Schichtbericht
- [ ] **Automatischer Bericht** wird beim Ausstempeln erstellt:
  - Abgeschlossene Fahrertouren des Mitarbeiters im Schicht-Zeitfenster (aus `driver_tours`)
  - Erledigte Aufgaben des Mitarbeiters im Schicht-Zeitfenster (aus `tasks`)
  - Erfasste Arbeitszeitblöcke (Einstempel-Ausstempel, minus Pausen)
- [ ] **Manuelle Tätigkeitseinträge:** Mitarbeiter kann zusätzliche Tätigkeiten eintragen (Freitext + Zeitangabe optional)
- [ ] **Bericht bearbeiten:** Mitarbeiter kann bis zur Admin-Abnahme ergänzen; danach nur noch Admin
- [ ] **Admin-Abnahme:** `reviewed_by`, `reviewed_at`; optionaler Kommentar des Admins
- [ ] **PDF-Export:** Schichtbericht als PDF (Name, Datum, Soll/Ist-Zeit, Pausennachweis inkl. automatisch abgezogener Mindestpause, Tätigkeitsliste)

### Urlaubsverwaltung
- [ ] **Urlaubskonto:** Pro Mitarbeiter und Jahr: `total_days` (Gesamtanspruch), `used_days` (genehmigt genommen), `remaining_days` (berechnet); Admin kann `total_days` anpassen
- [ ] **Urlaubsarten:** `urlaub` / `krank` / `feiertag` / `sonderurlaub` / `unbezahlt`
  - `krank` und `feiertag` können nur vom Admin eingetragen werden
  - `urlaub` und `sonderurlaub` durchlaufen den Antragsprozess
- [ ] **Urlaubsantrag stellen:** Mitarbeiter wählt Zeitraum + Art; Antrag mit Status `pending` erstellt
- [ ] **Antrag genehmigen/ablehnen:** Admin sieht offene Anträge; kann mit Kommentar ablehnen; bei Genehmigung werden Werktage vom Urlaubskonto abgezogen
- [ ] **Kollisionswarnung:** Beim Anlegen eines Antrags wird angezeigt, ob andere Mitarbeiter im gleichen Zeitraum Urlaub haben
- [ ] **Antrag stornieren:** Mitarbeiter kann genehmigten Urlaub stornieren (Status `storniert`); Tage werden zurückgebucht
- [ ] **Schichtplan-Integration:** Urlaub/Krankheit erscheint in der Wochenansicht; keine Schicht-Erstellung für abwesende Mitarbeiter (Warnung)
- [ ] **Krankmeldung rückwirkend:** Admin kann `krank` für vergangene Tage eintragen; fehlende Schichten werden als Krank markiert

### Benachrichtigungen
- [ ] Mitarbeiter wird per Email benachrichtigt, wenn Urlaubsantrag genehmigt/abgelehnt wurde
- [ ] Admin erhält Hinweis bei neuen Urlaubsanträgen (Dashboard-Widget oder Email, konfigurierbar)

## Edge Cases
- Mitarbeiter vergisst auszustempeln → Schicht bleibt `aktiv`; Admin sieht offene Schichten; kann manuell schließen
- Mitarbeiter stempelt zweimal ein (Doppelklick) → Idempotenz: zweiter Clock-in wird ignoriert wenn Schicht bereits `aktiv`
- Keine geplante Schicht für heute → freier Clock-in erlaubt (Admin-Konfiguration); oder Fehlermeldung
- Urlaubsantrag überschreitet Resturlaub → Warnung, aber Admin kann trotzdem genehmigen
- Antrag für Zeitraum mit bereits geplanten Schichten → Warnung; Schichten bleiben bestehen (Admin entscheidet)
- Mitarbeiter ist krank während genehmigtem Urlaub → Admin kann Urlaubstage in Krank umwandeln (Tage werden zurückgebucht)
- Mitarbeiter nimmt 20 min Pause bei 7h Schicht → System zieht 10 min nach (Differenz zur 30-min-Mindestpause); Schichtbericht zeigt: „20 min manuell + 10 min automatisch = 30 min gesamt"
- Mitarbeiter nimmt 40 min Pause bei 7h Schicht → kein automatischer Abzug nötig (40 > 30 min Minimum)
- Admin deaktiviert automatischen Abzug → keine automatische Korrektur; Warnung im Schichtbericht wenn Pause unter gesetzlichem Minimum
- Gesetzliche Feiertage: keine automatische Feiertagskalender-Integration im MVP; manuell einzutragen
- Schichtbericht-Quellen liefern keine Daten (kein Tour, keine Aufgabe) → Bericht wird trotzdem erstellt mit leerem Tätigkeitsblock

## Technical Requirements
- `shifts`: `id`, `user_id`, `planned_start`, `planned_end`, `actual_start`, `actual_end`, `break_minutes`, `status` (Enum), `note`, `company_id`
- `shift_report_entries`: `id`, `shift_id`, `type` (Enum: `tour` / `task` / `manual`), `reference_id` (nullable, für Tour/Aufgabe), `description`, `duration_minutes` (nullable), `created_at`
- `shift_reviews`: `shift_id`, `reviewed_by`, `reviewed_at`, `comment`
- `absence_requests`: `id`, `user_id`, `type` (Enum), `start_date`, `end_date`, `status` (Enum: `pending` / `approved` / `rejected` / `cancelled`), `approved_by`, `comment`, `company_id`
- `vacation_balances`: `user_id`, `year`, `total_days`, `used_days` (berechnet via Trigger/Service), `company_id`
- Schichtbericht-Generierung: `ShiftReportService` liest nach Ausstempeln alle passenden Tours und Tasks im Zeitfenster
- Überstunden-Kumulierung: rein informativer Report, kein Gleitzeitkonto im MVP

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)
_To be added by /architecture_

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
