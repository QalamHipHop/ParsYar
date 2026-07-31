import React, { useCallback, useState } from 'react';
import { api } from '../api/client.js';
import ResourceTable from '../components/ResourceTable.jsx';
import ResourceForm from '../components/ResourceForm.jsx';
import { timeAgo } from '../lib/format.js';

export default function Contacts() {
  const [open, setOpen] = useState(false);
  const [edit, setEdit] = useState(null);
  const fetcher = useCallback(() => api.contacts(), []);

  return (
    <>
      <ResourceTable
        title="مخاطبین"
        subtitle="پایگاه مخاطبین و اطلاعات تماس"
        fetcher={fetcher}
        searchKeys={['full_name', 'email', 'mobile', 'company']}
        createLabel="+ مخاطب جدید"
        onCreate={() => { setEdit(null); setOpen(true); }}
        onEdit={(r) => { setEdit(r); setOpen(true); }}
        onDelete={async (r) => { await api.del(`/crm/contacts/${r.id}`); }}
        columns={[
          { key: 'full_name', label: 'نام', render: r => (
            <div className="flex items-center gap-2.5">
              <div className="w-8 h-8 rounded-full bg-info-50 dark:bg-info-500/10 text-info-600 dark:text-info-400 grid place-items-center text-[11px] font-bold">{(r.full_name || r.name || '?').slice(0, 1)}</div>
              <div className="min-w-0">
                <div className="font-semibold truncate">{r.full_name || r.name || '—'}</div>
                <div className="text-[11px] text-ink-500 dark:text-ink-400 truncate">{r.position || ''}</div>
              </div>
            </div>
          ) },
          { key: 'email',   label: 'ایمیل',    render: r => r.email || '—' },
          { key: 'mobile',  label: 'موبایل',   render: r => <span className="ltr-num font-mono text-xs">{r.mobile || '—'}</span> },
          { key: 'company', label: 'شرکت',     render: r => r.company || '—' },
          { key: 'updated_at', label: 'بروزرسانی', render: r => <span className="text-[11px] text-ink-500 dark:text-ink-400">{timeAgo(r.updated_at || r.created_at)}</span> },
        ]}
      />
      <ResourceForm
        open={open}
        onClose={() => setOpen(false)}
        title={edit ? `ویرایش مخاطب #${edit.id}` : 'مخاطب جدید'}
        initial={edit || {}}
        fields={[
          { key: 'full_name', label: 'نام', type: 'text', required: true },
          { key: 'email',     label: 'ایمیل', type: 'email' },
          { key: 'mobile',    label: 'موبایل', type: 'text' },
          { key: 'phone',     label: 'تلفن',  type: 'text' },
          { key: 'company',   label: 'شرکت',  type: 'text' },
          { key: 'position',  label: 'سمت',   type: 'text' },
          { key: 'notes',     label: 'یادداشت', type: 'textarea' },
        ]}
        onSubmit={async (v) => {
          if (edit) await api.put(`/crm/contacts/${edit.id}`, v);
          else      await api.createContact(v);
        }}
      />
    </>
  );
}
