# PROJ-19: Admin: Einstellungen & Konfiguration

## Status: Planned
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- None (aber alle anderen Features lesen aus den Einstellungen)

## Beschreibung
Zentrale Konfigurationsverwaltung im Admin-Bereich (Dangerzone). Enthält: API-Integrationen (Stripe, PayPal, Lexoffice, Google OAuth), Email-Konfiguration, Zahlungseinstellungen, Mahnwesen-Konfiguration, Email- und PDF-Vorlagen, Standardwerte (Kundengruppe, Firma, Gast-Preisgruppe), Rechnungsnummer-Format.

## User Stories
- Als Admin möchte ich API-Keys und Zugangsdaten für externe Dienste (Stripe, PayPal, Lexoffice, Google) sicher hinterlegen.
- Als Admin möchte ich die Email-Absenderadresse und den SMTP-Server konfigurieren.
- Als Admin möchte ich die Fallback-Email-Adresse für Kunden ohne Email-Adresse hinterlegen.
- Als Admin möchte ich das Mahnwesen konfigurieren (ab wieviel Tage überfällig, automatisch oder manuell, welche Vorlage wann).
- Als Admin möchte ich die Gast-Preisgruppe (für nicht angemeldete Besucher) festlegen.
- Als Admin möchte ich Standard-Kundengruppe und Standard-Firma für neue Registrierungen festlegen.
- Als Admin möchte ich Email- und PDF-Vorlagen (Bestellbestätigung, Rechnung, Mahnung) bearbeiten.
- Als Admin möchte ich das Rechnungsnummer-Format konfigurieren.

## Acceptance Criteria
- [ ] **Firmen-Stammdaten:** Firmenname, **Vorname + Nachname des Inhabers** (optional, für Einzelunternehmen), Adresse, Email, Telefon, USt-ID, Bankverbindung (für Rechnungs-PDF)
  - Wenn Vorname + Nachname gesetzt: Rechnungs-PDF zeigt `Firmenname` + `Vorname Nachname` (z.B. „Kolabri Getränke / Max Mustermann")
  - Wenn nur Firmenname: nur Firmenname im Briefkopf
- [ ] **API-Integrationen:**
  - Stripe: Public Key, Secret Key, Webhook Secret; Test/Live-Modus Toggle
  - PayPal: Client ID, Secret, Webhook ID; Sandbox/Live Toggle
  - Lexoffice: API-Key; Sync aktiviert/deaktiviert
  - Google OAuth: Client ID, Client Secret (Redirect URI auto-generiert)
- [ ] **Email-Konfiguration:** SMTP Host, Port, Username, Password, Absender-Name, Absender-Email; Test-Email versenden
- [ ] **Fallback-Email:** Adresse für Kunden ohne Email (z.B. `auftrag@emailbrief.de`) -> Postversand
- [ ] **Standard-Einstellungen:** Standard-Kundengruppe, Standard-Firma/Company, Gast-Preisgruppe
- [ ] **Mahnwesen:**
  - Modus: **Automatisch** (benötigt Cron/cron-job.org) oder **Manuell** (Admin löst per Button aus)
  - Zahlungsziel (Tage nach Rechnungsdatum)
  - 1. Erinnerung: X Tage nach Fälligkeit, Vorlage
  - 2. Mahnung: Y Tage nach 1. Erinnerung, Vorlage
  - 3. Mahnung: Z Tage nach 2. Mahnung, Vorlage
  - 4. Inkasso: automatische Weitergabe an Inkasso Unternehmen, nach bestätigung durch Admin
  - _Hinweis: Automatischer Modus erfordert Cron-Job oder externen Cron-Service (z.B. cron-job.org)_
- [ ] **Rechnungsnummer-Format:** Prefix + Jahresformat + Nummer-Länge (z.B. `RE-{YYMM}-{NR:4}`)
- [ ] **Arbeitszeitgesetz (ArbZG) — Mindestpausen:**
  - Automatischer Mindestpausen-Abzug: aktiviert/deaktiviert
  - Konfigurierbare Schwellenwerte (Standardwerte nach ArbZG §4):
    - Schwelle 1: ab X Stunden → Y Minuten Mindestpause (Standard: 6h → 30 min)
    - Schwelle 2: ab X Stunden → Y Minuten Mindestpause (Standard: 9h → 45 min)
  - Hinweis im UI: „Änderungen können rechtliche Auswirkungen haben — bitte mit Ihrem Arbeitsrechtler abstimmen"
- [ ] **Email- und PDF-Vorlagen:** WYSIWYG- oder Markdown-Editor; Variablen werden erklärt (z.B. `{{customer_name}}`, `{{invoice_number}}`)
- [ ] **Alle Einstellungen** werden in `app_settings` Key-Value-Tabelle gespeichert
- [ ] **Secrets** (API-Keys, SMTP-Passwort) werden verschlüsselt in DB gespeichert (Laravel `encrypt()`)
- [ ] Einstellungsseite nur für Admins zugänglich (Dangerzone)
- [ ] Änderungen werden geloggt (Audit-Log: wer hat wann was geändert)

## Edge Cases
- Stripe-Key ist ungültig → Verbindungstest gibt Fehlermeldung, aber Einstellung wird gespeichert (Admin muss selbst prüfen)
- SMTP-Konfiguration falsch → Test-Email schlägt fehl, klare Fehlermeldung
- Standard-Kundengruppe wird gelöscht → Einstellung wird auf `NULL` gesetzt, Warnung im Admin
- Verschlüsselter API-Key kann nach Speichern nur als „*****" angezeigt werden (kein Klartext-Anzeige)

## Technical Requirements
- `app_settings` Tabelle: `key` (unique), `value` (text), `updated_at`, `updated_by`
- Secrets via `Crypt::encryptString()` / `Crypt::decryptString()`
- `.env` bleibt für System-Secrets (APP_KEY, DB-Credentials); App-spezifische API-Keys in `app_settings`

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)
_To be added by /architecture_

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
