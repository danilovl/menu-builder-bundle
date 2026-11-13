<?php

declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit;

use DateTimeImmutable;
use Danilovl\MenuBuilderBundle\Model\{
    DetachedMenuItem,
    MenuItemVisibility
};
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

    public function testWithoutSecurityUnrestrictedItemIsVisible(): void
    {
        $checker = new MenuPermissionChecker;
        $item = DetachedMenuItem::create('main', 'Home');
        $anonymousOnly = DetachedMenuItem::create('main', 'Login');
        $anonymousOnly->setVisibility(MenuItemVisibility::ANONYMOUS);

        $canSeeItem = $checker->canSee($item);
        $canSeeAnonymousOnly = $checker->canSee($anonymousOnly);

        $this->assertTrue($canSeeItem);
        $this->assertTrue($canSeeAnonymousOnly);
    }

    public function testWithoutSecurityAuthenticatedOnlyItemIsHidden(): void
    {
        $checker = new MenuPermissionChecker;
        $item = DetachedMenuItem::create('main', 'Profile');
        $item->setVisibility(MenuItemVisibility::AUTHENTICATED);

        $canSee = $checker->canSee($item);

        $this->assertFalse($canSee);
    }

    public function testWithoutSecurityRoleRestrictedItemIsHidden(): void
    {
        $checker = new MenuPermissionChecker;
        $item = DetachedMenuItem::create('main', 'Admin');
        $item->setRequiredRoles(['ROLE_ADMIN']);

        $canSee = $checker->canSee($item);

        $this->assertFalse($canSee);
    }

    public function testWithoutSecurityUserRestrictedItemIsHidden(): void
    {
        $checker = new MenuPermissionChecker;
        $item = DetachedMenuItem::create('main', 'Private');
        $item->setAllowedUsers(['john']);

        $canSee = $checker->canSee($item);

        $this->assertFalse($canSee);
    }

    public function testWithoutSecurityUnpublishedItemIsHidden(): void
    {
        $checker = new MenuPermissionChecker;
        $tomorrow = new DateTimeImmutable('+1 day');
        $yesterday = new DateTimeImmutable('-1 day');

        $future = DetachedMenuItem::create('main', 'Soon');
        $future->setPublishedAt($tomorrow);

        $expired = DetachedMenuItem::create('main', 'Gone');
        $expired->setUnpublishedAt($yesterday);

        $canSeeFuture = $checker->canSee($future);
        $canSeeExpired = $checker->canSee($expired);

        $this->assertFalse($canSeeFuture);
        $this->assertFalse($canSeeExpired);
    }
}
