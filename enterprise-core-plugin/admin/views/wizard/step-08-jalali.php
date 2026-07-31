<?php
/**
 * Wizard Step 8 — Jalali settings.
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$modes = [
    'astronomical' => 'محاسبهٔ نجومی (پیشنهادی — دقیق‌ترین، پشتیبانی کبیسه‌های واقعی)',
    '2820'         => 'الگوریتم ۲۸۲۰ ساله (سازگاری با سایر ابزارها)',
    '33'           => 'الگوریتم ۳۳ ساله (ساده، فقط برای دهه‌های اخیر)',
];
?>
<div class="pw-banner info">
    تقویم شمسی سه الگوریتم دارد. الگوریتم نجومی (محاسبهٔ لحظهٔ اعتدال بهاری) دقیق‌ترین است و با گاه‌شماری ایران هماهنگی کامل دارد.
</div>

<div class="pw-field">
    <label for="pw-jalali-mode">الگوریتم شمسی</label>
    <select id="pw-jalali-mode" name="jalali_mode">
        <?php foreach ($modes as $k => $v): ?>
            <option value="<?php echo esc_attr($k); ?>" <?php selected($state['jalali_mode'] ?? 'astronomical', $k); ?>><?php echo esc_html($v); ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="pw-row">
    <div class="pw-field">
        <label for="pw-jalali-fmt">قالب تاریخ شمسی</label>
        <select id="pw-jalali-fmt" name="jalali_format">
            <?php foreach (['Y/m/d' => '۱۴۰۳/۰۵/۱۲', 'd/m/Y' => '۱۲/۰۵/۱۴۰۳', 'Y-M-d' => '۱۲ مرداد ۱۴۰۳', 'd M Y' => '۱۲ مرداد ۱۴۰۳'] as $k => $v): ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($state['jalali_format'] ?? 'Y/m/d', $k); ?>><?php echo esc_html($v); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="pw-field">
        <label for="pw-jalali-locale">زبان اعداد</label>
        <select id="pw-jalali-locale" name="jalali_locale">
            <option value="fa" <?php selected($state['jalali_locale'] ?? 'fa', 'fa'); ?>>فارسی (۱۲۳۴)</option>
            <option value="en" <?php selected($state['jalali_locale'] ?? 'fa', 'en'); ?>>انگلیسی (1234)</option>
        </select>
    </div>
</div>
