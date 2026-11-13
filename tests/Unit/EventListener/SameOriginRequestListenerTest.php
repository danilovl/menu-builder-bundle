<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\EventListener;

use Danilovl\MenuBuilderBundle\EventListener\SameOriginRequestListener;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{
    Request,
    Response
};
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SameOriginRequestListenerTest extends TestCase
{
    /**
     * @param array<string, string> $headers
     */
    private function dispatch(
        array $headers,
        string $method = Request::METHOD_POST,
        string $route = 'danilovl_menu_builder_api_admin_create',
        int $requestType = HttpKernelInterface::MAIN_REQUEST
    ): RequestEvent {
        $request = Request::create('http://admin.example.com/api/menu/admin/items', $method);
        $request->attributes->set('_route', $route);
        $request->headers->add($headers);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, $requestType);

        $listener = new SameOriginRequestListener;
        $listener($event);

        return $event;
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('provideAllowedCases')]
    public function testAllowed(array $headers): void
    {
        $event = $this->dispatch($headers);

        $hasResponse = $event->hasResponse();

        $this->assertFalse($hasResponse);
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('provideForbiddenCases')]
    public function testForbidden(array $headers): void
    {
        $event = $this->dispatch($headers);

        $response = $event->getResponse();
        $this->assertNotNull($response);

        $statusCode = $response->getStatusCode();

        $this->assertSame(Response::HTTP_FORBIDDEN, $statusCode);
    }

    public function testSafeMethodIsIgnored(): void
    {
        $event = $this->dispatch(['Sec-Fetch-Site' => 'cross-site'], Request::METHOD_GET);

        $hasResponse = $event->hasResponse();

        $this->assertFalse($hasResponse);
    }

    public function testForeignRouteIsIgnored(): void
    {
        $event = $this->dispatch(['Sec-Fetch-Site' => 'cross-site'], route: 'app_other');

        $hasResponse = $event->hasResponse();

        $this->assertFalse($hasResponse);
    }

    public function testSubRequestIsIgnored(): void
    {
        $event = $this->dispatch(
            ['Sec-Fetch-Site' => 'cross-site'],
            requestType: HttpKernelInterface::SUB_REQUEST
        );

        $hasResponse = $event->hasResponse();

        $this->assertFalse($hasResponse);
    }

    public static function provideAllowedCases(): Generator
    {
        yield 'no headers (non-browser client)' => [[]];
        yield 'same-origin fetch' => [['Sec-Fetch-Site' => 'same-origin']];
        yield 'user initiated' => [['Sec-Fetch-Site' => 'none']];
        yield 'same-origin fetch with https origin behind proxy' => [[
            'Sec-Fetch-Site' => 'same-origin',
            'Origin' => 'https://admin.example.com'
        ]];
        yield 'origin fallback, same host, different scheme (reverse proxy)' => [['Origin' => 'https://admin.example.com']];
        yield 'origin fallback, same host, case insensitive' => [['Origin' => 'https://ADMIN.example.com']];
    }

    public static function provideForbiddenCases(): Generator
    {
        yield 'cross-site fetch' => [['Sec-Fetch-Site' => 'cross-site']];
        yield 'same-site fetch' => [['Sec-Fetch-Site' => 'same-site']];
        yield 'cross-site fetch wins over matching origin' => [[
            'Sec-Fetch-Site' => 'cross-site',
            'Origin' => 'http://admin.example.com'
        ]];
        yield 'origin fallback, foreign host' => [['Origin' => 'https://evil.example.org']];
        yield 'origin fallback, opaque origin' => [['Origin' => 'null']];
    }
}
