<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Builder;

use Danilovl\MenuBuilderBundle\Builder\MenuBuilder;
use Danilovl\MenuBuilderBundle\Model\{
    DetachedMenuItem,
    MenuItemInterface
};
use Danilovl\MenuBuilderBundle\Security\{
    MenuItemAttribute,
    MenuPermissionChecker
};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Danilovl\MenuBuilderBundle\Tests\Support\MockStorage;

final class MenuBuilderTest extends TestCase
{
    private function makePermissionsAlwaysAllow(): MenuPermissionChecker
    {
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(true);

        return new MenuPermissionChecker($auth);
    }

    public function testBuildReturnsAllItemsWhenAllAllowed(): void
    {
        $storage = new MockStorage;
        $home = DetachedMenuItem::create('main', 'Home');
        $about = DetachedMenuItem::create('main', 'About');
        $storage->save($home);
        $storage->save($about);

        $permissions = $this->makePermissionsAlwaysAllow();
        $builder = new MenuBuilder($storage, $permissions);
        $tree = $builder->build('main');

        $this->assertCount(2, $tree);
    }

    public function testBuildFiltersOutDeniedItems(): void
    {
        $storage = new MockStorage;
        $home = DetachedMenuItem::create('main', 'Home');
        $secret = DetachedMenuItem::create('main', 'Secret');
        $storage->save($home);
        $storage->save($secret);

        $secretId = $secret->getId();
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $callback = static function (string $attribute, mixed $subject) use ($secretId): bool {
            if ($attribute !== MenuItemAttribute::SEE) {
                return false;
            }
            if (!$subject instanceof MenuItemInterface) {
                return false;
            }

            return $subject->getId() !== $secretId;
        };
        $auth->method('isGranted')->willReturnCallback($callback);

        $permissions = new MenuPermissionChecker($auth);
        $builder = new MenuBuilder($storage, $permissions);
        $tree = $builder->build('main');

        $this->assertCount(1, $tree);
        $remainingLabel = $tree[0]->getLabel();
        $this->assertSame('Home', $remainingLabel);
    }

    public function testBuildReturnsEmptyForUnknownMenu(): void
    {
        $storage = new MockStorage;
        $permissions = $this->makePermissionsAlwaysAllow();

        $builder = new MenuBuilder($storage, $permissions);
        $tree = $builder->build('non-existent');

        $this->assertSame([], $tree);
    }

    public function testBuildPreservesOrderFromStorage(): void
    {
        $storage = new MockStorage;
        $first = DetachedMenuItem::create('main', 'First');
        $first->setPosition(0);
        $second = DetachedMenuItem::create('main', 'Second');
        $second->setPosition(1);
        $storage->save($first);
        $storage->save($second);

        $permissions = $this->makePermissionsAlwaysAllow();
        $builder = new MenuBuilder($storage, $permissions);
        $tree = $builder->build('main');

        $labels = [
            $tree[0]->getLabel(),
            $tree[1]->getLabel(),
        ];
        $this->assertSame(['First', 'Second'], $labels);
    }
}
