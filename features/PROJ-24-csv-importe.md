# PROJ-24: Admin: CSV-Importe (Kunden, Produkte, Lieferanten, LMIV)

## Status: Planned
**Created:** 2026-03-01
**Last Updated:** 2026-03-01

## Dependencies
- Requires: PROJ-9 (Admin: Stammdaten) — Produkte müssen vorhanden sein (für Produktimport-Update)
- Requires: PROJ-10 (Admin: Kundenverwaltung) — Kunden müssen vorhanden sein (für Kundenimport)
- Requires: PROJ-11 (Admin: Lieferantenverwaltung) — Lieferanten müssen vorhanden sein

## Beschreibung
Import-System für Massendaten aus CSV-Dateien. Unterstützte Import-Typen: Kunden, Produkte (Neuanlage + Update), Lieferanten, LMIV-Daten (Lebensmittelinformationsverordnung: Nährwerte, Allergene). Jeder Import zeigt eine Vorschau mit Validierungsfehlern vor der Ausführung.

## User Stories
- Als Admin möchte ich eine CSV-Datei hochladen und eine Vorschau mit Fehlern sehen, bevor Daten importiert werden.
- Als Admin möchte ich bestehende Produkte per CSV aktualisieren (z.B. Preise, EAN-Codes).
- Als Admin möchte ich neue Kunden aus einer Exportliste eines Altsystems importieren.
- Als Admin möchte ich LMIV-Daten (Nährwerte, Allergene) für Produkte per CSV einspielen.
- Als Admin möchte ich den Import-Status (wie viele Zeilen erfolgreich, wie viele fehlerhaft) nach dem Import einsehen.
- Als Admin möchte ich fehlerhafte Zeilen als CSV herunterladen, um sie zu korrigieren.

## Acceptance Criteria
- [ ] **Import-Typen:** Produkte (create + update by EAN), Kunden, Lieferanten, LMIV (Nährwerte + Allergene by EAN/Artikelnummer)
- [ ] **Upload-Oberfläche:** Datei hochladen (CSV, max. 10 MB), Trennzeichen wählen (Komma / Semikolon / Tab), Encoding wählen (UTF-8 / ISO-8859-1), Spalten-Mapping-Vorschau
- [ ] **Validierungsvorschau:** Bevor Import ausgeführt wird: Tabelle mit ersten 20 Zeilen, Fehler je Zeile werden rot hervorgehoben; Anzahl gültiger / fehlerhafter Zeilen
- [ ] **Pflichtfelder-Validierung:** Import bricht nicht ab, sondern sammelt Fehler je Zeile
- [ ] **Import ausführen:** Nur gültige Zeilen werden importiert; fehlerhafte Zeilen werden übersprungen und als CSV zum Download angeboten
- [ ] **Import-Protokoll:** Nach Ausführung: Anzahl Neue / Aktualisierte / Übersprungene Datensätze; Fehlerliste; Timestamp; ausführender Benutzer
- [ ] **LMIV-Import:** Spalten: EAN, Energie_kcal, Energie_kJ, Fett, GesättigteFettsäuren, Kohlenhydrate, Zucker, Ballaststoffe, Eiweiß, Salz, Allergene (kommasepariert)
- [ ] **Produktimport:** Match per EAN oder Artikelnummer; neue EAN → Neuanlage, bestehende EAN → Update nur angegebener Felder
- [ ] **Kundenimport:** Pflichtfelder: Firmenname oder Vorname+Nachname, Email; optionale Felder: Telefon, Adresse, Kundennummer, Kundengruppe
- [ ] **Duplikatprüfung:** Bei Kundenimport: wenn Email bereits vorhanden → Update oder überspringen (konfigurierbar vor Import)

