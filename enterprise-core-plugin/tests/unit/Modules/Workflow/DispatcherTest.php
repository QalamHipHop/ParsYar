<?php
/**
 * Tests for Workflow\Dispatcher.
 *
 * @package Enterprise\Tests\Unit\Modules\Workflow
 */

declare(strict_types=1);

namespace Enterprise\Tests\Unit\Modules\Workflow;

use PHPUnit\Framework\TestCase;
use Enterprise\Modules\Workflow\Dispatcher;

final class DispatcherTest extends TestCase
{
    public function testNodeTypesIsComplete(): void
    {
        $this->assertArrayHasKey('start',        Dispatcher::NODE_TYPES);
        $this->assertArrayHasKey('end',          Dispatcher::NODE_TYPES);
        $this->assertArrayHasKey('condition',    Dispatcher::NODE_TYPES);
        $this->assertArrayHasKey('send_sms',     Dispatcher::NODE_TYPES);
        $this->assertArrayHasKey('send_email',   Dispatcher::NODE_TYPES);
        $this->assertArrayHasKey('http_request', Dispatcher::NODE_TYPES);
        $this->assertArrayHasKey('delay',        Dispatcher::NODE_TYPES);
    }

    public function testOpsListIsValid(): void
    {
        $this->assertContains('==',  Dispatcher::OPS);
        $this->assertContains('!=',  Dispatcher::OPS);
        $this->assertContains('>',   Dispatcher::OPS);
        $this->assertContains('>=',  Dispatcher::OPS);
        $this->assertContains('contains', Dispatcher::OPS);
    }

    public function testValidateGraphRejectsEmpty(): void
    {
        $errors = Dispatcher::validateGraph(['nodes' => [], 'edges' => []]);
        $this->assertNotEmpty($errors);
    }

    public function testValidateGraphRequiresStartAndEnd(): void
    {
        $errors = Dispatcher::validateGraph([
            'nodes' => [
                ['id' => 'a', 'type' => 'send_sms', 'config' => []],
            ],
            'edges' => [],
        ]);
        $this->assertCount(2, $errors);
    }

    public function testValidateGraphAcceptsMinimalValidGraph(): void
    {
        $errors = Dispatcher::validateGraph([
            'nodes' => [
                ['id' => 's', 'type' => 'start',     'config' => []],
                ['id' => 'a', 'type' => 'send_sms',  'config' => []],
                ['id' => 'e', 'type' => 'end',       'config' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'from' => 's', 'to' => 'a', 'label' => 'default'],
                ['id' => 'e2', 'from' => 'a', 'to' => 'e', 'label' => 'default'],
            ],
        ]);
        $this->assertEmpty($errors);
    }

    public function testValidateGraphDetectsDanglingEdges(): void
    {
        $errors = Dispatcher::validateGraph([
            'nodes' => [
                ['id' => 's', 'type' => 'start', 'config' => []],
                ['id' => 'e', 'type' => 'end',   'config' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'from' => 's', 'to' => 'ghost', 'label' => 'default'],
            ],
        ]);
        $this->assertNotEmpty($errors);
    }

    public function testValidateGraphDetectsUnknownNodeType(): void
    {
        $errors = Dispatcher::validateGraph([
            'nodes' => [
                ['id' => 's', 'type' => 'start',         'config' => []],
                ['id' => 'x', 'type' => 'totally_made',  'config' => []],
                ['id' => 'e', 'type' => 'end',           'config' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'from' => 's', 'to' => 'x', 'label' => 'default'],
                ['id' => 'e2', 'from' => 'x', 'to' => 'e', 'label' => 'default'],
            ],
        ]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('totally_made', implode(' ', $errors));
    }
}
