# PROJ-39 — Kassenbericht / UST-Voranmeldung

**Status:** In Progress
**Erstellt:** 2026-05-21

## Ziel

Monatliche Kassenbericht-Seite im Admin-Bereich für die UST-Voranmeldung.
Datenquelle: `stats_pos_daily` (aggregiert aus JTL WAWI LS POS via `wawi_dbo_pos_bon/bonposition`).

## User Story

Als Betreiber möchte ich einmal im Monat einen Kassenbericht aufrufen, der meinen Umsatz
nach MwSt-Sätzen (19%/7%) aufschlüsselt, damit ich die Zahlen direkt in die UST-Voranmeldung
eintragen kann.

## Acceptance Criteria

- [ ] `/admin/kassenbericht` zeigt Monatsauswahl und Bericht-Tabelle
- [ ] Monatsauswahl: Default = aktueller Monat, beliebiger Monat wählbar
- [ ] Tabelle zeigt: Steuersatz | Brutto | Netto | MwSt | Menge
- [ ] Pfand-Einnahmen und Leergut-Rücknahmen als separate Zeilen
- [ ] Summen-Zeile am Ende
- [ ] Button "PDF herunterladen" → `kassenbericht-YYYY-MM.pdf`
- [ ] Wenn keine Daten: leere Tabelle mit Hinweis (kein Fehler)
- [ ] MwSt-Rate: über `products.tax_rate_id` → `tax_rates.rate_basis_points`, Fallback 19%

## Technische Details

### Datenquelle
- `stats_pos_daily` (täglich aggregiert via `php artisan stats:refresh-pos`)
- JOIN → `products.artikelnummer` → `tax_rates.rate_basis_points`
- `fEinzelPreis` = Brutto-VK-Preis → Netto = Brutto × 10000 / (10000 + mwst_bp)

### Neue Dateien
- `app/Services/Admin/KassenberichtService.php`
- `app/Http/Controllers/Admin/KassenberichtController.php`
- `resources/views/admin/kassenbericht/index.blade.php`
- `resources/views/pdf/kassenbericht.blade.php`

### Route
- `GET /admin/kassenbericht` → `admin.kassenbericht`
- `GET /admin/kassenbericht/pdf` → `admin.kassenbericht.pdf`
