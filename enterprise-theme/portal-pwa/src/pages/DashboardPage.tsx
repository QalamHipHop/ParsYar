import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { api, formatCurrency, formatDateJalali } from '../lib/api';
import type { Invoice, Payment, Profile } from '../lib/types';

export default function DashboardPage() {
  const { t } = useTranslation();
  const [profile, setProfile] = useState<Profile | null>(api.getProfile());
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [payments, setPayments] = useState<Payment[]>([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    (async () => {
      setLoading(true);
      setErr(null);
      try {
        const [p, inv, pay] = await Promise.all([
          api.me().catch(() => profile),
          api.invoices().catch(() => [] as Invoice[]),
          api.payments().catch(() => [] as Payment[]),
        ]);
        if (p) { api.saveProfile(p); setProfile(p); }
        setInvoices(inv);
        setPayments(pay);
      } catch (e: unknown) {
        setErr(e instanceof Error ? e.message : 'error');
      } finally {
        setLoading(false);
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const unpaid = invoices.filter(i => (i.paid || 0) < (i.total || 0)).length;
  const lastInv = invoices[0];

  return (
    <div className="space-y-4">
      <div>
        <div className="text-xs text-slate-500">{t('dashboard.welcome')}</div>
        <div className="text-xl font-semibold">{profile?.full_name || '—'}</div>
        {profile?.company && <div className="text-sm text-slate-500">{profile.company}</div>}
      </div>

      {err && (
        <div className="card border-rose-200 bg-rose-50 text-rose-800 text-sm">
          {t('common.error')}: {err}
        </div>
      )}

      <div className="card">
        <div className="text-sm font-semibold mb-3">{t('dashboard.summary')}</div>
        {loading ? (
          <div className="text-sm text-slate-500">{t('common.loading')}</div>
        ) : (
          <div className="grid grid-cols-3 gap-3">
            <Stat label={t('dashboard.totalInvoices')} value={String(invoices.length)} />
            <Stat label={t('dashboard.unpaid')} value={String(unpaid)} tone={unpaid > 0 ? 'warn' : 'ok'} />
            <Stat label={t('dashboard.openTickets')} value="—" />
          </div>
        )}
      </div>

      <div className="card">
        <div className="text-sm font-semibold mb-2">{t('dashboard.lastInvoice')}</div>
        {lastInv ? (
          <div className="text-sm space-y-1">
            <div className="flex justify-between"><span className="text-slate-500">{t('invoice.number')}</span><span>{lastInv.number}</span></div>
            <div className="flex justify-between"><span className="text-slate-500">{t('invoice.date')}</span><span>{formatDateJalali(lastInv.issue_date)}</span></div>
            <div className="flex justify-between"><span className="text-slate-500">{t('invoice.total')}</span><span>{formatCurrency(lastInv.total, lastInv.currency)}</span></div>
            <div className="flex justify-between"><span className="text-slate-500">{t('invoice.status')}</span><span>{lastInv.status}</span></div>
          </div>
        ) : (
          <div className="text-sm text-slate-500">{t('dashboard.noInvoice')}</div>
        )}
      </div>

      <div className="card">
        <div className="text-sm font-semibold mb-2">{t('dashboard.recentPayments')}</div>
        {payments.length ? (
          <ul className="divide-y divide-slate-100">
            {payments.slice(0, 3).map(p => (
              <li key={p.id} className="py-2 flex items-center justify-between text-sm">
                <div>
                  <div className="font-medium">{formatCurrency(p.amount, p.currency)}</div>
                  <div className="text-xs text-slate-500">{p.gateway} · {p.ref_id}</div>
                </div>
                <div className="text-xs text-slate-500">{formatDateJalali(p.paid_at)}</div>
              </li>
            ))}
          </ul>
        ) : (
          <div className="text-sm text-slate-500">{t('dashboard.noPayment')}</div>
        )}
      </div>
    </div>
  );
}

function Stat({ label, value, tone }: { label: string; value: string; tone?: 'ok' | 'warn' | 'err' }) {
  const colors: Record<string, string> = { ok: 'text-emerald-700', warn: 'text-amber-700', err: 'text-rose-700' };
  return (
    <div className="rounded-lg bg-slate-50 p-3">
      <div className="text-[11px] text-slate-500">{label}</div>
      <div className={`text-lg font-semibold ${colors[tone || ''] || ''}`}>{value}</div>
    </div>
  );
}
