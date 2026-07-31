import React, { useCallback } from 'react';
import { api } from '../api/client.js';
import ResourceTable from '../components/ResourceTable.jsx';
import Badge from '../components/Badge.jsx';
import { formatMoney, formatJalaliShort } from '../lib/format.js';

const STATUS = { pending: 'warning', success: 'success', failed: 'danger', refunded: 'default' };

export default function Payments() {
  const fetcher = useCallback(() => api.payments(), []);
  return (
    <ResourceTable
      title="پرداخت‌ها"
      subtitle="تراکنش‌های درگاه‌های بانکی"
      fetcher={fetcher}
      searchKeys={['ref_id', 'gateway', 'card_number']}
      columns={[
        { key: 'ref_id', label: 'کد پیگیری', render: r => <span className="font-mono text-[11px]">{r.ref_id || r.tracking_code || '—'}</span> },
        { key: 'amount', label: 'مبلغ', align: 'left', render: r => <span className="font-mono tabular-nums font-bold">{formatMoney(r.amount, r.currency || 'IRT')}</span> },
        { key: 'gateway', label: 'درگاه', render: r => <Badge variant="brand">{r.gateway || '—'}</Badge> },
        { key: 'method',  label: 'روش',   render: r => r.method || '—' },
        { key: 'status',  label: 'وضعیت', render: r => <Badge variant={STATUS[r.status] || 'default'}>{r.status || 'pending'}</Badge> },
        { key: 'paid_at', label: 'تاریخ', render: r => formatJalaliShort(r.paid_at || r.created_at) },
      ]}
    />
  );
}
