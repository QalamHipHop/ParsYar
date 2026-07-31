import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { api, type Contact, type Invoice, type Order, type Ticket } from '../lib/api';
import { fmtMoney } from '../lib/format';

export default function DashboardPage() {
  const { t } = useTranslation();
  const [me, setMe] = useState<Contact | null>(null);
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [orders, setOrders] = useState<Order[]>([]);
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    let mounted = true;
    Promise.allSettled([api.me(), api.listInvoices({ limit: 5 }), api.listOrders({}), api.listTickets()])
      .then(([m, i, o, tk]) => {
        if (!mounted) return;
        if (m.status === 'fulfilled') setMe(m.value);
        if (i.status === 'fulfilled') setInvoices(i.value);
        if (o.status === 'fulfilled') setOrders(o.value);
        if (tk.status === 'fulfilled') setTickets(tk.value);
        const failed = [m, i, o, tk].filter(x => x.status === 'rejected') as PromiseRejectedResult[];
        if (failed.length) setErr(failed[0].reason?.message || 'error');
      });
    return () => { mounted = false; };
  }, []);

  return (
    <div className="space-y-4">
      <div className="card">
        <div className="text-xs text-slate-500">{t('dashboard.welcome')}</div>
        <div className="text-lg font-bold">{me?.full_name || me?.email || '—'}</div>
        {me?.company && <div className="text-xs text-slate-500">{me.company}</div>}
        {err && <div className="text-rose-600 text-xs mt-2">{err}</div>}
      </div>

      <div className="grid sm:grid-cols-2 gap-3">
        <div className="card">
          <div className="text-xs text-slate-500 mb-2">{t('dashboard.lastInvoices')}</div>
          {invoices.length === 0 && <div className="text-xs text-slate-400">{t('dashboard.noData')}</div>}
          {invoices.slice(0, 5).map(inv => (
            <div key={inv.id} className="flex items-center justify-between text-sm py-1">
              <span>{inv.number}</span>
              <span className="text-slate-600">{fmtMoney(inv.total, inv.currency)}</span>
            </div>
          ))}
        </div>
        <div className="card">
          <div className="text-xs text-slate-500 mb-2">{t('dashboard.lastOrders')}</div>
          {orders.length === 0 && <div className="text-xs text-slate-400">{t('dashboard.noData')}</div>}
          {orders.slice(0, 5).map(o => (
            <div key={o.id} className="flex items-center justify-between text-sm py-1">
              <span>{o.number}</span>
              <span className="text-slate-600">{o.status}</span>
            </div>
          ))}
        </div>
      </div>

      <div className="card">
        <div className="text-xs text-slate-500 mb-2">{t('dashboard.openTickets')}</div>
        {tickets.length === 0 && <div className="text-xs text-slate-400">{t('dashboard.noData')}</div>}
        {tickets.slice(0, 5).map(tk => (
          <div key={tk.id} className="flex items-center justify-between text-sm py-1">
            <span className="truncate">{tk.subject}</span>
            <span className="badge-neutral">{tk.status}</span>
          </div>
        ))}
      </div>
    </div>
  );
}
