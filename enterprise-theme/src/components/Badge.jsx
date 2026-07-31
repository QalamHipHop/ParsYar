import React from 'react';
import { cx } from '../lib/format.js';

const VARIANTS = {
  default: 'badge-ink',
  brand:   'badge-brand',
  success: 'badge-success',
  warning: 'badge-warning',
  danger:  'badge-danger',
  info:    'badge-info',
  brutal:  'badge-brutal',
};

export default function Badge({ variant = 'default', dot = false, className = '', children, ...rest }) {
  return (
    <span {...rest} className={cx(VARIANTS[variant] || VARIANTS.default, className)}>
      {dot && <span className={cx('dot', {
        'bg-ink-400':    variant === 'default',
        'bg-brand-500':  variant === 'brand',
        'bg-success-500':variant === 'success',
        'bg-warning-500':variant === 'warning',
        'bg-danger-500': variant === 'danger',
        'bg-info-500':   variant === 'info',
      })} />}
      {children}
    </span>
  );
}
