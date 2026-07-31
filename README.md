# ParsYar

Enterprise CRM/ERP/HCM platform on WordPress. Persian-first, RTL-native, fully self-contained. No third-party plugin dependencies.

## What it is

ParsYar is a unified business platform that combines:

- **Custom Object Engine** — define entities, fields, relations, layouts without code (Salesforce-style metadata layer)
- **Double-entry Accounting** — mathematical balance guarantee on every journal entry, full Chart of Accounts, fiscal periods, financial statements
- **CRM** — leads, contacts, deals, activities, scoring, deduplication, segmentation, pipeline forecasting
- **ERP** — products, multi-warehouse inventory, lots/serials, FIFO/LIFO/WAC costing, purchase orders, sales invoices, payments
- **HRM** — employees, GPS attendance, leave management, payroll with Iranian tax brackets, performance reviews
- **Tax (Māndian)** — electronic invoice integration for `tax.gov.ir` (sale, purchase, return, correction) with digital signature
- **Workflow Engine** — visual drag-drop automation with triggers, conditions, actions, version history
- **Immutable Audit Log** — every write is recorded, append-only
- **Omnichannel Inbox** — email, SMS (Kavenegar/Melipayamak/Ghasedak), WhatsApp, Telegram, Bale, Instagram, webchat
- **HR & Payroll** — attendance with geofence, leave balance, payslip PDF
- **Documents/DMS** — upload, version, e-sign (ParsSign/Docusign/ClickSign), retention policy
- **Setup Wizard** — 23-step guided installer with import/export of configuration, demo seed

All written in PHP 8.1+ with strict types, React 18 + Tailwind 3 + Vite for the admin SPA, and a custom REST API at `/wp-json/enterprise/v1`.

## Why

Most WordPress-based CRMs in the Persian market depend on commercial third-party plugins: ACF for custom fields, WooCommerce for sales, a paid form plugin for lead capture, and a separate accounting tool that does not speak Persian or understand Iranian tax law. ParsYar replaces all of these with a single self-hosted stack. The result is a system that can be installed, white-labeled, audited, and extended without licensing a single external product.

The platform is engineered for the regulatory requirements of the Iranian market: Jalali calendar, Persian numerals, national ID, Sheba/IBAN, mobile-operator detection, and direct integration with the Māndian electronic-invoicing API.

## Architecture

```
ParsYar/
├── enterprise-core-plugin/         # PHP 8.1+ plugin
│   ├── enterprise-core.php         # Bootstrap (singleton, autoload, hooks)
│   ├── includes/                   # Core: Jalali, Validator, Db, Installer, Router
│   ├── api/                        # REST controllers (auth, object, record, crm, erp, hrm, tax, accounting, workflow, audit)
│   ├── modules/
│   │   ├── objects/                # Custom Object Engine + 22 field types + flat-table DDL
│   │   ├── accounting/             # Chart of Accounts + Ledger + double-entry guarantee
│   │   ├── crm/                    # LeadService (scoring, dedup, capture, routing)
│   │   ├── erp/                    # InventoryService, InvoiceService
│   │   ├── hrm/                    # EmployeeService, PayrollService
│   │   ├── tax/                    # MoodianClient (digital signature, e-Invoice submission)
│   │   ├── workflow/               # Dispatcher, Repository
│   │   └── audit/                  # Logger (append-only)
│   ├── admin/                      # WP admin pages, menu, Setup wizard
│   └── db/                         # Demo seeder
└── enterprise-theme/               # React 18 + Tailwind 3 SPA dashboard
    ├── src/                        # App, pages, REST client
    └── build/                      # Vite output
```

Layered design: Presentation (React + PHP templates) → REST API (auth, rate limit, capabilities) → Application Core (Object Engine, Ledger, Workflow, Audit) → Domain Modules (CRM/ERP/HRM/Tax) → Persistence (WP core tables + `wp_parsyar_*` + `wp_ent_*` + Object Cache).

The Object Engine is the centerpiece. Every user-defined entity gets its own flat MySQL table with column types generated from the field schema, automatic indexing on searchable fields, and Iranian validators (national ID, Sheba, mobile, card) wired into the write path. Records flow through a `RecordStore` that normalizes Persian digits, validates Iranian values, emits audit entries, and fires events into the Workflow Engine.

