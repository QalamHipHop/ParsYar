import React, { useCallback, useState } from 'react';
import { api } from '../api/client.js';
import ResourceTable from '../components/ResourceTable.jsx';
import ResourceForm from '../components/ResourceForm.jsx';
import Badge from '../components/Badge.jsx';
import { formatJalaliShort, timeAgo } from '../lib/format.js';

const STATUS_VARIANTS = {
  new: 'info', qualified: 'warning', proposal: 'brand', negotiation: 'warning', won: 'success', lost: 'danger',
};

const FIELDS = [
  { key: 'full_name', label: 'نام کامل', type: 'text',   required: true },
  { key: 'email',     label: 'ایمیل',     type: 'email'  },
  { key: 'mobile',    label: 'موبایل',    type: 'text'   },
  { key: 'company',   label: 'شرکت',      type: 'text'   },
  { key: 'status',    label: 'وضعیت',     type: 'select', options: ['new','qualified','proposal','negotiation','won','lost'] },
  { key: 'source',    label: 'منبع',      type: 'select', options: ['website','referral','social','ads','cold_call','other'] },
  { key: 'score',     label: 'امتیاز',    type: 'number', step: 1 },
  { key: 'notes',     label: 'یادداشت',   type: 'textarea' },
];

export default function Leads() {
  const [formOpen, setFormOpen] = useState(false);
  const [editRow, setEditRow]   = useState(null);

  const fetcher = useCallback(() => api.leads(), []);

  return (
    <>
      <ResourceTable
        title="سرنخ‌ها"
        subtitle="مدیریت سرنخ‌های فروش و فرآیند ارزیابی"
        fetcher={fetcher}
        searchKeys={['full_name', 'email', 'mobile', 'company']}
        createLabel="+ سرنخ جدید"
        onCreate={() => { setEditRow(null); setFormOpen(true); }}
        onEdit={(row) => { setEditRow(row); setFormOpen(true); }}
        onDelete={async (row) => { await api.del(`/crm/leads/${row.id}`); }}
        columns={[
          { key: 'full_name', label: 'نام',      sortable: true,
            render: r => (
              <div className="flex items-center gap-2.5">
                <div className="w-8 h-8 rounded-full bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400 grid place-items-center text-[11px] font-bold flex-shrink-0">
                  {(r.full_name || r.name || '?').slice(0, 1)}
                </div>
                <div className="min-w-0">
                  <div className="font-semibold truncate">{r.full_name || r.name || '—'}</div>
                  <div className="text-[11px] text-ink-500 dark:text-ink-400 truncate">{r.email || r.mobile || '—'}</div>
                </div>
              </div>
            ) },
          { key: 'company',   label: 'شرکت',      sortable: true, render: r => r.company || '—' },
          { key: 'status',    label: 'وضعیت',     render: r => <Badge variant={STATUS_VARIANTS[r.status] || 'default'}>{r.status || 'new'}</Badge> },
          { key: 'score',     label: 'امتیاز',    align: 'center', render: r => r.score != null ? <span className="font-mono text-xs font-bold">{r.score}</span> : '—' },
          { key: 'source',    label: 'منبع',      render: r => r.source || '—' },
          { key: 'updated_at',label: 'بروزرسانی', render: r => <span className="text-[11px] text-ink-500 dark:text-ink-400">{timeAgo(r.updated_at || r.created_at)}</span> },
        ]}
      />
      <ResourceForm
        open={formOpen}
        onClose={() => setFormOpen(false)}
        title={editRow ? `ویرایش سرنخ #${editRow.id}` : 'سرنخ جدید'}
        initial={editRow || {}}
        fields={FIELDS}
        onSubmit={async (values) => {
          if (editRow) await api.put(`/crm/leads/${editRow.id}`, values);
          else         await api.createLead(values);
        }}
      />
    </>
  );
}
