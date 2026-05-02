<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Controller\Api;

use Danilovl\MenuBuilderBundle\DependencyInjection\MenuBuilderConfig;
use Danilovl\MenuBuilderBundle\Service\RouteCatalog;
use Symfony\Component\HttpFoundation\{
    JsonResponse,
    Request
};
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '%danilovl_menu_builder.api.prefix%/routes', name: 'danilovl_menu_builder_api_routes_')]
readonly class RouteApiController
{
    public function __construct(
        private RouteCatalog $catalog,
        private MenuBuilderConfig $config
    ) {}

    #[Route(path: '', name: 'list', methods: [Request::METHOD_GET])]
    public function list(Request $request): JsonResponse
    {
        $term = (string) $request->query->get('q', '');
        $limit = $this->config->routeCatalogPreloadLimit;

        $all = $this->catalog->all();
        $total = count($all);

        $filtered = $this->catalog->filter($all, $term);
        $matched = count($filtered);

        $sliced = $limit > 0 ? array_slice($filtered, 0, $limit) : $filtered;
        $truncated = $limit > 0 && $matched > $limit;

        $items = [];
        foreach ($sliced as $info) {
            $items[] = $info->toArray();
        }

        return new JsonResponse([
            'items' => $items,
            'total' => $total,
            'matched' => $matched,
            'limit' => $limit,
            'truncated' => $truncated,
        ]);
    }
}
