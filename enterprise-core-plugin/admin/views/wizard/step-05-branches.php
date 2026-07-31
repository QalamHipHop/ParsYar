<?php
/**
 * Wizard Step 5 — Branches & departments.
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$branches = $state['branches'] ?? [];
?>
<div class="pw-banner info">
    شعب و دپارتمان‌های فیزیکی یا منطقه‌ای. هر شعبه می‌تواند انبار، صندوق، و فاکتور مستقل داشته باشد. شعبه‌ها در گزارش‌های تلفیقی/مستقل قابل تفکیک هستند.
</div>

<div id="pw-branches-list">
    <?php foreach ($branches as $i => $b): ?>
        <div class="pw-row-3" data-branch-index="<?php echo (int) $i; ?>" style="border:1px solid #e5e5e5; border-radius:10px; padding:12px; margin-bottom:10px;">
            <div class="pw-field">
                <label>کد</label>
                <input type="text" name="branches[<?php echo (int) $i; ?>][code]" value="<?php echo esc_attr($b['code'] ?? ''); ?>">
            </div>
            <div class="pw-field">
                <label>نام</label>
                <input type="text" name="branches[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr($b['name'] ?? ''); ?>">
            </div>
            <div class="pw-field">
                <label>شهر</label>
                <input type="text" name="branches[<?php echo (int) $i; ?>][city]" value="<?php echo esc_attr($b['city'] ?? ''); ?>">
            </div>
        </div>
    <?php endforeach; ?>
</div>

<button type="button" class="button" id="pw-add-branch">+ افزودن شعبه</button>
<template id="pw-branch-tpl">
    <div class="pw-row-3" style="border:1px solid #e5e5e5; border-radius:10px; padding:12px; margin-bottom:10px;">
        <div class="pw-field"><label>کد</label><input type="text" name="branches[__i__][code]"></div>
        <div class="pw-field"><label>نام</label><input type="text" name="branches[__i__][name]"></div>
        <div class="pw-field"><label>شهر</label><input type="text" name="branches[__i__][city]"></div>
    </div>
</template>
