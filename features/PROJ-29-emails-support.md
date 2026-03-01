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

### Komponenten-Struktur (UI-Baum)

```
/admin/support/
│
├── index                   ← Ticket-Liste
│   ├── Filter: Status (offen / in Bearbeitung / erledigt) | Bearbeiter | ungelesen
│   ├── Tabelle: Betreff | Absender | Eingegangen | Status | Bearbeiter
│   └── Ungelesene Tickets oben / fett hervorgehoben
│
└── {id}/                   ← Ticket-Detail
    ├── Kundenzuordnung (Suche + Verknüpfung)
    ├── Kundeninfo-Panel (wenn verknüpft): letzte Bestellungen, offene Rechnungen
    ├── Bearbeiter-Zuweisung (Dropdown)
    ├── Status-Änderung
    ├── Thread-Ansicht
    │   ├── Eingehende Emails (blau unterlegt)
    │   ├── Gesendete Antworten (grün unterlegt)
    │   └── Interne Notizen (gelb unterlegt — nicht an Kunden)
    ├── Antwort-Box (Textarea) + Anhänge
    └── [Antwort senden] [Interne Notiz speichern]
```

### Datenmodell

```
support_tickets
├── id, subject, from_email, from_name
├── message_id   VARCHAR UNIQUE  ← Email Message-ID (Duplikat-Schutz)
├── customer_id  → customers (nullable)
├── assigned_user_id → users (nullable)
├── status  ENUM: open | in_progress | done
├── unread  BOOL (DEFAULT TRUE)
└── company_id

support_ticket_messages
├── id, ticket_id → support_tickets
├── direction  ENUM: inbound | outbound
├── is_internal_note  BOOL
├── body_html, body_text
├── from_email, from_name (für inbound)
├── user_id → users (nullable)  ← wer hat geantwortet?
└── created_at

support_ticket_attachments
├── ticket_message_id → support_ticket_messages
├── filename, path, mime_type, size
└── company_id
```

### IMAP-Polling

```
deferred_task: EmailFetchJob (alle 5 Minuten via Cron):
  1. IMAP-Verbindung herstellen (aus Einstellungen PROJ-19)
  2. Ungelesene Emails abrufen
  3. Je Email:
     a. Message-ID → Duplikat? → Überspringen
     b. In-Reply-To vorhanden + passendes Ticket? → Dem Thread zuordnen
     c. Kein Thread → Neues Ticket erstellen
     d. Anhänge in Storage speichern
  4. Emails als gelesen markieren (im IMAP-Postfach)
```

### Thread-Zuordnung (Antwort-Matching)

```
Neue Email eingehend:
  1. Prüfe In-Reply-To Header → suche Message-ID in support_ticket_messages
  2. Gefunden → ticket_id des Eltern-Messages verwenden
  3. Ticket-Status war 'done' → automatisch auf 'open' zurücksetzen
  4. Nicht gefunden → neues Ticket

Alternative: Betreff-Matching (als Fallback):
  Betreff enthält „[Ticket #123]" → Ticket #123 zuordnen
  (Admin-Antworten erhalten diesen Tag automatisch im Betreff)
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| IMAP-Polling (kein Webhook) | Shared-Hosting hat keinen öffentlichen Webhook-Endpunkt; IMAP ist universell |
| `message_id` als Unique-Key | Verhindert Duplikate beim erneuten Polling zuverlässig |
| Interne Notizen | Mitarbeiter können intern kommunizieren ohne Kunden-Email |
| Automatisches Wiederöffnen | Wenn Kunde antwortet auf erledigtes Ticket, geht es nicht unter |

### Neue Controller / Services

```
Admin\SupportTicketController        ← index, show, update (Status/Bearbeiter)
Admin\SupportTicketMessageController ← store (Antwort/Notiz), destroy
Admin\SupportTicketKundeController   ← update (Kundenzuordnung)
EmailFetchService                   ← fetchNewEmails(), processEmail()
EmailFetchJob                       ← via deferred_tasks, alle 5 Minuten
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
