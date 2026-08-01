/**
 * Settings — system-wide preferences.
 * Tabs: عمومی (theme, locale, fiscal), اعلان‌ها (channels), امنیت (2FA, password, IP allowlist), داده‌ها (import/export, danger zone).
 */
import React, { useEffect, useState } from 'react';
import { api } from '../api/client.js';
import Card, { CardHeader } from '../components/Card.jsx';
import Button from '../components/Button.jsx';
import Badge from '../components/Badge.jsx';
import Input, { Switch, Select } from '../components/Input.jsx';
import { useToasts, useUI } from '../store';
import { cx } from '../lib/format.js';

const TABS = [
  { k: 'general',    l: 'عمومی',          icon: 'تنظیمات' },
  { k: 'localization', l: 'بومی‌سازی',     icon: 'بومی‌سازی' },
  { k: 'notifications', l: 'اعلان‌ها',    icon: 'اعلان‌ها' },
  { k: 'security',   l: 'امنیت',          icon: 'امنیت' },
  { k: 'data',       l: 'داده‌ها',        icon: 'داده‌ها' },
];

export default function Settings() {
  const [tab, setTab] = useState('general');
  const { themeMode, setTheme } = useUI();
  const push = useToasts(s => s.push);

  return (
    <div className="space-y-6 animate-fade-in">
      <div>
        <p className="text-xs font-bold tracking-widest uppercase text-ink-500 dark:text-ink-400">
          سامانه
        </p>
        <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight text-ink-900 dark:text-ink-50 mt-1">
          تنظیمات
        </h1>
      </div>

      <div className="flex flex-wrap gap-2 border-b border-ink-200/60 dark:border-ink-800">
        {TABS.map((t) => (
          <button
            key={t.k}
            onClick={() => setTab(t.k)}
            className={cx(
              'px-4 py-2 text-sm font-medium rounded-t-xl transition',
              tab === t.k
                ? 'bg-white dark:bg-ink-900 text-ink-900 dark:text-ink-50 border-x border-t border-ink-200/60 dark:border-ink-800'
                : 'text-ink-500 dark:text-ink-400 hover:text-ink-900 dark:hover:text-ink-50'
            )}
          >
            <span className="ms-2">{t.icon}</span>
            {t.l}
          </button>
        ))}
      </div>

      {tab === 'general'    && <GeneralTab themeMode={themeMode} setTheme={setTheme} />}
      {tab === 'localization' && <LocalizationTab />}
      {tab === 'notifications' && <NotificationsTab />}
      {tab === 'security'   && <SecurityTab />}
      {tab === 'data'       && <DataTab />}
    </div>
  );
}

function GeneralTab({ themeMode, setTheme }) {
  return (
    <Card variant="glass">
      <CardHeader title="ظاهر" subtitle="تم و حالت نمایش" />
      <div className="grid grid-cols-1 md:grid-cols-3 gap-2">
        {[
          { k: 'light', l: 'روشن',    ic: 'روشن' },
          { k: 'dark',  l: 'تیره',     ic: 'تیره' },
          { k: 'auto',  l: 'خودکار',  ic: 'خودکار' },
        ].map((t) => (
          <button
            key={t.k}
            onClick={() => setTheme(t.k)}
            className={cx(
              'rounded-xl border-2 p-4 text-center transition',
              themeMode === t.k
                ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/10'
                : 'border-ink-200 dark:border-ink-800 hover:border-ink-400'
            )}
          >
            <div className="text-2xl">{t.ic}</div>
            <div className="mt-1 text-sm font-semibold">{t.l}</div>
          </button>
        ))}
      </div>
    </Card>
  );
}

