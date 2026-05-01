# Handoff: Schnellbestellung – Schnelle Bestellerfassung

## Übersicht
Eine tastaturgesteuerte Schnellerfassungsmaske für Bestellungen. Der Nutzer kann Artikel über Artikelnummer oder Produktname suchen, eine Menge eingeben und mit Enter durch die Zeilen navigieren – ohne Maus. Das Interface ist auf maximale Eingabegeschwindigkeit ausgelegt.

## Über die Design-Dateien
Die Datei `Schnellbestellung.html` in diesem Paket ist ein **hochfideles HTML-Prototyp** – er zeigt das exakte Aussehen und das vollständige Interaktionsverhalten, ist aber kein Produktionscode. Die Aufgabe ist, diesen Prototypen in der bestehenden Codebase (mit deren Frameworks, Bibliotheken und Design-System) **neu zu implementieren** – nicht den HTML-Code direkt zu übernehmen.

## Fidelity: High-Fidelity
Der Prototyp ist pixelgenau mit finalem Layout, Farben, Typografie, Spacing und allen Interaktionen. Das Ziel ist eine möglichst genaue Umsetzung in der Ziel-Codebase.

---

## Screens / Views

### 1. Bestellerfassung (Hauptansicht)

**Layout:**
- Volle Viewport-Höhe, helles Grau als Hintergrund (`#f0ede8`)
- Schmale schwarze Header-Leiste (44px Höhe) mit Titel links, Datum rechts
- Hauptinhalt zentriert, max. 1100px breit, 24px Padding seitlich
- Keyboard-Hint-Leiste oben (Inline-Chips mit Tastenkürzel-Erklärungen)
- Weiße Tabellenkarte mit leichtem Schatten
- Footer-Bereich: Notizfeld links, Zusammenfassung + Button rechts

**Header:**
- Background: `#1a1a1a`, Höhe 44px
- Titel "Schnellbestellung" in weiß, 14px, bold
- Untertitel "Bestellerfassung" in `#555`, 12px
- Datum rechts in `#555`, 11px

**Keyboard-Hints:**
- Kleine Chips: `background: #e8e4de`, `border: 1px solid #ccc`, `border-radius: 3px`, Monospace 10px
- Labels daneben: `color: #999`, 11px

### 2. Tabellen-Header

Spalten (von links nach rechts):
| Spalte | Breite | Ausrichtung |
|--------|--------|-------------|
| # (Position) | 36px | Mitte |
| Artikel-Nr. | 120px | Links |
| Bezeichnung | flex/auto | Links |
| Einh. | 48px | Mitte |
| Menge | 80px | Rechts |
| EP (€) | 80px | Rechts |
| Gesamt (€) | 90px | Rechts |
| Löschen | 28px | Mitte |

- Header-Hintergrund: `#f7f5f2`
- Header-Schrift: 11px, uppercase, `letter-spacing: .04em`, `color: #888`, 600 weight
- Trennlinie unten: `2px solid #e0dcd6`

### 3. Bestellzeilen

**Artikel-Feld (Eingabe):**
- Kein sichtbarer Border-Rahmen, nur `border-bottom: 1.5px solid #ccc`
- Bei aufgelöstem Produkt: `border-bottom: 1.5px solid #1a6ef5`
- Placeholder: "Artikelnummer oder Name …" (nur erste Zeile)
- Hintergrund: transparent

**Produktname-Spalte (resolved):**
- Wenn aufgelöst: `#1a1a1a`, 13px
- Wenn nicht aufgelöst: `#bbb`, kursiv „—"

**Menge-Feld:**
- Gleicher Stil wie Artikel-Feld, textAlign: right
- `type="number"`, min="1"
- Placeholder: "Menge"

**Einheits-Spalte:** `color: #888`, 12px

**EP-Spalte:** `color: #888`, 12px, tabular-nums

**Gesamt-Spalte:** `color: #1a1a1a` wenn vorhanden, sonst `#ccc`, tabular-nums

**Löschen-Button:** × Symbol, `color: #ccc`, bei Hover `color: #e04545`

**Zeilentrenner:** `border-bottom: 1px solid #e8e4de`

**"Zeile hinzufügen"-Button:**
- Volle Breite, linksbündig, `color: #1a6ef5`, 12px
- `border-top: 1px solid #f0ede8`
- Hover: `background: #f7f9ff`
- Prefix „+" in 16px

### 4. Autocomplete-Dropdown

- Position: `absolute`, direkt unter dem Artikel-Feld, volle Breite der Zelle
- `background: #fff`, `border: 1px solid #ccc` (kein oberer Border), `box-shadow: 0 4px 12px rgba(0,0,0,.12)`
- Max. 8 Treffer, maxHeight 280px mit Scroll
- Jede Zeile: 6px/10px Padding, flex, gap 12px
- Spalten pro Treffer: Artikelnummer (monospace, 12px, opacity .7, min-width 48px) | Name (flex 1) | Einheit (opacity .6, 12px) | Preis (tabular-nums, 52px, right-align, 12px)
- Aktive Zeile: `background: #1a6ef5`, `color: #fff`
- Hover = aktiv setzen

### 5. Footer

**Notizfeld:**
- Label: "Bestellnotiz (optional)", 11px, `#888`
- Textarea: `border: 1px solid #ddd`, 6px/8px Padding, 12px, resize: vertical, 2 Zeilen Standardhöhe
- Placeholder: "z.B. dringend, Lager 2 …"

**Bestellzusammenfassung (rechts):**
- Positions-Anzahl: `#666`, 12px
- Gesamtbetrag: `#1a1a1a`, 16px, bold, tabular-nums
- Nur sichtbar wenn mindestens 1 gültige Zeile

