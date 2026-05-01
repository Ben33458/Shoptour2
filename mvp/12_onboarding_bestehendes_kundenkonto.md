# MVP: Onboarding – bestehendes Kundenkonto aktivieren

## Ziel
Zusätzlich zur normalen Registrierung soll es einen zweiten Einstieg geben:

- **Neu registrieren**
- **Bestehendes Kundenkonto aktivieren**

Damit können bestehende Kunden, die bereits im System als Kundenkonto vorhanden sind, erstmals einen Web-Benutzer anlegen und anschließend durch eine geführte Prüfung ihrer hinterlegten Daten gehen.

---

## Grundprinzip
Dies ist **keine normale Neuregistrierung**, sondern eine **Aktivierung eines bestehenden Kundenkontos** mit Benutzeranlage, Passwortvergabe und anschließendem Erst-Onboarding.

Vorhandene Verifizierungslogik aus der **Mitarbeiter-Registrierung** soll wiederverwendet werden, nicht neu gebaut werden.

---

## Einstieg im UI
Auf der Login-/Registrierungsseite zusätzlich zur normalen Registrierung eine weitere Option anzeigen:

- **Bestehendes Kundenkonto aktivieren**

Die Option soll klar von der normalen Registrierung getrennt sein.

---

## Aktivierungs-Flow

### 1. E-Mail eingeben
Der Nutzer gibt die E-Mail-Adresse ein, die bereits bei uns im Kundenkonto hinterlegt ist.

### 2. Interne Prüfung
System prüft, ob zur E-Mail genau ein aktivierbares Kundenkonto existiert.

### 3. Fallunterscheidung

#### Fall A: Genau ein aktivierbares Kundenkonto gefunden
- Verifizierungslogik aus der Mitarbeiter-Registrierung verwenden
- Bestätigungscode per E-Mail senden
- Code ist **15 Minuten** gültig
- „Code neu senden“ mit **Cooldown**
- **Rate Limit:** maximal 10 Versuche pro E-Mail **oder** 10 Versuche pro IP
- Wenn eines davon überschritten ist, wird das jeweils betroffene Objekt gesperrt/blockiert

#### Fall B: E-Mail mehrfach bei mehreren Kundenkonten vorhanden
- **Keine automatische Aktivierung**
- Interne E-Mail an **getraenke@kolabri.de** senden
- Inhalt mindestens:
  - betroffene E-Mail-Adresse
  - Hinweis, dass mehrere passende Kundenkonten gefunden wurden
  - betroffene Kundenkonten soweit intern sinnvoll identifizierbar
- Nutzerhinweis anzeigen, dass eine **manuelle Prüfung** erforderlich ist

#### Fall C: Zu der E-Mail existiert bereits ein aktiviertes Benutzerkonto
- Keine neue Aktivierung
- Hinweis anzeigen, dass das Benutzerkonto bereits aktiviert ist
- Link zur **Login-Seite**
- Link / Möglichkeit zur **Passwort-Zurücksetzung**

#### Fall D: Kein passendes Kundenkonto gefunden oder kein aktivierbares Konto vorhanden
- Keine Aktivierung
- Neutraler Hinweis, dass keine automatische Aktivierung möglich ist
- Verweis auf Kontakt per **E-Mail / Telefon / persönlich**

#### Fall E: Kundenkonto existiert, aber ohne hinterlegte E-Mail
- Keine Aktivierung möglich
- Kunde muss sich per **E-Mail / Telefon / persönlich** an uns wenden

---

## Nach erfolgreicher Verifizierung
Nach erfolgreicher Code-Bestätigung:

1. Nutzer vergibt ein Passwort
2. Benutzerkonto wird mit dem vorhandenen Kundenkonto verknüpft
3. Geführte Onboarding-Tour startet automatisch

Es darf **kein Duplikat-Kundenkonto** angelegt werden.

---

## Geführte Onboarding-Tour
Nach der Aktivierung soll der Nutzer einmal durch vorhandene Seiten geführt werden, um seine Daten zu prüfen und bei Bedarf direkt zu aktualisieren.

### Reihenfolge der Schritte
1. Persönliche Daten
2. E-Mail-Adressen
3. Adressen
4. Stammsortiment
5. Unterbenutzer
6. Rechnungen

### Wichtige Regeln
- Es sollen **bestehende Seiten** verwendet werden
- Keine unnötigen neuen Verwaltungsseiten bauen
- Jede Seite erhält oben eine **kurze Erklärung**, was man dort machen kann
- Diese Erklärung ist **schließbar**
- Danach bleibt auf der Seite ein kleiner Link sichtbar, z. B.:
  - **„Was kann ich hier machen?“**
