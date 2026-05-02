<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Service;

use Danilovl\MenuBuilderBundle\Dto\RouteInfo;
use Symfony\Component\Routing\RouterInterface;

class RouteCatalog
{
    /**
     * @var RouteInfo[]|null
     */
    private ?array $cache = null;

    public function __construct(private readonly RouterInterface $router) {}

    /**
     * @return RouteInfo[]
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $collection = $this->router->getRouteCollection();
        $items = [];
        foreach ($collection->all() as $name => $route) {
            $items[] = new RouteInfo(
                name: (string) $name,
                path: $route->getPath(),
                methods: $route->getMethods(),
            );
        }

        $this->cache = $items;

        return $items;
    }

    public function count(): int
    {
        return count($this->all());
    }

    /**
     * @param RouteInfo[] $items
     * @return RouteInfo[]
     */
    public function filter(array $items, string $term): array
    {
        $needle = mb_strtolower(mb_trim($term));
        if ($needle === '') {
            return $items;
        }

        $matcher = static function (RouteInfo $info) use ($needle): bool {
            $haystack = mb_strtolower($info->name . ' ' . $info->path);

            return str_contains($haystack, $needle);
        };

        return array_values(array_filter($items, $matcher));
    }
}
