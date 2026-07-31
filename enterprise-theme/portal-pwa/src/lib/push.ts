// WebPush subscription helper
import { api } from './api';

function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = atob(base64);
  const out = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; ++i) out[i] = raw.charCodeAt(i);
  return out;
}

export async function enablePush(): Promise<boolean> {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) return false;
  try {
    const reg = await navigator.serviceWorker.ready;
    let sub = await reg.pushManager.getSubscription();
    if (!sub) {
      const { publicKey } = await api.vapidPublicKey();
      const keyBytes = urlBase64ToUint8Array(publicKey);
      sub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        // The DOM lib expects a BufferSource; cast through unknown to avoid
        // the strict ArrayBuffer vs SharedArrayBuffer mismatch in TS 5.5+.
        applicationServerKey: keyBytes as unknown as BufferSource,
      });
    }
    await api.subscribePush(sub);
    await api.logEvent('push_enabled', { endpoint_prefix: sub.endpoint.slice(0, 32) });
    return true;
  } catch (e) {
    // eslint-disable-next-line no-console
    console.warn('[push] subscribe failed', e);
    return false;
  }
}

export async function disablePush(): Promise<boolean> {
  if (!('serviceWorker' in navigator)) return false;
  try {
    const reg = await navigator.serviceWorker.ready;
    const sub = await reg.pushManager.getSubscription();
    if (sub) {
      await api.unsubscribePush(sub.endpoint);
      await sub.unsubscribe();
    }
    return true;
  } catch { return false; }
}
