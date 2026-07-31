/**
 * Tenants — multi-tenant management (Holding mode, v2.0.0).
 * Lists tenants, lets you create, switch, archive, and manage branches.
 * Falls back to demo data when the API returns empty.
 */
import React, { useCallback, useEffect, useState } from 'react';
import Card, { CardHeader } from '../components/Card.jsx';
import Button from '../components/Button.jsx';
import Badge from '../components/Badge.jsx';
import Input, { Select, Textarea } from '../components/Input.jsx';
import Modal from '../components/Modal.jsx';
import { useToasts } from '../store';
import { api } from '../api/client.js';
import { cx, formatJalali, formatJalaliShort, timeAgo } from '../lib/format.js';

const PLAN_VARIANT = { starter: 'default', pro: 'info', enterprise: 'success' };
const PLAN_LABEL   = { starter: 'استارتر', pro: 'حرفه‌ای', enterprise: 'سازمانی' };

const DEMO_TENANTS = [
  { id: 1, name: 'شرکت مادر (هلدینگ پارس‌یار)',  slug: 'holding',     plan: 'enterprise', settings: { branding: { primary: '#18181b' } }, branches: 3, members: 28, created_at: '2024-01-12T10:00:00Z', archived: 0, is_default: 1 },
  { id: 2, name: 'شرکت آلفا',                       slug: 'alpha-co',    plan: 'pro',        settings: {}, branches: 2, members: 12, created_at: '2024-03-04T10:00:00Z', archived: 0, is_default: 0 },
  { id: 3, name: 'بوتیک نگین',                      slug: 'ngin',        plan: 'starter',    settings: {}, branches: 1, members: 3,  created_at: '2024-06-21T10:00:00Z', archived: 0, is_default: 0 },
  { id: 4, name: 'تست آرشیو',                       slug: 'archived-1',  plan: 'starter',    settings: {}, branches: 0, members: 0,  created_at: '2023-11-09T10:00:00Z', archived: 1, is_default: 0 },
];

