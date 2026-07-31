<?php
/**
 * Wizard Step 22 — Backup & Webhooks.
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$b = $state['backups'] ?? [];
$w = $state['webhooks'] ?? [];
?>
<div class="pw-banner info">
    پشتیبان‌گیری خودکار و webhookهای خروجی برای ارتباط با سیستم‌های خارجی.
</div>

<h4>پشتیبان‌گیری</h4>
<div class="pw-row">
    <div class="pw-field">
        <label>فعال‌سازی</label>
        <label class="pw-chip <?php echo !empty($b['enabled']) ? 'is-on' : ''; ?>" data-toggle="backups[enabled]"><?php echo !empty($b['enabled']) ? 'بله' : 'خیر'; ?></label>
    </div>
    <div class="pw-field">
        <label>برنامه</label>
        <select name="backups[schedule]">
            <option value="hourly" <?php selected($b['schedule'] ?? 'daily', 'hourly'); ?>>ساعتی</option>
            <option value="twicedaily" <?php selected($b['schedule'] ?? 'daily', 'twicedaily'); ?>>دو بار در روز</option>
            <option value="daily" <?php selected($b['schedule'] ?? 'daily', 'daily'); ?>>روزانه</option>
            <option value="weekly" <?php selected($b['schedule'] ?? '', 'weekly'); ?>>هفتگی</option>
        </select>
    </div>
    <div class="pw-field">
        <label>مقصد</label>
        <select name="backups[destination]">
            <option value="local" <?php selected($b['destination'] ?? 'local', 'local'); ?>>محلی (پوشهٔ uploads)</option>
            <option value="email" <?php selected($b['destination'] ?? '', 'email'); ?>>ایمیل</option>
            <option value="ftp" <?php selected($b['destination'] ?? '', 'ftp'); ?>>FTP</option>
            <option value="s3" <?php selected($b['destination'] ?? '', 's3'); ?>>S3 / S3-compatible</option>
        </select>
    </div>
    <div class="pw-field">
        <label>تعداد نگهداری</label>
        <input type="number" name="backups[keep_last]" value="<?php echo (int) ($b['keep_last'] ?? 14); ?>" min="1" max="365">
    </div>
</div>

<hr class="pw-divider">

<h4>Webhooks خروجی</h4>
<div class="pw-field">
    <label>فعال‌سازی</label>
    <label class="pw-chip <?php echo !empty($w['enabled']) ? 'is-on' : ''; ?>" data-toggle="webhooks[enabled]"><?php echo !empty($w['enabled']) ? 'بله' : 'خیر'; ?></label>
</div>
<div class="pw-row">
    <div class="pw-field">
        <label>کلید امضا (HMAC SHA-256)</label>
        <input type="text" name="webhooks[signing_secret]" value="<?php echo esc_attr($w['signing_secret'] ?? ''); ?>" dir="ltr" autocomplete="off">
    </div>
    <div class="pw-field">
        <label>حداکثر تلاش مجدد</label>
        <input type="number" name="webhooks[retry_max]" value="<?php echo (int) ($w['retry_max'] ?? 5); ?>" min="0" max="20">
    </div>
</div>
<p class="desc">پس از اتمام ویزارد، از صفحهٔ تنظیمات می‌توانید endpointهای webhook را اضافه کنید.</p>
