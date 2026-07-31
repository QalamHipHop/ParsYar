<?php
/**
 * Ledger Exceptions
 *
 * استثناهای سلسله‌مراتبی برای سیستم دفترداری دوطرفه.
 * هر خطا دارای کد یکتا برای machine-reading است.
 *
 * @package Enterprise\Modules\Accounting
 */

declare(strict_types=1);

namespace Enterprise\Modules\Accounting;

use Enterprise\Core\Exception;

/**
 * زمانی که مجموع بدهکار و بستانکار برابر نباشد.
 * این خطا بحرانی است و ثبت سند باید کاملاً متوقف شود.
 */
final class UnbalancedEntryException extends Exception
{
    public function __construct(float $debit, float $credit)
    {
        $diff = $debit - $credit;
        parent::__construct(
            sprintf(
                'Journal entry is not balanced: debit=%s credit=%s diff=%s',
                number_format($debit, 4, '.', ''),
                number_format($credit, 4, '.', ''),
                number_format($diff, 4, '.', '')
            ),
            'parsyar.ledger.unbalanced',
            [
                'debit'  => $debit,
                'credit' => $credit,
                'diff'   => $diff,
            ]
        );
    }
}

/**
 * زمانی که سند خالی باشد (بدون خط).
 */
final class EmptyEntryException extends Exception
{
    public function __construct()
    {
        parent::__construct(
            'Journal entry must have at least two lines.',
            'parsyar.ledger.empty_entry',
            []
        );
    }
}

/**
 * زمانی که حساب وجود نداشته باشد.
 */
final class AccountNotFoundException extends Exception
{
    public function __construct(string $code)
    {
        parent::__construct(
            sprintf('Account "%s" not found.', $code),
            'parsyar.ledger.account_not_found',
            ['code' => $code]
        );
    }
}

/**
 * زمانی که حساب غیرفعال باشد.
 */
final class InactiveAccountException extends Exception
{
    public function __construct(string $code)
    {
        parent::__construct(
            sprintf('Account "%s" is inactive and cannot be used.', $code),
            'parsyar.ledger.inactive_account',
            ['code' => $code]
        );
    }
}

/**
 * زمانی که دورهٔ مالی بسته باشد و تلاش برای ثبت سند شود.
 */
final class ClosedPeriodException extends Exception
{
    public function __construct(int $periodId, string $periodName)
    {
        parent::__construct(
            sprintf('Fiscal period "%s" (id=%d) is closed.', $periodName, $periodId),
            'parsyar.ledger.closed_period',
            ['period_id' => $periodId, 'period_name' => $periodName]
        );
    }
}

/**
 * زمانی که تراکنش سعی کند در دو دورهٔ مختلف ثبت شود.
 */
final class PeriodMismatchException extends Exception
{
    public function __construct(string $date1, string $date2)
    {
        parent::__construct(
            sprintf('All lines must belong to the same period (got %s and %s).', $date1, $date2),
            'parsyar.ledger.period_mismatch',
            ['date_1' => $date1, 'date_2' => $date2]
        );
    }
}

/**
 * زمانی که نوع حساب با عملیات مجاز نباشد (مثلاً تراکنش روی حساب سیستمی).
 */
final class InvalidAccountTypeException extends Exception
{
    public function __construct(string $code, string $operation, string $expectedType)
    {
        parent::__construct(
            sprintf('Cannot perform "%s" on account "%s" (expected type: %s).', $operation, $code, $expectedType),
            'parsyar.ledger.invalid_account_type',
            ['code' => $code, 'operation' => $operation, 'expected_type' => $expectedType]
        );
    }
}
