/**
 * ParsYar Customer Portal — REST client.
 * Talks to /wp-json/enterprise/v1/portal/* on the WordPress + ParsYar plugin.
 * Persists JWT (access + refresh) in localStorage with rotation on 401.
 */
import { type Session, type Profile, type Invoice, type Order, type Payment, type Ticket, type Notification } from './types';

const STORAGE_ACCESS    = 'parsyar:access';
const STORAGE_REFRESH   = 'parsyar:refresh';
const STORAGE_PROFILE   = 'parsyar:profile';
const STORAGE_BASE_URL  = 'parsyar:baseUrl';

export interface PortalConfig {
  apiBase: string;
  nonce: string;
  vapidPublicKey: string;
  company: { name: string; supportEmail: string; supportPhone: string };
}

// کانفیگ SPA از سمت WP تزریق می‌شود:
//   window.parsyarPortalConfig = { restUrl, nonce, vapidPublicKey, company, siteName, locale, ... }
// در dev، proxy ویتی به /wp-json می‌زنیم و از فالبک استفاده می‌کنیم.
interface RawPortalConfig {
  restUrl?: string;
  nonce?: string;
  vapidPublicKey?: string;
  company?: { name?: string; supportEmail?: string; supportPhone?: string };
  siteName?: string;
  locale?: string;
}

declare global {
  interface Window {
    PARSYAR_PORTAL_CONFIG?: PortalConfig;
    parsyarPortalConfig?: RawPortalConfig;
    parsyarPortal?: { logEvent: (type: string, payload?: Record<string, unknown>) => Promise<void> };
  }
}

function rawConfig(): RawPortalConfig {
  if (window.PARSYAR_PORTAL_CONFIG) {
    // already normalized (testing)
    const c = window.PARSYAR_PORTAL_CONFIG as unknown as RawPortalConfig;
    return c;
  }
  return window.parsyarPortalConfig || {};
}

function cfg(): PortalConfig {
  const r = rawConfig();
  const base = r.restUrl || '/wp-json/enterprise/v1/portal/';
  return {
    apiBase: base.endsWith('/') ? base.slice(0, -1) : base,
    nonce: r.nonce || '',
    vapidPublicKey: r.vapidPublicKey || '',
    company: {
      name: r.company?.name || r.siteName || 'ParsYar',
      supportEmail: r.company?.supportEmail || '',
      supportPhone: r.company?.supportPhone || '',
    },
  };
}

class ApiClient {
  private refreshing: Promise<Session | null> | null = null;

  // ----- session storage -----
  saveSession(s: Session): void { localStorage.setItem(STORAGE_ACCESS, JSON.stringify(s)); }
  getSession(): Session | null {
    const raw = localStorage.getItem(STORAGE_ACCESS);
    if (!raw) return null;
    try { return JSON.parse(raw) as Session; } catch { return null; }
  }
  clearSession(): void {
    localStorage.removeItem(STORAGE_ACCESS);
    localStorage.removeItem(STORAGE_REFRESH);
    localStorage.removeItem(STORAGE_PROFILE);
  }
  saveProfile(p: Profile): void { localStorage.setItem(STORAGE_PROFILE, JSON.stringify(p)); }
  getProfile(): Profile | null {
    const raw = localStorage.getItem(STORAGE_PROFILE);
    if (!raw) return null;
    try { return JSON.parse(raw) as Profile; } catch { return null; }
  }

  setBaseUrl(url: string): void { localStorage.setItem(STORAGE_BASE_URL, url); }
  getBaseUrl(): string {
    return localStorage.getItem(STORAGE_BASE_URL) || cfg().apiBase;
  }

  // ----- core fetch wrapper -----
  private async request<T>(path: string, init: RequestInit = {}, allowRefresh = true): Promise<T> {
    const url = this.getBaseUrl() + path;
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      ...(init.headers as Record<string, string> | undefined),
    };
    const session = this.getSession();
    if (session?.access_token) {
      headers['Authorization'] = `Bearer ${session.access_token}`;
    }
    const c = cfg();
    if (c.nonce) headers['X-WP-Nonce'] = c.nonce;