## Edge Cases
- CSV-Datei hat falsche Kodierung (Umlaute defekt) → Validierungshinweis; Encoding-Auswahl erneut anbieten
- Mehr als 10.000 Zeilen → Warnung; Import wird in Hintergrundprozess ausgeführt (`deferred_tasks`); Benutzer wird per Email benachrichtigt
- Alle Zeilen ungültig → Import nicht durchführen; Fehlerbericht anzeigen
- Spalten-Mapping nicht eindeutig → Vor Import: Pflicht-Mapping-Schritt (Dropdown: „Welche Spalte ist der Name?")
- Produkt-EAN existiert zweimal in CSV → Nur erste Zeile verarbeiten; zweite als Duplikat im Fehlerprotokoll markieren

## Technical Requirements
- CSV-Parsing: PHP `League\Csv`-Bibliothek (UTF-8-Safe, verschiedene Trennzeichen)
- Große Dateien: chunks von 500 Zeilen; `deferred_tasks` für Hintergrundverarbeitung
- `import_logs` Tabelle: `id`, `type`, `filename`, `total_rows`, `imported`, `updated`, `skipped`, `failed`, `user_id`, `created_at`
- Fehler-CSV: Originaldaten + zusätzliche Spalte `Fehler` am Ende

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur (UI-Baum)

```
/admin/import/
│
├── index                   ← Import-Auswahl + Historie
│   ├── Import-Typ wählen (Karten: Produkte / Kunden / Lieferanten / LMIV)
│   └── Import-Protokoll-Liste: Datum | Typ | Gesamt | OK | Fehler | Status
│
└── {typ}/                  ← Import-Workflow (3 Schritte)
    │
    ├── Schritt 1: Datei hochladen
    │   ├── Drag & Drop Upload-Zone (CSV, max. 10 MB)
    │   ├── Trennzeichen wählen (Komma / Semikolon / Tab)
    │   └── Encoding wählen (UTF-8 / ISO-8859-1)
    │
    ├── Schritt 2: Spalten-Mapping + Vorschau
    │   ├── Tabelle: erste 5 Zeilen der CSV
    │   ├── Je Spalte: Dropdown „Welches Feld?" (oder „ignorieren")
    │   ├── Validierungsvorschau: erste 20 Zeilen mit Fehlermarkierung
    │   └── Zusammenfassung: X gültig / Y fehlerhaft
    │
    └── Schritt 3: Import ausführen + Ergebnis
        ├── [Import starten] → Verarbeitung (Fortschrittsbalken)
        └── Ergebnis: Neu / Aktualisiert / Übersprungen / Fehler
            └── [Fehler-CSV herunterladen]
```

### Datenmodell

```
import_logs  [Import-Protokoll]
├── id
├── type  ENUM: products | customers | suppliers | lmiv
├── original_filename, total_rows
├── imported (neu), updated, skipped, failed
├── status ENUM: pending | processing | done | failed
├── error_file_path (nullable)  ← Fehler-CSV im Storage
├── user_id, created_at
└── company_id
```

### Import-Ablauf

```
Schritt 2 (Validierungsvorschau) — kein Speichern:
  CsvImportService::validate($file, $mapping)
  → parst ersten 100 Zeilen
  → prüft Pflichtfelder, Formate, Duplikate
  → gibt ValidationResult zurück (keine DB-Änderung)

Schritt 3 (Import ausführen):
  ≤ 500 Zeilen → synchron verarbeiten
  > 500 Zeilen → deferred_task erstellen → Email bei Fertigstellung

Fehlerbehandlung:
  Zeile ungültig → überspringen, in Fehler-CSV schreiben
  Zeile gültig   → importieren
  → kein Abbruch bei Teilfehler
```

### Import-Typen: Match-Logik

```
Produkt-Import:
  EAN vorhanden + EAN in DB → UPDATE
  EAN vorhanden, nicht in DB → CREATE
  keine EAN → CREATE (neue Artikelnummer generiert)

Kunden-Import:
  Email vorhanden in DB → UPDATE oder SKIP (konfigurierbar)
  Email nicht in DB → CREATE

LMIV-Import:
  Match per EAN → UPDATE Nährwert-/Allergen-Felder auf dem Produkt
  EAN nicht gefunden → Zeile als Fehler markieren
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| 3-Schritt-Wizard | Benutzer sieht Fehler BEVOR Daten importiert werden; verhindert Datenmüll |
| Synchron vs. deferred | Kleine Importe sofort fertig; große Importe im Hintergrund ohne Browser-Timeout |
| Fehler-CSV als Download | Admin kann Fehler in Excel korrigieren und erneut hochladen; kein manuelles Nacharbeiten |
| `League\Csv` Bibliothek | Robuste UTF-8/Encoding-Unterstützung; kein `fgetcsv()`-Workaround nötig |

### Neue Controller / Services

```
Admin\ImportController          ← index, show (Protokoll), store (Upload+Mapping)
Admin\ImportExecuteController   ← store (Ausführen)
CsvImportService               ← validate(), execute(), generateErrorCsv()
ProductImportHandler           ← importRow(), validateRow()
CustomerImportHandler          ← importRow(), validateRow()
LmivImportHandler              ← importRow(), validateRow()
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
