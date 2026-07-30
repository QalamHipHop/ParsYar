/**
 * Tiny REST client. Reads credentials from window.parsYarBoot
 * which is set by the theme's functions.php.
 */

const boot = (): { rest: string; nonce: string } => {
	const w = window as unknown as { parsYarBoot?: { rest: string; nonce: string } };
	return {
		rest:  w.parsYarBoot?.rest  ?? '/wp-json/pars-yar/v1/',
		nonce: w.parsYarBoot?.nonce ?? '',
	};
};

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
	const { rest, nonce } = boot();
	const res = await fetch(rest + path.replace(/^\//, ''), {
		...init,
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
			...(init.headers || {}),
		},
		credentials: 'same-origin',
	});
	if (!res.ok) {
		const text = await res.text();
		throw new Error(`HTTP ${res.status}: ${text}`);
	}
	return res.json() as Promise<T>;
}

export type FieldDef = {
	id: number;
	api_name: string;
	label: string;
	field_type: string;
	required: boolean;
	unique: boolean;
};

export type ObjectDef = {
	id: number;
	api_name: string;
	label: string;
	label_plural: string;
	fields: FieldDef[];
};

export type RecordRow = {
	id: number;
	[key: string]: unknown;
};

export const api = {
	objects: () => request<ObjectDef[]>('objects'),
	records: (apiName: string, limit = 50, offset = 0) =>
		request<{ items: RecordRow[]; count: number }>(
			`objects/${apiName}/records?limit=${limit}&offset=${offset}`
		),
	record: (apiName: string, id: number) =>
		request<RecordRow>(`objects/${apiName}/records/${id}`),
	create: (apiName: string, values: Record<string, unknown>) =>
		request<{ id: number }>(`objects/${apiName}/records`, {
			method: 'POST',
			body: JSON.stringify(values),
		}),
};
