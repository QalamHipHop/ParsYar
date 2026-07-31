<?php
/**
 * SmsAdapter — درگاه‌های پیامک ایرانی.
 *
 * پشتیبانی از:
 *   - Kavenegar   (https://kavenegar.com)
 *   - Melipayamak (https://melipayamak.com)
 *   - Ghasedak    (https://ghasedak.me)
 *   - SMS.ir      (https://sms.ir)
 *   - IPPanel     (نسل جدید — REST API)
 *   - Log         (توسعه — فقط لاگ)
 *
 * الگوی Adapter: یک interface مشترک + پیاده‌سازی مستقل برای هر سرویس‌دهنده.
 *
 * @package Enterprise\Modules\Notification
 */

declare(strict_types=1);

namespace Enterprise\Modules\Notification;

defined('ABSPATH') || exit;

interface SmsAdapterInterface
{
    /**
     * ارسال پیامک.
     *
     * @param string $to    شماره مقصد (فرمت بین‌المللی یا داخلی)
     * @param string $text  متن پیام
     * @return array{success:bool, message_id?:string, error?:string, raw?:array<string,mixed>}
     */
    public function send(string $to, string $text): array;

    /**
     * بررسی اعتبار حساب.
     *
     * @return array{success:bool, credit?:float, error?:string}
     */
    public function credit(): array;
}

final class SmsAdapter
{
    public const PROVIDERS = ['kavenegar', 'melipayamak', 'ghasedak', 'smsir', 'ippanel', 'log'];

    public static function make(?string $provider = null): SmsAdapterInterface
    {
        $provider = $provider ?: self::configuredProvider();
        return match ($provider) {
            'kavenegar'   => new KavenegarAdapter(),
            'melipayamak' => new MelipayamakAdapter(),
            'ghasedak'    => new GhasedakAdapter(),
            'smsir'       => new SmsIrAdapter(),
            'ippanel'     => new IppanelAdapter(),
            default       => new LogAdapter(),
        };
    }

    public static function configuredProvider(): string
    {
        $opt = get_option('parsyar_sms_provider', 'log');
        $opt = is_string($opt) ? $opt : 'log';
        return in_array($opt, self::PROVIDERS, true) ? $opt : 'log';
    }

    /**
     * ارسال سریع با provider پیش‌فرض.
     *
     * @return array{success:bool, message_id?:string, error?:string}
     */
    public static function send(string $to, string $text, ?string $provider = null): array
    {
        $adapter = self::make($provider);
        $result  = $adapter->send($to, $text);
        do_action('enterprise_sms_sent', $to, $text, $result, $provider);
        return $result;
    }
}

/* ====================================================================== *
 *  Kavenegar
 * ====================================================================== */

final class KavenegarAdapter implements SmsAdapterInterface
{
    private const ENDPOINT = 'https://api.kavenegar.com/v1';

    public function send(string $to, string $text): array
    {
        $apiKey = (string) get_option('parsyar_sms_kavenegar_apikey', '');
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'kavenegar api key not configured'];
        }
        $sender = (string) get_option('parsyar_sms_kavenegar_sender', '');
        $url = self::ENDPOINT . '/' . rawurlencode($apiKey) . '/sms/send.json';
        $body = http_build_query([
            'receptor' => self::normalize($to),
            'message'  => $text,
            'sender'   => $sender,
        ]);
        $res = self::httpPost($url, $body);
        if ($res['success'] && isset($res['data']['return']['status']) && $res['data']['return']['status'] === 200) {
            $entries = $res['data']['entries'] ?? [];
            $messageId = is_array($entries) && !empty($entries) ? (string) ($entries[0]['messageid'] ?? '') : '';
            return ['success' => true, 'message_id' => $messageId, 'raw' => $res['data']];
        }
        $err = $res['data']['return']['message'] ?? 'unknown error';
        return ['success' => false, 'error' => (string) $err, 'raw' => $res['data'] ?? []];
    }

    public function credit(): array
    {
        $apiKey = (string) get_option('parsyar_sms_kavenegar_apikey', '');
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'kavenegar api key not configured'];
        }
        $url = self::ENDPOINT . '/' . rawurlencode($apiKey) . '/account/info.json';
        $res = self::httpGet($url);
        if ($res['success']) {
            $remain = (float) ($res['data']['entries']['remaincredit'] ?? 0);
            return ['success' => true, 'credit' => $remain];
        }
        return ['success' => false, 'error' => 'request failed'];
    }

    private static function normalize(string $number): string
    {
        $n = preg_replace('/[^0-9]/', '', $number) ?? '';
        if (str_starts_with($n, '0098')) {
            $n = '0' . substr($n, 4);
        } elseif (str_starts_with($n, '98') && strlen($n) === 12) {
            $n = '0' . substr($n, 2);
        }
        return $n;
    }

    /** @return array{success:bool,data:array<string,mixed>,error?:string} */
    private static function httpPost(string $url, string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            return ['success' => false, 'data' => [], 'error' => $err];
        }
        $data = json_decode((string) $raw, true) ?: [];
        return ['success' => $code >= 200 && $code < 300, 'data' => $data];
    }

    /** @return array{success:bool,data:array<string,mixed>} */
    private static function httpGet(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode((string) $raw, true) ?: [];
        return ['success' => $code >= 200 && $code < 300, 'data' => $data];
    }
}

