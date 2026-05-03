<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Storage;

use Danilovl\MenuBuilderBundle\Model\MenuItemInterface;

interface StorageInterface
{
    /**
     * @return array<int, MenuItemInterface>
     */
    public function getTree(string $menuName, bool $onlyActive = true): array;

    public function findById(int|string $id): ?MenuItemInterface;

    /**
     * @return array<int, string>
     */
    public function getMenuNames(): array;

    public function save(MenuItemInterface $item): void;

    public function delete(MenuItemInterface $item): void;

    public function restore(MenuItemInterface $item): void;

    public function move(MenuItemInterface $item, ?MenuItemInterface $newParent, int $newPosition): void;

    public function renameMenu(string $oldName, string $newName): void;

    public function deleteMenu(string $name): void;

    public function setActiveForMenu(string $name, bool $active): void;

    public function isMenuActive(string $name): bool;

    /**
     * @return array<int, MenuItemInterface>
     */
    public function search(string $term, int $limit = 20): array;

    /**
     * @return array<int, MenuItemInterface>
     */
    public function getDeletedItems(string $menuName): array;

    public function getMaxPosition(string $menuName, int|string|null $parentId): int;
}
