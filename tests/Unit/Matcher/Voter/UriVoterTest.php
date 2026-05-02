<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Matcher\Voter;

use Danilovl\MenuBuilderBundle\Matcher\Voter\UriVoter;
use Danilovl\MenuBuilderBundle\Model\DetachedMenuItem;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{
    Request,
    RequestStack
};

final class UriVoterTest extends TestCase
{
    public function testReturnsNullWhenItemUriIsEmpty(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $stack = new RequestStack;

        $voter = new UriVoter($stack);
        $vote = $voter->matchItem($item);

        $this->assertNull($vote);
    }

    public function testReturnsNullWhenNoCurrentRequest(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $item->setUri('/home');
        $stack = new RequestStack;

        $voter = new UriVoter($stack);
        $vote = $voter->matchItem($item);

        $this->assertNull($vote);
    }

    #[DataProvider('provideMatchesAccountForTrailingSlashCases')]
    public function testMatchesAccountForTrailingSlash(string $itemUri, string $requestUri, bool $expected): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $item->setUri($itemUri);

        $request = Request::create($requestUri);
        $stack = new RequestStack;
        $stack->push($request);

        $voter = new UriVoter($stack);
        $vote = $voter->matchItem($item);

        $this->assertSame($expected, $vote);
    }

    public static function provideMatchesAccountForTrailingSlashCases(): Generator
    {
        yield 'exact match' => ['/home', '/home', true];
        yield 'trailing slash on item' => ['/home/', '/home', true];
        yield 'trailing slash on request' => ['/home', '/home/', true];
        yield 'different path' => ['/home', '/about', false];
        yield 'partial path mismatch' => ['/home', '/home/page', false];
    }
}
