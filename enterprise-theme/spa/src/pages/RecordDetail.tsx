import React, { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api, ObjectDef, FieldDef } from '../api/client';

type FormState = Record<string, string | number | null>;

export default function RecordDetail(): JSX.Element {
	const { apiName, id } = useParams<{ apiName: string; id?: string }>();
	const navigate = useNavigate();
	const [def, setDef] = useState<ObjectDef | null>(null);
	const [form, setForm] = useState<FormState>({});
	const [busy, setBusy] = useState(false);
	const [error, setError] = useState<string | null>(null);

	const isNew = id === 'new' || !id;

	useEffect(() => {
		if (!apiName) { return; }
		api.objects().then((list) => {
			const found = list.find((o) => o.api_name === apiName) ?? null;
			setDef(found);
			if (found && !isNew && id) {
				api.record(apiName, Number(id)).then((row) => {
					const next: FormState = {};
					found.fields.forEach((f) => {
						const v = row[f.api_name];
						next[f.api_name] = v === null || v === undefined ? '' : (v as string | number);
					});
					setForm(next);
				}).catch((e: Error) => setError(e.message));
			}
		}).catch((e: Error) => setError(e.message));
	}, [apiName, id, isNew]);

	if (!def) {
		return <p className="text-slate-500">در حال بارگذاری…</p>;
	}

	const submit = async (e: React.FormEvent): Promise<void> => {
		e.preventDefault();
		if (!apiName) { return; }
		setBusy(true);
		setError(null);
		try {
			const payload: Record<string, unknown> = {};
			Object.entries(form).forEach(([k, v]) => {
				if (v !== '' && v !== null) { payload[k] = v; }
			});
			await api.create(apiName, payload);
			navigate(`/objects/${apiName}`);
		} catch (e) {
			setError((e as Error).message);
		} finally {
			setBusy(false);
		}
	};

	return (
		<div className="space-y-4 max-w-2xl">
			<header>
				<h1 className="text-2xl font-bold">
					{isNew ? `ساخت ${def.label}` : `ویرایش ${def.label} #${id}`}
				</h1>
			</header>

			{error && (
				<div className="bg-red-50 border border-red-200 text-red-700 p-3 rounded-md text-sm">
					{error}
				</div>
			)}

			<form onSubmit={submit} className="py-card space-y-3">
				{def.fields.map((f) => (
					<FieldInput
						key={f.id}
						field={f}
						value={form[f.api_name] ?? ''}
						onChange={(v) => setForm((prev) => ({ ...prev, [f.api_name]: v }))}
					/>
				))}

				<div className="flex gap-2 pt-2">
					<button type="submit" disabled={busy} className="py-btn-primary">
						{busy ? 'در حال ذخیره…' : 'ذخیره'}
					</button>
					<button
						type="button"
						className="py-btn-ghost"
						onClick={() => navigate(`/objects/${apiName}`)}
					>
						انصراف
					</button>
				</div>
			</form>
		</div>
	);
}

function FieldInput({
	field,
	value,
	onChange,
}: {
	field: FieldDef;
	value: string | number | null;
	onChange: (v: string | number) => void;
}): JSX.Element {
	const inputType = (() => {
		switch (field.field_type) {
			case 'email':  return 'email';
			case 'phone':  return 'tel';
			case 'url':    return 'url';
			case 'number': return 'number';
			case 'date':   return 'datetime-local';
			default:       return 'text';
		}
	})();

	return (
		<div>
			<label className="py-label" htmlFor={field.api_name}>
				{field.label}
				{field.required && <span className="text-red-500 mr-1">*</span>}
			</label>
			<input
				id={field.api_name}
				type={inputType}
				value={value ?? ''}
				required={field.required}
				onChange={(e) =>
					onChange(field.field_type === 'number' ? Number(e.target.value) : e.target.value)
				}
				className="py-input"
			/>
		</div>
	);
}
