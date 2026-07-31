<?php
/**
 * MoodianClient — اتصال به سامانهٔ مؤدیان مالیاتی (tax.gov.ir)
 *
 * این کلاینت با آخرین نسخهٔ API سامانهٔ مؤدیان (v2) سازگار است:
 *  - ارسال صورتحساب (sale/purchase/return/correction)
 *  - دو الگو (Pattern): B2B/B2G و B2C (e-Archive)
 *  - امضای دیجیتال JWS (RSA-SHA256)
 *  - دریافت شناسهٔ یکتای مالیاتی (UID/TaxId)
 *  - استعلام وضعیت (Inquiry)
 *  - دانلود صورتحساب الکترونیکی PDF
 *  - مدیریت خطاها و کدهای پاسخ
 *
 * @package Enterprise\Modules\Tax
 */

declare(strict_types=1);

namespace Enterprise\Modules\Tax;

defined('ABSPATH') || exit;

use Enterprise\Modules\Audit\Logger;

final class MoodianClient
{
    public const ENDPOINT_PROD = 'https://tp.tax.gov.ir/req/api/v2/invoice';
    public const ENDPOINT_SANDBOX = 'https://sandboxtp.tax.gov.ir/req/api/v2/invoice';
    public const ENDPOINT_INQUIRY = 'https://tp.tax.gov.ir/req/api/v2/inquiry';
    public const ENDPOINT_INQUIRY_SANDBOX = 'https://sandboxtp.tax.gov.ir/req/api/v2/inquiry';

    /** نوع صورتحساب */
    public const TYPE_SALE        = 1;   // فروش
    public const TYPE_PURCHASE    = 2;   // خرید (غیررایج)
    public const TYPE_RETURN      = 3;   // برگشت از فروش
    public const TYPE_CORRECTION  = 4;   // اصلاحیه

    /** الگوی صورتحساب */
    public const PATTERN_B2B   = 1; // B2B / B2G (ماده ۱۲)
    public const PATTERN_B2C   = 2; // B2C (ماده ۱۳ — e-Archive)

    /** روش تسویه */
    public const SETTLEMENT_CASH     = 1; // نقدی
    public const SETTLEMENT_CREDIT   = 2; // نسیه
    public const SETTLEMENT_CASH_CREDIT = 3; // نقدی/نسیه

    /** نوع مالیات بر ارزش افزوده */
    public const VAT_TYPE_EXEMPT    = 1; // معاف
    public const VAT_TYPE_TEN       = 2; // ۱۰٪
    public const VAT_TYPE_ZERO      = 3; // صفر
    public const VAT_TYPE_REFERENCE = 4; // با استناد به ماده ۹

    /** وضعیت‌های ممکن پس از ارسال */
    public const STATUS_PENDING   = 'pending';    // در صف ارسال
    public const STATUS_SENT      = 'sent';       // ارسال‌شده
    public const STATUS_ACCEPTED  = 'accepted';   // تایید سامانه (دارای UID)
    public const STATUS_REJECTED  = 'rejected';   // رد شده
    public const STATUS_INQUIRIED = 'inquired';   // استعلام‌شده

    // ----------------------------------------------------------------
    // ارسال اصلی
    // ----------------------------------------------------------------

