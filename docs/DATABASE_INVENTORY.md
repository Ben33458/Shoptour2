# Shoptour2 — Datenbank-Inventar

> Vollständige Inventarisierung aller Tabellen, Models, Migrations und Beziehungen.  
> Stand: April 2026 | Basis: 258 Migrationsdateien

---

## Zusammenfassung

| Kennzahl | Wert |
|----------|------|
| Migrations-Dateien | 258 |
| App-Tabellen (Kernlogik) | ~155 |
| WaWi-Import-Tabellen (`wawi_*`) | ~16 |
| WaWi-DBO-Tabellen (Push via API) | ~14 |
| Ninox-Import-Tabellen (`ninox_*`) | ~44 |
| Lexoffice-Sync-Tabellen (`lexoffice_*`) | 11 |
| Primeur-Archiv-Tabellen (`primeur_*`) | 9 |
| System-Tabellen | 5 |
| Eloquent-Models | 167 |
| Model-Namespaces | 19 Verzeichnisse |

---

## 1. Kern-App-Tabellen (nach Modul)

### 1.1 Benutzerverwaltung & Auth

| Tabelle | Model | Migration | Beschreibung |
|---------|-------|-----------|-------------|
| `users` | `User` | `0001_01_01_000000` | Benutzerkonten (role: admin, mitarbeiter, kunde, sub_user) |
| `companies` | `Company` | `2024_01_12_000001` | Mandanten (Multi-Tenant vorbereitet, MVP = 1) |
| `sub_users` | `SubUser` | `2026_03_30_200001` | Unter-Benutzer von Büro-Kunden; `permissions` JSON |
| `sub_user_invitations` | `SubUserInvitation` | `2026_03_30_200002` | Einladungs-Tokens für Sub-User |
| `customer_activation_tokens` | `CustomerActivationToken` | `2026_04_09_000001` | E-Mail-Aktivierungslinks |
| `device_preferences` | `DevicePreference` | `2026_03_22_200000` | Gerätespezifische Einstellungen (Dark Mode, etc.) |
| `onboarding_tokens` | `OnboardingToken` | `2026_03_22_400004` | Mitarbeiter-Onboarding-Links |

### 1.2 Stammdaten — Produkte

| Tabelle | Model | Migration | Beschreibung |
|---------|-------|-----------|-------------|
| `products` | `Product` (App\Models\Catalog) | `2024_01_01_000009` | Kern-Stammdaten; Felder: `produktname`, `artikelnummer`, `mwst_satz` (Basispunkte) |
| `product_images` | `ProductImage` | `2024_01_21_000003` | Produktfotos; `path` für Storage::url() |
| `product_lines` | `ProductLine` | `2024_01_01_000002` | Produktlinien (Varianten-Gruppe) |
| `product_components` | `ProductComponent` | `2024_01_01_000010` | Stücklisten-Komponenten |
| `product_barcodes` | `ProductBarcode` | `2024_01_01_000011` | EAN/GTIN-Codes |
| `product_lmiv_versions` | `ProductLmivVersion` | `2024_01_15_000002` | Lebensmittel-Informations-Versionen |
| `product_mhd_batches` | `ProductMhdBatch` | `2026_04_13_100010` | MHD-Chargen (Mindesthaltbarkeit) |
| `product_leergut` | `ProductLeergut` | `2026_03_28_100001` | Leergut-Verknüpfungen |
| `product_write_offs` | `ProductWriteOff` | `2026_04_13_100012` | Abschreibungen/Verluste |
| `artikel_verpackungseinheiten` | — | `2026_04_23_100001` | Verpackungseinheiten pro Artikel |
| `artikel_mindestbestaende` | — | `2026_04_23_100003` | Mindestbestände pro Artikel/Lager |
| `mhd_regeln` | — | `2026_04_23_100008` | MHD-Warnregeln |
| `ladenhueter_regeln` | — | `2026_04_23_100009` | Ladenhüter-Erkennungsregeln |
| `ladenhueter_status` | — | `2026_04_23_100009` | Ladenhüter-Status je Artikel |
| `reconcile_product_rules` | `ReconcileProductRule` | `2026_04_16_080958` | WaWi-Abgleich-Regeln |

### 1.3 Stammdaten — Katalog

