import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { api, formatCurrency, formatDateJalali } from '../lib/api';
import type { Invoice } from '../lib/types';

export default function InvoicesPage() {
  const { t } = useTranslation();
  const [items, setItems] = useState<Invoice[]>([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    (async () => {
      try {
        const list = await api.invoices();
        setItems(list);
      } catch (e: unknown) {
        setErr(e instanceof Error ? e.message : 'error');
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  return (
    <div className="space-y-3">
      <h1 className="text-lg font-semibold">{t('invoice.title')}</h1>
      {loading && <div className="card text-sm text-slate-500">{t('common.loading')}</div>}
      {err && <div className="card text-sm text-rose-700">{t('common.error')}: {err}</div>}
      {!loading && !items.length && <div className="card text-sm text-slate-500">{t('common.empty')}</div>}
      <div className="space-y-2">
        {items.map(inv => {
          const status = inv.status || (inv.paid >= inv.total ? 'paid' : 'unpaid');
          return (
            <div key={inv.id} className="card">
              <div className="flex items-center justify-between mb-1">
                <div className="font-semibold">{inv.number}</div>
                <StatusBadge status={status} />
              </div>
              <div className="text-xs text-slate-500 mb-2">{formatDateJalali(inv.issue_date)}</div>
              <div className="grid grid-cols-2 gap-2 text-sm">
                <div>
                  <div className="text-slate-500 text-xs">{t('invoice.total')}</div>
                  <div className="font-medium">{formatCurrency(inv.total, inv.currency)}</div>
                </div>
                <div>
                  <div className="text-slate-500 text-xs">{t('invoice.paid')}</div>
                  <div className="font-medium">{formatCurrency(inv.paid, inv.currency)}</div>
                </div>
              </div>
              {inv.tax_invoice_uid && (
                <div className="mt-2 text-[11px] text-slate-500">شناسه مالیاتی: {inv.tax_invoice_uid}</div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

function StatusBadge({ status }: { status: string }) {
  const map: Record<string, string> = {
    paid: 'badge-ok',
    unpaid: 'badge-warn',
    overdue: 'badge-err',
    cancelled: 'badge-neutral',
    draft: 'badge-neutral',
  };
  return <span className={map[status] || 'badge-neutral'}>{status}</span>;
}
