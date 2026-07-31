# Changelog

## [3.0.0] - 2026-07-31 (Wizard v3.0 — Modern Design System)

### افزوده (Added)

- **Wizard v3.0 — Glassmorphism + Neo-brutalism Design System**:
  - بازنویسی کامل `admin/views/wizard/layout.php` با سیستم طراحی مدرن (glassmorphism + neo-brutalist)
  - **پس‌زمینهٔ پویا** با orbهای شناور و گرید subtle (GPU-friendly animations)
  - **Header شیشه‌ای** با backdrop-blur + saturate، sticky با shadow پویا
  - **Progress bar متحرک** با shimmer effect، آیکون‌های status (✓/–/●)، شمارندهٔ فارسی
  - **Sidebar مراحل** با ۲۳ step، ۴ حالت (current/done/skip/pending)، انیمیشن hover با translate
  - **Card اصلی** با glow پویا، ۲ دکمهٔ neo-brutalist (قبلی/رد/بعدی)، status badge
  - **System Check side card** با آیکون‌های ✓/! و meta info
  - **Tips side card** با لیست راهنما
  - **Toast system** با ۴ نوع (success/error/warning/info)، slide-up + slide-down animations
  - **Step-01 (Welcome)** بازطراحی شد: mode grid با آیکون + system overview با stats (ok/fail/total)
  - **Step-23 (Done)** بازطراحی شد: celebration hero با ✓ انیمیشنی، stats grid، summary card، ۶ quick action cards
  - **JavaScript framework** برای navigation: AJAX ذخیرهٔ خودکار، prev/next/skip، apply در انتها
  - **Chip groups** با single-select + data binding به hidden input (`data-pw-chip-group`)
  - **Mode grid** با ۵ mode + click-to-select (`data-pw-mode-grid`)
  - **Persian digits** در همهٔ شمارنده‌ها با helper function `parsyar_persian_digits()`
  - **Import/Export** بهبود یافته با admin-post.php endpoint
  - **Reset** با confirmation modal
  - **Responsive**: در ۱۱۰۰px و ۹۶۰px breakpoint ها grid layout تغییر می‌کند
  - **prefers-reduced-motion** رعایت می‌شود
  - **Helper function** `parsyar_persian_digits()` در layout اضافه شد
- **Bootstrap::VERSION**: 2.0.0 → 3.0.0

### تغییر (Changed)

- `enterprise-core.php`: VERSION به 3.0.0 ارتقا یافت
- Step-01 با آیکون emoji و mode grid کامل بازطراحی شد
- Step-23 با celebration animation و quick actions بازطراحی شد

## [2.0.0] - 2026-07-31 (Multi-tenant SaaS — Holding mode)

### افزوده (Added)

- **ماژول Multitenant (هستهٔ چند-مستأجری)** — `Enterprise\Modules\Multitenant\*`:
  - `Tenant` — مدل کامل با `uuid`, `slug`, `plan` (starter/pro/enterprise), `settings` (JSON), `branding` (JSON)
  - `Branch` — شعب هر tenant با `parent_id` (سلسله‌مراتب)، `is_default`, `is_active`، soft delete
  - `Membership` — ارتباط user↔tenant↔branch با `role` (owner/admin/manager/member/viewer) و `UNIQUE (user_id, tenant_id, branch_id)`
  - `Context` — حل خودکار tenant/branch فعال از روی header (`X-ParsYar-Company`, `X-ParsYar-Branch`) → query var → user_meta → tenant پیش‌فرض؛ رجیستر روی `rest_pre_dispatch` تا قبل از controller اجرا شود
  - `Repository` — cache group مستقل برای هر tenant + prune روزانه tenantهای آرشیو‌شدهٔ بالای ۹۰ روز
- **Multitenant REST API (۱۳ endpoint)** در namespace `enterprise/v1/tenants/*`:
  - `GET/POST /tenants`، `GET/PUT/DELETE /tenants/{id}`
  - `GET /tenants/current` (tenant فعال از Context)
  - `GET /tenants/me` (memberships کاربر جاری)
  - `POST /tenants/switch` (تغییر tenant/branch + ذخیره در user_meta)
  - `GET/POST /tenants/{id}/branches`، `GET/POST /tenants/{id}/members`
