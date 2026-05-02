<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Matcher\Voter;

use Danilovl\MenuBuilderBundle\Model\MenuItemInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class RouteVoter implements VoterInterface
{
    public function __construct(private readonly RequestStack $requestStack) {}

    public function matchItem(MenuItemInterface $item): ?bool
    {
        $itemRoute = $item->getRoute();
        $dependents = $item->getDependentActiveRoutes();
        if (($itemRoute === null || $itemRoute === '') && $dependents === []) {
            return null;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return null;
        }

        $currentRoute = $request->attributes->get('_route');
        if (!is_string($currentRoute) || $currentRoute === '') {
            return null;
        }

        if ($itemRoute === $currentRoute) {
            return $this->paramsMatch($item, $request->attributes->get('_route_params'));
        }

        return in_array($currentRoute, $dependents, true);
    }

    private function paramsMatch(MenuItemInterface $item, mixed $currentParams): bool
    {
        $itemParams = $item->getRouteParams();
        if ($itemParams === []) {
            return true;
        }
        if (!is_array($currentParams)) {
            return false;
        }

        foreach ($itemParams as $key => $value) {
            if (!array_key_exists($key, $currentParams)) {
                return false;
            }
            if (!self::stringifyScalar($currentParams[$key], $currentValue)) {
                return false;
            }
            if (!self::stringifyScalar($value, $itemValue)) {
                return false;
            }
            if ($currentValue !== $itemValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * @phpstan-assert-if-true string $out
     */
    private static function stringifyScalar(mixed $value, ?string &$out): bool
    {
        if (is_string($value)) {
            $out = $value;

            return true;
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            $out = (string) $value;

            return true;
        }
        $out = null;

        return false;
    }
}
