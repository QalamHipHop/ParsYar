import React, { useCallback, useState } from 'react';
import { api } from '../api/client.js';
import ResourceTable from '../components/ResourceTable.jsx';
import ResourceForm from '../components/ResourceForm.jsx';
import Badge from '../components/Badge.jsx';
import { formatMoney, formatJalaliShort } from '../lib/format.js';

const STATUS = {
  draft: 'default', sent: 'info', paid: 'success', partial: 'warning', overdue: 'danger', cancelled: 'default',
};

export default function Invoices() {
  const [open, setOpen] = useState(false);
  const [edit, setEdit] = useState(null);
  const fetcher = useCallback(() => api.invoices(), []);

  return (
    <>
      <ResourceTable
        title="فاکتورها"
        subtitle="صدور و پیگیری فاکتورهای فروش"
        fetcher={fetcher}
        searchKeys={['number', 'customer_name', 'tax_invoice_uid']}
        createLabel="+ فاکتور جدید"
        onCreate={() => { setEdit(null); setOpen(true); }}
        onEdit={(r) => { setEdit(r); setOpen(true); }}
        onDelete={async (r) => { await api.del(`/erp/invoices/${r.id}`); }}
        columns={[
          { key: 'number', label: 'شماره', render: r => (
            <div className="flex items-center gap-2">
              <div className="w-7 h-7 rounded-md bg-warning-50 dark:bg-warning-500/10 text-warning-600 dark:text-warning-500 grid place-items-center flex-shrink-0">
                <ReceiptIcon className="w-3.5 h-3.5" />
              </div>
              <div className="min-w-0">
                <div className="font-semibold font-mono text-xs">{r.number || `#${r.id}`}</div>
                {r.tax_invoice_uid && <div className="text-[10px] text-ink-500 dark:text-ink-400 font-mono">{r.tax_invoice_uid}</div>}
              </div>
            </div>
          ) },
          { key: 'customer_name', label: 'مشتری', render: r => r.customer_name || r.contact_name || '—' },
          { key: 'issue_date', label: 'تاریخ صدور', render: r => formatJalaliShort(r.issue_date) },
          { key: 'due_date',   label: 'سررسید',    render: r => r.due_date ? formatJalaliShort(r.due_date) : '—' },
          { key: 'total',      label: 'مبلغ',      align: 'left', render: r => <span className="font-mono tabular-nums font-bold">{formatMoney(r.total, r.currency || 'IRT')}</span> },
          { key: 'paid',       label: 'پرداخت‌شده',align: 'left', render: r => <span className="font-mono tabular-nums">{formatMoney(r.paid || 0, r.currency || 'IRT')}</span> },
          { key: 'status',     label: 'وضعیت',     render: r => <Badge variant={STATUS[r.status] || 'default'}>{r.status || 'draft'}</Badge> },
        ]}
      />
      <ResourceForm
        open={open}
        onClose={() => setOpen(false)}
        title={edit ? `ویرایش فاکتور #${edit.id}` : 'فاکتور جدید'}
        initial={edit || { issue_date: new Date().toISOString().slice(0, 10) }}
        fields={[
          { key: 'number',     label: 'شماره فاکتور', type: 'text' },
          { key: 'customer_name', label: 'نام مشتری', type: 'text', required: true },
          { key: 'issue_date', label: 'تاریخ صدور', type: 'date', required: true },
          { key: 'due_date',   label: 'سررسید',     type: 'date' },
          { key: 'currency',   label: 'ارز',         type: 'select', options: ['IRT','IRR','USD','EUR','AED'] },
          { key: 'total',      label: 'مبلغ کل',     type: 'number', step: 1 },
          { key: 'tax',        label: 'مالیات',       type: 'number', step: 1 },
          { key: 'discount',   label: 'تخفیف',       type: 'number', step: 1 },
          { key: 'status',     label: 'وضعیت',       type: 'select', options: ['draft','sent','paid','partial','overdue','cancelled'] },
          { key: 'notes',      label: 'یادداشت',     type: 'textarea' },
        ]}
        onSubmit={async (v) => {
          if (edit) await api.put(`/erp/invoices/${edit.id}`, v);
          else      await api.createInvoice(v);
        }}
      />
    </>
  );
}

const ReceiptIcon = (props) => (
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}>
    <path d="M4 2h16v20l-2-1-2 1-2-1-2 1-2-1-2 1-2-1-2 1V2z" />
    <path d="M8 7h8M8 11h8M8 15h5" />
  </svg>
);
