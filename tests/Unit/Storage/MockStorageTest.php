<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Storage;

use Danilovl\MenuBuilderBundle\Model\DetachedMenuItem;
use Danilovl\MenuBuilderBundle\Tests\Support\MockStorage;
use PHPUnit\Framework\TestCase;

final class MockStorageTest extends TestCase
{
    public function testSaveRootItemAddsToTreeAndIndex(): void
    {
        $storage = new MockStorage;
        $item = DetachedMenuItem::create('main', 'Home');

        $storage->save($item);

        $found = $storage->findById($item->getId());
        $tree = $storage->getTree('main', false);

        $this->assertSame($item, $found);
        $this->assertCount(1, $tree);
    }

    public function testSaveChildItemSkipsTreeAddButIndexes(): void
    {
        $storage = new MockStorage;
        $parent = DetachedMenuItem::create('main', 'Parent');
        $child = DetachedMenuItem::create('main', 'Child');
        $parent->addChild($child);

        $storage->save($parent);
        $storage->save($child);

        $rootTree = $storage->getTree('main', false);
        $foundChild = $storage->findById($child->getId());

        $this->assertCount(1, $rootTree);
        $this->assertSame($child, $foundChild);
    }

    public function testSaveIgnoresDuplicateInTreeBucket(): void
    {
        $storage = new MockStorage;
        $item = DetachedMenuItem::create('main', 'Home');

        $storage->save($item);
        $storage->save($item);

        $tree = $storage->getTree('main', false);

        $this->assertCount(1, $tree);
    }

    public function testFindByIdReturnsNullWhenMissing(): void
    {
        $storage = new MockStorage;

        $found = $storage->findById('non-existent');

        $this->assertNull($found);
    }

    public function testGetMenuNamesReturnsAllStoredMenus(): void
    {
        $storage = new MockStorage;
        $home = DetachedMenuItem::create('main', 'Home');
        $blog = DetachedMenuItem::create('footer', 'Blog');

        $storage->save($home);
        $storage->save($blog);

        $names = $storage->getMenuNames();

        sort($names);
        $this->assertSame(['footer', 'main'], $names);
    }

    public function testGetTreeOnlyActiveFiltersInactiveAndHidden(): void
    {
        $storage = new MockStorage;
        $visible = DetachedMenuItem::create('main', 'Visible');
        $inactive = DetachedMenuItem::create('main', 'Inactive');
        $inactive->setActive(false);
        $hidden = DetachedMenuItem::create('main', 'Hidden');
        $hidden->setIsDisplayed(false);

        $storage->save($visible);
        $storage->save($inactive);
        $storage->save($hidden);

        $active = $storage->getTree('main');
        $all = $storage->getTree('main', false);

        $this->assertCount(1, $active);
        $this->assertCount(3, $all);
    }

    public function testGetTreeSortsRootByPosition(): void
    {
        $storage = new MockStorage;
        $first = DetachedMenuItem::create('main', 'First');
        $first->setPosition(2);
        $second = DetachedMenuItem::create('main', 'Second');
        $second->setPosition(0);
        $third = DetachedMenuItem::create('main', 'Third');
        $third->setPosition(1);

        $storage->save($first);
        $storage->save($second);
        $storage->save($third);

        $tree = $storage->getTree('main', false);

        $labels = [
            $tree[0]->getLabel(),
            $tree[1]->getLabel(),
            $tree[2]->getLabel(),
        ];
        $this->assertSame(['Second', 'Third', 'First'], $labels);
    }

    public function testDeleteRemovesFromIndexAndTree(): void
    {
        $storage = new MockStorage;
        $item = DetachedMenuItem::create('main', 'Home');
        $storage->save($item);

        $storage->delete($item);

        $found = $storage->findById($item->getId());
        $tree = $storage->getTree('main', false);

        $this->assertNull($found);
        $this->assertSame([], $tree);
    }

    public function testRestoreReAddsItem(): void
    {
        $storage = new MockStorage;
        $item = DetachedMenuItem::create('main', 'Home');
        $storage->save($item);
        $storage->delete($item);

        $storage->restore($item);

        $found = $storage->findById($item->getId());

        $this->assertSame($item, $found);
    }

    public function testDeleteMenuRemovesAllItemsForMenu(): void
    {
        $storage = new MockStorage;
        $home = DetachedMenuItem::create('main', 'Home');
        $about = DetachedMenuItem::create('main', 'About');
        $blog = DetachedMenuItem::create('footer', 'Blog');

        $storage->save($home);
        $storage->save($about);
        $storage->save($blog);
        $storage->deleteMenu('main');

        $remainingNames = $storage->getMenuNames();
        $homeFound = $storage->findById($home->getId());
        $blogFound = $storage->findById($blog->getId());

        $this->assertSame(['footer'], $remainingNames);
        $this->assertNull($homeFound);
        $this->assertSame($blog, $blogFound);
    }

    public function testRenameMenuPreservesItemsUnderNewName(): void
    {
        $storage = new MockStorage;
        $home = DetachedMenuItem::create('main', 'Home');
        $storage->save($home);

        $storage->renameMenu('main', 'primary');

        $oldTree = $storage->getTree('main', false);
        $newTree = $storage->getTree('primary', false);
        $renamedItem = $storage->findById($home->getId());

        $this->assertSame([], $oldTree);
        $this->assertCount(1, $newTree);
        $this->assertSame('primary', $renamedItem?->getMenuName());
    }

