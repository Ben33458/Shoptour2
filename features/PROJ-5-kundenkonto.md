# PROJ-5: Kundenkonto

## Status: Planned
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- Requires: PROJ-1 (Auth) — Login erforderlich
- Requires: PROJ-4 (Checkout) — Bestellungen müssen existieren

## Beschreibung
Kundenbereich für eingeloggte Nutzer: Bestellhistorie, Rechnungen, Zahlungsstand, Kontoübersicht, Adressverwaltung, Standard-Zahlungsart, Trinkgeld-Funktion (Überzahlungen). Kunden können SEPA-Mandat online erteilen.

## User Stories
- Als Kunde möchte ich alle meine Bestellungen und deren aktuellen Status sehen.
- Als Kunde möchte ich meine Rechnungen einsehen und als PDF herunterladen.
- Als Kunde möchte ich meinen aktuellen Kontostand (offene Beträge) und alle Zahlungen sehen.
- Als Kunde möchte ich Überzahlungen als Trinkgeld verbuchen können.
- Als Kunde möchte ich meine Lieferadressen verwalten (hinzufügen, bearbeiten, löschen, Standard setzen).
- Als Kunde möchte ich meine Standard-Zahlungsmethode hinterlegen.
- Als Kunde möchte ich einem SEPA-Mandat online zustimmen.
- Als Kunde möchte ich mein Profil (Name, Email, Telefon) bearbeiten.
- Als Kunde möchte ich mein Passwort ändern.

## Acceptance Criteria
- [ ] Dashboard zeigt: Anzahl offener Bestellungen, offener Rechnungsbetrag, letzte Bestellung, Schnelllink zur Neubestellung
- [ ] Bestellliste: Datum, Bestellnummer, Status, Anzahl Positionen, Gesamtbetrag; klickbar für Detailansicht
- [ ] Bestelldetail: alle Positionen mit Preisen, Lieferadresse, Zahlungsmethode, aktueller Status
- [ ] Rechnungsliste: Rechnungsnummer, Datum, Betrag, Zahlungsstatus (offen/teilweise bezahlt/bezahlt/überzahlt)
- [ ] Rechnungs-PDF-Download für finalisierte Rechnungen
- [ ] Kontoübersicht: Alle Zahlungen chronologisch, laufender Saldo, aktueller Kontostand
- [ ] Überzahlung als Trinkgeld: Wenn Saldo positiv, Button „Als Trinkgeld verbuchen" mit Bestätigungsdialog
- [ ] Adressverwaltung: Adressen anzeigen, neue Adresse hinzufügen, bearbeiten, löschen, Standard-Adresse setzen
  - Felder je Adresse: Vorname, Nachname, Straße, Hausnr., PLZ, Stadt, Telefon, Lieferhinweis (Freitext)
  - **Abstellort:** Dropdown (Keller, EG, Garage, 1.OG, Sonstiges); bei „Sonstiges" Freitextfeld
  - **Abstellen bei Nichtantreffen:** Checkbox „Ware darf bei Abwesenheit abgestellt werden"
- [ ] Standard-Zahlungsart in Profil hinterlegen (aus verfügbaren Methoden der Kundengruppe)
- [ ] SEPA-Mandat-Zustimmung: Formular mit IBAN-Eingabe, Mandat-Text, digitale Unterschrift/Zustimmung
- [ ] Profil bearbeiten: Vorname, Nachname, Telefon (Email-Änderung erfordert Bestätigung)
- [ ] Passwort ändern: Altes Passwort + neues Passwort (min. 8 Zeichen) + Bestätigung
- [ ] Alle Aktionen erfordern aktive Session (keine öffentlichen Konto-URLs)

## Edge Cases
- Kunde hat noch keine Bestellungen → Leerzustand mit Link zum Katalog
- Rechnung ist noch im Draft-Status → PDF-Download nicht verfügbar, Status „In Bearbeitung"
- SEPA-Mandat bereits erteilt → Mandat anzeigen mit Option zum Widerrufen
- Kontostand ist negativ (offene Rechnung) → Aufforderung zur Zahlung prominent anzeigen
- Kunde versucht fremde Bestellnummer aufzurufen → 404 (keine Informationen über Existenz)
- Email-Änderung: Neue Email erhält Bestätigungslink; bis Bestätigung bleibt alte Email aktiv

## Technical Requirements
- Alle Kontoansichten hinter Auth-Middleware
- PDF-Download über gesicherte, temporäre URLs (kein direkter Storage-Zugriff)
- SEPA-Mandat-Daten verschlüsselt speichern (IBAN nie im Klartext in Logs)

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)
_To be added by /architecture_

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
