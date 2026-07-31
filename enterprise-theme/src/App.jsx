import React, { Suspense, lazy } from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import Sidebar from './components/Sidebar.jsx';
import Topbar, { CommandPalette } from './components/Topbar.jsx';
import ToastStack from './components/ToastStack.jsx';
import { useUI } from './store';

// Lazy pages — keeps initial bundle small
const Dashboard       = lazy(() => import('./pages/Dashboard.jsx'));
const ObjectsList     = lazy(() => import('./pages/ObjectsList.jsx'));
const RecordsList     = lazy(() => import('./pages/RecordsList.jsx'));
const ObjectBuilder   = lazy(() => import('./pages/ObjectBuilder.jsx'));
const Leads           = lazy(() => import('./pages/Leads.jsx'));
const Contacts        = lazy(() => import('./pages/Contacts.jsx'));
const Deals           = lazy(() => import('./pages/Deals.jsx'));
const Products        = lazy(() => import('./pages/Products.jsx'));
const Invoices        = lazy(() => import('./pages/Invoices.jsx'));
const Orders          = lazy(() => import('./pages/Orders.jsx'));
const Payments        = lazy(() => import('./pages/Payments.jsx'));
const Employees       = lazy(() => import('./pages/Employees.jsx'));
const Accounting      = lazy(() => import('./pages/Accounting.jsx'));
const Workflows       = lazy(() => import('./pages/Workflows.jsx'));
const Reports         = lazy(() => import('./pages/Reports.jsx'));
const Audit           = lazy(() => import('./pages/Audit.jsx'));
const Settings        = lazy(() => import('./pages/Settings.jsx'));
const Notifications   = lazy(() => import('./pages/Notifications.jsx'));
const Profile         = lazy(() => import('./pages/Profile.jsx'));
const WizardLauncher  = lazy(() => import('./pages/WizardLauncher.jsx'));
const Tenants         = lazy(() => import('./pages/Tenants.jsx'));

function PageLoader() {
  return (
    <div className="space-y-4 animate-fade-in">
      <div className="flex items-center gap-3 mb-2">
        <div className="skeleton h-8 w-48 rounded-lg" />
        <div className="skeleton h-8 w-24 rounded-lg ms-auto" />
      </div>
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        {Array.from({ length: 4 }).map((_, i) => <div key={i} className="skeleton h-24 rounded-2xl" />)}
      </div>
      <div className="skeleton h-72 rounded-2xl" />
    </div>
  );
}

export default function App() {
  const { sidebarOpen } = useUI();
  return (
    <div className="min-h-screen flex bg-ink-50 dark:bg-ink-950">
      <Sidebar />
      <div className="flex-1 flex flex-col min-w-0">
        <Topbar />
        <main className="flex-1 px-4 md:px-6 py-6 max-w-[1600px] w-full mx-auto">
          <Suspense fallback={<PageLoader />}>
            <Routes>
              <Route path="/" element={<Dashboard />} />
              <Route path="/objects" element={<ObjectsList />} />
              <Route path="/objects/new" element={<ObjectBuilder />} />
              <Route path="/objects/:api/edit" element={<ObjectBuilder />} />
              <Route path="/records/:api" element={<RecordsList />} />
              <Route path="/leads" element={<Leads />} />
              <Route path="/contacts" element={<Contacts />} />
              <Route path="/deals" element={<Deals />} />
              <Route path="/products" element={<Products />} />
              <Route path="/invoices" element={<Invoices />} />
              <Route path="/orders" element={<Orders />} />
              <Route path="/payments" element={<Payments />} />
              <Route path="/employees" element={<Employees />} />
              <Route path="/accounting" element={<Accounting />} />
              <Route path="/workflows" element={<Workflows />} />
              <Route path="/reports" element={<Reports />} />
              <Route path="/audit" element={<Audit />} />
              <Route path="/settings" element={<Settings />} />
              <Route path="/notifications" element={<Notifications />} />
              <Route path="/profile" element={<Profile />} />
              <Route path="/wizard" element={<WizardLauncher />} />
              <Route path="/tenants" element={<Tenants />} />
              <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
          </Suspense>
        </main>
      </div>
      <CommandPalette />
      <ToastStack />
    </div>
  );
}
