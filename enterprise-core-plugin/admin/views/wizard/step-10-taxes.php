<?php
/**
 * Wizard Step 10 — Taxes.
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$taxes = $state['taxes'] ?? [];
?>
<div class="pw-banner info">
    مالیات بر ارزش افزوده (VAT)، مالیات تکلیفی، و معافیت‌ها. در ایران مالیات بر ارزش افزوده ۱۰٪ (با احتساب سهم دولت ۱۰٪ است، نرخ مؤثر ۹٪). سامانهٔ مؤدیان به‌صورت خودکار فاکتورها را به tax.gov.ir ارسال می‌کند.
</div>

<div class="pw-row-3">
    <div class="pw-field">
        <label for="pw-vat">نرخ VAT (%)</label>
        <input type="number" id="pw-vat" name="taxes[vat_percent]" value="<?php echo (int) ($taxes['vat_percent'] ?? 10); ?>" min="0" max="100" step="0.5">
    </div>
    <div class="pw-field">
        <label for="pw-vat-rounding">گرد کردن</label>
        <select id="pw-vat-rounding" name="taxes[rounding]">
            <option value="half_up" <?php selected($taxes['rounding'] ?? 'half_up', 'half_up'); ?>>نیمه به بالا</option>
            <option value="half_down" <?php selected($taxes['rounding'] ?? '', 'half_down'); ?>>نیمه به پایین</option>
            <option value="banker" <?php selected($taxes['rounding'] ?? '', 'banker'); ?>>بانکی</option>
            <option value="none" <?php selected($taxes['rounding'] ?? '', 'none'); ?>>بدون گرد کردن</option>
        </select>
    </div>
    <div class="pw-field">
        <label>محاسبهٔ خودکار</label>
        <label class="pw-chip <?php echo !empty($taxes['auto_calculate']) ? 'is-on' : ''; ?>" data-toggle="taxes[auto_calculate]">
            <?php echo !empty($taxes['auto_calculate']) ? 'فعال' : 'غیرفعال'; ?>
        </label>
    </div>
</div>

<h4>مالیات تکلیفی</h4>
<p class="desc">مثلاً مالیات بر اجاره ۱۰٪ تکلیفی، یا مالیات بر حقوق.</p>
<textarea name="taxes[withholding_json]" rows="4" style="width:100%; font-family:monospace; font-size:12px;" placeholder='[{"name":"مالیات اجاره","rate":10,"subject":"rent"}]'><?php echo esc_textarea(wp_json_encode($taxes['withholding'] ?? [])); ?></textarea>

<h4>معافیت‌ها</h4>
<textarea name="taxes[exemptions_json]" rows="3" style="width:100%; font-family:monospace; font-size:12px;" placeholder='[{"code":"105","name":"صادرات","rate":0}]'><?php echo esc_textarea(wp_json_encode($taxes['exemptions'] ?? [])); ?></textarea>
