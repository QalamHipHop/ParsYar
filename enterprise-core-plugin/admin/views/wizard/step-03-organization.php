<?php
/**
 * Wizard Step 3 — Organization profile.
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$org = $state['org'] ?? [];
$industries = [
    'تولیدی', 'خدماتی', 'بازرگانی', 'فناوری اطلاعات', 'خرده‌فروشی',
    'ساخت‌وساز', 'حمل‌ونقل', 'مالی', 'بیمه', 'سلامت', 'آموزش',
    'گردشگری', 'کشاورزی', 'صنایع غذایی', 'نساجی', 'خودرو', 'نفت و گاز', 'سایر',
];
$sizes = [
    'micro'      => 'کوچک (۱-۹)',
    'small'      => 'کوچک (۱۰-۴۹)',
    'medium'     => 'متوسط (۵۰-۲۴۹)',
    'large'      => 'بزرگ (۲۵۰+)',
    'enterprise' => 'سازمانی',
];
?>
<div class="pw-banner info">
    اطلاعات حقوقی سازمان شما. این موارد در فاکتورها، گزارش‌های مالیاتی، و سامانهٔ مؤدیان استفاده می‌شود.
</div>

<div class="pw-row">
    <div class="pw-field">
        <label for="pw-org-name">نام سازمان *</label>
        <input type="text" id="pw-org-name" name="org[name]" value="<?php echo esc_attr($org['name'] ?? ''); ?>" required>
    </div>
    <div class="pw-field">
        <label for="pw-org-legal">نام حقوقی</label>
        <input type="text" id="pw-org-legal" name="org[legal_name]" value="<?php echo esc_attr($org['legal_name'] ?? ''); ?>">
        <span class="desc">نام ثبت‌شده در اساسنامه</span>
    </div>
</div>

<div class="pw-row-3">
    <div class="pw-field">
        <label for="pw-org-nid">شناسهٔ ملی (۱۱ رقم)</label>
        <input type="text" id="pw-org-nid" name="org[national_id]" value="<?php echo esc_attr($org['national_id'] ?? ''); ?>" maxlength="11" pattern="[0-9]{11}" inputmode="numeric" data-validator="national_id">
        <span class="desc">اعتبارسنجی الگوریتم رسمی</span>
    </div>
    <div class="pw-field">
        <label for="pw-org-eco">کد اقتصادی</label>
        <input type="text" id="pw-org-eco" name="org[economic_code]" value="<?php echo esc_attr($org['economic_code'] ?? ''); ?>" maxlength="32">
    </div>
    <div class="pw-field">
        <label for="pw-org-vat">شناسهٔ ارزش افزوده</label>
        <input type="text" id="pw-org-vat" name="org[vat_number]" value="<?php echo esc_attr($org['vat_number'] ?? ''); ?>" maxlength="32">
    </div>
</div>

<div class="pw-row">
    <div class="pw-field">
        <label for="pw-org-industry">صنعت</label>
        <select id="pw-org-industry" name="org[industry]">
            <?php foreach ($industries as $i): ?>
                <option value="<?php echo esc_attr($i); ?>" <?php selected($org['industry'] ?? '', $i); ?>><?php echo esc_html($i); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="pw-field">
        <label for="pw-org-size">اندازه</label>
        <select id="pw-org-size" name="org[size]">
            <?php foreach ($sizes as $k => $v): ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($org['size'] ?? 'small', $k); ?>><?php echo esc_html($v); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="pw-row">
    <div class="pw-field">
        <label for="pw-org-email">ایمیل</label>
        <input type="email" id="pw-org-email" name="org[email]" value="<?php echo esc_attr($org['email'] ?? ''); ?>" dir="ltr">
    </div>
    <div class="pw-field">
        <label for="pw-org-phone">تلفن</label>
        <input type="tel" id="pw-org-phone" name="org[phone]" value="<?php echo esc_attr($org['phone'] ?? ''); ?>" dir="ltr">
    </div>
</div>

<div class="pw-row">
    <div class="pw-field">
        <label for="pw-org-mobile">موبایل</label>
        <input type="tel" id="pw-org-mobile" name="org[mobile]" value="<?php echo esc_attr($org['mobile'] ?? ''); ?>" dir="ltr" maxlength="11" data-validator="mobile">
        <span class="desc">تشخیص خودکار اپراتور (همراه‌اول/ایرانسل/رایتل)</span>
    </div>
    <div class="pw-field">
        <label for="pw-org-website">وب‌سایت</label>
        <input type="url" id="pw-org-website" name="org[website]" value="<?php echo esc_attr($org['website'] ?? ''); ?>" dir="ltr">
    </div>
</div>

<div class="pw-field">
    <label for="pw-org-addr1">آدرس خط ۱</label>
    <input type="text" id="pw-org-addr1" name="org[address_line1]" value="<?php echo esc_attr($org['address_line1'] ?? ''); ?>">
</div>
<div class="pw-field">
    <label for="pw-org-addr2">آدرس خط ۲</label>
    <input type="text" id="pw-org-addr2" name="org[address_line2]" value="<?php echo esc_attr($org['address_line2'] ?? ''); ?>">
</div>

<div class="pw-row-3">
    <div class="pw-field">
        <label for="pw-org-province">استان</label>
        <input type="text" id="pw-org-province" name="org[province]" value="<?php echo esc_attr($org['province'] ?? ''); ?>">
    </div>
    <div class="pw-field">
        <label for="pw-org-city">شهر</label>
        <input type="text" id="pw-org-city" name="org[city]" value="<?php echo esc_attr($org['city'] ?? ''); ?>">
    </div>
    <div class="pw-field">
        <label for="pw-org-postal">کد پستی (۱۰ رقم)</label>
        <input type="text" id="pw-org-postal" name="org[postal_code]" value="<?php echo esc_attr($org['postal_code'] ?? ''); ?>" maxlength="10" pattern="[0-9]{10}" inputmode="numeric" data-validator="postal">
    </div>
</div>
