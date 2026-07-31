<?php
/**
 * Setup Wizard layout shell — v3.0 (Modern Design System).
 *
 * @var array $state
 * @var int   $current
 * @var array $progress
 * @var array $system
 * @var string $body (rendered step view)
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

use Enterprise\Admin\WizardState;

if (!function_exists('parsyar_persian_digits')) {
    /**
     * تبدیل ارقام انگلیسی به فارسی برای نمایش.
     */
    function parsyar_persian_digits(string $s): string
    {
        $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $english = ['0','1','2','3','4','5','6','7','8','9'];
        return str_replace($english, $persian, $s);
    }
}

$state    = $state ?? WizardState::load();
$current  = (int) ($current ?? ($state['current_step'] ?? 1));
$progress = $progress ?? WizardState::progress($state);
$system   = $system ?? [];
$body     = $body ?? '';

// برای استفاده در JS
$state_json = wp_json_encode($state);
?>
<div class="wrap parsyar-wizard pw-v3" dir="rtl" data-step="<?php echo (int) $current; ?>" data-state='<?php echo esc_attr($state_json); ?>'>
    <!-- Animated background -->
    <div class="pw-bg" aria-hidden="true">
        <div class="pw-bg-orb pw-bg-orb-1"></div>
        <div class="pw-bg-orb pw-bg-orb-2"></div>
        <div class="pw-bg-grid"></div>
    </div>

    <!-- Top header bar -->
    <header class="pw-header">
        <div class="pw-header-inner">
            <div class="pw-brand">
                <div class="pw-logo">پ</div>
                <div class="pw-brand-text">
                    <h1>ویزارد نصب ParsYar</h1>
                    <p>راه‌اندازی گام‌به‌گام پلتفرم سازمانی — قابل ازسرگیری، قابل صدور/ورود</p>
                </div>
            </div>
            <div class="pw-header-actions">
                <button type="button" class="pw-btn pw-btn-ghost" id="pw-export" title="برون‌بری پیکربندی">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                    <span>برون‌بری</span>
                </button>
                <label class="pw-btn pw-btn-ghost" for="pw-import-file" title="درون‌ریزی پیکربندی">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                    <span>درون‌ریزی</span>
                </label>
                <input type="file" id="pw-import-file" accept="application/json" hidden>
                <button type="button" class="pw-btn pw-btn-danger-ghost" id="pw-restart" title="شروع از نو">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8M21 3v5h-5M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16M3 21v-5h5"/></svg>
                    <span>شروع از نو</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Progress bar -->
    <div class="pw-progress">
        <div class="pw-progress-stats">
            <div class="pw-progress-numbers">
                <span class="pw-progress-done"><?php echo parsyar_persian_digits((string) $progress['done']); ?></span>
                <span class="pw-progress-sep">/</span>
                <span class="pw-progress-total"><?php echo parsyar_persian_digits((string) $progress['total']); ?></span>
                <span class="pw-progress-label">تکمیل‌شده</span>
            </div>
            <div class="pw-progress-percent">
                <span class="pw-progress-pct"><?php echo parsyar_persian_digits((string) $progress['percent']); ?></span><span>%</span>
            </div>
        </div>
        <div class="pw-progress-track">
            <div class="pw-progress-bar" style="width: <?php echo (int) $progress['percent']; ?>%">
                <div class="pw-progress-shine"></div>
            </div>
        </div>
        <div class="pw-progress-meta">
            <?php if ($progress['skipped']): ?>
                <span class="pw-pill pw-pill-skip"><?php echo parsyar_persian_digits((string) $progress['skipped']); ?> رد شده</span>
            <?php endif; ?>
            <?php if ($progress['remaining']): ?>
                <span class="pw-pill pw-pill-remain"><?php echo parsyar_persian_digits((string) $progress['remaining']); ?> باقی‌مانده</span>
            <?php endif; ?>
            <?php if ($progress['done'] === $progress['total']): ?>
                <span class="pw-pill pw-pill-done">آماده اعمال</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main grid: rail + card -->
    <div class="pw-grid">
        <aside class="pw-rail" aria-label="مراحل">
            <div class="pw-rail-head">
                <span class="pw-rail-title">مراحل نصب</span>
                <span class="pw-rail-count"><?php echo parsyar_persian_digits((string) $current); ?> / <?php echo parsyar_persian_digits((string) WizardState::STEPS); ?></span>
            </div>
            <ol class="pw-steps">
                <?php for ($i = 1; $i <= WizardState::STEPS; $i++):
                    $cls = 'pw-step';
                    $state_attr = '';
                    if ($i === $current) {
                        $cls .= ' is-current';
                        $state_attr = 'current';
                    } elseif (WizardState::isCompleted($i, $state)) {
                        $cls .= ' is-done';
                        $state_attr = 'done';
                    } elseif (WizardState::isSkipped($i, $state)) {
                        $cls .= ' is-skip';
                        $state_attr = 'skipped';
                    } else {
                        $cls .= ' is-pending';
                        $state_attr = 'pending';
                    }
                    if ($i < $current) {
                        $cls .= ' is-prev';
                    }
                ?>
                    <li class="<?php echo esc_attr($cls); ?>" data-step="<?php echo (int) $i; ?>" data-state="<?php echo esc_attr($state_attr); ?>">
                        <span class="pw-step-num"><?php echo parsyar_persian_digits((string) $i); ?></span>
                        <span class="pw-step-label"><?php echo esc_html(WizardState::STEP_LABELS[$i] ?? ''); ?></span>
                        <span class="pw-step-state" aria-hidden="true">
                            <?php if ($state_attr === 'done'): ?>✓<?php elseif ($state_attr === 'skipped'): ?>–<?php elseif ($state_attr === 'current'): ?>●<?php endif; ?>
                        </span>
                    </li>
                <?php endfor; ?>
            </ol>
            <div class="pw-rail-foot">
                <div class="pw-rail-version">v3.0 · modern</div>
            </div>
        </aside>

        <main class="pw-main">
            <div class="pw-card" data-step="<?php echo (int) $current; ?>">
                <div class="pw-card-glow" aria-hidden="true"></div>
                <div class="pw-card-head">
                    <div class="pw-card-head-text">
                        <span class="pw-step-tag">گام <?php echo parsyar_persian_digits((string) $current); ?> از <?php echo parsyar_persian_digits((string) WizardState::STEPS); ?></span>
                        <h2><?php echo esc_html(WizardState::STEP_LABELS[$current] ?? ''); ?></h2>
                    </div>
                    <div class="pw-card-head-actions">
                        <span class="pw-card-status" id="pw-card-status" data-state="idle">
                            <span class="pw-card-status-dot"></span>
                            <span class="pw-card-status-text">آماده</span>
                        </span>
                    </div>
                </div>
                <div class="pw-card-body" id="pw-step-body">
                    <?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <div class="pw-card-foot">
                    <button type="button" class="pw-btn pw-btn-ghost" data-pw-nav="prev" <?php echo $current <= 1 ? 'disabled' : ''; ?>>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                        <span>قبلی</span>
                    </button>
                    <div class="pw-card-foot-spacer"></div>
                    <button type="button" class="pw-btn pw-btn-secondary" data-pw-nav="skip">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/></svg>
                        <span>رد کردن</span>
                    </button>
                    <button type="button" class="pw-btn pw-btn-primary" data-pw-nav="next" id="pw-next-btn">
                        <span><?php echo $current === WizardState::STEPS ? 'اعمال و پایان' : 'بعدی'; ?></span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg>
                    </button>
                </div>
            </div>

            <!-- System check & tips aside -->
            <aside class="pw-side">
                <?php if ($current === 1 && !empty($system)): ?>
                <div class="pw-side-card pw-side-syscheck">
                    <h3>بررسی سیستم</h3>
                    <ul class="pw-sys-list">
                        <?php foreach ($system as $key => $check): ?>
                            <li class="pw-sys-item <?php echo !empty($check['ok']) ? 'is-ok' : 'is-fail'; ?>">
                                <span class="pw-sys-icon"><?php echo !empty($check['ok']) ? '✓' : '!'; ?></span>
                                <span class="pw-sys-label"><?php echo esc_html($check['label'] ?? $key); ?></span>
                                <span class="pw-sys-meta"><?php echo esc_html($check['value'] ?? ''); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <div class="pw-side-card pw-side-tips">
                    <h3>نکات کلیدی</h3>
                    <ul class="pw-tips">
                        <li>می‌توانید هر مرحله را با <b>رد کردن</b> رد کنید و بعداً از تنظیمات تکمیل کنید.</li>
                        <li>پیکربندی فعلی با <b>برون‌بری</b> قابل ذخیره روی چند سایت است.</li>
                        <li>تغییرات هر مرحله به‌صورت خودکار ذخیره می‌شود.</li>
                    </ul>
                </div>
            </aside>
        </main>
    </div>

    <!-- Toast container -->
    <div class="pw-toast-stack" id="pw-toast-stack" aria-live="polite" aria-atomic="false"></div>
