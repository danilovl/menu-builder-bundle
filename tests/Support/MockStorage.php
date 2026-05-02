<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Support;

use Danilovl\MenuBuilderBundle\Entity\MenuItem;
use Danilovl\MenuBuilderBundle\Model\MenuItemInterface;
use Danilovl\MenuBuilderBundle\Storage\StorageInterface;
use ReflectionProperty;

final class MockStorage implements StorageInterface
{
    /**
     * @var array<string, array<int, MenuItemInterface>>
     */
    private array $trees = [];

    /**
     * @var array<string, MenuItemInterface>
     */
    private array $byId = [];

    private int $idCounter = 1;

    /**
     * @return array<int, MenuItemInterface>
     */
    public function getTree(string $menuName, bool $onlyActive = true): array
    {
        $tree = $this->trees[$menuName] ?? [];
        $tree = $this->sortByPosition($tree);
        if (!$onlyActive) {
            return $tree;
        }

        $active = array_filter($tree, static function (MenuItemInterface $item): bool {
            return $item->isActive() && $item->isDisplayed();
        });

        return array_values($active);
    }

    /**
     * @param array<int, MenuItemInterface> $tree
     * @return array<int, MenuItemInterface>
     */
    private function sortByPosition(array $tree): array
    {
        $comparator = static function (MenuItemInterface $a, MenuItemInterface $b): int {
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
        return $this->byId[(string) $id] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function getMenuNames(): array
    {
        return array_keys($this->trees);
    }

    public function save(MenuItemInterface $item): void
    {
        if ($item->getId() === null) {
            if ($item instanceof MenuItem) {
                $item->setId($this->idCounter++);
            } else {
                $reflection = new ReflectionProperty($item, 'id');
                $reflection->setAccessible(true);
                $reflection->setValue($item, $this->idCounter++);
            }
        }

        $id = (string) $item->getId();
        $this->byId[$id] = $item;

        if ($item->getParent() !== null) {
            return;
        }

        $name = $item->getMenuName();
        $existing = $this->trees[$name] ?? [];
        if (array_any($existing, static fn ($node) => (string) $node->getId() === $id)) {
            return;
        }

        $this->trees[$name][] = $item;
    }

    public function delete(MenuItemInterface $item): void
    {
        $id = $item->getId();
        if ($id !== null) {
            unset($this->byId[(string) $id]);
        }

        $name = $item->getMenuName();
        if (!isset($this->trees[$name])) {
            return;
        }

        $itemId = $item->getId();
        $filtered = array_filter($this->trees[$name], static function (MenuItemInterface $node) use ($itemId): bool {
            return $node->getId() !== $itemId;
        });
        $this->trees[$name] = array_values($filtered);
    }

    public function restore(MenuItemInterface $item): void
    {
        $this->save($item);
    }

    public function deleteMenu(string $name): void
    {
        if (!isset($this->trees[$name])) {
            return;
        }

        foreach ($this->byId as $id => $item) {
            if ($item->getMenuName() === $name) {
                unset($this->byId[$id]);
            }
        }

        unset($this->trees[$name]);
    }

    public function renameMenu(string $oldName, string $newName): void
    {
        if (!isset($this->trees[$oldName])) {
            return;
        }

        foreach ($this->byId as $item) {
            if ($item->getMenuName() === $oldName) {
                $item->setMenuName($newName);
            }
        }

        $existing = $this->trees[$newName] ?? [];
        $this->trees[$newName] = array_merge($existing, $this->trees[$oldName]);
        unset($this->trees[$oldName]);
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
        foreach ($this->byId as $item) {
            if (count($matches) >= $limit) {
                break;
            }
            if ($this->itemMatches($item, $needle)) {
                $matches[] = $item;
            }
        }

        return $matches;
    }

    private function itemMatches(MenuItemInterface $item, string $needle): bool
    {
        $haystacks = [
            mb_strtolower($item->getLabel()),
            mb_strtolower((string) $item->getUri()),
            mb_strtolower((string) $item->getRoute()),
        ];

        return array_any($haystacks, static fn (string $haystack): bool => $haystack !== '' && str_contains($haystack, $needle));

    }

    public function setActiveForMenu(string $name, bool $active): void
    {
        foreach ($this->byId as $item) {
            if ($item->getMenuName() === $name) {
                $item->setActive($active);
            }
        }
    }

    public function isMenuActive(string $name): bool
    {
        return array_any($this->byId, static fn (MenuItemInterface $item): bool => $item->getMenuName() === $name && $item->isActive());

    }

    public function move(MenuItemInterface $item, ?MenuItemInterface $newParent, int $newPosition): void
    {
        $currentParent = $item->getParent();

        if ($currentParent !== null) {
            $currentParent->removeChild($item);
            $this->renumberChildren($currentParent);
        } else {
            $name = $item->getMenuName();
            if (isset($this->trees[$name])) {
                $itemId = $item->getId();
                $filter = static function (MenuItemInterface $node) use ($itemId): bool {
                    return $node->getId() !== $itemId;
                };
                $filtered = array_filter($this->trees[$name], $filter);
                $this->trees[$name] = array_values($filtered);
                $this->renumberRoots($name);
            }
        }

        $insertion = [$item];

        if ($newParent === null) {
            $item->setParent(null);
            $name = $item->getMenuName();
            $list = $this->trees[$name] ?? [];
            $count = count($list);
            $idx = min($newPosition, $count);
            if ($idx < 0) {
                $idx = 0;
            }

            array_splice($list, $idx, 0, $insertion);
            $this->trees[$name] = $list;
            $this->renumberRoots($name);

            return;
        }

        $iterable = $newParent->getChildren();
        $children = iterator_to_array($iterable);

        foreach ($children as $child) {
            $newParent->removeChild($child);
        }

        $count = count($children);
        $idx = min($newPosition, $count);
        if ($idx < 0) {
            $idx = 0;
        }

        array_splice($children, $idx, 0, $insertion);

        foreach ($children as $i => $child) {
            $child->setPosition($i);
            $newParent->addChild($child);
        }
    }

    private function renumberRoots(string $menuName): void
    {
        if (!isset($this->trees[$menuName])) {
            return;
        }
        foreach ($this->trees[$menuName] as $i => $node) {
            $node->setPosition($i);
        }
    }

    private function renumberChildren(MenuItemInterface $parent): void
    {
        $i = 0;
        foreach ($parent->getChildren() as $child) {
            $child->setPosition($i);
            $i++;
        }
    }
}
