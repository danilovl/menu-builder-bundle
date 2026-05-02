<?php

declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Service;

use Danilovl\MenuBuilderBundle\Builder\MenuBuilder;
use Danilovl\MenuBuilderBundle\DependencyInjection\MenuBuilderConfig;
use Danilovl\MenuBuilderBundle\Exception\MenuItemNotFoundException;
use Danilovl\MenuBuilderBundle\Security\MenuPermissionChecker;
use Danilovl\MenuBuilderBundle\Tests\Support\MockStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\{
    RouteCollection,
    RouterInterface
};
use Danilovl\MenuBuilderBundle\Service\{
    MenuManager,
    RouteExistenceChecker
};
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Validation;

final class MenuManagerTest extends TestCase
{
    /**
     * @return array{0: MenuManager, 1: MockStorage}
     */
    private function makeManager(): array
    {
        $storage = new MockStorage;

        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(true);
        $permissions = new MenuPermissionChecker($auth);

        $config = new MenuBuilderConfig(
            storageDriver: 'mock',
            storageRedisDsn: null,
            storageTtl: null,
            storageKeyPrefix: 'menu_builder.',
            storagePool: 'cache.app',
            softDelete: true,
            apiPrefix: '/api/menu',
            apiPublicCacheTtl: 0,
            routeCatalogPreloadLimit: 500,
            renderDefaultTemplate: '@MenuBuilder/menu/default.html.twig',
        );

        $builder = new MenuBuilder($storage, $permissions);
        $dispatcher = new EventDispatcher;
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $routeCollection = new RouteCollection;
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routeCollection);
        $routeChecker = new RouteExistenceChecker($router);

        $manager = new MenuManager($storage, $builder, $dispatcher, $validator, $config, $routeChecker);

        return [$manager, $storage];
    }

    public function testCreateAndFetch(): void
    {
        [$manager] = $this->makeManager();

        $item = $manager->create([
            'menuName' => 'main',
            'label' => 'Home',
            'uri' => '/',
        ]);
        $this->assertNotNull($item->getId());
        $this->assertSame('Home', $item->getLabel());

        $tree = $manager->getTree('main');
        $this->assertCount(1, $tree);
        $this->assertSame('Home', $tree[0]->getLabel());
    }

    public function testCreateChildAndMove(): void
    {
        [$manager] = $this->makeManager();

        $root = $manager->create(['menuName' => 'main', 'label' => 'Root', 'uri' => '/']);
        $child = $manager->create([
            'menuName' => 'main',
            'label' => 'Sub',
            'uri' => '/sub',
            'parentId' => $root->getId(),
        ]);

        $this->assertSame($root->getId(), $child->getParent()?->getId());

        // Move child back to root level.
        $childId = $child->getId();
        $this->assertNotNull($childId);
        $manager->move($childId, null, 0);
        $this->assertNull($child->getParent());
    }

    public function testToggleActive(): void
    {
        [$manager] = $this->makeManager();
        $item = $manager->create(['menuName' => 'main', 'label' => 'X', 'uri' => '/x']);
        $itemId = $item->getId();
        $this->assertNotNull($itemId);

        $manager->setActive($itemId, false);
        $tree = $manager->getTree('main');
        // MockStorage returns only active when onlyActive=true (default in getTree via Builder).
        $this->assertCount(0, $tree);

        $manager->setActive($itemId, true);
        $this->assertCount(1, $manager->getTree('main'));
    }

    public function testDelete(): void
    {
        [$manager] = $this->makeManager();
        $item = $manager->create(['menuName' => 'main', 'label' => 'X', 'uri' => '/x']);
        $itemId = $item->getId();
        $this->assertNotNull($itemId);
        $manager->delete($itemId);

        $this->expectException(MenuItemNotFoundException::class);
        $manager->find($itemId);
    }
}
