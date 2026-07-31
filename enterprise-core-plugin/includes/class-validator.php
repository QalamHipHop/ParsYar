<?php
/**
 * Iranian Validators — کد ملی، شیبا، موبایل، کد پستی، شماره کارت، ایمیل، URL
 *
 * @package Enterprise\Core
 */

declare(strict_types=1);

namespace Enterprise;

final class Validator
{
    /**
     * اعتبارسنجی کد ملی ۱۰ رقمی (افراد حقیقی).
     * الگوریتم: ضرب در موقعیت + mod 11.
     */
    public static function nationalId(string $code): bool
    {
        $code = preg_replace('/[^0-9]/', '', $code);
        if (strlen($code) !== 10) {
            return false;
        }
        if (preg_match('/^(\d)\1{9}$/', $code)) {
            return false; // همه ارقام یکسان
        }
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $code[$i] * (10 - $i);
        }
        $sum = $sum % 11;
        $check = (int) $code[9];
        return ($sum < 2 && $check === $sum) || ($sum >= 2 && $check === 11 - $sum);
    }

    /**
     * اعتبارسنجی شناسهٔ ملی ۱۱ رقمی (اشخاص حقوقی).
     */
    public static function legalId(string $code): bool
    {
        $code = preg_replace('/[^0-9]/', '', $code);
        if (strlen($code) !== 11) {
            return false;
        }
        if (preg_match('/^(\d)\1{10}$/', $code)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int) $code[$i] * (11 - $i);
        }
        $sum = $sum % 11;
        $check = (int) $code[10];
        if ($sum < 2) {
            return $check === $sum;
        }
        return $check === 11 - $sum;
    }

    /**
     * اعتبارسنجی شبا (IBAN ایران) — ۲۶ کاراکتر، شروع با IR.
     * الگوریتم: mod-97.
     */
    public static function sheba(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));
        if (strlen($iban) !== 26 || substr($iban, 0, 2) !== 'IR') {
            return false;
        }
        if (!preg_match('/^IR[0-9]{24}$/', $iban)) {
            return false;
        }
        // جابجایی ۴ کاراکتر اول به انتها
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        // تبدیل حروف به عدد: A=10, B=11, ..., Z=35
        $numeric = '';
        for ($i = 0; $i < strlen($rearranged); $i++) {
            $c = $rearranged[$i];
            if (ctype_digit($c)) {
                $numeric .= $c;
            } else {
                $numeric .= (string) (ord($c) - 55);
            }
        }
        // mod 97
        return bcmod($numeric, '97') === '1';
    }

    /**
     * اعتبارسنجی شمارهٔ موبایل ایران + تشخیص اپراتور.
     *
     * @return array{valid:bool,operator:?string,normalized:?string}
     */
    public static function mobile(string $number): array
    {
        $number = preg_replace('/[^0-9]/', '', $number);
        // تبدیل 0098 یا +98 یا 98 به 0
        if (str_starts_with($number, '0098')) {
            $number = '0' . substr($number, 4);
        } elseif (str_starts_with($number, '98') && strlen($number) === 12) {
            $number = '0' . substr($number, 2);
        }
        if (!preg_match('/^09[0-9]{9}$/', $number)) {
            return ['valid' => false, 'operator' => null, 'normalized' => null];
        }
        $prefix3 = substr($number, 0, 4); // 0910, 0990, etc.
        $prefix4 = substr($number, 0, 5); // 09100, 09910, etc.

        $operator = match (true) {
            str_starts_with($prefix4, '09910'),
            str_starts_with($prefix3, '0910'),
            str_starts_with($prefix3, '0911'),
            str_starts_with($prefix3, '0912'),
            str_starts_with($prefix3, '0913'),
            str_starts_with($prefix3, '0914'),
            str_starts_with($prefix3, '0915'),
            str_starts_with($prefix3, '0916'),
            str_starts_with($prefix3, '0917'),
            str_starts_with($prefix3, '0918'),
            str_starts_with($prefix3, '0919'),
            str_starts_with($prefix3, '0990') => 'همراه‌اول',

            str_starts_with($prefix4, '09010'),
            str_starts_with($prefix4, '09011'),
            str_starts_with($prefix3, '0930'),
            str_starts_with($prefix3, '0933'),
            str_starts_with($prefix3, '0935'),
            str_starts_with($prefix3, '0936'),
            str_starts_with($prefix3, '0937'),
            str_starts_with($prefix3, '0938'),
            str_starts_with($prefix3, '0939') => 'ایرانسل',

            str_starts_with($prefix3, '0920'),
            str_starts_with($prefix3, '0921') => 'رایتل',

            str_starts_with($prefix3, '0941') => 'مخابرات',

            default => 'نامشخص',
        };

        return ['valid' => true, 'operator' => $operator, 'normalized' => $number];
    }

    /**
     * اعتبارسنجی تلفن ثابت ایران (با کد شهر).
     */
    public static function phone(string $number): bool
    {
        $number = preg_replace('/[^0-9]/', '', $number);
        if (str_starts_with($number, '0098')) {
            $number = '0' . substr($number, 4);
        } elseif (str_starts_with($number, '98') && strlen($number) === 12) {
            $number = '0' . substr($number, 2);
        }
        // ۰ + کد شهر (۲ تا ۴ رقم) + ۸-۱۰ رقم
        return (bool) preg_match('/^0[1-9][0-9]{8,10}$/', $number);
    }

    /**
     * اعتبارسنجی کد پستی ۱۰ رقمی ایران.
     */
    public static function postalCode(string $code): bool
    {
        $code = preg_replace('/[^0-9]/', '', $code);
        if (strlen($code) !== 10) {
            return false;
        }
        if (preg_match('/^(\d)\1{9}$/', $code)) {
            return false;
        }
        // بررسی ساده: ۵ رقم اول ≠ همه صفر
        return (int) substr($code, 0, 5) > 0;
    }

    /**
     * اعتبارسنجی شمارهٔ کارت بانکی ایران (۱۶ رقم + Luhn + BIN).
     *
     * @return array{valid:bool,bank:?string,bank_code:?string}
     */
    public static function cardNumber(string $card): array
    {
        $card = preg_replace('/[^0-9]/', '', $card);
        if (strlen($card) !== 16) {
            return ['valid' => false, 'bank' => null, 'bank_code' => null];
        }
        // Luhn
        $sum = 0;
        for ($i = 0; $i < 16; $i++) {
            $d = (int) $card[15 - $i];
            if ($i % 2 === 1) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
        }
        if ($sum % 10 !== 0) {
            return ['valid' => false, 'bank' => null, 'bank_code' => null];
        }
        $bin = substr($card, 0, 6);
        $bank = self::BIN_TABLE[$bin] ?? null;
        return [
            'valid'     => true,
            'bank'      => $bank['name'] ?? null,
            'bank_code' => $bank['code'] ?? null,
        ];
    }

    /**
     * تبدیل اعداد فارسی/عربی به انگلیسی.
     */
    public static function persianToEnglish(string $value): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $value = str_replace($persian, $english, $value);
        $value = str_replace($arabic, $english, $value);
        return $value;
    }

    /**
     * تبدیل اعداد انگلیسی به فارسی (برای نمایش).
     */
    public static function englishToPersian(string $value): string
    {
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return str_replace($english, $persian, $value);
    }

    /**
     * جدول BIN (۶ رقم اول) → نام بانک + کد.
     * زیرمجموعه‌ای از مهم‌ترین بانک‌ها. در پروداکشن کامل می‌شود.
     */
    private const BIN_TABLE = [
        '603799' => ['name' => 'بانک ملی ایران', 'code' => 'MELLI'],
        '603770' => ['name' => 'بانک کشاورزی', 'code' => 'KESHAVARZI'],
        '603769' => ['name' => 'بانک صادرات ایران', 'code' => 'SADERAT'],
        '603728' => ['name' => 'بانک صنعت و معدن', 'code' => 'SANAT'],
        '603727' => ['name' => 'بانک پارسیان', 'code' => 'PARSIAN'],
        '610433' => ['name' => 'بانک ملت', 'code' => 'MELLAT'],
        '627353' => ['name' => 'بانک تجارت', 'code' => 'TEJARAT'],
        '589463' => ['name' => 'بانک رفاه کارگران', 'code' => 'REFAH'],
        '621986' => ['name' => 'بانک سامان', 'code' => 'SAMAN'],
        '639346' => ['name' => 'بانک سینا', 'code' => 'SINA'],
        '639607' => ['name' => 'بانک سرمایه', 'code' => 'SARMAYE'],
        '636214' => ['name' => 'بانک آینده', 'code' => 'AYANDEH'],
        '505416' => ['name' => 'بانک گردشگری', 'code' => 'GARDESHGARI'],
        '628023' => ['name' => 'بانک مسکن', 'code' => 'MASKAN'],
        '627760' => ['name' => 'پست بانک ایران', 'code' => 'POST'],
        '627488' => ['name' => 'بانک کارآفرین', 'code' => 'KARAFARIN'],
        '621988' => ['name' => 'بانک توسعه تعاون', 'code' => 'TOSEOH'],
        '502938' => ['name' => 'بانک دی', 'code' => 'DAY'],
        '504172' => ['name' => 'بانک رسالت', 'code' => 'RESALAT'],
        '504706' => ['name' => 'بانک ایران زمین', 'code' => 'IRANZAMIN'],
        '505785' => ['name' => 'بانک خاورمیانه', 'code' => 'Khavarmianeh'],
        '606373' => ['name' => 'بانک قرض‌الحسنه مهر ایران', 'code' => 'MEHR'],
        '639599' => ['name' => 'بانک قوامین', 'code' => 'GHAVAMIN'],
    ];
}