</div>

<?php
/**
 * تبدیل ارقام انگلیسی به فارسی برای نمایش.
 */
function parsyar_persian_digits(string $s): string {
    $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $english = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($english, $persian, $s);
}
?>
<style>
/* ============================================================
 *  ParsYar Wizard v3.0 — Glassmorphism + Neo-brutalism
 *  Modern design system with smooth animations
 * ============================================================ */

.parsyar-wizard.pw-v3 {
    --pw-bg: #fafafa;
    --pw-fg: #09090b;
    --pw-fg-soft: #52525b;
    --pw-fg-mute: #a1a1aa;
    --pw-line: rgba(9,9,11,0.08);
    --pw-line-strong: rgba(9,9,11,0.18);
    --pw-surface: rgba(255,255,255,0.72);
    --pw-surface-strong: rgba(255,255,255,0.92);
    --pw-glass-border: rgba(255,255,255,0.6);
    --pw-shadow: 0 8px 32px 0 rgba(9,9,11,0.08);
    --pw-shadow-brutal: 6px 6px 0 0 #09090b;
    --pw-shadow-brutal-sm: 3px 3px 0 0 #09090b;
    --pw-accent: #3478ff;
    --pw-accent-fg: #ffffff;
    --pw-success: #10b981;
    --pw-warning: #f59e0b;
    --pw-danger: #ef4444;
    --pw-ease: cubic-bezier(0.16, 1, 0.3, 1);
    --pw-ease-bounce: cubic-bezier(0.34, 1.56, 0.64, 1);

    max-width: 1400px;
    margin: 16px auto;
    padding: 0 16px 32px;
    font-family: 'Vazirmatn', 'IRANSansX', Tahoma, system-ui, -apple-system, sans-serif;
    color: var(--pw-fg);
    position: relative;
    min-height: calc(100vh - 32px);
}

