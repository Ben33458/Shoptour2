# PROJ-27: Admin: Newsletter (Gruppen, Abmeldung, Selbstverwaltung)

## Status: Planned
**Created:** 2026-03-01
**Last Updated:** 2026-03-01

## Dependencies
- Requires: PROJ-10 (Admin: Kundenverwaltung) — Empfänger sind Kunden
- Requires: PROJ-19 (Admin: Einstellungen) — SMTP-Konfiguration für Email-Versand

## Beschreibung
Einfaches internes Newsletter-System: Kunden können Newsletter-Gruppen zugeordnet werden. Admin erstellt und versendet Newsletter (HTML oder Text). Kunden können sich über einen Link abmelden (DSGVO). Keine externe Newsletter-Plattform nötig.

## User Stories
- Als Admin möchte ich Newsletter-Gruppen anlegen (z.B. „Gastronomie", „Privathaushalt", „Alle").
- Als Admin möchte ich Kunden Newsletter-Gruppen zuordnen.
- Als Admin möchte ich einen Newsletter erstellen (Betreff, HTML-Inhalt oder Textinhalt) und an eine oder mehrere Gruppen versenden.
- Als Admin möchte ich eine Vorschau des Newsletters sehen, bevor ich ihn versende.
- Als Admin möchte ich den Versandstatus sehen (wie viele emails gesendet/fehlgeschlagen).
- Als Kunde möchte ich mich über einen Link im Newsletter abmelden können (DSGVO-konform).
- Als Kunde möchte ich in meinem Kundenkonto Newsletter-Präferenzen selbst verwalten.

## Acceptance Criteria
- [ ] **Newsletter-Gruppen CRUD:** Name, Beschreibung; Kunden können mehreren Gruppen angehören
- [ ] **Kunden-Zuordnung:** In Kundenverwaltung: Kunden Newsletter-Gruppen zuordnen; Massenbearbeitung (mehrere Kunden auf einmal)
- [ ] **Newsletter erstellen:** Betreff, Absender-Name, HTML-Inhalt (WYSIWYG-Editor) oder Nur-Text; Anhänge (optional, max. 3 Dateien)
- [ ] **Empfänger auswählen:** Eine oder mehrere Newsletter-Gruppen; Vorschau der Empfängeranzahl
- [ ] **Test-Versand:** Newsletter an eigene Email senden (Vorschau ohne Massenversand)
- [ ] **Versand:** Emails über `deferred_tasks` in Batches (100 pro Durchgang); Versandstatus je Newsletter: gesamt / gesendet / fehlgeschlagen
- [ ] **Abmelde-Link:** Jede Email enthält einzigartigen Abmelde-Link; Klick trägt Kunden aus allen Newsletter-Gruppen aus und setzt `newsletter_opt_out = true`
- [ ] **Kundenkonto-Selbstverwaltung:** Eingeloggter Kunde kann in seinem Profil Newsletter-Abonnement an- oder abmelden
- [ ] **Opt-Out respektieren:** Kunden mit `newsletter_opt_out = true` erhalten keine Newsletter, auch wenn manuell zugeordnet
- [ ] **Newsletter-Archiv:** Liste versendeter Newsletter mit Datum, Empfängeranzahl, Status; letzter Newsletter immer einsehbar

## Edge Cases
- Keine Empfänger in gewählter Gruppe (alle haben opt_out) → Warnung vor Versand; Versand trotzdem möglich (0 Emails)
- Versand schlägt für einzelne Adressen fehl → Fehlgeschlagene Adressen werden protokolliert; Versand läuft weiter
- Abmelde-Link wird mehrfach geklickt → Idempotent; kein Fehler, nur Bestätigungsmeldung
- Kunde wird gelöscht, war in Newsletter-Gruppe → Newsletter-Gruppe-Eintrag wird mitgelöscht; versendete Newsletter bleiben im Archiv

## Technical Requirements
- `newsletter_groups`, `newsletter_group_members` (customer_id, group_id)
- `newsletters` Tabelle: Betreff, HTML-Body, Text-Body, Status, sent_at, company_id
- `newsletter_sends` Tabelle: newsletter_id, email, status (sent|failed), sent_at
- `customers.newsletter_opt_out` BOOLEAN (DEFAULT FALSE)
- Abmelde-Token: signed URL mit Kunden-Hash (kein Login nötig)
- WYSIWYG: einfacher HTML-Editor (z.B. Quill oder TipTap im Admin-Frontend)

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)
_To be added by /architecture_

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
