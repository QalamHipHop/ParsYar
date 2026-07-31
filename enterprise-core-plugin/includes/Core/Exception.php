<?php
/**
 * Base Exception — کلاس پایهٔ همهٔ خطاهای سیستم.
 *
 * هر exception در ParsYar سه چیز دارد:
 *   1. message: پیام قابل خواندن برای انسان (به فارسی)
 *   2. code:    کد machine-readable مثل parsyar.ledger.unbalanced
 *   3. details: آرایهٔ اطلاعات تکمیلی (مثلاً مقادیر فعلی، ID ها، ...)
 *
 * این ساختار باعث می‌شود:
 *   - API همیشه error envelope یکسان برگرداند
 *   - کلاینت (SPA، موبایل) بتواند بر اساس کد تصمیم بگیرد
 *   - لاگ‌ها contextual و قابل debug باشند
 *
 * @package Enterprise\Core
 */

declare(strict_types=1);

namespace Enterprise\Core;

defined('ABSPATH') || exit;

use Throwable;

class Exception extends \Exception
{
    /** @var string کد machine-readable (مثلاً parsyar.ledger.unbalanced) */
    protected string $errorCode;

    /** @var array<string, mixed> اطلاعات تکمیلی */
    protected array $details;

    /** @var int HTTP status code پیشنهادی برای پاسخ REST */
    protected int $httpStatus;

    /** @var string سطح شدت: critical|error|warning|info */
    protected string $severity;

    /**
     * @param string          $message    پیام فارسی/انگلیسی قابل خواندن
     * @param string          $errorCode  کد یکتای machine-readable
     * @param array           $details    اطلاعات تکمیلی
     * @param int             $httpStatus HTTP status (پیش‌فرض 422)
     * @param string          $severity   سطح شدت
     * @param int             $code       کد عددی (internal) — نباید با errorCode اشتباه شود
     * @param Throwable|null  $previous   exception قبلی
     */
    public function __construct(
        string $message = '',
        string $errorCode = 'parsyar.error.unknown',
        array $details = [],
        int $httpStatus = 422,
        string $severity = 'error',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->errorCode = $errorCode;
        $this->details   = $details;
        $this->httpStatus = $httpStatus;
        $this->severity  = $severity;
    }

    /**
     * کد machine-readable برای API.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * جزئیات ساختاریافته برای دیباگ.
     *
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * HTTP status پیشنهادی برای REST response.
     */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * سطح شدت (برای observability).
     */
    public function getSeverity(): string
    {
        return $this->severity;
    }

    /**
     * تبدیل به آرایه برای JSON encode در REST response.
     *
     * @return array{code:string,message:string,details:array<string,mixed>,http_status:int,severity:string}
     */
    public function toArray(): array
    {
        return [
            'code'       => $this->errorCode,
            'message'    => $this->getMessage(),
            'details'    => $this->details,
            'http_status'=> $this->httpStatus,
            'severity'   => $this->severity,
        ];
    }

    /**
     * تبدیل به RFC 7807 Problem Details (سازگار با استاندارد).
     *
     * @return array<string, mixed>
     */
    public function toProblemDetails(string $instance = null): array
    {
        $out = [
            'type'   => 'https://parsyar.dev/errors/' . $this->errorCode,
            'title'  => $this->errorCode,
            'status' => $this->httpStatus,
            'detail' => $this->getMessage(),
            'code'   => $this->errorCode,
        ];
        if (!empty($this->details)) {
            $out['errors'] = $this->details;
        }
        if ($instance !== null) {
            $out['instance'] = $instance;
        }
        return $out;
    }

    /**
     * نوشتن در لاگ WordPress + Audit Log سیستم.
     */
    public function report(): void
    {
        if (function_exists('\\Enterprise\\Modules\\Audit\\Logger::log')) {
            try {
                \Enterprise\Modules\Audit\Logger::log('exception', null, strtolower($this->severity), [
                    'code'    => $this->errorCode,
                    'message' => $this->getMessage(),
                    'details' => $this->details,
                    'file'    => $this->getFile(),
                    'line'    => $this->getLine(),
                ]);
            } catch (\Throwable $e) {
                // اگر audit logger در دسترس نبود، silent fail
            }
        }

        if ($this->severity === 'critical' || $this->severity === 'error') {
            error_log(sprintf(
                '[ParsYar][%s] %s — %s — %s',
                strtoupper($this->severity),
                $this->errorCode,
                $this->getMessage(),
                wp_json_encode($this->details)
            ));
        }
    }

    /**
     * ساخت یک exception بحرانی به‌سرعت.
     */
    public static function critical(string $message, string $code, array $details = []): self
    {
        return new self($message, $code, $details, 500, 'critical');
    }

    /**
     * ساخت یک warning به‌سرعت.
     */
    public static function warning(string $message, string $code, array $details = []): self
    {
        return new self($message, $code, $details, 200, 'warning');
    }
}
