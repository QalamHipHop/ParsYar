<?php
/**
 * PaymentGateways — درگاه‌های پرداخت ایرانی.
 *
 * درگاه‌های پشتیبانی‌شده:
 *   - ZarinPal   (https://www.zarinpal.com)
 *   - IDPay      (https://idpay.ir)
 *   - NextPay    (https://nextpay.org)
 *   - Saman      (https://sep.shaparak.ir)
 *   - Pasargad   (https://pep.co.ir)
 *   - Mellat     (https://bpm.shaparak.ir)
 *   - Saderat    (https://sadad.shaparak.ir)
 *   - AsanPardakht (https://asanpardakht.ir)
 *
 * الگو: Manager + Adapter. تمام درگاه‌ها از یک interface مشترک پیروی می‌کنند.
 *
 * @package Enterprise\Modules\Payment
 */

declare(strict_types=1);

namespace Enterprise\Modules\Payment;

defined('ABSPATH') || exit;

interface PaymentGatewayInterface
{
    /**
     * درخواست توکن پرداخت و دریافت URL ارجاع.
     *
     * @param array{
     *   amount:int,         // مبلغ به ریال
     *   callback:string,    // آدرس بازگشت
     *   order_id?:string,   // شناسهٔ سفارش
     *   description?:string,
     *   mobile?:string,
     *   email?:string
     * } $payload
     * @return array{success:bool, ref_id?:string, redirect_url?:string, error?:string, raw?:array}
     */
    public function request(array $payload): array;

    /**
     * تأیید پرداخت پس از بازگشت از درگاه.
     *
     * @param array{ref_id?:string, authority?:string, tracking_id?:string, ...} $payload
     * @return array{success:bool, ref_id?:string, card_pan?:string, error?:string, raw?:array}
     */
    public function verify(array $payload): array;
}

final class GatewayManager
{
    public const GATEWAYS = [
        'zarinpal'    => 'ZarinPal',
        'idpay'       => 'IDPay',
        'nextpay'     => 'NextPay',
        'saman'       => 'Saman',
        'pasargad'    => 'Pasargad',
        'mellat'      => 'Mellat',
        'saderat'     => 'Saderat',
        'asanpardakht'=> 'AsanPardakht',
    ];

    public static function make(string $key): PaymentGatewayInterface
    {
        return match ($key) {
            'zarinpal'     => new ZarinPalAdapter(),
            'idpay'        => new IDPayAdapter(),
            'nextpay'      => new NextPayAdapter(),
            'saman'        => new SamanAdapter(),
            'pasargad'     => new PasargadAdapter(),
            'mellat'       => new MellatAdapter(),
            'saderat'      => new SaderatAdapter(),
            'asanpardakht' => new AsanPardakhtAdapter(),
            default        => throw new \InvalidArgumentException("Unknown gateway: {$key}"),
        };
    }

    public static function configured(?string $key = null): string
    {
        if ($key !== null && isset(self::GATEWAYS[$key])) {
            return $key;
        }
        $opt = (string) get_option('parsyar_payment_gateway', 'zarinpal');
        return isset(self::GATEWAYS[$opt]) ? $opt : 'zarinpal';
    }
}

/* ====================================================================== *
 *  Helpers
 * ====================================================================== */

final class GatewayHttp
{
    /**
     * @param array<string,string> $headers
     * @return array{success:bool, status:int, body:string, data:array<string,mixed>}
     */
    public static function postJson(string $url, array $payload, array $headers = [], int $timeout = 30): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
        ]);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        $data = $raw !== false ? (json_decode((string) $raw, true) ?: []) : [];
        if ($raw === false) {
            return ['success' => false, 'status' => 0, 'body' => '', 'data' => [], 'error' => $err];
        }
        return ['success' => $code >= 200 && $code < 300, 'status' => $code, 'body' => (string) $raw, 'data' => $data];
    }

    /**
     * @param array<string,string> $headers
     * @return array{success:bool, status:int, body:string}
     */
    public static function postForm(string $url, array $form, array $headers = [], int $timeout = 30): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($form),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers),
        ]);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['success' => $code >= 200 && $code < 300, 'status' => $code, 'body' => (string) $raw];
    }
}

