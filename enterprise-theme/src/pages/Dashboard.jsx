import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client.js';
import { formatMoney, formatJalali, formatJalaliShort, timeAgo, englishToPersian, cx } from '../lib/format.js';
import { useToasts } from '../store';
import Card, { CardHeader } from '../components/Card.jsx';
import Button from '../components/Button.jsx';
import Badge from '../components/Badge.jsx';

const STATS = [
  { key: 'objects',   label: 'اشیاء تعریف‌شده', icon: CubeIcon,        href: '/objects',         tone: 'brand' },
  { key: 'leads',     label: 'سرنخ‌ها',          icon: FunnelIcon,      href: '/leads',           tone: 'warning' },
  { key: 'contacts',  label: 'مخاطبین',          icon: UsersIcon,       href: '/contacts',        tone: 'info' },
  { key: 'deals',     label: 'معاملات فعال',     icon: HandshakeIcon,   href: '/deals',           tone: 'success' },
  { key: 'products',  label: 'محصولات',          icon: BoxIcon,         href: '/products',        tone: 'brand' },
  { key: 'invoices',  label: 'فاکتورها',         icon: ReceiptIcon,     href: '/invoices',        tone: 'warning' },
  { key: 'orders',    label: 'سفارش‌ها',         icon: CartIcon,        href: '/orders',          tone: 'info' },
  { key: 'employees', label: 'پرسنل',            icon: IdIcon,          href: '/employees',       tone: 'success' },
];

