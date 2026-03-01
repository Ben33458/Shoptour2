# PROJ-26: Admin: Aufgabensystem (wiederkehrend, Verantwortliche)

## Status: Planned
**Created:** 2026-03-01
**Last Updated:** 2026-03-01

## Dependencies
- Requires: PROJ-18 (Admin: Benutzer & Rollen) — Aufgaben werden Benutzern zugewiesen

## Beschreibung
Internes Aufgaben- und To-Do-System für Mitarbeiter und Admin. Aufgaben können einmalig oder wiederkehrend (täglich, wöchentlich, monatlich) sein. Verantwortliche werden zugewiesen. Das Admin-Dashboard zeigt offene Aufgaben. Benachrichtigung per Email bei Fälligkeit.

## User Stories
- Als Admin möchte ich Aufgaben anlegen und einem Mitarbeiter zuweisen (z.B. „Montag: Regal auffüllen", „Letzter des Monats: Inventur Kühlhaus").
- Als Admin möchte ich wiederkehrende Aufgaben definieren, die automatisch neu erstellt werden.
- Als Mitarbeiter möchte ich meine offenen Aufgaben sehen und als erledigt markieren.
- Als Admin möchte ich überfällige Aufgaben im Dashboard sehen.
- Als Mitarbeiter möchte ich bei einer fälligen Aufgabe eine Email-Benachrichtigung erhalten.
- Als Admin möchte ich die Aufgabenhistorie (wann erledigt, von wem) einsehen.

## Acceptance Criteria
- [ ] **Aufgabe anlegen:** Titel (Pflicht), Beschreibung (optional), Fälligkeitsdatum, Verantwortlicher (Benutzer oder Rolle), Priorität (niedrig/mittel/hoch), Wiederholung (keine/täglich/wöchentlich/monatlich/jährlich)
- [ ] **Aufgaben-Liste (Admin):** Alle Aufgaben; Filter nach Status (offen/überfällig/erledigt), Verantwortlichem, Priorität; Sortierung nach Fälligkeit
- [ ] **Meine Aufgaben (Mitarbeiter):** Nur eigene Aufgaben; gefiltert nach Fälligkeit; offene zuerst
- [ ] **Aufgabe erledigen:** Status auf „erledigt" setzen; Erledigt-Zeitstempel und -Benutzer gespeichert; optionale Abschlussnotiz
- [ ] **Wiederkehrende Aufgaben:** Wenn erledigt → neue Aufgabe mit nächstem Fälligkeitsdatum erstellt (täglich +1 Tag, wöchentlich +7 Tage, etc.); via `deferred_tasks` Cron-Job
- [ ] **Überfällig-Markierung:** Aufgaben, deren Fälligkeitsdatum in der Vergangenheit liegt und die nicht erledigt sind, werden rot markiert
- [ ] **Dashboard-Widget:** Anzahl offener / überfälliger Aufgaben; Link zur Aufgabenliste
- [ ] **Email-Benachrichtigung:** Am Fälligkeitstag morgens 7 Uhr Email an Verantwortlichen; via `deferred_tasks`
- [ ] **Aufgabe löschen:** Nur wenn Status „offen"; mit Bestätigung; abgeschlossene Aufgaben bleiben als Archiv

## Edge Cases
- Verantwortlicher wird deaktiviert, hat aber offene Aufgaben → Aufgaben bleiben bestehen; Admin-Warnung; Neuzuweisung nötig
- Wiederkehrende Aufgabe wird gelöscht → Keine weiteren Instanzen; bestehende offene Instanz bleibt
- Aufgabe wird als erledigt markiert, aber Fälligkeit ist morgen → Erlaubt; gilt trotzdem als rechtzeitig erledigt
- Mehrere Benutzer haben die gleiche Aufgabe in ihrer Liste (Rolle zugewiesen) → Wer zuerst erledigt, schließt die Aufgabe für alle