| Tabelle | Model | Migration | Beschreibung |
|---------|-------|-----------|-------------|
| `brands` | `Brand` | `2024_01_01_000001` | Marken/Hersteller |
| `categories` | `Category` | `2024_01_01_000003` | Produktkategorien |
| `warengruppen` | `Warengruppe` | `2026_03_01_000001` | Warengruppen (höheres Niveau als Kategorien) |
| `gebinde` | `Gebinde` | `2024_01_01_000007` | Gebinde-Definitionen |
| `gebinde_components` | `GebindeComponent` | `2024_01_01_000008` | Gebinde-Stücklisten |
| `pfand_items` | `PfandItem` | `2024_01_01_000004` | Einzelne Pfand-Artikel |
| `pfand_sets` | `PfandSet` | `2024_01_01_000005` | Pfand-Kombinationen |
| `pfand_set_components` | `PfandSetComponent` | `2024_01_01_000006` | Pfand-Set-Stücklisten |
| `tax_rates` | `TaxRate` | `2024_01_19_000001` | Steuersätze (Basispunkte) |
| `pages` | `Page` | `2026_02_27_000001` | CMS-Seiten (Impressum, AGB, Landing Pages) |
| `app_settings` | `AppSetting` | `2024_01_02_000001` | Systemeinstellungen (key/value) |

### 1.4 Stammdaten — Kunden

| Tabelle | Model | Migration | Beschreibung |
|---------|-------|-----------|-------------|
| `customers` | `Customer` (App\Models\Pricing) | `2024_01_02_000003` | Kunden-Stammdaten; `price_display_mode`, Lexoffice-ID |
| `customer_groups` | `CustomerGroup` | `2024_01_02_000002` | Preisgruppen; `allowed_payment_methods` JSON |
| `customer_prices` | `CustomerPrice` | `2024_01_02_000005` | Individuelle Kundenpreise (Milli-Cent) |
| `customer_group_prices` | `CustomerGroupPrice` | `2024_01_02_000004` | Gruppen-Preise (Milli-Cent) |
| `customer_notes` | `CustomerNote` | `2026_03_16_055324` | interne Kundennotizen |
| `customer_favorites` | `CustomerFavorite` | `2026_03_30_233307` | Favoriten-Listen der Kunden |
| `customer_tour_orders` | `CustomerTourOrder` | `2024_01_05_000003` | Kundenzuordnung zu Regeltouren |
| `contacts` | `Contact` | `2024_01_20_000002` | Kontakte (polymorphisch: Kunde, Lieferant) |
| `addresses` | `Address` | `2024_01_21_000002` | Adressen (polymorphisch; `delivery_note`) |
| `source_matches` | `SourceMatch` | `2026_03_20_000002` | WaWi/Ninox → Kunden-Zuordnungsregeln |

### 1.5 Stammdaten — Lieferanten

| Tabelle | Model | Migration | Beschreibung |
|---------|-------|-----------|-------------|
| `suppliers` | `Supplier` | `2024_01_12_000003` | Lieferanten-Stammdaten |
| `supplier_products` | `SupplierProduct` | `2024_01_12_000004` | Lieferanten-Artikel-Verknüpfung |
| `supplier_order_profiles` | `SupplierOrderProfile` | `2026_04_13_100003` | Bestell-Profile pro Lieferant |
| `supplier_document_parsers` | `SupplierDocumentParser` | `2026_04_13_100004` | Parsing-Regeln für Lieferantendokumente |

### 1.6 Pricing

| Tabelle | Model | Migration | Beschreibung |
|---------|-------|-----------|-------------|
| `customer_prices` | `CustomerPrice` | — | Individuelle Kundenpreise |
| `customer_group_prices` | `CustomerGroupPrice` | — | Gruppenpreise |

**Preisfindungs-Reihenfolge:** Kundenpreis → Gruppenpreis → Produktlistenpreis → Aufschlag

### 1.7 Warenkorb & Shop

| Tabelle | Model | Migration | Beschreibung |
|---------|-------|-----------|-------------|
| `carts` | `Cart` (App\Models\Shop) | `2026_03_01_100001` | Warenkörbe (Session-basiert) |
| `cart_items` | `CartItem` | `2026_03_01_100002` | Warenkorb-Positionen |
| `order_number_sequences` | — | `2026_03_02_000005` | Fortlaufende Bestellnummern |

### 1.8 Bestellungen

| Tabelle | Model | Migration | Beschreibung |
|---------|-------|-----------|-------------|
| `orders` | `Order` (App\Models\Orders) | `2024_01_04_000001` | Bestellungen; `delivery_type`, `placed_by` |
| `order_items` | `OrderItem` | `2024_01_04_000002` | **Preis-Snapshot** beim Anlegen! Felder: `unit_price_net_milli`, `unit_price_gross_milli`, `unit_deposit_milli` |
| `order_item_components` | `OrderItemComponent` | `2024_01_04_000003` | Gebinde-Positionen |
| `order_adjustments` | `OrderAdjustment` | `2024_01_11_000002` | Rabatte, Aufschläge |
| `fulfillment_events` | `FulfillmentEvent` | `2024_01_05_000007` | Liefer-Ereignisse |
| `order_item_fulfillments` | `OrderItemFulfillment` | `2024_01_05_000008` | Positions-Lieferstatus |

