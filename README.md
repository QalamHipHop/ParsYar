# ParsYar — Enterprise Platform

اکوسیستم CRM/ERP بر بستر وردپرس — **بدون وابستگی به افزونه ثالث**.

## معماری

```
ParsYar/
├── enterprise-core-plugin/   # PHP Plugin (PHP 8.0+)
│   ├── enterprise-core.php   # Bootstrap
│   ├── includes/             # Db, Installer, Router
│   ├── api/                  # REST v1 Controllers
│   ├── modules/
│   │   ├── objects/          # Custom Object Engine (Salesforce-style)
│   │   ├── accounting/       # Two-sided Ledger (SAP-style)
│   │   ├── audit/            # Immutable Audit Trail
│   │   ├── crm/              # Lead Service + Scoring
│   │   ├── erp/              # Inventory + Invoices
│   │   ├── hrm/              # Employees + Payroll
│   │   ├── tax/              # سامانه مؤدیان (TAX.IR)
│   │   └── workflow/         # Visual Workflow Engine
│   ├── admin/                # WP Admin pages
│   └── db/                   # Demo Seeder
└── enterprise-theme/         # React 18 + Tailwind 3 (Vite)
    ├── src/
    │   ├── App.jsx
    │   ├── api/client.js     # REST client
    │   └── pages/            # Dashboard, CRM, ERP, HRM, Accounting, ...
    └── build/                # خروجی Vite (پس از npm run build)
```

## نصب سریع

1. پوشه‌ها را در ریشهٔ وردپرس کپی کن:
   ```
   wp-content/plugins/enterprise-core-plugin/
   wp-content/themes/enterprise-theme/
   ```
2. افزونه را فعال کن (جداول به‌طور خودکار ساخته می‌شوند).
3. تم را فعال کن.
4. به `Enterprise → Setup Wizard` برو و **یک‌کلیک** نصب کن (داده‌های دمو + حساب‌های پیش‌فرض).
5. داشبورد: `/enterprise`

## استانداردها

- PHP 8.0+ — PSR-12 style، Strict Types
- React 18 + Tailwind 3 + Vite
- امنیت: `prepare()`, Nonce, Capability Checks, XSS escaping
- Zero Dependency: بدون WooCommerce / Elementor

## ماژول‌ها

| ماژول | توضیح | مسیر |
|------|------|------|
| Object Engine | ساخت شیء + فیلد + رابطه بدون کد | `modules/objects/` |
| Accounting | دفتر کل دوطرفه + تراز آزمایشی | `modules/accounting/` |
| Audit | لاگ تغییرناپذیر | `modules/audit/` |
| CRM | Lead + Lead Scoring | `modules/crm/` |
| ERP | محصول + فاکتور + انبار | `modules/erp/` |
| HRM | پرسنل + Payroll (مالیات/بیمه) | `modules/hrm/` |
| Tax | اتصال به سامانه مؤدیان | `modules/tax/` |
| Workflow | گراف شرطی + Action | `modules/workflow/` |

## API

Base: `/wp-json/enterprise/v1/`

| متد | مسیر | توضیح |
|------|------|------|
| POST | `/auth/login` | ورود |
| GET  | `/objects` | لیست اشیاء |
| GET  | `/objects/{api}/records` | لیست رکوردها |
| POST | `/objects/{api}/records` | ایجاد رکورد |
| GET  | `/accounting/trial-balance` | تراز آزمایشی |
| POST | `/accounting/journal` | ثبت سند |
| POST | `/erp/invoices` | صدور فاکتور (سند خودکار) |
| POST | `/tax/invoices/{id}/submit` | ارسال به سامانه مؤدیان |
| GET  | `/audit` | لاگ حسابرسی |

## Build فرانت‌اند

```bash
cd enterprise-theme
npm install
npm run build
```

## مجوز

GPL-2.0-or-later