/* ====================================================================== *
 *  ZarinPal
 * ====================================================================== */

final class ZarinPalAdapter implements PaymentGatewayInterface
{
    private const SANDBOX = 'https://sandbox.zarinpal.com/pg/v4/payment/';
    private const PROD    = 'https://api.zarinpal.com/pg/v4/payment/';

    public function request(array $payload): array
    {
        $merchant = (string) get_option('parsyar_payment_zarinpal_merchant', '');
        $sandbox  = (bool)   get_option('parsyar_payment_zarinpal_sandbox', false);
        if ($merchant === '') {
            return ['success' => false, 'error' => 'zarinpal merchant not configured'];
        }
        $base = $sandbox ? self::SANDBOX : self::PROD;
        $body = [
            'merchant_id'  => $merchant,
            'amount'       => (int) $payload['amount'],
            'callback_url' => (string) $payload['callback'],
            'description'  => (string) ($payload['description'] ?? 'ParsYar payment'),
            'metadata'     => array_filter([
                'order_id' => (string) ($payload['order_id'] ?? ''),
                'mobile'   => (string) ($payload['mobile'] ?? ''),
                'email'    => (string) ($payload['email'] ?? ''),
            ]),
        ];
        $res = GatewayHttp::postJson($base . 'request.json', $body);
        $data = $res['data']['data'] ?? [];
        if ($res['success'] && (int) ($res['data']['errors'] ?? []) === 0 && !empty($data['authority'])) {
            $authority = (string) $data['authority'];
            $scheme    = $sandbox ? 'sandbox' : 'www';
            return [
                'success'      => true,
                'ref_id'       => $authority,
                'redirect_url' => sprintf('https://%s.zarinpal.com/pg/StartPay/%s', $scheme, $authority),
                'raw'          => $res['data'],
            ];
        }
        $errCode = $res['data']['errors']['code'] ?? 'unknown';
        $errMsg  = $res['data']['errors']['message'] ?? 'unknown error';
        return ['success' => false, 'error' => sprintf('[%s] %s', $errCode, $errMsg), 'raw' => $res['data']];
    }

    public function verify(array $payload): array
    {
        $merchant = (string) get_option('parsyar_payment_zarinpal_merchant', '');
        $sandbox  = (bool)   get_option('parsyar_payment_zarinpal_sandbox', false);
        if ($merchant === '') {
            return ['success' => false, 'error' => 'zarinpal merchant not configured'];
        }
        $base = $sandbox ? self::SANDBOX : self::PROD;
        $body = [
            'merchant_id' => $merchant,
            'amount'      => (int) ($payload['amount'] ?? 0),
            'authority'   => (string) ($payload['authority'] ?? $payload['ref_id'] ?? ''),
        ];
        if ($body['authority'] === '') {
            return ['success' => false, 'error' => 'authority missing'];
        }
        $res = GatewayHttp::postJson($base . 'verify.json', $body);
        $data = $res['data']['data'] ?? [];
        if ($res['success'] && (int) ($res['data']['errors'] ?? []) === 0 && !empty($data['ref_id'])) {
            return [
                'success'   => true,
                'ref_id'    => (string) $data['ref_id'],
                'card_pan'  => (string) ($data['card_pan'] ?? ''),
                'raw'       => $res['data'],
            ];
        }
        $errCode = $res['data']['errors']['code'] ?? 'unknown';
        $errMsg  = $res['data']['errors']['message'] ?? 'unknown error';
        return ['success' => false, 'error' => sprintf('[%s] %s', $errCode, $errMsg), 'raw' => $res['data']];
    }
}

/* ====================================================================== *
 *  IDPay
 * ====================================================================== */

final class IDPayAdapter implements PaymentGatewayInterface
{
    private const API = 'https://api.idpay.ir/v1.1/payment';

