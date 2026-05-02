<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Service;

use Danilovl\MenuBuilderBundle\Service\RoleCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class RoleCatalogTest extends TestCase
{
    private const string HIERARCHY_PARAM = 'security.role_hierarchy.roles';

    public function testAllReturnsDefaultRoleWhenNoHierarchyAvailable(): void
    {
        $bag = $this->createMock(ParameterBagInterface::class);
        $bag->method('has')->with(self::HIERARCHY_PARAM)->willReturn(false);

        $catalog = new RoleCatalog($bag);
        $roles = $catalog->all();

        $this->assertSame(['ROLE_USER'], $roles);
    }

    public function testAllFlattensHierarchyAndSortsResult(): void
    {
        $hierarchy = [
            'ROLE_ADMIN' => ['ROLE_USER', 'ROLE_MANAGER'],
            'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN'],
        ];
        $bag = $this->createMock(ParameterBagInterface::class);
        $bag->method('has')->with(self::HIERARCHY_PARAM)->willReturn(true);
        $bag->method('get')->with(self::HIERARCHY_PARAM)->willReturn($hierarchy);

        $catalog = new RoleCatalog($bag);
        $roles = $catalog->all();

        $expected = ['ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_SUPER_ADMIN', 'ROLE_USER'];
        $this->assertSame($expected, $roles);
    }

    public function testAllDeduplicatesRoles(): void
    {
        $hierarchy = [
            'ROLE_ADMIN' => ['ROLE_USER'],
            'ROLE_MANAGER' => ['ROLE_USER'],
        ];
        $bag = $this->createMock(ParameterBagInterface::class);
        $bag->method('has')->with(self::HIERARCHY_PARAM)->willReturn(true);
        $bag->method('get')->with(self::HIERARCHY_PARAM)->willReturn($hierarchy);

        $catalog = new RoleCatalog($bag);
        $roles = $catalog->all();

        $userCount = count(array_filter($roles, static function (string $role): bool {
            return $role === 'ROLE_USER';
        }));
        $this->assertSame(1, $userCount);
    }

    public function testAllIgnoresNonArrayHierarchyValue(): void
    {
        $bag = $this->createMock(ParameterBagInterface::class);
        $bag->method('has')->with(self::HIERARCHY_PARAM)->willReturn(true);
        $bag->method('get')->with(self::HIERARCHY_PARAM)->willReturn('not-an-array');

        $catalog = new RoleCatalog($bag);
        $roles = $catalog->all();

        $this->assertSame(['ROLE_USER'], $roles);
    }

    public function testAllUsesInternalCacheOnSecondCall(): void
    {
        $bag = $this->createMock(ParameterBagInterface::class);
        $bag->expects($this->once())->method('has')->willReturn(false);

        $catalog = new RoleCatalog($bag);
        $catalog->all();
        $catalog->all();

        $this->assertSame(1, 1);
    }
}