/* ====================================================================== *
 *  Melipayamak
 * ====================================================================== */

final class MelipayamakAdapter implements SmsAdapterInterface
{
    private const ENDPOINT = 'https://rest.payamak-panel.com/api/SendSMS/SendSMS';

    public function send(string $to, string $text): array
    {
        $username = (string) get_option('parsyar_sms_melipayamak_user', '');
        $password = (string) get_option('parsyar_sms_melipayamak_pass', '');
        $sender   = (string) get_option('parsyar_sms_melipayamak_sender', '');
        if ($username === '' || $password === '') {
            return ['success' => false, 'error' => 'melipayamak credentials not configured'];
        }
        $body = http_build_query([
            'username' => $username,
            'password' => $password,
            'to'       => self::normalize($to),
            'from'     => $sender,
            'text'     => $text,
        ]);
        $res = self::httpPost(self::ENDPOINT, $body);
        if ($res['success'] && isset($res['data']['RetStatus']) && (int) $res['data']['RetStatus'] === 1) {
            $id = (string) ($res['data']['Value'] ?? '');
            return ['success' => true, 'message_id' => $id, 'raw' => $res['data']];
        }
        $str = (string) ($res['data']['StrRetStatus'] ?? 'unknown');
        return ['success' => false, 'error' => $str, 'raw' => $res['data'] ?? []];
    }

    public function credit(): array
    {
        $username = (string) get_option('parsyar_sms_melipayamak_user', '');
        $password = (string) get_option('parsyar_sms_melipayamak_pass', '');
        if ($username === '' || $password === '') {
            return ['success' => false, 'error' => 'credentials not configured'];
        }
        $url = 'https://rest.payamak-panel.com/api/SendSMS/GetCredit';
        $body = http_build_query(['username' => $username, 'password' => $password]);
        $res = self::httpPost($url, $body);
        if ($res['success']) {
            return ['success' => true, 'credit' => (float) ($res['data']['Value'] ?? 0)];
        }
        return ['success' => false, 'error' => 'request failed'];
    }

    private static function normalize(string $number): string
    {
        $n = preg_replace('/[^0-9]/', '', $number) ?? '';
        if (str_starts_with($n, '0098')) {
            $n = substr($n, 4);
        } elseif (str_starts_with($n, '0') && strlen($n) === 11) {
            $n = '98' . substr($n, 1);
        }
        return $n;
    }

    /** @return array{success:bool,data:array<string,mixed>,error?:string} */
    private static function httpPost(string $url, string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            return ['success' => false, 'data' => [], 'error' => $err];
        }
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            $data = ['Raw' => (string) $raw];
        }
        return ['success' => $code >= 200 && $code < 300, 'data' => $data];
    }
}

/* ====================================================================== *
 *  Ghasedak
 * ====================================================================== */

final class GhasedakAdapter implements SmsAdapterInterface
{
    private const ENDPOINT = 'https://api.ghasedak.me/v2/sms/send';

