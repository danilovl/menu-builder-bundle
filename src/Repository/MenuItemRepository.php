<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Repository;

use Danilovl\MenuBuilderBundle\Entity\MenuItem;
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

    public function reorder(MenuItem $item, ?MenuItem $newParent, int $newPosition): void
    {
        $em = $this->getEntityManager();
        $rawOldParent = $item->getParent();

        if ($rawOldParent instanceof MenuItem) {
            $oldParent = $rawOldParent;
        } else {
            $oldParent = null;
        }

        $oldPosition = $item->getPosition();
        $itemId = (string) $item->getId();
        $menuName = $item->getMenuName();
        $sameParent = $this->isSameParent($oldParent, $newParent);

        if ($sameParent && $oldPosition === $newPosition) {
            $em->persist($item);
            $em->flush();

            return;
        }

        if (!$sameParent) {
            $oldShiftFrom = $oldPosition + 1;
            $this->shiftSiblings($menuName, $oldParent, $itemId, $oldShiftFrom, null, -1);
            $this->shiftSiblings($menuName, $newParent, $itemId, $newPosition, null, 1);
        } elseif ($oldPosition < $newPosition) {
            $shiftFrom = $oldPosition + 1;
            $this->shiftSiblings($menuName, $newParent, $itemId, $shiftFrom, $newPosition, -1);
        } else {
            $shiftTo = $oldPosition - 1;
            $this->shiftSiblings($menuName, $newParent, $itemId, $newPosition, $shiftTo, 1);
        }

        $item->setParent($newParent);
        $item->setPosition($newPosition);
        $em->persist($item);
        $em->flush();
    }

    private function isSameParent(?MenuItem $a, ?MenuItem $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }
        if ($a === null || $b === null) {
            return false;
        }

        return $a->getId() === $b->getId();
    }

    private function shiftSiblings(
        string $menuName,
        ?MenuItem $parent,
        string $excludeId,
        int $rangeFrom,
        ?int $rangeTo,
        int $delta,
    ): void {
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
            ->andWhere('i.id != :id')
            ->andWhere('i.position >= :from')
            ->setParameter('name', $menuName)
            ->setParameter('id', $excludeId)
            ->setParameter('from', $rangeFrom);

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
}
