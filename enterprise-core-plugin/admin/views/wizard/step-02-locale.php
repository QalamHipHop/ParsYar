<?php
/**
 * Wizard Step 2 — Language & locale.
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$languages = [
    'fa_IR' => 'فارسی (ایران)',
    'en_US' => 'English (US)',
    'ar'    => 'العربية',
    'ru_RU' => 'Русский',
];
$timezones = [
    'Asia/Tehran'    => 'تهران (UTC+3:30)',
    'Asia/Dubai'     => 'دبی (UTC+4)',
    'Asia/Riyadh'    => 'ریاض (UTC+3)',
    'Europe/Moscow'  => 'مسکو (UTC+3)',
    'Asia/Tokyo'     => 'توکیو (UTC+9)',
    'UTC'            => 'UTC',
];
$firstDays = [
    0 => 'یکشنبه',
    1 => 'دوشنبه',
    5 => 'جمعه',
    6 => 'شنبه',
];
?>
<div class="pw-banner info">
    زبان، منطقهٔ زمانی، و قالب تاریخ بر کل سیستم اعمال می‌شود. ترجمه‌ها از طریق فایل‌های <code>.po/.mo</code> قابل سفارشی‌سازی هستند.
</div>

<div class="pw-row">
    <div class="pw-field">
        <label for="pw-language">زبان اصلی</label>
        <select id="pw-language" name="language">
            <?php foreach ($languages as $k => $v): ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($state['language'] ?? 'fa_IR', $k); ?>><?php echo esc_html($v); ?></option>
            <?php endforeach; ?>
        </select>
        <span class="desc">زبان رابط کاربری پیش‌فرض. کاربران می‌توانند شخصی‌سازی کنند.</span>
    </div>

    <div class="pw-field">
        <label for="pw-timezone">منطقهٔ زمانی</label>
        <select id="pw-timezone" name="timezone">
            <?php foreach ($timezones as $k => $v): ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($state['timezone'] ?? 'Asia/Tehran', $k); ?>><?php echo esc_html($v); ?></option>
            <?php endforeach; ?>
        </select>
        <span class="desc">تمامی زمان‌ها در این منطقه ذخیره و نمایش داده می‌شوند.</span>
    </div>
</div>

<div class="pw-row-3">
    <div class="pw-field">
        <label for="pw-first-day">اولین روز هفته</label>
        <select id="pw-first-day" name="first_day_week">
            <?php foreach ($firstDays as $k => $v): ?>
                <option value="<?php echo (int) $k; ?>" <?php selected($state['first_day_week'] ?? 6, $k); ?>><?php echo esc_html($v); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="pw-field">
        <label for="pw-date-fmt">قالب تاریخ</label>
        <select id="pw-date-fmt" name="date_format">
            <?php foreach (['Y/m/d' => '۱۴۰۳/۰۵/۱۲', 'd/m/Y' => '۱۲/۰۵/۱۴۰۳', 'Y-m-d' => '۱۴۰۳-۰۵-۱۲', 'd-m-Y' => '۱۲-۰۵-۱۴۰۳'] as $k => $v): ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($state['date_format'] ?? 'Y/m/d', $k); ?>><?php echo esc_html($v); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="pw-field">
        <label for="pw-num-fmt">قالب اعداد</label>
        <select id="pw-num-fmt" name="number_format">
            <option value="fa" <?php selected($state['number_format'] ?? 'fa', 'fa'); ?>>فارسی (۱۲۳٬۴۵۶٫۷۸)</option>
            <option value="en" <?php selected($state['number_format'] ?? 'fa', 'en'); ?>>بین‌المللی (123,456.78)</option>
        </select>
    </div>
</div>