    public function send(string $to, string $text): array
    {
        $apiKey = (string) get_option('parsyar_sms_ghasedak_apikey', '');
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'ghasedak api key not configured'];
        }
        $sender = (string) get_option('parsyar_sms_ghasedak_sender', '');
        $body = json_encode([
            'message'  => $text,
            'receptor' => self::normalize($to),
            'linenumber' => $sender,
        ]);
        $res = self::httpJsonPost(self::ENDPOINT, $body, [
            'apikey: ' . $apiKey,
        ]);
        if ($res['success'] && isset($res['data']['result']['code']) && (int) $res['data']['result']['code'] === 200) {
            $items = $res['data']['items'] ?? [];
            $id = is_array($items) && !empty($items) ? (string) ($items[0]['id'] ?? '') : '';
            return ['success' => true, 'message_id' => $id, 'raw' => $res['data']];
        }
        return ['success' => false, 'error' => 'ghasedak send failed', 'raw' => $res['data'] ?? []];
    }

    public function credit(): array
    {
        $apiKey = (string) get_option('parsyar_sms_ghasedak_apikey', '');
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'api key not configured'];
        }
        $res = self::httpJsonGet('https://api.ghasedak.me/v2/account/info', ['apikey: ' . $apiKey]);
        if ($res['success']) {
            return ['success' => true, 'credit' => (float) ($res['data']['result']['credit'] ?? 0)];
        }
        return ['success' => false, 'error' => 'request failed'];
    }

    private static function normalize(string $number): string
    {
        $n = preg_replace('/[^0-9]/', '', $number) ?? '';
        if (str_starts_with($n, '0098')) {
            $n = substr($n, 4);
        } elseif (str_starts_with($n, '0') && strlen($n) === 11) {
            $n = '98' . substr($n, 1);
        }
        return $n;
    }

    /** @return array{success:bool,data:array<string,mixed>,error?:string} */
    private static function httpJsonPost(string $url, string $body, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
        ]);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode((string) $raw, true) ?: [];
        return ['success' => $code >= 200 && $code < 300, 'data' => $data];
    }

    /** @return array{success:bool,data:array<string,mixed>} */
    private static function httpJsonGet(string $url, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode((string) $raw, true) ?: [];
        return ['success' => $code >= 200 && $code < 300, 'data' => $data];
    }
}

/* ====================================================================== *
 *  SMS.ir
 * ====================================================================== */

final class SmsIrAdapter implements SmsAdapterInterface
{
    private const ENDPOINT = 'https://api.sms.ir/v1/send/verify';

    public function send(string $to, string $text): array
    {
        $apiKey = (string) get_option('parsyar_sms_smsir_apikey', '');
        $templateId = (int) get_option('parsyar_sms_smsir_template', 0);
        if ($apiKey === '' || $templateId === 0) {
            // fallback: simple send
            return $this->sendLikeToLike($to, $text, $apiKey);
        }
        $body = json_encode([
            'mobile'     => self::normalize($to),
            'templateId' => $templateId,
            'parameters' => [['name' => 'TEXT', 'value' => $text]],
        ]);
        $res = self::httpJsonPost(self::ENDPOINT, $body, ['x-api-key: ' . $apiKey]);
        if ($res['success'] && ($res['data']['status'] ?? 0) === 1) {
            return ['success' => true, 'message_id' => (string) ($res['data']['data']['messageId'] ?? '')];
        }
        return ['success' => false, 'error' => (string) ($res['data']['message'] ?? 'unknown'), 'raw' => $res['data'] ?? []];
    }

