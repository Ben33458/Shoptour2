# PROJ-21: Unterbenutzer & Kundenrechte (Büro-Accounts)

## Status: Planned
**Created:** 2026-03-01
**Last Updated:** 2026-03-01

## Dependencies
- Requires: PROJ-1 (Authentifizierung) — Unterbenutzer sind eigene Login-Accounts
- Requires: PROJ-10 (Admin: Kundenverwaltung) — Hauptkunde wird in Kundenverwaltung angelegt

## Beschreibung
Bürokunden (z.B. Unternehmen mit mehreren Bestellern) können Unterbenutzer anlegen. Jeder Unterbenutzer hat einen eigenen Login, bestellt aber im Namen des Hauptkunden (gleiche Kundennummer, gleiche Preiskonditionen, gleiche Lieferadresse). Der Hauptkunde (oder Admin) kann Rechte pro Unterbenutzer einschränken (nur bestellen, auch Rechnungen sehen, Adressen verwalten).

## User Stories
- Als Bürokunde (Hauptkunde) möchte ich Unterbenutzer anlegen, damit mehrere Mitarbeiter im Namen meiner Firma bestellen können.
- Als Hauptkunde möchte ich die Rechte jedes Unterbenutzers festlegen (z.B. nur bestellen, keine Rechnungen einsehen).
- Als Unterbenutzer möchte ich mich mit meinem eigenen Login anmelden und direkt im Namen meiner Firma bestellen.
- Als Unterbenutzer sehe ich nur die Bereiche, für die ich freigeschaltet bin.
- Als Admin möchte ich alle Unterbenutzer eines Kunden einsehen und ggf. deaktivieren.
- Als Hauptkunde möchte ich einen Unterbenutzer deaktivieren, ohne ihn löschen zu müssen.

## Acceptance Criteria
- [ ] **Unterbenutzer-Verwaltung im Kundenkonto:** Liste aller Unterbenutzer mit Name, Email, Rechten, Status (aktiv/inaktiv)
- [ ] **Unterbenutzer anlegen:** Name, Email, Passwort (oder Einladung per Email); Rolle wird automatisch auf „Unterbenutzer" gesetzt
- [ ] **Rechte-Matrix:** Pro Unterbenutzer konfigurierbar:
  - `Bestellen` (Pflicht, immer aktiv)
  - `Bestellhistorie einsehen` (eigene Bestellungen oder alle Firmenbestellungen)
  - `Rechnungen einsehen`
  - `Adressen verwalten`
  - `Stammsortiment bearbeiten`
  - `Unterbenutzer verwalten` (kann selbst Unterbenutzer anlegen)
- [ ] **Unterbenutzer deaktivieren/reaktivieren:** Sofort wirksam; Login wird gesperrt
- [ ] **Unterbenutzer löschen:** Nur wenn keine Bestellungen verknüpft; sonst nur deaktivieren
- [ ] **Kontext im Frontend:** Eingeloggter Unterbenutzer sieht im Header „Firma XY (als Max Muster)" — klar erkennbar, in wessen Namen gehandelt wird
- [ ] **Admin-Sicht:** Unter Kundenverwaltung → Tab „Unterbenutzer": alle Unterbenutzer des Kunden mit Rechten; Admin kann Unterbenutzer deaktivieren
- [ ] **Einladungs-Email:** Beim Anlegen eines Unterbenutzers wird eine Einladungsmail mit Passwort-Set-Link versandt
- [ ] **Bestellungen dem Hauptkunden zuordnen:** Alle Bestellungen eines Unterbenutzers werden der `customer_id` des Hauptkunden zugeordnet; `placed_by_user_id` vermerkt den Unterbenutzer

## Edge Cases
- Hauptkunde versucht, sich selbst als Unterbenutzer einzuladen → Verweigern
- Email wird bereits als Hauptkunde oder anderer Unterbenutzer verwendet → Fehlermeldung
- Unterbenutzer wird deaktiviert, während er gerade eine Bestellung abschließt → Bestellung wird abgeschlossen; danach kein Login mehr möglich
- Unterbenutzer mit Recht „Unterbenutzer verwalten" legt einen weiteren Unterbenutzer an → erlaubt, aber max. 1 Ebene tief (kein Sub-Sub-Unterbenutzer)
- Hauptkunde wird vom Admin gesperrt → alle Unterbenutzer-Logins werden ebenfalls gesperrt

