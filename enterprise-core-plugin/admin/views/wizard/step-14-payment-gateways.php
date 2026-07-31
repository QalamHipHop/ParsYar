<?php
/**
 * Wizard Step 14 — Payment gateways.
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$g = $state['payment_gateways'] ?? [];
$gateways = [
    'zarinpal'    => ['زرین‌پال',          'merchant_id',  'کد پذیرنده'],
    'idpay'       => ['آیدی‌پی',           'api_key',      'کلید API'],
    'nextpay'     => ['نکست‌پی',          'api_key',      'کلید API'],
    'saman'       => ['سامان',             'merchant_id',  'کد پذیرنده'],
    'pasargad'    => ['پاسارگاد',          'terminal_id',  'شناسهٔ ترمینال'],
    'mellat'      => ['ملت',               'terminal_id',  'شناسهٔ ترمینال + نام‌کاربری/گذرواژه'],
    'saderat'     => ['صادرات',            'terminal_id',  'شناسهٔ ترمینال'],
    'asanpardakht'=> ['آسان‌پرداخت',       'merchant_id',  'کد پذیرنده'],
];
?>
<div class="pw-banner info">
    درگاه‌های پرداخت ایرانی را فعال و تنظیم کنید. هر درگاه کلیدهای خود را دارد؛ توصیه می‌شود فقط درگاه‌هایی که استفاده می‌کنید فعال کنید.
</div>

<div style="display:grid; gap:10px;">
<?php foreach ($gateways as $k => [$title, $field, $fieldLabel]): $on = !empty($g[$k]['enabled']); ?>
    <div style="border:1px solid #e5e5e5; border-radius:12px; padding:14px;">
        <div class="pw-row" style="align-items:center;">
            <div>
                <strong><?php echo esc_html($title); ?></strong>
                <p class="desc" style="margin:2px 0 0;"><?php echo esc_html($fieldLabel); ?></p>
            </div>
            <div>
                <label class="pw-chip <?php echo $on ? 'is-on' : ''; ?>" data-toggle="payment_gateways[<?php echo esc_attr($k); ?>][enabled]"><?php echo $on ? 'فعال' : 'غیرفعال'; ?></label>
            </div>
        </div>
        <div class="pw-field" style="margin-top:10px;">
            <input type="text" name="payment_gateways[<?php echo esc_attr($k); ?>][<?php echo esc_attr($field); ?>]" value="<?php echo esc_attr($g[$k][$field] ?? ''); ?>" dir="ltr" placeholder="<?php echo esc_attr($fieldLabel); ?>" autocomplete="off">
        </div>
        <?php if ($k === 'mellat'): ?>
            <div class="pw-row">
                <div class="pw-field">
                    <label>نام‌کاربری</label>
                    <input type="text" name="payment_gateways[mellat][username]" value="<?php echo esc_attr($g['mellat']['username'] ?? ''); ?>" dir="ltr" autocomplete="off">
                </div>
                <div class="pw-field">
                    <label>گذرواژه</label>
                    <input type="password" name="payment_gateways[mellat][password]" value="<?php echo esc_attr($g['mellat']['password'] ?? ''); ?>" dir="ltr" autocomplete="new-password">
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>
