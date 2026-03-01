# PROJ-29: Admin: Emails & Support (Posteingang, Tickets)

## Status: Planned
**Created:** 2026-03-01
**Last Updated:** 2026-03-01

## Dependencies
- Requires: PROJ-10 (Admin: Kundenverwaltung) — Emails werden Kunden zugeordnet
- Requires: PROJ-19 (Admin: Einstellungen) — SMTP/IMAP-Konfiguration

## Beschreibung
Einfaches internes Support-System: Eingehende Kunden-Emails (über ein Postfach wie `auftrag@kolabri.de`) werden im Admin als Tickets angezeigt. Mitarbeiter können antworten. Tickets können Kunden zugeordnet und mit Status versehen werden. Kein vollständiges Helpdesk-System — nur ein einfacher strukturierter Posteingang.

## User Stories
- Als Mitarbeiter möchte ich alle eingehenden Kunden-Emails in einer zentralen Liste sehen.
- Als Mitarbeiter möchte ich eine Email direkt aus dem Admin-Bereich beantworten.
- Als Mitarbeiter möchte ich ein Ticket einem Kunden zuordnen, damit ich den Kontext der Beziehung sehe.
- Als Mitarbeiter möchte ich ein Ticket als „erledigt" markieren.
- Als Admin möchte ich offene Tickets im Dashboard sehen.
- Als Mitarbeiter möchte ich Tickets intern einem Kollegen zuweisen.

## Acceptance Criteria
- [ ] **Email-Posteingang:** Emails von konfiguriertem IMAP-Postfach werden per Polling (via `deferred_tasks`) abgerufen und als Tickets gespeichert; Polling alle 5 Minuten
- [ ] **Ticket-Liste:** Betreff, Absender, Empfangsdatum, Status (offen/in Bearbeitung/erledigt), zugeordneter Benutzer; Filter nach Status, Bearbeiter; ungelesene oben
- [ ] **Ticket-Detail:** Email-Thread (alle Antworten); Kundeninfo wenn zugeordnet (Bestellhistorie, offene Rechnungen)
- [ ] **Antwort senden:** Antwort direkt aus Ticket-Detail; geht als Email an Absender; Antwort wird im Thread gespeichert
- [ ] **Kunden-Zuordnung:** Ticket mit Kunden verknüpfen (Suche nach Email oder Name)
- [ ] **Status-Management:** `offen` → `in_bearbeitung` → `erledigt`; Wiederöffnung bei neuer Antwort automatisch
- [ ] **Zuweisung:** Ticket einem Mitarbeiter zuweisen; zugewiesener Mitarbeiter sieht Ticket in „Meine Tickets"
- [ ] **Dashboard-Widget:** Anzahl offener / unzugewiesener Tickets
- [ ] **Interne Notizen:** Mitarbeiter können interne Notizen zu einem Ticket schreiben (nicht an Kunden gesendet)
- [ ] **Anhänge:** Eingehende Anhänge werden gespeichert und im Ticket angezeigt; Anhänge können auch beim Antworten mitgeschickt werden

## Edge Cases
- Email-Abruf schlägt fehl (IMAP-Verbindung) → Fehler wird geloggt; nächster Versuch beim nächsten Polling-Zyklus
- Gleiche Email wird doppelt empfangen (Message-ID-Duplikat) → Duplikat wird ignoriert (Message-ID als Unique-Schlüssel)
- Antwort eines Kunden auf bestehendes Ticket → Wird dem bestehenden Ticket-Thread zugeordnet (via In-Reply-To Header oder Betreff-Matching)
- Ticket-Bearbeiter verlässt das Unternehmen → Tickets bleiben bestehen; Admin kann Neuzuweisung machen

## Technical Requirements
- `support_tickets` Tabelle: `id`, `subject`, `from_email`, `from_name`, `message_id` (unique), `customer_id` (nullable), `assigned_user_id` (nullable), `status ENUM`, `company_id`
- `support_ticket_messages` Tabelle: `ticket_id`, `direction ENUM(inbound|outbound)`, `body_html`, `body_text`, `is_internal_note`, `user_id` (nullable), `created_at`
- IMAP-Polling via `deferred_tasks`; `PHP IMAP`-Erweiterung oder `webklex/php-imap`-Bibliothek
- Email-Antwort via SMTP (konfiguriert in PROJ-19 Einstellungen)

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)
_To be added by /architecture_

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
