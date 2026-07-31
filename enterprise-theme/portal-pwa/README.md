# ParsYar Customer Portal (PWA)

> فاز ۱.۷.۰ — پورتال سلف‌سرویس مشتریان به‌صورت Progressive Web App

## ویژگی‌ها

- **Magic Link Login** — بدون رمز عبور، فقط ایمیل
- **JWT (HS256)** — Access (1h) + Refresh (7d)، rotation
- **آفلاین‌محور** — Service Worker با Workbox (NetworkFirst برای API، StaleWhileRevalidate برای assets)
- **Push Notifications** — VAPID، subscribe/unsubscribe
- **Install Banner** — `beforeinstallprompt` capture
- **RTL + فارسی‌محور** — فونت Vazirmatn، i18n با `fa-IR` پیش‌فرض
- **Pages**: Login, Verify, Dashboard, Invoices, Orders, Payments, Tickets (CRUD)

## اجرا

```bash
cd enterprise-theme/portal-pwa
npm install
npm run dev      # http://localhost:5173 (پروکسی به WP در :8080)
npm run build    # خروجی در dist/
npm run test     # vitest
```

## پیکربندی WordPress

- Frontend باید در `/wp-content/themes/enterprise-theme/portal-pwa/dist/` بیلد شود
- (اختیاری) یک page template به نام `portal.php` در theme اصلی اضافه شود که `dist/index.html` را echo کند
- endpointهای API به‌صورت خودکار از افزونه `enterprise-core-plugin` mount می‌شوند:
  `/wp-json/enterprise/v1/portal/*`

## ساختار

```
src/
├── App.tsx               # Routes + Layout
├── main.tsx              # Entry + SW register
├── index.css             # Tailwind
├── lib/
│   ├── api.ts            # fetch client + JWT
│   ├── i18n.ts           # i18next (fa/en)
│   ├── push.ts           # WebPush helpers
│   └── format.ts         # Intl helpers
├── pages/
│   ├── LoginPage.tsx
│   ├── VerifyPage.tsx
│   ├── DashboardPage.tsx
│   ├── InvoicesPage.tsx
│   ├── OrdersPage.tsx
│   ├── PaymentsPage.tsx
│   └── TicketsPage.tsx
├── components/
│   └── Banners.tsx       # Install + Push banners
├── sw.ts                 # Service worker (push handler)
└── test/
    ├── setup.ts
    └── api.test.ts
```

## امنیت

- همهٔ endpointهای protected نیاز به `Authorization: Bearer <jwt>` دارند
- Rate limit در backend: ۱ magic link / ۲ دقیقه / ایمیل، ۵ تلاش ناموفق → ۱۰ دقیقه ban
- JWT secret در `wp_options` به‌صورت autoload=false ذخیره می‌شود
- VAPID keypair در activation ساخته می‌شود (libsodium یا fallback)
- Push فقط به endpointهای متعلق به همان contact ارسال می‌شود
