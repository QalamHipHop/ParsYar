import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';

export default function LoginPage() {
  const { t } = useTranslation();
  const nav = useNavigate();
  const [email, setEmail] = useState('');
  const [sending, setSending] = useState(false);
  const [sent, setSent] = useState(false);
  const [devLink, setDevLink] = useState<string | null>(null);
  const [err, setErr] = useState<string | null>(null);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErr(null);
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
      setErr(t('login.invalidEmail'));
      return;
    }
    setSending(true);
    try {
      const r = await api.requestMagicLink(email);
      setSent(true);
      if (r.dev_link) setDevLink(r.dev_link);
      api.logEvent('login_magic_requested', { email_domain: email.split('@')[1] }).catch(() => {});
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : 'error';
      if (msg.toLowerCase().includes('rate')) setErr(t('login.rateLimited'));
      else setErr(msg);
    } finally {
      setSending(false);
    }
  };

  return (
    <div className="min-h-full grid place-items-center px-4 py-10">
      <div className="card w-full max-w-md">
        <div className="flex items-center gap-2 mb-4">
          <div className="h-10 w-10 rounded-lg bg-brand-600 grid place-items-center text-white font-bold">پ</div>
          <div>
            <div className="text-base font-semibold">{t('app.name')}</div>
            <div className="text-xs text-slate-500">ParsYar Portal</div>
          </div>
        </div>
        <h1 className="text-lg font-semibold mb-1">{t('login.title')}</h1>
        <p className="text-sm text-slate-500 mb-4">{t('login.subtitle')}</p>
        {sent ? (
          <div className="space-y-3">
            <div className="rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-900 px-3 py-2 text-sm">
              {t('login.sent')}
            </div>
            {devLink && (
              <div className="rounded-lg bg-amber-50 border border-amber-200 text-amber-900 px-3 py-2 text-sm space-y-1">
                <div className="font-medium">{t('login.devHint')}</div>
                <a className="break-all text-xs underline" href={devLink} onClick={(e) => { e.preventDefault(); nav('/portal-action/verify?token=' + encodeURIComponent(devLink.split('token=')[1] || '')); }}>
                  {devLink}
                </a>
              </div>
            )}
            <Link to="/login" className="btn-ghost w-full">{t('verify.backToLogin')}</Link>
          </div>
        ) : (
          <form onSubmit={submit} className="space-y-3">
            <div>
              <label className="label">{t('login.email')}</label>
              <input
                type="email"
                inputMode="email"
                autoComplete="email"
                className="input"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder={t('login.emailPlaceholder') as string}
                required
                dir="ltr"
              />
            </div>
            {err && <div className="text-sm text-rose-600">{err}</div>}
            <button type="submit" className="btn-primary w-full" disabled={sending}>
              {sending ? t('common.loading') : t('login.send')}
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