- Über diesen Link kann die Erklärung wieder eingeblendet werden

### Inhaltliche Regeln je Schritt
- **Persönliche Daten:** direkt editierbar
- **E-Mail-Adressen:** direkt editierbar
- **Adressen:** direkt editierbar
- **Stammsortiment:** direkt editierbar
- **Unterbenutzer:** optionaler Inhalt, aber Schritt ist Teil der Tour
- **Rechnungen:** bestehende Seite einbinden; Nutzer soll sie im Onboarding einmal sehen

### Navigation in der Tour
- Pro Seite ein klarer Weiter-Button
- Auf der **letzten Seite** soll der Button heißen:
  - **„Jetzt bestellen“**
- Dieser Button führt auf die **Shopseite**

---

## Wann gilt das Onboarding als abgeschlossen?
Das Onboarding gilt als **abgeschlossen**, sobald der Nutzer die **letzte Seite erreicht** und dort der Button **„Jetzt bestellen“** sichtbar ist.

Nicht erst nach Klick auf den Button.

---

## Speicherung / Status
Es soll gespeichert werden:

- ob das Aktivierungs-Onboarding abgeschlossen wurde
- welche Hilfeboxen pro Seite geschlossen wurden bzw. wieder einblendbar sind

Wichtig:
- **Keine neue Tabelle nur dafür anlegen**, wenn es sich vermeiden lässt
- Bestehende User-Metadaten / Settings / JSON-Felder / vorhandene Mechanik nutzen

---

## Logging
Änderungen und wichtige Vorgänge sollen geloggt werden.

Beispiele:
- Aktivierung gestartet
- Code versendet
- Rate Limit ausgelöst
- Mehrfachtreffer erkannt
- Benutzerkonto mit Kundenkonto verknüpft
- Stammdaten geändert
- Onboarding abgeschlossen

Wichtig:
- **Log ja**
- **keine neue Log-Tabelle nur für dieses Feature**
- Vorhandene Logging-/Audit-Mechanik nutzen

---

## Technische Vorgaben
- Verifizierungslogik aus Mitarbeiter-Registrierung wiederverwenden
- Keine doppelte Implementierung derselben Code-Logik
- Keine Duplikat-Accounts bauen
- Keine neue Sonderstruktur anlegen, wenn bestehende Systemlogik nutzbar ist
- Bestehende Seiten in die Tour einhängen, statt neue Dubletten zu bauen

---

## UX-Hinweise
- Der Flow muss einfach und klar sein
- Kein unnötiger Ballast
- Keine Skip-Option für die Tour
- Sechs Schritte sind akzeptiert und gewollt
- Nutzer soll vorhandene Daten **direkt** prüfen und ändern können

---

## Nicht-Ziele für dieses MVP
- Keine komplexe Rechte-/Freigabelogik zusätzlich erfinden
- Kein neues separates Onboarding-Modul als eigenes Monstersystem
- Keine neue Tabelle nur für Hilfeboxen oder nur für Logs, sofern vorhandene Felder/Mechaniken reichen
- Keine manuelle Kontenauswahl für Mehrfachtreffer durch den Nutzer

---

## Akzeptanzkriterien
Das MVP ist erfüllt, wenn:

1. Auf der Registrierungs-/Login-Seite die Option **„Bestehendes Kundenkonto aktivieren“** vorhanden ist
2. Ein bestehender Kunde mit genau einer passenden E-Mail einen Code anfordern kann
3. Die vorhandene Verifizierungslogik verwendet wird
4. Code 15 Minuten gültig ist
5. Cooldown für erneutes Senden funktioniert
6. Rate Limit mit 10 Versuchen pro E-Mail oder IP greift
7. Mehrfachtreffer eine interne Mail an getraenke@kolabri.de auslösen
8. Bereits aktivierte Benutzer zur Login-/Passwort-Reset-Strecke geleitet werden
9. Nach erfolgreicher Aktivierung ein Passwort gesetzt werden kann
10. Das Benutzerkonto korrekt mit dem bestehenden Kundenkonto verknüpft wird
11. Danach automatisch die 6-stufige Tour startet
12. Auf jeder Seite eine schließbare Erklärung vorhanden ist
13. Auf der letzten Seite der Button **„Jetzt bestellen“** sichtbar ist und zur Shopseite führt
14. Onboarding bereits beim Erreichen der letzten Seite als abgeschlossen gespeichert wird
15. Logging vorhanden ist, ohne dafür eine neue Spezial-Logtabelle zu bauen
