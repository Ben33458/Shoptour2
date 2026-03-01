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

### Komponenten-Struktur (UI-Baum)

```
/admin/newsletter/
│
├── index                   ← Versendete Newsletter (Archiv)
│   ├── Liste: Betreff | Datum | Empfänger | Status
│   └── [Neuer Newsletter]
│
├── create                  ← Newsletter erstellen
│   ├── Betreff, Absender-Name
│   ├── Empfänger: Gruppen-Multiselect + Empfängeranzahl-Vorschau
│   ├── WYSIWYG HTML-Editor (Inhalt)
│   ├── Anhänge (max. 3 Dateien)
│   ├── [Test-Versand] → Modal (eigene Email eingeben)
│   └── [Newsletter versenden] → Bestätigungs-Dialog
│
├── gruppen/                ← Newsletter-Gruppen
│   ├── index (Name, Beschreibung, Anzahl Mitglieder)
│   └── create / edit
│
└── {id}/                   ← Newsletter-Detail (nach Versand)
    ├── Betreff, Inhalt (readonly)
    ├── Versandstatistik: Gesendet / Fehlgeschlagen
    └── [Erneut senden] (Fehlgeschlagene)

/konto/newsletter           ← Kundenkonto-Selbstverwaltung
└── Switch: Newsletter abonnieren / abmelden
```

### Datenmodell

```
newsletter_groups
├── id, name, description
└── company_id

newsletter_group_members  [Pivot]
├── group_id → newsletter_groups
└── customer_id → customers

newsletters
├── id, subject, from_name
├── body_html, body_text
├── status ENUM: draft | sending | sent
├── sent_at, total_recipients, sent_count, failed_count
└── company_id

newsletter_sends  [Je Empfänger]
├── newsletter_id, email
├── status ENUM: pending | sent | failed
└── sent_at (nullable)

customers  [erweitert]
└── newsletter_opt_out BOOL (DEFAULT FALSE)

newsletter_unsubscribe_tokens  [Abmelde-Link]
├── customer_id, token (hashed, unique)
└── created_at  (kein expires_at — dauerhaft gültig)
```

### Versand-Ablauf

```
Admin klickt [Versenden]:
  1. Empfänger bestimmen: newsletter_group_members WHERE NOT opt_out
  2. newsletter_sends-Einträge erstellen (status=pending)
  3. deferred_task erstellen: „newsletter_send_{id}"
  4. Weiterleitung mit Toast „Versand wird vorbereitet"

deferred_task (via Cron):
  Verarbeitung in Batches à 100 Emails
  Je Email: SMTP-Versand + Abmelde-Link anhängen
  → newsletter_sends.status = sent | failed
  → newsletters.sent_count / failed_count aktualisieren
```

### Abmelde-Mechanismus

```
Footer jeder Email:
  [Vom Newsletter abmelden]
  → /newsletter/abmelden/{token}

Laravel-Route (ohne Auth):
  1. Token validieren → customer_id ermitteln
  2. customers.newsletter_opt_out = true
  3. Alle newsletter_group_members für diesen Kunden löschen
  4. Bestätigungsseite anzeigen
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| Eigenes System (kein Mailchimp) | Kundendaten bleiben intern; DSGVO einfacher; kein monatliches Abo |
| Batched SMTP-Versand | Shared-Hosting-SMTP hat Limits; Batches verhindern Blacklisting |
| Permanente Abmelde-Token | Keine Ablaufzeit = kein „Link abgelaufen"-Problem |
| Test-Versand vor Massenversand | Verhindert fehlerhafte Emails an alle Kunden |

### Neue Controller / Services

```
Admin\NewsletterController          ← index, create, store, show, resend
Admin\NewsletterGruppeController    ← CRUD Gruppen + Mitglieder-Verwaltung
Shop\NewsletterAbmeldeController    ← show (Abmelde-Bestätigung), store (Abmeldung)
Shop\NewsletterPraeferenzController ← update (Kundenkonto-Einstellung)
NewsletterDispatchService          ← buildRecipients(), dispatchBatch()
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
