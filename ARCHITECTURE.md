# ParsYar — معماری سیستم

> سند مرجع معماری — تک‌مبدأ حقیقت برای تصمیم‌های فنی.

## ۱. چشم‌انداز

ParsYar یک پلتفرم **CRM/ERP/HCM** بر بستر وردپرس است که با تمرکز بر **بازار ایران** ساخته شده. هدف اصلی: ارائهٔ یک جایگزین بومی، قابل سفارشی‌سازی، و سازگار با الزامات قانونی ایران (سامانهٔ مؤدیان، شاپرک، پست، بانک‌های داخلی).

## ۲. اصول بنیادین

1. **بدون وابستگی خارجی** — WooCommerce، Elementor، ACF، یا هر افزونهٔ ثالث دیگری استفاده نمی‌شود. هستهٔ ما خودش همه‌چیز را می‌سازد.
2. **همه‌چیز شیء سفارشی است** — مدل Salesforce-style: کاربر می‌تواند بدون کد، شیء تعریف کند، فیلد اضافه کند، و گزارش بسازد.
3. **دفترداری دوطرفه** — هر رویداد مالی دو طرف دارد (بدهکار/بستانکار). بدون استثناء.
4. **حسابرسی فراگیر** — هر تغییر، لاگ تغییرناپذیر دارد.
5. **ایران‌محور، جهانی‌پسند** — تقویم شمسی، اعداد فارسی، اعتبارسنج‌های بومی، در عین حال چندزبانه و چندارزی.
6. **رابط کاربری واکنشی و مینیمال** — تم با Tailwind 3، رنگ سفیدمشکی، سایه‌های خنثی.

## ۳. لایه‌ها

```
┌──────────────────────────────────────────────────────────────────┐
│                         Presentation                             │
│  ┌───────────────────────┐    ┌─────────────────────────────┐    │
│  │  React 18 + Tailwind  │    │  Classic WP (PHP templates)  │    │
│  │  (admin dashboard)    │    │  (front-end site, portal)    │    │
│  └──────────┬────────────┘    └──────────────┬──────────────┘    │
└─────────────┼─────────────────────────────────┼───────────────────┘
              │                                 │
              ▼                                 ▼
┌──────────────────────────────────────────────────────────────────┐
│                       REST API (/wp-json/enterprise/v1)          │
│  - Authentication (JWT / Cookie + Nonce)                          │
│  - Rate Limiting (token bucket per user/IP)                        │
│  - Capability-based Authorization                                  │
│  - OpenAPI 3.1 spec auto-generated                                 │
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
│  │            Domain Modules (CRM, ERP, HRM, Tax)            │  │
│  └────────────────────────────────────────────────────────────┘  │
└────────────────────────────┬─────────────────────────────────────┘
                             │
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│                       Persistence Layer                          │
│  ┌─────────────────────────┐    ┌──────────────────────────┐    │
│  │  WordPress core tables  │    │  Custom tables (8 prefix)│    │
│  │  (users, options, posts)│    │  wp_parsyar_*            │    │
│  └─────────────────────────┘    └──────────────────────────┘    │
│  Object Cache (Redis/Memcached) — auto-fallback to transients.   │
└──────────────────────────────────────────────────────────────────┘
```

## ۴. ماژول‌های دامنه

| ماژول | مسئولیت | جداول اختصاصی |
|--------|---------|---------------|
| **Objects** | موتور شیء سفارشی (Salesforce-style) | `wp_parsyar_object_schema`, `wp_parsyar_object_record`, `wp_parsyar_object_field` |
| **Accounting** | دفترداری دوطرفه + گزارش‌های مالیاتی | `wp_parsyar_chart_of_accounts`, `wp_parsyar_journal_entry`, `wp_parsyar_journal_line`, `wp_parsyar_fiscal_period` |
| **CRM** | سرنخ، مخاطب، معامله، فعالیت | `wp_parsyar_lead`, `wp_parsyar_contact`, `wp_parsyar_deal`, `wp_parsyar_activity` |
| **ERP** | محصول، انبار، فاکتور | `wp_parsyar_product`, `wp_parsyar_warehouse`, `wp_parsyar_stock_movement`, `wp_parsyar_invoice` |
| **HRM** | کارمند، حقوق، حضور و غیاب | `wp_parsyar_employee`, `wp_parsyar_payroll_run`, `wp_parsyar_attendance` |
| **Tax** | سامانهٔ مؤدیان + e-Archive | `wp_parsyar_tax_invoice`, `wp_parsyar_tax_invoice_item` |
| **Workflow** | اتوماسیون بصری | `wp_parsyar_workflow`, `wp_parsyar_workflow_run`, `wp_parsyar_workflow_log` |
| **Audit** | لاگ تغییرناپذیر | `wp_parsyar_audit_log` |
| **Wizards** | نصب و راه‌اندازی | — (options table) |

