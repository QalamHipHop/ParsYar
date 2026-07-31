# Changelog

تمام تغییرات مهم این پروژه در این فایل ثبت می‌شود.
قالب بر اساس [Keep a Changelog](https://keepachangelog.com/fa/1.1.0/) و
این پروژه از [Semantic Versioning](https://semver.org/lang/fa/) پیروی می‌کند.

## [1.2.1] - 2026-07-31

### اصلاح (Fix)

- **Installer / migration**: اکنون جدول‌های `wp_parsyar_workflows`,
  `wp_parsyar_workflow_runs`, `wp_parsyar_workflow_logs`,
  `wp_parsyar_webpush_subscriptions` در فعال‌سازی ساخته می‌شوند
  (قبلاً Repository به آن‌ها ارجاع می‌داد ولی جدولی وجود نداشت
  → fatal error در `Repository::startRun/log`).
- **Installer / migration**: ستون `tax_invoice_uid` به `wp_ent_invoices`
  اضافه شد (مورد نیاز Moodian client).
- **Installer / seed**: در سایت‌هایی که قبلاً `enterprise_seeded=yes` گذاشته
  شده ولی `wp_ent_accounts` خالی مانده، اکنون `ChartOfAccounts::installDefaults()`
  فراخوانی می‌شود.
- **Moodian Client**: ارجاع‌های `wp_parsyar_invoices` به `wp_ent_invoices`
  اصلاح شد (در loadInvoice / persistSuccess / persistError).

### افزوده (Added)

- **Rate limiting**: middleware پایه روی routeهای REST اینترپرایس
  (سقف ۶۰ درخواست/دقیقه به ازای IP+endpoint، قابل تنظیم با فیلتر
  `enterprise_rate_limit_per_minute`).
- **Security headers** در پاسخ‌های REST:
  - `Content-Security-Policy: default-src 'self'`
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: SAMEORIGIN`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `Permissions-Policy: geolocation=(), microphone=(), camera=()`

## [1.2.0] - نسخه‌های قبلی

تغییرات قبلی به‌طور خلاصه در README و ARCHITECTURE ثبت شده است.
