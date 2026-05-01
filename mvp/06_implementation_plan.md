# 06 – Umsetzungsplan für das MVP

## Phase 1 – Stammdaten und Rechte
1. employees
2. roles + employee_role
3. supervisor-Zuordnung
4. grundlegende Berechtigungsprüfung
5. shift_areas

## Phase 2 – Schichtplanung und Feiertage
1. holidays-Tabelle
2. Seeder/Importer für Hessen-Feiertage
3. shift_templates
4. shifts
5. grundlegende Lücken- und Ruhezeitwarnungen

## Phase 3 – Zeiterfassung
1. time_entries
2. break_entries
3. Start-/Pause-/Stop-Aktionen
4. automatische Pausenberechnung
5. Auto-Close zum geplanten Schichtende
6. 12h-Notbremse
7. Markierung für Admin-Review

## Phase 4 – Schichtbericht
1. shift_reports
2. checklist_templates
3. shift_report_checklist_items
4. Kassensturz / Differenz / Glasbruch / Freitext
5. optionaler Fotobeleg
6. Vollständigkeitsstatus

## Phase 5 – Aufgabenintegration
1. bestehende recurring_tasks einbinden
2. tasks-Tabelle für offene Aufgaben
3. Cronjob zur Erzeugung/Aktualisierung
4. Filterlogik für Aufgabenlisten
5. Aufgaben im Schichtbericht anzeigen

## Phase 6 – Urlaub
1. vacations-Tabelle
2. Mitarbeiterantrag
3. Admin-Entscheidung
4. Konflikthinweise ±7 Tage
5. Admin-Feld Lexoffice Lohn

## Phase 7 – Logging und Dashboard
1. zentrale system_logs
2. Log-Schreiben aus kritischen Aktionen
3. Admin-Dashboard bauen
4. Mitarbeiterprofil mit Achievements

## Phase 8 – Polishing
1. mobile Optimierung
2. Inline-Warnungen verbessern
3. Tests
4. Seed-Daten
5. Dokumentation

