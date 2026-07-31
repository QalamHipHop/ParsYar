import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { api } from '../lib/api';

export default function LoginPage() {
  const { t } = useTranslation();
  const nav = useNavigate();
  const [email, setEmail] = useState('');
  const [device, setDevice] = useState('');
  const [sending, setSending] = useState(false);
  const [sent, setSent] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErr(null);
    setSending(true);
    try {
      await api.requestMagicLink(email, device || undefined);
      setSent(true);
    } catch (e: unknown) {
      setErr((e as Error).message);
    } finally {
      setSending(false);
    }
  };

  return (
    <div className="min-h-screen grid place-items-center bg-gradient-to-br from-slate-100 to-slate-200 px-4">
      <div className="card w-full max-w-md">
        <div className="text-center mb-6">
          <div className="mx-auto h-12 w-12 rounded-xl bg-brand-600 grid place-items-center text-white text-xl font-bold mb-3">پ</div>
          <h1 className="text-lg font-bold">{t('auth.title')}</h1>
          <p className="text-xs text-slate-500 mt-1">ParsYar Customer Portal</p>
        </div>

        {sent ? (
          <div className="text-center">
            <div className="text-emerald-600 text-sm mb-4">{t('auth.sent')}</div>
            <button className="btn-ghost" onClick={() => nav('/login', { replace: true })}>
              {t('auth.backToLogin')}
            </button>
          </div>
        ) : (
          <form onSubmit={submit} className="space-y-3">
            <div>
              <label className="label">{t('auth.email')}</label>
              <input
                className="input"
                type="email"
                inputMode="email"
                autoComplete="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="name@company.com"
              />
            </div>
            <div>
              <label className="label">{t('auth.device')}</label>
              <input
                className="input"
                type="text"
                value={device}
                onChange={(e) => setDevice(e.target.value)}
                placeholder="مثلاً: آیفون من"
              />
            </div>
            {err && <div className="text-rose-600 text-xs">{err}</div>}
            <button type="submit" className="btn-primary w-full" disabled={sending}>
              {sending ? t('common.loading') : t('auth.send')}
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
