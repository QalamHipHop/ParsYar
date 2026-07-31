/**
 * ParsYar REST client.
 * Talks to /wp-json/enterprise/v1/portal/* on a WordPress + ParsYar plugin site.
 * Persists JWT (access + refresh) in AsyncStorage with rotation on 401.
 */
import axios, { AxiosInstance, AxiosError, InternalAxiosRequestConfig } from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';

const STORAGE_BASE_URL = '@parsyar:baseUrl';
const STORAGE_ACCESS    = '@parsyar:access';
const STORAGE_REFRESH   = '@parsyar:refresh';
const STORAGE_PROFILE   = '@parsyar:profile';

export interface Session {
  access_token: string;
  access_exp: number;
  refresh_token: string;
  refresh_exp: number;
  token_type: 'Bearer';
}

export interface Profile {
  id: number;
  uuid: string;
  full_name: string;
  email: string;
  phone: string;
  mobile: string;
  company: string;
  position: string;
}

export interface Invoice {
  id: number;
  uuid: string;
  number: string;
  issue_date: string;
  due_date: string;
  status: string;
  total: number;
  paid: number;
  currency: string;
  tax_invoice_uid: string;
}

export interface Order {
  id: number;
  uuid: string;
  number: string;
  order_date: string;
  status: string;
  total: number;
  currency: string;
}

export interface Payment {
  id: number;
  uuid: string;
  amount: number;
  currency: string;
  status: string;
  method: string;
  paid_at: string;
  gateway: string;
  ref_id: string;
  invoice_id: number;
}

export interface Ticket {
  id: number;
  uuid: string;
  subject: string;
  status: string;
  priority: string;
  category: string;
  created_at: string;
  updated_at: string;
}

class ApiClient {
  private instance!: AxiosInstance;
  private baseUrl: string = '';
  private refreshing: Promise<string> | null = null;
  private onUnauthorized?: () => void;

  setUnauthorizedHandler(fn: () => void) { this.onUnauthorized = fn; }

  async init(): Promise<void> {
    this.baseUrl = (await AsyncStorage.getItem(STORAGE_BASE_URL)) ?? '';
    this.instance = axios.create({
      baseURL: this.baseUrl,
      timeout: 15000,
      headers: { 'Content-Type': 'application/json' },
    });
    this.instance.interceptors.request.use(this.attachToken);
    this.instance.interceptors.response.use(undefined, this.handleError);
  }

  async setBaseUrl(url: string): Promise<void> {
    this.baseUrl = url.replace(/\/+$/, '');
    await AsyncStorage.setItem(STORAGE_BASE_URL, this.baseUrl);
    this.instance.defaults.baseURL = this.baseUrl;
  }

  getBaseUrl(): string { return this.baseUrl; }

  async clearSession(): Promise<void> {
    await AsyncStorage.multiRemove([STORAGE_ACCESS, STORAGE_REFRESH, STORAGE_PROFILE]);
  }

  async getSession(): Promise<Session | null> {
    const access = await AsyncStorage.getItem(STORAGE_ACCESS);
    const refresh = await AsyncStorage.getItem(STORAGE_REFRESH);
    if (!access || !refresh) return null;
    try {
      return JSON.parse(access) && JSON.parse(refresh)
        ? { access_token: JSON.parse(access).t, refresh_token: JSON.parse(refresh).t, access_exp: JSON.parse(access).e, refresh_exp: JSON.parse(refresh).e, token_type: 'Bearer' }
        : null;
    } catch { return null; }
  }

  private async storeTokens(s: Session): Promise<void> {
    await AsyncStorage.multiSet([
      [STORAGE_ACCESS,  JSON.stringify({ t: s.access_token,  e: s.access_exp  })],
      [STORAGE_REFRESH, JSON.stringify({ t: s.refresh_token, e: s.refresh_exp })],
    ]);
  }

  // -------- auth --------

  async requestMagicLink(email: string, device?: string): Promise<{ sent: boolean; contact_id: number }> {
    const { data } = await this.instance.post('/auth/magic-link', null, {
      params: { email, device_label: device },
    });
    return { sent: !!data?.data?.sent, contact_id: data?.data?.contact_id ?? 0 };
  }

  async verifyMagicLink(token: string): Promise<Session> {
    const { data } = await this.instance.get('/auth/verify', { params: { token } });
    const session = data.data as Session;
    await this.storeTokens(session);
    return session;
  }

