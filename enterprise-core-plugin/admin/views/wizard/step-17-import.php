<?php
/**
 * Wizard Step 17 — Import data.
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<div class="pw-banner info">
    داده‌های قبلی خود را از CSV یا Excel وارد کنید. سامانهٔ واردسازی دارای mapping هوشمند ستون‌ها، پیش‌نمایش، و اعتبارسنجی است. حداکثر ۵ مگابایت برای هر فایل.
</div>

<div class="pw-field">
    <label>انتخاب نوع داده برای ورود</label>
    <select name="imports[type">
        <option value="contacts">مخاطبین</option>
        <option value="leads">سرنخ‌ها</option>
        <option value="deals">معاملات</option>
        <option value="products">محصولات</option>
        <option value="invoices">فاکتورها</option>
        <option value="employees">کارمندان</option>
    </select>
</div>

<div class="pw-field">
    <label>فایل CSV/Excel</label>
    <input type="file" name="imports[file]" accept=".csv,.xls,.xlsx">
    <span class="desc">پس از انتخاب فایل، روی «بعدی» بزنید تا mapping ستون‌ها نمایش داده شود.</span>
</div>

<p class="desc" style="margin-top:14px;">
    نکته نکته: برای فایل‌های بزرگ یا ورودهای دوره‌ای، از مسیر <code>wp parsyar import &lt;file&gt;</code> در WP-CLI استفاده کنید.
</p>
