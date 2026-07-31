import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { enablePush, disablePush } from '../lib/push';

interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

export function InstallBanner() {
  const { t } = useTranslation();
  const [evt, setEvt] = useState<BeforeInstallPromptEvent | null>(null);
  const [dismissed, setDismissed] = useState(localStorage.getItem('parsyar.install.dismissed') === '1');

  useEffect(() => {
    const handler = (e: Event) => {
      e.preventDefault();
      setEvt(e as BeforeInstallPromptEvent);
    };
    window.addEventListener('beforeinstallprompt', handler);
    return () => window.removeEventListener('beforeinstallprompt', handler);
  }, []);

  if (!evt || dismissed) return null;

  const install = async () => {
    await evt.prompt();
    const r = await evt.userChoice;
    if (r.outcome === 'accepted') setEvt(null);
    setDismissed(true);
    localStorage.setItem('parsyar.install.dismissed', '1');
  };

  return (
    <div className="fixed inset-x-0 bottom-16 z-30 mx-auto max-w-md px-4">
      <div className="card slide-up flex items-start gap-3">
        <div className="flex-1">
          <div className="font-semibold text-sm">{t('install.title')}</div>
          <div className="text-xs text-slate-600 mt-0.5">{t('install.body')}</div>
        </div>
        <button onClick={install} className="btn-primary">{t('install.install')}</button>
        <button
          onClick={() => { setDismissed(true); localStorage.setItem('parsyar.install.dismissed', '1'); }}
          className="btn-ghost text-xs"
        >
          {t('install.dismiss')}
        </button>
      </div>
    </div>
  );
}

export function PushBanner() {
  const { t } = useTranslation();
  const [show, setShow] = useState(false);
  const [dismissed, setDismissed] = useState(localStorage.getItem('parsyar.push.dismissed') === '1');

  useEffect(() => {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
    if (Notification.permission === 'default' && !dismissed) {
      // نمایش بعد از 5 ثانیه
      const id = window.setTimeout(() => setShow(true), 5000);
      return () => clearTimeout(id);
    }
  }, [dismissed]);

  if (!show || dismissed) return null;

  const enable = async () => {
    const ok = await enablePush();
    setShow(false);
    setDismissed(true);
    localStorage.setItem('parsyar.push.dismissed', '1');
    // eslint-disable-next-line no-console
    console.info('[push] enabled =', ok);
  };

  return (
    <div className="fixed inset-x-0 top-14 z-30 mx-auto max-w-md px-4">
      <div className="card slide-up border-brand-500 flex items-start gap-3">
        <div className="flex-1">
          <div className="font-semibold text-sm">{t('push.enableTitle')}</div>
          <div className="text-xs text-slate-600 mt-0.5">{t('push.enableBody')}</div>
        </div>
        <button onClick={enable} className="btn-primary">{t('push.enable')}</button>
        <button
          onClick={() => { setDismissed(true); localStorage.setItem('parsyar.push.dismissed', '1'); }}
          className="btn-ghost text-xs"
        >
          {t('push.dismiss')}
        </button>
        {/* disablePush exported for parity; not used in UI but kept for future */}
        <span hidden aria-hidden onClick={() => disablePush()} />
      </div>
    </div>
  );
}
