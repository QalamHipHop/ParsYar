<?php
/**
 * Wizard Step 1 — خوش‌آمدگویی و بررسی سیستم.
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
    'solo'       => ['Solo',       'فریلنسرها و فروشندگان',           'تک‌کاربره'],
    'micro'      => ['Micro',      'کمتر از ۱۰ کارمند، یک شعبه',     'تک‌مستاجر'],
    'smb'        => ['SMB',        '۱۰ تا ۱۰۰ کارمند',                'چندشعبه'],
    'enterprise' => ['Enterprise', 'بیش از ۱۰۰ کارمند، چند منطقه',    'چندمستاجر'],
    'holding'    => ['Holding',    'کنسرسیوم، چند شخصیت حقوقی',      'چندشرکت'],
];
?>
<div class="pw-banner info">
    <strong>به ParsYar خوش آمدید.</strong> این ویزارد سیستم شما را بررسی و یک پیکربندی پایه می‌سازد. هر مرحله را می‌توانید <em>رد</em> کنید و بعداً از تنظیمات ادامه دهید. کل پیکربندی را می‌توان به JSON برون‌بری یا درون‌ریزی کرد.
</div>

<h3>انتخاب حالت استقرار</h3>
<p class="desc">بر اساس نیاز خود یکی از حالتهای زیر را انتخاب کنید. ویزارد بر این اساس ماژول‌ها و پیش‌فرض‌ها را تنظیم می‌کند. قابل تغییر در تنظیمات.</p>
<div class="pw-mode-grid" id="pw-mode-grid">
    <?php foreach ($modes as $k => [$title, $desc, $tag]): ?>
        <div class="pw-mode <?php echo $k === $mode ? 'is-on' : ''; ?>" data-mode="<?php echo esc_attr($k); ?>">
            <h3><?php echo esc_html($title); ?></h3>
            <p><?php echo esc_html($desc); ?></p>
            <p><?php echo esc_html($tag); ?></p>
        </div>
    <?php endforeach; ?>
</div>

<hr class="pw-divider">

<h3>بررسی سیستم</h3>
<p class="desc">پیش‌نیازهای زیرساخت قبل از نصب بررسی می‌شوند. در صورت وجود خطا، رفع کنید و صفحه را بازخوانی کنید.</p>

<div class="pw-check-grid">
    <?php foreach ($system as $c): ?>
        <div class="pw-check <?php echo $c['status'] === 'ok' ? 'ok' : 'fail'; ?>">
            <div class="pw-check-icon"><?php echo $c['status'] === 'ok' ? '✓' : '✕'; ?></div>
            <div class="pw-check-meta">
                <b><?php echo esc_html($c['label']); ?></b>
                <span><?php echo esc_html($c['message']); ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="pw-banner <?php echo $summary['ready'] ? 'success' : 'danger'; ?>" style="margin-top:14px;">
    <?php if ($summary['ready']): ?>
        ✓ سیستم آماده است. <?php echo (int) $summary['passed']; ?> مورد قبول، <?php echo (int) $summary['warnings']; ?> هشدار.
    <?php else: ?>
        ✕ <?php echo (int) $summary['failed']; ?> مورد بحرانی باید رفع شود.
    <?php endif; ?>
</div>
