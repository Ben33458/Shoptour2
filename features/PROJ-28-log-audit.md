# PROJ-28: Admin: Log & Audit (gefiltert, Dashboard)

## Status: Planned
**Created:** 2026-03-01
**Last Updated:** 2026-03-01

## Dependencies
- Requires: PROJ-18 (Admin: Benutzer & Rollen) — Aktionen werden Benutzern zugeordnet

## Beschreibung
Zentrales Audit-Log für alle relevanten Systemaktionen: Wer hat was wann geändert? Lesbar im Admin als gefilterte Liste. Wichtige Aktionen (Preiänderungen, Rollenwechsel, Kundenlöschungen) werden automatisch geloggt. Dient der Nachvollziehbarkeit und Fehleranalyse.

## User Stories
- Als Admin möchte ich sehen, wer wann eine Kundenadresse geändert hat.
- Als Admin möchte ich alle Aktionen eines bestimmten Benutzers in einem Zeitraum einsehen.
- Als Admin möchte ich nach bestimmten Aktionstypen filtern (z.B. nur Preisänderungen, nur Benutzer-Logins).
- Als Admin möchte ich den Audit-Log nach einem Fehler durchsuchen, um die Ursache zu finden.

## Acceptance Criteria
- [ ] **Audit-Log-Eintrag:** Jeder Eintrag enthält: Datum/Uhrzeit, Benutzer (Name + ID), Aktion (z.B. `customer.address.updated`), betroffenes Objekt (Typ + ID + Name), alte Werte (JSON), neue Werte (JSON), IP-Adresse, Request-ID
- [ ] **Automatisch geloggte Aktionen:**
  - Kundendaten geändert (Name, Adresse, Kundengruppe, Preise)
  - Produkt erstellt/geändert/gelöscht
  - Preis geändert
  - Bestellung-Status geändert
  - Rechnung finalisiert / storniert
  - Benutzer angelegt / Rolle geändert / deaktiviert
  - Login erfolgreich / Login fehlgeschlagen (3x in Folge)
  - Einstellungen geändert
- [ ] **Admin-Liste:** Tabelle: Datum, Benutzer, Aktion, Objekt; Klick auf Zeile zeigt vollständige Details (alte/neue Werte)
- [ ] **Filter:** nach Datum, Benutzer, Aktionstyp, Objekt-Typ
- [ ] **Suche:** Volltext-Suche über Objektname und Notizen
- [ ] **Log-Retention:** Logs werden 24 Monate aufbewahrt; ältere werden automatisch gelöscht (`deferred_tasks`)
- [ ] **Export:** Gefilterte Logs als CSV exportieren
- [ ] **Nicht änderbar:** Audit-Logs dürfen von niemandem (inkl. Admin) gelöscht oder bearbeitet werden — nur durch automatische Retention

## Edge Cases
- Aktion wird von System (kein Benutzer) ausgelöst → `user_id = NULL`; Akteur = „System"
- Log-Eintrag enthält personenbezogene Daten → Nach DSGVO-Anforderung: bei Kundenlöschung werden Kunden-Felder im Log anonymisiert (Name → „Gelöschter Kunde")
- Log-Tabelle wird sehr groß → Indexe auf `created_at`, `user_id`, `action`; Partition nach Monat (optional)

## Technical Requirements
- `audit_logs` Tabelle: `id`, `user_id` (nullable), `action` VARCHAR, `subject_type`, `subject_id`, `subject_label`, `old_values JSON`, `new_values JSON`, `ip_address`, `created_at`, `company_id`
- Kein Soft-Delete auf `audit_logs`; Retention via `deferred_tasks` (DELETE WHERE created_at < NOW() - 24 Monate)
- Logging via Laravel Model-Events + `AuditLogger`-Service (kein `laravel-auditing`-Package, eigene einfache Implementierung)
- Indizes: `(company_id, created_at)`, `(user_id)`, `(subject_type, subject_id)`

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)
_To be added by /architecture_

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
