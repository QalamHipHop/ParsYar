import React, { useEffect, useState } from 'react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';

export default function VerifyPage() {
  const { t } = useTranslation();
  const [params] = useSearchParams();
  const nav = useNavigate();
  const [state, setState] = useState<'pending' | 'ok' | 'err'>('pending');
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    const token = params.get('token') || '';
    if (!token) { setState('err'); setErr('missing token'); return; }
    (async () => {
      try {
        const r = await api.verifyMagicLink(token);
        api.saveSession({
          access_token: r.access_token,
          access_exp: r.access_exp,
          refresh_token: r.refresh_token,
          refresh_exp: r.refresh_exp,
          token_type: r.token_type || 'Bearer',
        });
        api.saveProfile(r.profile);
        setState('ok');
        api.logEvent('login_magic_verified', {}).catch(() => {});
        setTimeout(() => nav('/dashboard', { replace: true }), 600);
      } catch (e: unknown) {
        setState('err');
        setErr(e instanceof Error ? e.message : 'invalid');
      }
    })();
  }, [params, nav]);

  return (
    <div className="min-h-full grid place-items-center px-4 py-10">
      <div className="card w-full max-w-md text-center">
        <h1 className="text-lg font-semibold mb-2">{t('verify.title')}</h1>
        {state === 'pending' && <p className="text-sm text-slate-500">{t('verify.inProgress')}</p>}
        {state === 'ok' && <p className="text-sm text-emerald-700">{t('verify.success')}</p>}
        {state === 'err' && (
          <div className="space-y-3">
            <p className="text-sm text-rose-600">{t('verify.failed')}</p>
            {err && <p className="text-xs text-slate-500">{err}</p>}
            <Link to="/login" className="btn-primary inline-block">{t('verify.backToLogin')}</Link>
          </div>
        )}
      </div>
    </div>
  );
}