### 1.9 Rechnungen & Zahlungen

| Tabelle | Model | Migration | Beschreibung |
|---------|-------|-----------|-------------|
| `invoices` | `Invoice` (App\Models\Admin) | `2024_01_11_000003` | Rechnungen; `lexoffice_id`, `pdf_path` |
| `invoice_items` | `InvoiceItem` | `2024_01_11_000004` | Rechnungspositionen |
| `payments` | `Payment` | `2024_01_11_000005` | Zahlungen (Stripe, PayPal, SEPA) |
| `audit_logs` | `AuditLog` | `2024_01_11_000006` | Änderungsprotokoll (mit `level`) |
| `documents` | `Document` | `2026_04_13_100006` | Dokumente (Rechnungen, Lieferscheine) |
| `document_assignment_rules` | `DocumentAssignmentRule` | `2026_04_13_100007` | Zuordnungsregeln für Dokumente |

### 1.10 Mahnwesen

| Tabelle | Model | Migration | Beschreibung |
|---------|-------|-----------|-------------|
| `dunning_runs` | `DunningRun` (App\Models\Debtor) | `2026_04_05_100004` | Mahnläufe |
| `dunning_run_items` | `DunningRunItem` | `2026_04_05_100005` | Mahnpositionen (inkl. Zinsen) |
| `debtor_notes` | `DebtorNote` | `2026_04_05_100003` | Schuldner-Notizen |

### 1.11 Touren & Lieferung

| Tabelle | Model | Migration | Beschreibung |
|---------|-------|-----------|-------------|
| `regular_delivery_tours` | `RegularDeliveryTour` (App\Models\Delivery) | `2024_01_05_000001` | Regel-Touren (wöchentlich, PLZ) |
| `delivery_areas` | `DeliveryArea` | `2024_01_05_000002` | Liefergebiete |
| `tours` | `Tour` | `2024_01_05_000005` | Fahrtouren (konkret, datumsbezogen) |
| `tour_stops` | `TourStop` | `2024_01_05_000006` | Haltepunkte (Reihenfolge, Status) |
| `delivery_returns` | `DeliveryReturn` (App\Models\Rental) | `2026_03_31_100017` | Rücknahme bei Lieferung |
| `delivery_return_items` | `DeliveryReturnItem` | `2026_03_31_100018` | Rücknahme-Positionen |

### 1.12 Fahrer-PWA

| Tabelle | Model | Migration | Beschreibung |
|---------|-------|-----------|-------------|
| `driver_api_tokens` | `DriverApiToken` (App\Models\Driver) | `2024_01_06_000001` | Sanctum-ähnliche Tokens für Fahrer-App |
| `driver_events` | `DriverEvent` | `2024_01_06_000002` | Fahrer-Ereignisse (Scan, Foto, etc.) |
| `driver_uploads` | `DriverUpload` | `2024_01_06_000003` | Foto-Uploads (PoD) |

### 1.13 Kassenverwaltung

| Tabelle | Model | Migration | Beschreibung |
|---------|-------|-----------|-------------|
| `cash_registers` | `CashRegister` (App\Models\Driver) | (vor 2026_03_28) | Kassenregister; `register_type` |
| `cash_transactions` | `CashTransaction` | (vor 2026_03_28) | Kassenbuchungen; `category` |

### 1.14 Personal & Schichten

