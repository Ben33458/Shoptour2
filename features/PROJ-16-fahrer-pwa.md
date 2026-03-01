# PROJ-16: Fahrer-PWA

## Status: Planned
**Created:** 2026-02-28
**Last Updated:** 2026-02-28

## Dependencies
- Requires: PROJ-15 (Admin: Fahrertouren-Planung) — Touren müssen geplant sein
- Requires: PROJ-35 (Admin: Kassenverwaltung) — Kassenbuch-Struktur muss existieren

## Beschreibung
Mobile-optimierte Progressive Web App für Fahrer. Funktioniert offline (Service Worker). Fahrer lädt Tourdaten beim Start herunter (Bootstrap), erfasst Liefer-Events (angekommen, geliefert, nicht geliefert, Notiz), lädt Fotos hoch. Zahlungen (Bar/EC) werden pro Stop erfasst und im Kassenbuch gespeichert. Leergut-Rücknahme und Pfandausgleich können direkt am Stop eingetragen werden. Alle Events werden lokal gepuffert und synchronisiert, wenn wieder online.

### Tour-Status-Workflow

```
Geplant → Offen → Unterwegs → Fertig geliefert → Abgeschlossen
                                     ↓
                                  Problem → Abgeschlossen
                     (jederzeit) → Storniert
```

| Status | Bedeutung |
|--------|-----------|
| **Geplant** | Tour wurde angelegt; Fahrer noch nicht benachrichtigt/freigegeben |
| **Offen** | Tour freigegeben, Fahrer kann starten; Stops sind bereit |
| **Unterwegs** | Fahrer hat Tour gestartet (erstes `arrived`-Event) |
| **Fertig geliefert** | Alle Stops abgearbeitet (mind. 1 Stop muss `stop_finished` sein) |
| **Problem** | Mind. ein Stop hat aktives Problem (nicht angetroffen, beschädigt, etc.) |
| **Abgeschlossen** | Geld vollständig eingezahlt; Tour abgeschlossen |
| **Storniert** | Tour abgesagt (Admin-Aktion); keine weiteren Events möglich |

### Kassenbuch (Liefergeldbeutel)

Jeder Fahrer hat einen eigenen virtuellen Liefergeldbeutel (Kasse). Barzahlungen von Kunden werden diesem Beutel gutgeschrieben. Am Ende der Tour zahlt der Fahrer den Betrag in eine der Zielkassen ein (Tresor, Kasse im Büro, Bank). EC-Zahlungen fließen nicht in den Beutel — sie werden nur als Beleg gebucht.

### Pfandausgleich

Gibt ein Kunde Leergut zurück, kann der Fahrer die Mengen pro Gebinde erfassen. Es gibt zwei Abwicklungsmodi:

- **Pfandausgleich:** Der Leergut-Betrag wird direkt gegen die Bestellung verrechnet (OrderAdjustment). Der Fahrer nimmt entsprechend weniger Geld entgegen.
- **Keine Verrechnung:** Leergut wird nur mengenmäßig erfasst (z.B. für Kundengruppen mit `is_deposit_exempt = true`); kein Pfandausgleich.

