import React from 'react';
import { NavLink, Route, Routes } from 'react-router-dom';
import Dashboard from './pages/Dashboard';
import ObjectList from './pages/ObjectList';
import RecordDetail from './pages/RecordDetail';
import Audit from './pages/Audit';

const navItems = [
	{ to: '/',           label: 'داشبورد' },
	{ to: '/objects',    label: 'اشیاء' },
	{ to: '/audit',      label: 'حسابرسی' },
];

export default function App(): JSX.Element {
	return (
		<div className="min-h-screen flex">
			<aside className="w-60 bg-slate-900 text-slate-100 p-4 flex flex-col gap-2">
				<h1 className="text-lg font-bold mb-4">پارس‌یار</h1>
				{navItems.map((item) => (
					<NavLink
						key={item.to}
						to={item.to}
						end={item.to === '/'}
						className={({ isActive }) =>
							`block px-3 py-2 rounded-md text-sm ${
								isActive ? 'bg-brand-600 text-white' : 'hover:bg-slate-800'
							}`
						}
					>
						{item.label}
					</NavLink>
				))}
				<div className="mt-auto text-xs text-slate-400">v0.1.0</div>
			</aside>
			<main className="flex-1 p-6 overflow-auto">
				<Routes>
					<Route path="/" element={<Dashboard />} />
					<Route path="/objects" element={<ObjectList />} />
					<Route path="/objects/:apiName" element={<ObjectList />} />
					<Route path="/objects/:apiName/:id" element={<RecordDetail />} />
					<Route path="/audit" element={<Audit />} />
				</Routes>
			</main>
		</div>
	);
}