**Bestellen-Button:**
- Aktiv: `background: #1a6ef5`, weiß, 500 weight
- Deaktiviert: `background: #ccc`
- Padding: 10px 28px, 13px
- Hover (aktiv): `background: #0f5ce0`
- Keine Border-Radius (flach)

### 6. Bestättigungsansicht (nach Submit)

- Zentriertes weißes Panel, max 480px, `border-radius: 2px`, `box-shadow: 0 2px 12px rgba(0,0,0,.08)`
- Blaues ✓ Icon (32px), `color: #1a6ef5`
- Titel 18px, 600 weight
- Zusammenfassung: Anzahl Positionen · Artikel · Gesamtbetrag
- "Neue Bestellung"-Button: blau, gleicher Stil wie Bestellen-Button

---

## Interaktionen & Verhalten

### Tastatur-Flow (Kernfunktion)

1. **Artikel-Feld → Enter:**
   - Wenn Artikelnummer exakt gefunden: Produkt auflösen, Fokus zu Menge-Feld
   - Wenn Dropdown offen und ein Eintrag aktiv (↑↓): diesen auswählen, Fokus zu Menge
   - Sonst: nichts (Nutzer muss Produkt auswählen)

2. **Artikel-Feld → Tab:** Gleiche Logik wie Enter

3. **Menge-Feld → Enter:**
   - Wenn letzte Zeile: neue Zeile anhängen, Fokus auf deren Artikel-Feld
   - Wenn nicht letzte Zeile: Fokus auf Artikel-Feld der nächsten Zeile

4. **Menge-Feld → Backspace (wenn leer):** Fokus zurück zum Artikel-Feld der gleichen Zeile

5. **Artikel-Feld → Backspace (wenn leer, nicht erste Zeile):** Zeile löschen, Fokus auf vorherige Zeile

6. **Dropdown:**
   - Öffnet sich bei jeder Eingabe wenn Treffer vorhanden
   - ↑↓ navigiert Einträge
   - Enter/Tab bei aktivem Eintrag: auswählen
   - Escape: Dropdown schließen
   - Click auf Eintrag: auswählen (preventDefault um kein blur zu triggern)
   - onBlur mit 150ms Verzögerung schließen (damit Click-Handler zuerst feuert)

### Suchlogik
```
function searchProducts(query):
  1. Alle Produkte bei denen id.startsWith(query) → oben
  2. Alle anderen Produkte bei denen name.toLowerCase().includes(query) → darunter
  3. Maximal 8 Ergebnisse zurückgeben
```

### Zeilenmanagement
- Immer mindestens 1 Zeile vorhanden
- Beim Löschen der letzten Zeile: neue leere Zeile anlegen
- Zeilennummer (Pos.) = 1-basierter Index
- Gesamtpreis = EP × Menge (nur bei vollständig ausgefüllten Zeilen)

### Validierung
- Nur Zeilen mit `product !== null` UND `qty > 0` zählen als gültig
- "Bestellen"-Button nur aktiv bei ≥ 1 gültigen Zeile

---

## State Management

```typescript
interface Product {
  id: string;       // Artikelnummer z.B. "10001"
  name: string;     // "Kugelschreiber blau 0,5mm"
  unit: string;     // "Stk", "Pkg", "Rl", etc.
  price: number;    // Nettopreis
}

interface OrderRow {
  key: number;          // unique ID für React-Keys
  query: string;        // aktueller Eingabewert im Artikel-Feld
  product: Product | null;  // aufgelöstes Produkt
  qty: string;          // Mengeneingabe (als String für Input)
}

// App state:
rows: OrderRow[]
focusTarget: { key: number, field: "article" | "qty" } | null
orderNote: string
submitted: boolean
```

---

## Design Tokens

```
Farben:
  --blue:          #1a6ef5   (primary, links, aktive Borders)
  --blue-hover:    #0f5ce0
  --bg:            #f0ede8   (Seiten-Hintergrund)
  --surface:       #ffffff
  --header-bg:     #1a1a1a
  --border:        #e0dcd6
  --border-light:  #e8e4de
  --text:          #1a1a1a
  --text-muted:    #888888
  --text-subtle:   #999999
  --text-disabled: #cccccc

Typografie:
  Font-Family: Helvetica Neue, Helvetica, Arial, sans-serif
  Basis: 13px
  Klein: 11–12px
  Groß (Betrag): 16px

Spacing:
  Seiten-Padding: 24px
  Zellen-Padding: 5px 0 (Inputs), 8px 6px (Header)
  Dropdown-Zeile: 6px 10px
```

---

## Datenbankanbindung

Der Prototyp nutzt eine statische Mock-Datenbank (`PRODUCTS[]`). In der echten Implementierung:

- Die Suchfunktion `searchProducts(query)` soll durch einen **API-Call** ersetzt werden (z.B. `GET /api/products?q=kugelschreiber&limit=8`)
- Die Suche sollte **debounced** werden (ca. 200ms)
- `findByArticleNumber(id)` → `GET /api/products/:id`
- Die bestehende Produktdatenbank / Tabelle soll angebunden werden

---

## Zu integrieren in

Das bestehende **Bestellformular** in der vorhandenen Codebase ersetzen oder erweitern. Die Schnellerfassungs-Tabelle soll das Herzstück der Bestellmaske sein.

---

## Dateien in diesem Paket

- `README.md` – diese Dokumentation
- `Schnellbestellung.html` – vollständig interaktiver Prototyp (im Browser öffnen!)

**→ Prototyp unbedingt im Browser öffnen und testen, bevor mit der Implementierung begonnen wird.**
