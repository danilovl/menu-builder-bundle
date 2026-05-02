<?php

declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit;

use Danilovl\MenuBuilderBundle\Model\DetachedMenuItem;
use Danilovl\MenuBuilderBundle\Security\{
    MenuItemAttribute,
    MenuPermissionChecker
};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class MenuPermissionCheckerTest extends TestCase
{
    public function testCanSeeDelegatesToAuthorizationChecker(): void
    {
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->expects($this->once())
            ->method('isGranted')
            ->with(MenuItemAttribute::SEE, $this->isInstanceOf(DetachedMenuItem::class))
            ->willReturn(true);

        $checker = new MenuPermissionChecker($auth);
        $item = DetachedMenuItem::create('main', 'Home');

        $this->assertTrue($checker->canSee($item));
    }

    public function testCanSeeReturnsFalseWhenAuthDenies(): void
    {
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);

        $checker = new MenuPermissionChecker($auth);
        $item = DetachedMenuItem::create('main', 'Secret');

        $this->assertFalse($checker->canSee($item));
    }
}