## ۵. کنوانسیون‌ها

### ۵.۱ نام‌گذاری کلاس‌ها

```
Enterprise\Modules\<Domain>\<Entity>
Enterprise\Modules\<Domain>\<Entity>Service
Enterprise\Modules\<Domain>\<Entity>Repository
Enterprise\Modules\<Domain>\<Entity>Controller
Enterprise\Api\<Domain>Controller
```

### ۵.۲ نام‌گذاری جداول

همهٔ جداول سفارشی با پیش‌وند `wp_parsyar_` (پیش‌وند قابل تنظیم از طریق فیلتر `parsyar_db_prefix`).

### ۵.۳ کلیدها

- کلید اصلی: `id BIGINT UNSIGNED AUTO_INCREMENT`
- کلید یکتا برای API: `uuid CHAR(36)` (همیشه در هدر API برگردانده می‌شود، نه `id` داخلی)
- تمامی timestamp ها: `DATETIME` (نه `TIMESTAMP`) در UTC.

### ۵.۴ خطاها

استثناها از نوع `Enterprise\Core\Exception` ارث می‌برند و دارای کد خطای قابل خواندن برای ماشین هستند (مثلاً `parsyar.ledger.unbalanced`).

## ۶. امنیت

- تمامی endpoint های REST: `permission_callback` + nonce.
- تمامی فرم‌های admin: `wp_nonce_field()` + `current_user_can()`.
- تمامی کوئری‌ها: `$wpdb->prepare()` یا `QueryBuilder` داخلی.
- تمامی خروجی‌ها: توابع escape مناسب (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`).
- CSP header در پاسخ REST تنظیم می‌شود.
- Rate limit: ۶۰ درخواست در دقیقه برای هر کاربر (قابل تنظیم).

## ۷. مقیاس‌پذیری

- Object cache (Redis پیش‌فرض، Memcached پشتیبان).
- جداول جداگانه برای داده‌های پرحجم.
- Index ترکیبی روی ستون‌های پرکوئری.
- Action Scheduler برای کارهای سنگین پس‌زمینه.
- BigInt ID ها → پشتیبانی تا ۹.۲ کوئینتیلیون رکورد.

## ۸. استانداردهای کد

- PHP 8.1+ با `declare(strict_types=1)`.
- PSR-12 + WordPress-Extra (PHPCS).
- PHPStan level 8 در CI.
- JS/TS: ESLint + Prettier.
- Conventional Commits + SemVer.

## ۹. چرخهٔ انتشار

```
main (پایدار) ← release/*
  │
  ├── develop ← feature/*
  │
  └── hotfix ← fix/critical-*
```

نسخه‌ها: `MAJOR.MINOR.PATCH` (SemVer).
برچسب‌های انتشار در GitHub با changelog خودکار از طریق release-please.

## ۱۰. نقشهٔ راه

- [x] v1.0.0 — هستهٔ شیء سفارشی + دفترداری + REST + دمو (فاز ۱)
- [ ] v1.1.0 — ماژول CRM کامل (Leads/Contacts/Deals)
- [ ] v1.2.0 — ماژول ERP (Products/Inventory/Invoices)
- [ ] v1.3.0 — ماژول HRM (Employees/Payroll)
- [ ] v1.4.0 — ادغام سامانهٔ مؤدیان (e-Invoice واقعی)
- [ ] v1.5.0 — Workflow Engine بصری
- [ ] v2.0.0 — Portal مشتری + PWA

---

*هر تغییر معماری باید از طریق ADR (Architecture Decision Record) ثبت شود. فولدر `docs/adr/`*
