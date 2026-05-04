<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Repository;

use Danilovl\MenuBuilderBundle\Entity\MenuItem;
use Danilovl\MenuBuilderBundle\Model\MenuItemInterface;
use Doctrine\Common\Collections\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuItem>
 */
class MenuItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuItem::class);
    }

    /**
     * @return array<int, MenuItem>
     */
    public function findRootsByMenu(string $menuName, bool $onlyActive = true): array
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.menuName = :name')
            ->andWhere('i.parent IS NULL')
            ->andWhere('i.deletedAt IS NULL')
            ->setParameter('name', $menuName)
            ->orderBy('i.position', Order::Ascending->value);

        if ($onlyActive) {
            $qb->andWhere('i.active = true')
                ->andWhere('i.isDisplayed = true');
        }

        return self::filterMenuItems($qb->getQuery()->getResult());
    }

    /**
     * @return array<int, MenuItem>
     */
    public function findAllByMenu(string $menuName, bool $onlyActive = true): array
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.menuName = :name')
            ->andWhere('i.deletedAt IS NULL')
            ->setParameter('name', $menuName)
            ->orderBy('i.position', Order::Ascending->value);

        if ($onlyActive) {
            $qb->andWhere('i.active = true')
                ->andWhere('i.isDisplayed = true');
        }

        return self::filterMenuItems($qb->getQuery()->getResult());
    }

    /**
     * @return array<int, MenuItem>
     */
    public function findDeletedByMenu(string $menuName): array
    {
        $result = $this->createQueryBuilder('i')
            ->where('i.menuName = :name')
            ->andWhere('i.deletedAt IS NOT NULL')
            ->setParameter('name', $menuName)
            ->orderBy('i.deletedAt', Order::Descending->value)
            ->getQuery()
            ->getResult();

        return self::filterMenuItems($result);
    }

    /**
     * @return array<int, MenuItem>
     */
    public function search(string $term, int $limit): array
    {
        $like = '%' . $term . '%';

        $result = $this->createQueryBuilder('i')
            ->where('i.deletedAt IS NULL')
            ->andWhere('LOWER(i.label) LIKE LOWER(:term) OR LOWER(i.uri) LIKE LOWER(:term) OR LOWER(i.route) LIKE LOWER(:term)')
            ->setParameter('term', $like)
            ->orderBy('i.menuName', Order::Ascending->value)
            ->addOrderBy('i.position', Order::Ascending->value)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return self::filterMenuItems($result);
    }

    /**
     * @return array<int, MenuItem>
     */
    private static function filterMenuItems(mixed $rows): array
    {
        if (!is_iterable($rows)) {
            return [];
        }
        $items = [];
        foreach ($rows as $row) {
            if ($row instanceof MenuItem) {
                $items[] = $row;
            }
        }

        return $items;
    }

    /**
     * @return array<int, string>
     */
    public function findMenuNames(): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('DISTINCT i.menuName')
            ->where('i.deletedAt IS NULL')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'menuName');
    }

    /**
     * @return array<int, MenuItem>
     */
    public function findByParent(string $menuName, ?MenuItemInterface $parent): array
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.menuName = :name')
            ->andWhere('i.deletedAt IS NULL')
            ->setParameter('name', $menuName)
            ->orderBy('i.position', Order::Ascending->value);

        if ($parent === null) {
            $qb->andWhere('i.parent IS NULL');
        } else {
            $qb->andWhere('i.parent = :parent')
                ->setParameter('parent', $parent);
        }

        return self::filterMenuItems($qb->getQuery()->getResult());
    }

    public function reorder(MenuItem $item, ?MenuItem $newParent, int $newPosition): void
    {
        $em = $this->getEntityManager();
        $oldParent = $item->getParent();
        $menuName = $item->getMenuName();

        if ($this->isSameParent($oldParent, $newParent) && $item->getPosition() === $newPosition) {
            return;
        }

        if (!$this->isSameParent($oldParent, $newParent)) {
            $oldSiblings = $this->findByParent($menuName, $oldParent);
            $pos = 0;
            foreach ($oldSiblings as $sibling) {
                if ($sibling->getId() === $item->getId()) {
                    continue;
                }
                $sibling->setPosition($pos++);
            }
        }

        $item->setParent($newParent);
        $newSiblings = $this->findByParent($menuName, $newParent);
        $newSiblings = array_filter($newSiblings, static fn (MenuItem $i): bool => $i->getId() !== $item->getId());
        $newSiblings = array_values($newSiblings);

        array_splice($newSiblings, $newPosition, 0, [$item]);

        foreach ($newSiblings as $pos => $sibling) {
            $sibling->setPosition($pos);
        }

        $em->flush();
    }
    
    public function renumber(string $menuName, ?MenuItemInterface $parent): void
    {
        $siblings = $this->findByParent($menuName, $parent);
        foreach ($siblings as $pos => $sibling) {
            $sibling->setPosition($pos);
        }
        $this->getEntityManager()->flush();
    }

    private function isSameParent(?MenuItemInterface $a, ?MenuItemInterface $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }
        if ($a === null || $b === null) {
            return false;
        }

        return $a->getId() === $b->getId();
    }

    public function shiftSiblings(
        string $menuName,
        ?MenuItemInterface $parent,
        int|string|null $excludeId,
        int $rangeFrom,
        ?int $rangeTo,
        int $delta,
    ): void {
        if ($delta === 0) {
            return;
        }

        if ($delta >= 0) {
            $sign = '+';
        } else {
            $sign = '-';
        }
        $magnitude = abs($delta);
        $expression = sprintf('i.position %s %d', $sign, $magnitude);

        $qb = $this->createQueryBuilder('i')
            ->update()
            ->set('i.position', $expression)
            ->where('i.menuName = :name')
            ->andWhere('i.deletedAt IS NULL')
            ->andWhere('i.position >= :from')
            ->setParameter('name', $menuName)
            ->setParameter('from', $rangeFrom);

        if ($excludeId !== null) {
            $qb->andWhere('i.id != :id')
                ->setParameter('id', $excludeId);
        }

        if ($rangeTo !== null) {
            $qb->andWhere('i.position <= :to')
                ->setParameter('to', $rangeTo);
        }

        if ($parent === null) {
            $qb->andWhere('i.parent IS NULL');
        } else {
            $qb->andWhere('i.parent = :parent')
                ->setParameter('parent', $parent);
        }

        $qb->getQuery()->execute();
    }

    public function getMaxPosition(string $menuName, ?MenuItem $parent): int
    {
        $qb = $this->createQueryBuilder('i')
            ->select('MAX(i.position)')
            ->where('i.menuName = :name')
            ->andWhere('i.deletedAt IS NULL')
            ->setParameter('name', $menuName);

        if ($parent === null) {
            $qb->andWhere('i.parent IS NULL');
        } else {
            $qb->andWhere('i.parent = :parent')
                ->setParameter('parent', $parent);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return $result === null ? -1 : (int) $result;
    }
}
