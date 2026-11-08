# Reifegrad-Register (ENTWURF)

> **Zweck:** Zeigt pro Funktion, ob wir sie Kunden **anbieten** können, ob sie **Beta** ist oder nur als **Entwurf** vorliegt.
> Ergänzt `features/INDEX.md` (dort steht der *Entwicklungsstand*, hier die *Verkaufsreife*).
>
> **Stand:** 2026-11-08 · **Status dieses Dokuments:** Entwurf, abgeleitet aus Code/Routen/Specs, **noch nicht fachlich bestätigt**.
> Spalte „Reife" ist ein **Vorschlag** und muss von Dir/dem Team bestätigt werden (Spalte „Geprüft").

## Reifegrade

| Reife | Bedeutung | Gegenüber Kunden |
|---|---|---|
| **Live** | Produktiv im Einsatz, getestet, Support-fähig | verkaufbar |
| **Beta** | Läuft, bekannte Lücken, ausgewählte Kunden / interne Nutzung | nur mit Hinweis anbieten |
| **Entwurf** | Spec oder Teilcode vorhanden, nicht zuverlässig nutzbar | nicht zusagen |
| **Geplant** | Nur Idee / Roadmap | nur als Roadmap nennen |

## Regeln für die Einstufung
1. **Live** nur, wenn: produktiv genutzt **und** automatisierte Tests oder dokumentierte manuelle Abnahme **und** kein bekannter Blocker.
2. Ohne Nachweis (Spalte „Nachweis") wird nicht hochgestuft.
3. Jede Änderung trägt Datum + Person in „Geprüft" ein.
4. Spec-Header (`## Status:`) sind teilweise veraltet (viele stehen noch auf *Planned*, obwohl Code existiert) → **INDEX.md und Spec-Header angleichen** (siehe Befunde unten).

## Wichtige Befunde beim Erstellen
- **Testabdeckung ist dünn:** automatisierte Tests existieren nur für Events/Angebote, Wissensassistent, Jugendschutz und Kontoaktivierung (`tests/Feature/`). Für Shop, Checkout, Preisfindung, Pfand, Touren, Rechnungen, POS gibt es **keine** automatisierten Tests → diese Bereiche können nach Regel 1 aktuell höchstens **Beta** sein, bis eine Abnahme dokumentiert ist.
- **POS/TSE:** Die Kasse (`kasse.kolabri.de`, TSE funktionsfähig laut Team) ist **nicht in diesem Repo**: keine Referenz auf die Domain, kein TSE-/DSFinV-K-Code. Im Repo liegen nur `POST /api/pos/sale` und `GET /api/pos/products` (`Api/PosSaleController.php`), **ohne Auth-Middleware** („for MVP"), sowie Auswertungen (`PROJ-39`, `PosStatisticsService`). **Zu klären:** In welchem Repo/Server läuft die Kasse, und nutzt sie die offene `/api/pos/*`-API? Falls ja: Auth ist vor Produktivnutzung Pflicht. PROJ-33 ist als Spec veraltet („Planned / kein TSE").
- **Spec/INDEX-Drift:** INDEX.md meldet z. B. PROJ-13/15/23 „In Progress", die Spec-Header sagen „Planned".
- **PROJ-35** (Kassenverwaltung) ist im INDEX „Planned", im Code existieren aber `CashRegisterController` + `admin/cash-registers`.
- **PROJ-36** (Schicht/Zeit/Urlaub) ist im INDEX „Planned", im Code existieren `ShiftController`, `TimeTrackingAdminController`, `VacationAdminController`, `TimeClockController`.

---

## 1. Shop & Kundenseite

| Funktion | Reife (Vorschlag) | Nachweis im Code | Lücke / zu klären | Geprüft |
|---|---|---|---|---|
| Registrierung, Login, Kontoaktivierung, Google OAuth (PROJ-1) | Beta | `Auth/*`, `SocialController`, `/auth/google`, Socialite; `CustomerActivationTest` | Google läuft laut Team; Aktivierung getestet; Login/Reset ohne Tests | – |
| Produktkatalog, Suche, Filter (PROJ-2) | Beta | `Shop/ShopController`, `Api/ShopProductController` | keine Tests; Gastpreis-Logik prüfen | – |
| Warenkorb Gast + Auth (PROJ-3) | Beta | `Shop/CartController`, `CartService`, `CartMergeService` | keine Tests | – |
| Checkout Heimdienst/Abholung (PROJ-4) | Beta | `Shop/CheckoutController` | keine Tests; Lieferfenster-Logik abnehmen | – |
| Kundenkonto, Adressen, Rechnungen (PROJ-5) | Beta | `Shop/AccountController`, `views/shop/account_statement` | – | – |
| Preisfindung 3-stufig (PROJ-6) | Beta | `Services/Pricing/PriceResolverService` | Kernlogik ohne Tests → zuerst Tests | – |
| Pfand-System (PROJ-7) | Beta | `PfandCalculator`, `AdminPfandSet/ItemController`, `LeergutCartService` | ohne Tests; Spec „Planned" | – |
| Stammsortiment / Favoriten (PROJ-20) | Beta | `FavoriteController` | Mindestbestand/Notizen prüfen | – |
| Unterbenutzer & Rechte (PROJ-21) | Beta | `SubUserController`, `SubUser::defaultPermissions` | Rechtematrix abnehmen | – |
| Jugendschutz (Alterscheck) | Beta | `JugendschutzService`, `tests/Feature/JugendschutzTest.php` | getestet, Produktivnutzung bestätigen | – |
| Newsletter (PROJ-27) | Entwurf | nur Kundenfelder für E-Mail-Präferenzen (Migration `add_email_preferences_to_customers`) | kein Versand-/Gruppen-Controller; laut Team in der Praxis nicht getestet | – |

## 2. Zahlung & Finanzen

| Funktion | Reife (Vorschlag) | Nachweis | Lücke / zu klären | Geprüft |
|---|---|---|---|---|
| Stripe (Karte) | Beta | `Payments/CheckoutController`, `WebhookController`, `ShopStripeService`; `config/services.php` (`STRIPE_ENABLED`) | per Config abschaltbar; Live-Keys/Webhook bestätigen | – |
| PayPal | Beta | `ShopPayPalService`, Routen `/kasse/paypal/*` | Live-Betrieb bestätigen | – |
| SEPA-Mandat, IBAN-Prüfung | Beta | `SepaMandateService`, `IbanValidator`, `BankLookupController` | Lastschrift-Einzug/Export prüfen | – |
| Rechnungen, PDF (PROJ-13) | Beta | `AdminInvoiceController`, `InvoiceService`, dompdf | Finalisierung/GoBD abnehmen | – |
| Lexoffice-Sync | Beta | `Services/Integrations/Lexoffice*`, `LexofficeBankMatchingController` | Fehlerfälle/Doppelbuchung prüfen | – |
| Mahnwesen / Debitoren (PROJ-31) | Beta | `Services/Debtor/*`, `DunningRunController`, `views/emails/dunning` | Mahnlauf nur nach manueller Freigabe? klären | – |
| Berichte: Umsatz, Marge, Pfand, Tour-KPI (PROJ-25) | Beta | `Services/Reporting/*`, `AdminReportController` | keine Tests | – |
| Kassenbericht / UST-Voranmeldung (PROJ-39) | Beta | `KassenberichtService/Controller`, `/admin/kassenbericht`, PDF | Quelle `stats_pos_daily` aus JTL LS POS; steuerlich mit Steuerberater abstimmen | – |
| Umsatzmeldungen Hersteller (PROJ-34) | Entwurf | nur Spec | kein Controller gefunden | – |

## 3. Kasse / POS

| Funktion | Reife (Vorschlag) | Nachweis | Lücke / zu klären | Geprüft |
|---|---|---|---|---|
| POS-Kasse mit TSE (`kasse.kolabri.de`) | **Live (laut Team)** | Betrieb laut Team; **Code liegt außerhalb dieses Repos** | Repo/Server benennen; TSE-Nachweis (Zertifikat, Kassenmeldung) ablegen; eigene Reife-Zeilen (Barcode, Pfand-Rücknahme, Bon, Storno) dort pflegen | – |
| POS-API (Produktsuche, Verkauf) in diesem Repo | Beta, **Sicherheitslücke** | `Api/PosProductController`, `PosSaleController`, `routes/api.php` (nur Throttle) | **ohne Auth**: jeder kann Verkäufe anlegen, falls öffentlich erreichbar; Token-Auth ergänzen, prüfen ob `kasse.kolabri.de` sie nutzt | – |
| Kassenverwaltung / Kassenbuch (PROJ-35) | Beta | `CashRegisterController`, `views/admin/cash-registers` | Spec „Planned"; Kassenbuch/Transfers prüfen | – |
| POS-Statistik | Beta | `PosStatisticsService` | Datenquelle JTL | – |

## 4. Logistik, Touren, Fahrer

| Funktion | Reife (Vorschlag) | Nachweis | Lücke / zu klären | Geprüft |
|---|---|---|---|---|
| Regelmäßige Touren, Liefergebiete (PROJ-14) | Beta | `TourAssignmentService`, `Admin/touren` | – | – |
| Fahrertouren-Planung (PROJ-15) | Beta | `AdminFahrertourController`, `TourPlannerService` | Routenoptimierung? klären | – |
| Fahrer-PWA, Offline-Sync, Foto (PROJ-16) | Beta | `Driver/*`, `DriverSyncService`, `/api/driver/*` | Spec „Planned"; Offline-Konflikte testen | – |
| Fahrzeuge, Fahrer-Tokens | Beta | `AdminVehicleController`, `AdminDriverTokenController` | – | – |
| Ad-hoc-Stopps | Beta | `AdhocStopService`, `DriverAddStopController` | – | – |

## 5. Verwaltung & Stammdaten

| Funktion | Reife (Vorschlag) | Nachweis | Lücke / zu klären | Geprüft |
|---|---|---|---|---|
| Stammdaten: Produkte, Marken, Kategorien, Gebinde (PROJ-9) | Beta | `AdminProduct/Brand/Category/GebindeController` | – | – |
| LMIV / Allergene (Backend) | Beta | `AdminLmivController`, `LmivVersioningService`, `LmivFetchFromOffCommand` | **nur Admin**, nicht im Shop/Karte ausgespielt | – |
| Kundenverwaltung (PROJ-10) | Beta | `AdminCustomerController`, Importer | – | – |
| Lieferanten (PROJ-11) | Beta | `AdminSupplierController` | – | – |
| Bestellverwaltung (PROJ-12) | Beta | `AdminOrderController`, `OrderService`, Closeout | – | – |
| Lager, Bestände, Bewegungen (PROJ-23) | Beta | `AdminStock/Warehouse/StockMovementController`, `StockService` | – | – |
| Einkauf, Bestellvorschlag, Wareneingang (PROJ-32, 37) | Beta | `AdminEinkauf*`, `BestellvorschlagService`, MHD-Regeln | MHD-Management laut INDEX „In Progress" | – |
| Bestandsaufnahme, Ladenhüter | Beta | `Services/Bestandsaufnahme/*` | – | – |
| CSV-/Ninox-/Primeur-/JTL-Importe (PROJ-24) | Beta | `*CsvImporter`, `Ninox*`, `Primeur*`, `Wawi*` | wawi_/ninox_-Tabellen nur lesend | – |
| Benutzer & Rollen (PROJ-18) | Beta | `AdminUserController` | granulare Rechte prüfen | – |
| Dashboard (PROJ-17) | Beta | `AdminDashboardController` | – | – |
| Audit-Log (PROJ-28) | Beta | `AdminAuditLogController`, `AuditLogService` | – | – |
| E-Mail-Posteingang, Regeln (PROJ-29) | Beta | `Services/Communications/*`, Gmail | – | – |
| CMS-Seiten (PROJ-30) | Beta | `AdminPageController`, `Shop/PageController`, `Models/Page` | – | – |
| Aufgabensystem (PROJ-26) | Beta | `AdminTasksController`, `AdminRecurringTasksController` | – | – |

## 6. Events, Verleih, Angebote

| Funktion | Reife (Vorschlag) | Nachweis | Lücke / zu klären | Geprüft |
|---|---|---|---|---|
| Veranstaltungsbestellungen (PROJ-22) | Beta | `Services/Event/*`, `Shop/Rental*Controller` | – | – |
| Festinventar / Verleih | Beta | `Services/Rental/*`, `AdminRental*Controller` | – | – |
| Veranstaltungen & Angebote (PROJ-38) | **Beta (best belegt)** | `Services/Events/*`, `Admin/Events/*`; 7 Tests in `tests/Feature/Events/` | Ninox-/JTL-Import produktiv bestätigen | – |

## 7. Mitarbeiter & Wissen

| Funktion | Reife (Vorschlag) | Nachweis | Lücke / zu klären | Geprüft |
|---|---|---|---|---|
| Zeiterfassung, Stempeluhr (PROJ-36) | Beta | `TimeTrackingService`, `TimeClockController`, `BreakCalculationService` | INDEX „Planned" ist veraltet | – |
| Schichtplanung / Schichtbericht | Beta | `ShiftController`, `ShiftReportController` | – | – |
| Urlaubsverwaltung | Beta | `VacationService`, `VacationRequestController` | – | – |
| Mitarbeiter-Onboarding | Beta | `OnboardingService`, `Onboarding/*` | – | – |
| Wissensassistent (PROJ-40) | Beta | `Services/Knowledge/*`, 5 Tests | Qdrant/Paperless/BookStack-Betrieb bestätigen | – |

## 8. Lücken gegenüber SO'USE / iGetNow (Wettbewerb)

| Funktion | Reife | Anmerkung |
|---|---|---|
| Digitale Getränke-/Speisekarte (öffentlich, QR) | **Geplant** | Nutzt vorhandene Produkt- + LMIV-Daten; kleiner Aufwand |
| Allergen-/Zusatzstoff-Filter im Shop | **Geplant** | LMIV-Daten vorhanden, nicht ausgespielt |
| Mehrsprachigkeit (Shop/Karte) | **Geplant** | – |
| QR-Tischbestellung mit Bezahlung | **Geplant** (bewusst nicht Fokus) | siehe Positionierungsempfehlung |
| Apple Pay / Google Pay | **Geplant** | prüfen, ob über Stripe aktivierbar |
| Trinkgeld, Gutscheine, Loyalty | **Geplant** | – |
| Nachbestell-Vorschlag aus Kunden-Kasse (ready2order, Lightspeed) | **Geplant** | Differenzierer zu SO'USE |
| Küchenmonitor (KDS), Self-Order-Terminal | nicht geplant | Hardware-Thema, nicht unser Kerngeschäft |

---

## Nächste Schritte (ab Mittwoch)
1. Team geht die Spalte „Reife" durch und trägt „Geprüft" (Datum + Kürzel) ein.
2. Fragen klären: Repo der Kasse (`kasse.kolabri.de`) und Nutzung von `/api/pos/*`; Umsatzmeldungen (PROJ-34). Newsletter ist als Entwurf eingestuft, Google OAuth ist bestätigt.
3. `INDEX.md`: Spalte „Reife" ergänzen; Spec-Header angleichen.
4. Tests für die Kernpfade (Preisfindung, Checkout, Pfand, Rechnungen) anlegen, damit „Beta → Live" möglich wird.
5. Danach: Features aus Abschnitt 8 als eigene PROJ-Specs (ab PROJ-41) schreiben.