| Tabelle | Model | Migration | Beschreibung |
|---------|-------|-----------|-------------|
| `employees` | `Employee` (App\Models\Employee) | `2026_03_22_100000` | Mitarbeiter-Stammdaten |
| `shift_areas` | `ShiftArea` | `2026_03_22_101000` | Schicht-Bereiche |
| `shifts` | `Shift` | `2026_03_22_102000` | Schichten |
| `time_entries` | `TimeEntry` | `2026_03_22_103000` | Zeiterfassung |
| `break_segments` | `BreakSegment` | `2026_03_22_104000` | Pausen innerhalb Zeiterfassung |
| `checklist_templates` | `ChecklistTemplate` | `2026_03_22_105000` | Schicht-Checklisten-Vorlagen |
| `checklist_items` | `ChecklistItem` | `2026_03_22_106000` | Checklisten-Punkte |
| `shift_reports` | `ShiftReport` | `2026_03_22_107000` | Schichtberichte |
| `shift_report_checklist` | — | `2026_03_22_108000` | Pivot: Schichtbericht ↔ Checkliste |
| `employee_tasks` | `EmployeeTask` | `2026_03_22_109000` | Aufgaben (mit Zeiterfassung) |
| `employee_task_comments` | `EmployeeTaskComment` | `2026_03_25_100003` | Aufgaben-Kommentare |
| `vacation_requests` | `VacationRequest` | `2026_03_22_110000` | Urlaubsanträge |
| `vacation_balances` | `VacationBalance` | `2026_03_22_111000` | Urlaubskonten |
| `public_holidays` | `PublicHoliday` | `2026_03_22_112000` | Feiertage |
| `availability_blocks` | `AvailabilityBlock` | `2026_03_22_113000` | Verfügbarkeitssperren |
| `system_logs` | `SystemLog` | `2026_03_22_114000` | System-Logs |
| `sent_employee_emails` | `SentEmployeeEmail` | `2026_03_22_620000` | Versendete E-Mails an Mitarbeiter |
| `employee_login_security` | `LoginSecurity` | `2026_03_22_400005` | Login-Sicherheit (2FA, PIN) |
| `employee_feedbacks` | `EmployeeFeedback` | — | Mitarbeiter-Feedback |
| `employee_notifications` | `EmployeeNotification` | — | Push-Benachrichtigungen |
| `recurring_task_completions` | `RecurringTaskCompletion` | `2026_03_20_220000` | Abgeschlossene Wiederholungsaufgaben |
| `recurring_task_settings` | `RecurringTaskSetting` | `2026_03_20_230000` | Einstellungen Wiederholungsaufgaben |

### 1.15 Lagerverwaltung & Einkauf

| Tabelle | Model | Migration | Beschreibung |
|---------|-------|-----------|-------------|
| `warehouses` | `Warehouse` (App\Models\Inventory) | `2024_01_03_000001` | Lagerorte |
| `product_stocks` | `ProductStock` | `2024_01_03_000002` | Aktueller Bestand je Produkt/Lager |
| `stock_movements` | `StockMovement` | `2024_01_03_000003` | Lagerbewegungen (mit MHD, Korrekturgrund) |
| `purchase_orders` | `PurchaseOrder` (App\Models\Supplier) | `2024_01_12_000006` | Einkaufsbestellungen |
| `purchase_order_items` | `PurchaseOrderItem` | `2024_01_12_000007` | Einkaufspositionen |
| `goods_receipts` | `GoodsReceipt` (App\Models\Procurement) | `2026_04_13_100008` | Wareneingänge |
| `goods_receipt_items` | `GoodsReceiptItem` | `2026_04_13_100009` | Wareneingang-Positionen |
| `leergut_returns` | `LeergutReturn` | `2026_04_13_100013` | Leergut-Rücknahmen |
| `leergut_return_items` | `LeergutReturnItem` | `2026_04_13_100014` | Leergut-Positionen |
| `bestandsaufnahme_sessions` | — | `2026_04_23_100004` | Inventur-Sitzungen |
| `bestandsaufnahme_positionen` | — | `2026_04_23_100005` | Inventur-Positionen |
| `bestandsaufnahme_position_eingaben` | — | `2026_04_23_100006` | Inventur-Eingaben |

### 1.16 Kommunikation

| Tabelle | Model | Migration | Beschreibung |
|---------|-------|-----------|-------------|
| `communications` | `Communication` (App\Models\Communications) | `2026_03_22_300000` | E-Mail-Tickets (Gmail-Import) |
| `communication_confidence` | `CommunicationConfidence` | `2026_03_22_300001` | KI-Zuordnungs-Confidence |
| `communication_attachments` | `CommunicationAttachment` | `2026_03_22_300002` | E-Mail-Anhänge |
| `communication_rules` | `CommunicationRule` | `2026_03_22_300003` | Auto-Klassifizierungsregeln |
| `communication_tags` | `CommunicationTag` | `2026_03_22_300004` | Tags/Labels |
| `communication_tag_pivot` | — | `2026_03_22_300005` | Pivot: Communication ↔ Tag |
| `communication_audit` | `CommunicationAudit` | `2026_03_22_300006` | Audit-Trail für Tickets |
| `gmail_sync_state` | `GmailSyncState` | `2026_03_22_300007` | Gmail-Import-Status |

### 1.17 Veranstaltungen & Rental

