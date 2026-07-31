<?php
/**
 * Wizard Step 11 — Modules toggle.
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$modules = $state['modules'] ?? [];
$moduleLabels = [
    'crm'        => ['CRM', 'سرنخ، مخاطب، معامله، فعالیت، صندوق'],
    'erp'        => ['ERP', 'محصول، انبار، فاکتور، سفارش'],
    'hrm'        => ['HRM', 'کارمند، حقوق، حضور و غیاب'],
    'accounting' => ['حسابداری', 'دفترداری دوطرفه، گزارش‌های مالی'],
    'inbox'      => ['صندوق', 'ایمیل، SMS، پیام‌رسان‌ها'],
    'marketing'  => ['بازاریابی', 'کمپین، سگمنت، سفر مشتری'],
    'automation' => ['اتوماسیون', 'گردش کار، تریگر، اکشن'],
    'reports'    => ['گزارش‌ها', 'داشبورد، KPI، BI'],
    'support'    => ['پشتیبانی', 'تیکت، SLA، CSAT'],
    'projects'   => ['پروژه‌ها', 'تسک، گانت، تایم‌ترک'],
    'documents'  => ['مستندات', 'DMS، eSign، آرشیو'],
];
?>
<div class="pw-banner info">
    هر ماژول را می‌توانید فعال یا غیرفعال کنید. ماژول‌های غیرفعال منو و APIهایشان پنهان می‌شود ولی داده‌ها حذف نمی‌شود.
</div>

<div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:12px;">
<?php foreach ($moduleLabels as $k => [$title, $desc]): $on = !empty($modules[$k]); ?>
    <div class="pw-mode <?php echo $on ? 'is-on' : ''; ?>" data-module="<?php echo esc_attr($k); ?>" style="text-align:right;">
        <h3><?php echo esc_html($title); ?></h3>
        <p><?php echo esc_html($desc); ?></p>
        <p style="margin-top:8px; font-weight:600;"><?php echo $on ? 'فعال' : 'غیرفعال'; ?></p>
    </div>
<?php endforeach; ?>
</div>
