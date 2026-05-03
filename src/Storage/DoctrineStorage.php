<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Storage;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Danilovl\MenuBuilderBundle\DependencyInjection\MenuBuilderConfig;
use Danilovl\MenuBuilderBundle\Entity\MenuItem;
use Danilovl\MenuBuilderBundle\Model\MenuItemInterface;
use Danilovl\MenuBuilderBundle\Repository\MenuItemRepository;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineStorage implements StorageInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MenuItemRepository $repository,
        private readonly MenuBuilderConfig $config
    ) {}

    /**
     * @return array<int, MenuItem>
     */
    public function getTree(string $menuName, bool $onlyActive = true): array
    {
        $items = $this->repository->findAllByMenu($menuName, $onlyActive);
        $roots = array_filter($items, static function (MenuItem $item): bool {
            return $item->getParent() === null;
        });

        return array_values($roots);
    }

    public function findById(int|string $id): ?MenuItemInterface
    {
        $item = $this->repository->find($id);
        if (!$item instanceof MenuItem) {
            return null;
        }
        if ($item->getDeletedAt() !== null) {
            return null;
        }

        return $item;
    }

    /**
     * @return array<int, string>
     */
    public function getMenuNames(): array
    {
        return $this->repository->findMenuNames();
    }

    public function save(MenuItemInterface $item): void
    {
        if (!$item instanceof MenuItem) {
            throw new InvalidArgumentException('DoctrineStorage expects ' . MenuItem::class);
        }

        $this->em->persist($item);
        $this->em->flush();
    }

    public function delete(MenuItemInterface $item): void
    {
        if (!$item instanceof MenuItem) {
            return;
        }

        if ($this->config->softDelete) {
            $deletedAt = new DateTimeImmutable;
            $item->setDeletedAt($deletedAt);
            $this->em->persist($item);
        } else {
            $this->em->remove($item);
        }

        $this->em->flush();
    }

    public function restore(MenuItemInterface $item): void
    {
        if (!$item instanceof MenuItem) {
            return;
        }

        $item->setDeletedAt(null);
        $this->em->persist($item);
        $this->em->flush();
    }

    public function deleteMenu(string $name): void
    {
        if ($this->config->softDelete) {
            $now = new DateTimeImmutable;
            $this->em->createQueryBuilder()
                ->update(MenuItem::class, 'i')
                ->set('i.deletedAt', ':now')
                ->where('i.menuName = :name')
                ->andWhere('i.deletedAt IS NULL')
                ->setParameter('now', $now)
                ->setParameter('name', $name)
                ->getQuery()
                ->execute();
        } else {
            $this->em->createQueryBuilder()
                ->delete(MenuItem::class, 'i')
                ->where('i.menuName = :name')
                ->setParameter('name', $name)
                ->getQuery()
                ->execute();
        }

        $this->em->clear();
    }

    public function renameMenu(string $oldName, string $newName): void
    {
        $this->em->createQueryBuilder()
            ->update(MenuItem::class, 'i')
            ->set('i.menuName', ':new')
            ->where('i.menuName = :old')
            ->setParameter('new', $newName)
            ->setParameter('old', $oldName)
            ->getQuery()
            ->execute();

        $this->em->clear();
    }

    /**
     * @return array<int, MenuItem>
     */
    public function getDeletedItems(string $menuName): array
    {
        return $this->repository->findDeletedByMenu($menuName);
    }

    /**
     * @return array<int, MenuItem>
     */
    public function search(string $term, int $limit = 20): array
    {
        $trimmed = mb_trim($term);
        if ($trimmed === '') {
            return [];
        }

        return $this->repository->search($trimmed, $limit);
    }

    public function setActiveForMenu(string $name, bool $active): void
    {
        $this->em->createQueryBuilder()
            ->update(MenuItem::class, 'i')
            ->set('i.active', ':active')
            ->where('i.menuName = :name')
            ->andWhere('i.deletedAt IS NULL')
            ->setParameter('active', $active)
            ->setParameter('name', $name)
            ->getQuery()
            ->execute();

        $this->em->clear();
    }

    public function isMenuActive(string $name): bool
    {
        $count = (int) $this->em->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(MenuItem::class, 'i')
            ->where('i.menuName = :name')
            ->andWhere('i.active = true')
            ->andWhere('i.deletedAt IS NULL')
            ->setParameter('name', $name)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function move(MenuItemInterface $item, ?MenuItemInterface $newParent, int $newPosition): void
    {
        if (!$item instanceof MenuItem) {
            throw new InvalidArgumentException('DoctrineStorage expects ' . MenuItem::class);
        }
        if ($newParent !== null && !$newParent instanceof MenuItem) {
            throw new InvalidArgumentException('Parent must be ' . MenuItem::class);
        }

        $cursor = $newParent;
        while ($cursor !== null) {
            if ($cursor->getId() === $item->getId()) {
                throw new DomainException('Cannot move item into its own descendant.');
            }

            $cursor = $cursor->getParent();
        }

        $this->repository->reorder($item, $newParent, $newPosition);
    }

    public function getMaxPosition(string $menuName, int|string|null $parentId): int
    {
        $parent = ($parentId !== null && $parentId !== '') ? $this->repository->find($parentId) : null;

        return $this->repository->getMaxPosition($menuName, $parent);
    }
}
