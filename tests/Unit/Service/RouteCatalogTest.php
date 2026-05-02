<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Service;

use Danilovl\MenuBuilderBundle\Dto\RouteInfo;
use Danilovl\MenuBuilderBundle\Service\RouteCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\{
    Route,
    RouteCollection,
    RouterInterface
};

final class RouteCatalogTest extends TestCase
{
    public function testAllConvertsRouteCollectionToRouteInfo(): void
    {
        $collection = new RouteCollection;
        $homeRoute = new Route('/');
        $homeRoute->setMethods(['GET']);
        $apiRoute = new Route('/api/items');
        $apiRoute->setMethods(['POST']);
        $collection->add('app_home', $homeRoute);
        $collection->add('app_api_items', $apiRoute);

        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        $catalog = new RouteCatalog($router);
        $routes = $catalog->all();

        $this->assertCount(2, $routes);
        $first = $routes[0];
        $this->assertInstanceOf(RouteInfo::class, $first);
        $this->assertSame('app_home', $first->name);
        $this->assertSame('/', $first->path);
        $this->assertSame(['GET'], $first->methods);
    }

    public function testAllUsesCachedResultOnSecondCall(): void
    {
        $collection = new RouteCollection;
        $collection->add('app_home', new Route('/'));

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('getRouteCollection')
            ->willReturn($collection);

        $catalog = new RouteCatalog($router);
        $catalog->all();
        $catalog->all();

        $this->assertSame(1, 1);
    }

    public function testCountReturnsRouteCount(): void
    {
        $collection = new RouteCollection;
        $collection->add('a', new Route('/a'));
        $collection->add('b', new Route('/b'));
        $collection->add('c', new Route('/c'));

        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        $catalog = new RouteCatalog($router);
        $count = $catalog->count();

        $this->assertSame(3, $count);
    }

    public function testFilterEmptyTermReturnsInputUnchanged(): void
    {
        $collection = new RouteCollection;
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        $catalog = new RouteCatalog($router);
        $items = [
            new RouteInfo('app_home', '/', ['GET']),
            new RouteInfo('app_about', '/about', ['GET']),
        ];

        $result = $catalog->filter($items, '   ');

        $this->assertSame($items, $result);
    }

    public function testFilterMatchesByNameAndPath(): void
    {
        $collection = new RouteCollection;
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        $catalog = new RouteCatalog($router);
        $items = [
            new RouteInfo('app_home', '/home', ['GET']),
            new RouteInfo('app_blog_show', '/blog/{slug}', ['GET']),
            new RouteInfo('app_admin_index', '/admin', ['GET']),
        ];

        $byName = $catalog->filter($items, 'blog');
        $byPath = $catalog->filter($items, '/admin');

        $this->assertCount(1, $byName);
        $this->assertCount(1, $byPath);
        $this->assertSame('app_blog_show', $byName[0]->name);
        $this->assertSame('app_admin_index', $byPath[0]->name);
    }

    public function testFilterIsCaseInsensitive(): void
    {
        $collection = new RouteCollection;
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        $catalog = new RouteCatalog($router);
        $items = [new RouteInfo('app_HOME', '/Home', ['GET'])];

        $matches = $catalog->filter($items, 'home');

        $this->assertCount(1, $matches);
    }
}
