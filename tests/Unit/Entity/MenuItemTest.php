<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Entity;

use DateTimeImmutable;
use Danilovl\MenuBuilderBundle\Entity\MenuItem;
use PHPUnit\Framework\TestCase;

final class MenuItemTest extends TestCase
{
    public function testGetChildrenSkipsSoftDeletedChildren(): void
    {
        $parent = new MenuItem('main', 'Parent');
        $deleted = new MenuItem('main', 'Deleted');
        $kept = new MenuItem('main', 'Kept');
        $deletedAt = new DateTimeImmutable;

        $parent->addChild($deleted);
        $parent->addChild($kept);
        $deleted->setDeletedAt($deletedAt);

        $children = $parent->getChildren();
        $keys = $children->getKeys();
        $first = $children->first();
        $hasChildren = $parent->hasChildren();

        $this->assertCount(1, $children);
        $this->assertSame([0], $keys);
        $this->assertSame($kept, $first);
        $this->assertTrue($hasChildren);
    }

    public function testHasChildrenIsFalseWhenAllChildrenAreSoftDeleted(): void
    {
        $parent = new MenuItem('main', 'Parent');
        $child = new MenuItem('main', 'Child');
        $deletedAt = new DateTimeImmutable;

        $parent->addChild($child);
        $child->setDeletedAt($deletedAt);

        $hasChildren = $parent->hasChildren();
        $children = $parent->getChildren();

        $this->assertFalse($hasChildren);
        $this->assertCount(0, $children);
    }

    public function testRestoredChildIsVisibleAgain(): void
    {
        $parent = new MenuItem('main', 'Parent');
        $child = new MenuItem('main', 'Child');
        $deletedAt = new DateTimeImmutable;

        $parent->addChild($child);
        $child->setDeletedAt($deletedAt);
        $child->setDeletedAt(null);

        $hasChildren = $parent->hasChildren();

        $this->assertTrue($hasChildren);
    }
}