function LocalizationTab() {
  return (
    <Card variant="glass">
      <CardHeader title="بومی‌سازی" subtitle="زبان، تقویم، و واحد پول" />
      <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
        <Select
          label="زبان پیش‌فرض"
          value="fa-IR"
          options={[
            { value: 'fa-IR', label: 'فارسی (ایران)' },
            { value: 'en-US', label: 'English (US)' },
            { value: 'ar-SA', label: 'العربية' },
            { value: 'ru-RU', label: 'Русский' },
          ]}
        />
        <Select
          label="تقویم"
          value="jalali"
          options={[
            { value: 'jalali',   label: 'شمسی (جلالی)' },
            { value: 'gregorian', label: 'میلادی' },
          ]}
        />
        <Select
          label="واحد پول اصلی"
          value="IRT"
          options={[
            { value: 'IRT', label: 'تومان' },
            { value: 'IRR', label: 'ریال' },
            { value: 'USD', label: 'دلار' },
            { value: 'EUR', label: 'یورو' },
          ]}
        />
        <Select
          label="اولین روز هفته"
          value="saturday"
          options={[
            { value: 'saturday', label: 'شنبه' },
            { value: 'sunday',   label: 'یکشنبه' },
            { value: 'monday',   label: 'دوشنبه' },
          ]}
        />
      </div>
    </Card>
  );
}

function NotificationsTab() {
  const [email, setEmail] = useState(true);
  const [sms, setSms]     = useState(false);
  const [push, setPush]   = useState(true);
  return (
    <Card variant="glass">
      <CardHeader title="کانال‌های اعلان" />
      <div className="space-y-2">
        <Switch label="ایمیل" checked={email} onChange={(e) => setEmail(e.target.checked)} />
        <Switch label="پیامک" checked={sms}   onChange={(e) => setSms(e.target.checked)} />
        <Switch label="وب پوش" checked={push} onChange={(e) => setPush(e.target.checked)} />
      </div>
    </Card>
  );
}

function SecurityTab() {
  return (
    <Card variant="glass">
      <CardHeader title="امنیت" subtitle="احراز هویت دو مرحله‌ای، IP allowlist، و سیاست رمز عبور" />
      <div className="space-y-2">
        <Switch label="اجبار به ورود دو مرحله‌ای (2FA)" checked={false} onChange={() => {}} />
        <Switch label="IP allowlist فعال" checked={false} onChange={() => {}} />
        <Switch label="نگهداری گزارش حسابرسی 365 روز" checked={true} onChange={() => {}} />
      </div>
    </Card>
  );
}

function DataTab() {
  const [busy, setBusy] = useState(false);
  const push = useToasts(s => s.push);
  return (
    <>
      <Card variant="glass">
        <CardHeader title="پشتیبان‌گیری" subtitle="خروجی JSON از پیکربندی و شئی‌ها" />
        <Button
          loading={busy}
          onClick={async () => {
            setBusy(true);
            try {
              const r = await fetch('/wp-json/parsyar/v1/backup/export', {
                headers: { 'X-WP-Nonce': window.EnterpriseConfig?.nonce || '' },
                credentials: 'include',
              });
              if (!r.ok) throw new Error('HTTP ' + r.status);
              const blob = await r.blob();
              const url = URL.createObjectURL(blob);
              const a = document.createElement('a');
              a.href = url;
              a.download = `parsyar-backup-${new Date().toISOString().slice(0, 10)}.json`;
              a.click();
              URL.revokeObjectURL(url);
              push({ type: 'success', message: 'پشتیبان دانلود شد' });
            } catch (e) {
              push({ type: 'error', message: e.message });
            } finally {
              setBusy(false);
            }
          }}
        >
          دانلود پشتیبان
        </Button>
      </Card>

      <Card variant="brutal" className="border-danger-500">
        <CardHeader title="منطقه خطر" subtitle="عملیات‌های غیرقابل بازگشت" />
        <p className="text-sm text-ink-600 dark:text-ink-300 mb-3">
          پاک کردن کش، تمام داده‌های ذخیره‌شده در حافظهٔ موقت را حذف می‌کند.
        </p>
        <Button
          variant="danger"
          onClick={async () => {
            try {
              await api.post('/cache/flush', {});
              push({ type: 'success', message: 'کش پاک شد' });
            } catch (e) {
              push({ type: 'error', message: e.message });
            }
          }}
        >
          پاک کردن کش
        </Button>
      </Card>
    </>
  );
}
