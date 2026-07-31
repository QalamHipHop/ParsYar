import React, { useCallback, useState } from 'react';
import { api } from '../api/client.js';
import ResourceTable from '../components/ResourceTable.jsx';
import ResourceForm from '../components/ResourceForm.jsx';
import Badge from '../components/Badge.jsx';
import { formatMoney } from '../lib/format.js';

export default function Products() {
  const [open, setOpen] = useState(false);
  const [edit, setEdit] = useState(null);
  const fetcher = useCallback(() => api.products(), []);

  return (
    <>
      <ResourceTable
        title="انبار"
        subtitle="محصولات و موجودی کالا"
        fetcher={fetcher}
        searchKeys={['name', 'sku', 'barcode']}
        createLabel="+ محصول جدید"
        onCreate={() => { setEdit(null); setOpen(true); }}
        onEdit={(r) => { setEdit(r); setOpen(true); }}
        onDelete={async (r) => { await api.del(`/erp/products/${r.id}`); }}
        columns={[
          { key: 'name', label: 'نام', render: r => (
            <div className="flex items-center gap-2.5">
              <div className="w-9 h-9 rounded-lg bg-warning-50 dark:bg-warning-500/10 text-warning-600 dark:text-warning-500 grid place-items-center flex-shrink-0">
                <BoxIcon className="w-4 h-4" />
              </div>
              <div className="min-w-0">
                <div className="font-semibold truncate">{r.name || '—'}</div>
                <div className="text-[11px] font-mono text-ink-500 dark:text-ink-400">{r.sku || ''}</div>
              </div>
            </div>
          ) },
          { key: 'price',    label: 'قیمت',    align: 'left', render: r => <span className="font-mono tabular-nums">{formatMoney(r.price, r.currency || 'IRT')}</span> },
          { key: 'stock',    label: 'موجودی',  align: 'center', render: r => {
            const s = Number(r.stock || 0);
            return <Badge variant={s <= 0 ? 'danger' : s < 10 ? 'warning' : 'success'}>{s}</Badge>;
          } },
          { key: 'type',     label: 'نوع',     render: r => <Badge variant="default">{r.type || 'product'}</Badge> },
          { key: 'category', label: 'دسته',    render: r => r.category || '—' },
        ]}
      />
      <ResourceForm
        open={open}
        onClose={() => setOpen(false)}
        title={edit ? `ویرایش محصول #${edit.id}` : 'محصول جدید'}
        initial={edit || {}}
        fields={[
          { key: 'name',     label: 'نام',     type: 'text',     required: true },
          { key: 'sku',      label: 'SKU',     type: 'text' },
          { key: 'barcode',  label: 'بارکد',   type: 'text' },
          { key: 'type',     label: 'نوع',     type: 'select',   options: ['product','service'] },
          { key: 'price',    label: 'قیمت',    type: 'number',   step: 1 },
          { key: 'cost',     label: 'قیمت تمام‌شده', type: 'number', step: 1 },
          { key: 'currency', label: 'ارز',     type: 'select',   options: ['IRT','IRR','USD','EUR','AED'] },
          { key: 'stock',    label: 'موجودی اولیه', type: 'number', step: 1 },
          { key: 'description', label: 'توضیحات', type: 'textarea' },
        ]}
        onSubmit={async (v) => {
          if (edit) await api.put(`/erp/products/${edit.id}`, v);
          else      await api.createProduct(v);
        }}
      />
    </>
  );
}

const BoxIcon = (props) => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}>
    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
    <path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12" />
  </svg>
);
