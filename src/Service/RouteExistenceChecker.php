<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Service;

use Symfony\Component\Routing\RouterInterface;

class RouteExistenceChecker
{
    public function __construct(private readonly RouterInterface $router) {}

    public function exists(string $routeName): bool
    {
        $collection = $this->router->getRouteCollection();

        return $collection->get($routeName) !== null;
    }
}
