<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Controller\Api;

use Danilovl\MenuBuilderBundle\Service\RoleCatalog;
use Symfony\Component\HttpFoundation\{
    JsonResponse,
    Request
};
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '%danilovl_menu_builder.api.prefix%/roles', name: 'danilovl_menu_builder_api_roles_')]
readonly class RoleApiController
{
    public function __construct(private RoleCatalog $catalog) {}

    #[Route(path: '', name: 'list', methods: [Request::METHOD_GET])]
    public function list(): JsonResponse
    {
        $items = $this->catalog->all();

        return new JsonResponse(['items' => $items]);
    }
}
