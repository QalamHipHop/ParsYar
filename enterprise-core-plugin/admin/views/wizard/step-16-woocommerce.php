<?php
/**
 * Wizard Step 16 — WooCommerce sync.
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$wc = $state['woocommerce'] ?? [];
$wcActive = class_exists('WooCommerce');
?>
<div class="pw-banner <?php echo $wcActive ? 'success' : 'warning'; ?>">
    <?php if ($wcActive): ?>
        v افزونهٔ WooCommerce شناسایی شد. می‌توانید همگام‌سازی دوطرفه فعال کنید.
    <?php else: ?>
         افزونهٔ WooCommerce نصب نیست. این مرحله اختیاری است؛ در صورت نصب بعدی، از تنظیمات فعال کنید.
    <?php endif; ?>
</div>

<div class="pw-field">
    <label>همگام‌سازی با ووکامرس</label>
    <label class="pw-chip <?php echo !empty($wc['sync_enabled']) ? 'is-on' : ''; ?>" data-toggle="woocommerce[sync_enabled]"><?php echo !empty($wc['sync_enabled']) ? 'فعال' : 'غیرفعال'; ?></label>
</div>

<div class="pw-row">
    <div class="pw-field">
        <label>همگام‌سازی محصولات</label>
        <label class="pw-chip <?php echo !empty($wc['sync_products']) ? 'is-on' : ''; ?>" data-toggle="woocommerce[sync_products]"><?php echo !empty($wc['sync_products']) ? 'بله' : 'خیر'; ?></label>
    </div>
    <div class="pw-field">
        <label>همگام‌سازی سفارش‌ها</label>
        <label class="pw-chip <?php echo !empty($wc['sync_orders']) ? 'is-on' : ''; ?>" data-toggle="woocommerce[sync_orders]"><?php echo !empty($wc['sync_orders']) ? 'بله' : 'خیر'; ?></label>
    </div>
    <div class="pw-field">
        <label>همگام‌سازی مشتریان</label>
        <label class="pw-chip <?php echo !empty($wc['sync_customers']) ? 'is-on' : ''; ?>" data-toggle="woocommerce[sync_customers]"><?php echo !empty($wc['sync_customers']) ? 'بله' : 'خیر'; ?></label>
    </div>
</div>
