/**
 * Notifications — inbox of in-app notifications.
 * Mark read, clear, filter by type. Falls back to demo data if API returns empty.
 */
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { api } from '../api/client.js';
import Card from '../components/Card.jsx';
import Button from '../components/Button.jsx';
import Badge from '../components/Badge.jsx';
import { useToasts } from '../store';
import { cx, timeAgo, formatJalali } from '../lib/format.js';

const DEMO_NOTIFS = [
  { id: 1, type: 'invoice', title: 'فاکتور شماره INV-1024 صادر شد', body: 'فاکتور فروش برای مشتری «شرکت آلفا» صادر شد.', created_at: new Date(Date.now() - 1000 * 60 * 5).toISOString(), read_at: null, url: '/invoices' },
  { id: 2, type: 'lead',    title: 'سرنخ جدید از وب‌سایت', body: 'آقای رضایی فرم تماس را پر کرد.', created_at: new Date(Date.now() - 1000 * 60 * 30).toISOString(), read_at: null, url: '/leads' },
  { id: 3, type: 'workflow', title: 'گردش کار اجرا شد', body: 'گردش کار «پیگیری فروش» با موفقیت اجرا شد.', created_at: new Date(Date.now() - 1000 * 60 * 60 * 2).toISOString(), read_at: new Date().toISOString(), url: '/workflows' },
  { id: 4, type: 'ticket',  title: 'تیکت جدید از مشتری', body: 'مشتری «نگین» یک تیکت پشتیبانی ثبت کرد.', created_at: new Date(Date.now() - 1000 * 60 * 60 * 8).toISOString(), read_at: null, url: '/workflows' },
  { id: 5, type: 'system',  title: 'به‌روزرسانی سیستم', body: 'نسخه 2.1.0 با ۵ بهبود منتشر شد.', created_at: new Date(Date.now() - 1000 * 60 * 60 * 24).toISOString(), read_at: new Date().toISOString(), url: null },
];

const TYPE_META = {
  invoice:  { v: 'warning', ic: '🧾' },
  lead:     { v: 'info',    ic: '🧲' },
  workflow: { v: 'success', ic: '⚡' },
  ticket:   { v: 'brand',   ic: '🎫' },
  system:   { v: 'default', ic: '⚙️' },
};

export default function Notifications() {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all');
  const push = useToasts(s => s.push);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const r = await api.get('/notifications').catch(() => null);
      const list = Array.isArray(r) ? r : (r?.data || []);
      setItems(list.length ? list : DEMO_NOTIFS);
    } catch {
      setItems(DEMO_NOTIFS);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const markRead = (id) => {
    setItems((arr) => arr.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n)));
    api.post(`/notifications/${id}/read`, {}).catch(() => {});
  };
  const markAllRead = () => {
    const now = new Date().toISOString();
    setItems((arr) => arr.map((n) => ({ ...n, read_at: n.read_at || now })));
    api.post('/notifications/read-all', {}).catch(() => {});
    push({ type: 'success', message: 'همه اعلان‌ها خوانده شد' });
  };
  const remove = (id) => setItems((arr) => arr.filter((n) => n.id !== id));
  const clearAll = () => {
    if (!confirm('تمام اعلان‌ها پاک شود؟')) return;
    setItems([]);
    api.post('/notifications/clear', {}).catch(() => {});
  };

  const filtered = useMemo(() => {
    if (filter === 'all') return items;
    if (filter === 'unread') return items.filter((n) => !n.read_at);
    return items.filter((n) => n.type === filter);
  }, [items, filter]);

  const counts = useMemo(() => {
    const c = { all: items.length, unread: items.filter((n) => !n.read_at).length };
    items.forEach((n) => { c[n.type] = (c[n.type] || 0) + 1; });
    return c;
  }, [items]);

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p className="text-xs font-bold tracking-widest uppercase text-ink-500 dark:text-ink-400">
            سامانه
          </p>
          <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight text-ink-900 dark:text-ink-50 mt-1">
            اعلان‌ها
            {counts.unread > 0 && (
              <Badge variant="danger" className="ms-3 align-middle">{counts.unread} خوانده‌نشده</Badge>
            )}
          </h1>
        </div>
        <div className="flex gap-2">
          <Button variant="ghost" size="sm" onClick={load}>بروزرسانی</Button>
          <Button variant="secondary" size="sm" onClick={markAllRead}>خواندن همه</Button>
          <Button variant="danger" size="sm" onClick={clearAll}>پاک کردن</Button>
        </div>
      </div>

      {/* filters */}
      <div className="flex flex-wrap gap-2">
        {[
          { k: 'all',      l: 'همه' },
          { k: 'unread',   l: 'خوانده‌نشده' },
          { k: 'invoice',  l: '🧾 فاکتور' },
          { k: 'lead',     l: '🧲 سرنخ' },
          { k: 'workflow', l: '⚡ گردش کار' },
          { k: 'ticket',   l: '🎫 تیکت' },
          { k: 'system',   l: '⚙️ سیستم' },
        ].map((f) => (
          <button
            key={f.k}
            onClick={() => setFilter(f.k)}
            className={cx(
              'px-3 py-1.5 text-xs font-semibold rounded-full transition',
              filter === f.k
                ? 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950'
                : 'bg-white/60 dark:bg-ink-900/60 text-ink-700 dark:text-ink-300 border border-ink-200 dark:border-ink-800'
            )}
          >
            {f.l}
            {counts[f.k] != null && <span className="ms-1.5 opacity-60">{counts[f.k]}</span>}
          </button>
        ))}
      </div>

      {loading ? (
        <div className="space-y-2">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="skeleton h-16 rounded-2xl" />
          ))}
        </div>
      ) : filtered.length === 0 ? (
        <Card variant="glass" className="text-center py-12">
          <div className="text-4xl opacity-30">🔕</div>
          <p className="mt-2 text-sm text-ink-500 dark:text-ink-400">اعلانی برای نمایش وجود ندارد.</p>
        </Card>
      ) : (
        <div className="space-y-2">
          {filtered.map((n) => {
            const meta = TYPE_META[n.type] || TYPE_META.system;
            const isUnread = !n.read_at;
            return (
              <div
                key={n.id}
                onClick={() => isUnread && markRead(n.id)}
                className={cx(
                  'rounded-2xl border p-3 flex items-start gap-3 transition cursor-pointer',
                  isUnread
                    ? 'border-brand-300 dark:border-brand-700 bg-brand-50/40 dark:bg-brand-500/5'
                    : 'border-ink-200/60 dark:border-ink-800 bg-white/60 dark:bg-ink-900/40'
                )}
              >
                <div className="text-2xl select-none flex-shrink-0">{meta.ic}</div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2">
                    {isUnread && <span className="w-2 h-2 rounded-full bg-brand-500 flex-shrink-0" />}
                    <span className={cx('text-sm font-bold', isUnread ? 'text-ink-900 dark:text-ink-50' : 'text-ink-700 dark:text-ink-300')}>
                      {n.title}
                    </span>
                    <Badge variant={meta.v} className="ms-auto">{n.type}</Badge>
                  </div>
                  {n.body && <p className="text-xs text-ink-600 dark:text-ink-400 mt-1">{n.body}</p>}
                  <div className="text-[11px] text-ink-400 dark:text-ink-500 mt-1">{timeAgo(n.created_at)}</div>
                </div>
                <button
                  onClick={(e) => { e.stopPropagation(); remove(n.id); }}
                  className="text-ink-400 hover:text-danger-500 px-2"
                  aria-label="حذف"
                >✕</button>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