| Tabelle | Model | Migration | Beschreibung |
|---------|-------|-----------|-------------|
| `rental_item_categories` | `RentalItemCategory` (App\Models\Rental) | `2026_03_31_100001` | Leihartikel-Kategorien |
| `rental_items` | `RentalItem` | `2026_03_31_100002` | Leihartikel (Ausschank, Zelte, etc.) |
| `rental_inventory_units` | `RentalInventoryUnit` | `2026_03_31_100003` | Leihinventar-Einheiten |
| `rental_packaging_units` | `RentalPackagingUnit` | `2026_03_31_100004` | Verpackungseinheiten |
| `rental_time_models` | `RentalTimeModel` | `2026_03_31_100005` | Zeitmodelle (Stunden, Tage) |
| `rental_price_rules` | `RentalPriceRule` | `2026_03_31_100006` | Preisregeln |
| `rental_components` | `RentalComponent` | `2026_03_31_100007` | Leihartikel-Komponenten |
| `event_locations` | `EventLocation` (App\Models\Event) | `2026_03_31_100008` | Veranstaltungsorte |
| `rental_booking_items` | `RentalBookingItem` | `2026_03_31_100010` | Buchungs-Positionen |
| `rental_booking_allocations` | `RentalBookingAllocation` | `2026_03_31_100011` | Inventar-Zuteilungen |
| `damage_tariffs` | `DamageTariff` | `2026_03_31_100012` | Schadenstarife |
| `cleaning_fee_rules` | `CleaningFeeRule` | `2026_03_31_100013` | Reinigungsgebühren |
| `deposit_rules` | `DepositRule` | `2026_03_31_100014` | Pfandregeln für Rental |
| `rental_return_slips` | `RentalReturnSlip` | `2026_03_31_100015` | Rückgabe-Belege |
| `rental_return_slip_items` | `RentalReturnSlipItem` | `2026_03_31_100016` | Rückgabe-Positionen |

### 1.18 Fahrzeuge & Assets

| Tabelle | Model | Migration | Beschreibung |
|---------|-------|-----------|-------------|
| `vehicles` | — | `2026_03_31_100019` | Fahrzeuge |
| `vehicle_documents` | — | `2026_03_31_100020` | Fahrzeug-Dokumente (TÜV, etc.) |
| `asset_issues` | — | `2026_03_31_100021` | Asset-Schäden/Mängel |

### 1.19 Statistiken

| Tabelle | Model | Migration | Beschreibung |
|---------|-------|-----------|-------------|
| `stats_pos_daily` | — | `2026_04_15_000001` | Tägliche POS-Statistiken (aggregiert via `stats:refresh-pos`) |

### 1.20 System-Tabellen

| Tabelle | Model | Migration | Beschreibung |
|---------|-------|-----------|-------------|
| `deferred_tasks` | `DeferredTask` | `2024_01_18_000002` | DB-Queue-Ersatz (kein Redis) |
| `wawi_sync_log` | — | `2026_03_24_200000` | WaWi-Sync-Protokoll |
| `wawi_sync_state` | — | `2026_04_20_183844` | WaWi-Sync-Status (letzte Sync-Zeit) |
| `reconcile_feedback_log` | `ReconcileFeedbackLog` | `2026_03_20_124453` | Abgleich-Feedback |
| `cache` | — | `0001_01_01_000001` | Laravel Cache (DB-Treiber) |
| `jobs` | — | `0001_01_01_000002` | Laravel Jobs (unused — deferred_tasks stattdessen) |
| `import_bestandsaufnahme_laeufe` | `ImportBestandsaufnahmeLauf` | `2026_04_23_100010` | Inventur-Import-Läufe |
| `import_bestandsaufnahme_mappings` | `ImportBestandsaufnahmeMapping` | — | Inventur-Import-Mappings |
| `import_bestandsaufnahme_rohzeilen` | `ImportBestandsaufnahmeRohzeile` | — | Inventur-Import-Rohdaten |
| `import_bestandsaufnahme_konflikte` | `ImportBestandsaufnahmeKonflikt` | — | Inventur-Import-Konflikte |

---

## 2. WaWi-Import-Tabellen (NUR LESEN!)

> **WICHTIG:** Diese Tabellen werden bei jedem JTL WaWi-Sync überschrieben. Keine eigene Logik hier!

### 2.1 WaWi App-Import (`wawi_*` ohne `dbo`)

