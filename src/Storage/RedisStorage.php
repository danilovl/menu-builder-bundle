<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Storage;

use DomainException;
use Redis;
use Danilovl\MenuBuilderBundle\DependencyInjection\MenuBuilderConfig;
use Danilovl\MenuBuilderBundle\Model\{
    DetachedMenuItem,
    MenuItemInterface
};

class RedisStorage implements StorageInterface
{
    public function __construct(
        private readonly Redis $redis,
        private readonly MenuBuilderConfig $config
    ) {}

    /**
     * @return array<int, MenuItemInterface>
     */
    public function getTree(string $menuName, bool $onlyActive = true): array
    {
        $raw = $this->redis->get($this->key('tree:' . $menuName));
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }
        $tree = [];
        foreach ($data as $entry) {
            if (!is_array($entry) || !self::isStringKeyed($entry)) {
                continue;
            }
            $tree[] = DetachedMenuItem::fromArray($entry);
        }
        $tree = $this->sortByPosition($tree);

        if (!$onlyActive) {
            return $tree;
        }

        return $this->filterActive($tree);
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
        $menuName = $this->redis->hGet($this->key('index'), (string) $id);
        if (is_string($menuName) && $menuName !== '') {
            $found = $this->findInTree($this->getTree($menuName, false), $id);
            if ($found !== null) {
                return $found;
            }
        }

        foreach ($this->getMenuNames() as $name) {
            if ($name === $menuName) {
                continue;
            }

            $found = $this->findInTree($this->getTree($name, false), $id);
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
        $members = $this->redis->sMembers($this->key('names'));
        if (!is_array($members)) {
            return [];
        }

        return $members;
    }

    public function save(MenuItemInterface $item): void
    {
        if (!$item instanceof DetachedMenuItem) {
            throw new DomainException('RedisStorage only handles ' . DetachedMenuItem::class);
        }

        $menuName = $item->getMenuName();
        $tree = $this->getTree($menuName, false);
        $tree = $this->removeFromTree($tree, $item->getId());

        $parent = $item->getParent();
        if ($parent === null) {
            $tree[] = $item;
        } else {
            $parentId = (string) $parent->getId();
            $parentInTree = $this->findInTree($tree, $parentId);
            if ($parentInTree === null) {
                throw new DomainException('Parent not found in tree: ' . $parentId);
            }

            $parentInTree->addChild($item);
        }

        $this->persistTree($menuName, $tree);
        $this->refreshIndex($menuName, $tree);
        $this->redis->sAdd($this->key('names'), $menuName);
    }

    public function delete(MenuItemInterface $item): void
    {
        if (!$item instanceof DetachedMenuItem) {
            throw new DomainException('RedisStorage only handles ' . DetachedMenuItem::class);
        }

        $menuName = $item->getMenuName();
        $tree = $this->getTree($menuName, false);
        $tree = $this->removeFromTree($tree, $item->getId());
        $this->persistTree($menuName, $tree);
        $this->refreshIndex($menuName, $tree);
    }

    public function restore(MenuItemInterface $item): void
    {
        $this->save($item);
    }

    public function deleteMenu(string $name): void
    {
        $this->redis->del($this->key('tree:' . $name));
        $this->redis->sRem($this->key('names'), $name);

        $indexKey = $this->key('index');
        $existing = $this->redis->hGetAll($indexKey);
        if (!is_array($existing)) {
            return;
        }

        foreach ($existing as $id => $menuName) {
            if ($menuName === $name) {
                $this->redis->hDel($indexKey, $id);
            }
        }
    }

    public function renameMenu(string $oldName, string $newName): void
    {
        if ($oldName === $newName) {
            return;
        }

        $tree = $this->getTree($oldName, false);
        $this->renameInTree($tree, $newName);

        $existingNew = $this->getTree($newName, false);
        $merged = array_merge($existingNew, $tree);

        $this->persistTree($newName, $merged);
        $this->redis->del($this->key('tree:' . $oldName));

        $this->redis->sRem($this->key('names'), $oldName);
        $this->redis->sAdd($this->key('names'), $newName);

        $indexKey = $this->key('index');
        $existing = $this->redis->hGetAll($indexKey);
        if (!is_array($existing)) {
            return;
        }

        foreach ($existing as $id => $name) {
            if ($name === $oldName) {
                $this->redis->hSet($indexKey, $id, $newName);
            }
        }
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
            $tree = $this->getTree($name, false);
            $this->collectMatches($tree, $needle, $limit, $matches);
        }

