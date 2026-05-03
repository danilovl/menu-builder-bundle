<?php

declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Service;

use Danilovl\MenuBuilderBundle\Builder\MenuBuilder;
use Danilovl\MenuBuilderBundle\DependencyInjection\MenuBuilderConfig;
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

final class MenuPositionFixTest extends TestCase
{
    private function makeManager(): MenuManager
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

        return new MenuManager($storage, $builder, $dispatcher, $validator, $config, $routeChecker);
    }

    public function testNewItemsHaveIncreasingPosition(): void
    {
        $manager = $this->makeManager();

        $item1 = $manager->create(['menuName' => 'main', 'label' => 'First']);
        $item2 = $manager->create(['menuName' => 'main', 'label' => 'Second']);
        $item3 = $manager->create(['menuName' => 'main', 'label' => 'Third']);

        $this->assertSame(0, $item1->getPosition());
        $this->assertSame(1, $item2->getPosition());
        $this->assertSame(2, $item3->getPosition());
    }

    public function testNestedItemsHaveIncreasingPosition(): void
    {
        $manager = $this->makeManager();

        $root = $manager->create(['menuName' => 'main', 'label' => 'Root']);
        
        $child1 = $manager->create(['menuName' => 'main', 'label' => 'Child 1', 'parentId' => $root->getId()]);
        $child2 = $manager->create(['menuName' => 'main', 'label' => 'Child 2', 'parentId' => $root->getId()]);

        $this->assertSame(0, $child1->getPosition());
        $this->assertSame(1, $child2->getPosition());
    }

    public function testExplicitPositionIsRespected(): void
    {
        $manager = $this->makeManager();

        $item1 = $manager->create(['menuName' => 'main', 'label' => 'First', 'position' => 10]);
        $item2 = $manager->create(['menuName' => 'main', 'label' => 'Second']);

        $this->assertSame(10, $item1->getPosition());
        $this->assertSame(11, $item2->getPosition());
    }
}
