/**
 * WizardLauncher — entry point for the 23-step setup wizard from inside the SPA.
 * Shows steps, current progress, and a CTA to open the full wizard in a new tab.
 *
 * Note: the wizard itself lives in WP admin (`admin.php?page=parsyar-wizard`).
 * This page is the SPA's polished shortcut to it.
 */
import React, { useEffect, useState } from 'react';
import Card, { CardHeader } from '../components/Card.jsx';
import Button from '../components/Button.jsx';
import Badge from '../components/Badge.jsx';
import { api } from '../api/client.js';
import { useToasts } from '../store';
import { cx, formatJalali } from '../lib/format.js';

const STEPS = [
  { n: 1,  l: 'خوش‌آمدگویی',          d: 'بررسی سیستم و سلامت محیط',     grp: 'آماده‌سازی' },
  { n: 2,  l: 'زبان و منطقهٔ زمانی',   d: 'FA/EN/AR/RU + تقویم',           grp: 'آماده‌سازی' },
  { n: 3,  l: 'پروفایل سازمان',         d: 'شناسه ملی، کد اقتصادی، لوگو',  grp: 'سازمان' },
  { n: 4,  l: 'شرکت‌های چندگانه',       d: 'فعال‌سازی حالت هلدینگ',        grp: 'سازمان' },
  { n: 5,  l: 'شعب و دپارتمان‌ها',      d: 'ساختار شعب',                    grp: 'سازمان' },
  { n: 6,  l: 'ارزها و نرخ تبدیل',      d: 'IRT/IRR/USD/EUR/AED/TRY',       grp: 'مالی' },
  { n: 7,  l: 'سال مالی',                d: 'ایرانی/میلادی/سفارشی',         grp: 'مالی' },
  { n: 8,  l: 'تنظیمات تقویم شمسی',     d: 'الگوریتم ۲۸۲۰ یا ۳۳ ساله',     grp: 'مالی' },
  { n: 9,  l: 'خطوط فروش',              d: '۶ مرحلهٔ پیش‌فرض',              grp: 'فروش' },
  { n: 10, l: 'مالیات و عوارض',         d: 'VAT ۱۰٪ + معافیت‌ها',           grp: 'فروش' },
  { n: 11, l: 'ماژول‌ها',                d: 'فعال/غیرفعال‌سازی',             grp: 'عملیات' },
  { n: 12, l: 'کاربران و نقش‌ها',       d: '۹ نقش پیش‌فرض',                 grp: 'عملیات' },
  { n: 13, l: 'کانال‌های اعلان',        d: 'SMTP + SMS + Push + FCM',       grp: 'عملیات' },
  { n: 14, l: 'درگاه‌های پرداخت',       d: '۸ درگاه ایرانی',                grp: 'عملیات' },
  { n: 15, l: 'یکپارچگی‌های ایرانی',    d: 'مأندیان، شاپرک، جیبیت، ...',  grp: 'عملیات' },
  { n: 16, l: 'فروشگاه اینترنتی',       d: 'پل WooCommerce (اختیاری)',      grp: 'عملیات' },
  { n: 17, l: 'ورود داده',              d: 'CSV/Excel با نگاشت ستون‌ها',    grp: 'داده' },
  { n: 18, l: 'دادهٔ نمونه',            d: 'سرنخ، محصول، فاکتور، پرسنل',   grp: 'داده' },
  { n: 19, l: 'قالب و برندینگ',         d: 'لوگو، رنگ، فونت',               grp: 'ظاهر' },
  { n: 20, l: 'دستیار هوشمند',      d: 'سرویس‌های خارجی',     grp: 'هوش' },
  { n: 21, l: 'امنیت',                  d: '۲FA + IP allowlist',            grp: 'امنیت' },
  { n: 22, l: 'پشتیبان‌گیری و Webhook', d: 'زمانبندی + امضای HMAC',         grp: 'امنیت' },
  { n: 23, l: 'پایان',                  d: 'خلاصه و پرش به داشبورد',        grp: 'پایان' },
];

