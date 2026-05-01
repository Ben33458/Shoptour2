# 03 – Vorgeschlagenes MVP-Datenmodell

## 1. employees

| Feld | Typ | Zweck |
|---|---|---|
| id | bigint | PK |
| first_name | string | Vorname |
| last_name | string | Nachname |
| email | string nullable | Login / Kontakt |
| phone | string nullable | optional |
| is_active | boolean | aktiv/inaktiv |
| supervisor_id | bigint nullable | optionaler Vorgesetzter -> employees.id |
| weekly_target_minutes | integer nullable | Sollzeit |
| employment_start_date | date nullable | optional |
| employment_end_date | date nullable | optional |
| created_at | timestamp |  |
| updated_at | timestamp |  |

## 2. roles

| Feld | Typ | Zweck |
|---|---|---|
| id | bigint | PK |
| key | string unique | z. B. admin, planner, driver |
| name | string | Anzeigename |
| created_at | timestamp |  |
| updated_at | timestamp |  |

## 3. employee_role

| Feld | Typ | Zweck |
|---|---|---|
| employee_id | bigint | FK |
| role_id | bigint | FK |
| created_at | timestamp |  |

## 4. shift_areas

| Feld | Typ | Zweck |
|---|---|---|
| id | bigint | PK |
| key | string unique | market, delivery, support |
| name | string | Anzeigename |
| created_at | timestamp |  |
| updated_at | timestamp |  |

## 5. shifts

Aus Dienstplan erzeugte konkrete Schichten.

| Feld | Typ | Zweck |
|---|---|---|
| id | bigint | PK |
| employee_id | bigint | FK |
| shift_area_id | bigint | FK |
| shift_type_key | string nullable | z. B. market_open, market_close |
| starts_at | datetime | geplanter Start |
| ends_at | datetime | geplantes Ende |
| status | string | planned, active, completed, incomplete, reviewed, locked |
| created_from_template_id | bigint nullable | optional |
| notes | text nullable | Planungsnotiz |
| created_at | timestamp |  |
| updated_at | timestamp |  |

## 6. shift_templates

Regelmäßige feste Schichten.

| Feld | Typ | Zweck |
|---|---|---|
| id | bigint | PK |
| employee_id | bigint | FK |
| shift_area_id | bigint | FK |
| shift_type_key | string nullable | Typ |
| weekday | tinyint | 1-7 |
| start_time | time |  |
| end_time | time |  |
| valid_from | date nullable |  |
| valid_until | date nullable |  |
| is_active | boolean |  |
| created_at | timestamp |  |
| updated_at | timestamp |  |

## 7. time_entries

Eine Zeitbuchung pro Schicht.

| Feld | Typ | Zweck |
|---|---|---|
| id | bigint | PK |
| shift_id | bigint | FK |
| employee_id | bigint | FK |
| clock_in_at | datetime nullable | tatsächliches Einstempeln |
| clock_out_at | datetime nullable | tatsächliches oder automatisches Ausstempeln |
| clock_out_source | string nullable | manual, auto_planned_end, auto_12h_guard |
| worked_minutes_raw | integer nullable | rohe Arbeitszeit |
| pause_minutes_recorded | integer default 0 | echte Pause |
| pause_minutes_required | integer default 0 | gesetzlich erforderlich |
| pause_minutes_auto_deducted | integer default 0 | automatisch korrigiert |
| worked_minutes_payroll | integer nullable | für Auswertung/Abrechnung |
| compliance_status | string nullable | ok, warning, violation |
| requires_admin_review | boolean default false | Kennzeichnung |
| auto_closed_by_system | boolean default false | Kennzeichnung |
| created_at | timestamp |  |
| updated_at | timestamp |  |

## 8. break_entries

| Feld | Typ | Zweck |
|---|---|---|
| id | bigint | PK |
| time_entry_id | bigint | FK |
| started_at | datetime |  |
| ended_at | datetime nullable |  |
| duration_minutes | integer nullable | berechnet |
| created_at | timestamp |  |
| updated_at | timestamp |  |

## 9. time_entry_change_requests

Für manuelle Nachträge und Korrekturen.

| Feld | Typ | Zweck |
|---|---|---|
| id | bigint | PK |
| time_entry_id | bigint | FK |
| requested_by_employee_id | bigint | FK |
| approved_by_employee_id | bigint nullable | FK |
| reason | text | Pflicht |
| payload_before | json nullable | alter Stand |
| payload_after | json | gewünschter neuer Stand |
| status | string | pending, approved, rejected |
| decided_at | datetime nullable |  |
| created_at | timestamp |  |
| updated_at | timestamp |  |

## 10. shift_reports

| Feld | Typ | Zweck |
|---|---|---|
| id | bigint | PK |
| shift_id | bigint | FK |
| employee_id | bigint | FK |
| summary_text | longtext nullable | Was wurde erledigt |
| incidents_text | longtext nullable | besondere Vorkommnisse |
| cash_count_done | boolean nullable | Kassensturz |
| cash_difference_exists | boolean nullable | Abweichung |
| cash_difference_amount | decimal(10,2) nullable | Betrag |
| glass_break_occurred | boolean nullable | Glasbruch |
| attachment_path | string nullable | optionaler Fotobeleg |
| status | string | open, incomplete, complete, reviewed, locked |
| reviewed_by_employee_id | bigint nullable | FK |
| reviewed_at | datetime nullable |  |
| created_at | timestamp |  |
| updated_at | timestamp |  |

## 11. checklist_templates

