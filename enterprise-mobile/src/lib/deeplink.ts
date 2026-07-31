/**
 * Deep-link router.
 *
 *  ParsYar supports two link shapes:
 *    parsyar://verify?token=…              (app scheme, offline-friendly)
 *    https://yourdomain.com/portal/verify?token=…  (Universal/App Link)
 *
 *  Both arrive in the same listener — we normalise the path and dispatch
 *  navigation actions on the root navigator. The actual navigation ref is
 *  set once in App.tsx via `setLinkingRef()`.
 */
import { Linking, EmitterSubscription, Platform } from 'react-native';

export type ParsYarDeepLink =
  | { type: 'verify'; token: string }
  | { type: 'invoice'; id: number }
  | { type: 'order'; id: number }
  | { type: 'payment'; id: number }
  | { type: 'ticket'; id: number }
  | { type: 'unknown'; raw: string };

type LinkHandler = (link: ParsYarDeepLink) => void;

const handlers = new Set<LinkHandler>();
let linkingSub: EmitterSubscription | null = null;

export function parseLink(url: string | null): ParsYarDeepLink | null {
  if (!url) return null;
  // Normalise: parsyar://verify?token=x  vs  https://x/portal/verify?token=x
  let path = url;
  if (path.startsWith('parsyar://')) {
    path = path.slice('parsyar://'.length);
  } else if (path.includes('/portal/')) {
    const idx = path.indexOf('/portal/');
    path = path.slice(idx + '/portal/'.length);
  } else {
    return { type: 'unknown', raw: url };
  }
  const [pathname, queryStr] = path.split('?');
  const params = new URLSearchParams(queryStr ?? '');
  const parts = pathname.split('/').filter(Boolean);

  if (parts[0] === 'verify' && params.get('token')) {
    return { type: 'verify', token: params.get('token')! };
  }
  if (parts[0] === 'invoices' && parts[1]) return { type: 'invoice', id: Number(parts[1]) };
  if (parts[0] === 'orders' && parts[1]) return { type: 'order', id: Number(parts[1]) };
  if (parts[0] === 'payments' && parts[1]) return { type: 'payment', id: Number(parts[1]) };
  if (parts[0] === 'tickets' && parts[1]) return { type: 'ticket', id: Number(parts[1]) };

  return { type: 'unknown', raw: url };
}

export function onDeepLink(handler: LinkHandler): () => void {
  handlers.add(handler);
  return () => handlers.delete(handler);
}

function dispatch(url: string | null): void {
  const link = parseLink(url);
  if (!link) return;
  handlers.forEach((h) => h(link));
}

export async function startDeepLinking(): Promise<void> {
  // Initial URL (cold start)
  const initial = await Linking.getInitialURL();
  dispatch(initial);

  // Runtime URLs (warm/hot)
  if (linkingSub) linkingSub.remove();
  linkingSub = Linking.addEventListener('url', (evt) => dispatch(evt.url));
}

export function stopDeepLinking(): void {
  linkingSub?.remove();
  linkingSub = null;
  handlers.clear();
}

/**
 * Helper to build a shareable magic-link URL for the current platform.
 * Backend issues the link; this is for client-side preview/test only.
 */
export function buildVerifyLink(token: string, baseUrl?: string): string {
  if (Platform.OS === 'android') {
    return `parsyar://verify?token=${encodeURIComponent(token)}`;
  }
  if (baseUrl) {
    return `${baseUrl.replace(/\/+$/, '')}/portal/verify?token=${encodeURIComponent(token)}`;
  }
  return `parsyar://verify?token=${encodeURIComponent(token)}`;
}