    public function request(array $payload): array
    {
        $apiKey = (string) get_option('parsyar_payment_idpay_apikey', '');
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'idpay api key not configured'];
        }
        $body = [
            'order_id' => (string) ($payload['order_id'] ?? ('PY-' . time())),
            'amount'   => (int) $payload['amount'],
            'callback' => (string) $payload['callback'],
        ];
        $res = GatewayHttp::postJson(self::API, $body, ['X-API-KEY: ' . $apiKey, 'X-SANDBOX: ' . ((string) get_option('parsyar_payment_idpay_sandbox', '0'))]);
        if ($res['success'] && !empty($res['data']['id']) && (string) ($res['data']['error_code'] ?? '') === '') {
            return [
                'success'      => true,
                'ref_id'       => (string) $res['data']['id'],
                'redirect_url' => (string) $res['data']['link'],
                'raw'          => $res['data'],
            ];
        }
        $errCode = $res['data']['error_code'] ?? 'unknown';
        $errMsg  = $res['data']['error_message'] ?? 'unknown error';
        return ['success' => false, 'error' => sprintf('[%s] %s', $errCode, $errMsg), 'raw' => $res['data']];
    }

    public function verify(array $payload): array
    {
        $apiKey = (string) get_option('parsyar_payment_idpay_apikey', '');
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'api key not configured'];
        }
        $id = (string) ($payload['ref_id'] ?? $payload['id'] ?? '');
        if ($id === '') {
            return ['success' => false, 'error' => 'id missing'];
        }
        $res = GatewayHttp::postJson(self::API . '/verify', ['id' => $id, 'order_id' => (string) ($payload['order_id'] ?? '')], [
            'X-API-KEY: ' . $apiKey,
        ]);
        $d = $res['data'];
        if ($res['success'] && (string) ($d['status'] ?? '') === '100' && !empty($d['verify'])) {
            return [
                'success'  => true,
                'ref_id'   => (string) ($d['track_id'] ?? $id),
                'card_pan' => (string) ($d['payment']['card_no'] ?? ''),
                'raw'      => $d,
            ];
        }
        $msg = (string) ($d['error_message'] ?? 'verification failed');
        return ['success' => false, 'error' => $msg, 'raw' => $d];
    }
}

/* ====================================================================== *
 *  NextPay
 * ====================================================================== */

final class NextPayAdapter implements PaymentGatewayInterface
{
    private const API = 'https://nextpay.org/nx/gateway/token';

    public function request(array $payload): array
    {
        $apiKey = (string) get_option('parsyar_payment_nextpay_apikey', '');
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'nextpay api key not configured'];
        }
        $body = [
            'api_key'    => $apiKey,
            'order_id'   => (string) ($payload['order_id'] ?? ('PY-' . time())),
            'amount'     => (int) $payload['amount'],
            'callback_uri'=> (string) $payload['callback'],
        ];
        $res = GatewayHttp::postJson(self::API, $body);
        if ($res['success'] && (int) ($res['data']['code'] ?? -1) === 0 && !empty($res['data']['trans_id'])) {
            return [
                'success'      => true,
                'ref_id'       => (string) $res['data']['trans_id'],
                'redirect_url' => 'https://nextpay.org/nx/gateway/payment/' . $res['data']['trans_id'],
                'raw'          => $res['data'],
            ];
        }
        return ['success' => false, 'error' => (string) ($res['data']['msg'] ?? 'failed'), 'raw' => $res['data']];
    }

    public function verify(array $payload): array
    {
        $apiKey = (string) get_option('parsyar_payment_nextpay_apikey', '');
        $transId = (string) ($payload['ref_id'] ?? '');
        if ($apiKey === '' || $transId === '') {
            return ['success' => false, 'error' => 'missing params'];
        }
        $body = [
            'api_key'  => $apiKey,
            'order_id' => (string) ($payload['order_id'] ?? ''),
            'trans_id' => $transId,
            'amount'   => (int) ($payload['amount'] ?? 0),
        ];
        $res = GatewayHttp::postJson('https://nextpay.org/nx/gateway/verify', $body);
        $d = $res['data'];
        if ($res['success'] && (int) ($d['code'] ?? -1) === 0) {
            return [
                'success'  => true,
                'ref_id'   => (string) ($d['Shaparak_Ref_ID'] ?? $transId),
                'card_pan' => (string) ($d['card_holder'] ?? ''),
                'raw'      => $d,
            ];
        }
        return ['success' => false, 'error' => (string) ($d['msg'] ?? 'failed'), 'raw' => $d];
    }
}

