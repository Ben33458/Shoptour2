# PROJ-18: Admin: Benutzer & Rollen

## Status: Planned
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- Requires: PROJ-1 (Auth) — Benutzer-System ist die Basis

## Beschreibung
Verwaltung von Systembenutzern (Mitarbeiter, Admins) mit Rollen und granularen Rechten. Rollen: Admin (alles), Mitarbeiter (Mitarbeiterbereich), Kunde (nur Kundenbereich). Zusätzlich granulare Rechte pro Nutzer: sehen/bearbeiten/löschen für einzelne Bereiche. Neue Benutzer können direkt hier angelegt werden.

## User Stories
- Als Admin möchte ich neue Mitarbeiter-Accounts anlegen und ihnen Rollen zuweisen.
- Als Admin möchte ich granulare Rechte pro Nutzer konfigurieren (welche Bereiche sie sehen/bearbeiten/löschen dürfen).
- Als Admin möchte ich Nutzer deaktivieren (z.B. ausgeschiedener Mitarbeiter).
- Als Admin möchte ich Passwörter für Nutzer zurücksetzen.
- Als System soll jede Admin-Aktion den eingeloggten Nutzer prüfen und die Rechte validieren.

## Acceptance Criteria
- [ ] **Rollen:**
  - `admin` — Vollzugriff auf alles (auch Dangerzone)
  - `mitarbeiter` — Mitarbeiterbereich: Bestellungen, Rechnungen, Kunden, Lieferanten, Touren, Aufgaben, Log; kein Zugriff auf Dangerzone
  - `kunde` — Nur Kundenbereich (`/mein-konto/*`); kein Admin-Zugang
- [ ] **Granulare Rechte** (pro Bereich: `view`, `edit`, `delete`):
  - Bestellungen, Rechnungen, Kunden, Lieferanten, Produkte, Stammdaten, Touren, Berichte, Log
- [ ] Benutzerliste: Name, Email, Rolle, Status, letzte Aktivität; Suche
- [ ] Benutzer anlegen: Vorname, Nachname, Email, Rolle, initiales Passwort (oder Einladungs-Email)
- [ ] Benutzer bearbeiten: Name, Email (mit Bestätigung), Rolle, Rechte
- [ ] Passwort zurücksetzen: Neues Passwort setzen oder Reset-Link versenden
- [ ] Benutzer deaktivieren: Login wird blockiert; bestehende Sessions werden invalidiert
- [ ] Eigenes Profil bearbeiten: Jeder Nutzer kann Name, Email, Passwort selbst ändern
- [ ] Zwei-Faktor-Authentifizierung (2FA): Optional; TOTP (Google Authenticator) — konfigurierbar

## Edge Cases
- Admin versucht sich selbst zu deaktivieren → Verweigern (mind. 1 aktiver Admin muss immer existieren)
- Letzter Admin wird gelöscht → Verweigern
- Nutzer mit offenen, aktiven Touren (als Fahrer) wird deaktiviert → Warnung, aber erlaubt
- Rolle von `admin` auf `mitarbeiter` herabgestuft → alle aktuellen Admin-Sessions werden invalidiert

## Technical Requirements
- Rollen als Enum auf `users.role`
- Granulare Rechte als JSON oder separate `user_permissions`-Tabelle
- Middleware prüft Rechte auf allen Admin-Routen
- Session-Invalidierung bei Deaktivierung: `Auth::logoutOtherDevices()` oder Token-Widerruf

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)
_To be added by /architecture_

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
