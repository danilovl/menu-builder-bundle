<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Matcher\Voter;

use Danilovl\MenuBuilderBundle\Matcher\Voter\RegexVoter;
use Danilovl\MenuBuilderBundle\Model\DetachedMenuItem;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{
    Request,
    RequestStack
};

final class RegexVoterTest extends TestCase
{
    public function testConstructorRejectsEmptyPatternString(): void
    {
        $stack = new RequestStack;

        $this->expectException(InvalidArgumentException::class);
        new RegexVoter($stack, '');
    }

    public function testConstructorRejectsInvalidRegex(): void
    {
        $stack = new RequestStack;

        $this->expectException(InvalidArgumentException::class);
        new RegexVoter($stack, '/invalid(/');
    }

    public function testReturnsNullWhenNoPatternsConfigured(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $stack = new RequestStack;

        $voter = new RegexVoter($stack, []);
        $vote = $voter->matchItem($item);

        $this->assertNull($vote);
    }

    public function testReturnsNullWhenNoCurrentRequest(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $stack = new RequestStack;

        $voter = new RegexVoter($stack, '#^/admin#');
        $vote = $voter->matchItem($item);

        $this->assertNull($vote);
    }

    public function testTrueWhenAnyConfiguredPatternMatchesUri(): void
    {
        $item = DetachedMenuItem::create('main', 'Admin');
        $request = Request::create('/admin/users');
        $stack = new RequestStack;
        $stack->push($request);

        $patterns = ['#^/api#', '#^/admin#'];
        $voter = new RegexVoter($stack, $patterns);
        $vote = $voter->matchItem($item);

        $this->assertTrue($vote);
    }

    public function testNullWhenNoPatternMatchesUri(): void
    {
        $item = DetachedMenuItem::create('main', 'Admin');
        $request = Request::create('/public');
        $stack = new RequestStack;
        $stack->push($request);

        $voter = new RegexVoter($stack, '#^/admin#');
        $vote = $voter->matchItem($item);

        $this->assertNull($vote);
    }

    public function testItemAttributeOverridesGlobalPatternsString(): void
    {
        $item = DetachedMenuItem::create('main', 'Custom');
        $item->setAttributes(['regex_patterns' => '#^/custom#']);

        $request = Request::create('/custom/page');
        $stack = new RequestStack;
        $stack->push($request);

        $voter = new RegexVoter($stack, '#^/admin#');
        $vote = $voter->matchItem($item);

        $this->assertTrue($vote);
    }

    public function testItemAttributeOverridesGlobalPatternsArray(): void
    {
        $perItem = ['#^/custom#', '#^/special#'];
        $item = DetachedMenuItem::create('main', 'Custom');
        $item->setAttributes(['regex_patterns' => $perItem]);

        $request = Request::create('/special/zone');
        $stack = new RequestStack;
        $stack->push($request);

        $voter = new RegexVoter($stack, '#^/admin#');
        $vote = $voter->matchItem($item);

        $this->assertTrue($vote);
    }

    public function testItemAttributeFiltersOutNonStringEntries(): void
    {
        $perItem = ['', '#^/valid#', 42];
        $item = DetachedMenuItem::create('main', 'Custom');
        $item->setAttributes(['regex_patterns' => $perItem]);

        $request = Request::create('/valid/page');
        $stack = new RequestStack;
        $stack->push($request);

        $voter = new RegexVoter($stack, []);
        $vote = $voter->matchItem($item);

        $this->assertTrue($vote);
    }
}
