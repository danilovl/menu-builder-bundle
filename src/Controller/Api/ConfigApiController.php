<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Controller\Api;

use Danilovl\MenuBuilderBundle\DependencyInjection\MenuBuilderConfig;
use Symfony\Component\HttpFoundation\{
    JsonResponse,
    Request
};
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '%danilovl_menu_builder.api.prefix%/config', name: 'danilovl_menu_builder_api_config_')]
readonly class ConfigApiController
{
    public function __construct(private MenuBuilderConfig $config) {}

    #[Route(path: '', name: 'index', methods: [Request::METHOD_GET])]
    public function index(): JsonResponse
    {
        return new JsonResponse([
            'apiPrefix' => $this->config->apiPrefix,
        ]);
    }
}
