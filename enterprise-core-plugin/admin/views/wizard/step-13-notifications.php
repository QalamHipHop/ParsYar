<?php
/**
 * Wizard Step 13 — Notification channels.
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$n = $state['notifications'] ?? [];
$smsProviders = [
    'kavenegar'     => 'کاوه‌نگار (Kavenegar)',
    'melipayamak'   => 'ملی‌پیامک',
    'ghasedak'      => 'قاصدک',
    'smsir'         => 'SMS.ir',
    'parsian_sms'   => 'پارسیان',
    'rayan_sms'     => 'رایان اس‌ام‌اس',
];
?>
<div class="pw-banner info">
    تنظیم کانال‌های اعلان: ایمیل (SMTP)، پیامک (سرویس‌دهندهٔ ایرانی)، Push Web، اعلان درون‌برنامه‌ای.
</div>

<h4>ایمیل (SMTP)</h4>
<div class="pw-row-3">
    <div class="pw-field">
        <label for="pw-smtp-host">میزبان SMTP</label>
        <input type="text" id="pw-smtp-host" name="notifications[smtp_host]" value="<?php echo esc_attr($n['smtp_host'] ?? ''); ?>" dir="ltr" placeholder="smtp.example.com">
    </div>
    <div class="pw-field">
        <label for="pw-smtp-port">پورت</label>
        <input type="number" id="pw-smtp-port" name="notifications[smtp_port]" value="<?php echo (int) ($n['smtp_port'] ?? 587); ?>" dir="ltr">
    </div>
    <div class="pw-field">
        <label for="pw-smtp-secure">رمزنگاری</label>
        <select id="pw-smtp-secure" name="notifications[smtp_secure]">
            <option value="tls" <?php selected($n['smtp_secure'] ?? 'tls', 'tls'); ?>>TLS</option>
            <option value="ssl" <?php selected($n['smtp_secure'] ?? '', 'ssl'); ?>>SSL</option>
            <option value="none" <?php selected($n['smtp_secure'] ?? '', 'none'); ?>>بدون</option>
        </select>
    </div>
</div>
<div class="pw-row-3">
    <div class="pw-field">
        <label for="pw-smtp-user">نام‌کاربری</label>
        <input type="text" id="pw-smtp-user" name="notifications[smtp_user]" value="<?php echo esc_attr($n['smtp_user'] ?? ''); ?>" dir="ltr" autocomplete="off">
    </div>
    <div class="pw-field">
        <label for="pw-smtp-pass">گذرواژه</label>
        <input type="password" id="pw-smtp-pass" name="notifications[smtp_pass]" value="<?php echo esc_attr($n['smtp_pass'] ?? ''); ?>" dir="ltr" autocomplete="new-password">
    </div>
    <div class="pw-field">
        <label for="pw-smtp-from">ایمیل فرستنده</label>
        <input type="email" id="pw-smtp-from" name="notifications[smtp_from_email]" value="<?php echo esc_attr($n['smtp_from_email'] ?? ''); ?>" dir="ltr">
    </div>
</div>

<hr class="pw-divider">

<h4>پیامک (SMS)</h4>
<div class="pw-row-3">
    <div class="pw-field">
        <label for="pw-sms-provider">سرویس‌دهنده</label>
        <select id="pw-sms-provider" name="notifications[sms_provider]">
            <?php foreach ($smsProviders as $k => $v): ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($n['sms_provider'] ?? 'kavenegar', $k); ?>><?php echo esc_html($v); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="pw-field">
        <label for="pw-sms-key">کلید API</label>
        <input type="text" id="pw-sms-key" name="notifications[sms_api_key]" value="<?php echo esc_attr($n['sms_api_key'] ?? ''); ?>" dir="ltr" autocomplete="off">
    </div>
    <div class="pw-field">
        <label for="pw-sms-sender">شمارهٔ ارسال</label>
        <input type="text" id="pw-sms-sender" name="notifications[sms_sender]" value="<?php echo esc_attr($n['sms_sender'] ?? ''); ?>" dir="ltr">
    </div>
</div>

<div class="pw-row">
    <div class="pw-field">
        <label>Web Push</label>
        <label class="pw-chip <?php echo !empty($n['web_push']) ? 'is-on' : ''; ?>" data-toggle="notifications[web_push]"><?php echo !empty($n['web_push']) ? 'فعال' : 'غیرفعال'; ?></label>
    </div>
    <div class="pw-field">
        <label>اعلان درون‌برنامه‌ای</label>
        <label class="pw-chip <?php echo !empty($n['in_app']) ? 'is-on' : ''; ?>" data-toggle="notifications[in_app]"><?php echo !empty($n['in_app']) ? 'فعال' : 'غیرفعال'; ?></label>
    </div>
</div>