/* ===== Animated background ===== */
.pw-bg {
    position: fixed;
    inset: 0;
    z-index: -1;
    overflow: hidden;
    pointer-events: none;
}
.pw-bg-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.4;
    animation: pw-float 18s ease-in-out infinite;
}
.pw-bg-orb-1 {
    width: 480px;
    height: 480px;
    background: radial-gradient(circle, #3478ff 0%, transparent 70%);
    top: -120px;
    right: -120px;
    animation-delay: 0s;
}
.pw-bg-orb-2 {
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, #10b981 0%, transparent 70%);
    bottom: -200px;
    left: -200px;
    animation-delay: -9s;
}
.pw-bg-grid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(to right, rgba(9,9,11,0.03) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(9,9,11,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
    mask-image: radial-gradient(circle at 50% 30%, black 0%, transparent 80%);
    -webkit-mask-image: radial-gradient(circle at 50% 30%, black 0%, transparent 80%);
}
@keyframes pw-float {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(30px, -20px) scale(1.05); }
    66% { transform: translate(-20px, 30px) scale(0.95); }
}

/* ===== Header ===== */
.pw-header {
    margin: 0 -16px 16px;
    padding: 16px 24px;
    background: var(--pw-surface);
    backdrop-filter: blur(24px) saturate(180%);
    -webkit-backdrop-filter: blur(24px) saturate(180%);
    border-bottom: 1px solid var(--pw-line);
    position: sticky;
    top: 32px;
    z-index: 50;
}
.pw-header-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    max-width: 1368px;
    margin: 0 auto;
}
.pw-brand {
    display: flex;
    align-items: center;
    gap: 12px;
}
.pw-logo {
    width: 44px;
    height: 44px;
    background: var(--pw-fg);
    color: var(--pw-bg);
    display: grid;
    place-items: center;
    font-weight: 800;
    font-size: 20px;
    border-radius: 12px;
    box-shadow: var(--pw-shadow-brutal-sm);
    transition: transform 0.3s var(--pw-ease-bounce);
}
.pw-logo:hover { transform: rotate(-6deg) scale(1.05); }
.pw-brand-text h1 { margin: 0; font-size: 18px; font-weight: 800; letter-spacing: -0.01em; }
.pw-brand-text p { margin: 2px 0 0; font-size: 12px; color: var(--pw-fg-soft); }
.pw-header-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

/* ===== Buttons (neo-brutalist) ===== */
.pw-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 14px;
    border: 2px solid var(--pw-fg);
    background: var(--pw-bg);
    color: var(--pw-fg);
    border-radius: 10px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: transform 0.15s var(--pw-ease), box-shadow 0.15s var(--pw-ease), background 0.15s;
    user-select: none;
    white-space: nowrap;
    font-family: inherit;
}
.pw-btn:hover:not(:disabled) {
    transform: translate(-2px, -2px);
    box-shadow: var(--pw-shadow-brutal-sm);
}
.pw-btn:active:not(:disabled) { transform: translate(0, 0); box-shadow: none; }
.pw-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.pw-btn-primary {
    background: var(--pw-fg);
    color: var(--pw-bg);
    box-shadow: var(--pw-shadow-brutal-sm);
}
.pw-btn-secondary {
    background: var(--pw-bg);
    color: var(--pw-fg);
}
.pw-btn-ghost {
    background: transparent;
    border-color: var(--pw-line-strong);
    color: var(--pw-fg-soft);
}
.pw-btn-ghost:hover { color: var(--pw-fg); border-color: var(--pw-fg); }
.pw-btn-danger-ghost {
    background: transparent;
    border-color: var(--pw-danger);
    color: var(--pw-danger);
}
.pw-btn-danger-ghost:hover {
    background: var(--pw-danger);
    color: #fff;
    transform: translate(-2px, -2px);
    box-shadow: 3px 3px 0 0 var(--pw-danger);
}
.pw-btn .spinner {
    width: 12px;
    height: 12px;
    border: 2px solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: pw-spin 0.8s linear infinite;
    display: inline-block;
}
@keyframes pw-spin { to { transform: rotate(360deg); } }

