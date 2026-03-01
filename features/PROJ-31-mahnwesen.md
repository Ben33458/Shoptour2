# PROJ-31: Mahnwesen (automatisch, Zahlungserinnerungen, Kontoübersicht)

## Status: Planned
**Created:** 2026-03-01
**Last Updated:** 2026-03-01

## Dependencies
- Requires: PROJ-13 (Admin: Rechnungen) — Offene Rechnungen sind Grundlage für Mahnungen
- Requires: PROJ-19 (Admin: Einstellungen) — Mahnfristen und Email-Vorlagen konfigurierbar

## Beschreibung
Automatisches Mahnwesen: Überfällige Rechnungen lösen nach konfigurierbaren Fristen Zahlungserinnerungen und Mahnungen (1., 2., 3. Mahnung) aus. Emails werden automatisch versandt. Admin kann Mahnungen auch manuell auslösen oder sperren. Kontoübersicht pro Kunde zeigt alle offenen Posten.

## User Stories
- Als System möchte ich nach X Tagen Überfälligkeit automatisch eine Zahlungserinnerung per Email versenden.
- Als System möchte ich nach weiteren Y Tagen automatisch eine 1. Mahnung (mit Mahngebühr) versenden.
- Als Admin möchte ich den Mahnstatus einer Rechnung manuell einsehen und ggf. manuell eine Mahnung auslösen.
- Als Admin möchte ich eine Mahnung für eine bestimmte Rechnung sperren (z.B. Kulanz, Zahlungsvereinbarung).
- Als Admin möchte ich eine Kontoübersicht pro Kunde sehen: alle offenen Rechnungen, Gesamtschuld, Mahnstatus.
- Als Kunde möchte ich in meinem Kundenkonto meine offenen Rechnungen und den Gesamtbetrag sehen.

## Acceptance Criteria
- [ ] **Mahnstatus-Stufen:** `keine` → `erinnerung` → `mahnung_1` → `mahnung_2` → `mahnung_3` → `inkasso`
- [ ] **Automatische Eskalation (via `deferred_tasks`, täglich):**
  - Nach konfigurierter Erinnerungsfrist (z.B. 3 Tage nach Fälligkeit) → Email „Zahlungserinnerung"
  - Nach 1. Mahnfrist (z.B. 7 Tage nach Erinnerung) → Email „1. Mahnung" + Mahngebühr 1 (konfigurierbar)
  - Nach 2. Mahnfrist → Email „2. Mahnung" + Mahngebühr 2
  - Nach 3. Mahnfrist → Email „3. Mahnung" + Mahngebühr 3; Markierung für Admin
- [ ] **Mahngebühren:** Konfigurierbar in Einstellungen (Betrag je Stufe); werden auf nächste Rechnung addiert oder als separate Forderung ausgewiesen
- [ ] **Mahnsperre:** Admin kann `mahnsperre = true` auf Rechnung setzen → Keine automatischen Mahnungen mehr für diese Rechnung; Grund-Notiz Pflichtfeld
- [ ] **Manuelle Mahnung:** Admin kann manuell eine Mahnstufe erhöhen und Email sofort versenden
- [ ] **Kontoübersicht (Admin):** Pro Kunde: alle offenen Rechnungen, Gesamtschuld, Mahnstatus je Rechnung; sortierbar nach Fälligkeit und Mahnstatus
- [ ] **Kontoübersicht (Kundenkonto):** Eingeloggter Kunde sieht seine offenen Rechnungen und Gesamtbetrag
- [ ] **Email-Vorlagen:** Separate Vorlage je Mahnstufe; konfigurierbar in Einstellungen (PROJ-19); enthält: Rechnungsnummer, Betrag, Fälligkeitsdatum, Bankdaten, Mahngebühr
- [ ] **Mahnhistorie:** Pro Rechnung: wann welche Mahnstufe versandt, Zeitstempel, Benutzer (manuell) oder „System"

## Edge Cases
- Rechnung wird bezahlt bevor Mahnung ausgelöst → Keine Mahnung; Mahnstatus bleibt auf `keine`
- Teilzahlung eingetroffen → Mahnung wird weiter gesendet bis Restbetrag = 0; Betrag in Mahnung wird angepasst
- Kunde hat mehrere überfällige Rechnungen → Jede Rechnung wird einzeln gemahnt (nicht zusammengefasst, da verschiedene Fälligkeiten)
- Email-Versand schlägt fehl → Fehler geloggt; nächster Versuch nächsten Tag; Mahnstatus nicht erhöht
- Mahngebühr übersteigt Rechnungsbetrag → Mahngebühr wird trotzdem erhoben; Admin sieht Warnung

