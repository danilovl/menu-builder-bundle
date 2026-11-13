<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\{
    JsonResponse,
    Request,
    Response
};
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
final readonly class SameOriginRequestListener
{
    private const string ROUTE_PREFIX = 'danilovl_menu_builder_api_';

    private const array ALLOWED_FETCH_SITES = ['same-origin', 'none'];

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->isMethodSafe()) {
            return;
        }

        $route = $request->attributes->get('_route');
        if (!is_string($route) || !str_starts_with($route, self::ROUTE_PREFIX)) {
            return;
        }

        if ($this->isSameOrigin($request)) {
            return;
        }

        $response = new JsonResponse(
            data: ['error' => 'Cross-site request is not allowed'],
            status: Response::HTTP_FORBIDDEN
        );

        $event->setResponse($response);
    }

    private function isSameOrigin(Request $request): bool
    {
        $fetchSite = $request->headers->get('Sec-Fetch-Site');
        if ($fetchSite !== null) {
            return in_array(mb_strtolower($fetchSite), self::ALLOWED_FETCH_SITES, true);
        }

        $origin = $request->headers->get('Origin');
        if ($origin === null) {
            return true;
        }

        $originHost = parse_url($origin, PHP_URL_HOST);
        if (!is_string($originHost) || $originHost === '') {
            return false;
        }

        return mb_strtolower($originHost) === mb_strtolower($request->getHost());
    }
}
