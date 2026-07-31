import React from 'react';
import { cx } from '../lib/format.js';

/**
 * Button — 3 variants: primary (ink/black-brutal), secondary (white-brutal), ghost, danger, glass
 * sizes: sm, md, lg
 */
export default function Button({
  variant = 'primary',
  size = 'md',
  icon,
  iconRight,
  loading = false,
  className = '',
  children,
  ...props
}) {
  const variants = {
    primary:   'btn-primary',
    secondary: 'btn-secondary',
    ghost:     'btn-ghost',
    danger:    'btn-danger',
    glass:     'btn-glass',
  };
  const sizes = {
    xs: 'btn-xs',
    sm: 'btn-sm',
    md: '',
    lg: 'btn-lg',
  };
  return (
    <button
      {...props}
      disabled={props.disabled || loading}
      className={cx(variants[variant] || variants.primary, sizes[size], className)}
    >
      {loading ? (
        <span className="inline-block w-3.5 h-3.5 border-2 border-current border-r-transparent rounded-full animate-spin" />
      ) : icon ? <span className="inline-flex">{icon}</span> : null}
      {children}
      {iconRight && <span className="inline-flex">{iconRight}</span>}
    </button>
  );
}