/* ====================================================================== *
 *  Saman (Sep)
 * ====================================================================== */

final class SamanAdapter implements PaymentGatewayInterface
{
    private const TOKEN_URL = 'https://sep.shaparak.ir/Payment.aspx';
    private const VERIFY_URL = 'https://sep.shaparak.ir/verifyTransaction.xml';

    public function request(array $payload): array
    {
        $terminal = (string) get_option('parsyar_payment_saman_terminal', '');
        $username = (string) get_option('parsyar_payment_saman_username', '');
        $password = (string) get_option('parsyar_payment_saman_password', '');
        if ($terminal === '' || $username === '' || $password === '') {
            return ['success' => false, 'error' => 'saman credentials incomplete'];
        }
        $resNum = (string) ($payload['order_id'] ?? (string) time());
        $res = GatewayHttp::postForm(self::TOKEN_URL, [
            'TerminalID'    => $terminal,
            'Username'      => $username,
            'Password'      => $password,
            'Amount'        => (int) $payload['amount'],
            'OrderId'       => $resNum,
            'LocalDate'     => self::iranDate('Ymd'),
            'LocalTime'     => self::iranDate('His'),
            'ReturnUrl'     => (string) $payload['callback'],
            'MobileNumber'  => (string) ($payload['mobile'] ?? ''),
        ]);
        // موفقیت = یک توکن عددی برمی‌گردد
        $token = trim($res['body']);
        if ($res['success'] && ctype_digit($token) && strlen($token) > 10) {
            return [
                'success'      => true,
                'ref_id'       => $token,
                'redirect_url' => 'https://sep.shaparak.ir/Payment.aspx?Token=' . $token,
                'raw'          => ['token' => $token],
            ];
        }
        $err = self::samanError($token);
        return ['success' => false, 'error' => $err, 'raw' => ['body' => $token]];
    }

    public function verify(array $payload): array
    {
        $terminal = (string) get_option('parsyar_payment_saman_terminal', '');
        $username = (string) get_option('parsyar_payment_saman_username', '');
        $password = (string) get_option('parsyar_payment_saman_password', '');
        if ($terminal === '' || $username === '' || $password === '') {
            return ['success' => false, 'error' => 'credentials incomplete'];
        }
        $resNum = (string) ($payload['order_id'] ?? '');
        $refNum = (string) ($payload['ref_id'] ?? '');
        if ($resNum === '' || $refNum === '') {
            return ['success' => false, 'error' => 'order_id and ref_id required'];
        }
        $res = GatewayHttp::postForm(self::VERIFY_URL, [
            'TerminalNumber' => $terminal,
            'UserName'       => $username,
            'Password'       => $password,
            'OrderId'        => $resNum,
            'ReferenceNumber'=> $refNum,
        ]);
        $body = trim($res['body']);
        if ($res['success'] && $body !== '' && (float) $body > 0) {
            return [
                'success'  => true,
                'ref_id'   => $refNum,
                'raw'      => ['amount' => (float) $body],
            ];
        }
        return ['success' => false, 'error' => self::samanError($body), 'raw' => ['body' => $body]];
    }

    private static function iranDate(string $format): string
    {
        $tz = new \DateTimeZone('Asia/Tehran');
        $now = new \DateTime('now', $tz);
        return $now->format($format);
    }

