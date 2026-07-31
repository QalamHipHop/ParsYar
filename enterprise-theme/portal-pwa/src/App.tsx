import React, { useEffect, useState } from 'react';
import { Routes, Route, Navigate, NavLink, useLocation, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { api, type Session } from './lib/api';
import LoginPage from './pages/LoginPage';
import VerifyPage from './pages/VerifyPage';
import DashboardPage from './pages/DashboardPage';
import InvoicesPage from './pages/InvoicesPage';
import OrdersPage from './pages/OrdersPage';
import PaymentsPage from './pages/PaymentsPage';
import TicketsPage from './pages/TicketsPage';
import { InstallBanner, PushBanner } from './components/Banners';

declare global {
  interface Window {
    parsyarPortal?: {
      logEvent: (type: string, payload?: Record<string, unknown>) => Promise<void>;
    };
  }
}

// global helper برای ثبت رویداد بدون وارد کردن api
window.parsyarPortal = {
  logEvent: async (type, payload = {}) => {
    try { await api.logEvent(type, payload); } catch { /* ignore */ }
  }
};

function Layout({ children }: { children: React.ReactNode }) {
  const { t } = useTranslation();
  const nav = useNavigate();
  const loc = useLocation();
  const [offline, setOffline] = useState(!navigator.onLine);

  useEffect(() => {
    const on = () => setOffline(false);
    const off = () => setOffline(true);
    window.addEventListener('online', on);
    window.addEventListener('offline', off);
    return () => { window.removeEventListener('online', on); window.removeEventListener('offline', off); };
  }, []);

  const link = ({ isActive }: { isActive: boolean }) =>
    'block rounded-lg px-3 py-2 text-sm font-medium ' + (isActive ? 'bg-brand-600 text-white' : 'text-slate-700 hover:bg-slate-100');

  const handleLogout = async () => {
    try { await api.logout(); } catch { /* ignore */ }
    nav('/login', { replace: true });
  };

  return (
    <div className="min-h-full flex flex-col">
      <header className="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div className="mx-auto max-w-3xl px-4 py-3 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <div className="h-8 w-8 rounded-lg bg-brand-600 grid place-items-center text-white font-bold">پ</div>
            <div className="text-sm font-semibold">{t('app.name')}</div>
          </div>
          <button onClick={handleLogout} className="text-xs text-slate-500 hover:text-slate-900">{t('nav.logout')}</button>
        </div>
      </header>

      {offline && (
        <div className="bg-amber-100 text-amber-900 text-xs text-center py-1.5">
          {t('common.offline')}
        </div>
      )}

      <div className="flex-1 mx-auto w-full max-w-3xl px-4 py-4 pb-24">
        <nav className="grid grid-cols-3 sm:grid-cols-6 gap-1 mb-4">
          <NavLink to="/dashboard" className={link}>{t('nav.dashboard')}</NavLink>
          <NavLink to="/invoices" className={link}>{t('nav.invoices')}</NavLink>
          <NavLink to="/orders"    className={link}>{t('nav.orders')}</NavLink>
          <NavLink to="/payments"  className={link}>{t('nav.payments')}</NavLink>
          <NavLink to="/tickets"   className={link}>{t('nav.tickets')}</NavLink>
          <NavLink to="/tickets/new" className={link}>{t('ticket.new')}</NavLink>
        </nav>
        <main>{children}</main>
      </div>

      <InstallBanner />
      <PushBanner />

      <footer className="border-t border-slate-200 py-3 text-center text-[11px] text-slate-400">
        {t('app.poweredBy')}
      </footer>
      {/* suppress unused loc warning in some bundlers */}
      {false && <span data-loc={loc.pathname} />}
    </div>
  );
}

function Protected({ children }: { children: React.ReactNode }) {
  const nav = useNavigate();
  useEffect(() => {
    const s: Session | null = api.getSession();
    if (!s) nav('/login', { replace: true });
  }, [nav]);
  return <>{api.getSession() ? children : null}</>;
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/portal-action/verify" element={<VerifyPage />} />
      <Route path="/" element={<Navigate to="/dashboard" replace />} />
      <Route path="/dashboard" element={<Protected><Layout><DashboardPage /></Layout></Protected>} />
      <Route path="/invoices"  element={<Protected><Layout><InvoicesPage /></Layout></Protected>} />
      <Route path="/orders"    element={<Protected><Layout><OrdersPage /></Layout></Protected>} />
      <Route path="/payments"  element={<Protected><Layout><PaymentsPage /></Layout></Protected>} />
      <Route path="/tickets"   element={<Protected><Layout><TicketsPage /></Layout></Protected>} />
      <Route path="/tickets/new" element={<Protected><Layout><TicketsPage initialNew /></Layout></Protected>} />
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  );
}
