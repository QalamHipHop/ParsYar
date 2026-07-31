<?php
/**
 * Currencies — Multi-currency support
 *
 * @package Enterprise\Data
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

return [
    // ایران
    ['code' => 'IRT', 'name_fa' => 'تومان',          'name_en' => 'Iranian Toman',   'symbol' => 'تومان', 'decimals' => 0, 'country' => 'IR', 'is_base' => false, 'rate_to_irt' => 1.0],
    ['code' => 'IRR', 'name_fa' => 'ریال',           'name_en' => 'Iranian Rial',    'symbol' => 'ریال',  'decimals' => 0, 'country' => 'IR', 'is_base' => true,  'rate_to_irt' => 0.1],

    // بین‌المللی
    ['code' => 'USD', 'name_fa' => 'دلار آمریکا',     'name_en' => 'US Dollar',       'symbol' => '$',     'decimals' => 2, 'country' => 'US', 'is_base' => false, 'rate_to_irt' => 70000.0],
    ['code' => 'EUR', 'name_fa' => 'یورو',            'name_en' => 'Euro',            'symbol' => '€',     'decimals' => 2, 'country' => 'EU', 'is_base' => false, 'rate_to_irt' => 75000.0],
    ['code' => 'GBP', 'name_fa' => 'پوند انگلیس',     'name_en' => 'British Pound',   'symbol' => '£',     'decimals' => 2, 'country' => 'GB', 'is_base' => false, 'rate_to_irt' => 88000.0],
    ['code' => 'AED', 'name_fa' => 'درهم امارات',     'name_en' => 'UAE Dirham',      'symbol' => 'د.إ',   'decimals' => 2, 'country' => 'AE', 'is_base' => false, 'rate_to_irt' => 19000.0],
    ['code' => 'TRY', 'name_fa' => 'لیر ترکیه',       'name_en' => 'Turkish Lira',    'symbol' => '₺',     'decimals' => 2, 'country' => 'TR', 'is_base' => false, 'rate_to_irt' => 2300.0],
    ['code' => 'IQD', 'name_fa' => 'دینار عراق',      'name_en' => 'Iraqi Dinar',     'symbol' => 'د.ع',   'decimals' => 3, 'country' => 'IQ', 'is_base' => false, 'rate_to_irt' => 53.0],
    ['code' => 'SAR', 'name_fa' => 'ریال سعودی',     'name_en' => 'Saudi Riyal',     'symbol' => 'ر.س',   'decimals' => 2, 'country' => 'SA', 'is_base' => false, 'rate_to_irt' => 18600.0],
    ['code' => 'CNY', 'name_fa' => 'یوان چین',        'name_en' => 'Chinese Yuan',    'symbol' => '¥',     'decimals' => 2, 'country' => 'CN', 'is_base' => false, 'rate_to_irt' => 9700.0],
    ['code' => 'RUB', 'name_fa' => 'روبل روسیه',     'name_en' => 'Russian Ruble',   'symbol' => '₽',     'decimals' => 2, 'country' => 'RU', 'is_base' => false, 'rate_to_irt' => 780.0],
    ['code' => 'INR', 'name_fa' => 'روپیه هند',       'name_en' => 'Indian Rupee',    'symbol' => '₹',     'decimals' => 2, 'country' => 'IN', 'is_base' => false, 'rate_to_irt' => 840.0],
    ['code' => 'AFN', 'name_fa' => 'افغانی',          'name_en' => 'Afghan Afghani',  'symbol' => '؋',     'decimals' => 2, 'country' => 'AF', 'is_base' => false, 'rate_to_irt' => 950.0],
    ['code' => 'PKR', 'name_fa' => 'روپیه پاکستان',   'name_en' => 'Pakistani Rupee', 'symbol' => '₨',     'decimals' => 2, 'country' => 'PK', 'is_base' => false, 'rate_to_irt' => 250.0],
    ['code' => 'AZN', 'name_fa' => 'منات آذربایجان',  'name_en' => 'Azerbaijani Manat','symbol' => '₼',    'decimals' => 2, 'country' => 'AZ', 'is_base' => false, 'rate_to_irt' => 41200.0],
    ['code' => 'KWD', 'name_fa' => 'دینار کویت',     'name_en' => 'Kuwaiti Dinar',   'symbol' => 'د.ك',   'decimals' => 3, 'country' => 'KW', 'is_base' => false, 'rate_to_irt' => 228000.0],
    ['code' => 'OMR', 'name_fa' => 'ریال عمان',      'name_en' => 'Omani Rial',      'symbol' => 'ر.ع',   'decimals' => 3, 'country' => 'OM', 'is_base' => false, 'rate_to_irt' => 182000.0],
    ['code' => 'QAR', 'name_fa' => 'ریال قطر',        'name_en' => 'Qatari Riyal',    'symbol' => 'ر.ق',   'decimals' => 2, 'country' => 'QA', 'is_base' => false, 'rate_to_irt' => 19200.0],
    ['code' => 'BHD', 'name_fa' => 'دینار بحرین',    'name_en' => 'Bahraini Dinar',  'symbol' => 'د.ب',   'decimals' => 3, 'country' => 'BH', 'is_base' => false, 'rate_to_irt' => 186000.0],
    ['code' => 'CHF', 'name_fa' => 'فرانک سوئیس',     'name_en' => 'Swiss Franc',     'symbol' => 'CHF',   'decimals' => 2, 'country' => 'CH', 'is_base' => false, 'rate_to_irt' => 80000.0],
    ['code' => 'JPY', 'name_fa' => 'ین ژاپن',         'name_en' => 'Japanese Yen',    'symbol' => '¥',     'decimals' => 0, 'country' => 'JP', 'is_base' => false, 'rate_to_irt' => 470.0],
    ['code' => 'AUD', 'name_fa' => 'دلار استرالیا',   'name_en' => 'Australian Dollar','symbol' => 'A$',   'decimals' => 2, 'country' => 'AU', 'is_base' => false, 'rate_to_irt' => 46000.0],
    ['code' => 'CAD', 'name_fa' => 'دلار کانادا',    'name_en' => 'Canadian Dollar', 'symbol' => 'C$',    'decimals' => 2, 'country' => 'CA', 'is_base' => false, 'rate_to_irt' => 51000.0],
    ['code' => 'BTC', 'name_fa' => 'بیت‌کوین',       'name_en' => 'Bitcoin',         'symbol' => '₿',     'decimals' => 8, 'country' => 'XX', 'is_base' => false, 'rate_to_irt' => 6500000000.0],
];
