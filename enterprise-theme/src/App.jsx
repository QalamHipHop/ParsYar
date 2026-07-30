import React from 'react';
import { Routes, Route, NavLink, Navigate } from 'react-router-dom';
import Dashboard from './pages/Dashboard.jsx';
import ObjectsList from './pages/ObjectsList.jsx';
import RecordsList from './pages/RecordsList.jsx';
import Accounting from './pages/Accounting.jsx';
import Leads from './pages/Leads.jsx';
import Products from './pages/Products.jsx';
import Invoices from './pages/Invoices.jsx';
import Employees from './pages/Employees.jsx';
import Workflows from './pages/Workflows.jsx';
import Audit from './pages/Audit.jsx';

const nav = [
  { to: '/',                label: 'داشبورد' },
  { to: '/objects',         label: 'اشیاء' },
  { to: '/records/account', label: 'حساب‌ها' },
  { to: '/records/opportunity', label: 'فرصت‌ها' },
  { to: '/leads',           label: 'سرنخ‌ها' },
  { to: '/products',        label: 'انبار' },
  { to: '/invoices',        label: 'فاکتورها' },
  { to: '/employees',       label: 'پرسنل' },
  { to: '/accounting',      label: 'حسابداری' },
  { to: '/workflows',       label: 'گردش کار' },
  { to: '/audit',           label: 'حسابرسی' },
];

export default function App() {
  return (
    <div className="min-h-screen flex" dir="rtl">
      <aside className="w-64 bg-brand-900 text-white p-4 flex flex-col">
        <div className="text-2xl font-bold mb-1">ParsYar</div>
        <div className="text-xs text-brand-100 mb-6">Enterprise Platform</div>
        <nav className="space-y-1 flex-1">
          {nav.map(n => (
            <NavLink
              key={n.to}
              to={n.to}
              end={n.to === '/'}
              className={({ isActive }) =>
                `block px-3 py-2 rounded-lg text-sm ${
                  isActive ? 'bg-brand-700 text-white' : 'text-brand-100 hover:bg-brand-700/60'
                }`
              }
            >
              {n.label}
            </NavLink>
          ))}
        </nav>
        <div className="text-xs text-brand-200/70 mt-4">v1.0.0</div>
      </aside>
      <main className="flex-1 p-8 overflow-auto">
        <Routes>
          <Route path="/" element={<Dashboard />} />
          <Route path="/objects" element={<ObjectsList />} />
          <Route path="/records/:api" element={<RecordsList />} />
          <Route path="/leads" element={<Leads />} />
          <Route path="/products" element={<Products />} />
          <Route path="/invoices" element={<Invoices />} />
          <Route path="/employees" element={<Employees />} />
          <Route path="/accounting" element={<Accounting />} />
          <Route path="/workflows" element={<Workflows />} />
          <Route path="/audit" element={<Audit />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </main>
    </div>
  );
}
