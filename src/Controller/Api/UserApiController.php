<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Controller\Api;

use Danilovl\MenuBuilderBundle\Service\UserCatalogInterface;
use Symfony\Component\HttpFoundation\{
    JsonResponse,
    Request
};
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '%danilovl_menu_builder.api.prefix%/users', name: 'danilovl_menu_builder_api_users_')]
readonly class UserApiController
{
    public function __construct(private UserCatalogInterface $catalog) {}

    #[Route(path: '', name: 'list', methods: [Request::METHOD_GET])]
    public function list(Request $request): JsonResponse
    {
        $term = (string) $request->query->get('q', '');
        $limit = (int) $request->query->get('limit', 30);
        $clamped = max(1, min($limit, 100));
        $trimmed = mb_trim($term);

        if ($trimmed === '') {
            $payload = [
                'items' => [],
                'matched' => 0,
                'truncated' => false
            ];

            return new JsonResponse($payload);
        }

        $items = $this->catalog->search($trimmed, $clamped);
        $count = count($items);
        $payload = [
            'items' => $items,
            'matched' => $count,
            'truncated' => $count >= $clamped
        ];

        return new JsonResponse($payload);
    }
}
