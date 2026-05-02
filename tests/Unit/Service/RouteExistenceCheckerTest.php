<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Service;

use Danilovl\MenuBuilderBundle\Service\RouteExistenceChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\{
    Route,
    RouteCollection,
    RouterInterface
};

final class RouteExistenceCheckerTest extends TestCase
{
    public function testExistsReturnsTrueWhenRoutePresent(): void
    {
        $collection = new RouteCollection;
        $collection->add('app_home', new Route('/'));

        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        $checker = new RouteExistenceChecker($router);
        $exists = $checker->exists('app_home');

        $this->assertTrue($exists);
    }

    public function testExistsReturnsFalseWhenRouteAbsent(): void
    {
        $collection = new RouteCollection;
        $collection->add('app_home', new Route('/'));

        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        $checker = new RouteExistenceChecker($router);
        $exists = $checker->exists('app_unknown');

        $this->assertFalse($exists);
    }

    public function testExistsReturnsFalseForEmptyCollection(): void
    {
        $collection = new RouteCollection;

        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        $checker = new RouteExistenceChecker($router);
        $exists = $checker->exists('any_route');

        $this->assertFalse($exists);
    }
}
