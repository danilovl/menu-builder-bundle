<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Matcher\Voter;

use Danilovl\MenuBuilderBundle\Matcher\Voter\RouteVoter;
use Danilovl\MenuBuilderBundle\Model\DetachedMenuItem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{
    Request,
    RequestStack
};

final class RouteVoterTest extends TestCase
{
    public function testReturnsNullWhenNoRouteAndNoDependents(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $stack = new RequestStack;

        $voter = new RouteVoter($stack);
        $vote = $voter->matchItem($item);

        $this->assertNull($vote);
    }

    public function testReturnsNullWhenNoCurrentRequest(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $item->setRoute('app_home');
        $stack = new RequestStack;

        $voter = new RouteVoter($stack);
        $vote = $voter->matchItem($item);

        $this->assertNull($vote);
    }

    public function testReturnsNullWhenCurrentRouteIsMissingFromAttributes(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $item->setRoute('app_home');

        $request = Request::create('/home');
        $stack = new RequestStack;
        $stack->push($request);

        $voter = new RouteVoter($stack);
        $vote = $voter->matchItem($item);

        $this->assertNull($vote);
    }

    public function testTrueWhenItemRouteEqualsCurrentRoute(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $item->setRoute('app_home');

        $request = Request::create('/home');
        $request->attributes->set('_route', 'app_home');
        $stack = new RequestStack;
        $stack->push($request);

        $voter = new RouteVoter($stack);
        $vote = $voter->matchItem($item);

        $this->assertTrue($vote);
    }

    public function testFalseWhenRouteMatchesButRouteParamsDiffer(): void
    {
        $item = DetachedMenuItem::create('main', 'Show');
        $item->setRoute('app_show');
        $item->setRouteParams(['id' => '42']);

        $request = Request::create('/show/9');
        $request->attributes->set('_route', 'app_show');
        $request->attributes->set('_route_params', ['id' => '9']);
        $stack = new RequestStack;
        $stack->push($request);

        $voter = new RouteVoter($stack);
        $vote = $voter->matchItem($item);

        $this->assertFalse($vote);
    }

    public function testTrueWhenRouteParamsMatchAsString(): void
    {
        $item = DetachedMenuItem::create('main', 'Show');
        $item->setRoute('app_show');
        $item->setRouteParams(['id' => 42]);

        $request = Request::create('/show/42');
        $request->attributes->set('_route', 'app_show');
        $request->attributes->set('_route_params', ['id' => '42']);
        $stack = new RequestStack;
        $stack->push($request);

        $voter = new RouteVoter($stack);
        $vote = $voter->matchItem($item);

        $this->assertTrue($vote);
    }

    public function testTrueWhenCurrentRouteListedInDependents(): void
    {
        $item = DetachedMenuItem::create('main', 'Blog');
        $item->setRoute('app_blog_index');
        $item->setDependentActiveRoutes(['app_blog_show']);

        $request = Request::create('/blog/post');
        $request->attributes->set('_route', 'app_blog_show');
        $stack = new RequestStack;
        $stack->push($request);

        $voter = new RouteVoter($stack);
        $vote = $voter->matchItem($item);

        $this->assertTrue($vote);
    }

    public function testFalseWhenCurrentRouteIsUnrelated(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $item->setRoute('app_home');

        $request = Request::create('/about');
        $request->attributes->set('_route', 'app_about');
        $stack = new RequestStack;
        $stack->push($request);

        $voter = new RouteVoter($stack);
        $vote = $voter->matchItem($item);

        $this->assertFalse($vote);
    }
}
