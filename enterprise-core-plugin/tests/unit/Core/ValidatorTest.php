<?php
declare(strict_types=1);

namespace Enterprise\Tests\Unit\Core;

use Enterprise\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    // ---------- Iranian national code (کد ملی) ----------

    /**
     * @dataProvider validNationalCodes
     */
    public function testValidNationalCode(string $code): void
    {
        self::assertTrue(Validator::nationalCode($code), "{$code} should be valid");
    }

    public static function validNationalCodes(): array
    {
        return [
            ['0012345678'],
            ['1234567890'],
            ['0081234567'],
            ['1111111111'],
            ['0123456789'],
        ];
    }

    /**
     * @dataProvider invalidNationalCodes
     */
    public function testInvalidNationalCode(string $code): void
    {
        self::assertFalse(Validator::nationalCode($code), "{$code} should be invalid");
    }

    public static function invalidNationalCodes(): array
    {
        return [
            ['123'],
            ['abcdefghij'],
            [''],
            ['1111111112'], // wrong check digit
            ['1111111110'],
        ];
    }

    // ---------- Iranian mobile ----------

    /**
     * @dataProvider validMobiles
     */
    public function testValidMobile(string $code): void
    {
        self::assertTrue(Validator::mobile($code), "{$code} should be valid");
    }

    public static function validMobiles(): array
    {
        return [
            ['09123456789'],
            ['+989123456789'],
            ['00989123456789'],
            ['09901456789'],
            ['09351234567'],
        ];
    }

    /**
     * @dataProvider invalidMobiles
     */
    public function testInvalidMobile(string $code): void
    {
        self::assertFalse(Validator::mobile($code), "{$code} should be invalid");
    }

    public static function invalidMobiles(): array
    {
        return [
            ['12345'],
            ['0912345678'],  // 10 digits
            ['08123456789'],  // starts with 08
            ['abcdefghijk'],
        ];
    }

    // ---------- Iranian IBAN (Sheba) ----------

    public function testValidSheba(): void
    {
        // IR + 2 check + 24 digits
        self::assertTrue(Validator::sheba('IR820540102680020817909002'));
        self::assertTrue(Validator::sheba('IR062960000000100324200001'));
    }

    public function testInvalidSheba(): void
    {
        self::assertFalse(Validator::sheba('IR820540102680020817909003'));
        self::assertFalse(Validator::sheba('XX000000000000000000000000'));
        self::assertFalse(Validator::sheba('IR123'));
    }

    // ---------- Iranian postal code ----------

    /**
     * @dataProvider validPostalCodes
     */
    public function testValidPostalCode(string $code): void
    {
        self::assertTrue(Validator::postalCode($code));
    }

    public static function validPostalCodes(): array
    {
        return [
            ['1234567890'],
            ['9876543210'],
        ];
    }

    public function testInvalidPostalCode(): void
    {
        self::assertFalse(Validator::postalCode('12345'));
        self::assertFalse(Validator::postalCode('abcdefghij'));
    }

    // ---------- Card number ----------

    public function testValidCard(): void
    {
        // 16-digit card with valid Luhn.
        self::assertTrue(Validator::cardNumber('6037991234567890'));
    }

    public function testInvalidCard(): void
    {
        self::assertFalse(Validator::cardNumber('1234567890123456'));
        self::assertFalse(Validator::cardNumber('123'));
    }

    // ---------- Email + URL ----------

    public function testEmail(): void
    {
        self::assertTrue(Validator::email('user@example.com'));
        self::assertFalse(Validator::email('user@'));
        self::assertFalse(Validator::email('not-an-email'));
    }

    public function testUrl(): void
    {
        self::assertTrue(Validator::url('https://example.com'));
        self::assertTrue(Validator::url('http://example.com/path'));
        self::assertFalse(Validator::url('not a url'));
    }
}
