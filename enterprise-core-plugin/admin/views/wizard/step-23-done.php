<?php
/**
 * Wizard Step 23 — Done (v3.0 — celebration).
 *
 * @var array $state
 * @var array $progress
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

use Enterprise\Admin\WizardState;

$done = (int) ($progress['done'] ?? 0);
$skipped = (int) ($progress['skipped'] ?? 0);
$total = (int) ($progress['total'] ?? 23);
$percent = (int) ($progress['percent'] ?? 0);
$state = $state ?? WizardState::load();
?>
<div class="pw-done-hero">
    <div class="pw-done-icon" aria-hidden="true">
        <svg viewBox="0 0 64 64" width="64" height="64" fill="none">
            <circle cx="32" cy="32" r="30" fill="#10b981" stroke="#047857" stroke-width="2"/>
            <path d="M20 32 L28 40 L44 24" stroke="#fff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        </svg>
    </div>
    <h2 class="pw-done-title">نصب ParsYar با موفقیت کامل شد</h2>
    <p class="pw-done-sub">پیکربندی شما آماده است. می‌توانید از دکمهٔ «اعمال و پایان» برای ذخیرهٔ نهایی استفاده کنید.</p>
    <div class="pw-done-stats">
        <div class="pw-done-stat">
            <span class="pw-done-stat-num"><?php echo parsyar_persian_digits((string) $done); ?></span>
            <span class="pw-done-stat-label">تکمیل‌شده</span>
        </div>
        <div class="pw-done-stat">
            <span class="pw-done-stat-num"><?php echo parsyar_persian_digits((string) $skipped); ?></span>
            <span class="pw-done-stat-label">رد شده</span>
        </div>
        <div class="pw-done-stat">
            <span class="pw-done-stat-num"><?php echo parsyar_persian_digits((string) $percent); ?>٪</span>
            <span class="pw-done-stat-label">پیشرفت</span>
        </div>
    </div>
</div>

<h3 style="font-size:15px; font-weight:800; margin:24px 0 12px;">خلاصهٔ پیکربندی</h3>
<div class="pw-done-summary">
    <div class="pw-done-summary-item">
        <span class="pw-done-summary-label">حالت استقرار</span>
        <span class="pw-done-summary-value"><?php echo esc_html($state['mode'] ?? '—'); ?></span>
    </div>
    <div class="pw-done-summary-item">
        <span class="pw-done-summary-label">سازمان</span>
        <span class="pw-done-summary-value"><?php echo esc_html($state['org']['name'] ?? '—'); ?></span>
    </div>
    <div class="pw-done-summary-item">
        <span class="pw-done-summary-label">زبان و منطقه</span>
        <span class="pw-done-summary-value"><?php echo esc_html(($state['language'] ?? '—') . ' · ' . ($state['timezone'] ?? '—')); ?></span>
    </div>
    <div class="pw-done-summary-item">
        <span class="pw-done-summary-label">ارز پایه</span>
        <span class="pw-done-summary-value"><?php echo esc_html($state['base_currency'] ?? '—'); ?></span>
    </div>
    <div class="pw-done-summary-item">
        <span class="pw-done-summary-label">سال مالی</span>
        <span class="pw-done-summary-value"><?php echo esc_html(($state['fiscal_type'] ?? '—') . ' · ' . ($state['fiscal_start_md'] ?? '')); ?></span>
    </div>
    <div class="pw-done-summary-item">
        <span class="pw-done-summary-label">تقویم شمسی</span>
        <span class="pw-done-summary-value"><?php echo esc_html($state['jalali_mode'] ?? '—'); ?></span>
    </div>
    <div class="pw-done-summary-item pw-done-summary-wide">
        <span class="pw-done-summary-label">ماژول‌های فعال</span>
        <span class="pw-done-summary-value">
            <?php
            $activeModules = array_keys(array_filter($state['modules'] ?? []));
            if (!empty($activeModules)) {
                echo '<span class="pw-done-modules">';
                foreach ($activeModules as $m) {
                    echo '<span class="pw-done-module-chip">' . esc_html($m) . '</span>';
                }
                echo '</span>';
            } else {
                echo '—';
            }
            ?>
        </span>
    </div>
    <div class="pw-done-summary-item pw-done-summary-wide">
        <span class="pw-done-summary-label">ارزها</span>
        <span class="pw-done-summary-value">
            <?php
            $currs = $state['currencies'] ?? [];
            if (!empty($currs)) {
                echo '<span class="pw-done-modules">';
                foreach ($currs as $c) {
                    echo '<span class="pw-done-module-chip">' . esc_html($c) . '</span>';
                }
                echo '</span>';
            } else {
                echo '—';
            }
            ?>
        </span>
    </div>
</div>

<h3 style="font-size:15px; font-weight:800; margin:24px 0 12px;">قدم‌های بعدی</h3>
<div class="pw-done-actions">
    <a class="pw-done-action" href="<?php echo esc_url(admin_url('admin.php?page=enterprise')); ?>">
        <div class="pw-done-action-icon">📊</div>
        <div class="pw-done-action-text">
            <b>داشبورد Enterprise</b>
            <span>آمار لحظه‌ای، KPI، و نمودارها</span>
        </div>
    </a>
    <a class="pw-done-action" href="<?php echo esc_url(admin_url('admin.php?page=enterprise-objects')); ?>">
        <div class="pw-done-action-icon">📦</div>
        <div class="pw-done-action-text">
            <b>اشیاء سفارشی</b>
            <span>تعریف entityهای جدید بدون کد</span>
        </div>
    </a>
    <a class="pw-done-action" href="<?php echo esc_url(home_url('/enterprise')); ?>" target="_blank">
        <div class="pw-done-action-icon">⚡</div>
        <div class="pw-done-action-text">
            <b>پنل کاربری (SPA)</b>
            <span>رابط اصلی برای کاربران</span>
        </div>
    </a>
    <a class="pw-done-action" href="<?php echo esc_url(admin_url('admin.php?page=enterprise-setup')); ?>">
        <div class="pw-done-action-icon">🧙</div>
        <div class="pw-done-action-text">
            <b>ویزارد نصب</b>
            <span>بازگشت به ویزارد در هر زمان</span>
        </div>
    </a>
    <a class="pw-done-action" href="https://github.com/QalamHipHop/ParsYar" target="_blank" rel="noopener">
        <div class="pw-done-action-icon">📚</div>
        <div class="pw-done-action-text">
            <b>مستندات</b>
            <span>github.com/QalamHipHop/ParsYar</span>
        </div>
    </a>
    <a class="pw-done-action" href="<?php echo esc_url(admin_url('admin.php?page=enterprise-system')); ?>">
        <div class="pw-done-action-icon">🛠️</div>
        <div class="pw-done-action-text">
            <b>سلامت سیستم</b>
            <span>بررسی وضعیت سرویس‌ها و ماژول‌ها</span>
        </div>
    </a>
</div>

<div class="pw-banner info" style="margin-top:20px;">
    💡 برای اعمال تغییرات، دکمهٔ «اعمال و پایان» را بزنید. جداول، حساب‌های پیش‌فرض، و در صورت انتخاب، دادهٔ دمو ایجاد می‌شود.
</div>

<style>
.pw-done-hero {
    text-align: center;
    padding: 24px 20px;
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
    border: 2px solid #86efac;
    border-radius: 16px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}
.pw-done-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 20% 50%, rgba(16, 185, 129, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(52, 120, 255, 0.1) 0%, transparent 50%);
    pointer-events: none;
}
.pw-done-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 16px;
    animation: pw-done-pop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes pw-done-pop {
    0% { transform: scale(0); }
    100% { transform: scale(1); }
}
.pw-done-title {
    margin: 0 0 6px;
    font-size: 22px;
    font-weight: 800;
    color: #047857;
    position: relative;
}
.pw-done-sub {
    margin: 0 0 20px;
    font-size: 13px;
    color: #065f46;
    position: relative;
}
.pw-done-stats {
    display: flex;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
    position: relative;
}
.pw-done-stat {
    background: #fff;
    border: 1.5px solid #86efac;
    border-radius: 12px;
    padding: 12px 20px;
    min-width: 90px;
    text-align: center;
}
.pw-done-stat-num {
    display: block;
    font-size: 22px;
    font-weight: 800;
    color: #047857;
    line-height: 1;
}
.pw-done-stat-label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: #065f46;
    margin-top: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pw-done-summary {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    background: var(--pw-surface);
    border: 1px solid var(--pw-line);
    border-radius: 12px;
    padding: 4px;
}
.pw-done-summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    border-radius: 8px;
    background: #fff;
    border: 1px solid var(--pw-line);
    gap: 12px;
}
.pw-done-summary-wide { grid-column: 1 / -1; }
.pw-done-summary-label {
    font-size: 12px;
    color: var(--pw-fg-soft);
    font-weight: 600;
    white-space: nowrap;
}
.pw-done-summary-value {
    font-size: 13px;
    font-weight: 700;
    color: var(--pw-fg);
    text-align: left;
    word-break: break-word;
}
.pw-done-modules {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 4px;
    justify-content: flex-end;
}
.pw-done-module-chip {
    display: inline-block;
    padding: 2px 8px;
    background: rgba(9,9,11,0.06);
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    color: var(--pw-fg-soft);
}

.pw-done-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}
.pw-done-action {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px;
    background: #fff;
    border: 2px solid var(--pw-line);
    border-radius: 12px;
    text-decoration: none;
    color: var(--pw-fg);
    transition: all 0.15s var(--pw-ease);
    cursor: pointer;
}
.pw-done-action:hover {
    transform: translate(-2px, -2px);
    box-shadow: var(--pw-shadow-brutal-sm);
    border-color: var(--pw-fg);
    color: var(--pw-fg);
}
.pw-done-action-icon {
    font-size: 24px;
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    background: var(--pw-fg);
    color: var(--pw-bg);
    border-radius: 10px;
}
.pw-done-action-text {
    flex: 1;
    min-width: 0;
}
.pw-done-action-text b {
    display: block;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 2px;
}
.pw-done-action-text span {
    display: block;
    font-size: 11.5px;
    color: var(--pw-fg-soft);
    line-height: 1.3;
}

@media (max-width: 720px) {
    .pw-done-summary { grid-template-columns: 1fr; }
    .pw-done-actions { grid-template-columns: 1fr; }
}
</style>
