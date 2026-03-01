# PROJ-30: Admin: CMS-Seiten (Impressum, AGB, Landing Pages)

## Status: Planned
**Created:** 2026-03-01
**Last Updated:** 2026-03-01

## Dependencies
- None

## Beschreibung
Einfaches CMS für statische Seiten im Shop-Frontend: Impressum, AGB, Datenschutz, Über uns, Landing Pages. Admin kann Seiteninhalte (HTML oder Markdown) ohne Programmierkenntnisse bearbeiten. Seiten haben eine feste URL (Slug).

## User Stories
- Als Admin möchte ich den Inhalt des Impressums, der AGB und der Datenschutzerklärung im Admin bearbeiten, ohne Code anzufassen.
- Als Admin möchte ich neue statische Seiten (z.B. Landing Pages für Aktionen) anlegen.
- Als Admin möchte ich Seiten deaktivieren (nicht sichtbar im Shop) ohne sie zu löschen.
- Als Besucher möchte ich Impressum, AGB und Datenschutz über Links im Footer aufrufen.
- Als Admin möchte ich den Seiten-Slug (URL) festlegen.

## Acceptance Criteria
- [ ] **Seiten-Liste:** Alle CMS-Seiten mit Titel, Slug, Status (aktiv/inaktiv), zuletzt geändert
- [ ] **Seite anlegen:** Titel, Slug (auto-generiert aus Titel, editierbar), HTML-Inhalt (WYSIWYG-Editor), Meta-Titel, Meta-Beschreibung (für SEO), Status
- [ ] **Seite bearbeiten:** Alle Felder editierbar; Änderungen sofort aktiv nach Speichern
- [ ] **Seite deaktivieren:** Inaktive Seiten zeigen im Shop eine 404-Seite
- [ ] **Seite löschen:** Nur möglich wenn Seite nicht im Navigations-Footer verlinkt
- [ ] **Pflichtseiten:** Impressum, AGB, Datenschutz sind vordefiniert und können nicht gelöscht werden (nur Inhalt bearbeiten)
- [ ] **Frontend-Routing:** Seiten unter `/seite/{slug}` erreichbar; Inaktive → 404; nicht gefunden → 404
- [ ] **Footer-Links:** Im Shop-Footer werden Impressum, AGB und Datenschutz automatisch verlinkt
- [ ] **Versionierung:** Letzte 5 Versionen einer Seite werden gespeichert; Admin kann auf frühere Version zurücksetzen

## Edge Cases
- Slug existiert bereits → Validierungsfehler; Slug muss einmalig sein
- Slug wird nachträglich geändert → Alte URL liefert 301-Redirect auf neue URL (permanente Weiterleitung, 1 Ebene, kein Redirect-Loop)
- Admin löscht Pflichtseite (Impressum) → Verweigern mit Hinweis
- Seite mit leerem Inhalt → Erlaubt; wird im Frontend mit leerer Seite angezeigt

## Technical Requirements
- `cms_pages` Tabelle: `id`, `title`, `slug` (unique), `content_html`, `meta_title`, `meta_description`, `is_required` (BOOL), `status ENUM(active|inactive)`, `company_id`
- `cms_page_versions` Tabelle: `page_id`, `content_html`, `created_by`, `created_at` — nur letzte 5 aufbewahren
- WYSIWYG: TipTap oder Quill (im Admin-Frontend)
- Slug-Redirect: `cms_page_redirects` Tabelle: `old_slug`, `new_page_id`, `created_at`

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Komponenten-Struktur (UI-Baum)

```
/admin/cms/
│
├── index                   ← Seiten-Liste
│   ├── Tabelle: Titel | Slug | Status | Zuletzt geändert
│   └── [Neue Seite]
│
└── {id}/edit               ← Seite bearbeiten
    ├── Titel (Pflicht)
    ├── Slug (auto-generiert, editierbar; eindeutig)
    ├── Status (aktiv / inaktiv)
    ├── SEO: Meta-Titel, Meta-Beschreibung
    ├── WYSIWYG HTML-Editor (Hauptinhalt)
    ├── Versionsverlauf (letzte 5 Versionen → [Wiederherstellen])
    └── [Speichern]

Frontend (Shop):
└── /seite/{slug}           ← Öffentliche Seite
    ├── Aktiv   → Seiteninhalte anzeigen
    └── Inaktiv → 404

Footer (alle Shop-Seiten):
└── Links: Impressum | AGB | Datenschutz  ← automatisch aus Pflichtseiten
```

### Datenmodell

```
cms_pages
├── id, title, slug (unique), content_html
├── meta_title, meta_description
├── is_required  BOOL  ← Impressum/AGB/Datenschutz können nicht gelöscht werden
├── status  ENUM: active | inactive
└── company_id

cms_page_versions  [letzte 5 Versionen je Seite]
├── id, page_id → cms_pages
├── content_html (Snapshot)
├── created_by → users, created_at
└── (ältere Versionen werden beim Speichern gelöscht, FIFO)

cms_page_redirects  [Slug-Weiterleitungen]
├── old_slug (VARCHAR)
├── new_page_id → cms_pages
└── created_at
```

### Slug-Redirect-Logik

```
Wenn Slug einer Seite geändert wird:
  1. Alter Slug → cms_page_redirects (old_slug = alt, new_page_id = Seite)
  2. Neue Requests auf /seite/{old_slug} → 301 Redirect auf /seite/{new_slug}
  3. Prüfung auf Redirect-Loop: neuer Slug ≠ alter Slug eines bestehenden Redirects
```

### WYSIWYG-Editor

```
Admin-Frontend nutzt TipTap (ProseMirror-basiert):
  - Im Browser; kein Server-Rendering nötig
  - Exportiert sauberes HTML
  - Kein Inline-JS im generierten HTML → XSS-sicher
  - Inline-Bild-Upload → Laravel Storage; img-src aus Storage-URL
```

### Tech-Entscheidungen

| Entscheidung | Begründung |
|---|---|
| Eigenes CMS (kein WordPress-Plugin) | Shop-integriert; keine separate Wartung; Kunden-Context verfügbar |
| Versionierung (5 Versionen) | Undo ohne git; verhindert versehentlichen Inhaltsverlust |
| Pflicht-Seiten (is_required) | Impressum und AGB müssen immer erreichbar sein (rechtliche Anforderung) |
| 301-Redirect bei Slug-Änderung | SEO-freundlich; bestehende Links bleiben gültig |

### Neue Controller / Services

```
Admin\CmsSeiteController     ← index, create, store, edit, update, destroy
Shop\CmsSeiteController      ← show (öffentlich: /seite/{slug})
CmsRedirectMiddleware        ← prüft cms_page_redirects vor 404
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