## Technical Requirements
- `invoices.dunning_level ENUM(none|reminder|dunning_1|dunning_2|dunning_3|collections)`, `dunning_blocked BOOL`, `dunning_notes`
- `invoice_dunning_history` Tabelle: `invoice_id`, `level`, `sent_at`, `user_id` (nullable = System), `email_sent_to`
- Täglicher `DunningService`-Job via `deferred_tasks`: prüft alle `finalized`-Rechnungen mit offenen Beträgen
- Fristen und Gebühren in `settings`-Tabelle (PROJ-19): `dunning_reminder_days`, `dunning_1_days`, `dunning_fee_1_milli`, etc.

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur (UI-Baum)

```
/admin/mahnwesen/
│
├── index                   ← Kontoübersicht aller Kunden
│   ├── Tabelle: Kunde | Offener Betrag | Höchste Mahnstufe | Älteste überfällige Rechnung
│   ├── Filter: nur Kunden mit offenen Posten | Mahnstufe | Überfällig seit X Tagen
│   └── Klick auf Kunde → Kunden-Mahndetail
│
└── kunden/{id}/            ← Mahndetail je Kunde
    ├── Alle Rechnungen: Nummer | Betrag | Fällig | Mahnstufe | Mahnsperre
    ├── Je Rechnung:
    │   ├── [Mahnstufe manuell erhöhen] → sofortige Email
    │   ├── [Mahnsperre setzen/aufheben] → Notiz Pflichtfeld
    │   └── Mahnhistorie (wann welche Stufe, Zeitstempel, Benutzer/System)
    └── Gesamt: Gesamtschuld, Älteste Fälligkeit

/konto/rechnungen           ← Kundenkonto-Ansicht (PROJ-5)
└── Offene Rechnungen + Gesamtbetrag (readonly, kein Mahnstatus angezeigt)
```

### Datenmodell

```
invoices  [erweitert um Mahnfelder]
├── dunning_level ENUM: none | reminder | dunning_1 | dunning_2 | dunning_3 | collections
├── dunning_last_sent_at  DATETIME (nullable)
├── dunning_blocked       BOOL (DEFAULT FALSE)
└── dunning_block_notes   TEXT (nullable)

invoice_dunning_history  [Mahnprotokoll]
├── id, invoice_id → invoices
├── level       ENUM (gleich wie dunning_level)
├── sent_at     DATETIME
├── email_sent_to VARCHAR
├── user_id     → users (nullable)  ← NULL = automatisch vom System
└── company_id

settings  [erweitert in PROJ-19]
├── dunning_reminder_days INT  (z.B. 3)
├── dunning_1_days INT         (z.B. 10 nach Erinnerung)
├── dunning_2_days INT         (z.B. 7 nach Mahnung 1)
├── dunning_3_days INT         (z.B. 7 nach Mahnung 2)
├── dunning_fee_1_milli INT    (z.B. 500 = 5,00 €)
├── dunning_fee_2_milli INT
└── dunning_fee_3_milli INT
```

### Automatischer Mahnlauf (täglich)

```
DunningService::runDailyCheck() — via deferred_task:

  Für jede finalisierte Rechnung WHERE offener_betrag > 0:
    1. Mahnsperre aktiv? → Überspringen
    2. Fälligkeitsdatum berechnen + aktueller dunning_level:
       Stufen-Logik (Tage nach letzter Aktion):
         none      + X Tage → reminder  → Email „Zahlungserinnerung"
         reminder  + Y Tage → dunning_1 → Email „1. Mahnung" + Gebühr 1
         dunning_1 + Z Tage → dunning_2 → Email „2. Mahnung" + Gebühr 2
         dunning_2 + W Tage → dunning_3 → Email „3. Mahnung" + Gebühr 3
    3. dunning_level aktualisieren
    4. invoice_dunning_history-Eintrag erstellen
    5. Email via SMTP (nicht-blockierend)
```

### Mahngebühren-Buchung

```
Mahngebühr wird als neue Rechnung erstellt (nicht auf bestehende addiert):
  → Neue invoice mit 1 invoice_item (type='dunning_fee')
  → Betrag = settings.dunning_fee_X_milli
  → Verknüpft mit ursprünglicher Rechnung via notes / order_id

Vorteil: Klare Trennung; eigene Rechnungsnummer; Buchung eindeutig
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| Tage-basierte Eskalation | Einfach konfigurierbar; nachvollziehbar für Admin |
| Mahnsperre als explizites Flag | Kulanz-Vereinbarungen müssen dokumentiert sein; nicht stillschweigend |
| Mahngebühr als eigene Rechnung | Buchhalterisch korrekt; klare Trennung von Ursprungsrechnung |
| Mahnhistorie in eigener Tabelle | Vollständiges Protokoll; Originalrechnung nicht aufgebläht |

### Neue Controller / Services

```
Admin\MahnwesenController              ← index (Kontoübersicht), show (Kunden-Detail)
Admin\MahnstufenController             ← store (manuelle Eskalation)
Admin\MahnsperreController             ← store/destroy (Sperre setzen/aufheben)
DunningService                        ← runDailyCheck(), escalate(), sendDunningEmail()
DunningDailyJob                       ← via deferred_tasks, täglich
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
