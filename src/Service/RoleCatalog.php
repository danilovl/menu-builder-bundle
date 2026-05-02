<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class RoleCatalog
{
    private const string HIERARCHY_PARAM = 'security.role_hierarchy.roles';
    private const string DEFAULT_ROLE = 'ROLE_USER';

    /**
     * @var string[]|null
     */
    private ?array $cache = null;

    public function __construct(private readonly ParameterBagInterface $parameters) {}

    /**
     * @return string[]
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $roles = [self::DEFAULT_ROLE];

        if ($this->parameters->has(self::HIERARCHY_PARAM)) {
            $hierarchy = $this->parameters->get(self::HIERARCHY_PARAM);
            if (is_array($hierarchy)) {
                $this->collectFromHierarchy($hierarchy, $roles);
            }
        }

        $unique = array_values(array_unique($roles));
        sort($unique);

        $this->cache = $unique;

        return $unique;
    }

    /**
     * @param array<int|string, mixed> $hierarchy
     * @param string[] $roles
     */
    private function collectFromHierarchy(array $hierarchy, array &$roles): void
    {
        foreach ($hierarchy as $parent => $children) {
            if (is_string($parent)) {
                $roles[] = $parent;
            }
            if (!is_array($children)) {
                continue;
            }
            foreach ($children as $child) {
                if (is_string($child)) {
                    $roles[] = $child;
                }
            }
        }
    }
}
