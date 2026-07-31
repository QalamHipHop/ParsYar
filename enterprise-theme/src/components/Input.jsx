import React, { forwardRef, useId } from 'react';
import { cx } from '../lib/format.js';

/**
 * Input — variants: default, brutal, glass
 */
const Input = forwardRef(function Input({
  label, hint, error, prefix, suffix, variant = 'default',
  type = 'text', className = '', ...props
}, ref) {
  const id = useId();
  const variants = {
    default: 'input',
    brutal:  'input-brutal',
    glass:   'input-glass',
  };
  return (
    <div className="w-full">
      {label && (
        <label htmlFor={id} className="label">
          {label}
          {props.required && <span className="text-danger-500 ms-1">*</span>}
        </label>
      )}
      <div className="relative">
        {prefix && (
          <div className="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none text-ink-400">
            {prefix}
          </div>
        )}
        <input
          id={id}
          ref={ref}
          type={type}
          {...props}
          className={cx(
            variants[variant] || variants.default,
            prefix && 'ps-10',
            suffix && 'pe-10',
            error && '!border-danger-500 !ring-danger-500/20',
            className
          )}
        />
        {suffix && (
          <div className="absolute inset-y-0 end-0 pe-3 flex items-center pointer-events-none text-ink-400">
            {suffix}
          </div>
        )}
      </div>
      {hint && !error && <p className="mt-1 text-[11px] text-ink-500 dark:text-ink-400">{hint}</p>}
      {error && <p className="mt-1 text-[11px] text-danger-600 dark:text-danger-500 font-medium">{error}</p>}
    </div>
  );
});
export default Input;

export const Textarea = forwardRef(function Textarea({
  label, hint, error, className = '', rows = 4, ...props
}, ref) {
  const id = useId();
  return (
    <div className="w-full">
      {label && <label htmlFor={id} className="label">{label}{props.required && <span className="text-danger-500 ms-1">*</span>}</label>}
      <textarea
        id={id}
        ref={ref}
        rows={rows}
        {...props}
        className={cx('input min-h-[80px]', error && '!border-danger-500', className)}
      />
      {hint && !error && <p className="mt-1 text-[11px] text-ink-500 dark:text-ink-400">{hint}</p>}
      {error && <p className="mt-1 text-[11px] text-danger-600 dark:text-danger-500 font-medium">{error}</p>}
    </div>
  );
});

export const Select = forwardRef(function Select({
  label, hint, error, options = [], className = '', children, ...props
}, ref) {
  const id = useId();
  return (
    <div className="w-full">
      {label && <label htmlFor={id} className="label">{label}{props.required && <span className="text-danger-500 ms-1">*</span>}</label>}
      <select
        id={id}
        ref={ref}
        {...props}
        className={cx('input appearance-none pe-9 bg-no-repeat bg-[length:16px] bg-[position:calc(100%-12px)_center] dark:bg-[url("data:image/svg+xml;utf8,<svg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 20 20%27 fill=%27%23a1a1aa%27><path fill-rule=%27evenodd%27 d=%27M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z%27 clip-rule=%27evenodd%27/></svg>")]',
          error && '!border-danger-500', className)}
      >
        {children || options.map(o =>
          typeof o === 'string'
            ? <option key={o} value={o}>{o}</option>
            : <option key={o.value} value={o.value}>{o.label}</option>
        )}
      </select>
      {hint && !error && <p className="mt-1 text-[11px] text-ink-500 dark:text-ink-400">{hint}</p>}
      {error && <p className="mt-1 text-[11px] text-danger-600 dark:text-danger-500 font-medium">{error}</p>}
    </div>
  );
});

export const Switch = forwardRef(function Switch({ label, checked, onChange, disabled, className }, ref) {
  return (
    <label className={cx('inline-flex items-center gap-2.5 cursor-pointer select-none', disabled && 'opacity-50 cursor-not-allowed', className)}>
      <span className="relative inline-block w-9 h-5">
        <input
          ref={ref}
          type="checkbox"
          checked={!!checked}
          onChange={onChange}
          disabled={disabled}
          className="peer sr-only"
        />
        <span className="absolute inset-0 rounded-full bg-ink-200 dark:bg-ink-700 peer-checked:bg-brand-500 transition-colors" />
        <span className="absolute top-0.5 start-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform peer-checked:translate-x-4" />
      </span>
      {label && <span className="text-sm text-ink-700 dark:text-ink-200">{label}</span>}
    </label>
  );
});
