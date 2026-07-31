import React, { useCallback, useState } from 'react';
import { api } from '../api/client.js';
import ResourceTable from '../components/ResourceTable.jsx';
import Badge from '../components/Badge.jsx';
import { formatMoney, formatJalaliShort } from '../lib/format.js';

const STATUS = { pending: 'warning', processing: 'info', shipped: 'brand', delivered: 'success', cancelled: 'danger' };

export default function Orders() {
  const fetcher = useCallback(() => api.orders(), []);
  return (
    <ResourceTable
      title="سفارش‌ها"
      subtitle="سفارش‌های دریافتی و رهگیری ارسال"
      fetcher={fetcher}
      searchKeys={['number', 'customer_name', 'tracking_code']}
      columns={[
        { key: 'number', label: 'شماره', render: r => <span className="font-mono font-semibold text-xs">{r.number || `#${r.id}`}</span> },
        { key: 'customer_name', label: 'مشتری', render: r => r.customer_name || '—' },
        { key: 'order_date', label: 'تاریخ', render: r => formatJalaliShort(r.order_date || r.created_at) },
        { key: 'total',      label: 'مبلغ', align: 'left', render: r => <span className="font-mono tabular-nums font-bold">{formatMoney(r.total, r.currency || 'IRT')}</span> },
        { key: 'status',     label: 'وضعیت', render: r => <Badge variant={STATUS[r.status] || 'default'}>{r.status || 'pending'}</Badge> },
        { key: 'tracking_code', label: 'کد رهگیری', render: r => r.tracking_code ? <span className="font-mono text-[10px]">{r.tracking_code}</span> : '—' },
      ]}
    />
  );
}