/* ===== Progress ===== */
.pw-progress {
    background: var(--pw-surface);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid var(--pw-line);
    border-radius: 14px;
    padding: 14px 20px;
    margin-bottom: 16px;
}
.pw-progress-stats {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 10px;
}
.pw-progress-numbers {
    display: flex;
    align-items: baseline;
    gap: 4px;
    font-weight: 800;
}
.pw-progress-done { font-size: 28px; color: var(--pw-fg); }
.pw-progress-sep { font-size: 18px; color: var(--pw-fg-mute); }
.pw-progress-total { font-size: 14px; color: var(--pw-fg-soft); }
.pw-progress-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--pw-fg-soft);
    margin-right: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.pw-progress-pct {
    font-size: 22px;
    font-weight: 800;
    color: var(--pw-fg);
    font-variant-numeric: tabular-nums;
}
.pw-progress-track {
    height: 8px;
    background: rgba(9,9,11,0.06);
    border-radius: 6px;
    overflow: hidden;
    position: relative;
}
.pw-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--pw-fg) 0%, var(--pw-fg) 60%, var(--pw-accent) 100%);
    border-radius: 6px;
    position: relative;
    overflow: hidden;
    transition: width 0.6s var(--pw-ease);
    min-width: 8px;
}
.pw-progress-shine {
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    animation: pw-shimmer 2.5s linear infinite;
    background-size: 200% 100%;
}
@keyframes pw-shimmer { from { background-position: 200% 0; } to { background-position: -200% 0; } }
.pw-progress-meta {
    margin-top: 8px;
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.pw-pill {
    display: inline-flex;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    border: 1px solid;
}
.pw-pill-skip { background: #fff7ed; color: #9a3412; border-color: #fed7aa; }
.pw-pill-remain { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
.pw-pill-done { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }

/* ===== Main grid ===== */
.pw-grid {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 16px;
}

/* ===== Rail (left steps) ===== */
.pw-rail {
    background: var(--pw-surface);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid var(--pw-line);
    border-radius: 14px;
    padding: 14px;
    position: sticky;
    top: 120px;
    height: fit-content;
    max-height: calc(100vh - 140px);
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}
.pw-rail-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 8px 10px;
    border-bottom: 1px solid var(--pw-line);
    margin-bottom: 8px;
}
.pw-rail-title {
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--pw-fg-soft);
}
.pw-rail-count {
    font-size: 11px;
    font-weight: 700;
    color: var(--pw-fg-mute);
    background: rgba(9,9,11,0.05);
    padding: 2px 8px;
    border-radius: 999px;
}
.pw-steps {
    list-style: none;
    margin: 0;
    padding: 0;
    flex: 1;
    counter-reset: step;
}
.pw-step {
    display: flex;
    gap: 10px;
    align-items: center;
    padding: 9px 10px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 12.5px;
    font-weight: 600;
    transition: all 0.18s var(--pw-ease);
    border: 1px solid transparent;
    position: relative;
    color: var(--pw-fg-soft);
}
.pw-step:hover {
    background: rgba(9,9,11,0.04);
    color: var(--pw-fg);
    transform: translateX(-2px);
}
.pw-step.is-done {
    color: var(--pw-fg);
}
.pw-step.is-done .pw-step-num {
    background: var(--pw-success);
    color: #fff;
    border-color: var(--pw-success);
}
.pw-step.is-skip {
    color: var(--pw-fg-mute);
    text-decoration: line-through;
    text-decoration-color: var(--pw-fg-mute);
}
.pw-step.is-skip .pw-step-num {
    background: transparent;
    border-style: dashed;
}
.pw-step.is-current {
    background: var(--pw-fg);
    color: var(--pw-bg);
    box-shadow: var(--pw-shadow-brutal-sm);
    transform: translateX(-3px);
}
.pw-step.is-current .pw-step-num {
    background: var(--pw-bg);
    color: var(--pw-fg);
    animation: pw-pulse 2s ease-in-out infinite;
}
.pw-step.is-pending { color: var(--pw-fg-mute); }
.pw-step-num {
    width: 26px;
    height: 26px;
    display: grid;
    place-items: center;
    background: rgba(9,9,11,0.05);
    color: var(--pw-fg-soft);
    border-radius: 7px;
    font-size: 11px;
    font-weight: 800;
    flex-shrink: 0;
    border: 1.5px solid transparent;
    transition: all 0.2s;
    font-variant-numeric: tabular-nums;
}
.pw-step-label { flex: 1; line-height: 1.35; }
.pw-step-state {
    font-weight: 800;
    font-size: 13px;
    width: 18px;
    text-align: center;
    flex-shrink: 0;
}
.pw-step.is-current .pw-step-state { color: var(--pw-accent); }
@keyframes pw-pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.08); }
}
.pw-rail-foot {
    margin-top: 12px;
    padding-top: 10px;
    border-top: 1px solid var(--pw-line);
    text-align: center;
}
.pw-rail-version {
    font-size: 10px;
    color: var(--pw-fg-mute);
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

/* ===== Card (main step) ===== */
.pw-main {
    display: grid;
    grid-template-columns: 1fr 280px;
    gap: 16px;
    align-items: start;
}
.pw-card {
    background: var(--pw-surface-strong);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid var(--pw-line);
    border-radius: 16px;
    overflow: hidden;
    position: relative;
    box-shadow: var(--pw-shadow);
    animation: pw-fade-up 0.4s var(--pw-ease);
}
@keyframes pw-fade-up {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
.pw-card-glow {
    position: absolute;
    top: -50%;
    left: -10%;
    width: 60%;
    height: 200%;
    background: radial-gradient(circle, rgba(52, 120, 255, 0.12), transparent 60%);
    pointer-events: none;
    filter: blur(40px);
    animation: pw-float 12s ease-in-out infinite;
}
.pw-card-head {
    padding: 20px 24px;
    border-bottom: 1px solid var(--pw-line);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    position: relative;
    background: rgba(255,255,255,0.5);
}
.pw-card-head-text { flex: 1; min-width: 0; }
.pw-step-tag {
    font-size: 11px;
    color: var(--pw-fg-soft);
    text-transform: uppercase;
    letter-spacing: 0.6px;
    font-weight: 700;
    display: inline-block;
    padding: 2px 8px;
    background: rgba(9,9,11,0.05);
    border-radius: 6px;
}
.pw-card-head h2 {
    margin: 8px 0 0;
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -0.01em;
    color: var(--pw-fg);
}
.pw-card-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    background: rgba(16, 185, 129, 0.1);
    color: #047857;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    border: 1px solid rgba(16, 185, 129, 0.3);
}
.pw-card-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #10b981;
    animation: pw-pulse 1.5s ease-in-out infinite;
}
.pw-card-status[data-state="saving"] {
    background: rgba(245, 158, 11, 0.1);
    color: #92400e;
    border-color: rgba(245, 158, 11, 0.3);
}
.pw-card-status[data-state="saving"] .pw-card-status-dot {
    background: #f59e0b;
    animation: pw-spin 1s linear infinite;
}
.pw-card-status[data-state="error"] {
    background: rgba(239, 68, 68, 0.1);
    color: #b91c1c;
    border-color: rgba(239, 68, 68, 0.3);
}
.pw-card-status[data-state="error"] .pw-card-status-dot {
    background: #ef4444;
    animation: none;
}
.pw-card-body {
    padding: 24px;
    min-height: 320px;
    position: relative;
    z-index: 1;
}
.pw-card-foot {
    padding: 14px 24px;
    border-top: 1px solid var(--pw-line);
    display: flex;
    gap: 8px;
    align-items: center;
    background: rgba(255,255,255,0.5);
}
.pw-card-foot-spacer { flex: 1; }

