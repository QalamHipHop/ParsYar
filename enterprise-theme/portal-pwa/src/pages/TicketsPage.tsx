import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { api, formatDateJalali } from '../lib/api';
import type { Ticket } from '../lib/types';

interface Props { initialNew?: boolean }

export default function TicketsPage({ initialNew = false }: Props) {
  const { t } = useTranslation();
  const [items, setItems] = useState<Ticket[]>([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState<string | null>(null);
  const [creating, setCreating] = useState<boolean>(initialNew);
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [category, setCategory] = useState('other');
  const [priority, setPriority] = useState('normal');
  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState<string | null>(null);

  const load = async () => {
    setLoading(true);
    try { setItems(await api.tickets()); }
    catch (e: unknown) { setErr(e instanceof Error ? e.message : 'error'); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(); }, []);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErr(null);
    setSuccess(null);
    if (!subject.trim() || !body.trim()) return;
    setSubmitting(true);
    try {
      const t0 = await api.createTicket({ subject: subject.trim(), body: body.trim(), category, priority });
      setSuccess(`تیکت #${t0.id} ثبت شد.`);
      setSubject(''); setBody('');
      setCreating(false);
      await load();
    } catch (e: unknown) {
      setErr(e instanceof Error ? e.message : 'error');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h1 className="text-lg font-semibold">{t('ticket.title')}</h1>
        <button onClick={() => setCreating(v => !v)} className="btn-primary text-xs">
          {creating ? t('common.cancel') : t('ticket.new')}
        </button>
      </div>

      {creating && (
        <form onSubmit={submit} className="card space-y-3">
          <div>
            <label className="label">{t('ticket.subject')}</label>
            <input className="input" value={subject} onChange={e => setSubject(e.target.value)} required maxLength={200} />
          </div>
          <div>
            <label className="label">{t('ticket.body')}</label>
            <textarea className="input min-h-[100px]" value={body} onChange={e => setBody(e.target.value)} required />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="label">{t('ticket.category')}</label>
              <select className="input" value={category} onChange={e => setCategory(e.target.value)}>
                {['other', 'billing', 'technical', 'sales'].map(c =>
                  <option key={c} value={c}>{t(`ticket.cat.${c}` as any)}</option>
                )}
              </select>
            </div>
            <div>
              <label className="label">{t('ticket.priority')}</label>
              <select className="input" value={priority} onChange={e => setPriority(e.target.value)}>
                {['low', 'normal', 'high', 'urgent'].map(p =>
                  <option key={p} value={p}>{t(`ticket.pri.${p}` as any)}</option>
                )}
              </select>
            </div>
          </div>
          {err && <div className="text-sm text-rose-600">{err}</div>}
          {success && <div className="text-sm text-emerald-700">{success}</div>}
          <button type="submit" className="btn-primary w-full" disabled={submitting}>
            {submitting ? t('common.loading') : t('ticket.create')}
          </button>
        </form>
      )}

      {loading && <div className="card text-sm text-slate-500">{t('common.loading')}</div>}
      {!loading && !items.length && <div className="card text-sm text-slate-500">{t('common.empty')}</div>}
      <div className="space-y-2">
        {items.map(tk => (
          <div key={tk.id} className="card">
            <div className="flex items-center justify-between">
              <div className="font-semibold text-sm">{tk.subject}</div>
              <span className={
                tk.priority === 'urgent' ? 'badge-err' :
                tk.priority === 'high' ? 'badge-warn' : 'badge-neutral'
              }>{tk.priority}</span>
            </div>
            <div className="text-xs text-slate-500 mt-1">
              #{tk.id} · {tk.status} · {formatDateJalali(tk.created_at)}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
