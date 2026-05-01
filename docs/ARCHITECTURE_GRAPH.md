# Shoptour2 — Architektur-Übersicht

> Mermaid-Diagramme für Modulstruktur, Datenfluss und Abhängigkeiten.  
> Stand: April 2026 | Basis: vollständige Code-Analyse

---

## 1. Modulgraph (Systemübersicht)

```mermaid
graph TB
    subgraph Extern["Externe Systeme"]
        WAWI[JTL WaWi]
        NINOX[Ninox]
        LEXOFFICE[Lexoffice]
        STRIPE[Stripe]
        PAYPAL[PayPal]
        GMAIL[Gmail API]
        GOOGLE[Google OAuth]
    end

    subgraph Docker["Docker Stack"]
        APP[Laravel App\nPHP 8.2]
        NGINX[Nginx]
        MYSQL[(MySQL 8)]
        SCHEDULER[Scheduler Container\nschedule:run jede Min]
    end

    subgraph Frontend["Frontend"]
        ADMIN[Admin Backend\nBlade + Tailwind v4]
        SHOP[Webshop\nBlade + Alpine.js]
        PWA[Fahrer-PWA\nJS + IndexedDB]
    end

    NGINX --> APP
    APP --> MYSQL
    SCHEDULER --> APP

    WAWI -->|POST /api/sync| APP
    NINOX -->|Import| APP
    APP -->|Vouchers/Payments| LEXOFFICE
    LEXOFFICE -->|Webhooks| APP
    APP -->|Charges| STRIPE
    STRIPE -->|Webhooks| APP
    APP -->|Checkout| PAYPAL
    PAYPAL -->|Webhooks| APP
    GMAIL -->|Import| APP
    GOOGLE -->|OAuth| APP

    APP --> ADMIN
    APP --> SHOP
    APP --> PWA
```

---

## 2. Datenfluss — Import → Masterdaten → Bestellung → Lieferung → Rechnung → Mahnung

```mermaid
flowchart LR
    subgraph Import["Import-Sync (täglich / push)"]
        W[wawi_dbo_*\nPOS-Daten, Artikel]
        N[ninox_*\nLagerbestand]
        L[lexoffice_*\nVouchers, Contacts]
    end

    subgraph Master["Masterdaten (App-Tabellen)"]
        P[products\nprodukt_images\nproduct_prices]
        C[customers\ncustomer_groups\ncustomer_prices]
        S[suppliers\nsupplier_products]
        CAT[categories\nbrands\npfand_sets]
    end

    subgraph Order["Bestellprozess"]
        CART[CartService\n(Session)]
        ORDER[orders\norder_items\n→ Preis-Snapshot!]
        PAY[payments\nStripe/PayPal/SEPA]
    end

    subgraph Delivery["Lieferung"]
        TOUR[tours\ntour_stops]
        DRIVER[Fahrer-PWA\nOffline-Queue]
        POD[proof_of_delivery\nFotos]
    end

    subgraph Finance["Finanzen"]
        INV[invoices\ninvoice_items]
        LEX[Lexoffice\nSync]
        DUN[dunning_levels\nMahnungen]
    end

    subgraph Stats["Statistiken"]
        POS[stats_pos_daily\n→ refresh täglich 05:00]
        REP[Admin-Reports\nPOS / Artikel / MHD]
    end

    W -->|WawiSyncService| P
    W -->|stats:refresh-pos| POS
    N -->|NinoxSyncService| Master
    L -->|LexofficeService| Finance

    P --> CART
    C --> CART
    CART --> ORDER
    ORDER --> PAY
    ORDER --> TOUR
    TOUR --> DRIVER
    DRIVER --> POD
    ORDER --> INV
    INV --> LEX
    LEX -->|Zahlungsstatus| INV
    INV -->|Überfällig| DUN
    DUN -->|Mahnmail| C

    POS --> REP
    INV --> REP
```

---

## 3. Entity-Relationship — Kernentitäten

