# Shoptour2 – Pragmatische Roadmap

Basierend auf dem tatsächlichen Codebestand (Stand April 2026). Keine Theorie — nur was der Code hergibt.

---

## Phase 0 — Stabilisierung (sofort, laufender Betrieb)

Diese Punkte blockieren oder gefährden den laufenden Betrieb.

### 0.1 POS_BonPosition-Sync sicherstellen
**Problem:** JTL WaWi pusht `dbo.POS_BonPosition` nicht zuverlässig. Letzte Lücke: 20.–27. April 2026.  
**Auswirkung:** `stats_pos_daily` veraltet → Dashboard-Widget zeigt falschen Umsatz.  
**Aktion:** WaWi-seitig prüfen, warum BonPosition aus dem Sync-Plan fällt. Ggf. Monitoring-Alert einrichten.  
**Aufwand:** klein (WaWi-Konfiguration, kein Laravel-Code)

### 0.2 Lexoffice-Zahlungsabgleich überwachen
**Problem:** `lexoffice:import-payments` läuft alle 5 Min, aber API-Timeouts möglich.  
**Aktion:** `wawi_sync_log`/`wawi_sync_state` auf Lücken prüfen; ggf. Retry-Logik ausbauen.  
**Aufwand:** klein

### 0.3 Admin-Footer Woche/KW — getestet ✅
Erledigt (April 2026).

### 0.4 stats:refresh-pos läuft täglich um 05:00
Erledigt, funktioniert. Bei Lücken manuell: `php artisan stats:refresh-pos --days=10`

---

## Phase 1 — Datenmodell und Kern-Prozesse (kurzfristig, 1–4 Wochen)

### 1.1 PROJ-5 — Kundenkonto fertigstellen
**Status:** Planned  
**Inhalt:** Dashboard, Bestellhistorie, Rechnungs-Download, Adressverwaltung  
**Abhängigkeit:** PROJ-4 (Checkout) ist In Review → kann sofort starten  
**Dateien:** `app/Http/Controllers/Shop/AccountController.php`, `resources/views/shop/account/`  
**Aufwand:** mittel

### 1.2 PROJ-8 — Zahlungsabwicklung
**Status:** Planned  
**Inhalt:** Stripe + PayPal für Shop-Checkout; Services (`ShopStripeService`, `ShopPayPalService`) sind bereits implementiert  
**Offen:** Webhook-Handler, Payment-Status-Sync, Rechnung-nach-Zahlung  
**Aufwand:** mittel–groß  
**Risiko:** mittel (Stripe/PayPal Testumgebung erforderlich)

### 1.3 Models für Tabellen ohne Model erstellen
**Betroffene Tabellen:** `categories`, `companies`, `product_mhd_batches`, `rental_item_categories`  
**Aufwand:** klein

### 1.4 Policies implementieren
**Problem:** Keine `app/Policies/`-Dateien vorhanden — Authorization nur über Middleware.  
**Risiko:** Fehlende company_id-Prüfung auf Modell-Ebene ist ein Sicherheitsrisiko.  
**Aktion:** `php artisan make:policy` für Order, Invoice, Customer, Product  
**Aufwand:** mittel

---

## Phase 2 — Kernprozesse ausbauen (mittelfristig, 1–2 Monate)

### 2.1 PROJ-16 — Fahrer-PWA vollständig
**Status:** Planned  
**Vorhanden:** `/public/driver/app.js`, `/public/driver/sw.js`, IndexedDB-Offline-Queue  
**Offen:** Vollständige Offline-Sync-Implementierung, PoD-Foto-Upload stabil, Kassenentnahme  
**Aufwand:** groß

### 2.2 PROJ-17 — Admin-Dashboard verbessern
**Status:** Planned  
**Vorhanden:** Grundlegendes Dashboard mit Quick-Stats, Schicht-Warnungen, Gekühlte-Kästen-Widget  
**Offen:** KPI-Tiles für Umsatz, offene Rechnungen, Tour-Übersicht, Bestellungen heute  
**Aufwand:** mittel

### 2.3 PROJ-18 — Rollen & Berechtigungen
**Status:** Planned  
**Inhalt:** UI für Rollenverwaltung, granulare Admin-Rechte  
**Aufwand:** mittel

