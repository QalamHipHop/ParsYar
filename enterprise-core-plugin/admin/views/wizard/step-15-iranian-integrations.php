<?php
/**
 * Wizard Step 15 — Iranian integrations.
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$intg = $state['integrations'] ?? [];
$items = [
    'moodian'   => ['سامانهٔ مؤدیان مالیاتی (tax.gov.ir)', 'ارسال الکترونیکی فاکتور + امضای دیجیتال'],
    'shaparak'  => ['شاپرک', 'گزارش تراکنش‌های درگاه'],
    'post'      => ['پست ایران', 'پیگیری مرسوله، محاسبهٔ کرایه'],
    'jibit'     => ['جیبیت', 'استعلام IBAN، کارت، شبا، چک'],
    'finnotech' => ['فینوتک', 'استعلام‌های بانکی، اعتبارسنجی'],
    'neshan'    => ['نشان', 'نقشه و موقعیت‌یابی (API)'],
    'mapir'     => ['مپ،' . '"Map.ir', 'نقشه و مسیریابی'],
];
?>
<div class="pw-banner info">
    یکپارچگی‌های بومی ایران. سامانهٔ مؤدیان برای صدور فاکتور الکترونیکی مطابق مادهٔ ۱۲/۱۳ قانون پایانه‌های فروشگاهی الزامی است.
</div>

<div style="display:grid; gap:10px;">
<?php foreach ($items as $k => [$title, $desc]): $on = !empty($intg[$k]['enabled']); ?>
    <details style="border:1px solid #e5e5e5; border-radius:12px; padding:14px;" <?php echo $on ? 'open' : ''; ?>>
        <summary style="display:flex; align-items:center; gap:10px; cursor:pointer; list-style:none;">
            <strong style="flex:1;"><?php echo esc_html($title); ?></strong>
            <span class="desc" style="font-size:12px; color:#6b7280;"><?php echo esc_html($desc); ?></span>
            <span class="pw-chip <?php echo $on ? 'is-on' : ''; ?>" data-toggle="integrations[<?php echo esc_attr($k); ?>][enabled]"><?php echo $on ? 'فعال' : 'غیرفعال'; ?></span>
        </summary>
        <div class="pw-row" style="margin-top:12px;">
            <?php
            $fields = match ($k) {
                'moodian'   => ['tax_username' => 'نام‌کاربری مؤدی', 'tax_password' => 'گذرواژه', 'memory_id' => 'شناسهٔ حافظهٔ مالیاتی', 'private_key_path' => 'مسیر کلید خصوصی (.pem)'],
                'shaparak'  => ['terminal_id' => 'شناسهٔ ترمینال', 'merchant_id' => 'کد پذیرنده'],
                'post'      => ['username' => 'نام‌کاربری ادارهٔ پست'],
                'jibit'     => ['api_key' => 'کلید API', 'secret' => 'کلید محرمانه'],
                'finnotech' => ['client_id' => 'Client ID', 'client_secret' => 'Client Secret'],
                'neshan'    => ['api_key' => 'کلید API'],
                'mapir'     => ['api_key' => 'کلید API'],
                default     => [],
            };
            foreach ($fields as $fk => $fl): ?>
                <div class="pw-field">
                    <label><?php echo esc_html($fl); ?></label>
                    <input type="<?php echo str_contains($fk, 'password') || str_contains($fk, 'secret') ? 'password' : 'text'; ?>"
                           name="integrations[<?php echo esc_attr($k); ?>][<?php echo esc_attr($fk); ?>]"
                           value="<?php echo esc_attr($intg[$k][$fk] ?? ''); ?>" dir="ltr" autocomplete="off">
                </div>
            <?php endforeach; ?>
        </div>
    </details>
<?php endforeach; ?>
</div>
