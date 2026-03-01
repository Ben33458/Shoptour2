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
_To be added by /architecture_

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
