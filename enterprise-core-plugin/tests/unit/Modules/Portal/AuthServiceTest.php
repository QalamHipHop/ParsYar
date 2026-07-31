<?php
/**
 * Tests for AuthService (Portal) — JWT, base64url, vapid stub, VAPID lazy init.
 *
 * @package Enterprise\Tests\Unit\Modules\Portal
 */

declare(strict_types=1);

namespace Enterprise\Tests\Unit\Modules\Portal;

use PHPUnit\Framework\TestCase;
use Enterprise\Modules\Portal\AuthService;

final class AuthServiceTest extends TestCase
{
    public function testBase64UrlRoundTrip(): void
    {
        $bin = random_bytes(64);
        $enc = AuthService::base64UrlEncode($bin);
        $this->assertStringNotContainsString('+', $enc, 'base64url نباید شامل + باشد');
        $this->assertStringNotContainsString('/', $enc, 'base64url نباید شامل / باشد');
        $this->assertStringNotContainsString('=', $enc, 'base64url نباید شامل padding باشد');
        $dec = AuthService::base64UrlDecode($enc);
        $this->assertSame($bin, $dec, 'باید round-trip دقیق باشد');
    }

    public function testBase64UrlEmptyString(): void
    {
        $this->assertSame('', AuthService::base64UrlEncode(''));
        $this->assertSame('', AuthService::base64UrlDecode(''));
    }

    public function testTtlConstantsArePositive(): void
    {
        $this->assertGreaterThan(0, AuthService::TOKEN_TTL_SECONDS);
        $this->assertGreaterThan(0, AuthService::SESSION_TTL_SECONDS);
        $this->assertGreaterThan(AuthService::SESSION_TTL_SECONDS, AuthService::REFRESH_TTL_SECONDS);
        $this->assertGreaterThan(0, AuthService::MAGIC_RATELIMIT_WINDOW);
    }

    public function testFailedThresholdBiggerThanOne(): void
    {
        $this->assertGreaterThanOrEqual(3, AuthService::FAILED_LOGIN_THRESHOLD);
    }

    public function testValidateJwtRejectsMalformed(): void
    {
        $this->expectException(\RuntimeException::class);
        AuthService::validateJwt('not-a-jwt');
    }

    public function testValidateJwtRejectsTwoParts(): void
    {
        $this->expectException(\RuntimeException::class);
        AuthService::validateJwt('abc.def');
    }

    public function testValidateJwtRejectsBadSignature(): void
    {
        $payload = ['sub' => 1, 'exp' => time() + 60, 'typ' => 'access', 'jti' => 'x'];
        $h = AuthService::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $p = AuthService::base64UrlEncode(json_encode($payload));
        $badSig = AuthService::base64UrlEncode('not-a-real-signature');
        $jwt = "{$h}.{$p}.{$badSig}";
        $this->expectException(\RuntimeException::class);
        AuthService::validateJwt($jwt);
    }
}
