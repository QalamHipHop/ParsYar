import React, { useEffect, useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useUI } from '../store';
import { cx, formatJalali, timeAgo } from '../lib/format.js';
import Badge from './Badge.jsx';

const QUICK_LINKS = [
  { to: '/objects',          label: 'اشیاء',         group: 'اصلی' },
  { to: '/leads',            label: 'سرنخ‌ها',        group: 'کسب‌وکار' },
  { to: '/contacts',         label: 'مخاطبین',        group: 'کسب‌وکار' },
  { to: '/deals',            label: 'معاملات',        group: 'کسب‌وکار' },
  { to: '/products',         label: 'انبار',          group: 'کسب‌وکار' },
  { to: '/invoices',         label: 'فاکتورها',       group: 'کسب‌وکار' },
  { to: '/orders',           label: 'سفارش‌ها',       group: 'کسب‌وکار' },
  { to: '/payments',         label: 'پرداخت‌ها',      group: 'کسب‌وکار' },
  { to: '/accounting',       label: 'حسابداری',       group: 'عملیات' },
  { to: '/workflows',        label: 'گردش کار',       group: 'عملیات' },
  { to: '/reports',          label: 'گزارش‌ها',        group: 'عملیات' },
  { to: '/employees',        label: 'پرسنل',          group: 'عملیات' },
  { to: '/notifications',    label: 'اعلان‌ها',       group: 'سامانه' },
  { to: '/audit',            label: 'حسابرسی',        group: 'سامانه' },
  { to: '/settings',         label: 'تنظیمات',        group: 'سامانه' },
];

