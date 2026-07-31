# ParsYar — Enterprise Platform

<div align="center">

**اکوسیستم CRM/ERP/HCM بر بستر وردپرس — بدون وابستگی به افزونهٔ ثالث**

[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg)](https://www.php.net)
[![WordPress 6.5+](https://img.shields.io/badge/WordPress-6.5%2B-21759B.svg)](https://wordpress.org)
[![React 18](https://img.shields.io/badge/React-18-61DAFB.svg)](https://react.dev)
[![Tailwind 3](https://img.shields.io/badge/Tailwind-3-38B2AC.svg)](https://tailwindcss.com)
[![MySQL 8+](https://img.shields.io/badge/MySQL-8%2B-4479A1.svg)](https://www.mysql.com)
[![Code style: PSR-12 + WP](https://img.shields.io/badge/code%20style-PSR--12%20%2B%20WP-orange.svg)](CONTRIBUTING.md)

[امکانات](#-امکانات) · [معماری](#-معماری) · [نصب](#-نصب-سریع) · [مستندات](#-مستندات) · [مشارکت](#-مشارکت)

</div>

---

## فهرست مطالب

- [دربارهٔ پروژه](#-دربارهٔ-پروژه)
- [چرا ParsYar؟](#-چرا-parsyar)
- [امکانات](#-امکانات)
  - [هستهٔ Object Engine](#-هستهٔ-object-engine)
  - [دفترداری دوطرفه](#-دفترداری-دوطرفه)
  - [ماژول CRM](#-ماژول-crm)
  - [ماژول ERP](#-ماژول-erp)
  - [ماژول HRM](#-ماژول-hrm)
  - [ماژول Tax (سامانهٔ مؤدیان)](#-ماژول-tax-سامانهٔ-مؤدیان)
  - [Workflow Engine](#-workflow-engine)
  - [Audit Log تغییرناپذیر](#-audit-log-تغییرناپذیر)
  - [Wizard نصب](#-wizard-نصب)
  - [داشبورد React](#-داشبورد-react)
  - [اعتبارسنج‌های ایرانی](#-اعتبارسنج‌های-ایرانی)
  - [تقویم شمسی](#-تقویم-شمسی)
- [معماری](#-معماری)
  - [نمای شاختاری](#-نمای-شاختاری)
  - [نمای فیزیکی](#-نمای-فیزیکی)
  - [لایه‌ها](#-لایه‌ها)
  - [چرخهٔ درخواست](#-چرخهٔ-درخواست)
- [نصب سریع](#-نصب-سریع)
  - [پیش‌نیازها](#-پیش‌نیازها)
  - [نصب دستی](#-نصب-دستی)
  - [نصب با Docker](#-نصب-با-docker)
  - [نصب با WP-CLI](#-نصب-با-wp-cli)
  - [فعال‌سازی Wizard](#-فعال‌سازی-wizard)
- [پیکربندی](#-پیکربندی)
  - [تنظیمات سازمانی](#-تنظیمات-سازمانی)
  - [تنظیمات مالی](#-تنظیمات-مالی)
  - [تنظیمات اعلان](#-تنظیمات-اعلان)
  - [تنظیمات امنیتی](#-تنظیمات-امنیتی)
- [REST API](#-rest-api)
  - [احراز هویت](#-احراز-هویت)
  - [ساختار پاسخ](#-ساختار-پاسخ)
  - [Endpoint های اصلی](#-endpoint-های-اصلی)
  - [مثال کامل](#-مثال-کامل)
- [خط فرمان (WP-CLI)](#-خط-فرمان-wp-cli)
- [توسعه و مشارکت](#-توسعه-و-مشارکت)
- [استانداردها](#-استانداردها)
- [تست](#-تست)
- [نقشهٔ راه](#-نقشهٔ-راه)
- [مجوز](#-مجوز)
- [تماس](#-تماس)

---

## 🇮🇷 دربارهٔ پروژه

**ParsYar** (پارس‌یار) یک پلتفرم سازمانی متن‌باز بر بستر **WordPress** است که سه ماژول اصلی **CRM** (مدیریت ارتباط با مشتری)، **ERP** (برنامه‌ریزی منابع سازمان) و **HRM** (مدیریت منابع انسانی) را در یک هستهٔ واحد ادغام می‌کند.

تمرکز اصلی پروژه بر **بازار ایران** و **الزامات قانونی ایران** است: تقویم شمسی، اعداد فارسی، کد ملی، شبا، شماره کارت، اپراتور موبایل، **سامانهٔ مؤدیان** (e-Invoice)، شاپرک، و سازگاری کامل با داده‌های راست‌چین.

پروژه **بدون وابستگی به هیچ افزونهٔ ثالثی** ساخته شده است. WooCommerce، Elementor، ACF، Gravity Forms، یا هر افزونهٔ تجاری دیگری استفاده نمی‌شود. تمام قابلیت‌ها در هستهٔ داخلی پیاده‌سازی شده‌اند.

---

## ❓ چرا ParsYar؟

| مسئله | راه‌حل رایج (افزونهٔ خارجی) | راه‌حل ParsYar |
|--------|----------------------------|----------------|
| ساخت شیء سفارشی | ACF Pro / Toolset (هزینه + محدودیت) | **موتور شیء داخلی** با DDL خودکار و اعتبارسنجی |
| حسابداری دوطرفه | نیاز به افزونهٔ جداگانه | **دفترداری داخلی** با تضمین تراز |
| e-Invoice مؤدیان | افزونه‌های ناقص و قدیمی | **کلاینت بومی** با امضای دیجیتال و ارسال real-time |
| تقویم شمسی | شمسی‌سازی دستی قالب | **موتور Jalali داخلی** (۲ الگوریتم ۳۳ و ۲۸۲۰ ساله) |
| اعتبارسنج کد ملی | کدنویسی هر بار | **Validator مرکزی** با ۲۲ نوع اعتبارسنجی |
| داشبورد | نیاز به افزونهٔ گران‌قیمت | **React 18 + Tailwind 3** SPA داخلی |
| سفارشی‌سازی فیلد | بسته به افزونهٔ تجاری | **سفارشی‌سازی ۱۰۰٪ بدون کد** (Field Builder) |
| حسابرسی | افزونهٔ جداگانه | **Audit Log تغییرناپذیر** درون هسته |
| چندارزی/چندشرکتی | پیچیده و محدود | **Multi-Company، Multi-Branch، Multi-Currency** بومی |
| امنیت دادهٔ ایرانی | نگرانی از ارسال به سرور خارج | **همه‌چیز روی سرور خودتان** (self-hosted) |

---

## ✨ امکانات

### 🧱 هستهٔ Object Engine

قلب تپندهٔ سیستم. کاربر می‌تواند بدون یک خط کد، شیء تعریف کند (مثلاً «مشتری»، «محصول»، «پروژه»، «دستگاه»، «سفارش»)، فیلد اضافه کند، نوع هر فیلد را مشخص کند، و سیستم به‌طور خودکار:

- **جدول اختصاصی MySQL** با ستون‌های بهینه می‌سازد (Flat Tables)
- **ایندکس‌گذاری خودکار** روی فیلدهای پرکوئری
- **اعتبارسنجی** هنگام INSERT/UPDATE
- **REST endpoint** خودکار اضافه می‌کند
- **Audit log** برای هر تغییر می‌نویسد
- **اعتبارسنج ایرانی** (کد ملی، شبا، موبایل، کارت) خودکار اعمال می‌شود

**۲۲ نوع فیلد پشتیبانی‌شده:**

```
text, textarea, rich
int, decimal, bool
date, datetime, jalali
enum, multi, fk
file, image, json
email, url, phone, mobile
sheba, national_id, card
```

### 💰 دفترداری دوطرفه

سیستم حسابداری داخلی با تضمین ریاضی تراز:

```
∀ JournalEntry:  sum(debit) = sum(credit)
```

- **Chart of Accounts** ۵ رقمی (استاندارد ایران)
- **Journal Entry** با چند خط
- **Double-Entry** تضمینی (هیچ رویداد نیمه‌ثبت نمی‌شود)
- **Fiscal Period** با قفل دوره
- **گزارش‌های مالیاتی**: مادهٔ ۱۶۹، ارزش افزوده، مالیات حقوق
- **صورت‌های مالی**: ترازنامه، سود و زیان، گردش حساب
- **بانک**: مغایرت‌گیری (reconciliation)
- **چندارزی** با نرخ تبدیل روزانه

### 🤝 ماژول CRM

- **Leads** (سرنخ): جذب از فرم، وب، ایمیل، وب‌هوک
- **Contacts** (مخاطب): افراد + سازمان‌ها با رابطهٔ چند-به-چند
- **Deals** (معامله): Pipeline با Drag-Drop، احتمال برد، پیش‌بینی فروش
- **Activities** (فعالیت): تماس، ایمیل، جلسه، یادداشت
- **Scoring** خودکار: الگوریتم امتیازدهی بر اساس تعامل
- **Deduplication**: Levenshtein + Jaro-Winkler روی نام + موبایل + کد ملی
- **Segmentation**: ساخت قانونی با AND/OR/NOT
- **Timeline**: همهٔ تعاملات در یک جدول زمانی واحد

### 📦 ماژول ERP

- **محصولات** با تنوع (variants) و SKU
- **انبار چندگانه** با مکان‌یابی
- **حرکات انبار**: ورود، خروج، انتقال، تعدیل، رزرو
- **ردگیری Lot/Batch/Serial**
- **بهای تمام‌شده**: FIFO، LIFO، میانگین موزون، استاندارد
- **سفارش خرید** + **تأمین‌کننده**
- **فاکتور فروش** + **پیش‌فاکتور** + **برگشت از فروش**
- **پرداخت**: نقد، چک، کارت به کارت، درگاه آنلاین
- **بارکد** (Code128 + QR) + چاپ لیبل

### 👥 ماژول HRM

- **پروندهٔ کارمند** با مدارک
- **حضور و غیاب** با GPS + Geofence + IP + QR + عکس
- **مرخصی**: درخواست، مانده، تأیید چندمرحله‌ای
- **فیش حقوقی** با محاسبهٔ خودکار مالیات (پله‌بندی ایران)
- **بیمه**: سهم کارفرما + سهم کارمند
- **وام و مساعده**
- **ارزیابی عملکرد**: KPI + ۳۶۰ درجه
- **مسیر شغلی** و ارتقاء

### 🏛️ ماژول Tax (سامانهٔ مؤدیان)

- **صدور صورتحساب الکترونیکی** مطابق با آخرین نسخهٔ API سامانهٔ مؤدیان
- **۴ نوع صورتحساب**: فروش، خرید، برگشت از فروش، برگشت از خرید
- **الگوی اول** (فروش B2B و B2G با شناسهٔ ملی)
- **الگوی دوم** (فروش B2C بدون شناسه، e-Archive)
- **امضای دیجیتال** با کلید خصوصی
- **ارسال real-time** + دریافت taxId و referenceNumber
- **ذخیرهٔ JSON امضا‌شده** برای حسابرسی
- **گزارش خطاها** با توضیح فارسی و اقدام پیشنهادی
- **حافظهٔ مالیاتی** (e-Archive)
- **پشتیبانی از چند شرکت** (هر شرکت کلید مجزا)

### ⚙️ Workflow Engine

- **ویرایشگر بصری** (Drag-Drop) برای ساخت فرآیند
- **محرک‌ها** (Triggers): زمان‌بندی، رویداد، ورود به سگمنت، تغییر مرحلهٔ معامله
- **شرایط** (Conditions): فیلتر، انشعاب، A/B، انتظار، پنجرهٔ زمانی شمسی
- **اقدامات** (Actions): ارسال ایمیل، SMS، ایجاد وظیفه، به‌روزرسانی رکورد، وب‌هوک، فراخوانی AI، اجرای PHP
- **نسخه‌بندی** + تاریخچه
- **لاگ اجرا** با قابلیت retry
- **صف پردازش** (Action Scheduler)

### 📜 Audit Log تغییرناپذیر

- هر تغییر (ایجاد، به‌روزرسانی، حذف) **لاگ می‌شود**
- لاگ‌ها **append-only** هستند (هیچ‌کس نمی‌تواند حذف کند)
- شامل: actor، timestamp، مقادیر قبل و بعد، IP، user agent
- قابل جستجو، فیلتر، و خروجی CSV
- **حفظ قانونی** (retention) قابل تنظیم

### 🧙 Wizard نصب

نصب **۲۳ مرحله‌ای** با قابلیت:
- بازیابی خودکار در صورت قطع
- پرش از مراحل اختیاری
- ذخیرهٔ پیش‌نویس
- **صادرات/واردات** پیکربندی به JSON
- **تشخیص خودکار** حالت (Solo / Micro / SMB / Enterprise / Holding)
- **بارگذاری دادهٔ دمو** با یک کلیک
- **اعتبارسنجی پیش‌نیازها** (PHP، MySQL، افزونه‌ها، مجوزها)

### 🎨 داشبورد React

- **React 18** + **Tailwind 3** + **Vite 5**
- **رنگ‌بندی سفید/مشکی** مینیمال
- **RTL/LTR** خودکار
- **کامند پلت** (Cmd+K) برای جستجوی همه‌جا
- **نمودارهای تک‌رنگ** (Chart.js)
- **Drag-Drop** برای Kanban و Pipeline
- **ووکامرس-مانند** اما مستقل
- **PWA-ready**

### 🇮🇷 اعتبارسنج‌های ایرانی

| اعتبارسنج | توضیح | الگوریتم |
|------------|-------|----------|
| `nationalId` | کد ملی ۱۰ رقمی (افراد) | ضرب در موقعیت + mod 11 |
| `legalId` | شناسهٔ ملی ۱۱ رقمی (حقوقی) | mod 11 |
| `sheba` | شبا ۲۶ رقمی (IR...) | mod-97 بین‌المللی |
| `mobile` | موبایل + تشخیص اپراتور | prefix match (MCI/MTN/RTL) |
| `phone` | تلفن ثابت با کد شهر | regex با کد شهر معتبر |
| `postalCode` | کد پستی ۱۰ رقمی | sanity check |
| `cardNumber` | شمارهٔ کارت + BIN lookup | Luhn + جدول ۲۳ بانک |
| `persianToEnglish` | تبدیل اعداد فارسی/عربی | str_replace |
| `englishToPersian` | تبدیل اعداد انگلیسی به فارسی | str_replace |

### 📅 تقویم شمسی

- **الگوریتم ۳۳ ساله** (ساده، سریع، دقیق برای ۱۲۴۴–۱۴۷۳)
- **الگوریتم ۲۸۲۰ ساله** (دقیق برای محدودهٔ وسیع)
- **متدهای `fromGregorian`, `toGregorian`, `format`**
- **نام ماه‌های فارسی**
- **تشخیص سال کبیسه**
- **محاسبهٔ روز هفته** (شنبه = ۰)
- **تبدیل خودکار** در همهٔ تاریخ‌های سیستم

---

## 🏛️ معماری

### نمای شاختاری

```
┌──────────────────────────────────────────────────────────────────┐
│                    Client (Browser / CLI / Mobile)                │
└──────────────────────────────┬───────────────────────────────────┘
                               │
                  ┌────────────┴────────────┐
                  │                         │
                  ▼                         ▼
         ┌──────────────────┐      ┌──────────────────┐
         │  REST API (JSON) │      │  Classic WP PHP  │
         │  /wp-json/...    │      │  (front + portal)│
         └────────┬─────────┘      └────────┬─────────┘
                  │                         │
                  └────────────┬────────────┘
                               │
                               ▼
┌──────────────────────────────────────────────────────────────────┐
│                    Application Core (PHP 8.1+)                   │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────┐ │
│  │ Objects  │ │Accounting│ │ Workflow │ │  Audit   │ │  Tax   │ │
│  │ Engine   │ │ (Ledger) │ │ Engine   │ │  Logger  │ │ Client │ │
│  └─────┬────┘ └─────┬────┘ └─────┬────┘ └─────┬────┘ └────┬───┘ │
│        └────────────┴────────────┴────────────┴───────────┘     │
│                              │                                   │
│                              ▼                                   │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │     Domain Modules (CRM, ERP, HRM, Tax, Marketing)         │  │
│  └────────────────────────────────────────────────────────────┘  │
└──────────────────────────────┬───────────────────────────────────┘
                               │
                               ▼
┌──────────────────────────────────────────────────────────────────┐
│                  Persistence Layer (MySQL 8 / MariaDB 10.6)      │
│  ┌────────────────────────┐   ┌──────────────────────────────┐  │
│  │   WP core tables       │   │   Custom tables              │  │
│  │  (users, options, posts│   │   wp_parsyar_*               │  │
│  │  comments, terms)      │   │   wp_ent_data_<object>       │  │
│  └────────────────────────┘   └──────────────────────────────┘  │
│  Object Cache (Redis/Memcached) — auto-fallback to transients    │
└──────────────────────────────────────────────────────────────────┘
```

### نمای فیزیکی

```
ParsYar/
├── enterprise-core-plugin/         # PHP Plugin (هستهٔ اصلی)
│   ├── enterprise-core.php         # Bootstrap
│   ├── includes/                   # کلاس‌های هسته
│   │   ├── class-jalali.php        # موتور تقویم شمسی
│   │   ├── class-validator.php     # اعتبارسنج‌های ایرانی
│   │   ├── class-db.php            # لایهٔ دیتابیس
│   │   ├── class-installer.php     # نصب و ارتقاء
│   │   └── class-router.php        # مسیریاب REST
│   ├── api/                        # کنترلرهای REST
│   │   ├── class-restrouter.php
│   │   ├── class-authcontroller.php
│   │   ├── class-objectcontroller.php
│   │   ├── class-recordcontroller.php
│   │   ├── class-accountingcontroller.php
│   │   ├── class-crmcontroller.php
│   │   ├── class-erpcontroller.php
│   │   ├── class-hrmcontroller.php
│   │   ├── class-taxcontroller.php
│   │   ├── class-workflowcontroller.php
│   │   └── class-auditcontroller.php
│   ├── modules/
│   │   ├── objects/                # موتور شیء سفارشی
│   │   │   ├── class-bootstrap.php
│   │   │   ├── class-objectengine.php
│   │   │   ├── class-schemabuilder.php
│   │   │   ├── class-recordstore.php
│   │   │   ├── class-fieldtypes.php
│   │   │   └── class-objectexceptions.php
│   │   ├── accounting/             # دفترداری
│   │   │   ├── class-chartofaccounts.php
│   │   │   └── class-ledger.php
│   │   ├── crm/                    # CRM
│   │   │   └── class-leadservice.php
│   │   ├── erp/                    # ERP
│   │   │   ├── class-inventoryservice.php
│   │   │   └── class-invoiceservice.php
│   │   ├── hrm/                    # منابع انسانی
│   │   │   ├── class-employeeservice.php
│   │   │   └── class-payrollservice.php
│   │   ├── tax/                    # سامانهٔ مؤدیان
│   │   │   └── class-moodianclient.php
│   │   ├── workflow/               # اتوماسیون
│   │   │   ├── class-dispatcher.php
│   │   │   └── class-repository.php
│   │   └── audit/                  # حسابرسی
│   │       └── class-logger.php
│   ├── admin/                      # صفحات مدیریت WP
│   │   ├── class-menu.php
│   │   ├── class-adminpages.php
│   │   └── class-setup.php         # Wizard نصب
│   └── db/
│       └── class-demoseeder.php    # بارگذاری دادهٔ دمو
│
└── enterprise-theme/               # تم React + Tailwind
    ├── src/
    │   ├── App.jsx
    │   ├── main.jsx
    │   ├── index.css
    │   ├── api/client.js
    │   └── pages/                  # صفحات داشبورد
    │       ├── Dashboard.jsx
    │       ├── ObjectsList.jsx
    │       ├── RecordsList.jsx
    │       ├── Leads.jsx
    │       ├── Products.jsx
    │       ├── Invoices.jsx
    │       ├── Employees.jsx
    │       ├── Accounting.jsx
    │       ├── Workflows.jsx
    │       └── Audit.jsx
    ├── build/                      # خروجی Vite
    ├── package.json
    ├── vite.config.js
    ├── tailwind.config.js
    └── postcss.config.js
```

### لایه‌ها

1. **Presentation** — React SPA + PHP templates کلاسیک WP
2. **API** — REST v1 با احراز هویت JWT/Cookie
3. **Application Core** — Object Engine، Ledger، Workflow، Audit
4. **Domain Modules** — CRM, ERP, HRM, Tax
5. **Persistence** — WP core + جداول سفارشی + Object Cache

### چرخهٔ درخواست

```
HTTP Request
   │
   ▼
WP Router (rewrite)
   │
   ▼
REST API init (rest_api_init)
   │
   ▼
Permission callback (current_user_can + nonce)
   │
   ▼
Rate limit check (token bucket per user/IP)
   │
   ▼
Controller (AuthController, ObjectController, ...)
   │
   ▼
Service / Engine (ObjectEngine, Ledger, ...)
   │
   ▼
Repository (DB with prepared statements)
   │
   ▼
MySQL + Object Cache
   │
   ▼
Response (JSON with proper headers + CSP)
```

---

## 🚀 نصب سریع

### پیش‌نیازها

| جزء | حداقل | توصیه‌شده |
|------|-------|-----------|
| **PHP** | 8.1 | 8.2 یا 8.3 |
| **MySQL** | 8.0 | 8.0+ یا MariaDB 10.6+ |
| **WordPress** | 6.5 | 6.6+ |
| **Node** | 18 | 20 LTS |
| **RAM** | 512 MB | 2 GB+ |
| **Disk** | 200 MB | 1 GB+ |

**افزونه‌های PHP مورد نیاز:**
```
mbstring, intl, mysqli, zip, gd, bcmath, opcache,
json, openssl, fileinfo, ctype, iconv
```

**مجوزهای فایل:**
```
wp-content/uploads/  → 755 (writable)
wp-content/plugins/  → 755
```

### نصب دستی

```bash
# ۱. کلون کردن ریپو
git clone https://github.com/QalamHipHop/ParsYar.git

# ۲. کپی به وردپرس
cp -r ParsYar/enterprise-core-plugin/* /path/to/wordpress/wp-content/plugins/enterprise-core-plugin/
cp -r ParsYar/enterprise-theme/* /path/to/wordpress/wp-content/themes/enterprise-theme/

# ۳. ساخت داشبورد React
cd /path/to/wordpress/wp-content/themes/enterprise-theme/
npm install
npm run build

# ۴. فعال‌سازی
wp plugin activate enterprise-core-plugin --allow-root
wp theme activate enterprise --allow-root
```

### نصب با Docker

```bash
# کلون ریپو
git clone https://github.com/QalamHipHop/ParsYar.git
cd ParsYar

# اجرای stack (WordPress + MySQL + phpMyAdmin)
docker-compose up -d

# منتظر بالا آمدن (۳۰ ثانیه)
sleep 30

# فعال‌سازی افزونه و تم
docker-compose exec wordpress wp plugin activate enterprise-core-plugin
docker-compose exec wordpress wp theme activate enterprise

# بارگذاری دادهٔ دمو
docker-compose exec wordpress wp enterprise demo load
```

سپس به آدرس زیر بروید:
- **سایت**: http://localhost:8080
- **ادمین**: http://localhost:8080/wp-admin
- **phpMyAdmin**: http://localhost:8081
- **داشبورد ParsYar**: http://localhost:8080/enterprise

### نصب با WP-CLI

```bash
# نصب افزونه از دایرکتوری
wp plugin activate enterprise-core-plugin --path=/path/to/wordpress

# بررسی سلامت
wp enterprise doctor --path=/path/to/wordpress

# بارگذاری دادهٔ دمو
wp enterprise demo load --path=/path/to/wordpress

# ساخت اولین کاربر ادمین (اگر قبلاً نشده)
wp enterprise user create-admin \
  --username=admin \
  --email=admin@example.com \
  --name="مدیر سیستم" \
  --path=/path/to/wordpress
```

### فعال‌سازی Wizard

پس از فعال‌سازی افزونه، **به‌طور خودکار** به صفحهٔ Wizard هدایت می‌شوید:

```
wp-admin → Enterprise → Setup Wizard
```

اگر هدایت نشد، به‌صورت دستی بروید:
```
http://yoursite.com/wp-admin/admin.php?page=enterprise-setup
```

Wizard شامل **۲۳ مرحله** است:
1. خوش‌آمدگویی + بررسی پیش‌نیازها
2. زبان و منطقهٔ زمانی
3. مشخصات سازمان
4. چند شرکت (Holding)
5. شعبه‌ها
6. ارزها و نرخ تبدیل
7. سال مالی
8. تنظیمات شمسی
9. Pipeline فروش
10. مالیات
11. فعال/غیرفعال‌سازی ماژول‌ها
12. کاربران و نقش‌ها
13. کانال‌های اعلان
14. درگاه‌های پرداخت
15. یکپارچگی‌های ایرانی
16. فروشگاه (WooCommerce اختیاری)
17. ورود داده (CSV/Excel)
18. دادهٔ دمو
19. برندینگ
20. دستیار AI
21. امنیت
22. پشتیبان‌گیری
23. پایان

---

## ⚙️ پیکربندی

### تنظیمات سازمانی

```php
// wp-admin → Enterprise → Settings → Organization
[
    'legal_name'    => 'شرکت نمونه پارس',
    'persian_name'  => 'شرکت نمونه پارس',
    'national_id'   => '10101234567',
    'economic_code' => '411234567890',
    'vat_number'    => '411234567890',
    'industry'      => 'فناوری اطلاعات',
    'logo'          => '/uploads/2026/07/logo.png',
    'default_currency' => 'IRT',  // IRR, IRT, USD, EUR, AED
    'timezone'      => 'Asia/Tehran',
    'locale'        => 'fa_IR',
    'jalali_algo'   => '2820',     // 33 or 2820
]
```

### تنظیمات مالی

```php
// wp-admin → Enterprise → Settings → Taxes
[
    'vat_rate'         => 10.0,         // درصد مالیات بر ارزش افزوده
    'withholding'      => 0.0,          // مالیات تکلیفی
    'tax_inclusive'    => false,        // قیمت با مالیات است یا خیر
    'rounding'         => 'half_up',    // گرد کردن
    'fiscal_year_start' => '03-21',     // ۱ فروردین
    'currency_decimals' => 0,          // ریال = ۰، تومان = ۰
]
```

### تنظیمات اعلان

```php
// wp-admin → Enterprise → Settings → Notifications
[
    'email' => [
        'from_name'  => 'ParsYar',
        'from_email' => 'noreply@example.com',
        'smtp_host'  => 'smtp.example.com',
        'smtp_port'  => 587,
        'smtp_secure'=> 'tls',
    ],
    'sms' => [
        'provider'   => 'kavenegar',    // kavenegar, melipayamak, ghasedak, sms.ir
        'api_key'    => 'your-api-key',
        'sender'     => '10001234',
    ],
    'in_app' => [
        'enabled' => true,
        'retention_days' => 30,
    ],
    'web_push' => [
        'enabled' => false,
        'vapid_public'  => '',
        'vapid_private' => '',
    ],
]
```

### تنظیمات امنیتی

```php
// wp-admin → Enterprise → Settings → Security
[
    '2fa_required'    => true,
    '2fa_method'      => 'totp',        // totp, sms, email
    'session_timeout' => 30,           // دقیقه
    'password_min_length' => 12,
    'password_require_special' => true,
    'ip_allowlist'    => [],           // آرایهٔ IP / CIDR
    'audit_retention_days' => 365,
    'rate_limit_per_minute' => 60,
]
```

---

## 🔌 REST API

### احراز هویت

سه روش پشتیبانی می‌شود:

**۱) Cookie + Nonce** (برای فرم‌های وب)
```javascript
// Get nonce from wp_localize_script
const nonce = window.parsYarData.nonce;

fetch('/wp-json/enterprise/v1/objects', {
    headers: { 'X-WP-Nonce': nonce },
    credentials: 'same-origin'
});
```

**۲) JWT** (برای SPA و اپلیکیشن موبایل)
```bash
# دریافت توکن
curl -X POST https://example.com/wp-json/enterprise/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"xxx"}'

# استفاده
curl https://example.com/wp-json/enterprise/v1/objects \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

**۳) Application Password** (سازگار با WP 5.6+)
```bash
curl -u "appname:xxxx xxxx xxxx xxxx" \
  https://example.com/wp-json/enterprise/v1/objects
```

### ساختار پاسخ

**موفق:**
```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "page": 1,
    "per_page": 50,
    "total": 1234,
    "request_id": "req_abc123"
  }
}
```

**خطا:**
```json
{
  "success": false,
  "error": {
    "code": "parsyar.objects.not_found",
    "message": "Object \"customer\" not found.",
    "details": { "key": "customer" },
    "request_id": "req_abc123"
  }
}
```

### Endpoint های اصلی

| Method | Path | توضیح |
|--------|------|-------|
| `POST` | `/enterprise/v1/auth/login` | ورود + دریافت JWT |
| `POST` | `/enterprise/v1/auth/logout` | خروج |
| `GET` | `/me` | اطلاعات کاربر جاری |
| `GET` | `/enterprise/v1/objects` | لیست شیءهای تعریف‌شده |
| `POST` | `/enterprise/v1/objects` | تعریف شیء جدید |
| `GET` | `/enterprise/v1/objects/{key}` | دریافت شیء |
| `PATCH` | `/enterprise/v1/objects/{key}` | به‌روزرسانی شیء |
| `DELETE` | `/enterprise/v1/objects/{key}` | حذف شیء |
| `GET` | `/enterprise/v1/objects/{key}/records` | لیست رکوردها |
| `POST` | `/enterprise/v1/objects/{key}/records` | ایجاد رکورد |
| `GET` | `/enterprise/v1/objects/{key}/records/{id}` | دریافت رکورد |
| `PATCH` | `/enterprise/v1/objects/{key}/records/{id}` | به‌روزرسانی رکورد |
| `DELETE` | `/enterprise/v1/objects/{key}/records/{id}` | حذف رکورد |
| `GET` | `/enterprise/v1/crm/leads` | لیست سرنخ‌ها |
| `POST` | `/enterprise/v1/crm/leads` | ایجاد سرنخ |
| `POST` | `/enterprise/v1/crm/leads/{id}/convert` | تبدیل به مخاطب + معامله |
| `GET` | `/enterprise/v1/erp/products` | لیست محصولات |
| `POST` | `/enterprise/v1/erp/invoices` | صدور فاکتور |
| `POST` | `/enterprise/v1/tax/invoices` | صدور صورتحساب الکترونیکی |
| `GET` | `/enterprise/v1/accounting/journal` | دفتر روزنامه |
| `POST` | `/enterprise/v1/accounting/journal` | ثبت سند |
| `GET` | `/enterprise/v1/hr/employees` | لیست کارمندان |
| `POST` | `/enterprise/v1/hr/payroll/run` | اجرای فیش حقوقی |
| `GET` | `/enterprise/v1/audit/log` | لاگ حسابرسی |
| `GET` | `/enterprise/v1/reports/{report}` | گزارش‌ها |

### مثال کامل

**۱) تعریف یک شیء سفارشی "customer":**

```bash
curl -X POST https://example.com/wp-json/enterprise/v1/objects \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "key": "customer",
    "label": "مشتری",
    "label_plural": "مشتریان",
    "fields": [
      {"api_name":"first_name","label":"نام","type":"text","is_required":true,"is_unique":false},
      {"api_name":"last_name","label":"نام خانوادگی","type":"text","is_required":true},
      {"api_name":"national_id","label":"کد ملی","type":"national_id","is_unique":true},
      {"api_name":"mobile","label":"موبایل","type":"mobile"},
      {"api_name":"email","label":"ایمیل","type":"email"},
      {"api_name":"birthday","label":"تولد","type":"jalali"},
      {"api_name":"credit_limit","label":"سقف اعتبار","type":"decimal"},
      {"api_name":"status","label":"وضعیت","type":"enum","options":["active","inactive","blocked"]}
    ]
  }'
```

**۲) ایجاد رکورد:**

```bash
curl -X POST https://example.com/wp-json/enterprise/v1/objects/customer/records \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "علی",
    "last_name": "محمدی",
    "national_id": "1234567890",
    "mobile": "۰۹۱۲۳۴۵۶۷۸۹",     # اعداد فارسی هم قبول می‌شود
    "email": "ali@example.com",
    "credit_limit": "50000000"
  }'
```

**۳) سیستم به‌طور خودکار:**
- اعداد فارسی `۰۹۱۲...` → انگلیسی `0912...`
- اعتبارسنجی کد ملی
- تشخیص اپراتور (همراه‌اول)
- نرمال‌سازی credit_limit به DECIMAL
- ثبت در لاگ حسابرسی
- ارسال event به Workflow Engine

**پاسخ:**
```json
{
  "success": true,
  "data": {
    "id": 42,
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "first_name": "علی",
    "last_name": "محمدی",
    "national_id": "1234567890",
    "mobile": "09123456789",
    "email": "ali@example.com",
    "credit_limit": "50000000.0000",
    "status": "active",
    "created_at": "2026-07-31 08:00:00",
    "updated_at": "2026-07-31 08:00:00"
  },
  "meta": {"request_id": "req_xyz789"}
}
```

---

## 🖥️ خط فرمان (WP-CLI)

```bash
# وضعیت کلی سیستم
wp enterprise doctor

# بارگذاری دادهٔ دمو
wp enterprise demo load

# پاک کردن دادهٔ دمو
wp enterprise demo purge

# بررسی ساختار دیتابیس
wp enterprise db check

# بهینه‌سازی جداول
wp enterprise db optimize

# اجرای Workflow های در انتظار
wp enterprise workflow run

# تست اتصال به سامانهٔ مؤدیان
wp enterprise tax test-connection

# تست ارسال SMS
wp enterprise sms test 09123456789

# تست ایمیل
wp enterprise email test admin@example.com

# پاکسازی لاگ حسابرسی قدیمی
wp enterprise audit prune --days=365

# ساخت کاربر ادمین
wp enterprise user create-admin \
  --username=admin \
  --email=admin@example.com \
  --name="مدیر"

# صادرات تنظیمات
wp enterprise config export > parsyar-config.json

# واردات تنظیمات
wp enterprise config import parsyar-config.json

# اجرای migration
wp enterprise migrate run

# لیست Command ها
wp enterprise --help
```

---

## 🛠️ توسعه و مشارکت

ما از مشارکت استقبال می‌کنیم! لطفاً قبل از ارسال PR، [CONTRIBUTING.md](CONTRIBUTING.md) را مطالعه کنید.

### ساختار توسعه

```bash
# Fork و clone
git clone https://github.com/YOUR-USERNAME/ParsYar.git
cd ParsYar

# ساخت شاخه
git checkout -b feat/my-feature

# ایجاد تغییرات و commit
git add .
git commit -m "feat(module): description"

# ارسال به fork خودتان
git push origin feat/my-feature

# باز کردن PR در GitHub
```

### ساخت یک ماژول جدید (در ۵ دقیقه)

```php
<?php
/**
 * My Custom Module
 * @package Enterprise\Modules\MyModule
 */

declare(strict_types=1);

namespace Enterprise\Modules\MyModule;

use Enterprise\Modules\Objects\RecordStore;

final class MyService
{
    public function doSomething(int $recordId): array
    {
        // مثال: خواندن یک رکورد
        $store = RecordStore::forObjectByApi('customer');
        $record = $store->get($recordId);
        if (!$record) {
            return ['error' => 'not found'];
        }
        return $record;
    }
}
```

سپس در فولدر `enterprise-core-plugin/modules/my-module/` قرار دهید و autoload خودکار فعال می‌شود.

---

## 📏 استانداردها

- **PHP**: 8.1+ با `declare(strict_types=1)`، PSR-12 + WordPress-Extra
- **JavaScript**: ES2022، React 18، ESLint + Prettier
- **CSS**: Tailwind 3، BEM برای استایل سفارشی
- **Database**: MySQL 8 / MariaDB 10.6+، utf8mb4_persian_ci
- **API**: RESTful، OpenAPI 3.1، Semantic Versioning
- **Git**: Conventional Commits + SemVer

### کنوانسیون نام‌گذاری

```
PHP Classes:   PascalCase         → SchemaBuilder
PHP Methods:   camelCase          → getFields()
PHP Constants: UPPER_SNAKE        → MAX_RECORDS
DB Tables:     snake_case         → wp_parsyar_journal_entry
DB Columns:    snake_case         → created_at, is_active
API Routes:    kebab-case         → /api/v1/sales-orders
JSON Keys:     snake_case         → first_name, customer_id
React:         PascalCase (comp)  → <CustomerCard />
                camelCase (hooks) → useCustomer
```

---

## 🧪 تست

```bash
# تست‌های PHP (PHPUnit)
cd enterprise-core-plugin
composer install
vendor/bin/phpunit

# تست‌های JS (Vitest)
cd enterprise-theme
npm install
npm test

# تست E2E (Playwright)
npm run test:e2e

# بررسی کیفیت کد
composer run-script lint
composer run-script stan     # PHPStan level 8
npm run lint
```

---

## 🗺️ نقشهٔ راه

- [x] **v1.0.0** — هستهٔ شیء سفارشی + دفترداری + REST + دمو
- [x] **v1.0.1** — موتور Jalali + Validator ایرانی
- [ ] **v1.1.0** — ماژول CRM کامل (Leads/Contacts/Deals/Activities)
- [ ] **v1.2.0** — ماژول ERP (Products/Inventory/Invoices)
- [ ] **v1.3.0** — ماژول HRM (Employees/Payroll/Attendance)
- [ ] **v1.4.0** — ادغام واقعی سامانهٔ مؤدیان (e-Invoice)
- [ ] **v1.5.0** — Workflow Engine بصری (Drag-Drop)
- [ ] **v1.6.0** — گزارش‌ساز سفارشی
- [ ] **v1.7.0** — Portal مشتری (PWA)
- [ ] **v1.8.0** — اپلیکیشن موبایل (React Native)
- [ ] **v2.0.0** — Multi-Tenant SaaS Mode

---

## 📄 مجوز

این پروژه تحت مجوز [GPL-2.0-or-later](LICENSE) منتشر شده است — همان مجوز WordPress.

این یعنی:
- ✅ استفادهٔ تجاری آزاد است
- ✅ تغییر آزاد است
- ✅ توزیع آزاد است
- ⚠️ تغییرات شما نیز باید GPL باشد
- ⚠️ هیچ ضمانتی ارائه نمی‌شود

---

## 📞 تماس

- **نگهدارنده**: [Qalam](https://github.com/QalamHipHop)
- **ایمیل**: qalam@parsyar.dev
- **GitHub**: https://github.com/QalamHipHop/ParsYar
- **Issues**: https://github.com/QalamHipHop/ParsYar/issues
- **Discussions**: https://github.com/QalamHipHop/ParsYar/discussions

---

<div align="center">

ساخته‌شده با ❤️ در ایران

[بالا ↑](#parsyar--enterprise-platform)

</div>
