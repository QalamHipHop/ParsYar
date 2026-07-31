/**
 * ParsYar REST client (Admin SPA)
 * - /wp-json/parsyar/v1
 * - X-WP-Nonce, X-ParsYar-Company / X-ParsYar-Branch for multi-tenant
 * - Uniform envelope: { success, data, meta }
 */

const BASE = (window.EnterpriseConfig?.restUrl || '/wp-json/parsyar/v1').replace(/\/$/, '');

function getNonce() {
  return window.EnterpriseConfig?.nonce || '';
}
function getHeaders() {
  return {
    'Content-Type': 'application/json',
    'X-WP-Nonce': getNonce(),
    ...(window.EnterpriseConfig?.tenantId ? { 'X-ParsYar-Company': String(window.EnterpriseConfig.tenantId) } : {}),
    ...(window.EnterpriseConfig?.branchId  ? { 'X-ParsYar-Branch':  String(window.EnterpriseConfig.branchId)  } : {}),
  };
}

async function request(path, { method = 'GET', body, signal } = {}) {
  const res = await fetch(BASE + path, {
    method,
    credentials: 'include',
    signal,
    headers: getHeaders(),
    body: body ? JSON.stringify(body) : undefined,
  });
  if (!res.ok) {
    let msg = `HTTP ${res.status}`;
    try {
      const j = await res.json();
      msg = j?.error?.message || j?.message || msg;
    } catch { /* not json */ }
    const err = new Error(msg);
    err.status = res.status;
    err.path = path;
    throw err;
  }
  if (res.status === 204) return null;
  const text = await res.text();
  if (!text) return null;
  try {
    const j = JSON.parse(text);
    // unwrap envelope
    if (j && typeof j === 'object' && 'success' in j) {
      if (!j.success) {
        const e = new Error(j?.error?.message || 'request failed');
        e.code = j?.error?.code;
        e.status = res.status;
        throw e;
      }
      return j.data ?? j;
    }
    return j;
  } catch (e) {
    if (e instanceof SyntaxError) return text;
    throw e;
  }
}

