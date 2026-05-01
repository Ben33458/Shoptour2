# Frontend Testplan — Kolabri Getränkeshop

> Stand: 2026-04-11  
> Ziel: Alle kundenseitigen Bereiche systematisch auf Bugs, UX-Probleme und Fehler prüfen.

---

## Vorbereitung

- **Testbenutzer A**: Normaler Kunde (aktiviert, hat Bestellungen & Rechnungen)
- **Testbenutzer B**: Neukunde (frisch aktiviert, Onboarding noch nicht abgeschlossen)
- **Testbenutzer C**: Gast (nicht eingeloggt)
- Browser: Desktop + Mobile (375px)

---

## 1. Authentifizierung

| # | Szenario | Erwartetes Ergebnis | OK? |
|---|---------|---------------------|-----|
| 1.1 | `/anmelden` aufrufen als Gast | Login-Formular erscheint | |
| 1.2 | Falsches Passwort eingeben | Fehlermeldung erscheint, kein Login | |
| 1.3 | Korrektes Passwort → Login | Weiterleitung zu `/` oder letzter Seite | |
| 1.4 | `/registrieren` als Gast aufrufen | Registrierungsformular erscheint | |
| 1.5 | Registrierung mit bestehender E-Mail | Fehlermeldung "E-Mail bereits vergeben" | |
| 1.6 | `/passwort-vergessen` → E-Mail eingeben | Erfolgshinweis, E-Mail wird versendet | |
| 1.7 | Passwort-Reset-Link aufrufen | Formular für neues Passwort | |
| 1.8 | Ausloggen | Session beendet, Weiterleitung zu `/` | |
| 1.9 | Geschützte Seite als Gast aufrufen (z.B. `/mein-konto`) | Weiterleitung zu Login | |

---

## 2. Konto-Aktivierung (Neukunde)

| # | Szenario | Erwartetes Ergebnis | OK? |
|---|---------|---------------------|-----|
| 2.1 | `/konto-aktivieren` aufrufen | Formular für Kundennummer / E-Mail | |
| 2.2 | Ungültige Kundennummer eingeben | Fehlermeldung | |
| 2.3 | Gültiger Code eingeben | Weiterleitung zu Schritt 3 (Passwort) | |
| 2.4 | Passwort setzen (zu kurz) | Validierungsfehler | |
| 2.5 | Passwort erfolgreich setzen | Login, Weiterleitung zu Onboarding | |

---

## 3. Onboarding-Wizard (6 Schritte)

| # | Szenario | Erwartetes Ergebnis | OK? |
|---|---------|---------------------|-----|
| 3.1 | Schritt 1 (`?onboarding_step=profil`) aufrufen | Fortschrittsbalken zeigt 1/6, Hilfsbox sichtbar | |
| 3.2 | „Weiter →" auf Schritt 1 klicken | Weiterleitung zu Schritt 2 (emails) | |
| 3.3 | Schritt 2 → 3 → 4 → 5 durchklicken | Jeder Schritt zeigt korrekte Nummer | |
| 3.4 | Auf Schritt 5 Sub-User-Aktion (Einladen) ausführen | Kein Verlust des `onboarding_step`-Params, Banner bleibt | |
| 3.5 | Schritt 6 (Rechnungen) aufrufen | Banner zeigt 6/6 + **„Einrichtung abschließen ✓"** Button (unten + auf Seite) | |
| 3.6 | „Einrichtung abschließen ✓" klicken | Weiterleitung zu Shop-Startseite, Erfolgsmeldung | |
| 3.7 | Nach Abschluss `/mein-konto/rechnungen` erneut aufrufen | Kein Onboarding-Banner mehr, kein Abschließen-Button | |
| 3.8 | Hilfsbox schließen → Seite neu laden | Hilfsbox bleibt geschlossen, „Was kann ich hier machen?"-Link erscheint | |
| 3.9 | „Was kann ich hier machen?" klicken | Hilfsbox erscheint wieder | |
| 3.10 | Resume-Hint im Header klicken | Weiterleitung zum zuletzt besuchten Schritt | |

---

## 4. Shop / Produktkatalog

