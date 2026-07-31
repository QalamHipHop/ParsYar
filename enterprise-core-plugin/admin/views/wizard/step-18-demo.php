<?php
/**
 * Wizard Step 18 — Demo data.
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$d = $state['demo'] ?? [];
?>
<div class="pw-banner info">
    برای آزمایش سیستم می‌توانید دادهٔ نمونه وارد کنید. این داده‌ها در جداول پیش‌فرض درج می‌شوند و با اجرای مجدد ویزارد قابل پاک شدن هستند. برای محیط Production توصیه نمی‌شود.
</div>

<div class="pw-field">
    <label>بارگذاری دادهٔ دمو</label>
    <label class="pw-chip <?php echo !empty($d['enabled']) ? 'is-on' : ''; ?>" data-toggle="demo[enabled]"><?php echo !empty($d['enabled']) ? 'فعال' : 'غیرفعال'; ?></label>
</div>

<div class="pw-row-3">
    <div class="pw-field"><label>سرنخ</label><input type="number" name="demo[leads]" value="<?php echo (int) ($d['leads'] ?? 50); ?>" min="0" max="10000"></div>
    <div class="pw-field"><label>مخاطب</label><input type="number" name="demo[contacts]" value="<?php echo (int) ($d['contacts'] ?? 100); ?>" min="0" max="10000"></div>
    <div class="pw-field"><label>معامله</label><input type="number" name="demo[deals]" value="<?php echo (int) ($d['deals'] ?? 30); ?>" min="0" max="10000"></div>
    <div class="pw-field"><label>محصول</label><input type="number" name="demo[products]" value="<?php echo (int) ($d['products'] ?? 40); ?>" min="0" max="10000"></div>
    <div class="pw-field"><label>فاکتور</label><input type="number" name="demo[invoices]" value="<?php echo (int) ($d['invoices'] ?? 25); ?>" min="0" max="10000"></div>
    <div class="pw-field"><label>کارمند</label><input type="number" name="demo[employees]" value="<?php echo (int) ($d['employees'] ?? 10); ?>" min="0" max="10000"></div>
</div>
