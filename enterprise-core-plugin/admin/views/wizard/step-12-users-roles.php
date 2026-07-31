<?php
/**
 * Wizard Step 12 — Users & roles.
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$roles = [
    'super_admin'  => ['سوپرادمین', 'دسترسی کامل، تنظیمات سیستم، مدیریت نقش‌ها'],
    'admin'        => ['ادمین', 'مدیریت روزمره، بدون تغییر تنظیمات حساس'],
    'sales_manager'=> ['مدیر فروش', 'مدیریت تیم فروش، گزارش‌ها، پیش‌بینی'],
    'sales_rep'    => ['کارشناس فروش', 'سرنخ‌ها و معاملات خودش'],
    'support'      => ['پشتیبان', 'تیکت‌ها، چت، KB'],
    'marketing'    => ['بازاریاب', 'کمپین، سگمنت، اتوماسیون بازاریابی'],
    'hr'           => ['منابع انسانی', 'کارمند، حقوق، حضور'],
    'accountant'   => ['حسابدار', 'دفترداری، گزارش‌های مالی، مؤدیان'],
    'readonly'     => ['فقط‌خواندنی', 'مشاهده بدون تغییر'],
];
?>
<div class="pw-banner info">
    نقش‌های پیش‌فرض قابل ویرایش هستند. در آینده می‌توانید نقش سفارشی با capabilityهای دلخواه تعریف کنید.
</div>

<h4>نقش‌های فعال</h4>
<div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:10px;">
<?php foreach ($roles as $k => [$title, $desc]): $on = !empty($state['roles'][$k]); ?>
    <div class="pw-mode <?php echo $on ? 'is-on' : ''; ?>" data-role="<?php echo esc_attr($k); ?>" style="text-align:right;">
        <h3><?php echo esc_html($title); ?></h3>
        <p><?php echo esc_html($desc); ?></p>
    </div>
<?php endforeach; ?>
</div>

<hr class="pw-divider">

<h4>انتخاب مدیر اصلی</h4>
<p class="desc">کاربر فعلی به‌عنوان مدیر اصلی سیستم انتخاب می‌شود: <strong><?php echo esc_html(wp_get_current_user()->display_name ?? '—'); ?></strong></p>
<input type="hidden" name="admin_user_id" value="<?php echo (int) get_current_user_id(); ?>">

<h4>نقش‌های وردپرس اضافی می‌سازد</h4>
<ul style="font-size:12px; color:#6b7280;">
    <li><code>manage_enterprise</code> — دسترسی به داشبورد Enterprise</li>
    <li><code>edit_enterprise_records</code> — ایجاد/ویرایش رکوردها</li>
    <li><code>manage_enterprise_hr</code> — دسترسی به بخش منابع انسانی</li>
    <li><code>manage_enterprise_finance</code> — دسترسی به حسابداری</li>
</ul>