/* ===== Side cards (syscheck + tips) ===== */
.pw-side { display: flex; flex-direction: column; gap: 12px; position: sticky; top: 120px; }
.pw-side-card {
    background: var(--pw-surface);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid var(--pw-line);
    border-radius: 14px;
    padding: 16px;
}
.pw-side-card h3 {
    margin: 0 0 12px;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--pw-fg-soft);
}
.pw-sys-list { list-style: none; margin: 0; padding: 0; }
.pw-sys-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 0;
    border-bottom: 1px solid var(--pw-line);
    font-size: 12px;
}
.pw-sys-item:last-child { border-bottom: 0; }
.pw-sys-icon {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 11px;
    font-weight: 800;
    flex-shrink: 0;
}
.pw-sys-item.is-ok .pw-sys-icon { background: #ecfdf5; color: #047857; }
.pw-sys-item.is-fail .pw-sys-icon { background: #fef2f2; color: #b91c1c; }
.pw-sys-label { flex: 1; font-weight: 600; color: var(--pw-fg); }
.pw-sys-meta { font-size: 11px; color: var(--pw-fg-soft); font-family: ui-monospace, monospace; }
.pw-tips { margin: 0; padding-right: 18px; }
.pw-tips li { font-size: 12px; line-height: 1.7; color: var(--pw-fg-soft); margin-bottom: 8px; }
.pw-tips li b { color: var(--pw-fg); font-weight: 700; }

/* ===== Field styles (passed to step views) ===== */
.pw-field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
.pw-field label { font-weight: 700; font-size: 13px; color: var(--pw-fg); }
.pw-field .desc { font-size: 11.5px; color: var(--pw-fg-soft); }
.pw-field input[type="text"],
.pw-field input[type="email"],
.pw-field input[type="number"],
.pw-field input[type="url"],
.pw-field input[type="password"],
.pw-field input[type="tel"],
.pw-field select,
.pw-field textarea,
.pw-input {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid var(--pw-line-strong);
    border-radius: 10px;
    background: #fff;
    color: var(--pw-fg);
    font-size: 14px;
    font-family: inherit;
    transition: all 0.15s var(--pw-ease);
}
.pw-field input:focus,
.pw-field select:focus,
.pw-field textarea:focus,
.pw-input:focus {
    outline: none;
    border-color: var(--pw-fg);
    box-shadow: 0 0 0 3px rgba(9,9,11,0.1);
}
.pw-field textarea { min-height: 90px; resize: vertical; }
.pw-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
.pw-row-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.pw-row-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
.pw-actions-row { display: flex; gap: 8px; flex-wrap: wrap; }
.pw-divider { border: 0; border-top: 1px solid var(--pw-line); margin: 20px 0; }
.pw-banner {
    padding: 12px 14px;
    border-radius: 10px;
    font-size: 13px;
    margin-bottom: 16px;
    border: 1px solid;
}
.pw-banner.info { background: #f4f4f5; color: #1f1f1f; border-color: #e4e4e7; }
.pw-banner.success { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
.pw-banner.warning { background: #fffbeb; color: #92400e; border-color: #fde58a; }
.pw-banner.danger { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }

.pw-chip {
    display: inline-flex;
    gap: 6px;
    align-items: center;
    padding: 6px 12px;
    border: 2px solid var(--pw-line-strong);
    border-radius: 999px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    user-select: none;
    transition: all 0.15s var(--pw-ease);
    background: #fff;
}
.pw-chip:hover { border-color: var(--pw-fg); }
.pw-chip.is-on {
    background: var(--pw-fg);
    color: var(--pw-bg);
    border-color: var(--pw-fg);
}
.pw-check-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.pw-check {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 10px;
    border: 1.5px solid var(--pw-line);
    border-radius: 10px;
    background: #fff;
}
.pw-check.ok { border-color: #a7f3d0; background: #f0fdf4; }
.pw-check.fail { border-color: #fecaca; background: #fef2f2; }
.pw-check-icon { font-weight: 800; font-size: 14px; }
.pw-check.ok .pw-check-icon { color: #047857; }
.pw-check.fail .pw-check-icon { color: #b91c1c; }
.pw-check-meta { flex: 1; }
.pw-check-meta b { display: block; font-size: 12.5px; }
.pw-check-meta span { font-size: 11.5px; color: var(--pw-fg-soft); }

.pw-mode-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
.pw-mode {
    border: 2px solid var(--pw-line);
    border-radius: 12px;
    padding: 14px;
    cursor: pointer;
    text-align: center;
    background: #fff;
    transition: all 0.15s var(--pw-ease);
}
.pw-mode:hover { transform: translateY(-2px); box-shadow: var(--pw-shadow-brutal-sm); }
.pw-mode.is-on { border-color: var(--pw-fg); background: var(--pw-fg); color: var(--pw-bg); box-shadow: var(--pw-shadow-brutal-sm); }
.pw-mode h3 { margin: 0 0 4px; font-size: 14px; }
.pw-mode p { margin: 0; font-size: 11px; opacity: 0.7; }
.pw-mode-icon { font-size: 22px; display: block; margin-bottom: 6px; }

/* ===== Toasts ===== */
.pw-toast-stack {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 8px;
    pointer-events: none;
}
.pw-toast {
    pointer-events: auto;
    background: var(--pw-surface-strong);
    backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid var(--pw-line);
    border-radius: 12px;
    padding: 12px 16px;
    min-width: 280px;
    max-width: 380px;
    box-shadow: var(--pw-shadow);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 600;
    animation: pw-slide-up 0.3s var(--pw-ease-bounce);
    border-right: 4px solid var(--pw-fg);
}
.pw-toast.is-success { border-right-color: var(--pw-success); }
.pw-toast.is-error { border-right-color: var(--pw-danger); }
.pw-toast.is-warning { border-right-color: var(--pw-warning); }
.pw-toast.is-info { border-right-color: var(--pw-accent); }
.pw-toast-icon {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 12px;
    font-weight: 800;
    flex-shrink: 0;
}
.pw-toast.is-success .pw-toast-icon { background: #ecfdf5; color: #047857; }
.pw-toast.is-error .pw-toast-icon { background: #fef2f2; color: #b91c1c; }
.pw-toast.is-warning .pw-toast-icon { background: #fffbeb; color: #92400e; }
.pw-toast.is-info .pw-toast-icon { background: #eff6ff; color: #1e40af; }
.pw-toast-msg { flex: 1; line-height: 1.4; }
.pw-toast.is-leaving { animation: pw-slide-down 0.3s var(--pw-ease) forwards; }
@keyframes pw-slide-up {
    from { opacity: 0; transform: translateY(20px) scale(0.96); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes pw-slide-down {
    to { opacity: 0; transform: translateY(20px) scale(0.96); }
}

/* ===== RTL field alignment ===== */
[dir="rtl"] .pw-card-foot .pw-btn svg { transform: scaleX(-1); }
[dir="rtl"] .pw-card-status { direction: rtl; }

/* ===== Responsive ===== */
@media (max-width: 1100px) {
    .pw-main { grid-template-columns: 1fr; }
    .pw-side { position: static; }
}
@media (max-width: 960px) {
    .pw-grid { grid-template-columns: 1fr; }
    .pw-rail { position: static; max-height: none; }
    .pw-mode-grid { grid-template-columns: repeat(2, 1fr); }
    .pw-row, .pw-row-3, .pw-row-4 { grid-template-columns: 1fr; }
    .pw-check-grid { grid-template-columns: 1fr; }
    .pw-bg-orb-1, .pw-bg-orb-2 { display: none; }
}
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}
</style>

<script>
(function() {
    'use strict';
    if (window.ParsYarWizardV3) return;
    const cfg = window.ParsYarWizard || {};
    const $ = (s, p) => (p || document).querySelector(s);
    const $$ = (s, p) => Array.from((p || document).querySelectorAll(s));

    // ===== Toast system =====
    function toast(msg, type = 'info', duration = 3500) {
        const stack = $('#pw-toast-stack');
        if (!stack) return;
        const el = document.createElement('div');
        el.className = 'pw-toast is-' + type;
        const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : type === 'warning' ? '!' : 'i';
        el.innerHTML = '<span class="pw-toast-icon">' + icon + '</span><span class="pw-toast-msg"></span>';
        el.querySelector('.pw-toast-msg').textContent = msg;
        stack.appendChild(el);
        setTimeout(() => {
            el.classList.add('is-leaving');
            setTimeout(() => el.remove(), 300);
        }, duration);
    }

    function setCardStatus(state, text) {
        const el = $('#pw-card-status');
        if (!el) return;
        el.dataset.state = state;
        el.querySelector('.pw-card-status-text').textContent = text;
    }

    // ===== Step navigation =====
    function collectStepData() {
        const form = $('#pw-step-body');
        if (!form) return {};
        const data = {};
        $$('input, select, textarea', form).forEach(el => {
            if (!el.name) return;
            if (el.type === 'checkbox') {
                data[el.name] = el.checked;
            } else if (el.type === 'radio') {
                if (el.checked) data[el.name] = el.value;
            } else {
                data[el.name] = el.value;
            }
        });
        return data;
    }

    async function postStep(action) {
        const url = cfg.ajaxUrl || '/wp-admin/admin-ajax.php';
        const nonce = cfg.nonce || '';
        const step = parseInt($('.parsyar-wizard').dataset.step, 10) || 1;
        const data = collectStepData();
        const fd = new FormData();
        fd.append('action', cfg.action || 'parsyar_wizard');
        fd.append('nonce', nonce);
        fd.append('step', step);
        fd.append('step_action', action);
        fd.append('data', JSON.stringify(data));
        const nextBtn = $('#pw-next-btn');
        if (nextBtn) {
            nextBtn.disabled = true;
            const orig = nextBtn.innerHTML;
            nextBtn.innerHTML = '<span class="spinner"></span><span>در حال ذخیره...</span>';
            nextBtn.dataset.orig = orig;
        }
        setCardStatus('saving', 'در حال ذخیره...');
        try {
            const res = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
            const json = await res.json();
            if (!res.ok || !json.success) {
                throw new Error((json.data && json.data.message) || ('HTTP ' + res.status));
            }
            setCardStatus('idle', 'ذخیره شد');
            toast(action === 'skip' ? 'این مرحله رد شد' : 'ذخیره شد', 'success', 2000);
            return json.data || {};
        } catch (e) {
            setCardStatus('error', 'خطا');
            toast('خطا: ' + (e.message || 'unknown'), 'error', 5000);
            throw e;
        } finally {
            if (nextBtn) {
                nextBtn.disabled = false;
                if (nextBtn.dataset.orig) nextBtn.innerHTML = nextBtn.dataset.orig;
            }
        }
    }

    function gotoStep(step, applyIfLast) {
        const url = new URL(window.location.href);
        url.searchParams.set('step', String(step));
        if (applyIfLast) url.searchParams.set('apply', '1');
        window.location.href = url.toString();
    }

    // ===== Wire up =====
    document.addEventListener('DOMContentLoaded', function() {
        // Nav buttons
        $$('[data-pw-nav]').forEach(btn => {
            btn.addEventListener('click', async function() {
                const nav = this.dataset.pwNav;
                const step = parseInt($('.parsyar-wizard').dataset.step, 10) || 1;
                const total = 23;
                if (nav === 'prev') {
                    gotoStep(Math.max(1, step - 1));
                    return;
                }
                if (nav === 'skip') {
                    try { await postStep('skip'); gotoStep(step + 1); } catch (e) {}
                    return;
                }
                if (nav === 'next') {
                    try {
                        const r = await postStep('next');
                        if (step >= total) {
                            // last step — apply
                            await applyAll();
                        } else {
                            gotoStep(step + 1);
                        }
                    } catch (e) {}
                    return;
                }
            });
        });

        // Step clicks
        $$('.pw-step').forEach(el => {
            el.addEventListener('click', function() {
                const s = parseInt(this.dataset.step, 10);
                if (s > 0) gotoStep(s);
            });
        });

        // Export
        const ex = $('#pw-export');
        if (ex) ex.addEventListener('click', function() {
            const url = (cfg.ajaxUrl || '/wp-admin/admin-ajax.php') + '?action=' + (cfg.action || 'parsyar_wizard') + '_export&nonce=' + (cfg.nonce || '');
            // Fallback: use POST with action
            const fd = new FormData();
            fd.append('action', cfg.action || 'parsyar_wizard');
            fd.append('nonce', cfg.nonce || '');
            // Simpler: trigger download via form
            const f = document.createElement('form');
            f.method = 'POST';
            f.action = cfg.ajaxUrl;
            f.target = '_blank';
            const a = document.createElement('input');
            a.name = 'action'; a.value = (cfg.action || 'parsyar_wizard') + '_export';
            f.appendChild(a);
            const n = document.createElement('input');
            n.name = 'nonce'; n.value = cfg.nonce || '';
            f.appendChild(n);
            // admin-post expects admin-post.php not admin-ajax for non-AJAX
            // use admin-post.php?action=...
            f.action = (cfg.ajaxUrl || '').replace('admin-ajax.php', 'admin-post.php');
            document.body.appendChild(f);
            f.submit();
            setTimeout(() => f.remove(), 100);
            toast('در حال برون‌بری...', 'info', 2000);
        });

        // Import
        const imp = $('#pw-import-file');
        if (imp) imp.addEventListener('change', async function() {
            if (!this.files || !this.files[0]) return;
            const fd = new FormData();
            fd.append('action', cfg.action || 'parsyar_wizard');
            fd.append('nonce', cfg.nonce || '');
            fd.append('file', this.files[0]);
            try {
                setCardStatus('saving', 'در حال ورود...');
                const res = await fetch(cfg.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
                const json = await res.json();
                if (json.success) {
                    toast('پیکربندی وارد شد', 'success');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    throw new Error((json.data && json.data.message) || 'خطا');
                }
            } catch (e) {
                toast('خطا: ' + e.message, 'error', 5000);
                setCardStatus('error', 'خطا');
            }
        });

        // Reset
        const re = $('#pw-restart');
        if (re) re.addEventListener('click', async function() {
            if (!confirm((cfg.i18n && cfg.i18n.confirmReset) || 'پاک کردن همهٔ پیکربندی و شروع از نو؟')) return;
            const fd = new FormData();
            fd.append('action', cfg.action || 'parsyar_wizard');
            fd.append('nonce', cfg.nonce || '');
            fd.append('mode', 'reset');
            try {
                const res = await fetch(cfg.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
                const json = await res.json();
                if (json.success) {
                    toast('پیکربندی پاک شد', 'success');
                    setTimeout(() => window.location.reload(), 500);
                }
            } catch (e) {
                toast('خطا: ' + e.message, 'error');
            }
        });

        // Chip groups (single-select)
        $$('[data-pw-chip-group]').forEach(group => {
            const name = group.dataset.pwChipGroup;
            $$('.pw-chip', group).forEach(chip => {
                chip.addEventListener('click', function() {
                    $$('.pw-chip', group).forEach(c => c.classList.remove('is-on'));
                    this.classList.add('is-on');
                    const v = this.dataset.value;
                    let inp = group.querySelector('input[type="hidden"]');
                    if (!inp) {
                        inp = document.createElement('input');
                        inp.type = 'hidden';
                        inp.name = name;
                        group.appendChild(inp);
                    }
                    inp.value = v;
                });
            });
        });

        // Mode grid (5 mode selection)
        $$('[data-pw-mode-grid]').forEach(grid => {
            const name = grid.dataset.pwModeGrid;
            $$('.pw-mode', grid).forEach(mode => {
                mode.addEventListener('click', function() {
                    $$('.pw-mode', grid).forEach(m => m.classList.remove('is-on'));
                    this.classList.add('is-on');
                    const v = this.dataset.value;
                    let inp = grid.querySelector('input[type="hidden"]');
                    if (!inp) {
                        inp = document.createElement('input');
                        inp.type = 'hidden';
                        inp.name = name;
                        grid.appendChild(inp);
                    }
                    inp.value = v;
                });
            });
        });
    });

    async function applyAll() {
        const url = cfg.ajaxUrl || '/wp-admin/admin-ajax.php';
        const fd = new FormData();
        fd.append('action', 'parsyar_wizard_apply');
        fd.append('nonce', cfg.nonce || '');
        try {
            setCardStatus('saving', 'در حال اعمال...');
            const res = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
            const json = await res.json();
            if (json.success) {
                toast('نصب با موفقیت تکمیل شد ✓', 'success', 5000);
                setTimeout(() => {
                    if (json.data && json.data.redirect) window.location.href = json.data.redirect;
                }, 800);
            } else {
                throw new Error((json.data && json.data.message) || 'خطا');
            }
        } catch (e) {
            toast('خطا: ' + e.message, 'error', 5000);
            setCardStatus('error', 'خطا');
        }
    }

    window.ParsYarWizardV3 = { toast, setCardStatus, collectStepData, applyAll };
})();
</script>
