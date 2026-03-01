# Product Requirements Document — Kolabri Getränkeshop

## Vision
Kolabri ist eine vollständige E-Commerce- und Operations-Plattform für einen regionalen Getränke-Heimdienst.
Kunden (Privat, Büro, Gastronomie, Studentische Organisationen) können online bestellen oder im Lager/Markt abholen.
Das System digitalisiert den gesamten Betrieb: von der Bestellung über Tourenplanung und Lieferung bis zur Rechnungsstellung.

## Target Users

### Kunden (B2C / B2B)
- **Heimdienst-Kunden (Privat):** Bestellen Getränkekisten nach Hause, schätzen unkomplizierte Bestellung und schnelle Lieferung.
- **Büros:** Regelmäßige Großbestellungen, benötigen Sammelrechnungen, Unter-User (Besteller ≠ Rechnungsempfänger).
- **Gastronomie:** Professionelle Kunden mit eigenen Preiskonditionen, SEPA-Abbuchung, häufige Bestellungen aus Stammsortiment.
- **Studentische Organisationen:** Veranstaltungs-Bestellungen mit Festinventar (Leihgeräte), Liefer- und Abholtermin.

### Mitarbeiter / Fahrer
- Zugriff auf Tourenplanung, Bestellverwaltung, Rechnungen, Lagerverwaltung.
- Fahrer: Mobile-optimierte PWA für Offline-Tourenabwicklung (Lieferstatus, Fotos, Abweichungen).

### Admin
- Vollzugriff auf alle Bereiche: Stammdaten, Kunden, Lieferanten, Finanzen, Konfiguration, Benutzerrechte.

## Core Features (Roadmap)

| Priority | ID | Feature | Status |
|----------|-----|---------|--------|
| P0 (MVP) | PROJ-1 | Authentifizierung (Email/Passwort + Google OAuth) | Planned |
| P0 (MVP) | PROJ-2 | Produktkatalog (Browse, Filter, Suche, Gast-Preisgruppe) | Planned |
| P0 (MVP) | PROJ-3 | Warenkorb (Gast + Auth, Session) | Planned |
| P0 (MVP) | PROJ-4 | Checkout (Heimdienst, Abholung, Bestellbestätigung) | Planned |
| P0 (MVP) | PROJ-5 | Kundenkonto (Dashboard, Bestellhistorie, Rechnungen, Adressen) | Planned |
| P0 (MVP) | PROJ-6 | Preisfindung (3-stufig: Kunde → Gruppe → Basis) | Planned |
| P0 (MVP) | PROJ-7 | Pfand-System (Kalkulation, PfandSets, Gebinde) | Planned |
| P0 (MVP) | PROJ-8 | Zahlungsabwicklung (Stripe, PayPal, SEPA, Überweisung, Barzahlung, EC, Rechnung) | Planned |
| P0 (MVP) | PROJ-9 | Admin: Stammdaten (Produkte, Brands, Kategorien, Gebinde, Pfand, Warengruppen) | Planned |
| P0 (MVP) | PROJ-10 | Admin: Kundenverwaltung (CRUD, Kontakte, Adressen, Unterbenutzer, Rechte) | Planned |
| P0 (MVP) | PROJ-11 | Admin: Lieferantenverwaltung (CRUD, Kontakte, Lieferanten-Produkte) | Planned |
| P0 (MVP) | PROJ-12 | Admin: Bestellverwaltung (Liste, Detail, Bearbeiten, Status) | Planned |
| P0 (MVP) | PROJ-13 | Admin: Rechnungen (Draft, Finalize, PDF, Lexoffice-Sync) | Planned |
| P0 (MVP) | PROJ-14 | Admin: Regelmäßige Touren & Liefergebiete (CRUD) | Planned |
| P0 (MVP) | PROJ-15 | Admin: Fahrertouren-Planung (Erstellung, Aufteilung, Zuweisung) | Planned |
| P0 (MVP) | PROJ-16 | Fahrer-PWA (Bootstrap, Offline-Sync, Fulfillment, Foto-Upload) | Planned |
| P0 (MVP) | PROJ-17 | Admin: Dashboard (anpassbar, KPIs) | Planned |
| P0 (MVP) | PROJ-18 | Admin: Benutzer & Rollen (CRUD, Rechte-System) | Planned |
| P0 (MVP) | PROJ-19 | Admin: Einstellungen & Konfiguration | Planned |
| P1 | PROJ-20 | Stammsortiment (Kunden-Stammsortiment, Mindestbestand, Notizen) | Planned |
| P1 | PROJ-21 | Unterbenutzer & Kundenrechte (Büro-Accounts) | Planned |
| P1 | PROJ-22 | Veranstaltungsbestellungen + Festinventar (Leihinventar, Zeitfenster) | Planned |
| P1 | PROJ-23 | Admin: Lagerverwaltung (Warehouses, Stock, Bewegungen, UI) | Planned |
| P1 | PROJ-24 | Admin: CSV-Importe (Kunden, Produkte, Lieferanten, LMIV, etc.) | Planned |
| P1 | PROJ-25 | Admin: Berichte & Reports (Umsatz, Marge, Pfand, Tour-KPIs, CSV-Export) | Planned |
| P1 | PROJ-26 | Admin: Aufgabensystem (wiederkehrend, Verantwortliche, Dashboard) | Planned |
| P1 | PROJ-27 | Admin: Newsletter (Gruppen, Abmeldung, Versand) | Planned |
| P1 | PROJ-28 | Admin: Log & Audit (gefiltert, Dashboard-Ansicht) | Planned |
| P1 | PROJ-29 | Admin: Emails & Support (Posteingang, Tickets) | Planned |
| P1 | PROJ-30 | Admin: CMS-Seiten (Impressum, AGB, Landing Pages) | Planned |
| P1 | PROJ-31 | Mahnwesen (automatisch, Zahlungserinnerungen, Kontoübersicht) | Planned |
| P1 | PROJ-32 | Admin: Einkauf (PurchaseOrders, Einkaufs-Workflow) | Planned |
| P2 | PROJ-33 | POS-System / Kasse (Barcode, Sofortverkauf) | Planned |
| P2 | PROJ-34 | Umsatzmeldungen (pro Hersteller/Lieferant, Rhythmus, Email) | Planned |

## Success Metrics
- Kunden können eigenständig Bestellungen aufgeben (kein Telefonanruf nötig)
- Fahrer schließen Touren vollständig digital ab (kein Papier)
- Rechnungen werden automatisch nach Lieferung erstellt und per Email versendet
- Admin kann alle Stammdaten ohne Programmierkenntnisse pflegen
- System läuft auf Shared-Hosting (PHP 8.2+, MySQL)

## Constraints
- **Backend:** Laravel 12, PHP 8.2+, MySQL — bestehende Codebasis wird schrittweise migriert/erweitert
- **Frontend:** Next.js 16 (App Router), TypeScript, Tailwind CSS, shadcn/ui
- **Deployment:** Laravel auf Shared-Hosting (Internetwerk); Next.js auf Vercel
- **Multi-Mandanten:** company_id überall vorbereiten, aber MVP ist single-tenant (1 Firma)
- **Kein Queue-System:** Shared-Hosting ohne Cron — deferred_tasks als DB-basierte Queue
- **Liefergebiet:** Nur regionales Liefergebiet, kein bundesweiter Versand

## Non-Goals (für MVP)
- Bundesweiter Paketversand (DHL, DPD, etc.)
- Marketplace / Multi-Vendor
- Mobile-App (iOS/Android) — nur PWA
- B2B-Punchout / EDI-Schnittstellen
- Vollständiges Warenwirtschaftssystem