export default function Tenants() {
  const [list, setList] = useState([]);
  const [loading, setLoading] = useState(true);
  const [editing, setEditing] = useState(null);
  const [creating, setCreating] = useState(false);
  const push = useToasts(s => s.push);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const r = await api.tenants().catch(() => null);
      const items = Array.isArray(r) ? r : (r?.data || []);
      setList(items.length ? items : DEMO_TENANTS);
    } catch {
      setList(DEMO_TENANTS);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const onCreate = async (v) => {
    try {
      const r = await api.post('/tenants', v);
      const created = r?.id ? r : { id: Date.now(), ...v, plan: v.plan || 'starter', archived: 0, is_default: 0, branches: 0, members: 1, created_at: new Date().toISOString() };
      setList((arr) => [created, ...arr]);
      setCreating(false);
      push({ type: 'success', message: 'شرکت ساخته شد' });
    } catch (e) {
      push({ type: 'error', message: e.message });
    }
  };

  const onSave = async (v) => {
    try {
      await api.put(`/tenants/${editing.id}`, v);
      setList((arr) => arr.map((t) => (t.id === editing.id ? { ...t, ...v } : t)));
      setEditing(null);
      push({ type: 'success', message: 'به‌روزرسانی شد' });
    } catch (e) {
      push({ type: 'error', message: e.message });
    }
  };

  const onArchive = async (t) => {
    if (!confirm(`«${t.name}» آرشیو شود؟`)) return;
    try {
      await api.del(`/tenants/${t.id}`);
      setList((arr) => arr.map((x) => (x.id === t.id ? { ...x, archived: 1 } : x)));
      push({ type: 'success', message: 'آرشیو شد' });
    } catch (e) {
      push({ type: 'error', message: e.message });
    }
  };

  const onSwitch = async (t) => {
    try {
      await api.post('/tenants/switch', { tenant_id: t.id });
      push({ type: 'success', message: `به «${t.name}» سوئیچ شدید` });
    } catch (e) {
      push({ type: 'error', message: e.message });
    }
  };

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p className="text-xs font-bold tracking-widest uppercase text-ink-500 dark:text-ink-400">
            عملیات
          </p>
          <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight text-ink-900 dark:text-ink-50 mt-1">
            شرکت‌ها (Multi-tenant)
          </h1>
          <p className="text-sm text-ink-500 dark:text-ink-400 mt-1">
            هر شرکت، داده‌های ایزولهٔ خودش را دارد. سوئیچ بدون خروج از حساب.
          </p>
        </div>
        <Button variant="primary" onClick={() => setCreating(true)}>+ شرکت جدید</Button>
      </div>

      {loading ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
          {Array.from({ length: 3 }).map((_, i) => <div key={i} className="skeleton h-40 rounded-2xl" />)}
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
          {list.map((t) => (
            <Card key={t.id} variant={t.is_default ? 'brutal' : 'glass'} className={cx(t.archived && 'opacity-60')}>
              <div className="flex items-start justify-between gap-2 mb-2">
                <div className="min-w-0">
                  <h3 className="text-base font-extrabold truncate">{t.name}</h3>
                  <p className="text-[11px] text-ink-500 dark:text-ink-400 font-mono">/{t.slug}</p>
                </div>
                <Badge variant={PLAN_VARIANT[t.plan] || 'default'}>{PLAN_LABEL[t.plan] || t.plan}</Badge>
              </div>

              <div className="grid grid-cols-3 gap-2 my-3">
                <Stat label="شعب"   v={t.branches} />
                <Stat label="اعضا"  v={t.members}  />
                <Stat label="پلن"   v={PLAN_LABEL[t.plan] || t.plan} />
              </div>

              <div className="text-[11px] text-ink-400 dark:text-ink-500">
                ایجاد: {formatJalaliShort(t.created_at)} · {timeAgo(t.created_at)}
              </div>

              <div className="flex flex-wrap gap-1 mt-3">
                {t.is_default ? (
                  <Badge variant="success">پیش‌فرض</Badge>
                ) : (
                  <Button size="xs" variant="ghost" onClick={() => onSwitch(t)}>سوئیچ</Button>
                )}
                <Button size="xs" variant="ghost" onClick={() => setEditing(t)}>ویرایش</Button>
                {!t.archived && (
                  <Button size="xs" variant="danger" onClick={() => onArchive(t)}>آرشیو</Button>
                )}
              </div>
            </Card>
          ))}
        </div>
      )}

      <TenantModal
        open={Boolean(editing)}
        title={`ویرایش: ${editing?.name || ''}`}
        initial={editing || {}}
        onClose={() => setEditing(null)}
        onSubmit={onSave}
      />
      <TenantModal
        open={creating}
        title="ساخت شرکت جدید"
        initial={{ name: '', slug: '', plan: 'starter' }}
        onClose={() => setCreating(false)}
        onSubmit={onCreate}
        submitLabel="ساخت"
      />
    </div>
  );
}

function Stat({ label, v }) {
  return (
    <div className="rounded-lg bg-ink-50 dark:bg-ink-900/60 px-2 py-1.5">
      <div className="text-[10px] text-ink-500">{label}</div>
      <div className="text-sm font-extrabold mt-0.5 ltr-num">{typeof v === 'number' ? v.toLocaleString('fa-IR') : v}</div>
    </div>
  );
}

function TenantModal({ open, onClose, title, initial, onSubmit, submitLabel = 'ذخیره' }) {
  const [v, setV] = useState(initial);
  React.useEffect(() => { if (open) setV({ ...initial }); }, [open, initial]);
  const set = (k, val) => setV((s) => ({ ...s, [k]: val }));

  const submit = (e) => {
    e?.preventDefault?.();
    if (!v.name) return;
    onSubmit(v);
  };

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={title}
      size="lg"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>انصراف</Button>
          <Button variant="primary" onClick={submit}>{submitLabel}</Button>
        </>
      }
    >
      <form onSubmit={submit} className="space-y-3">
        <Input label="نام شرکت *" value={v.name || ''} onChange={(e) => set('name', e.target.value)} required />
        <Input label="نامک (slug)" value={v.slug || ''} onChange={(e) => set('slug', e.target.value)} placeholder="alpha-co" />
        <Select
          label="پلن"
          value={v.plan || 'starter'}
          onChange={(e) => set('plan', e.target.value)}
          options={[
            { value: 'starter',    label: 'استارتر' },
            { value: 'pro',        label: 'حرفه‌ای' },
            { value: 'enterprise', label: 'سازمانی' },
          ]}
        />
        <Textarea label="توضیحات" value={v.description || ''} onChange={(e) => set('description', e.target.value)} rows={2} />
      </form>
    </Modal>
  );
}
