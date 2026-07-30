import React from 'react';

/**
 * Audit view — placeholder.
 *
 * The full audit explorer (with hash-chain verification, filter by
 * object/record/actor/date range) is served by a dedicated REST endpoint
 * planned for Phase 1.5. This page documents the planned surface.
 */
export default function Audit(): JSX.Element {
	return (
		<div className="space-y-4">
			<header>
				<h1 className="text-2xl font-bold">گزارش حسابرسی</h1>
				<p className="text-slate-600 text-sm mt-1">
					هر تغییر در سیستم در یک زنجیره‌ی هش ثبت می‌شود.
				</p>
			</header>

			<div className="py-card text-sm text-slate-700 space-y-2">
				<p>
					این صفحه در فاز بعدی با موارد زیر تکمیل می‌شود:
				</p>
				<ul className="list-disc pr-5 space-y-1">
					<li>فیلتر بر اساس شیء / رکورد / کاربر / بازه زمانی</li>
					<li>تأیید صحت زنجیره‌ی هش (دکمه‌ی «بررسی یکپارچگی»)</li>
					<li>نمایش diff قبل/بعد برای هر entry</li>
					<li>خروجی CSV برای ارائه به بازرس مالیاتی</li>
				</ul>
			</div>
		</div>
	);
}
