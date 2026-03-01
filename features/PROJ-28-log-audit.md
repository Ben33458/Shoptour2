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

### Komponenten-Struktur (UI-Baum)

```
/admin/audit/
│
└── index                   ← Audit-Log-Liste
    ├── Filter-Panel
    │   ├── Datum (Von / Bis)
    │   ├── Benutzer (Dropdown)
    │   ├── Aktionstyp (Dropdown: z.B. customer.updated, price.changed)
    │   └── Objekt-Typ (Dropdown: Kunde / Produkt / Bestellung / ...)
    │
    ├── Tabelle: Datum | Benutzer | Aktion | Objekt | Details
    │   └── Zeile klickbar → Expandable Row (alte Werte / neue Werte als JSON-Diff)
    │
    └── [Als CSV exportieren] (gefilterte Ansicht)
```

### Datenmodell

```
audit_logs  [append-only, kein Update/Delete möglich]
├── id
├── user_id     → users (nullable)   ← NULL = System
├── action      VARCHAR  (z.B. „customer.address.updated")
├── subject_type VARCHAR  (z.B. „Customer")
├── subject_id   INT
├── subject_label VARCHAR  (Name-Snapshot zum Zeitpunkt der Aktion)
├── old_values  JSON (nullable)
├── new_values  JSON (nullable)
├── ip_address  VARCHAR
├── created_at
└── company_id

Indizes: (company_id, created_at DESC), (user_id), (subject_type, subject_id)
```

### Logging-Mechanismus

```
AuditLogger::log($action, $subject, $oldValues, $newValues):
  → Erstellt audit_logs-Eintrag
  → Aufruf an strategischen Punkten (nicht via Model-Observer,
    da gezieltes Logging wichtiger ist als automatisches)

Automatisch geloggte Aktionen:
  customer.*    → CustomerController (update, destroy)
  price.*       → PreisController (store, update, destroy)
  invoice.*     → InvoiceService (finalize, cancel)
  user.*        → UserController (store, update, toggleActive)
  order.status  → BestellungController (updateStatus)
  auth.login    → AuthController (success, failed)
  settings.*    → EinstellungenController (update)
```

### DSGVO-Anonymisierung

```
Wenn Kunde gelöscht wird (CustomerService::delete):
  → Suche alle audit_logs WHERE subject_type='Customer' AND subject_id=$id
  → UPDATE: subject_label = „Gelöschter Kunde #$id"
  → UPDATE: old_values / new_values: name/email-Felder anonymisieren
  → audit_logs bleiben erhalten (für Buchungsnachvollziehbarkeit)
```

### Retention-Job

```
Monatlicher deferred_task:
  DELETE FROM audit_logs WHERE created_at < NOW() - INTERVAL 24 MONTH
  → Kein vollständiges Truncate; nur Zeilen älter als 2 Jahre
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| Gezieltes Logging (kein Auto-Observer) | Verhindert Log-Spam durch interne Berechnungen; nur geschäftsrelevante Änderungen |
| Append-only (kein Delete durch Admin) | Audit-Logs verlieren ihren Wert wenn sie gelöscht werden können |
| JSON-Diff in Detail-Ansicht | Admin sieht auf einen Blick was sich geändert hat; kein Vergleich-Tool nötig |
| Retention 24 Monate | Ausreichend für Steuerprüfung (6-10 Jahre für Buchungen via Rechnungen abgedeckt) |

### Neue Controller / Services

```
Admin\AuditLogController     ← index (gefilterte Liste + Export)
AuditLogger                 ← log($action, $subject, $old, $new)
AuditRetentionJob           ← via deferred_tasks, monatlich
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
