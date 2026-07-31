<?php
declare(strict_types=1);

namespace Enterprise\Tests\Unit\Core;

use Enterprise\Str;
use PHPUnit\Framework\TestCase;

final class StrTest extends TestCase
{
    public function testUuidV4Format(): void
    {
        $u = Str::uuid();
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $u
        );
    }

    public function testUuidUniqueness(): void
    {
        $seen = [];
        for ($i = 0; $i < 1000; $i++) {
            $u = Str::uuid();
            self::assertArrayNotHasKey($u, $seen, "duplicate uuid {$u}");
            $seen[$u] = true;
        }
        self::assertCount(1000, $seen);
    }

    public function testRandomDigits(): void
    {
        $d = Str::randomDigits(8);
        self::assertSame(8, strlen($d));
        self::assertMatchesRegularExpression('/^\d{8}$/', $d);
    }

    public function testSlugify(): void
    {
        self::assertSame('hello-world', Str::slugify('Hello World!'));
        self::assertSame('persian-text', Str::slugify('Persian Text'));
    }

    public function testPersianDigits(): void
    {
        self::assertSame('۱۲۳۴۵۶۷۸۹۰', Str::toPersianDigits('1234567890'));
        self::assertSame('1234567890', Str::toEnglishDigits('۱۲۳۴۵۶۷۸۹۰'));
    }

    public function testMask(): void
    {
        self::assertSame('1234****9012', Str::mask('123456789012', 4, 4));
        self::assertSame('abcd', Str::mask('abcd', 10, 10));
    }

    public function testContains(): void
    {
        self::assertTrue(Str::contains('Hello World', 'world', true));
        self::assertTrue(Str::contains('Hello World', 'WORLD', true));
        self::assertTrue(Str::contains('Hello World', 'Hello'));
        self::assertFalse(Str::contains('Hello World', 'xyz'));
    }

    public function testStartsEndsWith(): void
    {
        self::assertTrue(Str::startsWith('Hello World', 'Hello'));
        self::assertFalse(Str::startsWith('Hello World', 'World'));
        self::assertTrue(Str::endsWith('Hello World', 'World'));
    }

    public function testLimit(): void
    {
        self::assertSame('Hello...', Str::limit('Hello World this is long', 8));
        self::assertSame('Hello', Str::limit('Hello', 8));
    }

    public function testCamelToSnake(): void
    {
        self::assertSame('hello_world', Str::camelToSnake('HelloWorld'));
        self::assertSame('user_id', Str::camelToSnake('userId'));
    }

    public function testSnakeToCamel(): void
    {
        self::assertSame('helloWorld', Str::snakeToCamel('hello_world'));
        self::assertSame('HelloWorld', Str::snakeToCamel('hello_world', true));
    }
}