| # | Szenario | Erwartetes Ergebnis | OK? |
|---|---------|---------------------|-----|
| 4.1 | `/` aufrufen als Gast | Startseite lädt, Produkte sichtbar | |
| 4.2 | Produkte filtern / suchen | Ergebnisse aktualisieren sich | |
| 4.3 | Produkt-Detailseite `/produkte/{id}` aufrufen | Produktinfo, Preis, „In den Warenkorb"-Button | |
| 4.4 | Preis als Gast anzeigen | Bruttopreis sichtbar | |
| 4.5 | Preis als eingeloggter Kunde | Korrekter Kundengruppen-Preis | |
| 4.6 | Nicht existierendes Produkt aufrufen | 404-Seite | |
| 4.7 | Produktbilder klicken | Vergrößerung / Galerie | |

---

## 5. Warenkorb

| # | Szenario | Erwartetes Ergebnis | OK? |
|---|---------|---------------------|-----|
| 5.1 | Produkt als Gast in Warenkorb legen | Warenkorb-Icon zeigt Anzahl | |
| 5.2 | `/warenkorb` aufrufen | Alle Artikel korrekt mit Preis gelistet | |
| 5.3 | Menge erhöhen / verringern | Preis aktualisiert sich | |
| 5.4 | Artikel entfernen | Artikel verschwindet, Summe passt sich an | |
| 5.5 | Warenkorb leeren | Leerer Zustand sichtbar | |
| 5.6 | Mini-Cart im Header öffnen | Zeigt aktuelle Artikel | |
| 5.7 | Einloggen mit gefülltem Gast-Warenkorb | Warenkorb bleibt erhalten | |
| 5.8 | Pfand-Artikel: Pfandbetrag sichtbar | Pfand wird in Summe angezeigt | |

---

## 6. Checkout

| # | Szenario | Erwartetes Ergebnis | OK? |
|---|---------|---------------------|-----|
| 6.1 | `/kasse` als Gast aufrufen | Weiterleitung zu Login | |
| 6.2 | `/kasse` mit leerem Warenkorb aufrufen | Hinweis "Warenkorb leer" oder Redirect | |
| 6.3 | Checkout-Formular öffnen | Lieferadresse vorausgefüllt (wenn vorhanden) | |
| 6.4 | Lieferart wählen (Heimdienst vs. Abholung) | Felder passen sich an | |
| 6.5 | Zahlungsart wählen | Alle konfigurierten Methoden erscheinen | |
| 6.6 | Bestellung ohne Pflichtfelder absenden | Validierungsfehler angezeigt | |
| 6.7 | Bestellung erfolgreich abschicken | Weiterleitung zu `/bestellung/{id}/abgeschlossen` | |
| 6.8 | Bestellbestätigung prüfen | Bestellnummer, Artikel, Summe korrekt | |

---

## 7. Mein Konto — Dashboard

| # | Szenario | Erwartetes Ergebnis | OK? |
|---|---------|---------------------|-----|
| 7.1 | `/mein-konto` aufrufen | Dashboard lädt, Übersicht sichtbar | |
| 7.2 | Widgets / KPIs korrekt | Letzte Bestellungen, offene Rechnungen | |
| 7.3 | Navigation zu Unterseiten | Alle Links funktionieren | |

---

## 8. Mein Konto — Bestellungen

| # | Szenario | Erwartetes Ergebnis | OK? |
|---|---------|---------------------|-----|
| 8.1 | `/mein-konto/bestellungen` aufrufen | Liste der Bestellungen, paginiert (20 pro Seite) | |
| 8.2 | Nächste Seite aufrufen | Weitere Bestellungen erscheinen | |
| 8.3 | Bestellung anklicken | Detailseite mit Artikel, Status, Rechnung | |
| 8.4 | Keine Bestellungen | Leerer Zustand sichtbar | |

---

## 9. Mein Konto — Rechnungen & Zahlungen

| # | Szenario | Erwartetes Ergebnis | OK? |
|---|---------|---------------------|-----|
| 9.1 | `/mein-konto/rechnungen` aufrufen | Rechnungsliste erscheint, max. 15 pro Seite | |
| 9.2 | Pagination | Seite 2, 3 usw. erreichbar | |
| 9.3 | Offener Saldo | Korrekt berechnet über ALLE Rechnungen (nicht nur Seite 1) | |
| 9.4 | PDF herunterladen | Lexoffice-PDF öffnet sich | |
| 9.5 | Rechnungsstatus | Offen / Überfällig / Bezahlt korrekt angezeigt | |
| 9.6 | Zahlungen aufgeklappt | Zahlungsdaten korrekt unter jeder Rechnung | |
| 9.7 | Kein Lexoffice-Kontakt | Leere Liste statt Fehler-404 | |

