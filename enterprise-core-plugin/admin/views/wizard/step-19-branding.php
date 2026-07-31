<?php
/**
 * Wizard Step 19 — Theme & branding.
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$b = $state['branding'] ?? [];
?>
<div class="pw-banner info">
    لوگو، رنگ سازمانی، و فونت را تنظیم کنید. این تنظیمات در رابط کاربری، ایمیل‌ها، و صفحهٔ ورود اعمال می‌شود.
</div>

<div class="pw-row">
    <div class="pw-field">
        <label>لوگو (URL)</label>
        <input type="url" name="branding[logo_url]" value="<?php echo esc_attr($b['logo_url'] ?? ''); ?>" dir="ltr" placeholder="https://">
        <span class="desc">بهترین اندازه: ۲۰۰×۶۴ پیکسل</span>
    </div>
    <div class="pw-field">
        <label>لوگوی صفحهٔ ورود</label>
        <input type="url" name="branding[login_logo]" value="<?php echo esc_attr($b['login_logo'] ?? ''); ?>" dir="ltr">
    </div>
</div>

<div class="pw-row">
    <div class="pw-field">
        <label>فونت اصلی</label>
        <select name="branding[primary_font]">
            <?php foreach (['Vazirmatn' => 'وزیرمتن (فارسی)', 'IRANSansX' => 'ایران‌سنس', 'Shabnam' => 'شبنم', 'Estedad' => 'استعداد', 'Inter' => 'Inter'] as $k => $v): ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($b['primary_font'] ?? 'Vazirmatn', $k); ?>><?php echo esc_html($v); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="pw-field">
        <label>رنگ اصلی</label>
        <input type="color" name="branding[primary_color]" value="<?php echo esc_attr($b['primary_color'] ?? '#0A0A0A'); ?>" dir="ltr">
    </div>
</div>

<div class="pw-field">
    <label>پاورقی ایمیل</label>
    <textarea name="branding[email_footer]" rows="3" style="width:100%;"><?php echo esc_textarea($b['email_footer'] ?? ''); ?></textarea>
</div>
