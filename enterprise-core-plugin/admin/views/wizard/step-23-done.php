<?php
/**
 * Wizard Step 23 — Done.
 *
 * @var array $state
 * @var array $progress
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

use Enterprise\Admin\WizardState;
?>
<div class="pw-banner success">
    <strong>✓ نصب ParsYar کامل شد.</strong>
    <?php echo (int) $progress['done']; ?> مرحله تکمیل، <?php echo (int) $progress['skipped']; ?> مرحله رد شده.
</div>

<h3>خلاصهٔ پیکربندی</h3>
<table class="widefat" style="margin-top:10px;">
    <tbody>
    <tr><th style="width:200px;">حالت استقرار</th><td><?php echo esc_html($state['mode'] ?? '—'); ?></td></tr>
    <tr><th>سازمان</th><td><?php echo esc_html($state['org']['name'] ?? '—'); ?></td></tr>
    <tr><th>زبان</th><td><?php echo esc_html($state['language'] ?? '—'); ?> · <?php echo esc_html($state['timezone'] ?? '—'); ?></td></tr>
    <tr><th>ارز پایه</th><td><?php echo esc_html($state['base_currency'] ?? '—'); ?></td></tr>
    <tr><th>سال مالی</th><td><?php echo esc_html($state['fiscal_type'] ?? '—'); ?></td></tr>
    <tr><th>تقویم شمسی</th><td><?php echo esc_html($state['jalali_mode'] ?? '—'); ?></td></tr>
    <tr><th>ماژول‌های فعال</th><td><?php echo esc_html(implode('، ', array_keys(array_filter($state['modules'] ?? [])))); ?></td></tr>
    <tr><th>پایگاه‌داده</th><td><?php echo esc_html(implode('، ', $state['currencies'] ?? [])); ?></td></tr>
    </tbody>
</table>

<h3 style="margin-top:18px;">قدم‌های بعدی</h3>
<ol style="line-height:2;">
    <li><a href="<?php echo esc_url(admin_url('admin.php?page=enterprise')); ?>">داشبورد Enterprise</a> — مشاهدهٔ آمار لحظه‌ای</li>
    <li><a href="<?php echo esc_url(admin_url('admin.php?page=enterprise-setup')); ?>">ویزارد نصب</a> — در هر زمان قابل بازگشت</li>
    <li><a href="<?php echo esc_url(admin_url('admin.php?page=enterprise-objects')); ?>">اشیاء سفارشی</a> — تعریف entityهای جدید</li>
    <li><a href="<?php echo esc_url(home_url('/enterprise')); ?>">پنل کاربری (React SPA)</a> — رابط اصلی برای کاربران</li>
    <li>مستندات: <a href="https://github.com/QalamHipHop/ParsYar" target="_blank" rel="noopener">github.com/QalamHipHop/ParsYar</a></li>
</ol>

<p class="pw-banner info" style="margin-top:18px;">
    💡 برای اعمال تغییرات، دکمهٔ «پایان» را بزنید. جداول، حساب‌های پیش‌فرض، و در صورت انتخاب، دادهٔ دمو ایجاد می‌شود.
</p>
