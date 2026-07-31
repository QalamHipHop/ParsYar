<?php
/**
 * Wizard Step 4 — Multi-company (Holding mode).
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$isHolding = ($state['mode'] ?? '') === 'holding';
$companies = $state['companies'] ?? [];
?>
<div class="pw-banner info">
    فقط در حالت <strong>Holding</strong> فعال است. اگر چند شخصیت حقوقی (شرکت مادر/زیرمجموعه) دارید، اینجا تعریف کنید. هر شرکت دفتر معین و گزارش‌های مالی مستقل دارد.
</div>

<?php if (!$isHolding): ?>
    <div class="pw-banner warning">
        حالت فعلی شما Holding نیست. اگر فعال کنید، در مرحلهٔ ۱ به Holding تغییر دهید.
    </div>
<?php endif; ?>

<div id="pw-companies-list">
    <?php foreach ($companies as $i => $c): ?>
        <div class="pw-row" data-company-index="<?php echo (int) $i; ?>" style="border:1px solid #e5e5e5; border-radius:10px; padding:12px; margin-bottom:10px;">
            <div class="pw-field">
                <label>نام شرکت</label>
                <input type="text" name="companies[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr($c['name'] ?? ''); ?>">
            </div>
            <div class="pw-field">
                <label>شناسهٔ ملی</label>
                <input type="text" name="companies[<?php echo (int) $i; ?>][national_id]" value="<?php echo esc_attr($c['national_id'] ?? ''); ?>" maxlength="11">
            </div>
        </div>
    <?php endforeach; ?>
</div>

<button type="button" class="button" id="pw-add-company">+ افزودن شرکت</button>

<template id="pw-company-tpl">
    <div class="pw-row" style="border:1px solid #e5e5e5; border-radius:10px; padding:12px; margin-bottom:10px;">
        <div class="pw-field">
            <label>نام شرکت</label>
            <input type="text" name="companies[__i__][name]">
        </div>
        <div class="pw-field">
            <label>شناسهٔ ملی</label>
            <input type="text" name="companies[__i__][national_id]" maxlength="11">
        </div>
    </div>
</template>