    let res = await fetch(url, { ...init, headers });
    if (res.status === 401 && allowRefresh && this.getSession()?.refresh_token) {
      const refreshed = await this.tryRefresh();
      if (refreshed) {
        headers['Authorization'] = `Bearer ${refreshed.access_token}`;
        res = await fetch(url, { ...init, headers });
      } else {
        this.clearSession();
      }
    }
    if (!res.ok) {
      let body: { message?: string; code?: string } = {};
      try { body = await res.json(); } catch { /* non-json */ }
      throw new Error(body.message || `HTTP ${res.status}`);
    }
    const text = await res.text();
    if (!text) return {} as T;
    try { return JSON.parse(text) as T; } catch { return {} as T; }
  }

  private async tryRefresh(): Promise<Session | null> {
    if (this.refreshing) return this.refreshing;
    const s = this.getSession();
    if (!s?.refresh_token) return null;
    this.refreshing = (async () => {
      try {
        const res = await fetch(this.getBaseUrl() + '/auth/refresh', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ refresh_token: s.refresh_token })
        });
        if (!res.ok) return null;
        const next = (await res.json()) as Session;
        this.saveSession(next);
        return next;
      } catch { return null; }
      finally { this.refreshing = null; }
    })();
    return this.refreshing;
  }

  // ----- auth -----
  requestMagicLink(email: string): Promise<{ ok: true; ttl: number; dev_link?: string }> {
    return this.request('/auth/magic-link', {
      method: 'POST',
      body: JSON.stringify({ email }),
    }, false);
  }
  verifyMagicLink(token: string): Promise<Session & { profile: Profile }> {
    return this.request('/auth/verify', {
      method: 'POST',
      body: JSON.stringify({ token }),
    }, false);
  }
  async logout(): Promise<void> {
    try { await this.request('/auth/logout', { method: 'POST' }); } catch { /* ignore */ }
    this.clearSession();
  }
  vapidPublicKey(): Promise<{ publicKey: string }> {
    return this.request('/auth/vapid-public-key', {}, false);
  }

  // ----- profile -----
  me(): Promise<Profile> { return this.request('/me'); }
  updateMe(p: Partial<Profile>): Promise<Profile> {
    return this.request('/me', { method: 'PUT', body: JSON.stringify(p) });
  }

  // ----- invoices -----
  invoices(): Promise<Invoice[]> { return this.request('/invoices'); }
  invoice(id: number): Promise<Invoice> { return this.request(`/invoices/${id}`); }

  // ----- orders -----
  orders(): Promise<Order[]> { return this.request('/orders'); }
  order(id: number): Promise<Order> { return this.request(`/orders/${id}`); }

  // ----- payments -----
  payments(): Promise<Payment[]> { return this.request('/payments'); }

  // ----- tickets -----
  tickets(): Promise<Ticket[]> { return this.request('/tickets'); }
  ticket(id: number): Promise<Ticket & { customer_reply?: string; staff_reply?: string }> {
    return this.request(`/tickets/${id}`);
  }
  createTicket(input: { subject: string; body: string; category?: string; priority?: string }): Promise<Ticket> {
    return this.request('/tickets', { method: 'POST', body: JSON.stringify(input) });
  }
  replyTicket(id: number, body: string): Promise<Ticket> {
    return this.request(`/tickets/${id}/reply`, { method: 'POST', body: JSON.stringify({ body }) });
  }

  // ----- quote requests -----
  requestQuote(notes: string, items?: { product_id?: number; qty?: number; note?: string }[]): Promise<{ uuid: string }> {
    return this.request('/quotes/request', { method: 'POST', body: JSON.stringify({ notes, items }) });
  }

  // ----- push -----
  subscribePush(sub: PushSubscription): Promise<{ ok: true }> {
    const j = sub.toJSON();
    return this.request('/push/subscribe', {
      method: 'POST',
      body: JSON.stringify({
        endpoint: j.endpoint,
        keys: j.keys,
      })
    });
  }
  unsubscribePush(endpoint: string): Promise<{ ok: true }> {
    return this.request('/push/subscribe', {
      method: 'DELETE',
      body: JSON.stringify({ endpoint })
    });
  }

  // ----- telemetry -----
  logEvent(type: string, payload: Record<string, unknown> = {}): Promise<{ ok: true }> {
    return this.request('/portal-event', {
      method: 'POST',
      body: JSON.stringify({ type, payload, client_ts: new Date().toISOString() })
    });
  }

  // ----- notifications (read history) -----
  notifications(): Promise<Notification[]> { return this.request('/notifications'); }
}

export const api = new ApiClient();

// helper: format currency (rial → toman in display)
export function formatCurrency(amount: number, currency = 'IRR'): string {
  try {
    return new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 0 }).format(amount) + ' ' + currency;
  } catch { return String(amount) + ' ' + currency; }
}

// helper: format date as Jalali-ish (using Intl fa-IR)
export function formatDateJalali(iso: string | null | undefined): string {
  if (!iso) return '—';
  try {
    const d = new Date(iso);
    return new Intl.DateTimeFormat('fa-IR', { year: 'numeric', month: 'long', day: 'numeric' }).format(d);
  } catch { return iso; }
}
