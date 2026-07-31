<?php
/**
 * Wizard Step 6 — Currencies & exchange.
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$currencies = [
    'IRT' => 'تومان ایران (IRT) — پیش‌فرض',
    'IRR' => 'ریال ایران (IRR)',
    'USD' => 'دلار آمریکا (USD)',
    'EUR' => 'یورو (EUR)',
    'AED' => 'درهم امارات (AED)',
    'TRY' => 'لیر ترکیه (TRY)',
    'GBP' => 'پوند انگلیس (GBP)',
    'CNY' => 'یوآن چین (CNY)',
    'RUB' => 'روبل روسیه (RUB)',
    'SAR' => 'ریال عربستان (SAR)',
    'IQD' => 'دینار عراق (IQD)',
    'AFN' => 'افغانی (AFN)',
];
$providers = [
    'manual'            => 'دستی (پیش‌فرض — خودتان وارد کنید)',
    'openexchangerates' => 'OpenExchangeRates (API)',
    'tgju'              => 'Tgju.org (بازار ایران)',
    'cbr'               => 'Central Bank of Russia',
    'navasan'           => 'Navasan (بازار ایران)',
];
?>
<div class="pw-banner info">
    سیستم به‌صورت همزمان از چند ارز پشتیبانی می‌کند. نرخ تبدیل می‌تواند خودکار (API) یا دستی وارد شود. ارز پایه در گزارش‌ها استفاده می‌شود.
</div>

<div class="pw-row">
    <div class="pw-field">
        <label for="pw-base-currency">ارز پایه</label>
        <select id="pw-base-currency" name="base_currency">
            <?php foreach ($currencies as $k => $v): ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($state['base_currency'] ?? 'IRT', $k); ?>><?php echo esc_html($v); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="pw-field">
        <label for="pw-exch-provider">تأمین‌کنندهٔ نرخ</label>
        <select id="pw-exch-provider" name="exchange_provider">
            <?php foreach ($providers as $k => $v): ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($state['exchange_provider'] ?? 'manual', $k); ?>><?php echo esc_html($v); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="pw-field">
    <label>ارزهای فعال</label>
    <div class="pw-actions-row" id="pw-currencies-list">
        <?php foreach ($currencies as $k => $v):
            $on = in_array($k, $state['currencies'] ?? ['IRT', 'IRR', 'USD'], true);
        ?>
            <span class="pw-chip <?php echo $on ? 'is-on' : ''; ?>" data-currency="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></span>
        <?php endforeach; ?>
    </div>
    <span class="desc">برای فعال/غیرفعال‌سازی کلیک کنید.</span>
</div>

<div class="pw-field" id="pw-exch-api-wrap" <?php echo ($state['exchange_provider'] ?? '') === 'manual' ? 'hidden' : ''; ?>>
    <label for="pw-exch-key">کلید API</label>
    <input type="text" id="pw-exch-key" name="exchange_api_key" value="<?php echo esc_attr($state['exchange_api_key'] ?? ''); ?>" dir="ltr" autocomplete="off">
    <span class="desc">برای OpenExchangeRates الزامی. سایر ارائه‌دهندگان بسته به نوع.</span>
</div>