    private static function samanError(string $code): string
    {
        $map = [
            '-1'  => 'خطای داخلی',
            '-2'  => 'TerminalID نامعتبر',
            '-3'  => 'مبلغ نامعتبر',
            '-4'  => 'OrderId تکراری',
            '-5'  => 'تعداد درخواست بیش از حد',
            '-6'  => 'پایانه غیرفعال',
            '-7'  => 'تاریخ/زمان نامعتبر',
            '-8'  => 'آدرس بازگشت نامعتبر',
            '-9'  => 'مبلغ بیش از سقف مجاز',
            '-10' => 'پارامترهای ناقص',
            '-11' => 'شماره موبایل نامعتبر',
            '-12' => 'خطای دسترسی',
            '-13' => 'حساب غیرفعال',
            '-14' => 'IP نامعتبر',
            '-15' => 'مبلغ تراکنش با پرداخت مطابقت ندارد',
        ];
        return $map[$code] ?? ('خطای ناشناخته: ' . $code);
    }
}

/* ====================================================================== *
 *  Pasargad (PEP)
 * ====================================================================== */

final class PasargadAdapter implements PaymentGatewayInterface
{
    private const TOKEN_URL = 'https://pep.shaparak.ir/Api/v1/Payment/GetToken';
    private const VERIFY_URL = 'https://pep.shaparak.ir/Api/v1/Payment/VerifyPayment';

    public function request(array $payload): array
    {
        $terminal = (string) get_option('parsyar_payment_pasargad_terminal', '');
        $username = (string) get_option('parsyar_payment_pasargad_username', '');
        $password = (string) get_option('parsyar_payment_pasargad_password', '');
        if ($terminal === '' || $username === '' || $password === '') {
            return ['success' => false, 'error' => 'pasargad credentials incomplete'];
        }
        $invoice = (string) ($payload['order_id'] ?? (string) time());
        $body = [
            'merchantCode' => $terminal,
            'invoiceNumber'=> $invoice,
            'invoiceDate'  => gmdate('Y/m/d H:i:s'),
            'amount'       => (int) $payload['amount'],
            'redirectAddress' => (string) $payload['callback'],
            'mobile'       => (string) ($payload['mobile'] ?? ''),
            'email'        => (string) ($payload['email'] ?? ''),
        ];
        $res = GatewayHttp::postJson(self::TOKEN_URL, $body, self::authHeader($username, $password));
        $d = $res['data'];
        if ($res['success'] && (string) ($d['isSuccess'] ?? 'false') === 'true' && !empty($d['token'])) {
            return [
                'success'      => true,
                'ref_id'       => (string) $d['token'],
                'redirect_url' => 'https://pep.shaparak.ir/Payment.aspx?token=' . $d['token'],
                'raw'          => $d,
            ];
        }
        return ['success' => false, 'error' => (string) ($d['message'] ?? 'failed'), 'raw' => $d];
    }

    public function verify(array $payload): array
    {
        $terminal = (string) get_option('parsyar_payment_pasargad_terminal', '');
        $username = (string) get_option('parsyar_payment_pasargad_username', '');
        $password = (string) get_option('parsyar_payment_pasargad_password', '');
        if ($terminal === '' || $username === '' || $password === '') {
            return ['success' => false, 'error' => 'credentials incomplete'];
        }
        $body = [
            'merchantCode'   => $terminal,
            'invoiceNumber'  => (string) ($payload['order_id'] ?? ''),
            'referenceNumber'=> (string) ($payload['ref_id'] ?? ''),
        ];
        $res = GatewayHttp::postJson(self::VERIFY_URL, $body, self::authHeader($username, $password));
        $d = $res['data'];
        if ($res['success'] && (string) ($d['isSuccess'] ?? 'false') === 'true') {
            return [
                'success'  => true,
                'ref_id'   => (string) ($payload['ref_id'] ?? ''),
                'card_pan' => (string) ($d['cardNumber'] ?? ''),
                'raw'      => $d,
            ];
        }
        return ['success' => false, 'error' => (string) ($d['message'] ?? 'failed'), 'raw' => $d];
    }

