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

    public function testReorderMaintainsSequentialPositions(): void
    {
        $manager = $this->makeManager();

        $items = [];
        for ($i = 0; $i < 5; $i++) {
            $items[] = $manager->create(['menuName' => 'main', 'label' => 'Item ' . $i]);
        }

        // Перемещаем элемент с позиции 4 на позицию 1
        $id = $items[4]->getId();
        $this->assertNotNull($id);
        $manager->move($id, null, 1);

        $rootItems = $manager->getTree('main');
        $positions = array_map(static fn ($item) => $item->getPosition(), $rootItems);

        $this->assertSame([0, 1, 2, 3, 4], $positions);
        $this->assertSame('Item 0', $rootItems[0]->getLabel());
        $this->assertSame('Item 4', $rootItems[1]->getLabel());
        $this->assertSame('Item 1', $rootItems[2]->getLabel());

        // Перемещаем между родителями
        $root2 = $manager->create(['menuName' => 'main', 'label' => 'Root 2']);
        $id0 = $items[0]->getId();
        $root2Id = $root2->getId();
        $this->assertNotNull($id0);
        $this->assertNotNull($root2Id);

        $manager->move($id0, $root2Id, 0);

        $rootItems = $manager->getTree('main');
        $this->assertCount(5, $rootItems); // items[1,2,3,4] + root2
        $rootPositions = array_map(static fn ($item) => $item->getPosition(), $rootItems);
        $this->assertSame([0, 1, 2, 3, 4], $rootPositions);

        $children = iterator_to_array($root2->getChildren());
        $this->assertCount(1, $children);
        $this->assertSame(0, $children[0]->getPosition());
        $this->assertSame('Item 0', $children[0]->getLabel());
    }

    public function testDeleteMaintainsSequentialPositions(): void
    {
        $manager = $this->makeManager();

        $items = [];
        for ($i = 0; $i < 5; $i++) {
            $items[] = $manager->create(['menuName' => 'main', 'label' => 'Item ' . $i]);
        }

        // Удаляем элемент с позиции 2 (Item 2)
        $id2 = $items[2]->getId();
        $this->assertNotNull($id2);
        $manager->delete($id2);

        $rootItems = $manager->getAllItems('main'); // Используем getAllItems, так как getTree может фильтровать
        $positions = array_map(static fn ($item) => $item->getPosition(), $rootItems);

        // Должно быть [0, 1, 2, 3] вместо [0, 1, 3, 4]
        $this->assertSame([0, 1, 2, 3], $positions);
        $this->assertSame('Item 0', $rootItems[0]->getLabel());
        $this->assertSame('Item 1', $rootItems[1]->getLabel());
        $this->assertSame('Item 3', $rootItems[2]->getLabel());
        $this->assertSame('Item 4', $rootItems[3]->getLabel());
    }
}