| Tabelle | Beschreibung |
|---------|-------------|
| `wawi_artikel` | Artikel aus WaWi |
| `wawi_kunden` | Kunden aus WaWi |
| `wawi_auftraege` | Aufträge aus WaWi |
| `wawi_auftragspositionen` | Auftragspositionen |
| `wawi_rechnungen` | Rechnungen aus WaWi |
| `wawi_lagerbestand` | Lagerbestand WaWi |
| `wawi_warenlager` | Warenlager-Daten |
| `wawi_zahlungen` | Zahlungen |
| `wawi_rechnung_zahlungen` | Rechnungs-Zahlungen |
| `wawi_artikel_attribute` | Artikel-Attribute |
| `wawi_hersteller` | Hersteller |
| `wawi_kategorien` | Kategorien |
| `wawi_kategorien_artikel` | Pivot: Kategorien ↔ Artikel |
| `wawi_zahlungsarten` | Zahlungsarten |
| `wawi_versandarten` | Versandarten |
| `wawi_preise` | Preise |

### 2.2 WaWi DBO-Tabellen (Push via API, `POST /api/sync`)

Diese Tabellen empfangen Push-Daten direkt von JTL WaWi über das API (`WAWI_SYNC_TOKEN`).

| Tabelle (bekannt) | Beschreibung |
|------------------|-------------|
| `wawi_dbo_pos_bon` | POS-Kassenbons |
| `wawi_dbo_pos_bonposition` | Kassenpositionen (**kritisch für stats_pos_daily**) |
| `wawi_dbo_tartikel` | Artikel-Stamm |
| `wawi_dbo_tliefartikel` | Lieferanten-Artikel |
| `wawi_dbo_tkunde` | Kunden |
| `wawi_dbo_tzahlung` | Zahlungen |
| weitere | Weitere DBO-Tabellen (aus wawi_sync_log.table_name) |

**Monitoring:** `wawi_sync_state` speichert letzten erfolgreichen Sync. Bei Lücke in `wawi_dbo_pos_bonposition` → `stats_pos_daily` veraltet → manuell: `php artisan stats:refresh-pos --days=10`

---

## 3. Ninox-Import-Tabellen (NUR LESEN!)

Alle `ninox_*`-Tabellen werden bei jedem Ninox-Import überschrieben.

| Tabelle | Beschreibung |
|---------|-------------|
| `ninox_veranstaltung` | Veranstaltungen |
| `ninox_veranstaltungstage` | Veranstaltungstage |
| `ninox_veranstaltungsjahr` | Jahreskalender |
| `ninox_aufgaben` | Aufgaben |
| `ninox_kontakte` | Kontakte |
| `ninox_dokumente` | Dokumente |
| `ninox_77_regelmaessige_aufgaben` | Wiederkehrende Aufgaben |
| `ninox_done_history` | Erledigungs-History |
| `ninox_mitarbeiter` | Mitarbeiter |
| `ninox_lieferanten` | Lieferanten |
| `ninox_kunden` | Kunden |
| `ninox_schluessel` | Schlüssel |
| `ninox_fest_inventar` | Fest-Inventar |
| `ninox_dokument` | Dokumente (einzeln) |
| `ninox_bestellung` | Bestellungen |
| `ninox_kassenbuch` | Kassenbuch |
| `ninox_regelmaessige_touren` | Regeltouren |
| `ninox_bestellannahme` | Bestellannahme |
| `ninox_liefer_tour` | Liefertouren |
| `ninox_pfand_ruecknahme` | Pfand-Rücknahmen |
| `ninox_hassia_rechner` | Hassia-Mengenrechner |
| `ninox_lieferadressen` | Lieferadressen |
| `ninox_kassen_umsatz` | Kassenumsatz |
| `ninox_marktbestand` | **Lagerbestand** (wichtigste Ninox-Tabelle) |
| `ninox_benachrichtigungen` | Benachrichtigungen |
| `ninox_schichtbericht` | Schichtberichte |
| `ninox_abbuchungen` | Abbuchungen |
| `ninox_sepa_mandat` | SEPA-Mandate |
| `ninox_warenkorb_artikel` | Warenkorb-Artikel |
| `ninox_belohnung` | Belohnungen |
| `ninox_wasser` | Wasserdaten |
| `ninox_festbedarf_warenkorb` | Festbedarf-Warenkorb |
| `ninox_warengruppe` | Warengruppen |
| `ninox_stammsortiment` | Stammsortiment |
| `ninox_arbeitsmaterial` | Arbeitsmaterial |
| `ninox_pausen` | Pausen |
| `ninox_monatsuebersicht` | Monatsübersicht |
| `ninox_log` | Log |
| `ninox_fahrzeug` | Fahrzeuge |
| `ninox_zahlungen` | Zahlungen |
| `ninox_buchhaltungs_dashboard` | Buchhaltungs-Dashboard |
| `ninox_kunden_historie` | Kunden-Historie |
| `ninox_buchhaltungsuebersicht` | Buchhaltungsübersicht |
| `ninox_import_runs` | Import-Läufe |
| `ninox_import_tables` | Import-Tabellen-Meta |
| `ninox_raw_records` | Roh-Datensätze |