- **Installer**: `migrateMultitenantTables()` (سه جدول با `UNIQUE KEY` و `KEY` مناسب برای InnoDB + utf8mb4) + `seedDefaultTenant()` برای نصب‌های تک‌مستأجری
- **Bootstrap**: `Multitenant\Context::boot()` و `Multitenant\Repository::boot()` به صورت خودکار در `registerHooks()` رجیستر می‌شوند
- **Composer PSR-4**: `Enterprise\Modules\Multitenant\` و `Enterprise\Api\Multitenant\` اضافه شد

### تغییر (Changed)

- نسخهٔ `Bootstrap::VERSION` و `Installer::VERSION` از `1.8.0` به `2.0.0` ارتقا یافت
- نسخهٔ theme و `PARSYAR_THEME_VERSION` به `2.0.0` sync شد
- `composer.json`: maintainer به `QalamHiphop <qalam@parsyar.dev>` تغییر یافت
- README کاملاً بازنویسی شد (تمام ماژول‌ها، ۲۲ نوع فیلد، سه سطح سرویس، Portal، Mobile، Multitenant، همهٔ REST routes)

## [1.8.0] - 2026-07-31 (Mobile backend — FCM/APNs)

### افزوده (Added)

- **ماژول Mobile (بک‌اند React Native)** — `Enterprise\Modules\Mobile\*`:
  - `Device` — مدل کامل با `platform` (ios/android)، `app_version`، `os_version`، `device_model`، `locale`، `push_enabled`، `is_active`، `last_seen_at`
  - `Device::register()` — upsert بر اساس `token` (idempotent برای re-install)
  - `MobileModule::sendToDevice()` — ارسال push از طریق FCM (Android) و APNs (iOS) با کلیدهای پیکربندی‌شده در admin
  - `MobileModule::pruneStaleDevices()` — اجرای روزانه روی `enterprise_daily` برای حذف deviceهای idle بالای ۱۸۰ روز
  - `Mobile REST Controller` — ۵ endpoint (`/mobile/info`, `/mobile/devices/register`, `/mobile/devices/heartbeat`, `/mobile/devices/{id}`, `/mobile/notifications/test`)
- **Mobile App (React Native 0.75)** — `enterprise-mobile/`:
  - ۹ صفحه + ۴ صفحه detail (Login, Verify, Dashboard, Invoices + Detail, Orders + Detail, Payments + Detail, Tickets + New + Detail, Profile, Settings)
  - **Magic Link** با endpointهای بک‌اند پورتال
  - **JWT rotation** خودکار روی ۴۰۱ با Axios interceptor و refresh-token واحد
  - **AsyncStorage** برای token، baseUrl، profile
  - **Biometric** (TouchID/FaceID/Fingerprint) با `react-native-biometrics` (opt-in)
  - **Push** با `react-native-push-notification` (FCM + APNs)
  - **Deep linking**: `parsyar://verify?token=…` + Universal Links
  - iOS Info.plist با ATS، FaceID usage description، deep link registration
  - AndroidManifest.xml با INTERNET، USE_BIOMETRIC، POST_NOTIFICATIONS، deep link filters
  - Network security config (cleartext فقط برای localhost dev)
  - Podfile با Fabric/Hermes، vector icons، push notification pod
  - build.gradle با ProGuard، Hermes، minSdk 24
  - GitHub Actions CI (`mobile-ci.yml`): typecheck + Jest + coverage
  - Jest tests برای `lib/api`
  - i18n با `fa-IR` پیش‌فرض + تشخیص خودکار locale دستگاه
  - Component library: Card, Button, Input, StatusBadge, Empty, Chip
  - Theme tokens آینهٔ PWA
  - Redux store با slices: auth + ui
  - README کامل با deep linking، security، roadmap
- **Installer**: `migrateMobileDevicesTable()` + `UNIQUE KEY` روی `token(191)` (سازگار با utf8mb4)
- **Admin**: صفحهٔ `Mobile App` با stats، FCM/APNs config، min app version، maintenance toggle
- **Bootstrap**: `MobileModule::boot()` به‌صورت خودکار رجیستر می‌شود

### تغییر (Changed)

