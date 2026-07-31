import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';

function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = atob(base64);
  const output = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; ++i) output[i] = rawData.charCodeAt(i);
  return output;
}

export function InstallBanner() {
  const { t } = useTranslation();
  const [evt, setEvt] = useState<any>(null);
  const [dismissed, setDismissed] = useState<boolean>(
    localStorage.getItem('parsyar:install_dismissed') === '1'
  );

  useEffect(() => {
    const onBeforeInstall = (e: Event) => { e.preventDefault(); setEvt(e); };
    window.addEventListener('beforeinstallprompt', onBeforeInstall as any);
    return () => window.removeEventListener('beforeinstallprompt', onBeforeInstall as any);
  }, []);

  if (!evt || dismissed) return null;
  const install = async () => {
    setEvt(null);
    await (evt as any).prompt();
    localStorage.setItem('parsyar:install_dismissed', '1');
    setDismissed(true);
  };
  return (
    <div className="slide-up fixed inset-x-3 bottom-3 z-30">
      <div className="card shadow-lg flex items-start gap-3">
        <div className="h-10 w-10 rounded-lg bg-brand-600 grid place-items-center text-white font-bold">پ</div>
        <div className="flex-1">
          <div className="font-semibold text-sm">{t('install.title')}</div>
          <div className="text-xs text-slate-500">{t('install.body')}</div>
        </div>
        <div className="flex flex-col gap-1">
          <button className="btn-primary text-xs" onClick={install}>{t('install.install')}</button>
          <button className="btn-ghost text-xs" onClick={() => { localStorage.setItem('parsyar:install_dismissed', '1'); setDismissed(true); }}>
            {t('install.dismiss')}
          </button>
        </div>
      </div>
    </div>
  );
}

export function PushBanner() {
  const { t } = useTranslation();
  const [show, setShow] = useState(false);
  const [subscribed, setSubscribed] = useState(false);

  useEffect(() => {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
    if (localStorage.getItem('parsyar:push_dismissed') === '1') return;
    const t = setTimeout(() => setShow(true), 5000);
    (async () => {
      try {
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        setSubscribed(!!sub);
      } catch { /* ignore */ }
    })();
    return () => clearTimeout(t);
  }, []);

  if (!show || subscribed) return null;

  const enable = async () => {
    try {
      const reg = await navigator.serviceWorker.ready;
      const { publicKey } = await api.vapidPublicKey();
      const perm = await Notification.requestPermission();
      if (perm !== 'granted') { setShow(false); return; }
      const sub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(publicKey) as unknown as BufferSource,
      });
      await api.subscribePush(sub);
      setSubscribed(true);
      setShow(false);
    } catch (e) {
      setShow(false);
    }
  };
  const dismiss = () => {
    localStorage.setItem('parsyar:push_dismissed', '1');
    setShow(false);
  };

  return (
    <div className="slide-up fixed inset-x-3 bottom-24 z-20">
      <div className="card shadow-md">
        <div className="font-semibold text-sm mb-1">{t('push.title')}</div>
        <div className="text-xs text-slate-500 mb-2">{t('push.body')}</div>
        <div className="flex gap-2">
          <button className="btn-primary text-xs" onClick={enable}>{t('push.enable')}</button>
          <button className="btn-ghost text-xs" onClick={dismiss}>{t('push.dismiss')}</button>
        </div>
      </div>
    </div>
  );
}