```mermaid
erDiagram
    customers {
        bigint id PK
        string name
        string email
        bigint company_id FK
        string price_display_mode
        int payment_method
    }
    customer_groups {
        bigint id PK
        string name
        string price_display_mode
        bigint company_id FK
    }
    users {
        bigint id PK
        string name
        string email
        string role
        bigint customer_id FK
    }
    sub_users {
        bigint id PK
        bigint user_id FK
        bigint parent_customer_id FK
        json permissions
    }
    products {
        bigint id PK
        string produktname
        string artikelnummer
        bigint company_id FK
        int mwst_satz
    }
    product_prices {
        bigint id PK
        bigint product_id FK
        bigint price_milli_cent
        string price_type
    }
    customer_prices {
        bigint id PK
        bigint product_id FK
        bigint customer_id FK
        bigint price_milli_cent
    }
    orders {
        bigint id PK
        bigint customer_id FK
        bigint company_id FK
        string status
        string delivery_type
        date delivery_date
    }
    order_items {
        bigint id PK
        bigint order_id FK
        bigint product_id FK
        int qty
        bigint unit_price_snapshot
        bigint pfand_milli_cent
    }
    invoices {
        bigint id PK
        bigint order_id FK
        bigint customer_id FK
        string status
        string lexoffice_id
        bigint total_milli_cent
    }
    tours {
        bigint id PK
        date tour_date
        bigint driver_id FK
        string status
    }
    tour_stops {
        bigint id PK
        bigint tour_id FK
        bigint order_id FK
        int sort_order
        string status
    }
    shifts {
        bigint id PK
        bigint user_id FK
        date shift_date
        time start_time
        time end_time
    }
    purchase_orders {
        bigint id PK
        bigint supplier_id FK
        string status
        date delivery_date
    }

    customers ||--o{ orders : "places"
    customers }o--|| customer_groups : "belongs to"
    users ||--o| customers : "linked to"
    users ||--o{ sub_users : "has"
    sub_users }o--|| customers : "parent customer"
    orders ||--o{ order_items : "contains"
    order_items }o--|| products : "references"
    products ||--o{ product_prices : "has"
    products ||--o{ customer_prices : "custom price"
    customers ||--o{ customer_prices : "gets"
    orders ||--o| invoices : "billed via"
    orders ||--o{ tour_stops : "delivered at"
    tours ||--o{ tour_stops : "includes"
    purchase_orders ||--o{ order_items : "sourced from"
```

---

## 4. Controller → Service → Model Abhängigkeiten

```mermaid
graph LR
    subgraph Controllers["Admin Controllers"]
        OC[OrderController]
        IC[InvoiceController]
        DC[AdminDashboardController]
        TC[TourController]
        CC[CustomerController]
        PC[ProductController]
        SC[Statistics/*Controller]
        WC[Admin/WarehouseController]
        PuC[PurchaseOrderController]
    end

    subgraph ShopControllers["Shop Controllers"]
        SHC[ShopController]
        CRTC[CartController]
        CHC[CheckoutController]
        ACC[AccountController]
    end

    subgraph Services["Services"]
        OS[OrderService]
        OPS[OrderPricingService]
        IS[InvoiceService]
        LS[LexofficeService]
        PS[PriceService]
        PF[PfandCalculator]
        CS[CartService]
        TS[TourService]
        PSS[PosStatisticsService]
        DS[DunningService]
        WS[WawiSyncService]
        BS[BestellvorschlagService]
        GS[GmailImportService]
        RS[RuleEngineService]
        SS[StockService]
    end

    subgraph Models["Key Models"]
        Order[Order]
        Invoice[Invoice]
        Product[Product]
        Customer[Customer]
        Tour[Tour]
        Shift[Shift]
        PurchaseOrder[PurchaseOrder]
    end

    OC --> OS
    OC --> OPS
    IC --> IS
    IC --> LS
    DC --> PSS
    TC --> TS
    CC --> Customer
    PC --> Product
    SC --> PSS
    WC --> SS
    PuC --> BS

    SHC --> PS
    SHC --> PF
    SHC --> Product
    CRTC --> CS
    CHC --> CS
    CHC --> OPS
    CHC --> OS
    ACC --> IS
    ACC --> Customer

    OS --> Order
    OPS --> PS
    OPS --> PF
    IS --> Invoice
    IS --> LS
    PS --> Product
    PS --> Customer
    TS --> Tour
    DS --> LS
    WS --> Order
    WS --> Customer
    GS --> RS
```

---

## 5. Scheduler-Übersicht (Artisan Commands)

