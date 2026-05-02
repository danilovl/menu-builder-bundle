<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Service;

use Danilovl\MenuBuilderBundle\Attribute\MenuItem as MenuItemAttribute;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Routing\RouterInterface;

readonly class MenuAttributeScanner
{
    public function __construct(private RouterInterface $router) {}

    /**
     * @return array<int, array{menu: string, attribute: MenuItemAttribute, route: ?string}>
     */
    public function scan(): array
    {
        $found = [];
        $collection = $this->router->getRouteCollection();

        foreach ($collection->all() as $routeName => $route) {
            $controller = $route->getDefault('_controller');
            if (!is_string($controller) || !str_contains($controller, '::')) {
                continue;
            }

            [$class, $method] = explode('::', $controller, 2);
            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if (!$reflection->hasMethod($method)) {
                continue;
            }

            $methodReflection = $reflection->getMethod($method);
            $attributes = $this->collectAttributes($methodReflection);

            foreach ($attributes as $attribute) {
                $effectiveRoute = $attribute->route ?? $routeName;
                $found[] = [
                    'menu' => $attribute->menu,
                    'attribute' => $attribute,
                    'route' => $effectiveRoute,
                ];
            }
        }

        return $found;
    }

    /**
     * @return array<int, MenuItemAttribute>
     */
    private function collectAttributes(ReflectionMethod $method): array
    {
        $attributes = $method->getAttributes(MenuItemAttribute::class);
        $result = [];
        foreach ($attributes as $attribute) {
            $result[] = $attribute->newInstance();
        }

        return $result;
    }
}