    public function testSearchMatchesByLabelUriAndRoute(): void
    {
        $storage = new MockStorage;
        $home = DetachedMenuItem::create('main', 'Home page');
        $home->setUri('/home');
        $about = DetachedMenuItem::create('main', 'About');
        $about->setUri('/about');
        $about->setRoute('app_about_show');

        $storage->save($home);
        $storage->save($about);

        $byLabel = $storage->search('home');
        $byUri = $storage->search('/about');
        $byRoute = $storage->search('app_about');

        $this->assertCount(1, $byLabel);
        $this->assertCount(1, $byUri);
        $this->assertCount(1, $byRoute);
    }

    public function testSearchEmptyTermReturnsEmptyResult(): void
    {
        $storage = new MockStorage;
        $home = DetachedMenuItem::create('main', 'Home');
        $storage->save($home);

        $result = $storage->search('   ');

        $this->assertSame([], $result);
    }

    public function testSearchRespectsLimit(): void
    {
        $storage = new MockStorage;
        for ($i = 0; $i < 5; $i++) {
            $item = DetachedMenuItem::create('main', 'Match' . $i);
            $storage->save($item);
        }

        $matches = $storage->search('match', 3);

        $this->assertCount(3, $matches);
    }

    public function testSetActiveForMenuTogglesAllItems(): void
    {
        $storage = new MockStorage;
        $home = DetachedMenuItem::create('main', 'Home');
        $about = DetachedMenuItem::create('main', 'About');
        $other = DetachedMenuItem::create('footer', 'Footer');
        $storage->save($home);
        $storage->save($about);
        $storage->save($other);

        $storage->setActiveForMenu('main', false);

        $isMainActive = $storage->isMenuActive('main');
        $isFooterActive = $storage->isMenuActive('footer');

        $this->assertFalse($isMainActive);
        $this->assertTrue($isFooterActive);
    }

    public function testIsMenuActiveTrueWhenAtLeastOneItemActive(): void
    {
        $storage = new MockStorage;
        $a = DetachedMenuItem::create('main', 'A');
        $a->setActive(false);
        $b = DetachedMenuItem::create('main', 'B');

        $storage->save($a);
        $storage->save($b);

        $isActive = $storage->isMenuActive('main');

        $this->assertTrue($isActive);
    }

    public function testMoveAtRootRenumbersPositions(): void
    {
        $storage = new MockStorage;
        $a = DetachedMenuItem::create('main', 'A');
        $b = DetachedMenuItem::create('main', 'B');
        $c = DetachedMenuItem::create('main', 'C');
        $storage->save($a);
        $storage->save($b);
        $storage->save($c);

        $storage->move($c, null, 0);
        $tree = $storage->getTree('main', false);

        $orderedLabels = [
            $tree[0]->getLabel(),
            $tree[1]->getLabel(),
            $tree[2]->getLabel(),
        ];
        $orderedPositions = [
            $tree[0]->getPosition(),
            $tree[1]->getPosition(),
            $tree[2]->getPosition(),
        ];

        $this->assertSame(['C', 'A', 'B'], $orderedLabels);
        $this->assertSame([0, 1, 2], $orderedPositions);
    }

    public function testMoveRootIntoChildAttachesAndClearsRoot(): void
    {
        $storage = new MockStorage;
        $parent = DetachedMenuItem::create('main', 'Parent');
        $child = DetachedMenuItem::create('main', 'Child');
        $storage->save($parent);
        $storage->save($child);

        $storage->move($child, $parent, 0);

        $rootTree = $storage->getTree('main', false);
        $childParent = $child->getParent();
        $children = iterator_to_array($parent->getChildren());

        $this->assertCount(1, $rootTree);
        $this->assertSame($parent, $childParent);
        $this->assertCount(1, $children);
    }

    public function testMoveChildBackToRootDetachesParent(): void
    {
        $storage = new MockStorage;
        $parent = DetachedMenuItem::create('main', 'Parent');
        $child = DetachedMenuItem::create('main', 'Child');
        $storage->save($parent);
        $storage->save($child);
        $storage->move($child, $parent, 0);

        $storage->move($child, null, 0);

        $childParent = $child->getParent();
        $rootTree = $storage->getTree('main', false);

        $this->assertNull($childParent);
        $this->assertCount(2, $rootTree);
    }

    public function testMovePositionLargerThanListAppendsAtEnd(): void
    {
        $storage = new MockStorage;
        $a = DetachedMenuItem::create('main', 'A');
        $b = DetachedMenuItem::create('main', 'B');
        $storage->save($a);
        $storage->save($b);

        $storage->move($a, null, 99);

        $tree = $storage->getTree('main', false);
        $labels = [
            $tree[0]->getLabel(),
            $tree[1]->getLabel(),
        ];

        $this->assertSame(['B', 'A'], $labels);
    }

    public function testMoveNegativePositionClampsToZero(): void
    {
        $storage = new MockStorage;
        $a = DetachedMenuItem::create('main', 'A');
        $b = DetachedMenuItem::create('main', 'B');
        $storage->save($a);
        $storage->save($b);

        $storage->move($b, null, -10);

        $tree = $storage->getTree('main', false);
        $firstLabel = $tree[0]->getLabel();

        $this->assertSame('B', $firstLabel);
    }
}
