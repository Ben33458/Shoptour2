# Shoptour2 — Kritische Code-Annahmen

> Dieses Dokument belegt nicht-offensichtliche Invarianten im Code, die Bugs verursachen,
> wenn sie falsch angewendet werden. Jede Aussage ist mit konkreter Datei/Zeile belegt.
>
> Stand: 2026-04-28 | Entstanden durch: vollständiges Geldfeld-Audit

---

## 1. Zwei verschiedene Basis-Punkte-Skalen — nicht verwechseln

Das System nutzt **zwei völlig verschiedene Basis-Punkte-Skalen**, die in unterschiedlichen Kontexten gelten.

### Skala A: Steuersätze (`10.000 = 100 %`)

| Wert | Bedeutung |
|------|-----------|
| `1_900` | 19 % MwSt |
| `700` | 7 % MwSt |

**Belege:**
- `database/seeders/TaxRateSeeder.php`: `['rate_basis_points' => 1900]` und `['rate_basis_points' => 700]`
- `app/Services/Pricing/PriceResolverService.php:122-156`: Berechnung `($netMilli * (10_000 + $taxRateBasisPoints)) / 10_000`
- Felder: `order_items.tax_rate_basis_points`, `invoice_items.tax_rate_basis_points`, `tax_rates.rate_basis_points`

### Skala B: Preisanpassungen in CustomerGroup (`1.000.000 = 100 %`)

| Wert | Bedeutung |
|------|-----------|
| `50_000` | 5 % Aufschlag |
| `1_000_000` | 100 % |

**Belege:**
- `app/Services/Pricing/PriceResolverService.php` (Kommentar + Anwendung)
- Feld: `customer_groups.price_adjustment_percent_basis_points`
- Anzeige in `resources/views/admin/customer-groups/index.blade.php:58`: `/ 10_000` (Skala-B-Wert → Prozentanzeige)

### Verwechslungsgefahr — historische Bugs in diesem Codestand

| Datei | Bug | Fix |
|-------|-----|-----|
| `app/Services/Integrations/LexofficeSync.php:71` | `/ 10_000` statt `/ 100` → Lexoffice erhielt 0,19 statt 19,00 für `taxRatePercentage` | Korrigiert auf `/ 100` |
| `app/Services/Admin/InvoiceService.php:117` | Fallback `190_000` statt `1_900` → 1.900 % MwSt auf Rechnungen ohne tax_rate | Korrigiert auf `1_900` |
| `resources/views/admin/customer-groups/index.blade.php:58` | `/ 100` statt `/ 10_000` → Prozentanzeige war 100× zu groß | Korrigiert auf `/ 10_000` |

Ursprung: Eine Migrations-Kommentarzeile in `2024_01_04_000005` beschrieb fälschlicherweise `190_000 = 19 %` — was Skala B ist, obwohl der Kontext Skala A meinte. Dieser Kommentar hat sich in Folgefehlern fortgepflanzt.

---

## 2. Geldfeld-Einheiten — bestätigte Feldnamen

**Einheit:** 1 EUR = 1.000.000 (Integer, kein Float, kein Decimal in DB)

### Bestätigte Feldnamen (Audit 2026-04-28)

| Tabelle | Feld | Einheit | Quelle |
|---------|------|---------|--------|
| `order_items` | `unit_price_net_milli` | Milli-Cent | `app/Models/Orders/OrderItem.php` |
| `order_items` | `unit_price_gross_milli` | Milli-Cent | `app/Models/Orders/OrderItem.php` |
| `order_items` | `unit_deposit_milli` | Milli-Cent | `app/Models/Orders/OrderItem.php` |
| `invoices` | `total_gross_milli` | Milli-Cent | `app/Services/Admin/InvoiceService.php` |
| `invoices` | `total_net_milli` | Milli-Cent | `app/Services/Admin/InvoiceService.php` |
| `invoice_items` | `line_total_gross_milli` | Milli-Cent | `app/Services/Admin/InvoiceService.php` |
| `invoice_items` | `cost_milli` | Milli-Cent (Einkaufspreis-Snapshot) | `app/Services/Admin/InvoiceService.php:121` |

**Nicht existierende Feldnamen** (früher in Docs fälschlich dokumentiert):
- ~~`unit_price_snapshot`~~ → heißt `unit_price_net_milli` + `unit_price_gross_milli`
- ~~`pfand_milli_cent`~~ → heißt `unit_deposit_milli`
- ~~`_milli_cent`-Suffix~~ → korrekter Suffix ist `_milli`