    private static function authHeader(string $u, string $p): array
    {
        return ['Authorization: Basic ' . base64_encode($u . ':' . $p)];
    }
}

/* ====================================================================== *
 *  Mellat (BPM)
 * ====================================================================== */

final class MellatAdapter implements PaymentGatewayInterface
{
    private const PAY_URL = 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat';
    private const GATEWAY = 'https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl';

    public function request(array $payload): array
    {
        $terminal = (string) get_option('parsyar_payment_mellat_terminal', '');
        $username = (string) get_option('parsyar_payment_mellat_username', '');
        $password = (string) get_option('parsyar_payment_mellat_password', '');
        if ($terminal === '' || $username === '' || $password === '') {
            return ['success' => false, 'error' => 'mellat credentials incomplete'];
        }
        // ملت نیاز به SOAP دارد — این پیاده‌سازی ساده با client URL است.
        $orderId = (int) ($payload['order_id'] ?? time());
        $localDate = gmdate('Ymd');
        $localTime = gmdate('His');
        $soap = self::buildSoap('bpPayRequest', [
            'terminalId'    => $terminal,
            'userName'      => $username,
            'userPassword'  => $password,
            'orderId'       => $orderId,
            'amount'        => (int) $payload['amount'],
            'localDate'     => $localDate,
            'localTime'     => $localTime,
            'additionalData'=> '',
            'callBackUrl'   => (string) $payload['callback'],
            'payerId'       => 0,
        ]);
        $res = self::callSoap(self::GATEWAY, $soap, 'bpPayRequest');
        if ($res['success'] && str_contains($res['body'], '<return>') && !str_contains($res['body'], '0|')) {
            $refId = self::extractSoapValue($res['body'], 'return');
            return [
                'success'      => true,
                'ref_id'       => $refId,
                'redirect_url' => self::PAY_URL . '?RefId=' . $refId,
                'raw'          => ['refId' => $refId],
            ];
        }
        $err = self::extractSoapValue($res['body'], 'return') ?: 'soap failed';
        return ['success' => false, 'error' => $err, 'raw' => ['body' => $res['body']]];
    }

    public function verify(array $payload): array
    {
        $terminal = (string) get_option('parsyar_payment_mellat_terminal', '');
        $username = (string) get_option('parsyar_payment_mellat_username', '');
        $password = (string) get_option('parsyar_payment_mellat_password', '');
        if ($terminal === '' || $username === '' || $password === '') {
            return ['success' => false, 'error' => 'credentials incomplete'];
        }
        $orderId  = (int) ($payload['order_id'] ?? 0);
        $saleRef  = (string) ($payload['ref_id'] ?? '');
        $saleOrderId = (int) ($payload['sale_order_id'] ?? 0);
        $soap = self::buildSoap('bpVerifyRequest', [
            'terminalId'      => $terminal,
            'userName'        => $username,
            'userPassword'    => $password,
            'orderId'         => $orderId,
            'saleOrderId'     => $saleOrderId,
            'saleReferenceId' => $saleRef,
        ]);
        $res = self::callSoap(self::GATEWAY, $soap, 'bpVerifyRequest');
        $ok = $res['success'] && str_contains($res['body'], '<return>0</return>');
        return [
            'success' => $ok,
            'ref_id'  => $saleRef,
            'error'   => $ok ? null : 'verify failed',
            'raw'     => ['body' => $res['body']],
        ];
    }

    private static function buildSoap(string $action, array $args): string
    {
        $body = '';
        foreach ($args as $k => $v) {
            $body .= '<' . $k . '>' . htmlspecialchars((string) $v, ENT_XML1) . '</' . $k . '>';
        }
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns="http://interfaces.core.sw.bps.com/">'
            . '<soapenv:Header/><soapenv:Body><ns:' . $action . '>' . $body . '</ns:' . $action . '></soapenv:Body></soapenv:Envelope>';
    }