## User Stories
- Als Fahrer möchte ich meine Tour für heute auf meinem Smartphone sehen (offline-fähig).
- Als Fahrer möchte ich Stops der Reihe nach abarbeiten und jeden Stop als „angekommen" und „abgeschlossen" markieren.
- Als Fahrer möchte ich für jede Bestellposition die tatsächlich gelieferte Menge erfassen (ItemFulfillment).
- Als Fahrer möchte ich nicht gelieferte Positionen mit einem Grund markieren (z.B. nicht angetroffen, beschädigt).
- Als Fahrer möchte ich eine Notiz zu einem Stop hinterlassen.
- Als Fahrer möchte ich ein Foto (Proof of Delivery) für einen Stop aufnehmen und hochladen.
- Als Fahrer möchte ich eine Barzahlung vom Kunden erfassen — der Betrag wird meinem Liefergeldbeutel gutgeschrieben.
- Als Fahrer möchte ich eine EC-Zahlung bestätigen — der Beleg wird gespeichert, kein Kasseneintrag.
- Als Fahrer möchte ich Leergut vom Kunden erfassen und optional einen Pfandausgleich durchführen (Betrag von Rechnung abziehen).
- Als Fahrer möchte ich am Ende der Tour sehen, wie viel Bargeld ich einzahlen muss und in welche Kasse.
- Als Fahrer möchte ich die Einzahlung in eine Kasse bestätigen (Tour → Status „Abgeschlossen").
- Als Fahrer möchte ich, dass alle meine Aktionen auch offline gespeichert und später synchronisiert werden.
- Als System sollen Fahrer-Events in der Datenbank verarbeitet und Bestellstatus aktualisiert werden.

## Acceptance Criteria

### Authentifizierung & Grundstruktur
- [ ] **Authentifizierung:** via API-Token (Bearer); Token wird durch Admin für den Fahrer verwaltet (PROJ-15)
- [ ] **Bootstrap (GET /api/driver/bootstrap):** Lädt alle TourStops des aktuellen Tages, Bestelldetails, Kundenadressen, offene Salden (Schuldbeträge), aktive Liefergeldbeutel-Summe
- [ ] **Offline-Modus:** Daten werden im Browser (IndexedDB / localStorage) gespeichert; App funktioniert ohne Internet
- [ ] **Sync (POST /api/driver/sync):** Event-basiert; idempotente Event-IDs (UUID v4)

### Stop-Abarbeitung
- [ ] **Events:** `arrived`, `stop_finished`, `item_fulfilled`, `item_not_delivered`, `note`
- [ ] **ItemFulfillment:** pro Position: `delivered_qty`, `not_delivered_qty`, `reason`
- [ ] **Foto-Upload (POST /api/driver/upload):** Multipart; max. 10 MB; verknüpft mit TourStop
- [ ] **Lieferhinweise je Stop** (aus Bootstrap-Daten der Adresse):
  - Abstellort prominent angezeigt (z.B. „Keller" oder „EG")
  - „Abstellen erlaubt" als deutliches visuelles Signal (grüne Badge / Icon)
  - Lieferhinweis-Freitext sichtbar auf Stop-Detail

### Zahlungserfassung
- [ ] **Barzahlung erfassen** (`payment_cash`-Event):
  - Fahrer gibt eingenommenen Betrag ein
  - Betrag wird dem Liefergeldbeutel des Fahrers gutgeschrieben (KassenbuchEintrag)
  - OrderPayment wird erstellt (Zahlungsart: `cash`)
  - Bei Überzahlung: Differenz wird als Trinkgeld/Guthaben auf Kundenkonto verbucht
- [ ] **EC-Zahlung bestätigen** (`payment_ec`-Event):
  - Fahrer bestätigt EC-Zahlung; Betrag und Referenz werden erfasst
  - Kein Kassenbucheintrag (EC geht direkt auf Bankkonto)
  - OrderPayment wird erstellt (Zahlungsart: `ec`)
- [ ] **Offener Saldo sichtbar:** Pro Stop wird angezeigt, ob Kunde noch offene Beträge hat (aus früheren Bestellungen)
- [ ] **Zahlungserfassung optional:** Fahrer kann Zahlung erfassen, muss aber nicht (bei Rechnungskunden / Vorkasse)

### Leergut & Pfandausgleich
- [ ] **Leergut-Erfassung** (`deposit_return`-Event):
  - Pro Gebinde-Typ: zurückgegebene Menge
  - Gebinde-Liste aus Bootstrap (Artikel der Bestellung)
- [ ] **Pfandausgleich-Option:**
  - Nur wenn Kunde `is_deposit_exempt = false`
  - Fahrer wählt: „Pfandausgleich" oder „Nur erfassen (kein Abzug)"
  - Bei Pfandausgleich: OrderAdjustment (negativ) auf Bestellung; Barzahlungs-Erwartung reduziert sich entsprechend
  - Leergut-Betrag wird aus PfandSet des Gebindes berechnet (serverseitig)
- [ ] **Deposit-exempt Kunden:** Pfandausgleich-Option wird nicht angezeigt; nur mengenmäßige Erfassung

### Tour-Abschluss & Einzahlung
- [ ] **Abschluss-Screen** nach „Fertig geliefert":
  - Summierung: Gesamt Bargeld eingenommen − Pfandausgleiche = Einzahlungsbetrag
  - Übersicht: Stop-für-Stop aufgelistet (Betrag, Zahlungsart)
  - Liste der verfügbaren Zielkassen (aus Bootstrap, konfiguriert in PROJ-35)
- [ ] **Einzahlung bestätigen** (`cash_deposit`-Event):
  - Fahrer wählt Zielkasse (z.B. „Tresor Kolabri")
  - Betrag wird eingetragen (kann von Erwartungsbetrag abweichen — Differenz wird protokolliert)
  - Kassenbucheintrag: Liefergeldbeutel → Zielkasse (Transfer)
  - Tour-Status wechselt zu **Abgeschlossen**
- [ ] **„Kontrolliert"-Feld:** Kann vom Admin nach Nachzählung gesetzt werden (nicht vom Fahrer)

### Tour-Status-Management
- [ ] Tour-Status-Übergänge werden serverseitig validiert (kein direkter Status-Jump erlaubt)
- [ ] Status **Problem** wird automatisch gesetzt, wenn ein Stop `item_not_delivered` mit kritischem Grund hat (konfigurierbar)
- [ ] Status **Fertig geliefert** wird automatisch gesetzt, wenn letzter Stop `stop_finished` ist
- [ ] Admin kann Status manuell überschreiben (PROJ-15)

### Offline & Sync
- [ ] **Offline-Puffer:** Wenn offline, werden Events lokal gespeichert; bei Netzwerkkonnektivität automatisch gesendet
- [ ] **Sync-Konflikt:** Idempotente Event-IDs (UUID pro Event); Duplicate-Sync ignoriert
- [ ] **Rate Limiting:** Bootstrap 120/min, Sync 60/min pro Token

### UI
- [ ] Klare, große Touch-Targets (min. 44×44px)
- [ ] Stop-Liste, Stop-Detail, Positions-Checkboxen, Kamera-Button, Notiz-Feld
- [ ] Zahlungs-Dialog (Bar/EC) mit Tastenfeld für Betrag
- [ ] Leergut-Dialog mit Gebinde-Mengen
- [ ] Abschluss-/Einzahlungs-Screen

### PWA
- [ ] **PWA:** Service Worker für Offline-Caching; Web App Manifest für Add-to-Homescreen

## Edge Cases
- Fahrer synchronisiert Event zweimal (schlechte Verbindung) → Idempotenz via Event-UUID, zweiter Sync ignoriert
- Fahrer hat veraltete Tour-Daten (Bootstrap vor 2 Tagen) → Warnung, manueller Re-Bootstrap
- Upload schlägt fehl → Retry mit Exponential Backoff; Foto bleibt lokal bis Upload erfolgreich
- Token abgelaufen/widerrufen während Schicht → Klare Fehlermeldung, Daten bleiben offline erhalten
- Zwei Fahrer mit gleichem Token → verhindert durch eindeutige Token (widerrufbar im Admin)
- Fahrer zahlt mehr ein als erwartet → Differenz wird protokolliert (Kassenbuch-Abweichung); kein Fehler
- Fahrer zahlt weniger ein → Differenz wird protokolliert; Admin sieht Warnung in PROJ-35
- Pfandausgleich für Gebinde ohne PfandSet → Betrag = 0; Leergut wird nur mengenmäßig erfasst
- Deposit-exempt Kunde gibt Leergut zurück → wird nur erfasst, kein OrderAdjustment
- Barzahlung bei Rechnungskunden → möglich (z.B. Nachzahlung alter Schulden); wird als Sonderzahlung erfasst
- Tour-Status „Storniert" → keine weiteren Events möglich; Fahrer sieht deutliche Fehlermeldung
- Fahrer versucht Einzahlung in gesperrte/inaktive Kasse → Validierungsfehler; andere Kasse wählen

## Technical Requirements
- API: Laravel API Routes mit DriverApiToken-Middleware (Bearer-Token-Auth)
- Event-IDs: UUID v4 (clientseitig generiert, serverseitig auf Duplikate geprüft)
- `DriverSyncService` aus bestehender Codebasis übernehmen
- Kassenbuch-Events: `KassenbuchEintrag` mit `from_kasse_id`, `to_kasse_id`, `betrag_milli`, `tour_id`, `stop_id`, `typ` (cash_received / cash_deposit / ec_received)
- Pfandausgleich-Berechnung: serverseitig via `PfandCalculator`; Client sendet nur Mengen, Server berechnet Betrag
- Bootstrap-Response enthält: verfügbare Zielkassen (gefiltert nach Fahrer-Berechtigung), aktueller Liefergeldbeutel-Stand
- Frontend: Next.js PWA oder separates Vite/React-Projekt (TBD in Architecture)
- Service Worker: Workbox oder manuell

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)
_To be added by /architecture_

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