### Umrechnungen zu externen Systemen

| System | Umrechnung | Beleg |
|--------|-----------|-------|
| **Stripe** | `milli / 1_000 = Cent (Integer)` | `app/Services/Payments/StripeProvider.php:44-45` |
| **Stripe Webhook** | `stripe_cents * 1_000 = milli` | `app/Services/Payments/StripeProvider.php` |
| **PayPal** | `milli / 1_000_000 = EUR (2 Dezimalstellen)` | `app/Services/Payments/ShopPayPalService.php:57` |
| **Lexoffice** | `milli / 1_000_000 = EUR (Float, 6 Dezimalstellen)` | `app/Services/Integrations/LexofficeSync.php:70` |
| **Anzeige (Blade)** | `number_format($milli / 1_000_000, 2, ',', '.')` | Helper `milli_to_eur()` |

---

## 3. company_id: Middleware vorhanden, aber kein automatisches Query-Scoping

### Was vorhanden ist

`CompanyMiddleware` (`app/Http/Middleware/CompanyMiddleware.php`) bindet die aktuelle Firma via IoC:
```php
app()->instance('current_company', $company); // Company|null
```

Die Middleware ist auf Admin-Routen aktiv (`routes/web.php:275, 283, 917`).

### Was NICHT vorhanden ist

- Kein `GlobalScope` auf Eloquent-Models
- Keine automatische `WHERE company_id = ?`-Ergänzung
- Jeder Controller muss manuell filtern

### Betroffene Controller (Audit 2026-04-28)

| Controller | company_id-Filter? | Risiko |
|-----------|-------------------|--------|
| `AdminCustomerController` | ✓ Ja — `->where('company_id', $company?->id)` | Sicher |
| `AdminOrderController` | ✗ Nein — `Order::with(...)->paginate(25)` | Zeigt alle Mandanten |
| `AdminInvoiceController` | ✗ Nein — `Invoice::with(...)->paginate(25)` | Zeigt alle Mandanten |

Solange nur eine `company_id` aktiv ist (Single-Tenant-Betrieb), ist das Risiko gering. Sobald ein zweiter Mandant angelegt wird, **werden fremde Bestellungen und Rechnungen im Admin sichtbar**.

---

## 4. WaWi-Sync: Dynamische Tabellen und Monitoring

### Dynamische `wawi_dbo_*`-Tabellen

`wawi_dbo_*`-Tabellen werden **nicht durch Migrations** angelegt, sondern beim ersten Sync von `DynamicSyncService` erstellt:

```php
// app/Services/Wawi/DynamicSyncService.php:18
public function tableNameFor(string $entity): string
{
    // "dbo.POS_BonPosition" → "wawi_dbo_pos_bonposition"
    return 'wawi_' . strtolower(str_replace(['.', ' '], '_', $entity));
}
```

Diese Tabellen sind **nicht in der 238-Tabellen-Migrations-Gesamtzahl** enthalten.

### Monitoring: BonPosition-Sync

Ein fertiger Monitoring-Endpunkt existiert bereits:

```
GET /api/sync/state
```

Gibt die letzte Sync-Zeit aller WaWi-Entitäten zurück. Entity-Key für BonPosition:

```json
{ "dbo.POS_BonPosition": { "last_ts": "2026-04-27T22:14:00Z", "count": 14820 } }
```

**Empfohlener Alert:** Wenn `now() - last_ts > 24h` → Alarm. `stats_pos_daily` wird dann nicht mehr aktuell sein, und das Dashboard zeigt veraltete Umsatzzahlen.

---

## 5. Preis-Snapshot-Immutabilität

`order_items` speichert beim Anlegen:
- `unit_price_net_milli` — Netto-Einheitspreis zum Bestellzeitpunkt
- `unit_price_gross_milli` — Brutto-Einheitspreis zum Bestellzeitpunkt
- `unit_deposit_milli` — Pfand je Einheit zum Bestellzeitpunkt

Diese Felder sind **immutable nach dem Anlegen**. `OrderPricingService` schreibt sie einmalig. Spätere Preisänderungen in `product_prices` oder `customer_prices` dürfen diese Werte **niemals überschreiben**.

---

*Stand: 2026-04-28 | Basis: vollständige Code-Analyse (read-only)*
