# ParsYar (پارس‌یار)

> **Enterprise CRM/ERP/HCM platform — built natively on WordPress, fluent in Persian, compliant with Iranian regulation.**
> Four artifacts ship from one repository: a WordPress plugin (the engine), a WordPress theme (the admin surface), a customer-facing PWA portal, and a React Native mobile app. All driven by a single REST API with a uniform envelope.

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4)](https://www.php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.5%2B-21759b)](https://wordpress.org)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B%20%7C%20MariaDB-10.6%2B-4479a1)](https://www.mysql.com)
[![React](https://img.shields.io/badge/React-18-61dafb)](https://react.dev)
[![React%20Native](https://img.shields.io/badge/React%20Native-0.75-61dafb)](https://reactnative.dev)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)](LICENSE)
[![Version](https://img.shields.io/badge/version-2.0.0-black)]()
[![CI](https://github.com/QalamHipHop/ParsYar/actions/workflows/ci.yml/badge.svg)](https://github.com/QalamHipHop/ParsYar/actions/workflows/ci.yml)
[![Mobile%20CI](https://github.com/QalamHipHop/ParsYar/actions/workflows/mobile-ci.yml/badge.svg)](https://github.com/QalamHipHop/ParsYar/actions/workflows/mobile-ci.yml)
[![Architecture](https://img.shields.io/badge/architecture-layered-black)]()
[![No third-party deps](https://img.shields.io/badge/dependencies-zero-black)]()

The name **پارس‌یار** means *"Persian Companion"*. The tagline in Farsi: **«CRM که به فارسی می‌اندیشد، در مقیاس جهانی می‌درخشد.»** — *"A CRM that thinks in Persian, scales in global."*

---

## Table of contents

- [Why ParsYar](#why-parsyar)
- [What is in the box](#what-is-in-the-box)
- [Architecture](#architecture)
- [Modules](#modules)
  - [Core engines (PHP)](#core-engines-php)
  - [Domain modules](#domain-modules)
  - [Customer surfaces (v1.7.0+)](#customer-surfaces-v170)
  - [Multi-tenant SaaS (v2.0.0)](#multi-tenant-saas-v200)
- [The Custom Object Engine](#the-custom-object-engine)
- [Double-entry accounting](#double-entry-accounting)
- [Iranian localization — deep, not skinned](#iranian-localization--deep-not-skinned)
- [The 23-step Setup Wizard](#the-23-step-setup-wizard)
- [REST API](#rest-api)
- [CLI](#cli)
- [Theme and SPA dashboard](#theme-and-spa-dashboard)
- [Customer Portal (PWA) — v1.7.0](#customer-portal-pwa--v170)
- [Mobile App (React Native) — v1.8.0](#mobile-app-react-native--v180)
- [Multi-tenant SaaS (Holding) — v2.0.0](#multi-tenant-saas-holding--v200)
- [Installation](#installation)
- [System requirements](#system-requirements)
- [Security](#security)
- [Roadmap](#roadmap)
- [Contributing](#contributing)
- [License](#license)

---

## Why ParsYar

Most "WordPress CRMs" sold in the Persian market are a stack of licensed third-party plugins glued together: ACF for fields, WooCommerce for sales, a paid form plugin for capture, a separate accounting tool that does not speak Persian and does not understand Iranian tax law. The result is a fragile tower: each plugin can break another, every upgrade is a risk, and the moment you need *real* Iranian localization (Jalali, Sheba, national ID, tax.gov.ir, Shaparak) you discover it was never there.

ParsYar replaces all of that with a single self-hosted stack written from scratch:

- **No third-party plugin dependencies.** No ACF, no WooCommerce, no JetEngine, no Pods, no Toolset. Everything — the custom object engine, the ledger, the workflow runner, the audit log, the Iranian e-invoice client, the customer portal, the mobile app, the multi-tenant context — is built in.
- **Persian-first is not a skin.** The validator layer treats `۰۹۱۲...` and `0912...` identically. The double-entry ledger writes 70+ account codes with Persian labels out of the box. The calendar engine ships a 2820-year Jalali algorithm. The PWA portal and the mobile app default to `fa-IR` RTL.
- **Iranian regulatory stack is wired into the write path.** Send a sale invoice, and the system knows it must also build a `header.body.invoice` payload, sign it with JWS (RSA-SHA256), and POST it to `tp.tax.gov.ir`. Send a payment, and the Sheba gets validated, the card BIN gets looked up, and the bank is identified.
- **Salesforce-style metadata layer.** Admins can define new entities (objects) and fields at runtime, with 22 field types, Flat-Table DDL, auto-indexing, and validation rules — without writing a single line of PHP.
- **Hardened for production.** Strict types, prepared statements everywhere, append-only audit log, double-entry guarantee, rate-limited REST, capability-based authorization, CSP headers, JWT with rotation, machine-readable error codes.
- **Three surfaces, one API.** The admin dashboard (React 18 + Tailwind), the customer portal (PWA, Workbox, offline-first), and the mobile app (React Native, biometrics, push) all talk to the same `enterprise/v1` REST namespace with a uniform `{success, data, meta}` envelope.
- **Multi-tenant from v2.0.0.** One install can host many companies (tenants) and many branches per company. The Multitenant Context filters every read at the SQL level — there is no way to accidentally leak data across tenants.

---

## What is in the box

```
ParsYar/
├── enterprise-core-plugin/                # PHP 8.1+ plugin (the engine) — v2.0.0
│   ├── enterprise-core.php                # Bootstrap singleton (PSR-4 autoload, hooks, install)
│   ├── composer.json                      # PSR-4 + dev tooling (PHPUnit, PHPCS, PHPStan)
│   ├── includes/                          # Core engines
│   │   ├── Core/
│   │   │   └── Exception.php              # Base exception with machine-readable error codes
│   │   ├── class-installer.php            # Activation, migrations, schema bootstrap
│   │   ├── class-router.php               # Front-end rewrite rules (/enterprise/*)
│   │   ├── class-jalali.php               # Jalali calendar (33-yr + 2820-yr algorithms)
│   │   ├── class-validator.php            # Iranian validators (nationalId, sheba, mobile, card…)
│   │   ├── class-data.php                 # Static data loader (provinces, banks, currencies…)
│   │   ├── class-relations.php            # Polymorphic relations engine (17 types)
│   │   ├── class-str.php                  # String utilities (Levenshtein, Jaro-Winkler, Soundex)
│   │   ├── class-cache.php                # Unified cache (Redis > Memcached > Transients)
│   │   ├── class-backup.php               # Config + critical-data backup / restore
│   │   ├── class-cli.php                  # WP-CLI commands (`wp parsyar …`)
│   │   └── support/
│   │       └── db.php                     # Prepared-statement wrapper over wpdb
│   ├── modules/                           # 16 domain modules
│   │   ├── objects/                       # Custom object engine (FieldTypes, SchemaBuilder, RecordStore, ObjectEngine, Bootstrap, ObjectExceptions)
│   │   ├── accounting/                    # Double-entry ledger (Ledger, ChartOfAccounts, Reports, LedgerExceptions)
│   │   ├── crm/                           # Lead, Contact, Deal, Activity, Organization, Pipeline
│   │   ├── erp/                           # Product, Inventory, Warehouse, StockMovement, ProductCategory, Invoice
│   │   ├── hrm/                           # Employee, Attendance, Leave, Payroll, PerformanceReview
│   │   ├── tax/                           # MoodianClient (e-invoice for tp.tax.gov.ir)
│   │   ├── inbox/                         # Omnichannel inbox (email, SMS, WhatsApp, Telegram, Bale, Rubika, Instagram, webchat, voice)
│   │   ├── calendar/                      # Recurring events, Jalali bridge, attendees, reminders
│   │   ├── workflow/                      # Visual automation (12 node types, branching, templates)
│   │   ├── audit/                         # Append-only change log
│   │   ├── reports/                       # Custom report builder (11 sources, 5 chart types)
│   │   ├── notification/                  # Email + SMS adapters (Kavenegar, Melipayamak, Ghasedak, SMS.ir, IPPanel)
│   │   ├── payment/                       # PaymentGatewayManager + 8 Iranian gateways
│   │   ├── sales/                         # Order, Payment, Refund services
│   │   ├── portal/                        # Customer portal (PWA backend) — v1.7.0
│   │   ├── mobile/                        # Mobile backend (FCM/APNs) — v1.8.0
│   │   └── multitenant/                   # Tenant, Branch, Membership, Context, Repository — v2.0.0
│   ├── api/                               # 70+ REST controllers + router
│   │   ├── class-restrouter.php           # /wp-json/enterprise/v1 routes
│   │   ├── class-authcontroller.php       # JWT login + me
│   │   ├── class-accountingcontroller.php # Accounts, journal, trial balance
│   │   ├── class-auditcontroller.php      # Audit log read
│   │   ├── class-crmcontroller.php        # Leads, contacts, deals, activities
│   │   ├── class-erpcontroller.php        # Products, warehouses, invoices, orders
│   │   ├── class-hrmcontroller.php        # Employees, attendance, payroll
│   │   ├── class-objectcontroller.php     # Custom object schemas
│   │   ├── class-recordcontroller.php     # Custom object records
│   │   ├── class-reportscontroller.php    # Custom report builder
│   │   ├── class-taxcontroller.php        # Moodian e-invoice
│   │   ├── class-workflowcontroller.php   # Workflow CRUD + run + logs
│   │   ├── class-securityheaders.php      # CSP / X-Frame-Options / Permissions-Policy
│   │   ├── mobile/                        # /mobile/* (v1.8.0)
│   │   ├── multitenant/                   # /tenants/* (v2.0.0)
│   │   └── portal/                        # /portal/* (v1.7.0)
│   ├── admin/                             # WP admin pages
│   │   ├── class-menu.php                 # Top-level menu + 12 submenus
│   │   ├── class-adminpages.php           # Dashboard + 10 page renderers
│   │   ├── class-wizard.php               # 23-step setup wizard controller
│   │   ├── class-wizard-state.php         # Resumable wizard state in wp_options
│   │   ├── class-systemcheck.php          # System health checks
│   │   ├── class-setup.php                # First-run redirect
│   │   ├── class-portalpage.php           # Customer Portal admin (v1.7.0)
│   │   ├── class-mobilepage.php           # Mobile App admin (v1.8.0)
│   │   └── views/
│   │       └── wizard/                    # 23 wizard step views
│   ├── assets/
│   │   ├── data/                          # iran-provinces, iran-banks, mobile-prefixes, currencies, languages, industries
│   │   └── js/wizard.js                   # Wizard front-end
│   ├── db/
│   │   └── class-demoseeder.php           # Demo data seeder
│   └── tests/                             # PHPUnit suites
│       ├── unit/                          # Brain/Monkey + Mockery unit tests
│       └── integration/                   # Integration tests
│
├── enterprise-theme/                      # WordPress theme — v2.0.0
│   ├── style.css                          # Theme metadata header
│   ├── functions.php                      # Theme bootstrap, asset loader, i18n
│   ├── front-page.php, page.php, single.php, archive.php, search.php,
│   ├── 404.php, comments.php, index.php, header.php, footer.php, sidebar.php
│   ├── page-dashboard.php                 # Mount point for the admin SPA
│   ├── portal.php                         # Mount point for the PWA portal
│   ├── enterprise/                        # Legacy / fallback PHP entry
│   ├── src/                               # React 18 + Tailwind 3 + Vite SPA (admin dashboard, 12 pages)
│   │   ├── App.jsx, main.jsx              # React 18 entry
│   │   ├── pages/                         # Dashboard, ObjectsList, RecordsList, Accounting, Invoices, Leads, Products, Employees, Workflows, Reports, Audit
│   │   ├── components/                    # Buttons, inputs, cards, badges, modals, toasts, command palette
│   │   ├── lib/                           # REST client, format helpers, i18n
│   │   └── store/                         # Redux Toolkit slices
│   ├── portal-pwa/                        # Customer Portal PWA (Vite + Workbox + Vitest) — v1.7.0
│   │   ├── src/
│   │   │   ├── App.tsx, main.tsx, sw.ts   # SPA + Service Worker
│   │   │   ├── lib/                       # api, i18n, push, format
│   │   │   ├── pages/                     # LoginPage, VerifyPage, DashboardPage, InvoicesPage, OrdersPage, PaymentsPage, TicketsPage
│   │   │   ├── components/                # Banners, layout
│   │   │   └── test/                      # Vitest
│   │   ├── public/                        # PWA icons (192, 512, maskable, apple-touch)
│   │   └── vite.config.ts                 # vite-plugin-pwa config
│   ├── assets/css/                        # 7 layers: tokens, base, layout, components, parsyar, rtl, print
│   ├── template-parts/
│   │   ├── dashboard/                     # kpi-card, kpi-grid, pipeline-mini, activity-feed, todo-list, revenue-chart, birthdays-widget
│   │   ├── components/                    # nav, sidebar, topbar
│   │   └── records/                       # list-view, kanban-view, calendar-view, gallery-view, detail-view
│   └── package.json                       # Vite + Tailwind + React 18
│
└── enterprise-mobile/                     # React Native app (iOS + Android) — v1.8.0
    ├── src/
    │   ├── App.tsx                        # Providers (Redux, i18n, Gesture, SafeArea) + RootNavigator
    │   ├── components/UI.tsx              # Card, Button, Input, Empty, StatusBadge, Chip
    │   ├── lib/
    │   │   ├── api.ts                     # Axios client + JWT rotation + AsyncStorage persistence
    │   │   └── i18n.ts                    # i18next (fa-IR default) + react-native-localize
    │   ├── navigation/RootNavigator.tsx   # Auth stack ↔ Main tabs (5 tabs)
    │   ├── screens/                       # 9 screens (Login, Verify, Dashboard, Invoices + Detail, Orders + Detail, Payments + Detail, Tickets + New + Detail, Profile, Settings)
    │   ├── store/                         # Redux Toolkit slices (auth + ui)
    │   └── theme/                         # Design tokens (mirror PWA)
    ├── ios/                               # Info.plist (ATS, deep link, FaceID), Podfile
    ├── android/                           # AndroidManifest.xml, build.gradle, network security config
    ├── __tests__/                         # Jest + RTL
    └── package.json                       # React Native 0.75 + TypeScript 5.5
```

Four artifacts, one product. The **plugin** is the engine. The **theme** is the admin surface. The **portal PWA** is the customer surface. The **mobile app** is the field surface.

---

## Architecture

```
┌──────────────────────────────────────────────────────────────────────────┐
│                          Presentation Layer                              │
│  ┌─────────────────────┐ ┌─────────────────────┐ ┌──────────────────┐  │
│  │ React 18 + Tailwind │ │ Customer Portal PWA │ │ React Native App │  │
│  │ (admin dashboard)   │ │ (Workbox, offline)  │ │ (iOS + Android)  │  │
│  └──────────┬──────────┘ └──────────┬──────────┘ └────────┬─────────┘  │
└─────────────┼────────────────────────┼──────────────────────┼────────────┘
              │                        │                      │
              ▼                        ▼                      ▼
┌──────────────────────────────────────────────────────────────────────────┐
│           REST API  —  /wp-json/enterprise/v1  —  uniform envelope       │
│  - Auth: JWT (HS256, rotation) / Cookie+Nonce / App Passwords            │
│  - Rate limit: 60 req/min per user/IP, configurable                      │
│  - Authz: capability-based permission_callback                          │
│  - Error codes: parsyar.ledger.unbalanced, parsyar.tenant.not_found, ... │
│  - Multi-tenant headers: X-ParsYar-Company, X-ParsYar-Branch              │
│  - CSP / X-Frame-Options / Permissions-Policy / HSTS                    │
│  - 70+ controllers, 200+ routes, all permission-gated                   │
└────────────────────────────────────┬─────────────────────────────────────┘
                                     │
                                     ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                          Application Core                                │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌──────────────┐      │
│  │  Objects   │  │ Accounting │  │  Workflow  │  │  Multitenant │      │
│  │  Engine    │  │ (Ledger)   │  │  Engine    │  │  Context     │      │
│  └─────┬──────┘  └─────┬──────┘  └─────┬──────┘  └──────┬───────┘      │
│        │               │               │                │              │
│        └───────────────┴───────────────┴────────────────┘              │
│                                   │                                     │
│                                   ▼                                     │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │   Domain Modules: CRM · ERP · HRM · Tax · Inbox · Calendar ·     │   │
│  │   Notification · Payment · Sales · Reports · Audit ·             │   │
│  │   Portal · Mobile                                                 │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│  Cross-cutting: Cache · Str · Validator · Data · Jalali · CLI · Backup │
└────────────────────────────────────┬─────────────────────────────────────┘
                                     │
                                     ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                            Persistence Layer                             │
│  ┌─────────────────────────┐         ┌──────────────────────────┐       │
│  │  WordPress core tables  │         │  Custom tables (prefix)  │       │
│  │  (users, options, posts)│         │  wp_ent_*   (engine)     │       │
│  │                         │         │  wp_parsyar_* (modules)  │       │
│  └─────────────────────────┘         └──────────────────────────┘       │
│  Object Cache: Redis (1st) → Memcached (2nd) → WordPress transients.     │
│  All writes go through Enterprise\Support\Db (prepared statements).      │
└──────────────────────────────────────────────────────────────────────────┘
```

**Layered.** Presentation → REST → Application Core → Domain → Persistence. Each layer talks only to the one below it.

**Modular monolith.** Every feature is a module that can be toggled. Nothing in the core hard-depends on a domain module. The `enterprise-core-plugin/composer.json` declares all PSR-4 mappings so a service can be replaced by an enterprise drop-in without touching the core.

**Audited by default.** Every write goes through `Enterprise\Support\Db`, every change is logged through `Enterprise\Modules\Audit\Logger`, every action is fired as an `enterprise_event` so the Workflow Engine can react.

**Tenant-aware.** The Multitenant Context (`Enterprise\Modules\Multitenant\Context`) sits in front of every REST request and resolves the active company/branch from headers, query vars, or user meta. Domains that opt in (`is_tenant_scoped = 1`) automatically get filtered by `tenant_id` and `branch_id`.

**Event-driven.** `do_action('enterprise_event', $eventName, $payload)` is the single seam between the application core and any side-effect (workflow run, audit log, push notification, webhook). Every domain service emits it on write.

---

## Modules

### Core engines (PHP)

These are not modules in the toggle sense — they ship with the plugin and provide the foundation.

| Engine | File | Responsibility |
|--------|------|----------------|
| **Bootstrap** | `enterprise-core.php` | PSR-4 autoload, hook registration, module boot |
| **Installer** | `includes/class-installer.php` | Activation, dbDelta migrations, seed defaults, capability map |
| **Router** | `includes/class-router.php` | Front-end rewrite rules (`/enterprise/{route}`) and template dispatch |
| **Jalali** | `includes/class-jalali.php` | 33-yr + 2820-yr Jalali algorithms: `fromGregorian`, `toGregorian`, `format`, `isLeap`, `daysInMonth`, `weekStart` |
| **Validator** | `includes/class-validator.php` | Iranian validators: national ID (mod-11), legal ID, Sheba (mod-97), mobile (operator detect), phone, postal code, card (Luhn + BIN), email, URL, persianToEnglish, englishToPersian |
| **Data** | `includes/class-data.php` | Static data loader: 31 provinces, 1200+ cities, 23 banks, mobile prefixes, currencies, languages, industries — all wp_cache-backed |
| **Relations** | `includes/class-relations.php` | Polymorphic relation engine, 17 typed relations (`contact_to_org`, `deal_to_invoice`, …) |
| **Str** | `includes/class-str.php` | Levenshtein, Jaro-Winkler, Soundex, normalize — used by lead dedup and contact merging |
| **Cache** | `includes/class-cache.php` | Unified cache layer: Redis → Memcached → Transients fallback, with versioned keys, hit/miss stats, and namespace invalidation |
| **Backup** | `includes/class-backup.php` | Export/import of config + Chart of Accounts + Object schemas + Workflow definitions + tenants/branches/currencies. Versioned format (`Backup::VERSION`) |
| **CLI** | `includes/class-cli.php` | WP-CLI command group: `wp parsyar status`, `cache info|flush`, `db install|seed|demo`, `reports trial-balance|income|balance-sheet|journal`, `ledger post`, `notifications test`, `objects list|create|delete`, `workflow run`, `tax moodian submit`, `backup create|restore` |
| **Db (Support)** | `includes/support/db.php` | `Db::table()`, `insert()`, `update()`, `delete()`, `select*()` — every method uses `wpdb::prepare()` |
| **Base Exception** | `includes/Core/Exception.php` | All exceptions carry `errorCode` (e.g. `parsyar.ledger.unbalanced`), human message, and `details` array — so the API returns a uniform envelope and clients can branch on code |

### Domain modules

| Module | Version | Responsibility | Key tables | Service class |
|--------|---------|----------------|------------|----------------|
| **Objects** | core | Custom entity engine (Salesforce-style). 22 field types, flat-table DDL, auto-indexing, polymorphic relations | `wp_ent_objects`, `wp_ent_object_fields`, `wp_ent_object_relations`, `wp_ent_data_*` | `Enterprise\Modules\Objects\ObjectEngine` + `RecordStore` + `SchemaBuilder` + `FieldTypes` + `Bootstrap` |
| **Accounting** | core | Double-entry ledger with mathematical balance guarantee, full Chart of Accounts, fiscal periods, financial statements | `wp_ent_accounts`, `wp_ent_journal_entries`, `wp_ent_journal_lines`, `wp_ent_fiscal_periods` | `Enterprise\Modules\Accounting\Ledger` + `ChartOfAccounts` + `Reports` |
| **CRM** | core | Lead capture, scoring, deduplication, pipeline + kanban, deal forecasting | `wp_parsyar_leads`, `wp_parsyar_contacts`, `wp_parsyar_deals`, `wp_parsyar_activities`, `wp_parsyar_organizations` | `LeadService`, `ContactService`, `PipelineService`, `ActivityService`, `OrganizationService` |
| **ERP** | core | Multi-warehouse inventory, lots/serials, FIFO/LIFO/WAC costing, sales invoices, payments, refunds | `wp_parsyar_products`, `wp_parsyar_warehouses`, `wp_parsyar_stock_movements`, `wp_parsyar_invoices`, `wp_parsyar_orders`, `wp_parsyar_payments`, `wp_parsyar_refunds` | `InventoryService`, `WarehouseService`, `ProductCategoryService`, `StockMovementService`, `InvoiceService`, `OrderService`, `PaymentService`, `RefundService` |
| **HRM** | core | Employees, attendance, payroll with Iranian tax brackets, contracts, performance | `wp_parsyar_employees`, `wp_parsyar_attendance`, `wp_parsyar_payroll_*` | `EmployeeService`, `AttendanceService`, `LeaveService`, `PayrollService`, `PerformanceReview` |
| **Tax (Māndian)** | core | E-invoice client for `tp.tax.gov.ir`. JWS signing, both Patterns (B2B & B2C), 4 invoice types, inquiry endpoint | `wp_parsyar_invoices` (extended), `wp_parsyar_tax_invoice_*` | `Enterprise\Modules\Tax\MoodianClient` |
| **Inbox** | core | Omnichannel aggregator: email, SMS, WhatsApp, Telegram, Bale, Rubika, Instagram, webchat, voice | `wp_parsyar_inbox_*` | `InboxService` |
| **Calendar** | core | Recurring events, Jalali bridge, attendee tracking, reminders | `wp_parsyar_calendar_*` | `CalendarEngine` (RRULE: DAILY/WEEKLY/MONTHLY/YEARLY) |
| **Workflow** | 1.5.0 | Visual automation: triggers, conditions, actions, 12 node types, scheduled runs, run logs, branching, template rendering (`{{ path.to.value }}`) | `wp_parsyar_workflows`, `wp_parsyar_workflow_runs`, `wp_parsyar_workflow_logs` | `Enterprise\Modules\Workflow\Dispatcher` + `Repository` |
| **Audit** | core | Append-only change log. Schema-level protected (no DELETE grant). | `wp_parsyar_audit_log` | `Enterprise\Modules\Audit\Logger` |
| **Reports** | 1.6.0 | Custom Report Builder: 11 sources, 5 chart types, CSV export, 4 templates | `wp_parsyar_reports` | `ReportService` |
| **Notification** | core | Email adapter (HTML + RTL + unsubscribe) + SMS adapter (Kavenegar, Melipayamak, Ghasedak, SMS.ir, IPPanel, Log) | (uses options + wp_mail) | `EmailAdapter`, `SmsAdapter`, `NotificationService` |
| **Payment** | core | 8 Iranian gateways: ZarinPal, IDPay, NextPay, Saman, Pasargad, Mellat, Saderat, AsanPardakht. Manager + Adapter. | (uses options for credentials) | `PaymentGatewayManager` + `PaymentGatewayInterface` |
| **Sales** | core | Order, Payment, Refund services (thin layer over ERP + Payment) | `wp_parsyar_orders`, `wp_parsyar_payments`, `wp_parsyar_refunds` | `OrderService`, `PaymentService`, `RefundService` |

### Customer surfaces (v1.7.0+)

| Module | Version | Responsibility | Key tables | Service class |
|--------|---------|----------------|------------|----------------|
| **Portal** | 1.7.0 | Customer-facing self-service (PWA). Magic link, JWT, profile, invoices, orders, payments, tickets, push, telemetry | `wp_parsyar_portal_tokens`, `wp_parsyar_portal_sessions`, `wp_parsyar_portal_tickets`, `wp_parsyar_quote_requests`, `wp_parsyar_push_subscriptions`, `wp_parsyar_portal_events` | `Enterprise\Modules\Portal\AuthService` + `PortalService` + `PortalModule` |
| **Mobile** | 1.8.0 | React Native backend: device registry, FCM (Android) and APNs (iOS) dispatcher, info/feature flags, heartbeat, daily prune (180 days) | `wp_parsyar_mobile_devices` | `Enterprise\Modules\Mobile\Device` + `MobileModule` + `Repository` |

### Multi-tenant SaaS (v2.0.0)

| Module | Version | Responsibility | Key tables | Service class |
|--------|---------|----------------|------------|----------------|
| **Multitenant** | 2.0.0 | Multi-company / multi-branch context, tenant CRUD, branch CRUD, membership/roles, daily prune, cache group | `wp_parsyar_tenants`, `wp_parsyar_branches`, `wp_parsyar_memberships` | `Enterprise\Modules\Multitenant\Tenant` + `Branch` + `Membership` + `Context` + `Repository` |
| **Setup Wizard** | core | 23-step guided installer with import/export of configuration, demo seed, system check | (uses `wp_options` for resumable state) | `Enterprise\Admin\Wizard` + `WizardState` |

---

## The Custom Object Engine

This is the centerpiece. Admins can define a new entity at runtime — *no code* — and ParsYar will:

1. Create a row in `wp_ent_objects` (metadata).
2. Create one row per field in `wp_ent_object_fields` (metadata).
3. Generate a **dedicated MySQL table** `wp_ent_data_{api_name}` with one typed column per field, plus auto-indexes for searchable fields.
4. Wire all 22 field types' validators into the write path (Persian digit normalization, mobile operator detection, Sheba mod-97, card Luhn + BIN, national-ID mod-11, postal-code format, etc.).
5. Emit `enterprise_event` for every write so the Workflow Engine can react.

The 22 supported field types (see `Enterprise\Modules\Objects\FieldTypes::ALL`):

| Group | Type constant | SQL type | Validator |
|-------|---------------|----------|-----------|
| Text | `text` | `VARCHAR(255)` | max length |
| Text | `textarea` | `LONGTEXT` | max length |
| Text | `rich` | `LONGTEXT` | sanitized HTML |
| Text | `email` | `VARCHAR(255)` | RFC + MX optional |
| Text | `url` | `VARCHAR(255)` | filter_var URL |
| Numbers | `int` | `BIGINT` | integer range |
| Numbers | `decimal` | `DECIMAL(20,4)` | precision/scale |
| Numbers | `bool` | `TINYINT(1)` | 0/1 |
| Date/Time | `date` | `DATE` | ISO 8601 |
| Date/Time | `datetime` | `DATETIME` | ISO 8601 |
| Date/Time | `jalali` | `VARCHAR(10)` | `YY/MM/DD` via `Jalali::isValid` |
| Choice | `enum` | `VARCHAR(64)` | whitelist |
| Choice | `multi` | `TEXT` | whitelist array |
| Choice | `json` | `LONGTEXT` | `json_decode` + schema |
| Relations | `fk` | `BIGINT` | existence check + tenant scope |
| Files | `file` | `VARCHAR(500)` | mime + size + path |
| Files | `image` | `VARCHAR(500)` | mime (image/*) + dimensions |
| Iran-specific | `phone` | `VARCHAR(32)` | `Validator::phone` |
| Iran-specific | `mobile` | `VARCHAR(32)` | `Validator::mobile` (returns operator) |
| Iran-specific | `sheba` | `VARCHAR(32)` | `Validator::sheba` (mod-97) |
| Iran-specific | `national_id` | `VARCHAR(16)` | `Validator::nationalId` (mod-11) |
| Iran-specific | `card` | `VARCHAR(20)` | `Validator::cardNumber` (Luhn + BIN lookup) |

System objects (seeded on install) include `account`, `contact`, `opportunity`, `project`, `contract`, `asset`. They are deletable in the schema but flagged `is_system = 1` so the admin UI can warn before dropping them.

The polymorphic Relations engine (`Enterprise\Relations`) lives on top of this — `from_type + from_id ↔ to_type + to_id` with 17 named relation types:

```
contact_to_org, contact_to_deal, contact_to_lead, contact_to_invoice, contact_to_ticket,
deal_to_org, deal_to_invoice, deal_to_owner, deal_to_contact,
employee_to_manager, employee_to_department,
invoice_to_payment, invoice_to_order, invoice_to_refund,
order_to_customer, ticket_to_contact, ticket_to_assignee
```

In multi-tenant mode (v2.0.0), every record gets a `tenant_id` + `branch_id` index automatically, and the Multitenant Context filters by these on every read.

---

## Double-entry accounting

The Ledger (`Enterprise\Modules\Accounting\Ledger`) is a hardened engine with five non-negotiable rules:

1. **Mathematical balance.** Every journal entry is validated so that `Σ debit == Σ credit` within a `0.005` tolerance. Imbalance throws `UnbalancedEntryException`.
2. **Atomicity.** Every entry is wrapped in `START TRANSACTION` / `COMMIT` / `ROLLBACK`. The partial-write window is zero.
3. **Immutability.** Posted entries cannot be edited. Reversal is implemented as a counter-entry that flips the original's status to `reversed` and points to the reversal via `reversed_by`. The audit trail is preserved.
4. **Period awareness.** Entries are tagged with a `fiscal_period_id`; posting into a `closed` period throws `ClosedPeriodException`.
5. **Active account gate.** Posting into an `is_active = 0` account throws `InactiveAccountException`.

Reports generated directly from the immutable ledger (via `Enterprise\Modules\Accounting\Reports`):

- **Trial Balance** (per company / per period)
- **General Journal** (with date range, source, account filters)
- **Income Statement** (P&L, by date range)
- **Balance Sheet** (as-of date)

The default Chart of Accounts is the Iranian 5-digit structure (1xxxx assets, 2xxxx liabilities, 3xxxx equity, 4xxxx revenue, 5xxxx expenses, 6xxxx memo/off-balance) with 70+ Persian-labeled accounts seeded on install via `ChartOfAccounts::installDefaults()`.

---

## Iranian localization — deep, not skinned

Persian-first is engineered at every layer.

| Concern | How ParsYar handles it |
|---------|------------------------|
| **Calendar** | Dual Jalali algorithm in `Enterprise\Jalali`: 33-year cycle (range 1244–1473 SH) and 2820-year cycle (wider range). `fromGregorian`, `toGregorian`, `format`, `isLeap`, `daysInMonth`, `weekStart` (Saturday=0). |
| **Persian digits** | `Enterprise\Validator::persianToEnglish()` is applied automatically to every string field with a numeric type. `۰۹۱۲...` and `0912...` are treated identically throughout the system. `englishToPersian()` available for output. |
| **National ID** | 10-digit mod-11 checksum for natural persons (`Validator::nationalId`). 11-digit for legal entities (`Validator::legalId`). Rejects all-same-digit codes. |
| **Sheba / IBAN** | 26-character Iranian IBAN with mod-97 validation (`Validator::sheba`). Auto-strips spaces, uppercases. |
| **Mobile** | Regex validation + operator detection by prefix (MCI, MTN Irancell, Rightel, Mokhaberat) (`Validator::mobile`). Returns `{valid, operator, normalized}`. |
| **Card number** | Luhn check + BIN lookup against 23 Iranian banks (`Validator::cardNumber`). Returns `{valid, bank, bank_code}`. |
| **Postal code** | 10-digit format validation (`Validator::postalCode`). |
| **Phone** | City-code aware (`Validator::phone`). |
| **Static data** | 31 Iranian provinces, 1200+ cities, 23 banks (with BIN + Sheba prefix), mobile operator prefixes, currencies, languages, industries — all loadable via `Enterprise\Data::{provinces,banks,mobilePrefixes,currencies,languages,industries}()` and `wp_cache`-backed for 24 h. |
| **Tax (Māndian)** | `Enterprise\Modules\Tax\MoodianClient` — full e-invoice API v2 client with JWS signing, 4 invoice types (sale/purchase/return/correction), 2 patterns (B2B/B2G and B2C e-Archive), 10+ error codes translated to Persian, UID/reference persistence, audit logging, `enterprise_event` firing. |
| **Fiscal year** | Defaults to Iranian year starting ~21 March. `parsyar_fiscal_type` (iranian / gregorian / custom) and `parsyar_fiscal_start_md` configurable. |
| **Date locale** | First day of week is Saturday. Jalali months in Persian (فروردین، اردیبهشت، …). |
| **Persian slug** | Compatible with Persian permalinks via WordPress core + theme's `parsyar_is_dashboard()` guard. |
| **i18n on PWA + Mobile** | `fa-IR` is the default locale in both the PWA portal and the React Native app. `react-native-localize` auto-detects the device locale on first launch. |
| **Persian labels in COA** | Every account seeded in the Chart of Accounts has a `label_fa` field, used in trial balance and financial statements. |
| **Jalali in date pickers** | All admin date pickers accept and display Jalali dates, with two-way binding to the underlying `DATETIME` columns. |

---

## The 23-step Setup Wizard

Activation launches an **AJAX-only, resumable, skippable** installer that persists state in `wp_options['parsyar_wizard_state']`. State can be **exported to JSON and re-imported** on another site for fleet deployment.

| # | Step | Persian label | What it does |
|---|------|---------------|--------------|
| 1 | Welcome + system check | خوش‌آمدگویی | PHP/WP/MySQL/ext/memory/permalinks/cron/HTTPS/upload dir |
| 2 | Language & locale | زبان و منطقهٔ زمانی | FA/EN/AR/RU + timezone + first day of week + number format |
| 3 | Organization profile | پروفایل سازمان | legal, national ID, economic code, logo, stamp, signature |
| 4 | Multi-company (Holding) | شرکت‌های چندگانه | activates multi-tenant mode (v2.0.0) |
| 5 | Branches | شعب و دپارتمان‌ها | multi-branch lite mode (v2.0.0) |
| 6 | Currencies & exchange | ارزها و نرخ تبدیل | IRT/IRR/USD/EUR/AED/TRY + OpenExchangeRates / CBR / TGJU / manual |
| 7 | Fiscal year | سال مالی | Iranian / Gregorian / custom start date |
| 8 | Jalali settings | تنظیمات تقویم شمسی | astronomical / 2820 / 33 + date format |
| 9 | Pipelines | خطوط فروش | default 6 stages (سرنخ → واجد شرایط → پیشنهاد → مذاکره → برنده / باخته) |
| 10 | Taxes | مالیات و عوارض | VAT 10% + withholding + exemptions |
| 11 | Modules | ماژول‌ها | toggle pillars |
| 12 | Users & roles | کاربران و نقش‌ها | Super Admin / Admin / Sales Manager / Sales Rep / Support / Marketing / HR / Accountant / Read-only |
| 13 | Notification channels | کانال‌های اعلان | SMTP + Kavenegar / Melipayamak / Ghasedak / SMS.ir / Web Push + In-app + FCM/APNs (mobile) |
| 14 | Payment gateways | درگاه‌های پرداخت | Zarinpal, IDPay, NextPay, Saman, Pasargad, Mellat, Saderat, AsanPardakht |
| 15 | Iranian integrations | یکپارچگی‌های ایرانی | Māndian, Shaparak, Tax, Post, Jibit, Finnotech, Neshan, Map.ir |
| 16 | WooCommerce sync | فروشگاه اینترنتی | opt-in bridge (when WooCommerce is present) |
| 17 | Import data | ورود داده | CSV/Excel with column-mapping UI |
| 18 | Demo data | دادهٔ نمونه | seeds accounts, leads, products, invoices, employees |
| 19 | Theme & branding | قالب و برندینگ | logo, login logo, email logo, favicon, primary font, accent color |
| 20 | AI assistant | دستیار هوش مصنوعی | OpenAI / Anthropic / Local-LLM / Rasa / Hugging Face |
| 21 | Security | امنیت | 2FA required, IP allowlist, password policy, audit retention |
| 22 | Backups & webhooks | پشتیبان‌گیری و Webhook | schedule (daily/weekly), destination (local/email/S3/FTP), keep-last, signing secret |
| 23 | Done | پایان | summary + jump links to the dashboard |

UI rules: left rail with status, top progress bar, AJAX-only, state in `wp_options`, resumable after browser close, exportable/importable JSON. The `Enterprise\Db\DemoSeeder` (used by step 18) seeds Chart of Accounts, currencies, default pipelines, and sample leads/products/employees.

---

## REST API

Base URL: `https://example.com/wp-json/enterprise/v1`

All responses use a uniform envelope:

```json
{
  "success": true,
  "data":    { "...": "..." },
  "meta":    { "request_id": "req_abc123" }
}
```

Errors carry a machine-readable code:

```json
{
  "success": false,
  "error":   { "code": "parsyar.ledger.unbalanced", "message": "...", "details": {} },
  "meta":    { "request_id": "req_abc123" }
}
```

**Three auth methods:**

1. **WordPress cookie + nonce** for in-app admin use.
2. **JWT** for the React SPA, the PWA portal, and the mobile app (`POST /auth/login` → `{access, refresh, profile}`).
3. **Application Passwords** for any external client.

**Multi-tenant headers (v2.0.0):**

```
X-ParsYar-Company: 1   # tenant id (or uuid)
X-ParsYar-Branch:  2   # branch id (or uuid)
```

The Multitenant Context resolves the active scope via header → query var → user meta → default tenant.

**Current routes** (extensible — add more under `api/`):

```
# ── Auth ────────────────────────────────────────────────────────────────
POST   /auth/login                                  JWT login
GET    /auth/me                                     Current user

# ── Objects (custom object engine) ──────────────────────────────────────
GET    /objects                                     List custom object schemas
POST   /objects                                     Create new object schema (admin)
GET    /objects/{api}                               Get object schema + fields
GET    /objects/{api}/records                       List records (filters, paging)
POST   /objects/{api}/records                       Create record (Iranian validation runs)
GET    /records/{id}                                Get record
PUT    /records/{id}                                Update record
DELETE /records/{id}                                Delete record

# ── Accounting ─────────────────────────────────────────────────────────
GET    /accounting/accounts                         Chart of Accounts (tree)
GET    /accounting/journal                          Journal entries (filters)
POST   /accounting/journal                          Post new entry (double-entry enforced)
GET    /accounting/trial-balance                    Trial balance

# ── CRM ────────────────────────────────────────────────────────────────
GET    /crm/leads                                   List leads
POST   /crm/leads                                   Create lead (capture/dedup/scoring)
GET    /crm/contacts                                List contacts
POST   /crm/contacts                                Create contact
GET    /crm/deals                                   List deals
POST   /crm/deals                                   Create deal
GET    /crm/pipelines                               List pipelines
GET    /crm/activities                              Activity feed

# ── ERP ────────────────────────────────────────────────────────────────
GET    /erp/products                                List products
POST   /erp/products                                Create product
GET    /erp/warehouses                              List warehouses
POST   /erp/invoices                                Create invoice
GET    /erp/invoices                                List invoices
GET    /erp/orders                                  List orders
POST   /erp/orders                                  Create order
GET    /erp/payments                                List payments
GET    /erp/refunds                                 List refunds

# ── HRM ────────────────────────────────────────────────────────────────
GET    /hrm/employees                               List employees
POST   /hrm/employees                               Create employee
GET    /hrm/attendance                              Attendance grid
POST   /hrm/attendance/check-in                     Punch in
POST   /hrm/attendance/check-out                    Punch out
GET    /hrm/leave                                   Leave requests
POST   /hrm/payroll/run                             Run payroll

# ── Tax (Māndian) ───────────────────────────────────────────────────────
POST   /tax/invoices/{id}/submit                    Submit invoice to Māndian (e-invoice)
GET    /tax/invoices/{id}/inquiry                   Inquiry reference from Māndian

# ── Workflow ────────────────────────────────────────────────────────────
GET    /workflows                                   List workflows
POST   /workflows                                   Create workflow
GET    /workflows/{id}                              Get workflow
PUT    /workflows/{id}                              Update workflow
DELETE /workflows/{id}                              Delete workflow
POST   /workflows/{id}/run                          Trigger run
GET    /workflows/{id}/runs                         List runs
GET    /workflows/{id}/logs                         List run logs
GET    /workflows/templates                         Built-in templates
GET    /workflows/node-types                        Available node types
GET    /workflows/triggers                           Available triggers
GET    /workflows/stats                             Workflow statistics

# ── Reports ─────────────────────────────────────────────────────────────
GET    /reports                                     List custom reports
POST   /reports                                     Create report
GET    /reports/{id}                                Get report
PUT    /reports/{id}                                Update report
DELETE /reports/{id}                                Delete report
GET    /reports/{id}/run                            Run report (returns result set)
GET    /reports/{id}/export.csv                     Export result as CSV (UTF-8 BOM)
POST   /reports/preview                             Preview without saving
GET    /reports/sources                             Available data sources
GET    /reports/meta                                Field metadata for a source
GET    /reports/templates                           Built-in report templates

# ── Portal (v1.7.0) — namespace: /portal ────────────────────────────────
POST   /portal/auth/magic-link                      Request magic link
POST   /portal/auth/verify                          Verify token → JWT pair
POST   /portal/auth/refresh                         Rotate access token
GET    /portal/auth/me                              Current portal session
GET    /portal/profile                              Contact profile
PUT    /portal/profile                              Update profile
GET    /portal/invoices                             Invoices for the contact
GET    /portal/orders                               Orders
GET    /portal/payments                             Payments
GET    /portal/tickets                              Support tickets
POST   /portal/tickets                              Create ticket
POST   /portal/tickets/{id}/reply                   Add customer reply
POST   /portal/quote-requests                       Submit a quote request
GET    /portal/quote-requests                       List my quote requests
POST   /portal/push/subscribe                       WebPush VAPID subscribe
POST   /portal/push/unsubscribe                     WebPush unsubscribe
POST   /portal/events                               Client telemetry
GET    /portal/info                                 VAPID public key + portal config

# ── Mobile (v1.8.0) — namespace: /mobile ────────────────────────────────
GET    /mobile/info                                 Feature flags + min app version + maintenance mode
POST   /mobile/devices/register                     Register FCM/APNs token
POST   /mobile/devices/heartbeat                    Refresh last_seen_at
DELETE /mobile/devices/{id}                         Unregister
POST   /mobile/notifications/test                   Send a test push to the authenticated device

# ── Multitenant (v2.0.0) — namespace: /tenants ──────────────────────────
GET    /tenants                                     List tenants (admin)
POST   /tenants                                     Create tenant
GET    /tenants/{id}                                Get tenant
PUT    /tenants/{id}                                Update tenant
DELETE /tenants/{id}                                Archive tenant
GET    /tenants/current                             Active tenant (resolved by Context)
GET    /tenants/me                                  Current user memberships
POST   /tenants/switch                              Switch active tenant + branch
GET    /tenants/{id}/branches                       List branches
POST   /tenants/{id}/branches                       Create branch
GET    /tenants/{id}/members                        List memberships
POST   /tenants/{id}/members                        Add member

# ── Audit ──────────────────────────────────────────────────────────────
GET    /audit                                       Read audit log (admin)
```

Every endpoint declares `permission_callback` mapped to a capability (`manage_enterprise`, `edit_enterprise_records`, `manage_enterprise_accounting`, `manage_enterprise_hr`, etc.) so unauthorized callers are rejected at the WP layer, not in the controller. The `SecurityHeaders` class adds CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, and Permissions-Policy to every REST response.

---

## CLI

`wp-cli` commands under the `parsyar` namespace (see `includes/class-cli.php`):

```bash
wp parsyar status                          # System health snapshot
wp parsyar cache info                      # Cache hits/misses by backend
wp parsyar cache flush                     # Invalidate all namespaces
wp parsyar db install                      # Run migrations
wp parsyar db seed                         # Seed defaults (Chart of Accounts, currencies)
wp parsyar db demo                         # Load demo data
wp parsyar reports trial-balance           # Print trial balance
wp parsyar reports income                  # Print income statement
wp parsyar reports balance-sheet           # Print balance sheet
wp parsyar reports journal                 # Print general journal
wp parsyar ledger post --from-json=…       # Post a journal entry from JSON
wp parsyar notifications test --to=09…     # Test SMS provider
wp parsyar notifications test --to=a@b     # Test SMTP
wp parsyar objects list                    # List custom object schemas
wp parsyar objects create                  # Create a new object schema
wp parsyar objects delete                  # Delete an object schema
wp parsyar workflow run --id=N             # Trigger a workflow run
wp parsyar tax moodian submit --id=N       # Submit invoice to Māndian
wp parsyar backup create                   # Export backup JSON
wp parsyar backup restore file.json        # Restore from backup JSON
```

Plus the legacy alias namespace (`wp enterprise …`) for backward compatibility:

```bash
wp enterprise doctor
wp enterprise demo load
wp enterprise demo purge
wp enterprise db check
wp enterprise db optimize
wp enterprise audit prune --days=365
wp enterprise user create-admin ...
wp enterprise config export > cfg.json
wp enterprise config import cfg.json
wp enterprise migrate run
```

---

## Theme and SPA dashboard

The **enterprise-theme** is a hybrid:

- **Classic WordPress templates** for the public-facing site (`front-page.php`, `page.php`, `single.php`, `archive.php`, `search.php`, `404.php`, `comments.php`, `woocommerce.php`).
- **A React 18 + Tailwind 3 + Vite SPA** for the admin dashboard (mounted on the `page-dashboard.php` template, available at `/enterprise/*` via the front-end router).

The design system is **ultra-minimal, pure black & white, glassmorphism touches, neo-brutalist accents, motion-first**:

- Design tokens: 5 surfaces × 5 ink shades + status colors, in `assets/css/tokens.css`.
- 7 CSS layers: tokens → base (reset, typography, container, stack, grid) → layout (app shell, topbar, sidebar, right rail) → components (buttons, inputs, cards, badges, avatars, tabs, tables, modals, toasts, alerts) → pages → RTL → print.
- 19-pillar sidebar grouped into **main / business / ops / system**.
- Command palette (`Cmd/Ctrl+K`) with fuzzy multilingual search.
- Toasts bottom-right (LTR) / bottom-left (RTL), 4s auto-dismiss, stackable.
- `prefers-reduced-motion` respected.

Dashboard template-parts (already built): `kpi-card`, `kpi-grid`, `pipeline-mini`, `activity-feed`, `todo-list`, `revenue-chart`, `birthdays-widget`.

Record views (already built): `list-view`, `kanban-view`, `calendar-view`, `gallery-view`, `detail-view`.

Pages in the React SPA: `Dashboard`, `ObjectsList`, `RecordsList`, `Accounting`, `Invoices`, `Leads`, `Products`, `Employees`, `Workflows`, `Reports`, `Audit`.

The `enterprise/index.php` fallback provides a no-JS path so the dashboard is still navigable when the SPA bundle is not yet built.

---

## Customer Portal (PWA) — v1.7.0

A separate front-end application inside the theme, built with **React 18 + Vite 5 + Tailwind 3 + Workbox 7** and shipped at `enterprise-theme/portal-pwa/`. It is the customer-facing self-service surface: invoices, orders, payments, support tickets.

**Auth flow:**

```
┌────────┐  email  ┌────────┐   POST /portal/auth/magic-link   ┌────────┐
│ Browser│────────▶│  WP    │──────────────────────────────────▶│ Auth   │
│ (PWA)  │         │ site   │   email + rate limit + ban       │Service │
└───┬────┘         └────────┘   returns dev_link in dev mode   └───┬────┘
    │                                                                │
    │ magic link with ?token=…                                       │  hash + store
    │◀───────────────────────────────────────────────────────────────│
    ▼                                                                │
GET /portal/verify?token=…  ────────────────────▶ consume + JWT pair │
    ◀────────────────────── access + refresh (HS256)  ──────────────┘
    │
    │  every API call
    ▼
Authorization: Bearer <jwt>     ───  401? → POST /auth/refresh  →  rotate
```

**Features:**

- **Magic link login** — no passwords, just email. Rate-limited (3 / 15 min) and ban-on-failure.
- **JWT (HS256)** — access (1 h) + refresh (7 d) with rotation on 401. `jti` is single-use; refresh writes a new pair and revokes the old.
- **WebPush (VAPID)** — `wp_parsyar_push_subscriptions` table, opt-in via the in-app push banner (delayed 5 s after dashboard mount).
- **Offline-first** — Service Worker with Workbox, NetworkFirst for `/portal/*`, StaleWhileRevalidate for assets, IndexedDB fallback for last list snapshots.
- **Install banner** — `beforeinstallprompt` is captured and surfaced 5 s after the dashboard mounts.
- **fa-IR default** — `i18next` with Persian bundle; Vazirmatn preconnect in `index.html`; RTL throughout.
- **Vitest** unit tests for the API client (formatCurrency, formatDateJalali, session roundtrip).

**Pages:** Login, Verify, Dashboard, Invoices, Orders, Payments, Tickets (+ New).

**PWA icons shipped:** `pwa-192x192.png`, `pwa-512x512.png`, `pwa-maskable-512x512.png`, `apple-touch-icon.png`.

```bash
cd enterprise-theme/portal-pwa
npm install
npm run dev      # http://localhost:5173 (proxy to WP on :8080)
npm run build    # dist/ — mounted by template `portal.php`
npm run test     # vitest
```

The theme template `portal.php` injects `vapidPublicKey` and support info into `window.parsyarPortalConfig` at render time. The PWA reads this on boot.

---

## Mobile App (React Native) — v1.8.0

A first-class iOS + Android client at `enterprise-mobile/`, built with **React Native 0.75 + TypeScript 5.5 + Redux Toolkit + i18next**. It pairs with the same REST API as the PWA (`/wp-json/enterprise/v1/portal/*` + `/mobile/*`).

**Capabilities:**

- **Magic link login** — email → backend magic link → app deep link → JWT pair.
- **JWT rotation** — Axios interceptor rotates the access token on 401 using the refresh token; persists the new pair in AsyncStorage.
- **Biometric gate** — `react-native-biometrics` (TouchID / FaceID / Fingerprint), opt-in from Settings.
- **Push** — `react-native-push-notification` over FCM (Android) and APNs (iOS). Tokens register via `POST /mobile/devices/register`. The backend dispatches via `MobileModule::sendToDevice()`.
- **Deep linking** — `parsyar://verify?token=…` and Universal Links `https://yourdomain.com/portal/verify?token=…` both wired to the Verify screen.
- **Offline-tolerant** — AsyncStorage caches profile + last list, app degrades gracefully when offline.
- **i18n** — `fa-IR` default, `react-native-localize` picks up the device locale on first launch.
- **State** — Redux Toolkit slices: `auth` (bootstrap, setBaseUrl, requestMagic, verify, logout) + `ui` (online, push, biometric, locale).

**Screens (9):**

| Route | Component | Purpose |
|-------|-----------|---------|
| Login | `LoginScreen.tsx` | Email + device + site URL setup |
| Verify | `VerifyScreen.tsx` | Verifies the magic-link token, lands on Dashboard |
| Dashboard | `DashboardScreen.tsx` | KPIs (open balance, open invoices), recent invoices, profile card |
| Invoices | `InvoicesScreen.tsx` | Pull-to-refresh list with status badges |
| Invoice Detail | `InvoiceDetailScreen.tsx` | Line items + tax invoice UID |
| Orders | `OrdersScreen.tsx` | Pull-to-refresh list |
| Order Detail | `OrderDetailScreen.tsx` | Order detail with status timeline |
| Payments | `PaymentsScreen.tsx` | Pull-to-refresh list with gateway + ref |
| Payment Detail | `PaymentDetailScreen.tsx` | Payment detail with bank + Sheba |
| Tickets | `TicketsScreen.tsx` | List + inline reply modal |
| New Ticket | `NewTicketScreen.tsx` | Form with category/priority chips |
| Ticket Detail | `TicketDetailScreen.tsx` | Conversation thread |
| Profile | `ProfileScreen.tsx` | Contact card + settings shortcut + logout |
| Settings | `SettingsScreen.tsx` | Biometric, push, language toggles |

**Platform config:**

- **iOS** — `Info.plist` enables ATS (HTTPS only), `NSFaceIDUsageDescription`, deep link URL scheme `parsyar`. `Podfile` pins Fabric, Hermes, vector-icons, push.
- **Android** — `AndroidManifest.xml` declares `INTERNET`, `USE_BIOMETRIC`, `POST_NOTIFICATIONS`, deep link `<data>` filters. Network security config allows cleartext only for `localhost` (dev). `build.gradle` enables ProGuard, Hermes, `minSdk 24`.

**CI:** `.github/workflows/mobile-ci.yml` runs `npm ci` → `npm run typecheck` → `npm test --ci --coverage` on every push.

**Backend pairing (v1.8.0):**

The plugin added a `Mobile` module in this release — `Enterprise\Modules\Mobile\Device`, `MobileModule`, and `Repository`:

- `POST /mobile/info` returns feature flags so the client can detect FCM/APNs availability and a maintenance-mode flag.
- `POST /mobile/devices/register` upserts by token (idempotent for re-installs) and binds the device to a portal contact via JWT.
- `POST /mobile/devices/heartbeat` refreshes `last_seen_at`.
- `MobileModule::pruneStaleDevices()` runs on `enterprise_daily` to drop devices idle for more than 180 days.
- The admin page `Mobile App` (Enterprise → Mobile App) shows stats, FCM/APNs config, the minimum supported app version, and a maintenance toggle.

```bash
cd enterprise-mobile
npm install

# Android
npm run android

# iOS
cd ios && pod install && cd ..
npm run ios
```

---

## Multi-tenant SaaS (Holding) — v2.0.0

ParsYar can now run in **single-tenant** (one company, one branch) or **multi-tenant** (one install, many companies, each with many branches) mode. The Holding mode is activated from **Wizard → Step 4** and from then on every record carries `tenant_id` and (optionally) `branch_id`.

**Concepts:**

| Concept | Description |
|---------|-------------|
| **Tenant** | A company — the legal entity that owns its data. Has `uuid`, `slug`, `plan` (`starter` / `pro` / `enterprise`), `settings`, `branding` (logo, colors). |
| **Branch** | A sub-unit of a tenant — a department, a city office, a warehouse. Has `parent_id` for hierarchy, `is_default`, `is_active`, soft delete. |
| **Membership** | The link user ↔ tenant ↔ branch with a `role` (`owner`, `admin`, `manager`, `member`, `viewer`). `UNIQUE (user_id, tenant_id, branch_id)`. |

**Context resolution (every request):**

```
X-ParsYar-Company header  →  ?parsyar_company query var  →
    user_meta 'parsyar_default_company_id'  →  first tenant the user is a member of
```

The `Enterprise\Modules\Multitenant\Context` is registered on `rest_pre_dispatch` and resolves the active scope before the controller runs. Any domain that opts in (`is_tenant_scoped = 1`) gets its queries rewritten to include `tenant_id = ?` and (when applicable) `branch_id = ?`.

**REST API (13 endpoints, see REST section above):**

- `/tenants`, `/tenants/{id}`, `/tenants/current`, `/tenants/me`, `/tenants/switch`
- `/tenants/{id}/branches`, `/tenants/{id}/members`

`POST /tenants/switch` grants branch access and persists `parsyar_default_company_id` + `parsyar_default_branch_id` in `user_meta`. The next request from the same user automatically uses the new scope.

**Operations:**

- `Multitenant\Repository::boot()` registers a daily `enterprise_daily` hook that prunes tenants archived for more than 90 days and warms the tenant cache group.
- `Multitenant\Repository::cacheGroup()` returns a deterministic cache key per tenant — used by every service that has tenant-scoped reads (Contacts, Invoices, Products, …) so the same query never crosses tenant boundaries.
- The installer seeds a default tenant on first activation so single-tenant sites keep working unchanged.

**Activation paths:**

- **Single-tenant** — install → wizard runs without Holding mode → one tenant created automatically → all existing data lives in it.
- **Multi-tenant** — install → Step 4 (Holding) toggled → wizard creates the parent tenant from the org profile → additional tenants created via admin UI or `/tenants` API.

---

## Installation

```bash
# 1. Clone
git clone https://github.com/QalamHipHop/ParsYar.git

# 2. Copy into a WordPress 6.5+ install
cp -r ParsYar/enterprise-core-plugin/*  /path/to/wordpress/wp-content/plugins/enterprise-core-plugin/
cp -r ParsYar/enterprise-theme/*        /path/to/wordpress/wp-content/themes/enterprise-theme/

# 3. Build the admin SPA (Node 20 LTS)
cd /path/to/wordpress/wp-content/themes/enterprise-theme
npm install
npm run build

# 4. Build the customer portal PWA (Node 20 LTS)
cd portal-pwa
npm install
npm run build
cd ..

# 5. Activate
wp plugin activate enterprise-core-plugin
wp theme activate enterprise

# 6. (Optional) Build the mobile app
cd ParsYar/enterprise-mobile
npm install
cd ios && pod install && cd ..
npm run android   # or: npm run ios
```

On first activation, the Setup Wizard opens automatically. To load demo data:

```bash
wp parsyar db demo
# or
wp enterprise demo load
```

To run the test suite (PHP):

```bash
cd enterprise-core-plugin
composer install
composer test           # full suite
composer test:unit      # unit only
composer test:coverage  # with coverage
composer phpcs          # WordPress-Extra coding standard
composer stan           # PHPStan level 8
```

To run the mobile test suite:

```bash
cd enterprise-mobile
npm install
npm test
```

---

## System requirements

- **PHP 8.1+** (8.2 or 8.3 recommended) with `declare(strict_types=1)`
- **WordPress 6.5+**
- **MySQL 8.0+** or **MariaDB 10.6+**
- **Required PHP extensions**: `mbstring`, `intl`, `mysqli`, `json`, `openssl`, `gd`, `zip`, `curl`, `fileinfo`, `ctype`, `iconv`, `bcmath`
- **Recommended PHP settings**: `memory_limit >= 256M`, `max_execution_time >= 60`, `upload_max_filesize >= 32M`
- **HTTPS** required for payment gateway APIs, the Māndian client, push notifications, and the mobile app
- **Object cache** (Redis or Memcached) for high-traffic deployments — auto-falls back to transients
- **Node 20 LTS** for building the theme SPA, the PWA, and the mobile app
- **Xcode 15+** (iOS), **Android Studio Koala+** with **minSdk 24** (Android)

---

## Security

- All REST endpoints guarded by `permission_callback` and nonce.
- All admin forms use `wp_nonce_field()` + `current_user_can()`.
- All queries go through `Enterprise\Support\Db` (which wraps `wpdb::prepare()`).
- All output escaped with `esc_html`, `esc_attr`, `esc_url`, `wp_kses`.
- CSP headers on REST responses (`SecurityHeaders` class).
- 2FA (TOTP), IP allowlist, device management, password policy, audit retention — all configurable in the wizard (Step 21).
- Rate limit: 60 requests/min per user/IP, configurable via `ENTERPRISE_RATE_LIMIT_PER_MINUTE`.
- Append-only audit log (`wp_parsyar_audit_log`) protected at the schema level (no DELETE grant).
- Ledger entries are immutable; reversal is implemented as a counter-entry, never a delete.
- JWT secrets and VAPID keys are auto-generated and stored in `wp_options`; never logged.
- Mobile and PWA authenticate with short-lived JWTs (1 h access) + long-lived refresh tokens (7 d); refresh is single-use and rotates the jti.
- Multi-tenant isolation: every tenant-scoped query is rewritten to filter by `tenant_id`; cross-tenant reads are blocked at the SQL level.
- All exceptions extend `Enterprise\Core\Exception` and carry a `parsyar.*` machine-readable error code so client code can branch on the exact failure mode.
- Persian transliteration does not bypass validators — the Validator normalizes `۰۹۱۲...` to ASCII before the checksum, so a Persian-digit national ID still passes mod-11.

---

## Roadmap

- [x] **1.0.0** — Object Engine + Ledger + REST + Demo (Genesis Build)
- [x] **1.0.1** — Jalali (33-year + 2820-year), Iranian validators
- [x] **1.1.0** — Full CRM (Leads, Contacts, Deals, Activities, scoring, dedup, pipeline)
- [x] **1.2.0** — Full ERP (Products, Inventory, Invoices, Payments, Refunds)
- [x] **1.2.1** — Migration fixes (parsyar_* tables, tax_invoice_uid, rate limit, CSP)
- [x] **1.3.0** — 23-step Setup Wizard + Māndian e-Invoice (full spec, JWS-signed)
- [x] **1.4.0** — Full HRM (Employees, Attendance, Leave, Payroll, Performance)
- [x] **1.5.0** — Visual Workflow Editor (drag-drop node graph + 12 node types + templates)
- [x] **1.6.0** — Custom Report Builder (11 sources, 5 chart types, CSV export, templates)
- [x] **1.7.0** — Customer Portal (PWA, magic link, JWT, WebPush, offline-first)
- [x] **1.8.0** — Mobile app (React Native, FCM/APNs, biometric, deep link, 9 screens)
- [x] **2.0.0** — Multi-tenant SaaS (Holding: tenants, branches, memberships, context)
- [ ] **2.1.0** — AI assistant wiring (OpenAI / Anthropic / local LLM) inside the wizard
- [ ] **2.2.0** — Public API + OpenAPI 3.1 spec + SDK generator
- [ ] **2.3.0** — Webhooks v2 (per-event signing, retry, dead-letter)
- [ ] **2.4.0** — Real-time collaboration (Yjs + WebSocket gateway)
- [ ] **2.5.0** — Marketplace for vertical packs (real-estate, clinic, school, retail)

---

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md) for the full contract: branch naming, commit message format (Conventional Commits), PR rules, testing requirements, code-style enforcement (PSR-12 + WordPress-Extra), and code of conduct.

**Commit convention**: `feat(module): subject`, `fix(module): subject`, `refactor(module): subject`, `perf`, `docs`, `test`, `chore`.

**Branch naming**: `feat/`, `fix/`, `refactor/`, `perf/`, `docs/`, `test/`, `chore/`.

**Author**: QalamHiphop `<qalam@parsyar.dev>` — GitHub: [QalamHipHop](https://github.com/QalamHipHop)

---

## License

GPL-2.0-or-later. Free for commercial use, modification, and distribution. Modifications must remain GPL.

See [`LICENSE`](LICENSE) for the full text.

---

<div align="center">

**ساخته‌شده با دقت در تهران · Built with care in Tehran**

</div>
