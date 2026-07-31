const BASE = window.EnterpriseConfig?.restUrl || '/wp-json/enterprise/v1';

function getNonce() {
  return window.EnterpriseConfig?.nonce || '';
}

async function request(path, { method = 'GET', body, headers = {} } = {}) {
  const res = await fetch(BASE + path, {
    method,
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': getNonce(),
      ...headers,
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  if (!res.ok) {
    const text = await res.text();
    throw new Error(`HTTP ${res.status}: ${text}`);
  }
  return res.status === 204 ? null : res.json();
}

export const api = {
  get: (p)    => request(p),
  post: (p, b) => request(p, { method: 'POST',   body: b }),
  put:  (p, b) => request(p, { method: 'PUT',    body: b }),
  del:  (p)    => request(p, { method: 'DELETE' }),

  objects:        ()      => request('/objects'),
  object:         (api)   => request(`/objects/${api}`),
  records:        (api)   => request(`/objects/${api}/records`),
  createRecord:   (api, b)=> request(`/objects/${api}/records`, { method: 'POST', body: b }),
  deleteRecord:   (id)    => request(`/records/${id}`, { method: 'DELETE' }),

  leads:          ()      => request('/crm/leads'),
  createLead:     (b)     => request('/crm/leads', { method: 'POST', body: b }),

  products:       ()      => request('/erp/products'),
  createProduct:  (b)     => request('/erp/products', { method: 'POST', body: b }),

  invoices:       ()      => request('/erp/invoices'),
  createInvoice:  (b)     => request('/erp/invoices', { method: 'POST', body: b }),

  employees:      ()      => request('/hrm/employees'),
  createEmployee: (b)     => request('/hrm/employees', { method: 'POST', body: b }),
  runPayroll:     (b)     => request('/hrm/payroll/run', { method: 'POST', body: b }),

  accounts:       ()      => request('/accounting/accounts'),
  trialBalance:   ()      => request('/accounting/trial-balance'),
  journal:        ()      => request('/accounting/journal'),
  postEntry:      (b)     => request('/accounting/journal', { method: 'POST', body: b }),

  workflows:      ()      => request('/workflows'),
  createWorkflow: (b)     => request('/workflows', { method: 'POST', body: b }),
  workflow:       (id)    => request(`/workflows/${id}`),
  updateWorkflow: (id, b) => request(`/workflows/${id}`, { method: 'PUT', body: b }),
  deleteWorkflow: (id)    => request(`/workflows/${id}`, { method: 'DELETE' }),
  duplicateWorkflow: (id, name) => request(`/workflows/${id}/duplicate`, { method: 'POST', body: { name } }),
  runWorkflow:    (id, payload) => request(`/workflows/${id}/run`, { method: 'POST', body: payload || {} }),
  workflowRuns:   (id)    => request(`/workflows/${id}/runs`),
  workflowLogs:   (id)    => request(`/workflows/${id}/logs`),
  workflowTemplates: ()   => request('/workflows/templates'),
  workflowTriggers:   ()   => request('/workflows/triggers'),
  workflowNodeTypes:  ()   => request('/workflows/node-types'),
  workflowStats:      ()   => request('/workflows/stats'),

  audit:          ()      => request('/audit'),
};
