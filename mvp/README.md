# ShopTools 2 – Mitarbeiterverwaltung MVP

Dieses Paket enthält die MVP-Spezifikation für Claude, damit die Mitarbeiterverwaltung in ShopTools 2 strukturiert umgesetzt werden kann.

## Inhalt

- `01_overview_and_scope.md` – Ziel, Umfang, Abgrenzung
- `02_product_requirements.md` – funktionale Anforderungen
- `03_data_model.md` – vorgeschlagenes MVP-Datenmodell
- `04_business_rules.md` – fachliche und rechtliche Regeln
- `05_ui_ux_notes.md` – Bedienlogik, mobile Nutzung, keine Popups
- `06_implementation_plan.md` – empfohlene Umsetzungsreihenfolge
- `07_acceptance_criteria.md` – Abnahmekriterien
- `08_claude_build_prompt.md` – fertiger Arbeits-Prompt für Claude

## Ziel des MVP

Das MVP soll folgende Kernbereiche abdecken:

1. Mitarbeiter und Rollen
2. Dienstplanbasierte Schichten
3. Zeiterfassung inkl. Pausen
4. Schichtbericht mit Pflichtfeldern und Checklisten
5. Offene Aufgaben inkl. bestehender regelmäßiger Aufgaben per Cronjob
6. Urlaubsanträge und Admin-Freigabe
7. Feiertage Hessen als Stammdaten
8. Admin-Dashboard mit relevanten Kennzahlen
9. Revisionssichere Logtabelle
10. Mobile nutzbare Oberfläche

## Wichtige Leitlinien

- keine Popups als Hauptmechanik
- deutliche Inline-Hinweise, Banner, Statuschips
- echte Zeit und automatisch korrigierte Zeit getrennt speichern
- revisionssichere Änderungen
- mehrere Rollen pro Mitarbeiter
- Mitarbeiter können Vorgesetzten zugeordnet werden
- Schichten werden aus dem Dienstplan erzeugt

