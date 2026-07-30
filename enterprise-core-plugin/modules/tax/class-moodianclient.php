<?php
declare(strict_types=1);

namespace Enterprise\Modules\Tax;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;

/**
 * اتصال به سامانه مؤدیان (TAX.IR) — صورتحساب الکترونیکی.
 *
 * این پیاده‌سازی ساختار پیام و امضای دیجیتال را فراهم می‌کند.
 * در محیط production باید client certificate (API Key سامانه مؤدیان) اضافه شود.
 */
final class MoodianClient
{
    private const ENDPOINT = 'https://tp.tax.gov.ir/req/api/v2/invoice';

    /**
     * ارسال فاکتور به سامانه مؤدیان و دریافت UID صورتحساب.
     */
    public static function submitInvoice(int $invoiceId): string
    {
        $invoice = Db::getRow('invoices', ['id' => $invoiceId]);
        if (!$invoice) {
            throw new \RuntimeException('Invoice not found');
        }
        if (!empty($invoice['tax_invoice_uid'])) {
            return (string) $invoice['tax_invoice_uid'];
        }

        $payload = self::buildPayload((int) $invoice['id'], $invoice);
        $signed  = self::sign($payload);

        $response = wp_remote_post(self::ENDPOINT, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body'    => wp_json_encode($signed, JSON_UNESCAPED_UNICODE),
        ]);
        if (is_wp_error($response)) {
            throw new \RuntimeException('Tax API error: ' . $response->get_error_message());
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        if ($code !== 200 || empty($json['result']['uid'])) {
            throw new \RuntimeException('Tax API rejected invoice: ' . $body);
        }
        $uid = (string) $json['result']['uid'];
        Db::update('invoices', ['tax_invoice_uid' => $uid], ['id' => $invoiceId]);
        \Enterprise\Modules\Audit\Logger::log('invoice', $invoiceId, 'tax_submit', ['uid' => $uid]);
        return $uid;
    }

    private static function buildPayload(int $invoiceId, array $invoice): array
    {
        return [
            'header' => [
                'requestTraceId' => self::uuidv4(),
                'fiscalId'       => get_option('enterprise_fiscal_id', ''),
                'time'           => (int) (microtime(true) * 1000),
            ],
            'body'   => [
                'invoice' => [
                    'internalInvoiceNumber' => $invoice['invoice_no'],
                    'issueDate'             => $invoice['issue_date'] . 'T00:00:00+03:30',
                    'totalAmount'           => (float) $invoice['total'],
                    'taxAmount'             => (float) $invoice['tax'],
                ],
            ],
        ];
    }

    private static function sign(array $payload): array
    {
        // امضای دیجیتال: در این نسخه payload اصلی برگشت داده می‌شود.
        // در production باید با کلید خصوصی سازمان امضا شود.
        $payload['signature'] = hash('sha256', wp_json_encode($payload));
        return $payload;
    }

    private static function uuidv4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
