<?php
/**
 * Wizard Step 7 — Fiscal year.
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$types = [
    'iranian'   => 'سال مالی ایرانی (از ۱ فروردین ~ ۲۱ مارس)',
    'gregorian' => 'سال مالی میلادی (از ۱ ژانویه)',
    'custom'    => 'سفارشی (تاریخ دلخواه)',
];
?>
<div class="pw-banner info">
    سال مالی شروع و پایان دوره‌های حسابداری و گزارش‌های مالیاتی را مشخص می‌کند. در ایران معمولاً ۱ فروردین تا پایان اسفند است.
</div>

<div class="pw-row">
    <div class="pw-field">
        <label for="pw-fiscal-type">نوع سال مالی</label>
        <select id="pw-fiscal-type" name="fiscal_type">
            <?php foreach ($types as $k => $v): ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($state['fiscal_type'] ?? 'iranian', $k); ?>><?php echo esc_html($v); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="pw-field" id="pw-fiscal-start-wrap">
        <label for="pw-fiscal-start">روز شروع (MM-DD)</label>
        <input type="text" id="pw-fiscal-start" name="fiscal_start_md" value="<?php echo esc_attr($state['fiscal_start_md'] ?? '03-21'); ?>" placeholder="03-21" pattern="\d{2}-\d{2}">
    </div>
</div>

<div class="pw-field">
    <label for="pw-fiscal-label">برچسب سال مالی</label>
    <input type="text" id="pw-fiscal-label" name="fiscal_label" value="<?php echo esc_attr($state['fiscal_label'] ?? 'سال مالی'); ?>">
    <span class="desc">مثلاً «سال مالی ۱۴۰۳» یا «FY2024»</span>
</div>
