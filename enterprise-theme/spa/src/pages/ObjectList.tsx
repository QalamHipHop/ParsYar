import React, { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api, ObjectDef, RecordRow, FieldDef } from '../api/client';

export default function ObjectList(): JSX.Element {
	const { apiName } = useParams<{ apiName?: string }>();
	const [objects, setObjects] = useState<ObjectDef[]>([]);
	const [selected, setSelected] = useState<ObjectDef | null>(null);
	const [records, setRecords] = useState<RecordRow[]>([]);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState<string | null>(null);

	useEffect(() => {
		api.objects().then((list) => {
			setObjects(list);
			if (apiName) {
				const match = list.find((o) => o.api_name === apiName);
				if (match) { setSelected(match); }
			} else if (list.length > 0) {
				setSelected(list[0]);
			}
		}).catch((e: Error) => setError(e.message));
	}, [apiName]);

	useEffect(() => {
		if (!selected) { return; }
		setLoading(true);
		api.records(selected.api_name)
			.then((res) => setRecords(res.items))
			.catch((e: Error) => setError(e.message))
			.finally(() => setLoading(false));
	}, [selected]);

	return (
		<div className="space-y-4">
			<header className="flex items-center justify-between">
				<h1 className="text-2xl font-bold">اشیاء</h1>
				{selected && (
					<Link to={`/objects/${selected.api_name}/new`} className="py-btn-primary">
						افزودن رکورد
					</Link>
				)}
			</header>

			{error && (
				<div className="bg-red-50 border border-red-200 text-red-700 p-3 rounded-md text-sm">
					{error}
				</div>
			)}

			<div className="flex gap-2 flex-wrap">
				{objects.map((o) => (
					<button
						key={o.id}
						type="button"
						onClick={() => setSelected(o)}
						className={`py-btn-ghost border ${
							selected?.id === o.id ? 'border-brand-500 text-brand-700' : 'border-transparent'
						}`}
					>
						{o.label}
					</button>
				))}
			</div>

			{selected && (
				<div className="py-card overflow-auto">
					{loading ? (
						<p className="text-slate-500">در حال بارگذاری…</p>
					) : records.length === 0 ? (
						<p className="text-slate-500">هیچ رکوردی یافت نشد.</p>
					) : (
						<RecordTable fields={selected.fields} rows={records} />
					)}
				</div>
			)}
		</div>
	);
}

function RecordTable({ fields, rows }: { fields: FieldDef[]; rows: RecordRow[] }): JSX.Element {
	const cols = fields.slice(0, 6);
	return (
		<table className="w-full text-sm">
			<thead className="text-right text-slate-500 border-b">
				<tr>
					<th className="py-2 pr-2">#</th>
					{cols.map((c) => <th key={c.id} className="py-2 pr-2">{c.label}</th>)}
				</tr>
			</thead>
			<tbody>
				{rows.map((r) => (
					<tr key={r.id} className="border-b last:border-0 hover:bg-slate-50">
						<td className="py-2 pr-2 font-mono text-slate-500">{r.id}</td>
						{cols.map((c) => (
							<td key={c.id} className="py-2 pr-2">
								{String(r[c.api_name] ?? '')}
							</td>
						))}
					</tr>
				))}
			</tbody>
		</table>
	);
}