export default function Topbar() {
  const { themeMode, setTheme, toggleCommand, sidebarOpen, toggleSidebar } = useUI();
  const nav = useNavigate();
  const loc = useLocation();
  const [now, setNow] = useState(new Date());
  useEffect(() => {
    const t = setInterval(() => setNow(new Date()), 60_000);
    return () => clearInterval(t);
  }, []);

  return (
    <header className="sticky top-0 z-20 h-16 px-4 md:px-6 flex items-center gap-3 border-b border-ink-200/60 dark:border-white/5 bg-white/70 dark:bg-ink-950/70 backdrop-blur-2xl">
      {/* mobile menu toggle */}
      <button
        onClick={toggleSidebar}
        className="md:hidden p-2 rounded-lg text-ink-700 dark:text-ink-200 hover:bg-ink-100 dark:hover:bg-ink-900"
        aria-label="منو"
      >
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      </button>

      {/* breadcrumb */}
      <div className="hidden sm:flex items-center gap-2 text-xs text-ink-500 dark:text-ink-400">
        <span>داشبورد</span>
        <span className="text-ink-300">/</span>
        <span className="font-semibold text-ink-800 dark:text-ink-100">{titleFromPath(loc.pathname)}</span>
      </div>

      {/* search trigger */}
      <button
        onClick={toggleCommand}
        className="ms-auto flex items-center gap-2 px-3 h-9 rounded-xl border border-ink-200 dark:border-ink-800 bg-white/60 dark:bg-ink-900/60 hover:bg-white dark:hover:bg-ink-900 text-ink-500 dark:text-ink-400 transition w-72 max-w-full"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <span className="text-xs flex-1 text-start">جستجو یا دسترسی سریع...</span>
        <span className="kbd">⌘K</span>
      </button>

      {/* theme switcher */}
      <div className="flex items-center gap-1 p-1 rounded-xl border border-ink-200 dark:border-ink-800 bg-white/60 dark:bg-ink-900/60">
        {[
          { k: 'light', ic: <SunIcon /> },
          { k: 'dark',  ic: <MoonIcon /> },
          { k: 'auto',  ic: <AutoIcon /> },
        ].map(t => (
          <button
            key={t.k}
            onClick={() => setTheme(t.k)}
            className={cx(
              'p-1.5 rounded-lg transition',
              themeMode === t.k
                ? 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950'
                : 'text-ink-500 dark:text-ink-400 hover:text-ink-900 dark:hover:text-ink-50'
            )}
            title={t.k === 'auto' ? 'خودکار' : t.k === 'dark' ? 'تیره' : 'روشن'}
            aria-label={t.k}
          >
            {t.ic}
          </button>
        ))}
      </div>

      {/* date pill */}
      <div className="hidden md:flex items-center gap-2 text-[11px] text-ink-500 dark:text-ink-400 px-3 h-9 rounded-xl border border-ink-200 dark:border-ink-800 bg-white/60 dark:bg-ink-900/60">
        <span className="dot-info" />
        <span>{formatJalali(now.toISOString(), { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</span>
      </div>

      {/* user avatar */}
      <button
        onClick={() => nav('/settings')}
        className="w-9 h-9 rounded-full bg-ink-950 dark:bg-ink-50 text-white dark:text-ink-950 grid place-items-center font-bold text-sm shadow-brutal-sm hover:shadow-brutal transition"
        title="پروفایل"
      >
        م
      </button>
    </header>
  );
}

function titleFromPath(p) {
  const m = {
    '/': 'داشبورد', '/objects': 'اشیاء', '/leads': 'سرنخ‌ها',
    '/products': 'انبار', '/invoices': 'فاکتورها', '/employees': 'پرسنل',
    '/accounting': 'حسابداری', '/workflows': 'گردش کار', '/audit': 'حسابرسی',
    '/contacts': 'مخاطبین', '/deals': 'معاملات', '/orders': 'سفارش‌ها',
    '/payments': 'پرداخت‌ها', '/reports': 'گزارش‌ها', '/settings': 'تنظیمات',
    '/notifications': 'اعلان‌ها',
  };
  for (const k of Object.keys(m).sort((a,b) => b.length - a.length)) {
    if (k === '/' ? p === '/' : p.startsWith(k)) return m[k];
  }
  return '';
}

const SunIcon = () => (<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>);
const MoonIcon = () => (<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>);
const AutoIcon = () => (<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 0 0 20z" fill="currentColor"/></svg>);

// ─── Command Palette ───
export function CommandPalette() {
  const { commandPaletteOpen, closeCommand } = useUI();
  const nav = useNavigate();
  const [q, setQ] = useState('');

  useEffect(() => {
    if (!commandPaletteOpen) return;
    const onKey = (e) => { if (e.key === 'Escape') closeCommand(); };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [commandPaletteOpen, closeCommand]);

  // global shortcut
  useEffect(() => {
    const onKey = (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        useUI.getState().toggleCommand();
      }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, []);

  useEffect(() => { if (commandPaletteOpen) setQ(''); }, [commandPaletteOpen]);

  if (!commandPaletteOpen) return null;

  const norm = (s) => (s || '').toString().toLowerCase().replace(/\u200c/g, ' ').trim();
  const q2 = norm(q);
  const filtered = QUICK_LINKS.filter(it => !q2 || norm(it.label).includes(q2) || norm(it.group).includes(q2));
  const grouped = filtered.reduce((acc, it) => {
    (acc[it.group] = acc[it.group] || []).push(it);
    return acc;
  }, {});

  const go = (to) => { closeCommand(); nav(to); };

  return (
    <div className="fixed inset-0 z-[200] flex items-start justify-center pt-24 px-4 animate-fade-in" role="dialog" aria-modal="true">
      <div className="absolute inset-0 bg-ink-950/40 backdrop-blur-sm" onClick={closeCommand} />
      <div className="relative w-full max-w-xl surface-glass-strong rounded-2xl shadow-glass-lg overflow-hidden animate-scale-in">
        <div className="flex items-center gap-2.5 px-4 h-12 border-b border-ink-200/50 dark:border-white/10">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="text-ink-400"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input
            autoFocus
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="جستجو در پنل..."
            className="flex-1 bg-transparent border-0 outline-none text-sm placeholder:text-ink-400"
          />
          <span className="kbd">ESC</span>
        </div>
        <div className="max-h-[60vh] overflow-y-auto py-2">
          {Object.keys(grouped).length === 0 && (
            <div className="px-4 py-10 text-center text-sm text-ink-500">نتیجه‌ای یافت نشد.</div>
          )}
          {Object.entries(grouped).map(([group, items]) => (
            <div key={group} className="mb-1">
              <div className="px-4 py-1.5 text-[10px] uppercase tracking-widest font-bold text-ink-400">{group}</div>
              {items.map(it => (
                <button
                  key={it.to}
                  onClick={() => go(it.to)}
                  className="w-full flex items-center justify-between gap-3 px-4 py-2.5 text-sm hover:bg-ink-100/60 dark:hover:bg-ink-900/40 transition text-ink-700 dark:text-ink-200"
                >
                  <span>{it.label}</span>
                  <span className="text-[10px] text-ink-400">{it.to}</span>
                </button>
              ))}
            </div>
          ))}
        </div>
        <div className="flex items-center justify-between px-4 h-9 border-t border-ink-200/50 dark:border-white/10 text-[10px] text-ink-500">
          <div className="flex items-center gap-2">
            <span className="kbd">↑</span><span className="kbd">↓</span> پیمایش
            <span className="kbd">↵</span> انتخاب
          </div>
          <span>ParsYar · v2.0.0</span>
        </div>
      </div>
    </div>
  );
}
