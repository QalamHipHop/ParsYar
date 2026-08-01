<?php
/**
 * Wizard Step 1 — خوش‌آمدگویی و بررسی سیستم (v3.0).
 *
 * @var array $state
 * @var array $system
 * @var array $summary
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

use Enterprise\Admin\WizardState;

$mode = $state['mode'] ?? 'micro';
$modes = [
    'solo'       => ['Solo',       'فریلنسرها و فروشندگان',           'تک‌کاربره',     ''],
    'micro'      => ['Micro',      'کمتر از ۱۰ کارمند، یک شعبه',     'تک‌مستاجر',     ''],
    'smb'        => ['SMB',        '۱۰ تا ۱۰۰ کارمند',                'چندشعبه',       ''],
    'enterprise' => ['Enterprise', 'بیش از ۱۰۰ کارمند، چند منطقه',    'چندمستاجر',     ''],
    'holding'    => ['Holding',    'کنسرسیوم، چند شخصیت حقوقی',      'چندشرکت',       ''],
];
$summary = $summary ?? ['ok' => 0, 'fail' => 0, 'warn' => 0];
$system  = $system ?? [];
?>
<div class="pw-banner info">
    <strong>به ParsYar خوش آمدید.</strong> این ویزارد سیستم شما را بررسی و یک پیکربندی پایه می‌سازد. هر مرحله را می‌توانید <em>رد</em> کنید و بعداً از تنظیمات ادامه دهید. کل پیکربندی را می‌توان به JSON برون‌بری یا درون‌ریزی کرد.
</div>

<h3 style="font-size:15px; font-weight:800; margin:18px 0 10px;">انتخاب حالت استقرار</h3>
<p class="desc" style="font-size:12px; color:var(--pw-fg-soft); margin:0 0 14px;">بر اساس نیاز خود یکی از حالتهای زیر را انتخاب کنید. ویزارد بر این اساس ماژول‌ها و پیش‌فرض‌ها را تنظیم می‌کند. قابل تغییر در تنظیمات.</p>
<div class="pw-mode-grid" id="pw-mode-grid" data-pw-mode-grid="mode">
    <?php foreach ($modes as $k => [$title, $desc, $tag, $icon]): ?>
        <div class="pw-mode <?php echo $k === $mode ? 'is-on' : ''; ?>" data-value="<?php echo esc_attr($k); ?>" data-mode="<?php echo esc_attr($k); ?>" role="button" tabindex="0">
            <span class="pw-mode-icon"><?php echo esc_html($icon); ?></span>
            <h3><?php echo esc_html($title); ?></h3>
            <p><?php echo esc_html($desc); ?></p>
            <p style="margin-top:6px;"><b style="font-weight:700;"><?php echo esc_html($tag); ?></b></p>
        </div>
    <?php endforeach; ?>
</div>

<hr class="pw-divider">

<h3 style="font-size:15px; font-weight:800; margin:0 0 12px;">بررسی سلامت سیستم</h3>
<?php
$ok = 0; $fail = 0;
foreach ($system as $c) { !empty($c['ok']) ? $ok++ : $fail++; }
$total = count($system);
$summary = ['ok' => $ok, 'fail' => $fail, 'total' => $total];
?>
<div class="pw-system-overview">
    <div class="pw-system-stat pw-system-stat-ok">
        <span class="pw-system-stat-num"><?php echo parsyar_persian_digits((string) $ok); ?></span>
        <span class="pw-system-stat-label">تأیید شده</span>
    </div>
    <div class="pw-system-stat pw-system-stat-fail">
        <span class="pw-system-stat-num"><?php echo parsyar_persian_digits((string) $fail); ?></span>
        <span class="pw-system-stat-label">نیاز به توجه</span>
    </div>
    <div class="pw-system-stat pw-system-stat-total">
        <span class="pw-system-stat-num"><?php echo parsyar_persian_digits((string) $total); ?></span>
        <span class="pw-system-stat-label">مجموع</span>
    </div>
</div>

<div class="pw-check-grid" style="margin-top:14px;">
    <?php foreach ($system as $key => $check): ?>
        <div class="pw-check <?php echo !empty($check['ok']) ? 'ok' : 'fail'; ?>">
            <span class="pw-check-icon"><?php echo !empty($check['ok']) ? 'v' : '!'; ?></span>
            <div class="pw-check-meta">
                <b><?php echo esc_html($check['label'] ?? $key); ?></b>
                <span><?php echo esc_html($check['value'] ?? ''); ?></span>
                <?php if (!empty($check['help'])): ?>
                    <div style="margin-top:4px; font-size:11px; color:#92400e;"><?php echo esc_html($check['help']); ?></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<style>
.pw-system-overview {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}
.pw-system-stat {
    padding: 14px;
    border-radius: 10px;
    border: 2px solid;
    text-align: center;
    transition: transform 0.15s;
}
.pw-system-stat:hover { transform: translateY(-2px); }
.pw-system-stat-ok { background: #f0fdf4; border-color: #86efac; }
.pw-system-stat-fail { background: #fef2f2; border-color: #fca5a5; }
.pw-system-stat-total { background: #eff6ff; border-color: #93c5fd; }
.pw-system-stat-num {
    display: block;
    font-size: 28px;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 4px;
}
.pw-system-stat-ok .pw-system-stat-num { color: #047857; }
.pw-system-stat-fail .pw-system-stat-num { color: #b91c1c; }
.pw-system-stat-total .pw-system-stat-num { color: #1e40af; }
.pw-system-stat-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--pw-fg-soft);
}
</style>
