import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { api, type Ticket } from '../lib/api';
import { fmtDate } from '../lib/format';

const CATS = ['billing', 'technical', 'sales', 'shipping', 'other'] as const;
const PRIS = ['low', 'normal', 'high', 'urgent'] as const;

export default function TicketsPage({ initialNew = false }: { initialNew?: boolean }) {
  const { t } = useTranslation();
  const [items, setItems] = useState<Ticket[]>([]);
  const [open, setOpen] = useState(initialNew);
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [cat, setCat] = useState<typeof CATS[number]>('other');
  const [pri, setPri] = useState<typeof PRIS[number]>('normal');
  const [sending, setSending] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  const load = () => api.listTickets().then(setItems).catch(() => {});
  useEffect(() => { load(); }, []);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErr(null);
    setSending(true);
    try {
      await api.createTicket({ subject, body, category: cat, priority: pri });
      setSubject(''); setBody(''); setOpen(false);
      await load();
    } catch (e: unknown) {
      setErr((e as Error).message);
    } finally {
      setSending(false);
    }
  };

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between">
        <h1 className="text-base font-bold">{t('nav.tickets')}</h1>
        <button onClick={() => setOpen(o => !o)} className="btn-primary">
          {open ? t('common.cancel') : t('ticket.new')}
        </button>
      </div>

      {open && (
        <form onSubmit={submit} className="card space-y-3">
          <div>
            <label className="label">{t('ticket.subject')}</label>
            <input className="input" required minLength={3} value={subject} onChange={e => setSubject(e.target.value)} />
          </div>
          <div className="grid grid-cols-2 gap-2">
            <div>
              <label className="label">{t('ticket.category')}</label>
              <select className="input" value={cat} onChange={e => setCat(e.target.value as typeof CATS[number])}>
                {CATS.map(c => <option key={c} value={c}>{t(`ticket.${c}`)}</option>)}
              </select>
            </div>
            <div>
              <label className="label">{t('ticket.priority')}</label>
              <select className="input" value={pri} onChange={e => setPri(e.target.value as typeof PRIS[number])}>
                {PRIS.map(p => <option key={p} value={p}>{t(`ticket.${p}`)}</option>)}
              </select>
            </div>
          </div>
          <div>
            <label className="label">{t('ticket.body')}</label>
            <textarea className="input min-h-[120px]" required minLength={10} value={body} onChange={e => setBody(e.target.value)} />
          </div>
          {err && <div className="text-rose-600 text-xs">{err}</div>}
          <button type="submit" className="btn-primary w-full" disabled={sending}>
            {sending ? t('common.loading') : t('ticket.submit')}
          </button>
        </form>
      )}

      {items.length === 0 && !open && <div className="card text-center text-xs text-slate-500">{t('dashboard.noData')}</div>}
      {items.map(tk => (
        <div key={tk.id} className="card">
          <div className="flex items-center justify-between">
            <div className="font-semibold text-sm">{tk.subject}</div>
            <div className="flex gap-1">
              <span className="badge-neutral">{tk.status}</span>
              <span className="badge-warn">{tk.priority}</span>
            </div>
          </div>
          <div className="text-xs text-slate-500 mt-1">{fmtDate(tk.created_at)} · {t(`ticket.${tk.category}`)}</div>
        </div>
      ))}
    </div>
  );
}