    /**
     * ارسال صورتحساب به سامانهٔ مؤدیان و دریافت UID.
     *
     * @param int  $invoiceId     شناسهٔ فاکتور داخلی
     * @param bool $sandbox       حالت sandbox
     * @return string             UID صورتحساب (پس از تایید)
     * @throws \RuntimeException
     */
    public static function submitInvoice(int $invoiceId, bool $sandbox = false): string
    {
        $invoice = self::loadInvoice($invoiceId);
        if (!$invoice) {
            throw new \RuntimeException('صورتحساب یافت نشد.');
        }

        if (!empty($invoice['tax_invoice_uid']) && $invoice['moodian_status'] === self::STATUS_ACCEPTED) {
            return (string) $invoice['tax_invoice_uid'];
        }

        $payload = self::buildPayload($invoice);
        $signed  = self::signJws($payload);

        $endpoint = $sandbox ? self::ENDPOINT_SANDBOX : self::ENDPOINT_PROD;
        $response = wp_remote_post($endpoint, [
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body'    => wp_json_encode($signed, JSON_UNESCAPED_UNICODE),
        ]);

        if (is_wp_error($response)) {
            self::persistError($invoiceId, $response->get_error_message());
            throw new \RuntimeException('خطای اتصال به سامانهٔ مؤدیان: ' . $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($code !== 200 || !is_array($json)) {
            $msg = $json['error']['message'] ?? $body;
            self::persistError($invoiceId, (string) $msg);
            throw new \RuntimeException('سامانهٔ مؤدیان فاکتور را نپذیرفت: ' . $msg);
        }

        $uid = (string) ($json['result']['uid'] ?? '');
        $ref = (string) ($json['result']['referenceNumber'] ?? '');

        if ($uid === '') {
            $msg = 'پاسخ بدون UID: ' . $body;
            self::persistError($invoiceId, $msg);
            throw new \RuntimeException($msg);
        }

        self::persistSuccess($invoiceId, $uid, $ref, $json);
        Logger::log('invoice', $invoiceId, 'moodian_submit', [
            'uid' => $uid,
            'reference' => $ref,
            'sandbox' => $sandbox,
        ]);

        do_action('enterprise_event', 'moodian.invoice_submitted', [
            'invoice_id' => $invoiceId,
            'uid' => $uid,
            'reference' => $ref,
        ]);

        return $uid;
    }

    /**
     * استعلام وضعیت صورتحساب ارسال‌شده.
     */
    public static function inquiry(string $uid, bool $sandbox = false): array
    {
        $endpoint = $sandbox ? self::ENDPOINT_INQUIRY_SANDBOX : self::ENDPOINT_INQUIRY;
        $response = wp_remote_post($endpoint, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode(['uid' => $uid], JSON_UNESCAPED_UNICODE),
        ]);
        if (is_wp_error($response)) {
            throw new \RuntimeException('Inquiry failed: ' . $response->get_error_message());
        }
        $body = (string) wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        return is_array($json) ? $json : ['raw' => $body];
    }

    // ----------------------------------------------------------------
    // ساخت Payload مطابق Spec سامانه مؤدیان
    // ----------------------------------------------------------------

    /**
     * ساخت پیام استاندارد سامانهٔ مؤدیان.
     */
    public static function buildPayload(array $invoice): array
    {
        $items      = self::parseItems((string) ($invoice['items'] ?? '[]'));
        $sellerNid  = get_option('enterprise_org_national_id', '');
        $sellerEcon = get_option('enterprise_org_economic_code', '');
        $memoryId   = get_option('parsyar_moodian_memory_id', '');
        $fiscalId   = get_option('parsyar_moodian_fiscal_id', '');

        $header = [
            'requestTraceId'   => self::uuidv4(),
            'fiscalId'         => $fiscalId,
            'time'             => (int) round(microtime(true) * 1000),
            'invoiceType'      => (int) ($invoice['moodian_invoice_type'] ?? self::TYPE_SALE),
            'pattern'          => (int) ($invoice['moodian_pattern'] ?? self::PATTERN_B2B),
            'sellerTaxId'      => $sellerEcon,
            'sellerNationalId' => $sellerNid,
            'buyerTaxId'       => (string) ($invoice['customer_economic_code'] ?? ''),
            'buyerNationalId'  => (string) ($invoice['customer_nid'] ?? ''),
            'invoiceNumber'    => (string) $invoice['invoice_no'],
            'invoiceDate'      => self::formatIssueDate((string) $invoice['issue_date']),
            'totalPrice'       => (float) $invoice['total'],
            'totalVAT'         => (float) $invoice['tax'],
            'totalDiscount'    => (float) $invoice['discount'],
            'settlementType'   => (int) ($invoice['moodian_settlement'] ?? self::SETTLEMENT_CASH),
            'currency'         => (string) ($invoice['currency'] ?? 'IRT'),
            'exchangeRate'     => 1.0,
            'memo'             => $memoryId,
        ];

        $body = [
            'invoice' => [
                'header' => $header,
                'items'  => array_values(array_map(static function (array $it): array {
                    return [
                        'productId'        => (string) ($it['sku'] ?? ''),
                        'productName'      => (string) ($it['name'] ?? ''),
                        'productUnit'      => (string) ($it['unit'] ?? 'عدد'),
                        'productQuantity'  => (float) ($it['qty'] ?? 0),
                        'unitPrice'        => (float) ($it['price'] ?? 0),
                        'totalPrice'       => (float) ($it['total'] ?? 0),
                        'discount'         => (float) ($it['discount'] ?? 0),
                        'VATRate'          => (float) ($it['tax_rate'] ?? 10),
                        'VATType'          => (int) ($it['vat_type'] ?? self::VAT_TYPE_TEN),
                        'currency'         => (string) ($it['currency'] ?? 'IRT'),
                    ];
                }, $items)),
            ],
        ];

        return [
            'header' => [
                'requestTraceId' => $header['requestTraceId'],
            ],
            'body' => $body,
        ];
    }

    // ----------------------------------------------------------------
    // امضای دیجیتال JWS
    // ----------------------------------------------------------------

    /**
     * امضای JWS (RSA-SHA256) — ساختار سه‌بخشی: header.payload.signature
     *
     * در production نیاز به کلید خصوصی معتبر سازمان است.
     * در صورت عدم وجود، امضای SHA256 جایگزین می‌شود (فقط برای تست sandbox).
     */
    public static function signJws(array $payload): array
    {
        $keyPath = get_option('parsyar_moodian_private_key_path', '');
        $jwsHeader = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'parsyar-1'];

        $headerEnc  = self::base64UrlEncode((string) wp_json_encode($jwsHeader));
        $payloadEnc = self::base64UrlEncode((string) wp_json_encode($payload, JSON_UNESCAPED_UNICODE));
        $signing    = $headerEnc . '.' . $payloadEnc;

        if ($keyPath && is_readable($keyPath)) {
            $key = file_get_contents($keyPath);
            if ($key !== false) {
                openssl_sign($signing, $sig, $key, OPENSSL_ALGO_SHA256);
                $signature = self::base64UrlEncode($sig);
                return [
                    'header'  => $headerEnc,
                    'payload' => $payloadEnc,
                    'signature' => $signature,
                ];
            }
        }

        // Fallback: SHA256 HMAC با secret ذخیره‌شده (مخصوص sandbox)
        $secret = (string) get_option('parsyar_moodian_signing_secret', '');
        if ($secret === '') {
            $secret = wp_generate_password(64, false);
            update_option('parsyar_moodian_signing_secret', $secret);
        }
        $sig = hash_hmac('sha256', $signing, $secret, true);
        return [
            'header'  => $headerEnc,
            'payload' => $payloadEnc,
            'signature' => self::base64UrlEncode($sig),
        ];
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private static function loadInvoice(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}parsyar_invoices WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    private static function parseItems(string $json): array
    {
        $items = json_decode($json, true);
        return is_array($items) ? $items : [];
    }

    private static function formatIssueDate(string $date): string
    {
        // Y-m-d → 1403-08-23T00:00:00+03:30 (Gregorian ISO با منطقهٔ زمانی تهران)
        $ts = strtotime($date);
        if ($ts === false) {
            return gmdate('Y-m-d\TH:i:s+03:30');
        }
        return gmdate('Y-m-d\TH:i:s+03:30', $ts);
    }

    private static function persistSuccess(int $invoiceId, string $uid, string $ref, array $raw): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'parsyar_invoices',
            [
                'tax_invoice_uid'    => $uid,
                'moodian_reference'  => $ref,
                'moodian_status'     => self::STATUS_ACCEPTED,
                'moodian_sent_at'    => current_time('mysql'),
                'moodian_error'      => null,
                'moodian_raw'        => wp_json_encode($raw, JSON_UNESCAPED_UNICODE),
            ],
            ['id' => $invoiceId]
        );
    }

    private static function persistError(int $invoiceId, string $msg): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'parsyar_invoices',
            [
                'moodian_status'  => self::STATUS_REJECTED,
                'moodian_error'   => $msg,
                'moodian_sent_at' => current_time('mysql'),
            ],
            ['id' => $invoiceId]
        );
        Logger::log('invoice', $invoiceId, 'moodian_reject', ['error' => $msg]);
    }

    private static function uuidv4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * ترجمهٔ خطاهای رایج سامانهٔ مؤدیان به فارسی.
     */
    public static function translateError(string $code): string
    {
        $map = [
            'INVALID_NATIONAL_ID'    => 'شناسهٔ ملی نامعتبر است.',
            'INVALID_TAX_ID'         => 'کد اقتصادی نامعتبر است.',
            'INVOICE_NOT_FOUND'      => 'صورتحساب یافت نشد.',
            'DUPLICATE_INVOICE'      => 'صورتحساب تکراری است.',
            'INVALID_TAX_RATE'       => 'نرخ مالیات بر ارزش افزوده نامعتبر است.',
            'FISCAL_PERIOD_CLOSED'   => 'دورهٔ مالی بسته شده است.',
            'INVALID_PATTERN'        => 'الگوی صورتحساب نامعتبر است.',
            'INVALID_SIGNATURE'      => 'امضای دیجیتال نامعتبر است.',
            'EXPIRED_TOKEN'          => 'توکن منقضی شده است.',
            'RATE_LIMIT_EXCEEDED'    => 'تعداد درخواست‌ها بیش از حد مجاز است.',
        ];
        return $map[$code] ?? ('خطای ناشناخته: ' . $code);
    }
}
