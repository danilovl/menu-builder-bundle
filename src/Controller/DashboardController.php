<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Controller;

use Danilovl\MenuBuilderBundle\DependencyInjection\MenuBuilderConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{
    Request,
    Response
};
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: 'danilovl/menu-builder/dashboard', name: 'danilovl_menu_builder_dashboard_')]
class DashboardController extends AbstractController
{
    public function __construct(private readonly MenuBuilderConfig $config) {}

    #[Route(path: '', name: 'index', methods: [Request::METHOD_GET])]
    public function index(): Response
    {
        $context = ['api_base' => $this->config->apiPrefix];

        return $this->render('@MenuBuilder/dashboard/index.html.twig', $context);
    }
}