---

## 4. Lexoffice-Sync-Tabellen (NUR LESEN für Stammdaten!)

| Tabelle | Model | Beschreibung |
|---------|-------|-------------|
| `lexoffice_vouchers` | `LexofficeVoucher` | Belege (Rechnungen, Gutschriften); `pdf_path`, Dunning-Status |
| `lexoffice_contacts` | `LexofficeContact` | Kontakte aus Lexoffice |
| `lexoffice_articles` | `LexofficeArticle` | Artikel |
| `lexoffice_payment_conditions` | `LexofficePaymentCondition` | Zahlungsbedingungen |
| `lexoffice_posting_categories` | `LexofficePostingCategory` | Buchungskategorien |
| `lexoffice_print_layouts` | `LexofficePrintLayout` | Drucklayouts |
| `lexoffice_recurring_templates` | `LexofficeRecurringTemplate` | Wiederkehrende Vorlagen |
| `lexoffice_countries` | `LexofficeCountry` | Länder |
| `lexoffice_payments` | `LexofficePayment` | Zahlungen (für Abgleich) |
| `lexoffice_import_runs` | — | Import-Läufe |
| `lexoffice_blocks` | — | Gesperrte Lexoffice-Objekte |

---

## 5. Primeur-Archiv-Tabellen (inaktiv / unklar ob weitergeführt)

| Tabelle | Beschreibung |
|---------|-------------|
| `primeur_import_runs` | Import-Läufe |
| `primeur_source_files` | Quelldateien |
| `primeur_customers` | Primeur-Kunden |
| `primeur_articles` | Primeur-Artikel |
| `primeur_orders` | Primeur-Bestellungen |
| `primeur_order_items` | Bestellpositionen |
| `primeur_cash_receipts` | Kassenbelege |
| `primeur_cash_receipt_items` | Belegpositionen |
| `primeur_cash_daily` | Tageskassen |
| `primeur_cash_sessions` | Kassensitzungen |

> Entscheidung offen: Aktiv weiterführen oder als deprecated markieren?

---

## 6. Eloquent-Model-Namespaces

| Namespace | Pfad | Inhalt |
|-----------|------|--------|
| `App\Models` | `app/Models/` | User, Company, Contact, Address, ... |
| `App\Models\Admin` | `app/Models/Admin/` | Invoice, InvoiceItem, Payment, AuditLog, DeferredTask, Lexoffice* |
| `App\Models\Catalog` | `app/Models/Catalog/` | Product, Brand, Category, Gebinde, PfandSet, ... |
| `App\Models\Pricing` | `app/Models/Pricing/` | Customer, CustomerGroup, CustomerPrice, AppSetting, ... |
| `App\Models\Orders` | `app/Models/Orders/` | Order, OrderItem, OrderItemComponent |
| `App\Models\Shop` | `app/Models/Shop/` | Cart, CartItem |
| `App\Models\Delivery` | `app/Models/Delivery/` | Tour, TourStop, RegularDeliveryTour, DeliveryArea, ... |
| `App\Models\Driver` | `app/Models/Driver/` | DriverApiToken, DriverEvent, DriverUpload, CashRegister, CashTransaction |
| `App\Models\Employee` | `app/Models/Employee/` | Employee, Shift, TimeEntry, VacationRequest, ... |
| `App\Models\Inventory` | `app/Models/Inventory/` | Warehouse, ProductStock, StockMovement |
| `App\Models\Procurement` | `app/Models/Procurement/` | GoodsReceipt, ProductMhdBatch, ProductWriteOff, LeergutReturn, ... |
| `App\Models\Supplier` | `app/Models/Supplier/` | Supplier, SupplierProduct, PurchaseOrder, PurchaseOrderItem, ... |
| `App\Models\Rental` | `app/Models/Rental/` | RentalItem, RentalBookingItem, RentalReturnSlip, ... |
| `App\Models\Event` | `app/Models/Event/` | EventLocation |
| `App\Models\Debtor` | `app/Models/Debtor/` | DunningRun, DunningRunItem, DebtorNote |
| `App\Models\Communications` | `app/Models/Communications/` | Communication, CommunicationRule, CommunicationTag, ... |
| `App\Models\Import` | `app/Models/Import/` | ImportBestandsaufnahme* |
| `App\Models\Ninox` | `app/Models/Ninox/` | Ninox-Import-Models |
| `App\Models\Primeur` | `app/Models/Primeur/` | Primeur-Archiv-Models |
| `App\Models\Vehicles` | `app/Models/Vehicles/` | (Fahrzeug-Models) |
| `App\Models\Bestandsaufnahme` | `app/Models/Bestandsaufnahme/` | Inventur-Models |

