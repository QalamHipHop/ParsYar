# ParsYar (پارس‌یار)

> **Enterprise CRM/ERP/HCM platform — built natively on WordPress, fluent in Persian, compliant with Iranian regulation.**

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4)](https://www.php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.5%2B-21759b)](https://wordpress.org)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B%20%7C%20MariaDB-10.6%2B-4479a1)](https://www.mysql.com)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)](LICENSE)
[![Version](https://img.shields.io/badge/version-1.3.0-black)]()
[![CI](https://github.com/QalamHipHop/ParsYar/actions/workflows/ci.yml/badge.svg)](https://github.com/QalamHipHop/ParsYar/actions/workflows/ci.yml)
[![Architecture](https://img.shields.io/badge/architecture-layered-black)]()
[![No third-party deps](https://img.shields.io/badge/dependencies-zero-black)]()

The name **پارس‌یار** means *"Persian Companion"*. The tagline in Farsi: **«CRM که به فارسی می‌اندیشد، در مقیاس جهانی می‌درخشد.»** — *"A CRM that thinks in Persian, scales in global."*

---

## Table of contents

- [Why ParsYar](#why-parsyar)
- [What is in the box](#what-is-in-the-box)
- [Architecture](#architecture)
- [Modules](#modules)
- [The Custom Object Engine](#the-custom-object-engine)
- [Double-entry accounting](#double-entry-accounting)
- [Iranian localization — deep, not skinned](#iranian-localization--deep-not-skinned)
- [The 23-step Setup Wizard](#the-23-step-setup-wizard)
- [REST API](#rest-api)
- [CLI](#cli)
- [Theme and SPA dashboard](#theme-and-spa-dashboard)
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

- **No third-party plugin dependencies.** No ACF, no WooCommerce, no JetEngine, no Pods, no Toolset. Everything — the custom object engine, the ledger, the workflow runner, the audit log, the Iranian e-invoice client — is built in.
- **Persian-first is not a skin.** The validator layer treats `۰۹۱۲...` and `0912...` identically. The double-entry ledger writes 70+ account codes with Persian labels out of the box. The calendar engine ships a 2820-year Jalali algorithm.
- **Iranian regulatory stack is wired into the write path.** Send a sale invoice, and the system knows it must also build a `header.body.invoice` payload, sign it with JWS (RSA-SHA256), and POST it to `tp.tax.gov.ir`. Send a payment, and the Sheba gets validated, the card BIN gets looked up, and the bank is identified.
- **Salesforce-style metadata layer.** Admins can define new entities (objects) and fields at runtime, with 22 field types, Flat-Table DDL, auto-indexing, and validation rules — without writing a single line of PHP.
- **Hardened for production.** Strict types, prepared statements everywhere, append-only audit log, double-entry guarantee, rate-limited REST, capability-based authorization.

---

## What is in the box

```
ParsYar/
├── enterprise-core-plugin/        # PHP 8.1+ plugin (the brain)
│   ├── enterprise-core.php        # Bootstrap singleton (PSR-4 autoload, hooks, install)
│   ├── includes/                  # Core engines
│   ├── modules/                   # Domain modules (CRM, ERP, HRM, ...)
│   ├── api/                       # REST controllers
│   ├── admin/                     # WP admin pages + 23-step wizard
│   └── db/                        # Demo seeder
└── enterprise-theme/              # React 18 + Tailwind 3 + Vite SPA dashboard
    ├── src/                       # SPA source (App, pages, REST client)
    ├── assets/css/                # Tokens, base, layout, components, RTL, print
    ├── template-parts/            # Dashboard widgets, record views
    └── functions.php              # Theme bootstrap, asset loader, i18n
```

Two artifacts, one product. The **plugin** is the engine. The **theme** is the surface.

---

## Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│                         Presentation                             │
│  ┌───────────────────────┐    ┌─────────────────────────────┐    │
│  │  React 18 + Tailwind  │    │  Classic WP (PHP templates)  │    │
│  │  (admin dashboard)    │    │  (front-end, portal)         │    │
│  └──────────┬────────────┘    └──────────────┬──────────────┘    │
└─────────────┼─────────────────────────────────┼───────────────────┘
              │                                 │
              ▼                                 ▼
┌──────────────────────────────────────────────────────────────────┐
│                       REST API (/wp-json/enterprise/v1)          │
│  - Authentication (JWT / Cookie+Nonce / App Passwords)            │
│  - Rate Limiting (60 req/min per user/IP)                         │
│  - Capability-based Authorization                                 │
│  - Uniform response envelope: {success, data, meta}               │
│  - Machine-readable error codes: parsyar.ledger.unbalanced, ...  │
└────────────────────────────┬─────────────────────────────────────┘
                             │
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│                        Application Core                          │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌──────────┐  │
│  │  Objects   │  │ Accounting │  │  Workflow  │  │  Audit   │  │
│  │  Engine    │  │ (Ledger)   │  │  Engine    │  │  Logger  │  │
│  └─────┬──────┘  └─────┬──────┘  └─────┬──────┘  └─────┬────┘  │
│        │               │               │               │        │
│        └───────────────┴───────────────┴───────────────┘        │
│                              │                                    │
│                              ▼                                    │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │    Domain Modules: CRM · ERP · HRM · Tax · Inbox · ...     │  │
│  └────────────────────────────────────────────────────────────┘  │
└────────────────────────────┬─────────────────────────────────────┘
                             │
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│                       Persistence Layer                          │
│  ┌─────────────────────────┐    ┌──────────────────────────┐    │
│  │  WordPress core tables  │    │  Custom tables (8 prefix)│    │
│  │  (users, options, posts)│    │  wp_ent_* (engine)       │    │
│  │                         │    │  wp_parsyar_* (modules)  │    │
│  └─────────────────────────┘    └──────────────────────────┘    │
│  Object Cache (Redis/Memcached) — auto-fallback to transients.   │
└──────────────────────────────────────────────────────────────────┘
```

**Layered.** Presentation → REST → Application Core → Domain → Persistence. Each layer talks only to the one below it.

**Modular monolith.** Every feature is a module that can be toggled. Nothing in the core hard-depends on a domain module.

**Audited by default.** Every write goes through `Enterprise\Support\Db`, every change is logged through `Enterprise\Modules\Audit\Logger`, every action is fired as an `enterprise_event` so the Workflow Engine can react.

---

## Modules

| Module | Responsibility | Key tables | Service class |
|--------|---------------|-----------|----------------|
| **Objects** | Custom entity engine (Salesforce-style). 22 field types, flat-table DDL, auto-indexing, polymorphic relations | `wp_ent_objects`, `wp_ent_object_fields`, `wp_ent_object_relations`, `wp_ent_data_*` | `Enterprise\Modules\Objects\ObjectEngine` + `RecordStore` + `SchemaBuilder` |
| **Accounting** | Double-entry ledger with mathematical balance guarantee, full Chart of Accounts, fiscal periods, financial statements | `wp_ent_accounts`, `wp_ent_journal_entries`, `wp_ent_journal_lines`, `wp_ent_fiscal_periods` | `Enterprise\Modules\Accounting\Ledger` + `ChartOfAccounts` |
| **CRM** | Lead capture, scoring, deduplication, pipeline + kanban, deal forecasting | `wp_parsyar_leads`, `wp_parsyar_contacts`, `wp_parsyar_deals`, `wp_parsyar_activities` | `LeadService`, `ContactService`, `PipelineService`, `ActivityService`, `OrganizationService` |
| **ERP** | Multi-warehouse inventory, lots/serials, FIFO/LIFO/WAC costing, sales invoices, payments, refunds | `wp_parsyar_products`, `wp_parsyar_warehouses`, `wp_parsyar_stock_movements`, `wp_parsyar_invoices`, `wp_parsyar_orders`, `wp_parsyar_payments`, `wp_parsyar_refunds` | `InventoryService`, `WarehouseService`, `ProductCategoryService`, `StockMovementService`, `InvoiceService`, `OrderService`, `PaymentService`, `RefundService` |
| **HRM** | Employees, attendance, payroll with Iranian tax brackets, contracts, performance | `wp_parsyar_employees`, `wp_parsyar_attendance`, `wp_parsyar_payroll_*` | `EmployeeService`, `PayrollService` |
| **Tax (Māndian)** | E-invoice client for `tp.tax.gov.ir`. JWS signing, both Patterns (B2B & B2C), 4 invoice types, inquiry endpoint | `wp_parsyar_invoices` (extended), `wp_parsyar_tax_invoice_*` | `Enterprise\Modules\Tax\MoodianClient` |
| **Inbox** | Omnichannel aggregator: email, SMS, WhatsApp, Telegram, Bale, Instagram, webchat, voice | `wp_parsyar_inbox_*` | `InboxService` |
| **Calendar** | Recurring events, Jalali bridge, attendee tracking, reminders | `wp_parsyar_calendar_*` | `CalendarEngine` |
| **Workflow** | Visual automation: triggers, conditions, actions, scheduled runs, run logs | `wp_parsyar_workflows`, `wp_parsyar_workflow_runs`, `wp_parsyar_workflow_logs` | `Enterprise\Modules\Workflow\Dispatcher` + `Repository` |
| **Audit** | Append-only change log. Schema-level protected (no DELETE grant). | `wp_parsyar_audit_log` | `Enterprise\Modules\Audit\Logger` |
| **Multitenant** | Multi-company / multi-branch context (opt-in, activated by Holding mode in the wizard) | (uses parsyar_companies, parsyar_branches in options) | `Enterprise\Modules\Multitenant\Context` |
| **Setup Wizard** | 23-step guided installer with import/export of configuration, demo seed, system check | (uses `wp_options` for resumable state) | `Enterprise\Admin\Wizard` + `WizardState` |

---

## The Custom Object Engine

This is the centerpiece. Admins can define a new entity at runtime — *no code* — and ParsYar will:

1. Create a row in `wp_ent_objects` (metadata).
2. Create one row per field in `wp_ent_object_fields` (metadata).
3. Generate a **dedicated MySQL table** `wp_ent_data_{api_name}` with one typed column per field, plus auto-indexes for searchable fields.
4. Wire all 22 field types' validators into the write path (Persian digit normalization, mobile operator detection, Sheba mod-97, card Luhn + BIN, national-ID mod-11, postal-code format, etc.).
5. Emit `enterprise_event` for every write so the Workflow Engine can react.

The 22 supported field types (see `Enterprise\Modules\Objects\FieldTypes::ALL`):

| Group | Types |
|-------|-------|
| Text | `text`, `textarea`, `rich`, `email`, `url` |
| Numbers | `int`, `decimal`, `bool` |
| Date/Time | `date`, `datetime`, `jalali` |
| Choice | `enum`, `multi`, `json` |
| Relations | `fk` (foreign key to another object) |
| Files | `file`, `image` |
| Iran-specific | `phone`, `mobile`, `sheba`, `national_id`, `card` |

System objects (seeded on install) include `account`, `contact`, `opportunity`, `project`, `contract`, `asset`. They are deletable in the schema but flagged `is_system = 1` so the admin UI can warn before dropping them.

The polymorphic Relations engine (`Enterprise\Relations`) lives on top of this — `from_type + from_id ↔ to_type + to_id` with typed relations like `contact_to_org`, `deal_to_invoice`, `employee_to_manager`, etc. (17 named relation types.)

---

## Double-entry accounting

The Ledger (`Enterprise\Modules\Accounting\Ledger`) is a hardened engine with five non-negotiable rules:

1. **Mathematical balance.** Every journal entry is validated so that `Σ debit == Σ credit` within a `0.005` tolerance. Imbalance throws `UnbalancedEntryException`.
2. **Atomicity.** Every entry is wrapped in `START TRANSACTION` / `COMMIT` / `ROLLBACK`. The partial-write window is zero.
3. **Immutability.** Posted entries cannot be edited. Reversal is implemented as a counter-entry that flips the original's status to `reversed` and points to the reversal via `reversed_by`. The audit trail is preserved.
4. **Period awareness.** Entries are tagged with a `fiscal_period_id`; posting into a `closed` period throws `ClosedPeriodException`.
5. **Active account gate.** Posting into an `is_active = 0` account throws `InactiveAccountException`.

Reports generated directly from the immutable ledger:
- **Trial Balance** (per company / per period)
- **General Journal** (with date range, source, account filters)
- **Income Statement** (P&L, by date range)
- **Balance Sheet** (as-of date)

The default Chart of Accounts is the Iranian 5-digit structure (1xxxx assets, 2xxxx liabilities, 3xxxx equity, 4xxxx revenue, 5xxxx expenses, 6xxxx memo/off-balance) with 70+ Persian-labeled accounts seeded on install.

---

## Iranian localization — deep, not skinned

Persian-first is engineered at every layer.

| Concern | How ParsYar handles it |
|---------|------------------------|
| **Calendar** | Dual Jalali algorithm in `Enterprise\Jalali`: 33-year cycle (range 1244–1473 SH) and 2820-year cycle (wider range). `fromGregorian`, `toGregorian`, `format`, `isLeap`, `daysInMonth`, `weekStart` (Saturday=0). |
| **Persian digits** | `Enterprise\Validator::persianToEnglish()` is applied automatically to every string field with a numeric type. `۰۹۱۲...` and `0912...` are treated identically throughout the system. |
| **National ID** | 10-digit mod-11 checksum for natural persons (`Validator::nationalId`). 11-digit for legal entities (`Validator::legalId`). Rejects all-same-digit codes. |
| **Sheba / IBAN** | 26-character Iranian IBAN with mod-97 validation (`Validator::sheba`). Auto-strips spaces, uppercases. |
| **Mobile** | Regex validation + operator detection by prefix (MCI, MTN Irancell, Rightel, Mokhaberat) (`Validator::mobile`). Returns `{valid, operator, normalized}`. |
| **Card number** | Luhn check + BIN lookup against 23 Iranian banks (`Validator::cardNumber`). Returns `{valid, bank, bank_code}`. |
| **Postal code** | 10-digit format validation (`Validator::postalCode`). |
| **Phone** | City-code aware (`Validator::phone`). |
| **Static data** | 31 Iranian provinces, 1200+ cities, 23 banks (with BIN + Sheba prefix), mobile operator prefixes, currencies, languages, industries — all loadable via `Enterprise\Data::*` and wp_cache-backed. |
| **Tax (Māndian)** | `Enterprise\Modules\Tax\MoodianClient` — full e-invoice API v2 client with JWS signing, 4 invoice types (sale/purchase/return/correction), 2 patterns (B2B/B2G and B2C e-Archive), 10+ error codes translated to Persian, UID/reference persistence, audit logging, `enterprise_event` firing. |
| **Fiscal year** | Defaults to Iranian year starting ~21 March. `parsyar_fiscal_type` (iranian / gregorian / custom) and `parsyar_fiscal_start_md` configurable. |
| **Date locale** | First day of week is Saturday. Jalali months in Persian (فروردین، اردیبهشت، …). |
| **Persian slug** | Compatible with Persian permalinks via WordPress core + theme's `parsyar_is_dashboard()` guard. |

---

## The 23-step Setup Wizard

Activation launches an **AJAX-only, resumable, skippable** installer that persists state in `wp_options['parsyar_wizard_state']`. State can be **exported to JSON and re-imported** on another site for fleet deployment.

| # | Step | Persian label | What it does |
|---|------|---------------|--------------|
| 1 | Welcome + system check | خوش‌آمدگویی | PHP/WP/MySQL/ext/memory/permalinks/cron/HTTPS/upload dir |
| 2 | Language & locale | زبان و منطقهٔ زمانی | FA/EN/AR/RU + timezone + first day of week + number format |
| 3 | Organization profile | پروفایل سازمان | legal, national ID, economic code, logo, stamp, signature |
| 4 | Multi-company (Holding) | شرکت‌های چندگانه | activates multi-tenant mode |
| 5 | Branches | شعب و دپارتمان‌ها | multi-branch lite mode |
| 6 | Currencies & exchange | ارزها و نرخ تبدیل | IRT/IRR/USD/EUR/AED/TRY + OpenExchangeRates / CBR / TGJU / manual |
| 7 | Fiscal year | سال مالی | Iranian / Gregorian / custom start date |
| 8 | Jalali settings | تنظیمات تقویم شمسی | astronomical / 2820 / 33 + date format |
| 9 | Pipelines | خطوط فروش | default 6 stages (سرنخ → واجد شرایط → پیشنهاد → مذاکره → برنده / باخته) |
| 10 | Taxes | مالیات و عوارض | VAT 10% + withholding + exemptions |
| 11 | Modules | ماژول‌ها | toggle pillars |
| 12 | Users & roles | کاربران و نقش‌ها | Super Admin / Admin / Sales Manager / Sales Rep / Support / Marketing / HR / Accountant / Read-only |
| 13 | Notification channels | کانال‌های اعلان | SMTP + Kavenegar / Melipayamak / Ghasedak / SMS.ir + Web Push + In-app |
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

UI rules: left rail with status, top progress bar, AJAX-only, state in `wp_options`, resumable after browser close, exportable/importable JSON.

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
2. **JWT** for the React SPA and mobile clients (`POST /auth/login`).
3. **Application Passwords** for any external client.

**Current routes** (extensible — add more under `api/`):

```
POST   /auth/login                                  JWT login
GET    /auth/me                                     Current user

GET    /objects                                     List custom object schemas
POST   /objects                                     Create new object schema (admin)
GET    /objects/{api}                               Get object schema + fields
GET    /objects/{api}/records                       List records (filters, paging)
POST   /objects/{api}/records                       Create record (Iranian validation runs)
GET    /records/{id}                                Get record
PUT    /records/{id}                                Update record
DELETE /records/{id}                                Delete record

GET    /accounting/accounts                         Chart of Accounts (tree)
GET    /accounting/journal                          Journal entries (filters)
POST   /accounting/journal                          Post new entry (double-entry enforced)
GET    /accounting/trial-balance                    Trial balance

GET    /crm/leads                                   List leads
POST   /crm/leads                                   Create lead (capture/dedup/scoring)

GET    /erp/products                                List products
POST   /erp/products                                Create product
GET    /erp/invoices                                List invoices
POST   /erp/invoices                                Create invoice

GET    /hrm/employees                               List employees
POST   /hrm/employees                               Create employee
POST   /hrm/payroll/run                             Run payroll

POST   /tax/invoices/{id}/submit                    Submit invoice to Māndian (e-invoice)

GET    /workflows                                   List workflows
POST   /workflows                                   Create workflow

GET    /audit                                       Read audit log (admin)
```

Every endpoint declares `permission_callback` mapped to a capability (`manage_enterprise`, `edit_enterprise_records`, `manage_enterprise_accounting`, `manage_enterprise_hr`, etc.) so unauthorized callers are rejected at the WP layer, not in the controller.

---

## CLI

```bash
wp enterprise doctor                   # System health
wp enterprise demo load                # Seed demo data
wp enterprise demo purge               # Remove demo data
wp enterprise db check                 # Verify schema
wp enterprise db optimize              # Optimize tables
wp enterprise workflow run             # Process queued workflows
wp enterprise tax test-connection      # Test Māndian API
wp enterprise sms test 09123456789     # Test SMS provider
wp enterprise email test admin@x.com   # Test SMTP
wp enterprise audit prune --days=365   # Prune old audit logs
wp enterprise user create-admin ...    # Bootstrap admin
wp enterprise config export > cfg.json # Export configuration
wp enterprise config import cfg.json   # Import configuration
wp enterprise migrate run              # Run migrations
```

---

## Theme and SPA dashboard

The **enterprise-theme** is a hybrid:

- **Classic WordPress templates** for the public-facing site (`front-page.php`, `page.php`, `single.php`, `archive.php`, `search.php`, `404.php`, `comments.php`, `woocommerce.php`).
- **A React 18 + Tailwind 3 + Vite SPA** for the admin dashboard (mounted under `/app/*` or `/enterprise/*`).

The design system is **ultra-minimal, pure black & white, glassmorphism touches, neo-brutalist accents, motion-first**:

- Design tokens: 5 surfaces × 5 ink shades + status colors, in `assets/css/tokens.css`.
- 7 CSS layers: tokens → base (reset, typography, container, stack, grid) → layout (app shell, topbar, sidebar, right rail) → components (buttons, inputs, cards, badges, avatars, tabs, tables, modals, toasts, alerts) → pages → RTL → print.
- 19-pillar sidebar grouped into **main / business / ops / system**.
- Command palette (`Cmd/Ctrl+K`) with fuzzy multilingual search.
- Toasts bottom-right (LTR) / bottom-left (RTL), 4s auto-dismiss, stackable.
- `prefers-reduced-motion` respected.

Dashboard template-parts (already built): `kpi-card`, `kpi-grid`, `pipeline-mini`, `activity-feed`, `todo-list`, `revenue-chart`, `birthdays-widget`.

Record views (already built): `list-view`, `kanban-view`, `calendar-view`, `gallery-view`, `detail-view`.

---

## Installation

```bash
# 1. Clone
git clone https://github.com/QalamHipHop/ParsYar.git

# 2. Copy into a WordPress 6.5+ install
cp -r ParsYar/enterprise-core-plugin/*  /path/to/wordpress/wp-content/plugins/enterprise-core-plugin/
cp -r ParsYar/enterprise-theme/*        /path/to/wordpress/wp-content/themes/enterprise-theme/

# 3. Build the theme (Node 20 LTS)
cd /path/to/wordpress/wp-content/themes/enterprise-theme
npm install
npm run build

# 4. Activate
wp plugin activate enterprise-core-plugin
wp theme activate enterprise
```

On first activation, the Setup Wizard opens automatically. To load demo data:

```bash
wp enterprise demo load
```

---

## System requirements

- **PHP 8.1+** (8.2 or 8.3 recommended) with `declare(strict_types=1)`
- **WordPress 6.5+**
- **MySQL 8.0+** or **MariaDB 10.6+**
- **Required PHP extensions**: `mbstring`, `intl`, `mysqli`, `json`, `openssl`, `gd`, `zip`, `curl`, `fileinfo`, `ctype`, `iconv`, `bcmath`
- **Recommended PHP settings**: `memory_limit >= 256M`, `max_execution_time >= 60`, `upload_max_filesize >= 32M`
- **HTTPS** required for payment gateway APIs and the Māndian client
- **Object cache** (Redis or Memcached) for high-traffic deployments — auto-falls back to transients
- **Node 20 LTS** (only for building the theme SPA)

---

## Security

- All REST endpoints guarded by `permission_callback` and nonce.
- All admin forms use `wp_nonce_field()` + `current_user_can()`.
- All queries go through `Enterprise\Support\Db` (which wraps `wpdb::prepare()`).
- All output escaped with `esc_html`, `esc_attr`, `esc_url`, `wp_kses`.
- CSP headers on REST responses.
- 2FA (TOTP), IP allowlist, device management, password policy, audit retention — all configurable in the wizard (Step 21).
- Rate limit: 60 requests/min per user/IP, configurable.
- Append-only audit log (`wp_parsyar_audit_log`) protected at the schema level (no DELETE grant).
- Ledger entries are immutable; reversal is implemented as a counter-entry, never a delete.

---

## Roadmap

- [x] **1.0.0** — Object Engine + Ledger + REST + Demo (Genesis Build)
- [x] **1.0.1** — Jalali (33-year + 2820-year), Iranian validators
- [x] **1.1.0** — Full CRM (Leads, Contacts, Deals, Activities, scoring, dedup, pipeline)
- [x] **1.2.0** — Full ERP (Products, Inventory, Invoices, Payments, Refunds)
- [x] **1.3.0** — 23-step Setup Wizard + Māndian e-Invoice (full spec, JWS-signed)
- [ ] **1.4.0** — Full HRM (Employees, Attendance, Leave, Payroll, Performance) + CSAT
- [ ] **1.5.0** — Visual Workflow Editor (drag-drop node graph)
- [ ] **1.6.0** — Custom Report Builder
- [ ] **1.7.0** — Customer Portal (PWA)
- [ ] **1.8.0** — Mobile app (React Native)
- [ ] **2.0.0** — Multi-tenant SaaS mode (Holding: per-tenant isolation)

---

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md) for the full contract: branch naming, commit message format (Conventional Commits), PR rules, testing requirements, code-style enforcement (PSR-12 + WordPress-Extra), and code of conduct.

**Commit convention**: `feat(module): subject`, `fix(module): subject`, `refactor(module): subject`, `perf`, `docs`, `test`, `chore`.

**Branch naming**: `feat/`, `fix/`, `refactor/`, `perf/`, `docs/`, `test/`, `chore/`.

**Author**: Qalam `<qalam@parsyar.dev>` — GitHub: [QalamHipHop](https://github.com/QalamHipHop)

---

## License

GPL-2.0-or-later. Free for commercial use, modification, and distribution. Modifications must remain GPL.

See [`LICENSE`](LICENSE) for the full text.

---

<div align="center">

**ساخته‌شده با دقت در تهران · Built with care in Tehran**

</div>