  async refreshTokens(): Promise<string> {
    if (this.refreshing) return this.refreshing;
    this.refreshing = (async () => {
      const raw = await AsyncStorage.getItem(STORAGE_REFRESH);
      if (!raw) throw new Error('No refresh token');
      const { t } = JSON.parse(raw);
      const { data } = await axios.post(`${this.baseUrl}/auth/refresh`, { refresh_token: t });
      const session = data.data as Session;
      await this.storeTokens(session);
      this.refreshing = null;
      return session.access_token;
    })();
    try { return await this.refreshing; }
    catch (e) { this.refreshing = null; throw e; }
  }

  async logout(): Promise<void> {
    try { await this.instance.post('/auth/logout'); } catch { /* ignore */ }
    await this.clearSession();
  }

  // -------- profile --------

  async getProfile(): Promise<Profile> {
    const { data } = await this.instance.get('/me');
    await AsyncStorage.setItem(STORAGE_PROFILE, JSON.stringify(data.data));
    return data.data;
  }

  async getCachedProfile(): Promise<Profile | null> {
    const raw = await AsyncStorage.getItem(STORAGE_PROFILE);
    return raw ? JSON.parse(raw) : null;
  }

  // -------- data --------

  async listInvoices(filters: { status?: string; from?: string; to?: string } = {}, limit = 50, offset = 0): Promise<Invoice[]> {
    const { data } = await this.instance.get('/invoices', { params: { ...filters, limit, offset } });
    return data.data ?? [];
  }

  async listOrders(filters: { status?: string } = {}, limit = 50, offset = 0): Promise<Order[]> {
    const { data } = await this.instance.get('/orders', { params: { ...filters, limit, offset } });
    return data.data ?? [];
  }

  async listPayments(limit = 50, offset = 0): Promise<Payment[]> {
    const { data } = await this.instance.get('/payments', { params: { limit, offset } });
    return data.data ?? [];
  }

  async listTickets(filters: { status?: string } = {}, limit = 50, offset = 0): Promise<Ticket[]> {
    const { data } = await this.instance.get('/tickets', { params: { ...filters, limit, offset } });
    return data.data ?? [];
  }

  async getTicket(id: number): Promise<Ticket> {
    const { data } = await this.instance.get(`/tickets/${id}`);
    return data.data;
  }

  async createTicket(payload: { subject: string; body: string; category?: string; priority?: string }): Promise<{ id: number }> {
    const { data } = await this.instance.post('/tickets', payload);
    return data.data;
  }

  async replyTicket(id: number, body: string): Promise<void> {
    await this.instance.post(`/tickets/${id}/reply`, { body });
  }

  async logEvent(type: string, payload: Record<string, unknown> = {}): Promise<void> {
    try { await this.instance.post('/portal-event', { type, payload }); } catch { /* ignore */ }
  }

  // -------- interceptors --------

  private attachToken = async (config: InternalAxiosRequestConfig): Promise<InternalAxiosRequestConfig> => {
    const raw = await AsyncStorage.getItem(STORAGE_ACCESS);
    if (raw) {
      try {
        const { t, e } = JSON.parse(raw);
        if (Date.now() / 1000 < e) {
          config.headers = config.headers ?? ({} as any);
          (config.headers as any).Authorization = `Bearer ${t}`;
        }
      } catch { /* ignore */ }
    }
    return config;
  };

  private handleError = async (err: AxiosError): Promise<unknown> => {
    const original = err.config as InternalAxiosRequestConfig & { _retried?: boolean };
    if (err.response?.status === 401 && original && !original._retried) {
      original._retried = true;
      try {
        const newToken = await this.refreshTokens();
        original.headers = original.headers ?? ({} as any);
        (original.headers as any).Authorization = `Bearer ${newToken}`;
        return this.instance.request(original);
      } catch (refreshErr) {
        await this.clearSession();
        this.onUnauthorized?.();
        return Promise.reject(refreshErr);
      }
    }
    return Promise.reject(err);
  };
}

export const api = new ApiClient();
export const formatCurrency = (amount: number, currency = 'IRR'): string => {
  try {
    return new Intl.NumberFormat('fa-IR', { style: 'currency', currency, maximumFractionDigits: 0 }).format(amount);
  } catch {
    return `${amount.toLocaleString('fa-IR')} ${currency}`;
  }
};
export const formatDateJalali = (iso: string): string => {
  if (!iso) return '';
  try {
    return new Intl.DateTimeFormat('fa-IR', { year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(iso));
  } catch { return iso; }
};