    public function credit(): array
    {
        $apiKey = (string) get_option('parsyar_sms_smsir_apikey', '');
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'api key not configured'];
        }
        $res = self::httpJsonGet('https://api.sms.ir/v1/credit', ['x-api-key: ' . $apiKey]);
        if ($res['success']) {
            return ['success' => true, 'credit' => (float) ($res['data']['data']['credit'] ?? 0)];
        }
        return ['success' => false, 'error' => 'request failed'];
    }

    private function sendLikeToLike(string $to, string $text, string $apiKey): array
    {
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'sms.ir api key not configured'];
        }
        $body = json_encode([
            'lineNumber'  => (int) get_option('parsyar_sms_smsir_line', 0),
            'messageText' => $text,
            'mobiles'     => [self::normalize($to)],
        ]);
        $res = self::httpJsonPost('https://api.sms.ir/v1/send/bulk', $body, ['x-api-key: ' . $apiKey]);
        if ($res['success'] && ($res['data']['status'] ?? 0) === 1) {
            return ['success' => true, 'message_id' => '', 'raw' => $res['data']];
        }
        return ['success' => false, 'error' => (string) ($res['data']['message'] ?? 'unknown')];
    }

    private static function normalize(string $number): string
    {
        $n = preg_replace('/[^0-9]/', '', $number) ?? '';
        if (str_starts_with($n, '0098')) {
            $n = substr($n, 4);
        } elseif (str_starts_with($n, '0') && strlen($n) === 11) {
            $n = substr($n, 1);
        }
        return $n;
    }

    /** @return array{success:bool,data:array<string,mixed>} */
    private static function httpJsonPost(string $url, string $body, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
        ]);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode((string) $raw, true) ?: [];
        return ['success' => $code >= 200 && $code < 300, 'data' => $data];
    }

    /** @return array{success:bool,data:array<string,mixed>} */
    private static function httpJsonGet(string $url, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode((string) $raw, true) ?: [];
        return ['success' => $code >= 200 && $code < 300, 'data' => $data];
    }
}

/* ====================================================================== *
 *  IPPanel (REST نسل جدید)
 * ====================================================================== */

final class IppanelAdapter implements SmsAdapterInterface
{
    private const ENDPOINT = 'https://api2.ippanel.com/api/v1';

    public function send(string $to, string $text): array
    {
        $apiKey = (string) get_option('parsyar_sms_ippanel_apikey', '');
        $sender = (string) get_option('parsyar_sms_ippanel_sender', '');
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'ippanel api key not configured'];
        }
        $body = json_encode([
            'from'      => $sender,
            'to'        => [self::normalize($to)],
            'message'   => $text,
        ]);
        $res = self::httpJsonPost(self::ENDPOINT . '/sms/send', $body, ['Authorization: AccessKey ' . $apiKey]);
        if ($res['success'] && ($res['data']['status'] ?? '') === 'OK') {
            return ['success' => true, 'message_id' => (string) ($res['data']['data']['message_id'] ?? ''), 'raw' => $res['data']];
        }
        return ['success' => false, 'error' => (string) ($res['data']['message'] ?? 'unknown'), 'raw' => $res['data'] ?? []];
    }

    public function credit(): array
    {
        $apiKey = (string) get_option('parsyar_sms_ippanel_apikey', '');
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'api key not configured'];
        }
        $res = self::httpJsonGet(self::ENDPOINT . '/account/credit', ['Authorization: AccessKey ' . $apiKey]);
        if ($res['success']) {
            return ['success' => true, 'credit' => (float) ($res['data']['data']['credit'] ?? 0)];
        }
        return ['success' => false, 'error' => 'request failed'];
    }

    private static function normalize(string $number): string
    {
        $n = preg_replace('/[^0-9]/', '', $number) ?? '';
        if (str_starts_with($n, '0098')) {
            $n = substr($n, 4);
        } elseif (str_starts_with($n, '0') && strlen($n) === 11) {
            $n = '98' . substr($n, 1);
        }
        return $n;
    }

    /** @return array{success:bool,data:array<string,mixed>} */
    private static function httpJsonPost(string $url, string $body, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
        ]);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode((string) $raw, true) ?: [];
        return ['success' => $code >= 200 && $code < 300, 'data' => $data];
    }

    /** @return array{success:bool,data:array<string,mixed>} */
    private static function httpJsonGet(string $url, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode((string) $raw, true) ?: [];
        return ['success' => $code >= 200 && $code < 300, 'data' => $data];
    }
}

/* ====================================================================== *
 *  Log (development)
 * ====================================================================== */

final class LogAdapter implements SmsAdapterInterface
{
    public function send(string $to, string $text): array
    {
        if (function_exists('error_log')) {
            error_log(sprintf('[parsyar:sms:log] to=%s text=%s', $to, $text));
        }
        return [
            'success'    => true,
            'message_id' => 'log-' . substr(bin2hex(random_bytes(6)), 0, 12),
            'raw'        => ['mode' => 'log', 'sent_at' => gmdate('c')],
        ];
    }

    public function credit(): array
    {
        return ['success' => true, 'credit' => INF];
    }
}