Schichttyp-/bereichsabhängige Checklisten-Vorlagen.

| Feld | Typ | Zweck |
|---|---|---|
| id | bigint | PK |
| shift_area_id | bigint nullable | optional |
| shift_type_key | string nullable | optional |
| label | string | Text |
| sort_order | integer | Reihenfolge |
| is_required | boolean default true | Pflichtpunkt |
| is_active | boolean default true |  |
| created_at | timestamp |  |
| updated_at | timestamp |  |

## 12. shift_report_checklist_items

Konkrete Haken im Schichtbericht.

| Feld | Typ | Zweck |
|---|---|---|
| id | bigint | PK |
| shift_report_id | bigint | FK |
| checklist_template_id | bigint | FK |
| label_snapshot | string | Text zum Zeitpunkt |
| is_required | boolean | Snapshot |
| is_checked | boolean default false |  |
| checked_at | datetime nullable |  |
| created_at | timestamp |  |
| updated_at | timestamp |  |

## 13. recurring_tasks

Bestehende Tabelle konzeptionell, hier nur ergänzt, nicht neu erfunden.

| Feld | Typ | Zweck |
|---|---|---|
| id | bigint | PK |
| title | string |  |
| description | text nullable |  |
| shift_area_id | bigint nullable | Bereich |
| recurrence_type | string | hourly, daily, weekly, monthly, quarterly, yearly, custom |
| recurrence_interval | integer default 1 | z. B. alle 2 Wochen |
| recurrence_anchor_mode | string | after_completion, calendar_fixed |
| due_rule_payload | json nullable | flexible Regeln |
| is_active | boolean |  |
| created_at | timestamp |  |
| updated_at | timestamp |  |

## 14. tasks

Offene Aufgaben.

| Feld | Typ | Zweck |
|---|---|---|
| id | bigint | PK |
| recurring_task_id | bigint nullable | Ursprung |
| created_by_employee_id | bigint nullable | manuell angelegt von |
| assigned_employee_id | bigint nullable | optional |
| shift_area_id | bigint nullable | Bereich |
| title | string |  |
| description | text nullable |  |
| task_kind | string | mandatory_today, recurring_flexible, one_off |
| priority | string | low, normal, high, critical |
| due_at | datetime nullable | Fälligkeit |
| must_be_done_today | boolean default false | für harte Tagespflichten |
| status | string | open, in_progress, done, skipped, overdue |
| completed_by_employee_id | bigint nullable | FK |
| completed_at | datetime nullable |  |
| points_awarded | integer default 0 | Gamification |
| created_at | timestamp |  |
| updated_at | timestamp |  |

## 15. vacations

| Feld | Typ | Zweck |
|---|---|---|
| id | bigint | PK |
| employee_id | bigint | FK |
| from_date | date |  |
| to_date | date |  |
| employee_note | text nullable | Freitext |
| is_reachable_in_emergency | boolean nullable | freiwillig |
| replacement_employee_id | bigint nullable | optional |
| replacement_confirmed | boolean nullable | optional |
| status | string | requested, approved, rejected, canceled |
| admin_note | text nullable | Entscheidung/Kommentar |
| entered_in_lexoffice_payroll | boolean default false | nur Admin/HR |
| decided_by_employee_id | bigint nullable | FK |
| decided_at | datetime nullable |  |
| created_at | timestamp |  |
| updated_at | timestamp |  |

## 16. holidays

Hessen-Feiertage als Tabelle.

| Feld | Typ | Zweck |
|---|---|---|
| id | bigint | PK |
| date | date unique |  |
| name | string | Feiertagsname |
| state_code | string | HE |
| is_public_holiday | boolean default true |  |
| market_open_default | boolean default false | Defaultlogik |
| delivery_allowed_default | boolean default true | Fahrerschichten möglich |
| notes | text nullable |  |
| created_at | timestamp |  |
| updated_at | timestamp |  |

## 17. achievements

| Feld | Typ | Zweck |
|---|---|---|
| id | bigint | PK |
| key | string unique |  |
| name | string |  |
| description | text nullable |  |
| icon | string nullable | optional |
| is_active | boolean |  |
| created_at | timestamp |  |
| updated_at | timestamp |  |

## 18. employee_achievements

| Feld | Typ | Zweck |
|---|---|---|
| id | bigint | PK |
| employee_id | bigint | FK |
| achievement_id | bigint | FK |
| awarded_at | datetime |  |
| meta | json nullable | Zusatzdaten |
| created_at | timestamp |  |
| updated_at | timestamp |  |

## 19. system_logs

Zentrale revisionssichere Logtabelle.

| Feld | Typ | Zweck |
|---|---|---|
| id | bigint | PK |
| category | string | time_tracking, shift_report, task, vacation, schedule, cash, auth, system |
| event_type | string | create, update, approve, reject, auto_close, cron_generate, etc. |
| actor_employee_id | bigint nullable | wer |
| object_type | string | Modellname |
| object_id | bigint nullable | betroffener Datensatz |
| is_automatic | boolean default false | Systemaktion |
| before_payload | json nullable | Altwert |
| after_payload | json nullable | Neuwert |
| reason | text nullable | Begründung |
| created_at | timestamp | Ereigniszeit |

## Technische Hinweise
- Keine Felder still überschreiben, wenn Revisionssicherheit betroffen ist.
- Kritische Werte zusätzlich loggen.
- Statuswerte als Enums oder konstante Value Objects umsetzen.
- Für bestehende Aufgabenlogik Migrationen vorsichtig planen, nicht blind alles umbauen.

