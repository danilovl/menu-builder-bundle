<?php

declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Dto\Request;

use Danilovl\MenuBuilderBundle\Dto\Request\CreateMenuItemRequest;
use PHPUnit\Framework\TestCase;

final class CreateMenuItemRequestTest extends TestCase
{
    public function testDefaultPositionIsNull(): void
    {
        $request = new CreateMenuItemRequest(
            menuName: 'main',
            label: 'Test'
        );

        $this->assertNull($request->position);
        $data = $request->toArray();
        $this->assertArrayHasKey('position', $data);
        $this->assertNull($data['position']);
    }

    public function testExplicitPositionIsPreserved(): void
    {
        $request = new CreateMenuItemRequest(
            menuName: 'main',
            label: 'Test',
            position: 10
        );

        $this->assertSame(10, $request->position);
        $data = $request->toArray();
        $this->assertSame(10, $data['position']);
    }
}