export default function Dashboard() {
  const [stats, setStats] = useState(null);
  const [recentInvoices, setRecentInvoices] = useState([]);
  const [recentLeads, setRecentLeads] = useState([]);
  const [workflowStats, setWorkflowStats] = useState(null);
  const [trialBalance, setTrialBalance] = useState(null);
  const [loading, setLoading] = useState(true);
  const push = useToasts(s => s.push);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const [objs, leads, contacts, deals, prods, invs, orders, emps, wfs, tb] = await Promise.all([
          api.objects().catch(() => []),
          api.leads().catch(() => []),
          api.contacts().catch(() => []),
          api.deals().catch(() => []),
          api.products().catch(() => []),
          api.invoices().catch(() => []),
          api.orders().catch(() => []),
          api.employees().catch(() => []),
          api.workflowStats().catch(() => null),
          api.trialBalance().catch(() => null),
        ]);
        if (cancelled) return;
        setStats({
          objects:   Array.isArray(objs)   ? objs.length   : 0,
          leads:     Array.isArray(leads)   ? leads.length  : 0,
          contacts:  Array.isArray(contacts)? contacts.length : 0,
          deals:     Array.isArray(deals)   ? deals.length  : 0,
          products:  Array.isArray(prods)   ? prods.length  : 0,
          invoices:  Array.isArray(invs)    ? invs.length   : 0,
          orders:    Array.isArray(orders)  ? orders.length : 0,
          employees: Array.isArray(emps)    ? emps.length   : 0,
        });
        setRecentInvoices(Array.isArray(invs) ? invs.slice(0, 5) : []);
        setRecentLeads(Array.isArray(leads) ? leads.slice(0, 5) : []);
        setWorkflowStats(wfs);
        setTrialBalance(tb);
      } catch (e) {
        push({ type: 'error', message: 'بارگذاری داشبورد با خطا مواجه شد: ' + e.message });
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, [push]);

  if (loading) {
    return <DashboardSkeleton />;
  }

  return (
    <div className="space-y-6 animate-fade-in">
      {/* Header */}
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p className="text-xs font-bold tracking-widest uppercase text-ink-500 dark:text-ink-400">داشبورد</p>
          <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight text-ink-900 dark:text-ink-50 mt-1">
            خوش آمدید 👋
          </h1>
          <p className="text-sm text-ink-500 dark:text-ink-400 mt-1">
            نمایی کلی از عملکرد سازمان شما در <span className="font-semibold text-ink-700 dark:text-ink-200">{formatJalali(new Date().toISOString(), { year: 'numeric', month: 'long' })}</span>
          </p>
        </div>
        <div className="flex gap-2">
          <Link to="/wizard"><Button variant="secondary" size="sm" icon={<SettingsIcon className="w-4 h-4" />}>ویزارد نصب</Button></Link>
          <Link to="/reports"><Button variant="primary" size="sm">ساخت گزارش</Button></Link>
        </div>
      </div>

      {/* Stat grid */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        {STATS.map(s => (
          <StatCard
            key={s.key}
            label={s.label}
            value={englishToPersian(String(stats?.[s.key] ?? 0))}
            icon={s.icon}
            href={s.href}
            tone={s.tone}
          />
        ))}
      </div>

      {/* Main grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {/* Trial balance + revenue */}
        <Card variant="glass-strong" className="lg:col-span-2">
          <CardHeader
            title="تراز آزمایشی"
            subtitle="خلاصهٔ حساب‌های دفتر کل"
            action={<Link to="/accounting" className="text-xs font-semibold text-brand-600 dark:text-brand-300 hover:underline">مشاهده همه ←</Link>}
          />
          {trialBalance?.accounts ? (
            <div className="space-y-2">
              {trialBalance.accounts.slice(0, 6).map((a, i) => {
                const max = Math.max(...trialBalance.accounts.map(x => Math.abs(x.balance || 0))) || 1;
                const pct = Math.min(100, Math.abs(a.balance || 0) / max * 100);
                return (
                  <div key={a.id || i} className="flex items-center gap-3">
                    <div className="w-28 text-xs font-mono text-ink-600 dark:text-ink-300 truncate" title={a.code}>{a.code}</div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center justify-between text-xs mb-1">
                        <span className="font-semibold text-ink-800 dark:text-ink-100 truncate">{a.label_fa || a.label || a.name}</span>
                        <span className={cx('tabular-nums font-mono font-bold', a.balance >= 0 ? 'text-success-600' : 'text-danger-600')}>
                          {formatMoney(a.balance, 'IRT')}
                        </span>
                      </div>
                      <div className="h-1.5 rounded-full bg-ink-100 dark:bg-ink-800 overflow-hidden">
                        <div className={cx('h-full rounded-full', a.balance >= 0 ? 'bg-success-500' : 'bg-danger-500')} style={{ width: pct + '%' }} />
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          ) : (
            <EmptyState text="داده‌ای برای تراز آزمایشی موجود نیست." />
          )}
        </Card>

        {/* Workflow stats */}
        <Card variant="brutal">
          <CardHeader title="گردش‌های کاری" subtitle="عملکرد اتوماسیون" />
          {workflowStats ? (
            <div className="grid grid-cols-2 gap-3">
              <Mini label="کل" value={englishToPersian(String(workflowStats.workflows_total || 0))} />
              <Mini label="فعال" value={englishToPersian(String(workflowStats.workflows_active || 0))} tone="success" />
              <Mini label="اجراها" value={englishToPersian(String(workflowStats.runs_total || 0))} />
              <Mini label="موفقیت" value={englishToPersian(String((workflowStats.success_rate || 0) + '%'))} tone="brand" />
            </div>
          ) : (
            <EmptyState text="گردش کاری تعریف نشده." />
          )}
          <div className="mt-4">
            <Link to="/workflows"><Button variant="primary" size="sm" className="w-full">مدیریت گردش کار</Button></Link>
          </div>
        </Card>
      </div>

      {/* Secondary grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <Card variant="glass">
          <CardHeader
            title="آخرین فاکتورها"
            action={<Link to="/invoices" className="text-xs font-semibold text-brand-600 dark:text-brand-300 hover:underline">همه ←</Link>}
          />
          {recentInvoices.length ? (
            <div className="-mx-2">
              {recentInvoices.map((inv) => (
                <div key={inv.id} className="flex items-center gap-3 px-2 py-2.5 rounded-lg hover:bg-ink-100/40 dark:hover:bg-ink-900/40 transition">
                  <div className="w-9 h-9 rounded-lg bg-warning-50 dark:bg-warning-500/10 text-warning-600 dark:text-warning-500 grid place-items-center">
                    <ReceiptIcon className="w-4 h-4" />
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="text-sm font-semibold truncate">{inv.number || `#${inv.id}`}</div>
                    <div className="text-[11px] text-ink-500 dark:text-ink-400">{formatJalaliShort(inv.issue_date || inv.created_at)}</div>
                  </div>
                  <div className="text-left">
                    <div className="text-sm font-bold tabular-nums">{formatMoney(inv.total, inv.currency || 'IRT')}</div>
                    <Badge variant={
                      inv.status === 'paid' ? 'success' :
                      inv.status === 'overdue' ? 'danger' :
                      inv.status === 'sent' ? 'info' : 'default'
                    } className="mt-0.5">{inv.status || 'draft'}</Badge>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <EmptyState text="هنوز فاکتوری صادر نشده." />
          )}
        </Card>

        <Card variant="glass">
          <CardHeader
            title="سرنخ‌های اخیر"
            action={<Link to="/leads" className="text-xs font-semibold text-brand-600 dark:text-brand-300 hover:underline">همه ←</Link>}
          />
          {recentLeads.length ? (
            <div className="-mx-2">
              {recentLeads.map((l) => (
                <div key={l.id} className="flex items-center gap-3 px-2 py-2.5 rounded-lg hover:bg-ink-100/40 dark:hover:bg-ink-900/40 transition">
                  <div className="w-9 h-9 rounded-full bg-info-50 dark:bg-info-500/10 text-info-600 dark:text-info-500 grid place-items-center text-xs font-bold">
                    {(l.full_name || l.name || '?').slice(0, 1)}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="text-sm font-semibold truncate">{l.full_name || l.name || 'بدون نام'}</div>
                    <div className="text-[11px] text-ink-500 dark:text-ink-400 truncate">{l.email || l.mobile || '—'}</div>
                  </div>
                  <div className="text-[10px] text-ink-400">{timeAgo(l.created_at)}</div>
                </div>
              ))}
            </div>
          ) : (
            <EmptyState text="سرنخی ثبت نشده است." />
          )}
        </Card>
      </div>
    </div>
  );
}

function StatCard({ label, value, icon, href, tone = 'brand' }) {
  const tones = {
    brand:   { bg: 'bg-brand-50 dark:bg-brand-500/10',   fg: 'text-brand-600 dark:text-brand-400' },
    success: { bg: 'bg-success-50 dark:bg-success-500/10', fg: 'text-success-600 dark:text-success-400' },
    warning: { bg: 'bg-warning-50 dark:bg-warning-500/10', fg: 'text-warning-600 dark:text-warning-400' },
    danger:  { bg: 'bg-danger-50 dark:bg-danger-500/10',  fg: 'text-danger-600 dark:text-danger-400' },
    info:    { bg: 'bg-info-50 dark:bg-info-500/10',     fg: 'text-info-600 dark:text-info-400' },
  };
  const t = tones[tone] || tones.brand;
  return (
    <Link
      to={href}
      className="group block surface-glass rounded-2xl p-4 hover:-translate-y-0.5 hover:shadow-glass-lg transition-all duration-200"
    >
      <div className="flex items-start justify-between">
        <div className={cx('w-10 h-10 rounded-xl grid place-items-center', t.bg, t.fg)}>{icon}</div>
        <div className="text-2xl text-ink-300 dark:text-ink-700 group-hover:text-ink-900 dark:group-hover:text-ink-50 transition flip-rtl">→</div>
      </div>
      <div className="mt-3">
        <div className="text-2xl font-extrabold tabular-nums tracking-tight text-ink-900 dark:text-ink-50">{value}</div>
        <div className="text-[11px] font-semibold text-ink-500 dark:text-ink-400 uppercase tracking-wider mt-0.5">{label}</div>
      </div>
    </Link>
  );
}

function Mini({ label, value, tone = 'default' }) {
  const tones = {
    default: 'text-ink-900 dark:text-ink-50',
    success: 'text-success-600 dark:text-success-400',
    brand:   'text-brand-600 dark:text-brand-400',
  };
  return (
    <div className="bg-white/40 dark:bg-ink-950/30 rounded-lg p-3">
      <div className="text-[10px] font-bold uppercase tracking-wider text-ink-500 dark:text-ink-400">{label}</div>
      <div className={cx('text-2xl font-extrabold tabular-nums mt-0.5', tones[tone])}>{value}</div>
    </div>
  );
}

function EmptyState({ text }) {
  return (
    <div className="text-center py-8 text-sm text-ink-500 dark:text-ink-400">
      <div className="text-2xl mb-1 opacity-40">∅</div>
      {text}
    </div>
  );
}

function DashboardSkeleton() {
  return (
    <div className="space-y-5">
      <div className="skeleton h-12 w-72 rounded-xl" />
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        {Array.from({ length: 8 }).map((_, i) => <div key={i} className="skeleton h-28 rounded-2xl" />)}
      </div>
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div className="skeleton h-72 rounded-2xl lg:col-span-2" />
        <div className="skeleton h-72 rounded-2xl" />
      </div>
    </div>
  );
}

/* ── Icons ── */
const I = (children) => (props) => (
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" {...props}>{children}</svg>
);
const CubeIcon      = I(<><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></>);
const FunnelIcon    = I(<><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></>);
const UsersIcon     = I(<><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></>);
const HandshakeIcon = I(<><path d="M11 17l-5-5 5-5M18 12H8M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z"/></>);
const BoxIcon       = I(<><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></>);
const ReceiptIcon   = I(<><path d="M4 2h16v20l-2-1-2 1-2-1-2 1-2-1-2 1-2-1-2 1V2z"/><path d="M8 7h8M8 11h8M8 15h5"/></>);
const CartIcon      = I(<><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></>);
const IdIcon        = I(<><rect x="2" y="4" width="20" height="16" rx="2"/><circle cx="8" cy="10" r="2"/><path d="M14 10h4M14 14h4M6 16h6"/></>);
const SettingsIcon  = I(<><circle cx="12" cy="12" r="3"/><path d="M12 1v6m0 6v6m11-7h-6m-6 0H1m17.36-7.36l-4.24 4.24m-4.24 4.24l-4.24 4.24m0-13.36l4.24 4.24m4.24 4.24l4.24 4.24"/></>);