## Technical Requirements
- `sub_users` Tabelle: `user_id → users`, `parent_customer_id → customers`, `permissions JSON`
- Bestellungen: `placed_by_user_id` (nullable) auf `orders`-Tabelle, `customer_id` bleibt Hauptkunde
- Middleware: `SubUserPermission` — prüft, ob Unterbenutzer Berechtigung für aktuelle Route hat
- Einladungs-Link: signed URL mit 48h Ablauf

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur (UI-Baum)

```
/konto/unterbenutzer                     ← Hauptkunde verwaltet Unterbenutzer
│
├── Unterbenutzer-Liste
│   ├── Name, Email, Rechte-Übersicht, Status (aktiv/inaktiv)
│   └── [Bearbeiten] [Deaktivieren/Reaktivieren]
│
├── [Unterbenutzer einladen] → Modal
│   ├── Name, Email
│   ├── Rechte-Matrix (Checkboxen je Berechtigung)
│   └── [Einladung senden]
│
└── Berechtigungs-Editor (je Unterbenutzer)
    ├── Bestellen (immer aktiv, nicht abwählbar)
    ├── Bestellhistorie (eigene / alle Firmenbestellungen)
    ├── Rechnungen einsehen
    ├── Adressen verwalten
    ├── Stammsortiment bearbeiten
    └── Unterbenutzer verwalten

/admin/kunden/{id}/unterbenutzer         ← Admin-Tab in Kundenverwaltung
└── Gleiche Liste; Admin kann deaktivieren, Rechte sehen
```

### Datenmodell

```
sub_users  [Unterbenutzer-Verknüpfung]
├── id
├── user_id            → users (der Unterbenutzer-Account)
├── parent_customer_id → customers (der Hauptkunde)
├── permissions        JSON  {orders, invoices, addresses, assortment, sub_users}
├── active             BOOL
└── company_id

users  [Standard-User-Tabelle, erweitert um Rolle]
└── role ENUM: ... | sub_user

orders  [erweitert]
└── placed_by_user_id → users (nullable)  ← wer hat bestellt?

sub_user_invitations  [Einladungs-Token]
├── email, parent_customer_id, permissions JSON
├── token (signed URL, hashed), expires_at
└── used_at (nullable)
```

### Berechtigungs-Middleware

```
SubUserPermission prüft je Route:

  Route                         → Benötigte Permission
  POST /warenkorb/*             → orders (immer erlaubt)
  GET  /konto/rechnungen        → invoices
  POST /konto/adressen          → addresses
  /konto/stammsortiment         → assortment
  /konto/unterbenutzer          → sub_users

  Fehlend → 403 mit Hinweis „Keine Berechtigung für diesen Bereich"

Header-Kontext wenn Unterbenutzer eingeloggt:
  „Müller GmbH (als Max Muster)" — klar erkennbar, für wen gehandelt wird
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| Eigene `sub_users`-Tabelle | Unterbenutzer-Rechte sind per Eltern-Kunde spezifisch; keine systemweiten Rollen |
| `permissions` als JSON | 6 Berechtigungen flexibel erweiterbar ohne Schema-Migration |
| Einladung per signed URL (48h) | Kein temporäres Passwort; Nutzer setzt eigenes Passwort; sicher |
| `placed_by_user_id` auf Bestellungen | Transparenz: Hauptkunde sieht, wer bestellt hat |
| Max. 1 Unterbenutzer-Ebene | Verhindert komplexe Hierarchien; Büro-Use-Case braucht keine Sub-Sub-User |

### Neue Controller / Services

```
Shop\UnterbenutzerverwaltungController  ← index, invite, update (Rechte), toggleActive
Admin\KundeSubUserController            ← index (readonly), toggleActive
SubUserPermission (Middleware)          ← prüft Berechtigungen je Route
SubUserInvitationService               ← generateToken(), sendInvitation(), acceptInvitation()
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
