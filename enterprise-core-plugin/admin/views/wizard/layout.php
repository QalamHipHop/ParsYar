<?php
/**
 * Setup Wizard layout shell.
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

$state    = $state ?? WizardState::load();
$current  = (int) ($current ?? ($state['current_step'] ?? 1));
$progress = $progress ?? WizardState::progress($state);
$system   = $system ?? [];
$body     = $body ?? '';
?>
<div class="wrap parsyar-wizard" dir="rtl">
    <div class="pw-header">
        <div class="pw-brand">
            <span class="pw-logo">P</span>
            <div>
                <h1>ویزارد نصب ParsYar</h1>
                <p class="pw-sub">راه‌اندازی گام‌به‌گام پلتفرم سازمانی — قابل ازسرگیری، قابل صدور/ورود</p>
            </div>
        </div>
        <div class="pw-actions">
            <button type="button" class="button" id="pw-export">برون‌بری پیکربندی</button>
            <label class="button" for="pw-import-file">درون‌ریزی پیکربندی</label>
            <input type="file" id="pw-import-file" accept="application/json" hidden>
            <button type="button" class="button" id="pw-restart">شروع از نو</button>
        </div>
    </div>

    <div class="pw-progress">
        <div class="pw-progress-bar"><span style="width: <?php echo (int) $progress['percent']; ?>%"></span></div>
        <div class="pw-progress-meta">
            <span><?php echo (int) $progress['done']; ?> از <?php echo (int) $progress['total']; ?> تکمیل‌شده</span>
            <?php if ($progress['skipped']): ?>
                <span>· <?php echo (int) $progress['skipped']; ?> رد شده</span>
            <?php endif; ?>
            <span>· <?php echo (int) $progress['percent']; ?>٪</span>
        </div>
    </div>

    <div class="pw-grid">
        <aside class="pw-rail">
            <ol class="pw-steps">
                <?php for ($i = 1; $i <= WizardState::STEPS; $i++):
                    $cls = 'pw-step';
                    if ($i === $current) {
                        $cls .= ' is-current';
                    } elseif (WizardState::isCompleted($i, $state)) {
                        $cls .= ' is-done';
                    } elseif (WizardState::isSkipped($i, $state)) {
                        $cls .= ' is-skip';
                    }
                ?>
                    <li class="<?php echo esc_attr($cls); ?>" data-step="<?php echo (int) $i; ?>">
                        <span class="pw-step-num"><?php echo (int) $i; ?></span>
                        <span class="pw-step-label"><?php echo esc_html(WizardState::STEP_LABELS[$i] ?? ''); ?></span>
                        <span class="pw-step-state"></span>
                    </li>
                <?php endfor; ?>
            </ol>
        </aside>

        <main class="pw-main">
            <div class="pw-card">
                <div class="pw-card-head">
                    <span class="pw-step-tag">گام <?php echo (int) $current; ?> از <?php echo (int) WizardState::STEPS; ?></span>
                    <h2><?php echo esc_html(WizardState::STEP_LABELS[$current] ?? ''); ?></h2>
                </div>
                <div class="pw-card-body" id="pw-step-body">
                    <?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <div class="pw-card-foot">
                    <?php if ($current > 1): ?>
                        <button type="button" class="button" data-pw-nav="prev">قبلی</button>
                    <?php endif; ?>
                    <button type="button" class="button" data-pw-nav="skip">رد کردن</button>
                    <button type="button" class="button button-primary" data-pw-nav="next">
                        <?php echo $current === WizardState::STEPS ? 'پایان' : 'بعدی'; ?>
                    </button>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
.parsyar-wizard { max-width: 1280px; margin: 16px auto; }
.pw-header { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:14px 18px; background:#fff; border:1px solid #e5e5e5; border-radius:14px; }
.pw-brand { display:flex; gap:12px; align-items:center; }
.pw-logo { width:42px; height:42px; background:#0a0a0a; color:#fff; display:grid; place-items:center; font-weight:800; border-radius:10px; }
.pw-header h1 { margin:0; font-size:18px; }
.pw-sub { margin:2px 0 0; color:#6b7280; font-size:12px; }
.pw-actions { display:flex; gap:8px; align-items:center; }
.pw-actions .button { cursor:pointer; }
.pw-progress { margin:14px 0; padding:12px 18px; background:#fff; border:1px solid #e5e5e5; border-radius:14px; }
.pw-progress-bar { height:6px; background:#f1f1f1; border-radius:6px; overflow:hidden; }
.pw-progress-bar span { display:block; height:100%; background:#0a0a0a; transition:width .3s ease; }
.pw-progress-meta { display:flex; gap:8px; font-size:12px; color:#6b7280; margin-top:6px; }
.pw-grid { display:grid; grid-template-columns: 280px 1fr; gap:16px; }
.pw-rail { background:#fff; border:1px solid #e5e5e5; border-radius:14px; padding:12px; position:sticky; top:32px; height:fit-content; }
.pw-steps { list-style:none; margin:0; padding:0; counter-reset: step; }
.pw-step { display:flex; gap:10px; align-items:center; padding:8px 10px; border-radius:8px; cursor:pointer; font-size:13px; }
.pw-step:hover { background:#fafafa; }
.pw-step.is-current { background:#0a0a0a; color:#fff; }
.pw-step.is-current .pw-step-num { background:#fff; color:#0a0a0a; }
.pw-step.is-done .pw-step-state::before { content: "✓"; color:#0a0a0a; }
.pw-step.is-skip .pw-step-state::before { content: "–"; color:#9ca3af; }
.pw-step-num { width:24px; height:24px; display:grid; place-items:center; background:#f1f1f1; border-radius:6px; font-size:12px; font-weight:600; }
.pw-step-label { flex:1; }
.pw-step-state { font-weight:700; }
.pw-main .pw-card { background:#fff; border:1px solid #e5e5e5; border-radius:14px; overflow:hidden; }
.pw-card-head { padding:18px 22px; border-bottom:1px solid #e5e5e5; }
.pw-card-head h2 { margin:6px 0 0; font-size:18px; }
.pw-step-tag { font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; }
.pw-card-body { padding:22px; min-height:340px; }
.pw-card-foot { padding:14px 22px; border-top:1px solid #e5e5e5; display:flex; gap:8px; justify-content:flex-end; }
.pw-card-foot .button { padding:6px 16px; height:auto; }
.pw-field { display:flex; flex-direction:column; gap:6px; margin-bottom:14px; }
.pw-field label { font-weight:600; font-size:13px; }
.pw-field .desc { font-size:12px; color:#6b7280; }
.pw-row { display:grid; grid-template-columns: repeat(2, 1fr); gap:14px; }
.pw-row-3 { display:grid; grid-template-columns: repeat(3, 1fr); gap:14px; }
.pw-actions-row { display:flex; gap:8px; flex-wrap:wrap; }
.pw-chip { display:inline-flex; gap:6px; align-items:center; padding:6px 10px; border:1px solid #e5e5e5; border-radius:999px; cursor:pointer; font-size:12px; user-select:none; }
.pw-chip.is-on { background:#0a0a0a; color:#fff; border-color:#0a0a0a; }
.pw-divider { border:0; border-top:1px solid #e5e5e5; margin:18px 0; }
.pw-banner { padding:12px 14px; border-radius:10px; font-size:13px; }
.pw-banner.info { background:#f4f4f4; color:#1f1f1f; border:1px solid #e5e5e5; }
.pw-banner.success { background:#0a0a0a; color:#fff; }
.pw-banner.warning { background:#fffbe6; border:1px solid #fde58a; color:#7a5b00; }
.pw-banner.danger { background:#fff0f0; border:1px solid #ffc1c1; color:#7a0019; }
.pw-check-grid { display:grid; grid-template-columns: repeat(2, 1fr); gap:8px; }
.pw-check { display:flex; gap:10px; align-items:flex-start; padding:10px; border:1px solid #e5e5e5; border-radius:10px; }
.pw-check.ok { border-color:#cfd8c1; background:#f9fbf6; }
.pw-check.fail { border-color:#ffc1c1; background:#fff7f7; }
.pw-check-icon { font-weight:800; }
.pw-check-meta { flex:1; }
.pw-check-meta b { display:block; font-size:13px; }
.pw-check-meta span { font-size:12px; color:#6b7280; }
.pw-mode-grid { display:grid; grid-template-columns: repeat(5, 1fr); gap:10px; }
.pw-mode { border:1px solid #e5e5e5; border-radius:12px; padding:14px; cursor:pointer; text-align:center; }
.pw-mode.is-on { border-color:#0a0a0a; background:#0a0a0a; color:#fff; }
.pw-mode h3 { margin:0 0 4px; font-size:14px; }
.pw-mode p { margin:0; font-size:11px; color:inherit; opacity:.7; }
@media (max-width: 960px) { .pw-grid { grid-template-columns: 1fr; } .pw-rail { position:static; } .pw-row, .pw-row-3 { grid-template-columns: 1fr; } .pw-mode-grid { grid-template-columns: repeat(2, 1fr); } .pw-check-grid { grid-template-columns: 1fr; } }
</style>
