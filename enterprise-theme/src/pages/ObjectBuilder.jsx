/**
 * ObjectBuilder — visual schema editor for a Custom Object.
 * Lists existing fields, lets the admin add new ones (22 supported types),
 * and persists the schema via api.object()/createObject.
 *
 * Glassmorphism shell + neo-brutalist controls, RTL-first.
 */
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { api } from '../api/client.js';
import Card, { CardHeader } from '../components/Card.jsx';
import Button from '../components/Button.jsx';
import Badge from '../components/Badge.jsx';
import Input, { Select, Textarea } from '../components/Input.jsx';
import { useToasts } from '../store';
import { cx } from '../lib/format.js';

// 22 supported field types (mirrors FieldTypes::ALL in PHP engine)
const FIELD_TYPES = [
  { v: 'text',         l: 'متن کوتاه',         g: 'متن' },
  { v: 'textarea',     l: 'متن بلند',           g: 'متن' },
  { v: 'rich',         l: 'متن غنی (HTML)',     g: 'متن' },
  { v: 'email',        l: 'ایمیل',              g: 'متن' },
  { v: 'url',          l: 'آدرس URL',           g: 'متن' },
  { v: 'int',          l: 'عدد صحیح',           g: 'عدد' },
  { v: 'decimal',      l: 'عدد اعشاری',         g: 'عدد' },
  { v: 'bool',         l: 'بولی (بله/خیر)',     g: 'عدد' },
  { v: 'date',         l: 'تاریخ میلادی',       g: 'تاریخ' },
  { v: 'datetime',     l: 'تاریخ و ساعت',       g: 'تاریخ' },
  { v: 'jalali',       l: 'تاریخ شمسی',         g: 'تاریخ' },
  { v: 'enum',         l: 'انتخابی تک',         g: 'انتخابی' },
  { v: 'multi',        l: 'انتخابی چندگانه',    g: 'انتخابی' },
  { v: 'json',         l: 'JSON',                g: 'انتخابی' },
  { v: 'fk',           l: 'کلید خارجی',         g: 'رابطه' },
  { v: 'file',         l: 'فایل',               g: 'فایل' },
  { v: 'image',        l: 'تصویر',              g: 'فایل' },
  { v: 'phone',        l: 'تلفن',                g: 'ایران' },
  { v: 'mobile',       l: 'موبایل',              g: 'ایران' },
  { v: 'sheba',        l: 'شبا (IBAN)',          g: 'ایران' },
  { v: 'national_id',  l: 'کد ملی',              g: 'ایران' },
  { v: 'card',         l: 'شماره کارت',          g: 'ایران' },
];

const TYPE_GROUPS = FIELD_TYPES.reduce((acc, t) => {
  (acc[t.g] = acc[t.g] || []).push(t);
  return acc;
}, {});

const SAMPLE = {
  api: 'product',
  label: 'محصول',
  plural_label: 'محصولات',
  description: '',
  is_system: 0,
  icon: 'BoxIcon',
  fields: [],
};

