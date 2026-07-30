import React, { useEffect, useState } from 'react';
import { api, ObjectDef } from '../api/client';

export default function Dashboard(): JSX.Element {
	const [objects, setObjects] = useState<ObjectDef[] | null>(null);
	const [error, setError] = useState<string | null>(null);

	useEffect(() => {
		api.objects()
			.then(setObjects)
			.catch((e: Error) => setError(e.message));
	}, []);

	return (
		<div className="space-y-6">
			<header>
				<h1 className="text-2xl font-bold">داشبورد</h1>
				<p className="text-slate-600 text-sm mt-1">
					نمایی کلی از اشیاء ثبت‌شده در سیستم.
				</p>
			</header>

			{error && (
				<div className="bg-red-50 border border-red-200 text-red-700 p-3 rounded-md text-sm">
					{error}
				</div>
			)}

			{!objects && !error && <p className="text-slate-500">در حال بارگذاری…</p>}

			{objects && (
				<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
					{objects.map((o) => (
						<div key={o.id} className="py-card">
							<div className="text-sm text-slate-500">شیء</div>
							<div className="text-lg font-semibold mt-1">{o.label}</div>
							<div className="text-xs text-slate-400 mt-1">API: {o.api_name}</div>
							<div className="mt-3 text-sm text-slate-600">
								{o.fields.length} فیلد
							</div>
						</div>
					))}
				</div>
			)}
		</div>
	);
}
