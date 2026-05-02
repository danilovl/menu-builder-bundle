<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Controller\Api;

use Danilovl\MenuBuilderBundle\DependencyInjection\MenuBuilderConfig;
use Danilovl\MenuBuilderBundle\Model\MenuItemInterface;
use Symfony\Component\HttpFoundation\{
    JsonResponse,
    Request
};
use Danilovl\MenuBuilderBundle\Service\{
    MenuItemNormalizer,
    MenuManager
};
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '%danilovl_menu_builder.api.prefix%', name: 'danilovl_menu_builder_api_public_')]
readonly class MenuPublicApiController
{
    public function __construct(
        private MenuManager $menus,
        private MenuItemNormalizer $normalizer,
        private MenuBuilderConfig $config
    ) {}

    #[Route(
        path: '/tree/{name}',
        name: 'tree',
        requirements: ['name' => '[^/]+'],
        methods: [Request::METHOD_GET],
    )]
    public function tree(string $name, Request $request): JsonResponse
    {
        $tree = $this->menus->getTree($name);
        $maxDepthRaw = $request->query->get('maxDepth');

        if ($maxDepthRaw !== null && $maxDepthRaw !== '') {
            $maxDepth = max(0, (int) $maxDepthRaw);
            $tree = $this->trimDepth($tree, $maxDepth, 0);
        }

        $previewLocaleRaw = $request->query->get('previewLocale');
        $previewLocale = null;
        if (is_string($previewLocaleRaw) && $previewLocaleRaw !== '') {
            $previewLocale = $previewLocaleRaw;
        }

        $items = $this->normalizer->normalizeTree($tree, $previewLocale);
        $payload = [
            'menu' => $name,
            'items' => $items,
        ];

        $response = new JsonResponse($payload);

        $ttl = $this->config->apiPublicCacheTtl;
        if ($ttl > 0) {
            $response->setMaxAge($ttl);
            $response->setPublic();
        }

        $content = $response->getContent();
        if ($content === false || $content === '') {
            $content = '';
        }
        $etag = md5($content);
        $response->setEtag($etag);

        return $response;
    }

    /**
     * @param array<int, MenuItemInterface> $items
     * @return array<int, MenuItemInterface>
     */
    private function trimDepth(array $items, int $maxDepth, int $currentDepth): array
    {
        if ($currentDepth >= $maxDepth) {
            foreach ($items as $item) {
                foreach ($item->getChildren() as $child) {
                    $item->removeChild($child);
                }
            }

            return $items;
        }

        $nextDepth = $currentDepth + 1;
        foreach ($items as $item) {
            $iterable = $item->getChildren();
            $children = iterator_to_array($iterable);
            $this->trimDepth($children, $maxDepth, $nextDepth);
        }

        return $items;
    }

    #[Route(path: '', name: 'list', methods: [Request::METHOD_GET])]
    public function listMenus(): JsonResponse
    {
        $names = $this->menus->getMenuNames();
        $items = [];
        foreach ($names as $name) {
            $active = $this->menus->isMenuActive($name);
            $items[] = [
                'name' => $name,
                'active' => $active
            ];
        }

        $payload = ['menus' => $items];

        return new JsonResponse($payload);
    }
}