export default function ObjectBuilder() {
  const { api: apiName } = useParams();
  const nav = useNavigate();
  const push = useToasts(s => s.push);
  const isEdit = Boolean(apiName);

  const [schema, setSchema] = useState(SAMPLE);
  const [loading, setLoading] = useState(isEdit);
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState({});

  // load existing schema when editing
  useEffect(() => {
    if (!isEdit) return;
    let cancelled = false;
    (async () => {
      try {
        const r = await api.object(apiName);
        if (cancelled) return;
        setSchema({
          api: r.api || r.api_name || apiName,
          label: r.label || r.singular || '',
          plural_label: r.plural_label || r.plural || '',
          description: r.description || '',
          is_system: r.is_system || 0,
          icon: r.icon || 'CubeIcon',
          fields: Array.isArray(r.fields) ? r.fields : [],
        });
      } catch (e) {
        push({ type: 'error', message: 'بارگذاری شئی با خطا مواجه شد: ' + e.message });
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, [apiName, isEdit, push]);

  const slugify = (s) =>
    (s || '')
      .toString()
      .toLowerCase()
      .replace(/[\s_]+/g, '-')
      .replace(/[^\u0600-\u06FFa-z0-9-]/g, '')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '') || 'object';

  const setField = (idx, patch) =>
    setSchema((s) => ({ ...s, fields: s.fields.map((f, i) => (i === idx ? { ...f, ...patch } : f)) }));

  const addField = () =>
    setSchema((s) => ({
      ...s,
      fields: [
        ...s.fields,
        {
          api_name: '',
          label: '',
          type: 'text',
          required: false,
          searchable: false,
          unique: false,
          default_value: '',
          options: '',
          help: '',
        },
      ],
    }));

  const removeField = (idx) =>
    setSchema((s) => ({ ...s, fields: s.fields.filter((_, i) => i !== idx) }));

  const moveField = (idx, dir) =>
    setSchema((s) => {
      const next = [...s.fields];
      const j = idx + dir;
      if (j < 0 || j >= next.length) return s;
      [next[idx], next[j]] = [next[j], next[idx]];
      return { ...s, fields: next };
    });

  const validate = () => {
    const e = {};
    if (!schema.label.trim()) e.label = 'نام شئی الزامی است';
    if (!isEdit) {
      const slug = slugify(schema.api || schema.label);
      if (!slug) e.api = 'نام سیستمی (api_name) معتبر نیست';
    }
    schema.fields.forEach((f, i) => {
      if (!f.api_name || !String(f.api_name).trim()) e[`f_${i}_api`] = 'نام فیلد الزامی است';
      if (!f.label || !String(f.label).trim()) e[`f_${i}_label`] = 'برچسب فیلد الزامی است';
    });
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const save = async () => {
    if (!validate()) {
      push({ type: 'warning', message: 'لطفاً فیلدهای الزامی را کامل کنید' });
      return;
    }
    setBusy(true);
    try {
      const payload = {
        ...schema,
        api: isEdit ? schema.api : slugify(schema.api || schema.label),
        fields: schema.fields.map((f) => ({
          ...f,
          api_name: slugify(f.api_name),
          options: f.type === 'enum' || f.type === 'multi'
            ? String(f.options || '').split(',').map((x) => x.trim()).filter(Boolean)
            : f.options,
        })),
      };
      if (isEdit) {
        await api.put(`/objects/${schema.api}`, payload);
        push({ type: 'success', message: 'شئی به‌روزرسانی شد' });
      } else {
        const r = await api.post('/objects', payload);
        push({ type: 'success', message: 'شئی ساخته شد' });
        nav(`/objects/${payload.api}/edit`, { replace: true });
        return;
      }
    } catch (e) {
      push({ type: 'error', message: e.message || 'خطا در ذخیره' });
    } finally {
      setBusy(false);
    }
  };

  const destroy = async () => {
    if (!isEdit) return;
    if (!confirm(`شئی «${schema.label}» و تمام رکوردهایش حذف شود؟`)) return;
    try {
      await api.del(`/objects/${schema.api}`);
      push({ type: 'success', message: 'شئی حذف شد' });
      nav('/objects', { replace: true });
    } catch (e) {
      push({ type: 'error', message: e.message });
    }
  };

  if (loading) {
    return (
      <div className="space-y-3 animate-fade-in">
        <div className="skeleton h-8 w-1/3 rounded-lg" />
        <div className="skeleton h-72 rounded-2xl" />
      </div>
    );
  }

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p className="text-xs font-bold tracking-widest uppercase text-ink-500 dark:text-ink-400">
            اشیاء
          </p>
          <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight text-ink-900 dark:text-ink-50 mt-1">
            {isEdit ? `ویرایش شئی: ${schema.label}` : 'ساخت شئی جدید'}
          </h1>
          <p className="text-sm text-ink-500 dark:text-ink-400 mt-1">
            شئی‌ها قلب پارس‌یار هستند — هر نوع داده‌ای که نیاز دارید تعریف کنید.
          </p>
        </div>
        <div className="flex gap-2">
          {isEdit && (
            <Button variant="danger" onClick={destroy} size="sm">
              حذف شئی
            </Button>
          )}
          <Button variant="ghost" onClick={() => nav('/objects')}>
            بازگشت
          </Button>
          <Button variant="primary" onClick={save} loading={busy}>
            {isEdit ? 'ذخیره تغییرات' : 'ساخت شئی'}
          </Button>
        </div>
      </div>

      {/* Schema meta */}
      <Card variant="glass">
        <CardHeader title="مشخصات شئی" subtitle="نام، برچسب، و توضیحات کلی" />
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          <Input
            label="نام (مفرد) *"
            value={schema.label}
            onChange={(e) => setSchema((s) => ({ ...s, label: e.target.value, api: s.api || slugify(e.target.value) }))}
            hint={errors.label}
            error={errors.label}
            required
          />
          <Input
            label="نام (جمع)"
            value={schema.plural_label}
            onChange={(e) => setSchema((s) => ({ ...s, plural_label: e.target.value }))}
            placeholder="مثلاً: محصولات"
          />
          <Input
            label="نام سیستمی (api_name)"
            value={schema.api}
            onChange={(e) => setSchema((s) => ({ ...s, api: e.target.value }))}
            disabled={isEdit}
            hint={isEdit ? 'بعد از ساخت قابل تغییر نیست' : 'به انگلیسی، با حروف کوچک و خط تیره'}
            error={errors.api}
          />
          <Input
            label="آیکون"
            value={schema.icon}
            onChange={(e) => setSchema((s) => ({ ...s, icon: e.target.value }))}
            placeholder="CubeIcon, BoxIcon, ..."
          />
          <div className="md:col-span-2">
            <Textarea
              label="توضیحات"
              value={schema.description}
              onChange={(e) => setSchema((s) => ({ ...s, description: e.target.value }))}
              rows={2}
            />
          </div>
        </div>
      </Card>

      {/* Fields */}
      <Card variant="glass">
        <CardHeader
          title="فیلدها"
          subtitle={`${schema.fields.length} فیلد تعریف‌شده`}
          action={
            <Button variant="primary" size="sm" onClick={addField}>
              + افزودن فیلد
            </Button>
          }
        />

        {schema.fields.length === 0 ? (
          <div className="text-center py-10 text-ink-500 dark:text-ink-400 text-sm">
            هنوز فیلدی تعریف نشده. برای شروع روی «افزودن فیلد» بزنید.
          </div>
        ) : (
          <div className="space-y-3">
            {schema.fields.map((f, idx) => (
              <div
                key={idx}
                className="rounded-xl border border-ink-200/60 dark:border-ink-800 bg-white/60 dark:bg-ink-900/40 p-3 space-y-3"
              >
                <div className="flex items-center gap-2">
                  <Badge variant="brand">#{idx + 1}</Badge>
                  <span className="text-xs text-ink-500 dark:text-ink-400 font-mono">
                    {f.api_name || '—'}
                  </span>
                  <div className="ms-auto flex gap-1">
                    <button
                      onClick={() => moveField(idx, -1)}
                      className="p-1 text-ink-500 hover:text-ink-900 dark:hover:text-ink-100"
                      aria-label="بالا"
                      disabled={idx === 0}
                    >▲</button>
                    <button
                      onClick={() => moveField(idx, 1)}
                      className="p-1 text-ink-500 hover:text-ink-900 dark:hover:text-ink-100"
                      aria-label="پایین"
                      disabled={idx === schema.fields.length - 1}
                    >▼</button>
                    <button
                      onClick={() => removeField(idx)}
                      className="p-1 text-danger-500 hover:text-danger-700"
                      aria-label="حذف"
                    >✕</button>
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-2">
                  <Input
                    label="برچسب *"
                    value={f.label || ''}
                    onChange={(e) => setField(idx, { label: e.target.value })}
                    placeholder="مثلاً: نام محصول"
                    error={errors[`f_${idx}_label`]}
                  />
                  <Input
                    label="نام سیستمی *"
                    value={f.api_name || ''}
                    onChange={(e) => setField(idx, { api_name: slugify(e.target.value) })}
                    placeholder="name"
                    error={errors[`f_${idx}_api`]}
                  />
                  <Select
                    label="نوع"
                    value={f.type || 'text'}
                    onChange={(e) => setField(idx, { type: e.target.value })}
                    options={FIELD_TYPES.map((t) => ({ value: t.v, label: `${t.g} — ${t.l}` }))}
                  />
                </div>

                <div className="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                  {[
                    { k: 'required',   l: 'الزامی' },
                    { k: 'searchable', l: 'قابل جستجو' },
                    { k: 'unique',     l: 'یکتا' },
                    { k: 'indexed',    l: 'ایندکس' },
                  ].map((opt) => (
                    <label
                      key={opt.k}
                      className="flex items-center gap-2 px-2 py-1.5 rounded-lg border border-ink-200 dark:border-ink-800 cursor-pointer hover:bg-ink-50 dark:hover:bg-ink-900"
                    >
                      <input
                        type="checkbox"
                        checked={!!f[opt.k]}
                        onChange={(e) => setField(idx, { [opt.k]: e.target.checked })}
                        className="accent-brand-500"
                      />
                      <span>{opt.l}</span>
                    </label>
                  ))}
                </div>

                {(f.type === 'enum' || f.type === 'multi') && (
                  <Input
                    label="گزینه‌ها (با کاما جدا کنید)"
                    value={Array.isArray(f.options) ? f.options.join(',') : f.options || ''}
                    onChange={(e) => setField(idx, { options: e.target.value })}
                    placeholder="مثلاً: فعال, غیرفعال, در انتظار"
                  />
                )}

                <Input
                  label="مقدار پیش‌فرض"
                  value={f.default_value || ''}
                  onChange={(e) => setField(idx, { default_value: e.target.value })}
                />
                <Input
                  label="راهنما"
                  value={f.help || ''}
                  onChange={(e) => setField(idx, { help: e.target.value })}
                  placeholder="متن راهنما برای کاربر"
                />
              </div>
            ))}
          </div>
        )}
      </Card>
    </div>
  );
}
