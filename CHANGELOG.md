# Changelog

تمام تغییرات مهم این پروژه در این فایل ثبت می‌شود.
قالب بر اساس [Keep a Changelog](https://keepachangelog.com/fa/1.1.0/) و
این پروژه از [Semantic Versioning](https://semver.org/lang/fa/) پیروی می‌کند.

## [1.5.0] - 2026-07-31

### افزوده (Added)

- **Workflow Dispatcher v2**: گراف واقعی با چند یال خروجی از یک گره (branching)،
  سه نوع مسیر شرطی `true`/`false`/`default`، حفاظت در برابر حلقه (MAX_HOPS=200).
- **گره‌های جدید**: `http_request` (POST/GET/PUT/DELETE)، `delay` (WP-Cron)،
  `create_task`، `branch`، `merge`. در مجموع ۱۲ نوع گره با رنگ‌بندی متمایز.
- **عملگرهای شرطی جدید**: `>=`, `<=`, `contains`, `starts_with`, `ends_with`,
  `in`, `not_in`, `empty`, `not_empty`.
- **Template rendering**: قابلیت `{{ path.to.value }}` در تمام فیلدهای متنی
  (پیامک، ایمیل، URL، value، ...).
- **اعتبارسنجی گراف**: متد `Dispatcher::validateGraph()` قبل از ذخیره فراخوانی
  می‌شود و خطاها را در HTTP 422 برمی‌گرداند.
- **Workflow REST API کامل** (۱۲ اندپوینت):
  - `GET    /workflows` و `?active=1`
  - `GET    /workflows/{id}`، `POST /workflows`، `PUT /workflows/{id}`، `DELETE /workflows/{id}`
  - `POST   /workflows/{id}/duplicate`، `POST /workflows/{id}/run`
  - `GET    /workflows/{id}/runs`، `GET /workflows/{id}/logs`
  - `GET    /workflows/templates`، `/triggers`، `/node-types`، `/stats`
- **۳ قالب آماده**: welcome-lead (پیامک خوش‌آمد)، overdue-reminder (شرط مبلغ +
  ایمیل/SMS)، deal-won (set_field + task + notify).
- **آمار Workflow**: تعداد کل، فعال، اجراها، نرخ موفقیت.
- **Visual Workflow Editor (React + SVG)**: drag-drop روی بوم گراف، اتصال
  گره‌ها با click+drag و انتخاب label (`true`/`false`/`default`)، inspector
  برای هر گره با فرم پویا بر اساس نوع، sidebar با لیست کامل گره‌ها، نوار
  آمار در بالا، دسترسی سریع به قالب‌ها از لیست اصلی.
- **دو تست واحد جدید** برای Dispatcher در `tests/unit/Modules/Workflow/`.

### تغییر (Changed)

- **Installer VERSION** از `1.2.1` به `1.5.0` ارتقا یافت.
- **Dispatcher قدیمی** به‌طور کامل بازنویسی شد (الگوریتم جدید، BFS با
  branching، visited-set، logs کامل‌تر).

## [1.4.0] - 2026-07-31

### افزوده (Added)

- **HRM Suite کامل**: ۴ سرویس جدید (EmployeeService، AttendanceService،
  LeaveService، PayrollService) + PerformanceReview. ۲۵+ اندپوینت REST.
- **EmployeeService**: فیلدهای فارسی (نام پدر، سری شناسنامه، محل تولد)،
  اطلاعات بانکی (شبا/کارت/حساب)، شماره بیمه، نوع استخدام، مدیر، تاریخ
  قرارداد، مانده مرخصی/استعلاجی، مهارت‌ها، آواتار، یادداشت.
  جستجوی ۵سطحی (نام، کدملی، موبایل، ایمیل، ...).
- **AttendanceService**: check_in/check_out، محاسبهٔ خودکار تأخیر نسبت به
  شروع شیفت + اضافه‌کاری، کسر ۳۰ دقیقه ناهار برای شیفت ≥ ۶ ساعت، تشخیص
  ناهنجاری (check_out ≤ check_in، پانج ناقص)، monthGrid، monthSummary،
  teamStats.
- **LeaveService**: ۱۰ نوع مرخصی (استحقاقی، استعلاجی، بدون حقوق، زایمان،
  پدر، سوگواری، ازدواج، حج، مأموریت، بیماری خانواده)، تأیید/رد/لغو با
  فیلدهای audit، annualBalance، yearReport، حالت half_day و hourly.
- **PayrollService**: createRun() با idempotency بر اساس (company, year, month)،
  محاسبهٔ خودکار حقوق ماهانه، مالیات پلکانی ایران (۰-۵۰M: ۰٪، ۵۰-۱۰۰M: ۱۰٪،
  ۱۰۰-۲۰۰M: ۱۵٪، ۲۰۰-۴۰۰M: ۲۰٪، >۴۰۰M: ۲۵٪)، بیمه ۷٪، pro-rated حقوق
  پایه، اضافه‌کاری ۱۴۰٪ دستمزد ساعتی.
- **PerformanceReview**: ۴ محور (بهره‌وری ۴۰٪، کیفیت ۳۰٪، کار تیمی ۱۵٪،
  وقت‌شناسی ۱۵٪)، چرخهٔ draft→submitted→acknowledged→finalized.

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