export default function WizardLauncher() {
  const [progress, setProgress] = useState(null);
  const [loading, setLoading] = useState(true);
  const push = useToasts(s => s.push);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const r = await api.get('/wizard/state').catch(() => null);
        if (cancelled) return;
        setProgress(r || { current: 1, completed: [], last_run: null });
      } catch {
        if (!cancelled) setProgress({ current: 1, completed: [], last_run: null });
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, []);

  const openWizard = (step = 1) => {
    const url = `/wp-admin/admin.php?page=parsyar-wizard&step=${step}`;
    window.open(url, '_blank', 'noopener');
  };

  const markStepDone = (n) => {
    setProgress((p) => ({
      ...p,
      completed: Array.from(new Set([...(p?.completed || []), n])),
      current: Math.max(p?.current || 1, n + 1),
    }));
    api.post('/wizard/state', { current: n + 1, completed_step: n }).catch(() => {});
    push({ type: 'success', message: `مرحلهٔ ${n} علامت‌گذاری شد` });
  };

  const current  = progress?.current || 1;
  const done     = progress?.completed || [];
  const doneCount = done.length;
  const pct = Math.round((doneCount / STEPS.length) * 100);

  const groups = STEPS.reduce((acc, s) => {
    (acc[s.grp] = acc[s.grp] || []).push(s);
    return acc;
  }, {});

  return (
    <div className="space-y-6 animate-fade-in">
      <div>
        <p className="text-xs font-bold tracking-widest uppercase text-ink-500 dark:text-ink-400">
          عملیات
        </p>
        <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight text-ink-900 dark:text-ink-50 mt-1">
          ویزارد نصب پارس‌یار
        </h1>
        <p className="text-sm text-ink-500 dark:text-ink-400 mt-1">
          ۲۳ مرحلهٔ ساده برای راه‌اندازی کامل سیستم. هر زمان خواستید می‌توانید متوقف و بعداً ادامه دهید.
        </p>
      </div>

      <Card variant="brutal" className="border-brand-500">
        <div className="flex flex-wrap items-center gap-4">
          <div className="text-5xl">🪄</div>
          <div className="flex-1 min-w-0">
            <div className="text-xs text-ink-500 font-bold tracking-wider">پیشرفت</div>
            <div className="text-2xl font-extrabold text-ink-900 dark:text-ink-50">
              {englishToPersianDigits(doneCount)} از {englishToPersianDigits(STEPS.length)} مرحله
              <span className="text-sm font-normal text-ink-500 dark:text-ink-400 ms-2">({englishToPersianDigits(pct)}٪)</span>
            </div>
            <div className="mt-2 h-2 rounded-full bg-ink-200 dark:bg-ink-800 overflow-hidden">
              <div className="h-full bg-brand-500 transition-all" style={{ width: `${pct}%` }} />
            </div>
          </div>
          <Button variant="primary" onClick={() => openWizard(current)}>
            ادامه از مرحلهٔ {englishToPersianDigits(current)}
          </Button>
        </div>
      </Card>

      {Object.entries(groups).map(([grp, steps]) => (
        <Card key={grp} variant="glass">
          <CardHeader
            title={grp}
            subtitle={`${steps.filter((s) => done.includes(s.n)).length} / ${steps.length} تمام‌شده`}
          />
          <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
            {steps.map((s) => {
              const isDone = done.includes(s.n);
              const isCurrent = s.n === current;
              return (
                <div
                  key={s.n}
                  className={cx(
                    'rounded-xl border p-3 flex items-center gap-3 transition',
                    isDone
                      ? 'border-success-300 bg-success-50/40 dark:bg-success-500/5'
                      : isCurrent
                      ? 'border-brand-500 bg-brand-50/50 dark:bg-brand-500/10'
                      : 'border-ink-200 dark:border-ink-800'
                  )}
                >
                  <div
                    className={cx(
                      'w-9 h-9 rounded-lg grid place-items-center font-bold text-sm flex-shrink-0',
                      isDone
                        ? 'bg-success-500 text-white'
                        : isCurrent
                        ? 'bg-brand-500 text-white'
                        : 'bg-ink-200 dark:bg-ink-800 text-ink-600 dark:text-ink-300'
                    )}
                  >
                    {isDone ? 'v' : englishToPersianDigits(s.n)}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="text-sm font-bold truncate">{s.l}</div>
                    <div className="text-[11px] text-ink-500 dark:text-ink-400 truncate">{s.d}</div>
                  </div>
                  <div className="flex gap-1 flex-shrink-0">
                    {!isDone && (
                      <button
                        onClick={() => markStepDone(s.n)}
                        className="text-[11px] px-2 py-1 rounded-md border border-ink-200 dark:border-ink-700 hover:bg-ink-50 dark:hover:bg-ink-900"
                        title="علامت‌گذاری به‌عنوان انجام‌شده"
                      >
                        v
                      </button>
                    )}
                    <button
                      onClick={() => openWizard(s.n)}
                      className="text-[11px] px-2 py-1 rounded-md bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950"
                    >
                      شروع
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        </Card>
      ))}

      {progress?.last_run && (
        <p className="text-xs text-ink-400 text-center">
          آخرین اجرا: {formatJalali(progress.last_run)}
        </p>
      )}
    </div>
  );
}

function englishToPersianDigits(s) {
  return String(s).replace(/[0-9]/g, (c) => '۰۱۲۳۴۵۶۷۸۹'[parseInt(c, 10)]);
}
