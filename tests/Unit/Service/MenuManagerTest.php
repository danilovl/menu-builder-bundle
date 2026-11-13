<?php

declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Service;

use Danilovl\MenuBuilderBundle\Builder\MenuBuilder;
use Danilovl\MenuBuilderBundle\DependencyInjection\MenuBuilderConfig;
use Danilovl\MenuBuilderBundle\Exception\MenuItemNotFoundException;
use Danilovl\MenuBuilderBundle\Security\MenuPermissionChecker;
use Danilovl\MenuBuilderBundle\Tests\Support\MockStorage;
use DomainException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\{
    RouteCollection,
    RouterInterface
};
use Danilovl\MenuBuilderBundle\Service\{
    MenuImporter,
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

    public function testCreateRejectsUnsafeUri(): void
    {
        [$manager, $storage] = $this->makeManager();

        $data = ['menuName' => 'main', 'label' => 'XSS', 'uri' => "java\tscript:alert(1)"];

        try {
            $manager->create($data);
            $this->fail('DomainException expected.');
        } catch (DomainException $e) {
            $message = $e->getMessage();
            $this->assertStringContainsString('"javascript" is not allowed', $message);
        }

        $tree = $storage->getTree('main', false);

        $this->assertSame([], $tree);
    }

    public function testCreateRejectsUnsafeTranslationUri(): void
    {
        [$manager] = $this->makeManager();

        $data = [
            'menuName' => 'main',
            'label' => 'XSS',
            'uri' => '/ok',
            'translations' => ['cs' => ['uri' => 'data:text/html,x']],
        ];

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('translations[cs].uri');

        $manager->create($data);
    }

    public function testUpdateRejectsUnsafeUri(): void
    {
        [$manager] = $this->makeManager();
        $createData = ['menuName' => 'main', 'label' => 'X', 'uri' => '/x'];
        $updateData = ['uri' => 'javascript:alert(1)'];

        $item = $manager->create($createData);
        $itemId = $item->getId();
        $this->assertNotNull($itemId);

        $this->expectException(DomainException::class);

        $manager->update($itemId, $updateData);
    }

    public function testImportRejectsUnsafeUriBeforePersisting(): void
    {
        [$manager, $storage] = $this->makeManager();
        $importer = new MenuImporter($manager);

        $payload = [
            'menu' => 'main',
            'items' => [
                ['label' => 'XSS', 'uri' => 'javascript:alert(1)'],
            ],
        ];

        try {
            $importer->import($payload);
            $this->fail('DomainException expected.');
        } catch (DomainException $e) {
            $message = $e->getMessage();
            $this->assertStringContainsString('Imported item "XSS"', $message);
        }

        $tree = $storage->getTree('main', false);

        $this->assertSame([], $tree);
    }

    public function testImportRejectsUnsafeTranslationUriInChild(): void
    {
        [$manager] = $this->makeManager();
        $importer = new MenuImporter($manager);

        $payload = [
            'menu' => 'main',
            'items' => [
                [
                    'label' => 'Parent',
                    'uri' => '/parent',
                    'children' => [
                        ['label' => 'Child', 'uri' => '/child', 'translations' => ['en' => ['uri' => 'vbscript:x']]],
                    ],
                ],
            ],
        ];

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('translations[en].uri');

        $importer->import($payload);
    }
}
