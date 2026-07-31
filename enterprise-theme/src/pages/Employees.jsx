import React, { useCallback, useState } from 'react';
import { api } from '../api/client.js';
import ResourceTable from '../components/ResourceTable.jsx';
import ResourceForm from '../components/ResourceForm.jsx';
import Badge from '../components/Badge.jsx';
import { formatMoney, formatJalaliShort } from '../lib/format.js';

export default function Employees() {
  const [open, setOpen] = useState(false);
  const [edit, setEdit] = useState(null);
  const fetcher = useCallback(() => api.employees(), []);

  return (
    <>
      <ResourceTable
        title="پرسنل"
        subtitle="کارمندان، قراردادها و اطلاعات سازمانی"
        fetcher={fetcher}
        searchKeys={['full_name', 'national_id', 'mobile', 'position']}
        createLabel="+ پرسنل جدید"
        onCreate={() => { setEdit(null); setOpen(true); }}
        onEdit={(r) => { setEdit(r); setOpen(true); }}
        onDelete={async (r) => { await api.del(`/hrm/employees/${r.id}`); }}
        columns={[
          { key: 'full_name', label: 'نام', render: r => (
            <div className="flex items-center gap-2.5">
              <div className="w-8 h-8 rounded-full bg-success-50 dark:bg-success-500/10 text-success-600 dark:text-success-400 grid place-items-center text-[11px] font-bold">{(r.full_name || r.name || '?').slice(0, 1)}</div>
              <div className="min-w-0">
                <div className="font-semibold truncate">{r.full_name || r.name || '—'}</div>
                <div className="text-[11px] text-ink-500 dark:text-ink-400 truncate">{r.position || ''}</div>
              </div>
            </div>
          ) },
          { key: 'national_id', label: 'کد ملی', render: r => <span className="font-mono text-xs ltr-num">{r.national_id || '—'}</span> },
          { key: 'mobile', label: 'موبایل', render: r => <span className="ltr-num font-mono text-xs">{r.mobile || '—'}</span> },
          { key: 'department', label: 'دپارتمان', render: r => r.department || '—' },
          { key: 'salary', label: 'حقوق پایه', align: 'left', render: r => r.salary ? <span className="font-mono tabular-nums">{formatMoney(r.salary, 'IRT')}</span> : '—' },
          { key: 'status', label: 'وضعیت', render: r => <Badge variant={r.status === 'active' ? 'success' : 'default'}>{r.status || 'active'}</Badge> },
        ]}
      />
      <ResourceForm
        open={open}
        onClose={() => setOpen(false)}
        title={edit ? `ویرایش پرسنل #${edit.id}` : 'پرسنل جدید'}
        initial={edit || {}}
        fields={[
          { key: 'full_name',  label: 'نام کامل',   type: 'text',     required: true },
          { key: 'national_id',label: 'کد ملی',     type: 'text' },
          { key: 'mobile',     label: 'موبایل',     type: 'text' },
          { key: 'email',      label: 'ایمیل',      type: 'email' },
          { key: 'position',   label: 'سمت',       type: 'text' },
          { key: 'department', label: 'دپارتمان',   type: 'text' },
          { key: 'hire_date',  label: 'تاریخ استخدام', type: 'date' },
          { key: 'salary',     label: 'حقوق پایه',  type: 'number', step: 1 },
          { key: 'status',     label: 'وضعیت',      type: 'select', options: ['active','inactive','suspended'] },
        ]}
        onSubmit={async (v) => {
          if (edit) await api.put(`/hrm/employees/${edit.id}`, v);
          else      await api.createEmployee(v);
        }}
      />
    </>
  );
}