### 2.4 PROJ-36 — Schichtplanung vollständig
**Status:** Planned  
**Vorhanden:** Shifts, TimeEntries, VacationRequests, ShiftReports — Tabellen und Models vorhanden  
**Offen:** UI für Schichtplanung, Wochensicht, Schicht-Tausch-Workflow  
**Aufwand:** mittel–groß

### 2.5 PROJ-35 — Kassenverwaltung
**Status:** Planned  
**Vorhanden:** `cash_registers`, `cash_transactions` Tabellen + Views (`admin/cash-registers/`)  
**Offen:** Kassenbuch-Export, Tagesabschluss, Überträge  
**Aufwand:** mittel

### 2.6 Wawi-Abgleich (Artikelnummern) stabilisieren
**Problem:** 12 Produkte haben noch falsche `N{ninox_id}`-Artikelnummern (Duplikate blockieren UPDATE).  
**Aktion:** Manuelle Prüfung und Bereinigung; SQL liegt bereits in Session-History.  
**Aufwand:** klein

---

## Phase 3 — Komfortfunktionen (mittelfristig, 2–3 Monate)

### 3.1 PROJ-19 — Admin-Einstellungen-UI
**Inhalt:** API-Keys, E-Mail-Config, Mahnwesen-Parameter, Vorlagen-Editor  
**Aufwand:** mittel

### 3.2 PROJ-25 — Berichte & Reports ausbauen
**Vorhanden:** `admin/statistics/` mit Artikel-, MHD-, Pfand-, POS-Auswertungen  
**Offen:** CSV-Export für alle Reports, Umsatz-Report, Margenbericht  
**Aufwand:** mittel

### 3.3 PROJ-27 — Newsletter
**Vorhanden:** `admin/communications/` — E-Mail-Import, Regeln, Kommunikations-Tags  
**Offen:** Newsletter-Versand, Abmelde-Link, Gruppen-Filter  
**Aufwand:** mittel

### 3.4 Primeur-Modul evaluieren
**Status:** Archiv-Daten migriert (`primeur_*`), eigene View-Sektion vorhanden  
**Frage:** Wird Primeur aktiv weitergeführt? Wenn nein → Views und Routen als deprecated markieren.  
**Aufwand:** klein (Entscheidung) → dann klein–mittel (Bereinigung)

### 3.5 Multi-Tenant aktivieren
**Vorhanden:** `company_id` auf allen Tabellen  
**Offen:** CompanyScope als GlobalScope auf alle Models, CompanyMiddleware aktivieren  
**Aufwand:** mittel  
**Risiko:** mittel (alle Queries müssen geprüft werden)

---

## Phase 4 — Automatisierung und KI-Unterstützung (langfristig, 3–6 Monate)

### 4.1 Bestellvorschläge automatisieren
**Vorhanden:** `BestellvorschlagService`, `PurchasePlanningService`, Command `kolabri:po:create`  
**Offen:** Automatischer wöchentlicher Bestellvorschlag per E-Mail, Lieferanten-Antwort-Import  
**Aufwand:** mittel

### 4.2 KI-gestützte Kommunikationszuordnung
**Vorhanden:** `RuleEngineService`, `GmailImportService`, `CommunicationConfidence`-Tabelle  
**Offen:** ML-basierte Ticket-Zuordnung (Sentiment, Priorität, Auto-Antworten)  
**Aufwand:** groß

### 4.3 Prognose-Engine (Schattenbestellungen)
**Idee:** Aus POS-Daten + Saisonalität → automatische Bestellvorschläge  
**Basis:** `stats_pos_daily` vorhanden, `PosStatisticsService::artikelWeeklyTrend()` implementiert  
**Aufwand:** groß

### 4.4 Fahrer-PWA: vollständige Offline-Navigation
**Offen:** Integration mit OpenRouteService oder ähnlichem für optimierte Tourenführung  
**Aufwand:** groß

---

## Empfohlene Reihenfolge (nächste 8 Wochen)

```
Woche 1–2: PROJ-5 (Kundenkonto) + 1.3 (fehlende Models)
Woche 3–4: PROJ-8 (Zahlungen, Stripe/PayPal)
Woche 5–6: PROJ-17 (Dashboard) + 1.4 (Policies)
Woche 7–8: PROJ-36 (Schichtplanung-UI) oder PROJ-16 (Fahrer-PWA)
```

---

*Erstellt: 2026-04-28 | Basis: Code-Analyse read-only*
