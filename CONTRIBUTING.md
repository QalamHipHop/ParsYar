# Contributing to ParsYar

سلام! خوشحالیم که می‌خوای در توسعهٔ ParsYar سهیم بشی. این سند قرارداد مشارکت ماست.

## قرارداد نام‌گذاری شاخه‌ها

| پیشوند | کاربرد | مثال |
|--------|--------|------|
| `feat/` | قابلیت جدید | `feat/contacts-duplicate-detection` |
| `fix/` | رفع باگ | `fix/jalali-leap-year-1403` |
| `refactor/` | بازنویسی بدون تغییر رفتار | `refactor/ledger-transaction-builder` |
| `perf/` | بهبود عملکرد | `perf/index-people-organization` |
| `docs/` | فقط مستندات | `docs/iranian-compliance` |
| `test/` | فقط تست | `test/validators-sheba` |
| `chore/` | ابزارسازی / CI / build | `chore/upgrade-vite-5` |

## قرارداد کامیت‌ها (Conventional Commits)

```
<type>(<scope>): <subject> [TICKET-123]

<body — اختیاری، ۷۲ کاراکتر در هر خط>

<footer — اختیاری، ارجاع به issue>
```

- **type** همان پیشوند بالا بدون `/` است.
- **scope** نام ماژول است (`crm`, `accounting`, `tax`, `theme`, `wizard`, …).
- **subject** به فارسی یا انگلیسی، بدون نقطه در انتها، امری.
- بدنهٔ کامیت باید **چرا** را توضیح دهد، نه **چه** (کد خودش چه را نشان می‌دهد).

مثال‌ها:

```
feat(crm): افزودن تشخیص مخاطب تکراری با Jaro-Winkler
fix(tax): اصلاح امضای دیجیتال صورتحساب الکترونیکی مؤدیان
perf(ledger): ایندکس ترکیبی (company_id, fiscal_year, posted_at)
docs(readme): افزودن بخش نصب اوبونتو ۲۲.۰۴
```

## قانون طلایی PR

1. **هر PR یک هدف.** اگه دو کار بی‌ربط داری، دو PR بزن.
2. **بدون TODO در کد.** اگه ناقصه، issue باز کن و TODO ممنوع.
3. **تست‌ها باید سبز باشن** قبل از درخواست بازبینی.
4. **PHP** با PSR-12 + WordPress-Extra (sniffer ما در CI اجرا می‌شود).
5. **JS/React** با ESLint تنظیم WordPress + Prettier.
6. **دسترسی‌ها** همیشه با `current_user_can()` و `wp_nonce_field()` محافظت می‌شود.
7. **ایمنی داده**: تمامی کوئری‌ها با `$wpdb->prepare()`، تمامی خروجی‌ها با توابع escape مناسب.
8. **متن‌های فارسی** همیشه در `__()` / `esc_html__()` / `_x()` پیچیده می‌شوند.

## ساختار تست

- `tests/unit/` — PHPUnit برای PHP.
- `tests/integration/` — WP-CLI + Brain Monkey.
- `tests/e2e/` — Playwright برای تم.

```bash
# اجرای همهٔ تست‌ها
composer test
npm test
```

## فرآیند بازبینی

- حداقل **یک تأیید** از مالک ماژول.
- برای تغییرات `db/schema*` یا `modules/tax/*` باید **دو تأیید** داشته باشی.
- ربات CI موظف است: lint، test، security scan، و compatibility check (PHP 8.1+ / WP 6.5+) را اجرا کند.

## مسائل اخلاقی

- هیچ دادهٔ واقعی مشتری در ریپو قرار نگیرد. دمو دیتا استفاده شود.
- هیچ کلید API واقعی commit نشود. از `.env.example` الگو بگیر.

## تماس

- Matrix: `#parsyar:matrix.org`
- ایمیل: `qalam@parsyar.dev`

— سپاس از مشارکت تو تیره
