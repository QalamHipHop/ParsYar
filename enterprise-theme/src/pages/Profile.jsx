/**
 * Profile — current user profile + quick links.
 * Tries /auth/me; falls back to demo data.
 */
import React, { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api } from '../api/client.js';
import Card, { CardHeader } from '../components/Card.jsx';
import Button from '../components/Button.jsx';
import Badge from '../components/Badge.jsx';
import { useToasts } from '../store';
import { formatJalali, timeAgo, cx } from '../lib/format.js';

const DEMO_USER = {
  id: 1,
  full_name: 'علی محمدی',
  email: 'ali@parsyar.dev',
  mobile: '09123456789',
  role: 'admin',
  avatar: null,
  created_at: new Date(Date.now() - 1000 * 60 * 60 * 24 * 90).toISOString(),
  last_login: new Date(Date.now() - 1000 * 60 * 30).toISOString(),
  stats: { records: 1247, leads: 84, invoices: 312 },
};

const ROLE_LABEL = {
  admin: 'مدیر کل',
  manager: 'مدیر',
  sales: 'فروش',
  support: 'پشتیبانی',
  hr: 'منابع انسانی',
  accountant: 'حسابدار',
  viewer: 'مشاهده‌گر',
};

export default function Profile() {
  const [me, setMe] = useState(null);
  const [loading, setLoading] = useState(true);
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState({});
  const push = useToasts(s => s.push);
  const nav = useNavigate();

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const r = await api.get('/auth/me').catch(() => null);
        if (cancelled) return;
        setMe(r || DEMO_USER);
        setDraft(r || DEMO_USER);
      } catch {
        if (!cancelled) {
          setMe(DEMO_USER);
          setDraft(DEMO_USER);
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, []);

  const save = async () => {
    try {
      await api.put('/auth/me', {
        full_name: draft.full_name,
        email: draft.email,
        mobile: draft.mobile,
      }).catch(() => {});
      setMe(draft);
      setEditing(false);
      push({ type: 'success', message: 'پروفایل به‌روزرسانی شد' });
    } catch (e) {
      push({ type: 'error', message: e.message });
    }
  };

  const logout = async () => {
    if (!confirm('خروج از حساب کاربری؟')) return;
    try { await api.post('/auth/logout', {}); } catch {}
    try {
      // clear any local session-like keys
      Object.keys(localStorage)
        .filter((k) => k.startsWith('parsyar:'))
        .forEach((k) => localStorage.removeItem(k));
    } catch {}
    push({ type: 'info', message: 'خارج شدید' });
    nav('/login', { replace: true });
  };

  if (loading) {
    return (
      <div className="space-y-3 animate-fade-in">
        <div className="skeleton h-32 rounded-2xl" />
        <div className="skeleton h-48 rounded-2xl" />
      </div>
    );
  }

  if (!me) {
    return <div className="text-center py-12 text-ink-500">خطا در بارگذاری پروفایل</div>;
  }

  const initials = (me.full_name || me.email || '?').trim().slice(0, 1).toUpperCase();

  return (
    <div className="space-y-6 animate-fade-in">
      <div>
        <p className="text-xs font-bold tracking-widest uppercase text-ink-500 dark:text-ink-400">
          سامانه
        </p>
        <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight text-ink-900 dark:text-ink-50 mt-1">
          پروفایل
        </h1>
      </div>

      {/* Hero card */}
      <Card variant="glass-strong">
        <div className="flex flex-wrap items-center gap-4">
          <div className="w-20 h-20 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-white grid place-items-center text-2xl font-extrabold shadow-glow">
            {initials}
          </div>
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-2 flex-wrap">
              <h2 className="text-xl font-extrabold tracking-tight text-ink-900 dark:text-ink-50">
                {me.full_name}
              </h2>
              {me.role && (
                <Badge variant="brand">{ROLE_LABEL[me.role] || me.role}</Badge>
              )}
            </div>
            <p className="text-sm text-ink-500 dark:text-ink-400 mt-1 ltr-num">{me.email}</p>
            {me.mobile && <p className="text-xs text-ink-500 dark:text-ink-400 ltr-num">{me.mobile}</p>}
          </div>
          <div className="flex gap-2">
            {!editing ? (
              <Button variant="secondary" size="sm" onClick={() => setEditing(true)}>ویرایش</Button>
            ) : (
              <>
                <Button variant="ghost" size="sm" onClick={() => { setEditing(false); setDraft(me); }}>انصراف</Button>
                <Button variant="primary" size="sm" onClick={save}>ذخیره</Button>
              </>
            )}
          </div>
        </div>
      </Card>

      {/* Editable fields / view */}
      {editing ? (
        <Card variant="glass">
          <CardHeader title="ویرایش اطلاعات" />
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-semibold mb-1">نام کامل</label>
              <input
                className="w-full rounded-lg border border-ink-200 dark:border-ink-700 bg-white dark:bg-ink-900 px-3 py-2 text-sm"
                value={draft.full_name || ''}
                onChange={(e) => setDraft({ ...draft, full_name: e.target.value })}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1">ایمیل</label>
              <input
                className="w-full rounded-lg border border-ink-200 dark:border-ink-700 bg-white dark:bg-ink-900 px-3 py-2 text-sm ltr-num"
                value={draft.email || ''}
                onChange={(e) => setDraft({ ...draft, email: e.target.value })}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1">موبایل</label>
              <input
                className="w-full rounded-lg border border-ink-200 dark:border-ink-700 bg-white dark:bg-ink-900 px-3 py-2 text-sm ltr-num"
                value={draft.mobile || ''}
                onChange={(e) => setDraft({ ...draft, mobile: e.target.value })}
              />
            </div>
          </div>
        </Card>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
          <Card variant="glass">
            <div className="text-xs text-ink-500">عضویت از</div>
            <div className="text-sm font-bold mt-1">{formatJalali(me.created_at)}</div>
            <div className="text-[11px] text-ink-400 mt-0.5">{timeAgo(me.created_at)}</div>
          </Card>
          <Card variant="glass">
            <div className="text-xs text-ink-500">آخرین ورود</div>
            <div className="text-sm font-bold mt-1">{formatJalali(me.last_login)}</div>
            <div className="text-[11px] text-ink-400 mt-0.5">{timeAgo(me.last_login)}</div>
          </Card>
          <Card variant="glass">
            <div className="text-xs text-ink-500">رکوردهای ایجادشده</div>
            <div className="text-sm font-bold mt-1 ltr-num">{me.stats?.records?.toLocaleString('fa-IR') || '—'}</div>
            <div className="text-[11px] text-ink-400 mt-0.5">در کل سامانه</div>
          </Card>
        </div>
      )}

      {/* Quick links */}
      <Card variant="glass">
        <CardHeader title="دسترسی سریع" />
        <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
          {[
            { to: '/settings',      l: 'تنظیمات',   ic: 'تنظیمات' },
            { to: '/notifications', l: 'اعلان‌ها',  ic: 'اعلان‌ها' },
            { to: '/wizard',        l: 'ویزارد نصب', ic: '🪄' },
            { to: '/audit',         l: 'حسابرسی',   ic: 'امنیت' },
          ].map((q) => (
            <Link
              key={q.to}
              to={q.to}
              className="rounded-xl border border-ink-200 dark:border-ink-800 p-3 hover:bg-ink-50 dark:hover:bg-ink-900 transition text-center"
            >
              <div className="text-2xl">{q.ic}</div>
              <div className="text-xs font-semibold mt-1">{q.l}</div>
            </Link>
          ))}
        </div>
      </Card>

      <Card variant="brutal" className="border-danger-500">
        <CardHeader title="خروج از حساب" subtitle="پایان جلسهٔ کاری فعلی" />
        <Button variant="danger" onClick={logout}>خروج</Button>
      </Card>
    </div>
  );
}