The Ledger is a hardened double-entry engine. Every journal entry is validated for balance (debit == credit within 0.005 tolerance), atomic (`START TRANSACTION` / `COMMIT` / `ROLLBACK`), tagged with a fiscal period, and append-only. Reversal is implemented as a counter-entry, never as a delete, so the audit trail stays intact. Trial balance, journal, income statement, and balance sheet are built directly from the same immutable data.

## Requirements

- PHP 8.1+ (8.2 or 8.3 recommended)
- MySQL 8.0+ or MariaDB 10.6+
- WordPress 6.5+
- Node 20 LTS (for theme build)
- Required PHP extensions: `mbstring, intl, mysqli, zip, gd, bcmath, opcache, json, openssl, fileinfo, ctype, iconv`

## Installation

```bash
# 1. Clone
git clone https://github.com/QalamHipHop/ParsYar.git

# 2. Copy into WordPress
cp -r ParsYar/enterprise-core-plugin/* /path/to/wordpress/wp-content/plugins/enterprise-core-plugin/
cp -r ParsYar/enterprise-theme/* /path/to/wordpress/wp-content/themes/enterprise-theme/

# 3. Build the theme
cd /path/to/wordpress/wp-content/themes/enterprise-theme/
npm install
npm run build

# 4. Activate
wp plugin activate enterprise-core-plugin
wp theme activate enterprise
```

On first activation the Setup Wizard opens automatically. The wizard runs 23 steps, validates system requirements, lets the operator choose a deployment mode (Solo / Micro / SMB / Enterprise / Holding), configures currencies, fiscal year, Jalali settings, pipelines, taxes, payment gateways, Iranian integrations (Māndian, Shaparak, Jibit, Neshan/Map.ir), notification channels, branding, and an AI assistant. State is persisted in `wp_options`, the wizard is resumable, and the full configuration can be exported or imported as a JSON document.

To load demo data for evaluation:

```bash
wp enterprise demo load
```

## REST API

The API lives at `/wp-json/enterprise/v1`. Three authentication methods are supported: WordPress cookie + nonce for in-app use, JWT for the React SPA and mobile, and Application Passwords for any external client.

```bash
# Login (JWT)
curl -X POST https://example.com/wp-json/enterprise/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"xxx"}'

# Define a custom object
curl -X POST https://example.com/wp-json/enterprise/v1/objects \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "key": "customer",
    "label": "مشتری",
    "fields": [
      {"api_name":"first_name","label":"نام","type":"text","is_required":true},
      {"api_name":"national_id","label":"کد ملی","type":"national_id","is_unique":true},
      {"api_name":"mobile","label":"موبایل","type":"mobile"},
      {"api_name":"credit_limit","label":"سقف اعتبار","type":"decimal"}
    ]
  }'

# Create a record (Persian digits accepted, validated automatically)
curl -X POST https://example.com/wp-json/enterprise/v1/objects/customer/records \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "علی",
    "national_id": "۱۲۳۴۵۶۷۸۹۰",
    "mobile": "۰۹۱۲۳۴۵۶۷۸۹",
    "credit_limit": "50000000"
  }'
```

All API responses follow a uniform envelope:

```json
{ "success": true, "data": {...}, "meta": {"request_id":"req_..."} }
```

Errors carry a machine-readable code (`parsyar.ledger.unbalanced`, `parsyar.objects.not_found`, ...) and structured details.

## Iranian localization

Persian-first is not a skin. It is built into every layer.

- **Jalali calendar** — dual algorithm (33-year cycle for the common range 1244–1473, 2820-year cycle for the wider range). Conversion, formatting, weekday calculation, leap-year detection.
- **Persian numerals** — automatic conversion at the validator layer. `۰۹۱۲...` and `0912...` are treated identically.
- **National ID** — 10-digit checksum (mod 11) for natural persons, 11-digit for legal entities.
- **Sheba/IBAN** — 26-character Iranian IBAN with mod-97 validation.
- **Mobile** — regex validation plus operator detection (MCI / MTN / Rightel / Mokhaberat) by prefix.
- **Card number** — Luhn check plus BIN lookup against 23 Iranian banks.
- **Postal code** — 10-digit format validation.
- **Phone** — city-code aware.
- **Māndian** — electronic-invoice API client with digital signature, four invoice types (sale / purchase / return / correction), two patterns (B2B/B2G and B2C e-Archive), real-time submission, error decoding, and signed-JSON storage for audit.
- **Fiscal year** — defaults to Iranian year starting around March 21.
- **Cities and provinces** — full 31-province list with over 1200 cities, with lat/lng.

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

