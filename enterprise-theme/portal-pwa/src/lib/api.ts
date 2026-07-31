// API client با JWT
const STORAGE_KEY = 'parsyar.portal.session';
const BASE = '/wp-json/enterprise/v1/portal';

export interface Session {
  access_token: string;
  access_exp: number;
  refresh_token: string;
  refresh_exp: number;
  token_type: string;
}

export interface Contact { id: number; full_name: string; email: string; phone: string; mobile: string; company: string; position: string; }
export interface Invoice { id: number; uuid: string; number: string; issue_date: string; due_date: string; status: string; total: number; paid: number; currency: string; tax_invoice_uid: string; }
export interface Order   { id: number; uuid: string; number: string; order_date: string; status: string; total: number; currency: string; }
export interface Payment { id: number; uuid: string; amount: number; currency: string; status: string; method: string; paid_at: string; gateway: string; ref_id: string; invoice_id: number; }
export interface Ticket  { id: number; uuid: string; subject: string; status: string; priority: string; category: string; created_at: string; updated_at: string; }

function loadSession(): Session | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const s = JSON.parse(raw) as Session;
    if (!s.access_token || s.access_exp < Math.floor(Date.now() / 1000)) return null;
    return s;
  } catch { return null; }
}

function saveSession(s: Session | null): void {
  if (s) localStorage.setItem(STORAGE_KEY, JSON.stringify(s));
  else localStorage.removeItem(STORAGE_KEY);
}

function authHeader(): Record<string, string> {
  const s = loadSession();
  return s ? { Authorization: `${s.token_type} ${s.access_token}` } : {};
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const url = `${BASE}${path}`;
  const r = await fetch(url, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      ...authHeader(),
      ...(init.headers || {})
    }
  });
  if (r.status === 401) {
    // try refresh once
    const refreshed = await tryRefresh();
    if (refreshed) {
      const r2 = await fetch(url, {
        ...init,
        headers: { 'Content-Type': 'application/json', ...authHeader(), ...(init.headers || {}) }
      });
      if (r2.ok) return (await r2.json()).data as T;
    }
    saveSession(null);
    throw new Error('unauthorized');
  }
  if (!r.ok) {
    const j = await r.json().catch(() => ({}));
    throw new Error(j.message || `HTTP ${r.status}`);
  }
  const j = await r.json();
  return j.data as T;
}

async function tryRefresh(): Promise<boolean> {
  const s = loadSession();
  if (!s) return false;
  try {
    const r = await fetch(`${BASE}/auth/refresh`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ refresh_token: s.refresh_token })
    });
    if (!r.ok) return false;
    const j = await r.json();
    saveSession(j.data as Session);
    return true;
  } catch { return false; }
}

export const api = {
  getSession: loadSession,
  setSession: saveSession,
  requestMagicLink: (email: string, device?: string) =>
    request<{ sent: boolean; contact_id: number }>(`/auth/magic-link`, {
      method: 'POST',
      body: JSON.stringify({ email, device_label: device })
    }),
  verifyMagicLink: (token: string) =>
    fetch(`${BASE}/auth/verify?token=${encodeURIComponent(token)}`).then(async (r) => {
      const j = await r.json();
      if (!r.ok) throw new Error(j.message || 'verify_failed');
      saveSession(j.data as Session);
      return j.data as Session;
    }),
  logout: () => request<{ revoked: boolean }>('/auth/logout', { method: 'POST' }).finally(() => saveSession(null)),
  vapidPublicKey: () => request<{ key: string }>('/auth/vapid-public-key'),
  me: () => request<Contact>('/me'),
  listInvoices: (filters: Record<string, string|number> = {}) => {
    const q = new URLSearchParams(filters as Record<string, string>).toString();
    return request<Invoice[]>(`/invoices${q ? '?' + q : ''}`);
  },
  listOrders: (filters: Record<string, string|number> = {}) => {
    const q = new URLSearchParams(filters as Record<string, string>).toString();
    return request<Order[]>(`/orders${q ? '?' + q : ''}`);
  },
  listPayments: () => request<Payment[]>('/payments'),
  listTickets: () => request<Ticket[]>('/tickets'),
  createTicket: (data: { subject: string; body: string; category: string; priority: string }) =>
    request<{ id: number }>('/tickets', { method: 'POST', body: JSON.stringify(data) }),
  pushSubscribe: (sub: PushSubscription) =>
    request<{ id: number }>('/push/subscribe', {
      method: 'POST',
      body: JSON.stringify({
        endpoint: sub.endpoint,
        keys: sub.toJSON().keys,
        user_agent: navigator.userAgent
      })
    }),
  pushUnsubscribe: (endpoint: string) =>
    request<{ deleted: boolean }>('/push/subscribe', {
      method: 'DELETE',
      body: JSON.stringify({ endpoint })
    }),
  logEvent: (type: string, payload: Record<string, unknown> = {}) =>
    request<{ id: number }>('/portal-event', {
      method: 'POST',
      body: JSON.stringify({ type, payload, client_ts: new Date().toISOString() })
    })
};
