<?php
/**
 * Tests for PortalService — ticket/quote validation + UUID + json helpers.
 *
 * @package Enterprise\Tests\Unit\Modules\Portal
 */

declare(strict_types=1);

namespace Enterprise\Tests\Unit\Modules\Portal;

use PHPUnit\Framework\TestCase;
use Enterprise\Modules\Portal\PortalService;

final class PortalServiceTest extends TestCase
{
    public function testTicketStatusesAndPrioritiesAndCategories(): void
    {
        $this->assertContains('open', PortalService::TICKET_STATUSES);
        $this->assertContains('closed', PortalService::TICKET_STATUSES);
        $this->assertContains('high', PortalService::TICKET_PRIORITIES);
        $this->assertContains('urgent', PortalService::TICKET_PRIORITIES);
        $this->assertContains('billing', PortalService::TICKET_CATEGORIES);
        $this->assertContains('other', PortalService::TICKET_CATEGORIES);
    }

    public function testUuidFormat(): void
    {
        $u = PortalService::uuid();
        // RFC 4122
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $u
        );
    }

    public function testUuidsAreUnique(): void
    {
        $a = PortalService::uuid();
        $b = PortalService::uuid();
        $this->assertNotSame($a, $b);
    }

    public function testCreateTicketRejectsShortSubject(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \Enterprise\Modules\Portal\PortalService::createTicket(1, [
            'subject' => 'hi',
            'body'    => 'این یک شرح کافی است که بیش از ده کاراکتر دارد.',
        ]);
    }

    public function testCreateTicketRejectsShortBody(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \Enterprise\Modules\Portal\PortalService::createTicket(1, [
            'subject' => 'موضوع معتبر',
            'body'    => 'کم',
        ]);
    }

    public function testCreateQuoteRequestRequiresItems(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \Enterprise\Modules\Portal\PortalService::createQuoteRequest(1, [
            'notes' => 'این شرح کافی است برای پاس شدن ولی آیتمی ندارد.',
        ]);
    }

    public function testCreateQuoteRequestRequiresNotes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \Enterprise\Modules\Portal\PortalService::createQuoteRequest(1, [
            'notes' => 'کم',
            'items' => [['name' => 'محصول تست', 'qty' => 1]],
        ]);
    }
}
