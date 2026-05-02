<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Builder;

use Danilovl\MenuBuilderBundle\Model\{
    DetachedMenuItem,
    MenuItemInterface
};
use Danilovl\MenuBuilderBundle\Security\MenuPermissionChecker;
use Danilovl\MenuBuilderBundle\Storage\StorageInterface;

class MenuBuilder
{
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly MenuPermissionChecker $permissions
    ) {}

    /**
     * @return array<int, MenuItemInterface>
     */
    public function build(string $menuName): array
    {
        $tree = $this->storage->getTree($menuName);

        return $this->filterRecursive($tree);
    }

    /**
     * @param array<int, MenuItemInterface> $items
     * @return array<int, MenuItemInterface>
     */
    private function filterRecursive(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (!$item->isActive() || !$item->isDisplayed() || !$this->permissions->canSee($item)) {
                continue;
            }

            $detached = DetachedMenuItem::fromItem($item);
            $children = iterator_to_array($item->getChildren());
            $filteredChildren = $this->filterRecursive($children);

            foreach ($filteredChildren as $child) {
                $detached->addChild($child);
            }

            $result[] = $detached;
        }

        return $result;
    }
}
