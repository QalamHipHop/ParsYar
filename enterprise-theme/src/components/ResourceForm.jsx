import React, { useEffect, useState } from 'react';
import Modal from './Modal.jsx';
import Button from './Button.jsx';
import Input, { Textarea, Select, Switch } from './Input.jsx';
import { useToasts } from '../store';

/**
 * ResourceForm — generic create/edit modal.
 * props:
 *   - open, onClose
 *   - title
 *   - fields: [{ key, label, type, options?, required?, hint?, render? }]
 *     type: text|email|number|date|datetime|textarea|select|switch|json
 *   - initial: object
 *   - onSubmit: async (values) => void
 */
export default function ResourceForm({
  open, onClose, title, fields = [], initial = {}, onSubmit, submitLabel = 'ذخیره',
}) {
  const [values, setValues] = useState(initial);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState(null);
  const push = useToasts(s => s.push);

  useEffect(() => {
    if (open) {
      setValues({ ...initial });
      setErr(null);
      setBusy(false);
    }
  }, [open, initial]);

  const set = (k, v) => setValues(s => ({ ...s, [k]: v }));

  const handleSubmit = async (e) => {
    e?.preventDefault?.();
    setErr(null);
    setBusy(true);
    try {
      await onSubmit(values);
      push({ type: 'success', message: 'ذخیره شد.' });
      onClose?.();
    } catch (e) {
      setErr(e.message || 'خطا');
      push({ type: 'error', message: e.message });
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={title}
      size="lg"
      footer={
        <>
          <Button variant="ghost" onClick={onClose} type="button">انصراف</Button>
          <Button variant="primary" onClick={handleSubmit} loading={busy} type="button">{submitLabel}</Button>
        </>
      }
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          {fields.map(f => {
            if (f.type === 'custom' && typeof f.render === 'function') {
              return <div key={f.key} className={f.full ? 'md:col-span-2' : ''}>{f.render({ value: values[f.key], onChange: v => set(f.key, v) })}</div>;
            }
            const v = values[f.key] ?? '';
            if (f.type === 'textarea') {
              return <div key={f.key} className="md:col-span-2"><Textarea label={f.label + (f.required ? ' *' : '')} value={v} hint={f.hint} onChange={(e) => set(f.key, e.target.value)} required={f.required} /></div>;
            }
            if (f.type === 'select') {
              return <div key={f.key}><Select label={f.label + (f.required ? ' *' : '')} value={v} options={f.options || []} hint={f.hint} onChange={(e) => set(f.key, e.target.value)} required={f.required} /></div>;
            }
            if (f.type === 'switch') {
              return <div key={f.key} className="md:col-span-2"><Switch label={f.label} checked={!!v} onChange={(e) => set(f.key, e.target.checked)} /></div>;
            }
            if (f.type === 'json') {
              return (
                <div key={f.key} className="md:col-span-2">
                  <Textarea
                    label={f.label + (f.required ? ' *' : '')}
                    value={typeof v === 'string' ? v : JSON.stringify(v || {}, null, 2)}
                    hint={f.hint || 'JSON معتبر'}
                    rows={6}
                    onChange={(e) => {
                      try { set(f.key, JSON.parse(e.target.value)); setErr(null); } catch { set(f.key, e.target.value); }
                    }}
                    required={f.required}
                  />
                </div>
              );
            }
            return (
              <div key={f.key}>
                <Input
                  label={f.label + (f.required ? ' *' : '')}
                  type={
                    f.type === 'number' ? 'number' :
                    f.type === 'date'   ? 'date'   :
                    f.type === 'datetime' ? 'datetime-local' :
                    f.type === 'email'  ? 'email'  : 'text'
                  }
                  step={f.step}
                  value={v}
                  onChange={(e) => {
                    const raw = e.target.value;
                    set(f.key, f.type === 'number' ? (raw === '' ? '' : Number(raw)) : raw);
                  }}
                  required={f.required}
                  hint={f.hint}
                />
              </div>
            );
          })}
        </div>
        {err && <div className="text-xs text-danger-600 bg-danger-50 dark:bg-danger-500/10 border border-danger-200 dark:border-danger-500/30 rounded-lg p-2.5">{err}</div>}
      </form>
    </Modal>
  );
}