---

## 7. Tabellen ohne Eloquent-Model (bekannt)

| Tabelle | Problem | Empfehlung |
|---------|---------|-----------|
| `shift_report_checklist` | Pivot-Tabelle — kein Model nötig | OK |
| `communication_tag_pivot` | Pivot-Tabelle — kein Model nötig | OK |
| `stats_pos_daily` | Kein Model — Zugriff via QueryBuilder | `php artisan make:model StatsPosDaily` |
| `vehicles` | Kein Model vorhanden | `php artisan make:model Vehicle` |
| `vehicle_documents` | Kein Model | — |
| `asset_issues` | Kein Model | — |
| `bestandsaufnahme_sessions` | Kein Model | `make:model BestandsaufnahmeSession` |
| `bestandsaufnahme_positionen` | Kein Model | — |
| `bestandsaufnahme_position_eingaben` | Kein Model | — |
| `ladenhueter_regeln` | Kein Model | — |
| `ladenhueter_status` | Kein Model | — |
| `artikel_verpackungseinheiten` | Kein Model | — |
| `artikel_mindestbestaende` | Kein Model | — |
| `mhd_regeln` | Kein Model | — |
| `wawi_sync_log` | Kein Model — Zugriff via DB::table | OK für Sync-Zwecke |
| `wawi_sync_state` | Kein Model | OK |
| `order_number_sequences` | Kein Model — atomarer Zähler | OK |
| `jobs` | Kein Model — Laravel Standard | OK (unused) |

---

## 8. Kritische Datenbank-Constraints

### 8.1 Geldfelder
Alle Geldbeträge als `unsignedBigInteger` in Milli-Cent:
- `order_items.unit_price_net_milli` — **immutable** nach Anlegen (Netto-Einheitspreis)
- `order_items.unit_price_gross_milli` — **immutable** nach Anlegen (Brutto-Einheitspreis)
- `order_items.unit_deposit_milli` — Pfand je Einheit (immutable nach Anlegen)
- `invoice_items.unit_price_milli_cent`, `invoice_items.cost_milli`
- `customer_prices.price_milli_cent`
- `customer_group_prices.price_milli_cent`

### 8.2 Steuersatz-Felder
`INT` in Basispunkten: `mwst_satz` auf `products` (1900 = 19%, 700 = 7%)

### 8.3 Multi-Tenant
`company_id` auf allen App-Tabellen. `CompanyScope` als GlobalScope noch nicht aktiviert (steht an in Phase 3.5).

### 8.4 Pflicht-Indizes (Performance-kritisch)
- `orders`: Index auf `customer_id`, `status`, `delivery_date`
- `invoice_items`: Index auf `invoice_id`
- `stats_pos_daily`: Index auf `artnr`, `tag`
- `wawi_dbo_pos_bon`: Index auf `kKassierer`, `dDatum`
- `wawi_dbo_tliefartikel`: Index auf `kArtikel` (2026_04_24 Hotfix)

---

## 9. Seeder

| Seeder | Beschreibung |
|--------|-------------|
| `DatabaseSeeder` | Hauptseeder — ruft andere auf |
| (weitere Seeder nicht vollständig inventarisiert) | `php artisan db:seed` |

Für Testdaten empfohlen: Artisan Tinker + Factory-Klassen.

---

## 10. Bekannte Altlasten & Duplikate

| Problem | Beschreibung |
|---------|-------------|
| **WaWi-Artikelnummern-Duplikate** | 12 Produkte mit `N{ninox_id}`-Nummern — blockieren `UPDATE` via WaWi-Sync |
| **`jobs`-Tabelle** | Erstellt von Laravel, aber `deferred_tasks` wird stattdessen genutzt — `jobs` ungenutzt |
| **`wawi_*` vs `wawi_dbo_*`** | Zwei WaWi-Import-Strategien: bulk-Import (`wawi_*`) und API-Push (`wawi_dbo_*`) — könnte konsolidiert werden |
| **Ninox als Lager-Master** | `ninox_marktbestand` parallel zu `product_stocks` — welcher ist der Master? |
| **Primeur-Tabellen** | 9 Tabellen für möglicherweise inaktives Modul |
| **`employee_feedbacks` / `employee_notifications`** | Modelle vorhanden, Tabellen-Existenz nicht verifiziert |

---

*Stand: 2026-04-28 | Projekt: shoptour2 | 258 Migrations | 167+ Models*
