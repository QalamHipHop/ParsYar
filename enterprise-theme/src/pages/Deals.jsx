import React, { useCallback, useState } from 'react';
import { api } from '../api/client.js';
import ResourceTable from '../components/ResourceTable.jsx';
import ResourceForm from '../components/ResourceForm.jsx';
import Badge from '../components/Badge.jsx';
import { formatMoney, timeAgo } from '../lib/format.js';

const STAGES = {
  lead: 'info', qualified: 'brand', proposal: 'warning', negotiation: 'warning', won: 'success', lost: 'danger',
};

export default function Deals() {
  const [open, setOpen] = useState(false);
  const [edit, setEdit] = useState(null);
  const fetcher = useCallback(() => api.deals(), []);

  return (
    <>
      <ResourceTable
        title="معاملات"
        subtitle="فرصت‌های فروش و Pipeline"
        fetcher={fetcher}
        searchKeys={['title', 'contact_name', 'company']}
        createLabel="+ معاملهٔ جدید"
        onCreate={() => { setEdit(null); setOpen(true); }}
        onEdit={(r) => { setEdit(r); setOpen(true); }}
        onDelete={async (r) => { await api.del(`/crm/deals/${r.id}`); }}
        columns={[
          { key: 'title', label: 'عنوان', render: r => <span className="font-semibold">{r.title || r.name || '—'}</span> },
          { key: 'amount', label: 'مبلغ', align: 'left', render: r => <span className="font-mono tabular-nums font-bold">{formatMoney(r.amount, r.currency || 'IRT')}</span> },
          { key: 'stage',  label: 'مرحله', render: r => <Badge variant={STAGES[r.stage] || 'default'}>{r.stage || 'lead'}</Badge> },
          { key: 'probability', label: 'احتمال', align: 'center', render: r => r.probability != null ? <span className="font-mono text-xs">{r.probability}%</span> : '—' },
          { key: 'expected_close_date', label: 'تاریخ بستن', render: r => r.expected_close_date || '—' },
          { key: 'updated_at', label: 'بروزرسانی', render: r => <span className="text-[11px] text-ink-500 dark:text-ink-400">{timeAgo(r.updated_at || r.created_at)}</span> },
        ]}
      />
      <ResourceForm
        open={open}
        onClose={() => setOpen(false)}
        title={edit ? `ویرایش معامله #${edit.id}` : 'معاملهٔ جدید'}
        initial={edit || {}}
        fields={[
          { key: 'title',     label: 'عنوان', type: 'text', required: true },
          { key: 'amount',    label: 'مبلغ',  type: 'number', step: 1 },
          { key: 'currency',  label: 'ارز',   type: 'select', options: ['IRT','IRR','USD','EUR','AED','TRY'] },
          { key: 'stage',     label: 'مرحله', type: 'select', options: ['lead','qualified','proposal','negotiation','won','lost'] },
          { key: 'probability', label: 'احتمال (٪)', type: 'number', step: 1 },
          { key: 'expected_close_date', label: 'تاریخ بستن', type: 'date' },
          { key: 'notes',     label: 'یادداشت', type: 'textarea' },
        ]}
        onSubmit={async (v) => {
          if (edit) await api.put(`/crm/deals/${edit.id}`, v);
          else      await api.createDeal(v);
        }}
      />
    </>
  );
}
