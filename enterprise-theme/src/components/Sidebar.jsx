import React from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { useUI } from '../store';
import { cx } from '../lib/format.js';

/**
 * 19-pillar sidebar grouped into:
 *   - main      → dashboard, objects, records
 *   - business  → leads, contacts, deals, products, invoices, orders, payments
 *   - ops       → accounting, workflows, reports, employees
 *   - system    → audit, settings, notifications
 */

const GROUPS = [
  {
    title: 'اصلی',
    items: [
      { to: '/',                label: 'داشبورد',      icon: HomeIcon,         end: true },
      { to: '/objects',         label: 'اشیاء',         icon: CubeIcon },
      { to: '/records/account', label: 'حساب‌ها',       icon: BookmarkIcon },
      { to: '/records/opportunity', label: 'فرصت‌ها',   icon: TargetIcon },
    ],
  },
  {
    title: 'کسب‌وکار',
    items: [
      { to: '/leads',      label: 'سرنخ‌ها',     icon: FunnelIcon },
      { to: '/contacts',   label: 'مخاطبین',     icon: UsersIcon },
      { to: '/deals',      label: 'معاملات',     icon: HandshakeIcon },
      { to: '/products',   label: 'انبار',       icon: BoxIcon },
      { to: '/invoices',   label: 'فاکتورها',    icon: ReceiptIcon },
      { to: '/orders',     label: 'سفارش‌ها',    icon: CartIcon },
      { to: '/payments',   label: 'پرداخت‌ها',   icon: CreditIcon },
    ],
  },
  {
    title: 'عملیات',
    items: [
      { to: '/accounting', label: 'حسابداری',    icon: CalculatorIcon },
      { to: '/workflows',  label: 'گردش کار',    icon: FlowIcon },
      { to: '/reports',    label: 'گزارش‌ها',     icon: ChartIcon },
      { to: '/employees',  label: 'پرسنل',       icon: IdIcon },
    ],
  },
  {
    title: 'سامانه',
    items: [
      { to: '/notifications', label: 'اعلان‌ها',  icon: BellIcon },
      { to: '/audit',         label: 'حسابرسی',   icon: ShieldIcon },
      { to: '/settings',      label: 'تنظیمات',   icon: GearIcon },
    ],
  },
];

export default function Sidebar() {
  const { sidebarCollapsed, collapseSidebar } = useUI();
  const loc = useLocation();
  return (
    <aside
      className={cx(
        'flex-shrink-0 h-screen sticky top-0 z-30 hidden md:flex flex-col',
        'border-l border-ink-200/60 dark:border-white/5',
        'bg-white/80 dark:bg-ink-950/80 backdrop-blur-2xl',
        sidebarCollapsed ? 'w-[68px]' : 'w-64',
        'transition-[width] duration-300'
      )}
    >
      {/* brand */}
      <div className="flex items-center gap-2.5 px-4 h-16 border-b border-ink-200/60 dark:border-white/5">
        <div className="w-9 h-9 rounded-xl bg-ink-950 dark:bg-ink-50 grid place-items-center text-white dark:text-ink-950 font-black text-lg shadow-brutal-sm flex-shrink-0">پ</div>
        {!sidebarCollapsed && (
          <div className="min-w-0 animate-fade-in">
            <div className="font-extrabold text-sm tracking-tight truncate text-ink-900 dark:text-ink-50">ParsYar</div>
            <div className="text-[10px] text-ink-500 dark:text-ink-400 font-medium truncate">Enterprise Platform</div>
          </div>
        )}
      </div>

      {/* nav */}
      <nav className="flex-1 overflow-y-auto py-3 px-2.5 space-y-4 no-scrollbar">
        {GROUPS.map(g => (
          <div key={g.title}>
            {!sidebarCollapsed && (
              <div className="px-2.5 mb-1.5 text-[10px] uppercase tracking-widest font-bold text-ink-400 dark:text-ink-500">
                {g.title}
              </div>
            )}
            <ul className="space-y-0.5">
              {g.items.map(item => (
                <li key={item.to}>
                  <NavLink
                    to={item.to}
                    end={item.end}
                    className={({ isActive }) => cx(
                      'group relative flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-all duration-150',
                      isActive
                        ? 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950 shadow-brutal-sm'
                        : 'text-ink-600 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-900 hover:text-ink-900 dark:hover:text-ink-50',
                    )}
                    title={sidebarCollapsed ? item.label : undefined}
                  >
                    <span className="flex-shrink-0 w-5 h-5">{item.icon}</span>
                    {!sidebarCollapsed && <span className="truncate">{item.label}</span>}
                  </NavLink>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </nav>

      {/* footer / collapse */}
      <div className="p-2.5 border-t border-ink-200/60 dark:border-white/5">
        <button
          onClick={collapseSidebar}
          className="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs text-ink-500 dark:text-ink-400 hover:bg-ink-100 dark:hover:bg-ink-900 transition"
        >
          <span className={cx('transition-transform', sidebarCollapsed && 'flip-rtl rotate-180')}>
            <ChevronIcon />
          </span>
          {!sidebarCollapsed && <span>جمع کردن</span>}
        </button>
      </div>
    </aside>
  );
}

/* ───────── Icons (single-path 20x20 stroke) ───────── */
const I = (children) => (props) => (
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" {...props}>{children}</svg>
);
const HomeIcon        = I(<><path d="M3 9.5L12 3l9 6.5V20a2 2 0 0 1-2 2h-4v-7h-6v7H5a2 2 0 0 1-2-2V9.5z"/></>);
const CubeIcon        = I(<><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></>);
const BookmarkIcon    = I(<><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></>);
const TargetIcon      = I(<><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></>);
const FunnelIcon      = I(<><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></>);
const UsersIcon       = I(<><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></>);
const HandshakeIcon   = I(<><path d="M11 17l-5-5 5-5M18 12H8M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z"/></>);
const BoxIcon         = I(<><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></>);
const ReceiptIcon     = I(<><path d="M4 2h16v20l-2-1-2 1-2-1-2 1-2-1-2 1-2-1-2 1V2z"/><path d="M8 7h8M8 11h8M8 15h5"/></>);
const CartIcon        = I(<><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></>);
const CreditIcon      = I(<><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></>);
const CalculatorIcon  = I(<><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01"/></>);
const FlowIcon        = I(<><rect x="3" y="3" width="6" height="6" rx="1"/><rect x="15" y="15" width="6" height="6" rx="1"/><path d="M9 6h3a3 3 0 0 1 3 3v6M18 9V6a3 3 0 0 0-3-3H9"/></>);
const ChartIcon       = I(<><path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 5-5"/></>);
const IdIcon          = I(<><rect x="2" y="4" width="20" height="16" rx="2"/><circle cx="8" cy="10" r="2"/><path d="M14 10h4M14 14h4M6 16h6"/></>);
const BellIcon        = I(<><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10 21a2 2 0 0 0 4 0"/></>);
const ShieldIcon      = I(<><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></>);
const GearIcon        = I(<><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></>);
const ChevronIcon     = I(<><path d="M9 18l6-6-6-6"/></>);