- `enterprise-core.php` PSR-4 namespaces: `Enterprise\Modules\Mobile\`, `Enterprise\Api\Mobile\` اضافه شد
- `admin/class-menu.php`: زیرمنوی «Mobile App» اضافه شد

## [1.7.0] - 2026-07-31 (PWA frontend completed)

### افزوده (Added)

- **PWA Frontend — تکمیل** (این commit):
  - `src/lib/types.ts`: تایپ‌های مشترک (Session, Profile, Invoice, Order, Payment, Ticket, Notification)
  - `src/lib/api.ts`: کلاینت fetch کامل با rotation خودکار JWT روی 401، persist در localStorage، VAPID، push subscribe/unsubscribe، لاگ رویداد، و هلپرهای `formatCurrency` / `formatDateJalali`
  - `src/lib/i18n.ts`: باندل کامل fa-IR برای همهٔ صفحات (login, verify, dashboard, invoices, orders, payments, tickets, install, push)
  - `src/lib/api.test.ts`: تست‌های Vitest برای format helpers + session roundtrip
  - `src/vite-env.d.ts`: type reference برای Vite + vite-plugin-pwa
  - `src/pages/LoginPage.tsx`: فرم ایمیل، submit magic link، dev_link bypass، پیام rate-limit
  - `src/pages/VerifyPage.tsx`: query `?token=…` → `/auth/verify`، حالت‌های success/error
  - `src/pages/DashboardPage.tsx`: خوش‌آمد، ۳-stat grid، آخرین فاکتور، پرداخت‌های اخیر
  - `src/pages/InvoicesPage.tsx`: لیست با status badge، total/paid، نمایش `tax_invoice_uid`
  - `src/pages/OrdersPage.tsx`: لیست با status
  - `src/pages/PaymentsPage.tsx`: لیست با method/gateway/ref
  - `src/pages/TicketsPage.tsx`: لیست + فرم تیکت جدید (category, priority)
  - `src/components/Banners.tsx`: `InstallBanner` (capture `beforeinstallprompt`) + `PushBanner` (subscribe VAPID، تأخیر ۵ ثانیه)
  - `index.html`: preconnect فونت Vazirmatn + apple-touch-icon + PWA meta tags
  - `enterprise-theme/portal.php`: تزریق `vapidPublicKey` و اطلاعات پشتیبانی به `window.parsyarPortalConfig`

## [1.7.0] - 2026-07-31 (initial release)

### افزوده (Added)

- **Customer Portal (PWA) — ماژول کامل**:
  - `Enterprise\Modules\Portal\AuthService`: magic link + JWT (HS256) + refresh + rate limit + failed-attempt ban
  - `Enterprise\Modules\Portal\PortalService`: profile, invoices, orders, payments, tickets (CRUD), quote requests, push subscriptions, client events
  - `Enterprise\Modules\Portal\PortalModule`: auto-boot (JWT/VAPID warmup + daily prune of expired tokens/sessions/events)
  - `Enterprise\Api\Portal\PortalController`: ۱۹ endpoint REST در namespace `enterprise/v1/portal/*`
  - `Enterprise\Admin\PortalPage`: صفحهٔ مدیریت پورتال با آمار، پیکربندی، VAPID key
  - VAPID keypair در activation ساخته می‌شود
  - ۶ جدول جدید: `parsyar_portal_tokens`, `parsyar_portal_sessions`, `parsyar_portal_tickets`, `parsyar_quote_requests`, `parsyar_push_subscriptions`, `parsyar_portal_events`
  - migration `migratePortalTables()` در Installer
  - page template `portal.php` در theme اصلی (SPA mount + asset rewriting + WP config injection)
  - PWA icons: `pwa-192x192.png`, `pwa-512x512.png`, `pwa-maskable-512x512.png`, `apple-touch-icon.png`
  - ۲ تست واحد جدید (AuthService, PortalService) + smoke test
  - مستندات معماری در `docs/portal/ARCHITECTURE.md`
- **PWA Frontend (React 18 + Vite 5 + Tailwind 3 + Workbox 7)**:
  - ۷ صفحه: Login, Verify, Dashboard, Invoices, Orders, Payments, Tickets
  - i18next با fa-IR پیش‌فرض
  - Service Worker با push handler سفارشی
  - Install banner (`beforeinstallprompt`) + Push banner (با تأخیر ۵ ثانیه)
  - Offline: NetworkFirst برای API، StaleWhileRevalidate برای assets
  - Vitest test suite برای API client

### تغییر (Changed)

- Installer VERSION به 1.7.0 ارتقا یافت
- Bootstrap VERSION به 1.7.0 sync شد (قبلاً 1.3.0)
- Author: QalamHiphop (parsYar dev) به‌عنوان maintainer
- PSR-4 autoload: `Enterprise\Modules\Portal\`, `Enterprise\Api\Portal\`, `Enterprise\Modules\Mobile\`, `Enterprise\Api\Mobile\` اضافه شد
- `RestRouter::register()`: portal routes رجیستر می‌شوند
- منوی ادمین: زیرمنوی «Customer Portal» اضافه شد
- `enterprise_daily` cron event برای prune روزانه ثبت می‌شود

## [1.8.0] - 2026-07-31

### افزوده (Added)

- **اپلیکیشن موبایل (React Native)** — فاز ۱.۸.۰:
  - پروژهٔ `enterprise-mobile/` با React Native 0.75 + TypeScript 5.5 + Redux Toolkit + i18next
  - ۹ صفحه: Login, Verify, Dashboard, Invoices, Orders, Payments, Tickets (+NewTicket), Profile, Settings
  - **Magic Link** با همان endpointهای بک‌اند پورتال: `/auth/magic-link`, `/auth/verify`, `/auth/refresh`
  - **JWT rotation** خودکار روی 401 با Axios interceptor و refresh-token واحد
  - **AsyncStorage** برای ذخیرهٔ token، baseUrl، profile
  - **Biometric** (TouchID/FaceID/Fingerprint) با `react-native-biometrics` (opt-in)
  - **Push** با `react-native-push-notification` (FCM + APNs)
  - **Deep linking**: `parsyar://verify?token=…` + Universal Links `https://yourdomain.com/portal/verify?token=…`
  - **iOS Info.plist** با ATS (HTTPS only)، FaceID usage description، deep link registration
  - **AndroidManifest.xml** با INTERNET، USE_BIOMETRIC، POST_NOTIFICATIONS، deep link filters
  - **Network security config** (cleartext فقط برای localhost dev)
  - **Podfile** با Fabric/Hermes، vector icons، push notification pod
  - **build.gradle** با ProGuard، Hermes، minSdk 24
  - **GitHub Actions CI** (`mobile-ci.yml`): typecheck + Jest + coverage
  - **Jest tests** برای `lib/api` (formatCurrency، formatDateJalali، setBaseUrl، requestMagicLink، verifyMagicLink)
  - **i18n** با fa-IR پیش‌فرض + تشخیص خودکار locale دستگاه از `react-native-localize`
  - **Component library**: Card, Button, Input, StatusBadge, Empty, Chip
  - **Theme tokens** آینهٔ PWA (white/black/glassmorphism)
  - **Redux store** با slices: auth (bootstrap, setBaseUrl, requestMagic, verify, logout) + ui (online, push, biometric, locale)
  - **README** کامل با deep linking، security، roadmap

### تغییر (Changed)

- `enterprise-core.php` PSR-4 namespaces: `Enterprise\Modules\Mobile\`, `Enterprise\Api\Mobile\` اضافه شد (برای آینده)

تمام تغییرات مهم این پروژه در این فایل ثبت می‌شود.
قالب بر اساس [Keep a Changelog](https://keepachangelog.com/fa/1.1.0/) و
این پروژه از [Semantic Versioning](https://semver.org/lang/fa/) پیروی می‌کند.

## [1.6.0] - 2026-07-31

### افزوده (Added)

- **Custom Report Builder (ReportService)**: ساخت گزارش‌های سفارشی بدون کدنویسی.
  - ۱۱ منبع داده: contacts, organizations, leads, deals, products,
    invoices, payments, employees, attendance, leaves, journal.
  - فیلتر پویا با ۱۰ اپراتور (`==`, `!=`, `>`, `>=`, `<`, `<=`,
    `contains`, `in`, `empty`, `not_empty`).
  - Group By بر اساس ستون‌های دلخواه.
  - Aggregations: count, sum, avg, min, max.
  - مرتب‌سازی + limit (۱ تا ۵۰۰۰).
  - ۵ نوع نمودار: table, bar, line, pie, area.
  - اشتراک‌گذاری گزارش (`is_public`).
  - خروجی CSV با BOM فارسی.
- **۴ قالب آماده**: مخاطبان به تفکیک شهر، معاملات بسته‌شده بر اساس ماه،
  محصولات پرفروش (top-10)، هشدار موجودی کم.
- **Reports REST API** (۹ اندپوینت):
  - `GET /reports`, `GET/POST /reports`, `PUT/DELETE /reports/{id}`
  - `GET /reports/{id}/run`, `GET /reports/{id}/export.csv`
  - `POST /reports/preview` (اجرای بدون ذخیره)
  - `GET /reports/sources`, `/reports/meta`, `/reports/templates`
- **جدول `wp_parsyar_reports`**: ذخیرهٔ گزارش‌ها با `config_json`.
- **Frontend (React)**: صفحهٔ `Reports.jsx` با ادیتور کامل
  (form برای filters, group_by, metrics)، پیش‌نمایش زنده، جدول نتایج،
  خروجی CSV از لیست و ادیتور، بارگذاری قالب.
- **۷ تست واحد** برای ReportService در `tests/unit/Modules/Reports/`.

### تغییر (Changed)

- **Installer VERSION** از `1.5.0` به `1.6.0` ارتقا یافت.
- **API client**: متدهای reports اضافه شد.

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
