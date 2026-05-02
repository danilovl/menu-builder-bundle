<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Dto;

use Danilovl\MenuBuilderBundle\Dto\RouteInfo;
use PHPUnit\Framework\TestCase;

final class RouteInfoTest extends TestCase
{
    public function testConstructorAssignsAllProperties(): void
    {
        $methods = ['GET', 'POST'];
        $info = new RouteInfo('app_home', '/home', $methods);

        $this->assertSame('app_home', $info->name);
        $this->assertSame('/home', $info->path);
        $this->assertSame($methods, $info->methods);
    }

    public function testToArrayProducesExpectedShape(): void
    {
        $methods = ['GET'];
        $info = new RouteInfo('app_show', '/show/{id}', $methods);

        $array = $info->toArray();

        $expected = [
            'name' => 'app_show',
            'path' => '/show/{id}',
            'methods' => $methods,
        ];
        $this->assertSame($expected, $array);
    }

    public function testToArrayPreservesEmptyMethodsList(): void
    {
        $info = new RouteInfo('any', '/any', []);

        $array = $info->toArray();

        $this->assertSame([], $array['methods']);
    }
}
