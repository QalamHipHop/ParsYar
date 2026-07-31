/**
 * Shared TypeScript types for the ParsYar Customer Portal PWA.
 */

export interface Session {
  access_token: string;
  access_exp: number;        // unix seconds
  refresh_token: string;
  refresh_exp: number;       // unix seconds
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
  tax_invoice_uid?: string | null;
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

export interface Notification {
  id: number;
  type: string;
  title: string;
  body: string;
  url?: string;
  read_at: string | null;
  created_at: string;
}