---

## 10. Mein Konto — Profil

| # | Szenario | Erwartetes Ergebnis | OK? |
|---|---------|---------------------|-----|
| 10.1 | `/mein-konto/profil` aufrufen | Formulare mit aktuellen Daten vorausgefüllt | |
| 10.2 | Namen ändern und speichern | Erfolgsmeldung, Daten aktualisiert | |
| 10.3 | Ungültige E-Mail eingeben | Validierungsfehler | |
| 10.4 | Passwort ändern (falsch altes PW) | Fehler "Aktuelles Passwort falsch" | |
| 10.5 | Passwort erfolgreich ändern | Erfolgsmeldung | |
| 10.6 | Preisanzeigemodus ändern | Brutto/Netto-Umschaltung funktioniert im Shop | |

---

## 11. Mein Konto — Adressen

| # | Szenario | Erwartetes Ergebnis | OK? |
|---|---------|---------------------|-----|
| 11.1 | `/mein-konto/adressen` aufrufen | Vorhandene Adressen gelistet | |
| 11.2 | Neue Adresse anlegen | Adresse erscheint in der Liste | |
| 11.3 | Adresse bearbeiten | Änderungen gespeichert | |
| 11.4 | Adresse löschen | Adresse entfernt | |
| 11.5 | Standardadresse setzen | Als Standard markiert, im Checkout vorausgewählt | |

---

## 12. Mein Konto — Stammsortiment

| # | Szenario | Erwartetes Ergebnis | OK? |
|---|---------|---------------------|-----|
| 12.1 | `/mein-konto/stammsortiment` aufrufen | Favoriten-Produkte gelistet | |
| 12.2 | Produkt hinzufügen | Produkt erscheint in der Liste | |
| 12.3 | Produkt entfernen | Produkt verschwindet | |
| 12.4 | Sollbestand eintragen | Wert gespeichert | |
| 12.5 | Alle Stammsortiment-Artikel in Warenkorb | Warenkorb korrekt gefüllt | |

---

## 13. Mein Konto — Unterbenutzer

| # | Szenario | Erwartetes Ergebnis | OK? |
|---|---------|---------------------|-----|
| 13.1 | `/mein-konto/unterbenutzer` aufrufen | Liste der Sub-User | |
| 13.2 | Einladung versenden | E-Mail wird gesendet, Pending-Status sichtbar | |
| 13.3 | Einladungslink aufrufen (`/einladung/{token}`) | Registrierungsformular für Sub-User | |
| 13.4 | Sub-User einloggen | Bestellung nur mit erlaubten Rechten möglich | |
| 13.5 | Rechte bearbeiten | Änderungen sofort wirksam | |
| 13.6 | Sub-User entfernen | Account deaktiviert | |

---

## 14. CMS-Seiten

| # | Szenario | Erwartetes Ergebnis | OK? |
|---|---------|---------------------|-----|
| 14.1 | Footer-Links aufrufen (Impressum, AGB, Datenschutz) | Seiten laden korrekt | |
| 14.2 | Nicht existierende Seite `/seite/xyz` | 404-Seite | |

---

## 15. Fehlerseiten & Edge Cases

| # | Szenario | Erwartetes Ergebnis | OK? |
|---|---------|---------------------|-----|
| 15.1 | Nicht existierende URL aufrufen | 404-Seite mit Navigation | |
| 15.2 | Auf fremde Bestellung zugreifen (`/mein-konto/bestellungen/{id}`) | 403 oder 404 | |
| 15.3 | Session abgelaufen → geschützte Seite aufrufen | Weiterleitung zu Login, nach Login zurück zur Zielseite | |
| 15.4 | Seite auf Mobilgerät (375px) | Keine überlappenden Elemente, alles lesbar | |
| 15.5 | Dark Mode (falls vorhanden) | Kontraste ausreichend, keine weißen Boxen auf weißem Hintergrund | |

---

## Hinweise zum Testen

- Nach jeder Aktion die URL prüfen — unerwartete Redirects sind ein Hinweis auf Fehler
- Browser-Konsole auf JS-Fehler prüfen (F12)
- Netzwerk-Tab: prüfen ob CSRF-Token mitgesendet wird (POST-Requests)
- Formulare immer auch mit leerem / ungültigem Input testen
