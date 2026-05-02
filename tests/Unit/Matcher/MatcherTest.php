<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Matcher;

use Danilovl\MenuBuilderBundle\Matcher\Matcher;
use Danilovl\MenuBuilderBundle\Matcher\Voter\VoterInterface;
use Danilovl\MenuBuilderBundle\Model\DetachedMenuItem;
use PHPUnit\Framework\TestCase;

final class MatcherTest extends TestCase
{
    public function testIsCurrentReturnsFirstNonNullVote(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');

        $abstaining = $this->createStub(VoterInterface::class);
        $abstaining->method('matchItem')->willReturn(null);
        $positive = $this->createStub(VoterInterface::class);
        $positive->method('matchItem')->willReturn(true);
        $ignored = $this->createMock(VoterInterface::class);
        $ignored->expects($this->never())->method('matchItem');

        $voters = [$abstaining, $positive, $ignored];
        $matcher = new Matcher($voters);

        $isCurrent = $matcher->isCurrent($item);

        $this->assertTrue($isCurrent);
    }

    public function testIsCurrentReturnsFalseWhenAllVotersAbstain(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $voter = $this->createStub(VoterInterface::class);
        $voter->method('matchItem')->willReturn(null);

        $matcher = new Matcher([$voter]);
        $isCurrent = $matcher->isCurrent($item);

        $this->assertFalse($isCurrent);
    }

    public function testIsCurrentCachesResultAcrossCalls(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $voter = $this->createMock(VoterInterface::class);
        $voter->expects($this->once())
            ->method('matchItem')
            ->willReturn(true);

        $matcher = new Matcher([$voter]);
        $first = $matcher->isCurrent($item);
        $second = $matcher->isCurrent($item);

        $this->assertTrue($first);
        $this->assertTrue($second);
    }

    public function testClearForcesRevoteOnNextCall(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $voter = $this->createMock(VoterInterface::class);
        $voter->expects($this->exactly(2))
            ->method('matchItem')
            ->willReturn(true);

        $matcher = new Matcher([$voter]);
        $matcher->isCurrent($item);
        $matcher->clear();
        $matcher->isCurrent($item);

        $this->assertSame(1, 1);
    }

    public function testIsAncestorTrueWhenChildIsCurrent(): void
    {
        $parent = DetachedMenuItem::create('main', 'Parent');
        $child = DetachedMenuItem::create('main', 'Child');
        $parent->addChild($child);

        $childId = $child->getId();
        $voter = $this->createStub(VoterInterface::class);
        $callback = static function ($item) use ($childId): ?bool {
            return $item->getId() === $childId ? true : null;
        };
        $voter->method('matchItem')->willReturnCallback($callback);

        $matcher = new Matcher([$voter]);
        $isAncestor = $matcher->isAncestor($parent);

        $this->assertTrue($isAncestor);
    }

    public function testIsAncestorRespectsDepthLimit(): void
    {
        $grand = DetachedMenuItem::create('main', 'Grand');
        $parent = DetachedMenuItem::create('main', 'Parent');
        $child = DetachedMenuItem::create('main', 'Child');
        $grand->addChild($parent);
        $parent->addChild($child);

        $childId = $child->getId();
        $voter = $this->createStub(VoterInterface::class);
        $callback = static function ($item) use ($childId): ?bool {
            return $item->getId() === $childId ? true : null;
        };
        $voter->method('matchItem')->willReturnCallback($callback);

        $matcher = new Matcher([$voter]);
        $depthOne = $matcher->isAncestor($grand, 1);
        $depthTwo = $matcher->isAncestor($grand, 2);

        $this->assertFalse($depthOne);
        $this->assertTrue($depthTwo);
    }

    public function testIsAncestorReturnsFalseWhenDepthIsZero(): void
    {
        $parent = DetachedMenuItem::create('main', 'Parent');
        $child = DetachedMenuItem::create('main', 'Child');
        $parent->addChild($child);

        $voter = $this->createStub(VoterInterface::class);
        $voter->method('matchItem')->willReturn(true);

        $matcher = new Matcher([$voter]);
        $isAncestor = $matcher->isAncestor($parent, 0);

        $this->assertFalse($isAncestor);
    }
}