## Technical Requirements
- `tasks` Tabelle: `id`, `title`, `description`, `due_date`, `assigned_user_id`, `assigned_role` (nullable), `priority ENUM`, `recurrence ENUM`, `status ENUM(open|done|cancelled)`, `completed_at`, `completed_by`, `parent_task_id` (für Wiederholungsreihe), `company_id`
- Cron via `deferred_tasks`: täglich prüfen, ob wiederkehrende abgeschlossene Aufgaben neue Instanzen brauchen
- Email via `deferred_tasks`: Fälligkeitstag-Benachrichtigungen

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur (UI-Baum)

```
/admin/aufgaben/
│
├── index (Admin-Ansicht: Alle Aufgaben)
│   ├── Filter: Status (offen/überfällig/erledigt) | Verantwortlicher | Priorität
│   ├── Tabelle: Titel | Fälligkeit | Priorität | Verantwortlicher | Status
│   │   └── Überfällige Zeilen rot hervorgehoben
│   └── [Neue Aufgabe] → Modal
│
├── create/edit → Modal / Drawer
│   ├── Titel (Pflicht), Beschreibung
│   ├── Fälligkeitsdatum (Datepicker)
│   ├── Verantwortlicher (Benutzer-Dropdown oder Rolle)
│   ├── Priorität (niedrig / mittel / hoch — farbcodiert)
│   └── Wiederholung (keine / täglich / wöchentlich / monatlich / jährlich)
│
└── Meine Aufgaben (alle Mitarbeiter — im persönlichen Dashboard-Widget)
    ├── Nur eigene offene Aufgaben; sortiert nach Fälligkeit
    └── [Erledigt markieren] → Abschluss-Notiz optional

Dashboard-Widget:
├── „X offene Aufgaben (Y überfällig)"
└── Link → /admin/aufgaben/
```

### Datenmodell

```
tasks
├── id, title, description (nullable)
├── due_date (DATE)
├── assigned_user_id → users (nullable)
├── assigned_role    VARCHAR nullable  ← Alternativ: Rolle statt Person
├── priority  ENUM: low | medium | high
├── recurrence ENUM: none | daily | weekly | monthly | yearly
├── status    ENUM: open | done | cancelled
├── completed_at, completed_by → users (nullable)
├── completion_notes (nullable)
├── parent_task_id → tasks (nullable)  ← für Wiederholungsreihen
└── company_id
```

### Wiederkehrende Aufgaben

```
Wenn Aufgabe mit recurrence != 'none' als 'done' markiert:
  → TaskRecurrenceService::createNext($task):
      next_due_date = due_date + recurrence_interval
      Neue Aufgabe mit gleichen Feldern + parent_task_id = $task.id
      (wird via deferred_task getriggert, nicht synchron)
```

### Email-Benachrichtigung

```
Täglicher deferred_task (früh morgens):
  → DunningNotificationService::sendDueTasks()
  → Prüft: tasks WHERE due_date = TODAY AND status = 'open'
  → Sendet Email an assigned_user (oder alle Nutzer der assigned_role)
  → Kein Throttling nötig: pro Tag max. 1 Email je Aufgabe
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| Aufgaben an Rolle oder Person | Wenn Stelle vakant ist, können alle Inhaber der Rolle sehen; flexibler |
| `parent_task_id` für Wiederholungsreihen | Reihe nachverfolgbar; ursprüngliche Aufgabe ist immer erkennbar |
| Kein separates Task-Queue-System | Einfacher `deferred_tasks`-Ansatz reicht für regionalen Betrieb |
| Modal/Drawer für Anlage | Kontextsensitiv; Admin verlässt die Liste nicht |

### Neue Controller / Services

```
Admin\AufgabeController         ← index, store, update, destroy, complete
TaskRecurrenceService          ← createNext($task)
TaskDueNotificationJob         ← via deferred_tasks, täglich
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
