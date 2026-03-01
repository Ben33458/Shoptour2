# PROJ-8: Zahlungsabwicklung

## Status: Planned
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- Requires: PROJ-1 (Auth) — Kunde muss identifizierbar sein
- Requires: PROJ-13 (Rechnungen) — Zahlungen werden gegen Rechnungen verbucht

## Beschreibung
Unterstützt mehrere Zahlungsmethoden: Stripe (Kreditkarte), PayPal, SEPA-Mandat, Überweisung, Barzahlung, EC-Zahlung, Auf Rechnung. Verfügbare Methoden sind pro Kundengruppe konfigurierbar. Zahlungen werden gegen Rechnungen verbucht und der Saldo berechnet.

## User Stories
- Als Admin möchte ich festlegen, welche Zahlungsmethoden für welche Kundengruppe verfügbar sind.
- Als Kunde möchte ich beim Checkout nur die für mich freigeschalteten Zahlungsmethoden sehen.
- Als Kunde möchte ich per Stripe (Kreditkarte/SEPA) sofort online bezahlen.
- Als Kunde möchte ich per PayPal bezahlen.
- Als Kunde möchte ich einem SEPA-Lastschrift-Mandat zustimmen (einmalig im Kundenkonto).
- Als Admin möchte ich manuelle Zahlungen (Überweisung, Bar, EC) auf Rechnungen verbuchen.
- Als System soll ein Stripe-Webhook Zahlungen automatisch bestätigen.
- Als System soll ein PayPal-Webhook Zahlungen automatisch bestätigen.

## Acceptance Criteria
- [ ] Zahlungsmethoden-Konfiguration im Admin: für jede Kundengruppe wählbar: Stripe, PayPal, SEPA, Überweisung, Barzahlung, EC, Rechnung
- [ ] Checkout zeigt nur freigeschaltete Methoden der Kundengruppe des eingeloggten Nutzers
- [ ] **Stripe:** Redirect zu Stripe Checkout → Webhook `payment_intent.succeeded` verbucht Zahlung
- [ ] **PayPal:** Redirect zu PayPal → Webhook `PAYMENT.CAPTURE.COMPLETED` verbucht Zahlung
- [ ] **SEPA-Mandat:** Kunde erteilt Mandat im Kundenkonto (IBAN + Zustimmung); Admin löst Abbuchung aus
- [ ] **Überweisung:** Bestellung wird angelegt; Rechnung wird per Email mit Bankdaten versendet; Admin verbucht Eingang manuell
- [ ] **Barzahlung / EC:** Für Abholung im Lager; Zahlung wird durch Mitarbeiter nach Abholung verbucht
- [ ] **Auf Rechnung:** Sofortige Bestellung; Zahlung durch Kunden nach Erhalt der Rechnung
- [ ] Zahlung-Datensatz: `invoice_id`, `amount_milli`, `payment_date`, `provider`, `provider_transaction_id`
- [ ] Stripe-Signatur-Validierung auf Webhook-Endpunkt (HMAC)
- [ ] PayPal-Signatur-Validierung auf Webhook-Endpunkt
- [ ] Rechnungs-Status aktualisiert sich automatisch: offen → teilweise bezahlt → bezahlt
- [ ] Überzahlung wird erkannt und im Kundenkonto als Guthaben angezeigt
- [ ] Admin: Manuelle Zahlung verbuchen (Betrag, Datum, Methode, Notiz) auf jeder Rechnung

## Edge Cases
- Stripe-Zahlung schlägt fehl → Bestellung wurde noch nicht angelegt; Nutzer kann erneut versuchen
- Webhook kommt doppelt (Idempotenz) → Zahlung darf nicht doppelt verbucht werden (`provider_transaction_id` unique)
- Rechnung wird storniert, aber Zahlung wurde bereits gebucht → Rückerstattungsprozess, manuell im Admin
- SEPA-Rücklastschrift → Zahlung rückgängig machen, Saldo anpassen, Kunde benachrichtigen
- Zahlung für bereits vollständig bezahlte Rechnung → als Überzahlung / Guthaben verbuchen

## Technical Requirements
- Stripe: `stripe/stripe-php` SDK; Webhook-Secret aus `.env`
- PayPal: `srmklive/paypal` oder offizielle SDK; Webhook-Verifikation
- Alle Beträge in milli-cents (Integer)
- SEPA-IBAN verschlüsselt speichern (Laravel Encryption)
- Webhook-Endpunkte haben keine Auth-Middleware (Signatur ist die Authentifizierung)

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)
_To be added by /architecture_

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
