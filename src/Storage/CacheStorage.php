<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Storage;

use DomainException;
use Danilovl\MenuBuilderBundle\DependencyInjection\MenuBuilderConfig;
use Symfony\Contracts\Cache\{
    CacheInterface,
    ItemInterface
};
use Danilovl\MenuBuilderBundle\Model\{
    DetachedMenuItem,
    MenuItemInterface
};

class CacheStorage implements StorageInterface
{
    private const string KEY_TREE_PREFIX = 'tree.';
    private const string KEY_INDEX = 'index';
    private const string KEY_NAMES = 'names';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly MenuBuilderConfig $config
    ) {}

    /**
     * @return array<int, MenuItemInterface>
     */
    public function getTree(string $menuName, bool $onlyActive = true): array
    {
        $tree = $this->loadTree($menuName);
        $tree = $this->sortByPosition($tree);
        if (!$onlyActive) {
            return $tree;
        }

        return $this->filterActive($tree);
    }

    /**
     * @param array<int, DetachedMenuItem> $tree
     * @return array<int, DetachedMenuItem>
     */
    private function sortByPosition(array $tree): array
    {
        $comparator = static function (DetachedMenuItem $a, DetachedMenuItem $b): int {
            return $a->getPosition() <=> $b->getPosition();
        };
        usort($tree, $comparator);

        foreach ($tree as $node) {
            $iterable = $node->getChildren();
            $children = iterator_to_array($iterable);
            $sorted = $this->sortByPosition($children);

            foreach ($children as $child) {
                $node->removeChild($child);
            }
            foreach ($sorted as $child) {
                $node->addChild($child);
            }
        }

        return $tree;
    }

    public function findById(int|string $id): ?MenuItemInterface
    {
        $index = $this->loadIndex();
        $menuName = $index[$id] ?? null;
        if ($menuName !== null) {
            $found = $this->findInTree($this->loadTree($menuName), $id);
            if ($found !== null) {
                return $found;
            }
        }

        foreach ($this->getMenuNames() as $name) {
            if ($name === $menuName) {
                continue;
            }

            $found = $this->findInTree($this->loadTree($name), $id);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function getMenuNames(): array
    {
        return $this->loadNames();
    }

    /**
     * @return array<string, string>
     */
    private function loadIndex(): array
    {
        $raw = $this->readCache(self::KEY_INDEX);
        if (!is_array($raw)) {
            return [];
        }
        $clean = [];
        foreach ($raw as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $clean[$k] = $v;
            }
        }

        return $clean;
    }

    /**
     * @param array<int|string, string> $index
     */
    private function saveIndex(array $index): void
    {
        $key = $this->key(self::KEY_INDEX);
        $this->cache->delete($key);
        $ttl = $this->ttl();
        $this->cache->get($key, static function (ItemInterface $item) use ($index, $ttl): array {
            $item->expiresAfter($ttl);

            return $index;
        });
    }

    /**
     * @return array<int, string>
     */
    private function loadNames(): array
    {
        $raw = $this->readCache(self::KEY_NAMES);
        if (!is_array($raw)) {
            return [];
        }
        $clean = [];
        foreach ($raw as $value) {
            if (is_string($value)) {
                $clean[] = $value;
            }
        }

        return $clean;
    }

    private function readCache(string $suffix): mixed
    {
        $key = $this->key($suffix);
        $ttl = $this->ttl();

        return $this->cache->get($key, static function (ItemInterface $item) use ($ttl): array {
            $item->expiresAfter($ttl);

            return [];
        });
    }

    /**
     * @param array<int, string> $names
     */
    private function saveNames(array $names): void
    {
        $key = $this->key(self::KEY_NAMES);
        $this->cache->delete($key);
        $ttl = $this->ttl();
        $this->cache->get($key, static function (ItemInterface $item) use ($names, $ttl): array {
            $item->expiresAfter($ttl);

            return $names;
        });
    }

    public function save(MenuItemInterface $item): void
    {
        if (!$item instanceof DetachedMenuItem) {
            throw new DomainException('CacheStorage only handles DetachedMenuItem.');
        }

        $menuName = $item->getMenuName();
        $tree = $this->loadTree($menuName);
        $tree = $this->removeFromTree($tree, $item->getId());

        $parent = $item->getParent();
        if ($parent === null) {
            $tree[] = $item;
        } else {
            $parentInTree = $this->findInTree($tree, (string) $parent->getId());
            if ($parentInTree === null) {
                throw new DomainException('Parent not found in tree: ' . (string) $parent->getId());
            }

            $parentInTree->addChild($item);
        }

        $this->saveTree($menuName, $tree);
        $this->refreshIndex($menuName, $tree);
        $this->touchNames($menuName);
    }

    public function delete(MenuItemInterface $item): void
    {
        if (!$item instanceof DetachedMenuItem) {
            throw new DomainException('CacheStorage only handles DetachedMenuItem.');
        }

        $menuName = $item->getMenuName();
        $tree = $this->loadTree($menuName);
        $tree = $this->removeFromTree($tree, $item->getId());

        $this->saveTree($menuName, $tree);
        $this->refreshIndex($menuName, $tree);
    }

    public function restore(MenuItemInterface $item): void
    {
        $this->save($item);
    }

    public function deleteMenu(string $name): void
    {
        $this->cache->delete($this->key(self::KEY_TREE_PREFIX . $name));

        $names = $this->loadNames();
        $filteredNames = array_values(array_filter($names, static function (string $value) use ($name): bool {
            return $value !== $name;
        }));
        $this->saveNames($filteredNames);

        $index = $this->loadIndex();
        foreach ($index as $id => $menuName) {
            if ($menuName === $name) {
                unset($index[$id]);
            }
        }
        $this->saveIndex($index);
    }

    public function renameMenu(string $oldName, string $newName): void
    {
        if ($oldName === $newName) {
            return;
        }

        $tree = $this->loadTree($oldName);
        $this->renameInTree($tree, $newName);

        $existingNew = $this->loadTree($newName);
        $merged = array_merge($existingNew, $tree);

        $this->saveTree($newName, $merged);
        $this->cache->delete($this->key(self::KEY_TREE_PREFIX . $oldName));

        $this->renameInNamesList($oldName, $newName);
        $this->remapIndex($oldName, $newName);
    }

    /**
     * @return array<int, MenuItemInterface>
     */
    public function getDeletedItems(string $menuName): array
    {
        return [];
    }

    /**
     * @return array<int, MenuItemInterface>
     */
    public function search(string $term, int $limit = 20): array
    {
        $needle = mb_strtolower(mb_trim($term));
        if ($needle === '') {
            return [];
        }

        $matches = [];
        foreach ($this->getMenuNames() as $name) {
            if (count($matches) >= $limit) {
                break;
            }
            $tree = $this->loadTree($name);
            $this->collectMatches($tree, $needle, $limit, $matches);
        }

        return $matches;
    }

    /**
     * @param array<int, DetachedMenuItem> $tree
     * @param array<int, MenuItemInterface> $matches
     */
    private function collectMatches(array $tree, string $needle, int $limit, array &$matches): void
    {
        foreach ($tree as $node) {
            if (count($matches) >= $limit) {
                return;
            }
            if ($this->nodeMatches($node, $needle)) {
                $matches[] = $node;
            }
            $children = iterator_to_array($node->getChildren());
            $this->collectMatches($children, $needle, $limit, $matches);
        }
    }

    private function nodeMatches(DetachedMenuItem $node, string $needle): bool
    {
        $haystacks = [
            mb_strtolower($node->getLabel()),
            mb_strtolower((string) $node->getUri()),
            mb_strtolower((string) $node->getRoute()),
        ];
        foreach ($haystacks as $haystack) {
            if ($haystack !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function setActiveForMenu(string $name, bool $active): void
    {
        $tree = $this->loadTree($name);
        $this->setActiveRecursive($tree, $active);
        $this->saveTree($name, $tree);
    }

    public function isMenuActive(string $name): bool
    {
        $tree = $this->loadTree($name);

        return $this->treeHasActive($tree);
    }

    public function move(MenuItemInterface $item, ?MenuItemInterface $newParent, int $newPosition): void
    {
        if (!$item instanceof DetachedMenuItem) {
            throw new DomainException('CacheStorage only handles DetachedMenuItem.');
        }
        if ($newParent !== null && !$newParent instanceof DetachedMenuItem) {
            throw new DomainException('CacheStorage only handles DetachedMenuItem parents.');
        }

        $menuName = $item->getMenuName();
        $itemId = $item->getId();
        $tree = $this->loadTree($menuName);
        $tree = $this->removeFromTree($tree, $itemId);
        $item->setParent(null);

        if ($newParent === null) {
            $tree = $this->insertAtPosition($tree, $item, $newPosition);
            $this->saveTree($menuName, $tree);
            $this->refreshIndex($menuName, $tree);

            return;
        }

        $parentId = $newParent->getId();
        $parentNode = $this->findInTree($tree, $parentId);
        if ($parentNode === null) {
            throw new DomainException('Target parent not found in tree.');
        }

        $iterable = $parentNode->getChildren();
        $children = self::onlyDetached(iterator_to_array($iterable));

        foreach ($children as $child) {
            $parentNode->removeChild($child);
        }

        $children = $this->insertAtPosition($children, $item, $newPosition);

        foreach ($children as $child) {
            $parentNode->addChild($child);
        }

        $this->saveTree($menuName, $tree);
        $this->refreshIndex($menuName, $tree);
    }

    /**
     * @param array<int, DetachedMenuItem> $list
     * @return array<int, DetachedMenuItem>
     */
    private function insertAtPosition(array $list, DetachedMenuItem $item, int $newPosition): array
    {
        $list = array_values($list);
        $count = count($list);
        $idx = min($newPosition, $count);
        if ($idx < 0) {
            $idx = 0;
        }

        $insertion = [$item];
        array_splice($list, $idx, 0, $insertion);

        foreach ($list as $i => $node) {
            $node->setPosition($i);
        }

        return $list;
    }

    /**
     * @param array<int, DetachedMenuItem> $tree
     */
    private function setActiveRecursive(array $tree, bool $active): void
    {
        foreach ($tree as $node) {
            $node->setActive($active);
            $children = iterator_to_array($node->getChildren());
            $this->setActiveRecursive($children, $active);
        }
    }

    /**
     * @param array<int, DetachedMenuItem> $tree
     */
    private function treeHasActive(array $tree): bool
    {
        foreach ($tree as $node) {
            if ($node->isActive()) {
                return true;
            }
            $children = iterator_to_array($node->getChildren());
            if ($this->treeHasActive($children)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, DetachedMenuItem> $tree
     */
    private function renameInTree(array $tree, string $newName): void
    {
        foreach ($tree as $node) {
            $node->setMenuName($newName);
            $children = iterator_to_array($node->getChildren());
            $this->renameInTree($children, $newName);
        }
    }

    private function renameInNamesList(string $oldName, string $newName): void
    {
        $current = $this->loadNames();
        $current = array_values(array_filter($current, static function (string $value) use ($oldName): bool {
            return $value !== $oldName;
        }));
        if (!in_array($newName, $current, true)) {
            $current[] = $newName;
        }

        $this->saveNames($current);
    }

    private function remapIndex(string $oldName, string $newName): void
    {
        $current = $this->loadIndex();
        foreach ($current as $id => $name) {
            if ($name === $oldName) {
                $current[$id] = $newName;
            }
        }
        $this->saveIndex($current);
    }

    private function key(string $suffix): string
    {
        return $this->config->storageKeyPrefix . $suffix;
    }

    private function ttl(): ?int
    {
        $ttl = $this->config->storageTtl;
        if ($ttl === null || $ttl <= 0) {
            return null;
        }

        return $ttl;
    }

    /**
     * @return array<int, DetachedMenuItem>
     */
    private function loadTree(string $menuName): array
    {
        $raw = $this->readCache(self::KEY_TREE_PREFIX . $menuName);
        if (!is_array($raw)) {
            return [];
        }
        $tree = [];
        foreach ($raw as $data) {
            if (!is_array($data) || !self::isStringKeyed($data)) {
                continue;
            }
            $tree[] = DetachedMenuItem::fromArray($data);
        }

        return $tree;
    }

    /**
     * @param array<int|string, mixed> $data
     * @phpstan-assert-if-true array<string, mixed> $data
     */
    private static function isStringKeyed(array $data): bool
    {
        foreach (array_keys($data) as $key) {
            if (!is_string($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<mixed> $values
     * @return array<int, DetachedMenuItem>
     */
    private static function onlyDetached(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            if ($value instanceof DetachedMenuItem) {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array<int, MenuItemInterface> $tree
     */
    private function saveTree(string $menuName, array $tree): void
    {
        $key = $this->key(self::KEY_TREE_PREFIX . $menuName);
        $this->cache->delete($key);
        $ttl = $this->ttl();
        $this->cache->get($key, static function (ItemInterface $item) use ($tree, $ttl): array {
            $item->expiresAfter($ttl);
            $nodes = array_filter($tree, static function (mixed $node): bool {
                return $node instanceof DetachedMenuItem;
            });

            return array_map(static function (DetachedMenuItem $node): array {
                return $node->toArray();
            }, $nodes);
        });
    }

    /**
     * @param array<int, DetachedMenuItem> $tree
     */
    private function findInTree(array $tree, int|string $id): ?DetachedMenuItem
    {
        foreach ($tree as $node) {
            if ((string) $node->getId() === (string) $id) {
                return $node;
            }

            $children = iterator_to_array($node->getChildren());
            $found = $this->findInTree($children, $id);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param array<int, DetachedMenuItem> $tree
     * @return array<int, DetachedMenuItem>
     */
    private function removeFromTree(array $tree, int|string $id): array
    {
        $result = [];
        foreach ($tree as $node) {
            if ((string) $node->getId() === (string) $id) {
                continue;
            }

            $children = iterator_to_array($node->getChildren());
            $filtered = $this->removeFromTree($children, $id);
            foreach ($children as $child) {
                $node->removeChild($child);
            }
            foreach ($filtered as $child) {
                $node->addChild($child);
            }

            $result[] = $node;
        }

        return $result;
    }

    /**
     * @param array<int, MenuItemInterface> $tree
     * @return array<int, MenuItemInterface>
     */
    private function filterActive(array $tree): array
    {
        $active = array_filter($tree, static function (MenuItemInterface $item): bool {
            return $item->isActive() && $item->isDisplayed();
        });

        return array_values($active);
    }

    /**
     * @param array<int, MenuItemInterface> $tree
     */
    private function refreshIndex(string $menuName, array $tree): void
    {
        $current = $this->loadIndex();
        foreach ($current as $id => $name) {
            if ($name === $menuName) {
                unset($current[$id]);
            }
        }

        $this->collectIds($tree, $menuName, $current);
        $this->saveIndex($current);
    }

    /**
     * @param array<int, MenuItemInterface> $nodes
     * @param array<int|string, string> $index
     */
    private function collectIds(array $nodes, string $menuName, array &$index): void
    {
        foreach ($nodes as $node) {
            if (!$node instanceof DetachedMenuItem) {
                continue;
            }

            $index[$node->getId()] = $menuName;
            $children = iterator_to_array($node->getChildren());
            $this->collectIds($children, $menuName, $index);
        }
    }

    private function touchNames(string $menuName): void
    {
        $current = $this->loadNames();
        if (in_array($menuName, $current, true)) {
            return;
        }
        $current[] = $menuName;
        $this->saveNames($current);
    }
}