## Standards

- PHP 8.1+ with `declare(strict_types=1)`, PSR-12 + WordPress-Extra coding standard
- JavaScript ES2022, React 18, Tailwind 3, Vite 5, ESLint + Prettier
- MySQL `utf8mb4_persian_ci` collation on Persian text columns
- RESTful API, OpenAPI 3.1 spec, semantic versioning
- Conventional Commits, branch naming `feat/`, `fix/`, `refactor/`, `perf/`, `docs/`, `test/`, `chore/`
- PHPStan level 8, PHPCS, PHPUnit, Playwright E2E
- GPL-2.0-or-later (same license as WordPress)

## Modules at a glance

| Module | Responsibility | Key tables |
|--------|---------------|-----------|
| Objects | Custom entity engine with flat-table DDL | `wp_ent_data_<key>`, `wp_ent_objects`, `wp_ent_object_fields` |
| Accounting | Double-entry ledger, CoA, financial statements | `wp_ent_accounts`, `wp_ent_journal_entries`, `wp_ent_journal_lines`, `wp_ent_fiscal_periods` |
| CRM | Lead capture, scoring, dedup, pipeline | `wp_parsyar_lead`, `wp_parsyar_contact`, `wp_parsyar_deal`, `wp_parsyar_activity` |
| ERP | Products, inventory, invoicing | `wp_parsyar_product`, `wp_parsyar_warehouse`, `wp_parsyar_stock_movement`, `wp_parsyar_invoice` |
| HRM | Employees, attendance, payroll | `wp_parsyar_employee`, `wp_parsyar_payroll_run`, `wp_parsyar_attendance` |
| Tax | Māndian e-Invoice | `wp_parsyar_tax_invoice`, `wp_parsyar_tax_invoice_item` |
| Workflow | Visual automation | `wp_parsyar_workflow`, `wp_parsyar_workflow_run`, `wp_parsyar_workflow_log` |
| Audit | Immutable change log | `wp_parsyar_audit_log` |

## Roadmap

- 1.0.0 — Object Engine, Ledger, REST, demo (shipped)
- 1.0.1 — Jalali, Iranian validators (shipped)
- 1.1.0 — Full CRM (Leads, Contacts, Deals, Activities, scoring, dedup, pipeline)
- 1.2.0 — Full ERP (Products, Inventory, Invoices, Payments, Returns)
- 1.3.0 — Full HRM (Employees, Attendance, Leave, Payroll, Performance)
- 1.4.0 — Live Māndian integration (sandbox + production)
- 1.5.0 — Visual Workflow editor
- 1.6.0 — Custom report builder
- 1.7.0 — Customer Portal (PWA)
- 1.8.0 — Mobile app (React Native)
- 2.0.0 — Multi-tenant SaaS mode

## Security

- All REST endpoints guarded by `permission_callback` and nonce.
- All queries go through `wpdb::prepare()` or the in-house `Db` helper.
- All output escaped with `esc_html`, `esc_attr`, `esc_url`, `wp_kses`.
- CSP headers on REST responses.
- 2FA (TOTP), IP allowlist, device management, password policy, audit retention.
- Rate limit (60 req/min default, configurable) on REST.
- Append-only audit log protected at the schema level (no DELETE grant on `wp_parsyar_audit_log`).

## Contributing

See `CONTRIBUTING.md` for the full contract: branch naming, commit message format, PR rules, testing requirements, code-style enforcement, and code of conduct.

## License

GPL-2.0-or-later. Free for commercial use, modification, and distribution. Modifications must remain GPL.

## Contact

Maintainer: Qalam. Email: qalam@parsyar.dev. GitHub: https://github.com/QalamHipHop/ParsYar.