    private static function callSoap(string $url, string $xml, string $action): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: ""',
            ],
        ]);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['success' => $code >= 200 && $code < 300, 'body' => (string) $raw];
    }

    private static function extractSoapValue(string $xml, string $tag): string
    {
        if (preg_match('/<' . preg_quote($tag, '/') . '>([^<]*)<\/' . preg_quote($tag, '/') . '>/', $xml, $m)) {
            return $m[1];
        }
        return '';
    }
}

/* ====================================================================== *
 *  Saderat (Sadad)
 * ====================================================================== */

final class SaderatAdapter implements PaymentGatewayInterface
{
    private const TOKEN_URL = 'https://sadad.shaparak.ir/V1/PeymentApi/PaymentRequest';
    private const VERIFY_URL = 'https://sadad.shaparak.ir/V1/PeymentApi/PaymentVerify';

    public function request(array $payload): array
    {
        $merchant = (string) get_option('parsyar_payment_saderat_merchant', '');
        $terminal = (string) get_option('parsyar_payment_saderat_terminal', '');
        $key      = (string) get_option('parsyar_payment_saderat_key', '');
        if ($merchant === '' || $terminal === '' || $key === '') {
            return ['success' => false, 'error' => 'saderat credentials incomplete'];
        }
        $orderId = (int) ($payload['order_id'] ?? time());
        $amount  = (int) $payload['amount'];
        $localDate = gmdate('Ymd');
        $localTime = gmdate('His');

        $signData = $terminal . ';' . $orderId . ';' . $amount . ';' . $localDate . ';' . $localTime . ';' . $key;
        $sign = strtoupper(hash_hmac('sha256', $signData, $key));

        $body = [
            'TerminalId'       => $terminal,
            'MerchantOrderId'  => $orderId,
            'Amount'           => $amount,
            'LocalDateTime'    => $localDate . $localTime,
            'SignData'         => $sign,
            'ReturnUrl'        => (string) $payload['callback'],
            'AdditionalData'   => '',
            'Originator'       => null,
        ];
        $res = GatewayHttp::postJson(self::TOKEN_URL, $body);
        $d = $res['data'];
        if ($res['success'] && (int) ($d['ResCode'] ?? -1) === 0 && !empty($d['Token'])) {
            return [
                'success'      => true,
                'ref_id'       => (string) $d['Token'],
                'redirect_url' => 'https://sadad.shaparak.ir/V1/Peyment/Payment?token=' . $d['Token'],
                'raw'          => $d,
            ];
        }
        $err = (string) ($d['Description'] ?? $d['ResCode'] ?? 'failed');
        return ['success' => false, 'error' => $err, 'raw' => $d];
    }

    public function verify(array $payload): array
    {
        $merchant = (string) get_option('parsyar_payment_saderat_merchant', '');
        $terminal = (string) get_option('parsyar_payment_saderat_terminal', '');
        $key      = (string) get_option('parsyar_payment_saderat_key', '');
        if ($terminal === '' || $key === '') {
            return ['success' => false, 'error' => 'credentials incomplete'];
        }
        $token = (string) ($payload['ref_id'] ?? '');
        if ($token === '') {
            return ['success' => false, 'error' => 'token missing'];
        }
        $sign = strtoupper(hash_hmac('sha256', $token, $key));
        $res = GatewayHttp::postJson(self::VERIFY_URL, ['Token' => $token, 'SignData' => $sign]);
        $d = $res['data'];
        if ($res['success'] && (int) ($d['ResCode'] ?? -1) === 0) {
            return [
                'success'  => true,
                'ref_id'   => $token,
                'card_pan' => (string) ($d['CardNumber'] ?? ''),
                'raw'      => $d,
            ];
        }
        return ['success' => false, 'error' => (string) ($d['Description'] ?? 'failed'), 'raw' => $d];
    }
}

/* ====================================================================== *
 *  AsanPardakht
 * ====================================================================== */