        return $matches;
    }

    /**
     * @param array<int, MenuItemInterface> $tree
     * @param array<int, MenuItemInterface> $matches
     */
    private function collectMatches(array $tree, string $needle, int $limit, array &$matches): void
    {
        foreach ($tree as $node) {
            if (!$node instanceof DetachedMenuItem) {
                continue;
            }
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
        $tree = $this->getTree($name, false);
        $this->setActiveRecursive($tree, $active);
        $this->persistTree($name, $tree);
    }

    public function isMenuActive(string $name): bool
    {
        $tree = $this->getTree($name, false);

        return $this->treeHasActive($tree);
    }

    public function move(MenuItemInterface $item, ?MenuItemInterface $newParent, int $newPosition): void
    {
        if (!$item instanceof DetachedMenuItem) {
            throw new DomainException('RedisStorage expects ' . DetachedMenuItem::class);
        }

        $menuName = $item->getMenuName();
        $itemId = (string) $item->getId();
        $tree = $this->getTree($menuName, false);
        $tree = $this->removeFromTree($tree, $itemId);
        $item->setParent(null);

        if ($newParent === null) {
            $tree = $this->insertAtPosition($tree, $item, $newPosition);
            $this->persistTree($menuName, $tree);
            $this->refreshIndex($menuName, $tree);

            return;
        }

        $parentId = (string) $newParent->getId();
        $parentNode = $this->findInTree($tree, $parentId);
        if ($parentNode === null) {
            throw new DomainException('Target parent not found.');
        }

        $iterable = $parentNode->getChildren();
        $children = iterator_to_array($iterable);

        foreach ($children as $child) {
            $parentNode->removeChild($child);
        }

        $children = $this->insertAtPosition($children, $item, $newPosition);

        foreach ($children as $child) {
            $parentNode->addChild($child);
        }

        $this->persistTree($menuName, $tree);
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
     * @param array<int, MenuItemInterface> $tree
     */
    private function setActiveRecursive(array $tree, bool $active): void
    {
        foreach ($tree as $node) {
            if (!$node instanceof DetachedMenuItem) {
                continue;
            }

            $node->setActive($active);
            $children = iterator_to_array($node->getChildren());
            $this->setActiveRecursive($children, $active);
        }
    }

    /**
     * @param array<int, MenuItemInterface> $tree
     */
    private function treeHasActive(array $tree): bool
    {
        foreach ($tree as $node) {
            if (!$node instanceof DetachedMenuItem) {
                continue;
            }

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
     * @param array<int, MenuItemInterface> $tree
     */
    private function renameInTree(array $tree, string $newName): void
    {
        foreach ($tree as $node) {
            if (!$node instanceof DetachedMenuItem) {
                continue;
            }

            $node->setMenuName($newName);
            $children = iterator_to_array($node->getChildren());
            $this->renameInTree($children, $newName);
        }
    }

    private function key(string $suffix): string
    {
        return $this->config->storageKeyPrefix . $suffix;
    }

    /**
     * @param array<int, MenuItemInterface> $tree
     */
    private function persistTree(string $menuName, array $tree): void
    {
        $detached = array_filter($tree, static function (mixed $node): bool {
            return $node instanceof DetachedMenuItem;
        });
        $data = array_map(static function (DetachedMenuItem $node): array {
            return $node->toArray();
        }, $detached);

        $key = $this->key('tree:' . $menuName);
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $this->redis->set($key, $json);

        $ttl = $this->config->storageTtl;
        if ($ttl !== null && $ttl > 0) {
            $this->redis->expire($key, $ttl);
        }
    }

    /**
     * @param array<int, MenuItemInterface> $tree
     */
    private function findInTree(array $tree, int|string $id): ?DetachedMenuItem
    {
        foreach ($tree as $node) {
            if (!$node instanceof DetachedMenuItem) {
                continue;
            }

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
     * @param array<int, MenuItemInterface> $tree
     * @return array<int, DetachedMenuItem>
     */
    private function removeFromTree(array $tree, int|string $id): array
    {
        $result = [];
        foreach ($tree as $node) {
            if (!$node instanceof DetachedMenuItem) {
                continue;
            }
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
     */
    private function refreshIndex(string $menuName, array $tree): void
    {
        $key = $this->key('index');
        $existing = $this->redis->hGetAll($key);
        if (!is_array($existing)) {
            $existing = [];
        }

        foreach ($existing as $id => $name) {
            if ($name === $menuName) {
                $this->redis->hDel($key, $id);
            }
        }

        $ids = [];
        $this->collectIds($tree, $menuName, $ids);
        foreach ($ids as $id => $name) {
            $this->redis->hSet($key, (string) $id, $name);
        }
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
}
