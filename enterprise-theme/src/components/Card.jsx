import React from 'react';
import { cx } from '../lib/format.js';

/**
 * Card — 4 visual flavors: glass (default), glass-strong, brutal, brutal-accent
 */
export default function Card({
  variant = 'glass',
  padded = true,
  hover = false,
  className = '',
  children,
  ...rest
}) {
  const variants = {
    glass:          'surface-glass rounded-2xl',
    'glass-strong': 'surface-glass-strong rounded-2xl',
    brutal:         'surface-brutal rounded-xl',
    'brutal-sm':    'surface-brutal-sm rounded-lg',
    'brutal-accent':'surface-brutal-accent rounded-xl',
    flat:           'bg-white dark:bg-ink-900 border border-ink-200 dark:border-ink-800 rounded-2xl',
  };
  return (
    <div
      {...rest}
      className={cx(
        variants[variant] || variants.glass,
        padded && 'p-5',
        hover && 'transition-all hover:-translate-y-0.5 hover:shadow-glass-lg',
        className
      )}
    >
      {children}
    </div>
  );
}

export function CardHeader({ title, subtitle, action, className = '' }) {
  return (
    <div className={cx('flex items-start justify-between gap-3 mb-4', className)}>
      <div className="min-w-0">
        {title && <h3 className="text-base font-bold text-ink-900 dark:text-ink-50 tracking-tight">{title}</h3>}
        {subtitle && <p className="text-xs text-ink-500 dark:text-ink-400 mt-0.5">{subtitle}</p>}
      </div>
      {action && <div className="flex-shrink-0">{action}</div>}
    </div>
  );
}

export function CardBody({ className = '', children }) {
  return <div className={cx('text-sm text-ink-700 dark:text-ink-300', className)}>{children}</div>;
}