export const api = {
  get:  (p, o)    => request(p, { ...o, method: 'GET' }),
  post: (p, b, o) => request(p, { ...o, method: 'POST', body: b }),
  put:  (p, b, o) => request(p, { ...o, method: 'PUT',  body: b }),
  del:  (p, o)    => request(p, { ...o, method: 'DELETE' }),
  raw:  request,

  // ─── System ───
  status:       ()    => request('/status'),

  // ─── Objects ───
  objects:        ()      => request('/objects'),
  object:         (api)   => request(`/objects/${api}`),
  records:        (api, p = {}) => {
    const qs = new URLSearchParams(p).toString();
    return request(`/objects/${api}/records${qs ? '?' + qs : ''}`);
  },
  record:         (id)    => request(`/records/${id}`),
  createRecord:   (api, b)=> request(`/objects/${api}/records`, { method: 'POST', body: b }),
  updateRecord:   (id, b) => request(`/records/${id}`, { method: 'PUT', body: b }),
  deleteRecord:   (id)    => request(`/records/${id}`, { method: 'DELETE' }),

  // ─── CRM ───
  leads:          ()      => request('/crm/leads'),
  lead:           (id)    => request(`/crm/leads/${id}`),
  createLead:     (b)     => request('/crm/leads', { method: 'POST', body: b }),
  contacts:       ()      => request('/crm/contacts'),
  createContact:  (b)     => request('/crm/contacts', { method: 'POST', body: b }),
  deals:          ()      => request('/crm/deals'),
  createDeal:     (b)     => request('/crm/deals', { method: 'POST', body: b }),
  pipelines:      ()      => request('/crm/pipelines'),
  activities:     ()      => request('/crm/activities'),

  // ─── ERP ───
  products:       ()      => request('/erp/products'),
  createProduct:  (b)     => request('/erp/products', { method: 'POST', body: b }),
  warehouses:     ()      => request('/erp/warehouses'),
  invoices:       (p = {}) => { const qs = new URLSearchParams(p).toString(); return request(`/erp/invoices${qs ? '?' + qs : ''}`); },
  createInvoice:  (b)     => request('/erp/invoices', { method: 'POST', body: b }),
  orders:         ()      => request('/erp/orders'),
  payments:       ()      => request('/erp/payments'),
  refunds:        ()      => request('/erp/refunds'),

  // ─── HRM ───
  employees:      ()      => request('/hrm/employees'),
  createEmployee: (b)     => request('/hrm/employees', { method: 'POST', body: b }),
  attendance:     ()      => request('/hrm/attendance'),
  checkIn:        (b)     => request('/hrm/attendance/check-in', { method: 'POST', body: b }),
  checkOut:       (b)     => request('/hrm/attendance/check-out', { method: 'POST', body: b }),
  runPayroll:     (b)     => request('/hrm/payroll/run', { method: 'POST', body: b }),

  // ─── Accounting ───
  accounts:       ()      => request('/accounting/accounts'),
  journal:        (p = {}) => { const qs = new URLSearchParams(p).toString(); return request(`/accounting/journal${qs ? '?' + qs : ''}`); },
  postEntry:      (b)     => request('/accounting/journal', { method: 'POST', body: b }),
  trialBalance:   ()      => request('/accounting/trial-balance'),
  incomeStatement:()      => request('/accounting/income'),
  balanceSheet:   ()      => request('/accounting/balance-sheet'),

  // ─── Workflows ───
  workflows:      ()      => request('/workflows'),
  workflow:       (id)    => request(`/workflows/${id}`),
  createWorkflow: (b)     => request('/workflows', { method: 'POST', body: b }),
  updateWorkflow: (id, b) => request(`/workflows/${id}`, { method: 'PUT', body: b }),
  deleteWorkflow: (id)    => request(`/workflows/${id}`, { method: 'DELETE' }),
  duplicateWorkflow: (id, name) => request(`/workflows/${id}/duplicate`, { method: 'POST', body: { name } }),
  runWorkflow:    (id, payload = {}) => request(`/workflows/${id}/run`, { method: 'POST', body: payload }),
  workflowRuns:   (id)    => request(`/workflows/${id}/runs`),
  workflowLogs:   (id)    => request(`/workflows/${id}/logs`),
  workflowTemplates: ()   => request('/workflows/templates'),
  workflowTriggers:  ()   => request('/workflows/triggers'),
  workflowNodeTypes: ()   => request('/workflows/node-types'),
  workflowStats:     ()   => request('/workflows/stats'),

  // ─── Reports ───
  reports:         ()      => request('/reports'),
  report:          (id)    => request(`/reports/${id}`),
  createReport:    (b)     => request('/reports', { method: 'POST', body: b }),
  updateReport:    (id, b) => request(`/reports/${id}`, { method: 'PUT', body: b }),
  deleteReport:    (id)    => request(`/reports/${id}`, { method: 'DELETE' }),
  runReport:       (id, p) => request(`/reports/${id}/run${p ? '?' + new URLSearchParams(p).toString() : ''}`),
  previewReport:   (b)     => request('/reports/preview', { method: 'POST', body: b }),
  reportSources:   ()      => request('/reports/sources'),
  reportMeta:      (source)=> request(`/reports/meta?source=${encodeURIComponent(source)}`),
  reportTemplates: ()      => request('/reports/templates'),

  // ─── Audit ───
  audit:          (p = {}) => { const qs = new URLSearchParams(p).toString(); return request(`/audit${qs ? '?' + qs : ''}`); },

  // ─── Multitenant ───
  tenants:        ()      => request('/tenants'),
  tenant:         (id)    => request(`/tenants/${id}`),
  createTenant:   (b)     => request('/tenants', { method: 'POST', body: b }),
  updateTenant:   (id, b) => request(`/tenants/${id}`, { method: 'PUT', body: b }),
  archiveTenant:  (id)    => request(`/tenants/${id}`, { method: 'DELETE' }),
  currentTenant:  ()      => request('/tenants/current'),
  myMemberships:  ()      => request('/tenants/me'),
  switchTenant:   (b)     => request('/tenants/switch', { method: 'POST', body: b }),
  branches:       (tid)   => request(`/tenants/${tid}/branches`),
  createBranch:   (tid, b)=> request(`/tenants/${tid}/branches`, { method: 'POST', body: b }),
  members:        (tid)   => request(`/tenants/${tid}/members`),
  addMember:      (tid, b)=> request(`/tenants/${tid}/members`, { method: 'POST', body: b }),

  // ─── Profile / Auth extras ───
  me:             ()      => request('/auth/me'),
  updateMe:       (b)     => request('/auth/me', { method: 'PUT', body: b }),

  // ─── Notifications ───
  notifications:  ()      => request('/notifications'),
  markNotifRead:  (id)    => request(`/notifications/${id}/read`, { method: 'POST' }),
  markAllNotifs:  ()      => request('/notifications/read-all', { method: 'POST' }),
  clearNotifs:    ()      => request('/notifications/clear', { method: 'POST' }),

  // ─── Wizard ───
  wizardState:    ()      => request('/wizard/state'),
  setWizardStep:  (b)     => request('/wizard/state', { method: 'POST', body: b }),
};