final class AsanPardakhtAdapter implements PaymentGatewayInterface
{
    private const TOKEN_URL = 'https://ipgsoap.asanpardakht.ir/pay?action=create&type=json';
    private const VERIFY_URL = 'https://ipgsoap.asanpardakht.ir/pay?action=verify&type=json';

    public function request(array $payload): array
    {
        $merchant = (string) get_option('parsyar_payment_asanpardakht_merchant', '');
        $key      = (string) get_option('parsyar_payment_asanpardakht_key', '');
        $username = (string) get_option('parsyar_payment_asanpardakht_username', '');
        $password = (string) get_option('parsyar_payment_asanpardakht_password', '');
        if ($merchant === '' || $key === '' || $username === '' || $password === '') {
            return ['success' => false, 'error' => 'asanpardakht credentials incomplete'];
        }
        $orderId = (int) ($payload['order_id'] ?? time());
        $amount  = (int) $payload['amount'];
        $localDate = gmdate('Ymd');
        $localTime = gmdate('His');
        $additional = '';

        $sign = $this->sign([
            'merchant'   => $merchant,
            'orderId'    => $orderId,
            'amount'     => $amount,
            'localDate'  => $localDate,
            'localTime'  => $localTime,
            'additional' => $additional,
            'callback'   => (string) $payload['callback'],
        ], $key);

        $body = [
            'merchantConfigurationId' => $merchant,
            'serviceTypeId'           => 1,
            'localInvoiceId'          => (string) $orderId,
            'amount'                  => $amount,
            'localDate'               => $localDate,
            'localTime'               => $localTime,
            'additionalData'          => $additional,
            'callbackUrl'             => (string) $payload['callback'],
            'sign'                    => $sign,
        ];
        $res = GatewayHttp::postJson(self::TOKEN_URL, $body);
        $d = $res['data'];
        if ($res['success'] && (string) ($d['Succeed'] ?? 'false') === 'true' && !empty($d['Token'])) {
            return [
                'success'      => true,
                'ref_id'       => (string) $d['Token'],
                'redirect_url' => 'https://ipgsoap.asanpardakht.ir/payment/?token=' . $d['Token'],
                'raw'          => $d,
            ];
        }
        return ['success' => false, 'error' => (string) ($d['Message'] ?? 'failed'), 'raw' => $d];
    }

    public function verify(array $payload): array
    {
        $merchant = (string) get_option('parsyar_payment_asanpardakht_merchant', '');
        $key      = (string) get_option('parsyar_payment_asanpardakht_key', '');
        if ($merchant === '' || $key === '') {
            return ['success' => false, 'error' => 'credentials incomplete'];
        }
        $amount   = (int) ($payload['amount'] ?? 0);
        $orderId  = (int) ($payload['order_id'] ?? 0);
        $token    = (string) ($payload['ref_id'] ?? '');
        $sign = $this->sign([
            'merchant' => $merchant,
            'orderId'  => $orderId,
            'amount'   => $amount,
            'token'    => $token,
        ], $key);

        $body = [
            'merchantConfigurationId' => $merchant,
            'localInvoiceId'          => (string) $orderId,
            'token'                   => $token,
            'sign'                    => $sign,
        ];
        $res = GatewayHttp::postJson(self::VERIFY_URL, $body);
        $d = $res['data'];
        if ($res['success'] && (string) ($d['Succeed'] ?? 'false') === 'true') {
            return [
                'success'  => true,
                'ref_id'   => $token,
                'card_pan' => (string) ($d['CardNumber'] ?? ''),
                'raw'      => $d,
            ];
        }
        return ['success' => false, 'error' => (string) ($d['Message'] ?? 'failed'), 'raw' => $d];
    }

    private function sign(array $p, string $key): string
    {
        $str = ($p['merchant'] ?? '') . ($p['orderId'] ?? '') . ($p['amount'] ?? '') . ($p['localDate'] ?? '') . ($p['localTime'] ?? '') . ($p['additional'] ?? '') . ($p['callback'] ?? '') . ($p['token'] ?? '');
        return base64_encode(hash_hmac('sha256', $str, $key, true));
    }
}
