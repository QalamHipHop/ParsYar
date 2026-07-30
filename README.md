# ParsYar — Enterprise Platform

یک اکوسیستم CRM/ERP بر بستر وردپرس — شامل تم و پلاگین بومی، بدون وابستگی به افزونه‌های ثالث.

## ساختار

```
parsYar/
├── enterprise-theme/        # React + Tailwind SPA (Headless Dashboard)
├── enterprise-core-plugin/  # PHP Plugin: Object Engine + Accounting + CRM/ERP/HRM
└── install/                 # One-Click Installer & Demo Seeder
```

## نصب

1. فایل ZIP را در `wp-content/plugins/` و `wp-content/themes/` اکسترکت کن.
2. افزونه `enterprise-core-plugin` را فعال کن.
3. تم `enterprise-theme` را فعال کن.
4. به `Enterprise → Setup Wizard` برو و یک‌کلیک نصب کن.

## استانداردها

- PHP 8.0+ — PSR-12
- React 18 + Tailwind 3
- امنیت: Prepared Statements, Nonce, Capability Checks
- Zero Dependency: بدون WooCommerce, بدون Elementor
