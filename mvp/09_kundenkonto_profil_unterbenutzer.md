# 09 – Kundenkonto, Profil & Unterbenutzer (PROJ-5, PROJ-21)

## Status: Implementiert

---

## Umgesetzter Funktionsumfang

### Kundenkonto-Dashboard (`/mein-konto`)
- Firmenname wird bevorzugt über Vor-/Nachname angezeigt
- Kundennummer wird im Header-Bereich angezeigt
- Kacheln: Bestellungen, Adressen, Profil & Einstellungen, Stammsortiment, Unterbenutzer (nicht für Sub-User), Rechnungen / Weiter shoppen
- "Profil bearbeiten"-Button direkt im Header-Bereich

### Profil & Einstellungen (`/mein-konto/profil`)
Einzige Seite für alle Kunden-Selbstverwaltung:
- **Persönliche Daten:** Vorname, Nachname, Firma, Telefon
  - schreibt in `customers`-Tabelle
  - Vor-/Nachname wird auch auf `users` gespiegelt (für `$user->name`)
- **E-Mail-Adressen:** Haupt-E-Mail, Rechnungs-E-Mail, Versandbenachrichtigungs-E-Mail
- **Preisanzeige:** Brutto / Netto (überschreibt Gruppeneinstellung)
- **Versandbenachrichtigung:** Opt-in-Checkbox
- **Newsletter-Präferenz:** all / important_only / none
- **Passwort ändern:** separates Formular auf gleicher Seite
  - aktuelles Passwort wird mit `Hash::check()` geprüft
  - Rate-Limiting: 5 Versuche/Minute
  - Fehler direkt am Feld, kein Seiten-Reload

### Adressen (`/mein-konto/adressen`)
- Firma-Feld ist erste Position im Formular
- Lieferhinweis-Feld (max. 500 Zeichen, z. B. "Bitte klingeln")
- Firmenname wird in der Adresskarte als eigene Zeile angezeigt

### Unterbenutzer (`/mein-konto/unterbenutzer`) — PROJ-21
Nur für Hauptkunden sichtbar (nicht für Sub-User selbst).

**Einladungsflow:**
1. Hauptkunde gibt E-Mail, Name und Berechtigungen ein
2. Einladungs-E-Mail mit 48h-Link (`/einladung/{token}`)
3. Empfänger setzt Passwort, Account wird angelegt (role=`sub_user`)
4. Sub-User ist sofort eingeloggt und sieht das Konto des Elternkunden

**Berechtigungen (JSON in `sub_users.permissions`):**

| Schlüssel | Bedeutung | Default |
|---|---|---|
| `orders` | Bestellhistorie sehen | true |
| `order_history` | `own` oder `all` | `own` |
| `invoices` | Rechnungen sehen | false |
| `addresses` | Adressen verwalten | false |
| `assortment` | Stammsortiment sehen | false |
| `sub_users` | Unterbenutzer verwalten | false |
| `bestellen_all` | Im Shop bestellen (alle Produkte) | false |
| `bestellen_favoritenliste` | Aus Stammsortiment bestellen | true |
| `sollbestaende_bearbeiten` | Sollbestände bearbeiten | false |
| `preise_sehen` | Preise anzeigen | false |

**Sub-User im Konto:**
- Sehen das Konto des Elternkunden (Bestellungen, Adressen etc.)
- Banner: "Sie handeln im Namen von Firma XY als Max Muster"
- Können kein Profil des Elternkunden ändern
- Checkout: schreibt `placed_by_user_id` auf die Bestellung

---

## Datenmodell

### `sub_users`
```
id, user_id (FK users), parent_customer_id (FK customers),
permissions JSON, active bool, timestamps
```

### `sub_user_invitations`
```
id, email, first_name, last_name, parent_customer_id (FK customers),
permissions JSON, token string(64) unique, expires_at, used_at nullable, timestamps
```

### Erweiterungen an bestehenden Tabellen

| Tabelle | Neue Spalten |
|---|---|
| `customers` | `billing_email`, `notification_email`, `email_notification_shipping`, `newsletter_consent`, `price_display_mode` |
| `addresses` | `delivery_note` |
| `orders` | `placed_by_user_id` (nullable FK users) |

---

## Wichtige technische Entscheidungen

- `requireCustomer()` in allen Shop-Controllern muss Sub-User via `subUser->parentCustomer` auflösen — NICHT nur `isKunde()` prüfen
- `role` muss in `User::$fillable` stehen, sonst wird `role=sub_user` bei `User::create()` stillschweigend ignoriert
- Newsletter-Default ist `important_only` (nicht `all`) — Kunden wurden nie aktiv gefragt
- Double Opt-In für Newsletter noch nicht implementiert (→ PROJ-27)

---

## Offene Punkte

- DSGVO Newsletter Double Opt-In (PROJ-27): Timestamp + IP bei Einwilligung speichern
- Rechnungs-Download: funktioniert technisch, aber es sind noch keine lokalen Invoice-PDFs in der DB (Lexoffice-Sync fehlt noch → PROJ-13)
- Sub-User-Einladung ohne E-Mail-Server testen sobald SMTP konfiguriert
