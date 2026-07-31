<?php
/**
 * ParsYar — Command Palette
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;
?>
<div class="p-cmd" id="parsyar-cmd" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('جستجوی فرمان', 'parsyar'); ?>" hidden>
    <div class="p-cmd__panel" role="document">
        <input type="search"
               class="p-cmd__input"
               id="parsyar-cmd-input"
               placeholder="<?php esc_attr_e('تایپ کنید تا جستجو یا فرمان اجرا شود...', 'parsyar'); ?>"
               autocomplete="off"
               spellcheck="false" />
        <div class="p-cmd__results" id="parsyar-cmd-results" role="listbox" aria-label="<?php esc_attr_e('نتایج', 'parsyar'); ?>">
            <div class="p-cmd__empty"><?php esc_html_e('برای شروع تایپ کنید...', 'parsyar'); ?></div>
        </div>
        <div class="p-cmd__footer">
            <div class="p-cluster">
                <span><span class="p-key">↑</span> <span class="p-key">↓</span> <?php esc_html_e('پیمایش', 'parsyar'); ?></span>
                <span><span class="p-key">↵</span> <?php esc_html_e('انتخاب', 'parsyar'); ?></span>
            </div>
            <div class="p-cluster">
                <span><span class="p-key">esc</span> <?php esc_html_e('بستن', 'parsyar'); ?></span>
            </div>
        </div>
    </div>
</div>
