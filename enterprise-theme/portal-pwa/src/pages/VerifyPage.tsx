import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';

export default function VerifyPage() {
  const { t } = useTranslation();
  const [params] = useSearchParams();
  const nav = useNavigate();
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    const token = params.get('token') || params.get('t') || '';
    if (!token) {
      setErr(t('auth.verifyFailed'));
      return;
    }
    api.verifyMagicLink(token)
      .then(() => {
        api.logEvent('portal_login_success', { via: 'magic_link' }).catch(() => {});
        nav('/dashboard', { replace: true });
      })
      .catch((e: Error) => setErr(e.message || t('auth.verifyFailed')));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <div className="min-h-screen grid place-items-center bg-slate-100 px-4">
      <div className="card w-full max-w-md text-center">
        {!err ? (
          <div className="text-slate-700">{t('auth.verifying')}</div>
        ) : (
          <>
            <div className="text-rose-600 text-sm mb-3">{err}</div>
            <button className="btn-ghost" onClick={() => nav('/login', { replace: true })}>
              {t('auth.backToLogin')}
            </button>
          </>
        )}
      </div>
    </div>
  );
}