```mermaid
gantt
    title Scheduler — Täglich / Stündlich / Alle 5 Min
    dateFormat HH:mm
    axisFormat %H:%M

    section Alle 5 Min
    kolabri:tasks:run           :00:00, 5m
    lexoffice:import-payments   :00:00, 5m

    section Stündlich
    kolabri:sync:lexoffice      :00:00, 60m

    section Täglich 05:00
    stats:refresh-pos           :05:00, 30m

    section Täglich 06:00
    kolabri:dunning:check       :06:00, 30m

    section Täglich 07:00
    kolabri:reports:daily       :07:00, 30m
```

**Manuelle Commands:**
```bash
php artisan stats:refresh-pos --days=10       # POS-Statistiken für N Tage neu aggregieren
php artisan kolabri:po:create                 # Bestellvorschlag erstellen
php artisan kolabri:tasks:run                 # Deferred Tasks einmalig verarbeiten
php artisan lexoffice:import-payments         # Zahlungsabgleich sofort
php artisan tinker                            # REPL für Debugging
```

---

## 6. Auth & Middleware-Stack

```mermaid
flowchart TD
    REQ[Eingehender Request]

    REQ --> WEB{Route-Gruppe}

    WEB -->|web| SESSION[StartSession\nVerifyCsrfToken]
    WEB -->|api| SANCTUM[auth:sanctum\nSanctumToken]

    SESSION --> AUTH{Authentifiziert?}
    AUTH -->|Nein - Shop| GUEST[Gast-Modus\nSession-Warenkorb]
    AUTH -->|Ja| ROLE{Rolle?}

    ROLE -->|admin / mitarbeiter| ADMIN_MW[Admin-Middleware\nrole:admin]
    ROLE -->|kunde| SHOP_MW[Shop-Middleware\nshop.order]
    ROLE -->|sub_user| SUB_MW[Sub-User-Resolver\n→ parentCustomer]
    ROLE -->|fahrer| DRIVER_MW[API Bearer-Token\nSanctum]

    ADMIN_MW --> ADMIN_CTRL[Admin-Controller]
    SHOP_MW --> SHOP_CTRL[Shop-Controller]
    SUB_MW --> SHOP_CTRL
    DRIVER_MW --> DRIVER_CTRL[Driver-API-Controller]
    SANCTUM --> WAWI_CTRL[WaWi-Sync-Controller\nWAWI_SYNC_TOKEN]
```

---

## 7. Zahlungsfluss (PROJ-8)

```mermaid
sequenceDiagram
    participant K as Kunde (Browser)
    participant S as Shoptour2 (Laravel)
    participant ST as Stripe
    participant PP as PayPal
    participant L as Lexoffice

    K->>S: POST /checkout/complete
    S->>S: OrderPricingService::calculate()
    S->>S: Order::create() + OrderItems

    alt Stripe
        S->>ST: createPaymentIntent()
        ST->>K: client_secret
        K->>ST: confirmPayment()
        ST->>S: POST /api/payments/stripe/webhook
        S->>S: Order.status = paid
    end

    alt PayPal
        S->>PP: createOrder()
        PP->>K: approval URL
        K->>PP: approve
        PP->>S: POST /api/payments/paypal/webhook
        S->>S: Order.status = paid
    end

    S->>S: InvoiceService::createFromOrder()
    S->>L: createVoucher()
    S->>K: E-Mail: Bestellbestätigung + PDF
```

---

## 8. Fahrer-PWA Offline-Architektur

```mermaid
flowchart LR
    subgraph PWA["Fahrer-PWA (Browser)"]
        SW[Service Worker\nsw.js]
        IDB[IndexedDB\nOffline Queue]
        APP_JS[app.js\nAlpine.js + Fetch]
    end

    subgraph Server["Laravel API"]
        API[/api/driver/tours\n/api/driver/sync]
        AUTH[Sanctum Bearer Token]
    end

    APP_JS -->|Online| API
    APP_JS -->|Offline - Write| IDB
    SW -->|Background Sync| IDB
    IDB -->|Sync wenn online| API
    API --> AUTH

    subgraph Actions["Fahrer-Aktionen"]
        SCAN[Lieferung bestätigen]
        PHOTO[PoD-Foto hochladen]
        CASH[Kassenentnahme]
        DEV[Abweichung melden]
    end

    SCAN --> APP_JS
    PHOTO --> APP_JS
    CASH --> APP_JS
    DEV --> APP_JS
```

---

*Stand: 2026-04-28 | Projekt: shoptour2 | Basis: vollständige Code-Analyse*
