<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Controller\Api;

use DomainException;
use Danilovl\MenuBuilderBundle\Exception\MenuItemNotFoundException;
use Symfony\Component\HttpFoundation\{
    JsonResponse,
    Request,
    Response
};
use Danilovl\MenuBuilderBundle\Service\{
    MenuExporter,
    MenuImporter,
    MenuItemNormalizer,
    MenuManager
};
use Danilovl\MenuBuilderBundle\Dto\Request\{
    BulkItemsRequest,
    CreateMenuItemRequest,
    ImportMenuRequest,
    MoveItemRequest,
    RenameMenuRequest,
    ToggleActiveRequest,
    UpdateMenuItemRequest
};
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '%danilovl_menu_builder.api.prefix%/admin', name: 'danilovl_menu_builder_api_admin_')]
readonly class MenuAdminApiController
{
    public function __construct(
        private MenuManager $menus,
        private MenuItemNormalizer $normalizer,
        private MenuExporter $exporter,
        private MenuImporter $importer
    ) {}

    #[Route(path: '/items/bulk-delete', name: 'bulk_delete', methods: [Request::METHOD_POST])]
    public function bulkDelete(
        #[MapRequestPayload] BulkItemsRequest $payload,
    ): JsonResponse {
        $deleted = 0;
        $errors = [];
        foreach ($payload->ids as $id) {
            try {
                $this->menus->delete($id);
                $deleted++;
            } catch (MenuItemNotFoundException $e) {
                $errors[] = ['id' => $id, 'error' => $e->getMessage()];
            }
        }

        return new JsonResponse(['deleted' => $deleted, 'errors' => $errors]);
    }

    #[Route(path: '/items/bulk-active', name: 'bulk_active', methods: [Request::METHOD_POST])]
    public function bulkSetActive(
        #[MapRequestPayload] BulkItemsRequest $payload,
    ): JsonResponse {
        if ($payload->active === null) {
            return new JsonResponse(['error' => 'active flag is required'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $updated = 0;
        $errors = [];
        foreach ($payload->ids as $id) {
            try {
                $this->menus->setActive($id, $payload->active);
                $updated++;
            } catch (MenuItemNotFoundException $e) {
                $errors[] = ['id' => $id, 'error' => $e->getMessage()];
            }
        }

        return new JsonResponse(['updated' => $updated, 'errors' => $errors]);
    }

    #[Route(path: '/menus/{name}/export', name: 'export', methods: [Request::METHOD_GET])]
    public function exportMenu(string $name): JsonResponse
    {
        $existing = $this->menus->getMenuNames();
        if (!in_array($name, $existing, true)) {
            return new JsonResponse(['error' => sprintf('Menu "%s" not found.', $name)], Response::HTTP_NOT_FOUND);
        }

        $payload = $this->exporter->export($name);
        $response = new JsonResponse($payload);
        $filename = sprintf('menu-%s.json', $name);
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }

    #[Route(path: '/menus/import', name: 'import', methods: [Request::METHOD_POST])]
    public function importMenu(
        #[MapRequestPayload] ImportMenuRequest $payload,
    ): JsonResponse {
        try {
            $result = $this->importer->import($payload->toArray(), $payload->overrideMenuName);
        } catch (DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse($result, Response::HTTP_CREATED);
    }

    #[Route(path: '/{name}/trash', name: 'trash_list', methods: [Request::METHOD_GET])]
    public function listTrash(string $name): JsonResponse
    {
        $items = $this->menus->getDeletedItems($name);
        $payload = array_map(function ($item): array {
            $normalized = $this->normalizer->normalize($item);
            $normalized['menuName'] = $item->getMenuName();

            return $normalized;
        }, $items);

        return new JsonResponse(['menu' => $name, 'items' => $payload]);
    }

    #[Route(path: '/items/{id}/restore', name: 'restore', methods: [Request::METHOD_POST])]
    public function restoreItem(string $id): JsonResponse
    {
        try {
            $item = $this->menus->restore($id);
        } catch (MenuItemNotFoundException) {
            throw new NotFoundHttpException;
        }
        $normalized = $this->normalizer->normalize($item);

        return new JsonResponse($normalized);
    }

    #[Route(path: '/search', name: 'search', methods: [Request::METHOD_GET])]
    public function search(Request $request): JsonResponse
    {
        $term = (string) $request->query->get('q', '');
        $limit = (int) $request->query->get('limit', 20);
        $clampedLimit = max(1, min($limit, 100));

        $results = $this->menus->search($term, $clampedLimit);
        $payload = [];
        foreach ($results as $item) {
            $normalized = $this->normalizer->normalize($item);
            $normalized['menuName'] = $item->getMenuName();
            $payload[] = $normalized;
        }

        return new JsonResponse(['items' => $payload]);
    }

    #[Route(path: '/{name}/items', name: 'list', methods: [Request::METHOD_GET])]
    public function listItems(string $name, Request $request): JsonResponse
    {
        $tree = $this->menus->getAllItems($name);
        $normalized = $this->normalizer->normalizeTree($tree, $this->previewLocale($request));

        return new JsonResponse(['menu' => $name, 'items' => $normalized]);
    }

    #[Route(path: '/menus/{name}', name: 'rename_menu', methods: [Request::METHOD_PATCH])]
    public function renameMenu(
        string $name,
        #[MapRequestPayload] RenameMenuRequest $payload,
    ): JsonResponse {
        try {
            $this->menus->renameMenu($name, $payload->name);
        } catch (MenuItemNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['name' => mb_trim($payload->name)]);
    }

    #[Route(path: '/menus/{name}', name: 'delete_menu', methods: [Request::METHOD_DELETE])]
    public function deleteMenu(string $name): JsonResponse
    {
        try {
            $this->menus->deleteMenu($name);
        } catch (MenuItemNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/menus/{name}/active', name: 'toggle_menu_active', methods: [Request::METHOD_POST])]
    public function toggleMenuActive(
        string $name,
        #[MapRequestPayload] ToggleActiveRequest $payload,
    ): JsonResponse {
        try {
            $this->menus->setMenuActive($name, $payload->active);
        } catch (MenuItemNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['name' => $name, 'active' => $payload->active]);
    }

    #[Route(path: '/items', name: 'create', methods: [Request::METHOD_POST])]
    public function createItem(
        Request $request,
        #[MapRequestPayload] CreateMenuItemRequest $payload,
    ): JsonResponse {
        try {
            $item = $this->menus->create($payload->toArray());
        } catch (MenuItemNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $normalized = $this->normalizer->normalize($item, $this->previewLocale($request));

        return new JsonResponse($normalized, Response::HTTP_CREATED);
    }

    #[Route(path: '/items/{id}', name: 'update', methods: [Request::METHOD_PUT, Request::METHOD_PATCH])]
    public function updateItem(
        string $id,
        Request $request,
        #[MapRequestPayload] UpdateMenuItemRequest $payload,
    ): JsonResponse {
        try {
            $item = $this->menus->update($id, $payload->toArray());
        } catch (MenuItemNotFoundException) {
            throw new NotFoundHttpException;
        } catch (DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $normalized = $this->normalizer->normalize($item, $this->previewLocale($request));

        return new JsonResponse($normalized);
    }

    private function previewLocale(Request $request): ?string
    {
        $value = $request->query->get('previewLocale');
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    #[Route(path: '/items/{id}', name: 'delete', methods: [Request::METHOD_DELETE])]
    public function deleteItem(string $id): JsonResponse
    {
        try {
            $this->menus->delete($id);
        } catch (MenuItemNotFoundException) {
            throw new NotFoundHttpException;
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/items/{id}/duplicate', name: 'duplicate', methods: [Request::METHOD_POST])]
    public function duplicateItem(string $id): JsonResponse
    {
        try {
            $item = $this->menus->duplicate($id);
        } catch (MenuItemNotFoundException) {
            throw new NotFoundHttpException;
        } catch (DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $normalized = $this->normalizer->normalize($item);

        return new JsonResponse($normalized, Response::HTTP_CREATED);
    }

    #[Route(path: '/items/{id}/active', name: 'toggle_active', methods: [Request::METHOD_POST])]
    public function toggleActive(
        string $id,
        #[MapRequestPayload] ToggleActiveRequest $payload,
    ): JsonResponse {
        try {
            $item = $this->menus->setActive($id, $payload->active);
        } catch (MenuItemNotFoundException) {
            throw new NotFoundHttpException;
        }
        $normalized = $this->normalizer->normalize($item);

        return new JsonResponse($normalized);
    }

    #[Route(path: '/items/{id}/move', name: 'move', methods: [Request::METHOD_POST])]
    public function move(
        string $id,
        #[MapRequestPayload] MoveItemRequest $payload,
    ): JsonResponse {
        try {
            $item = $this->menus->move(
                id: $id,
                newParentId: $payload->parentId,
                newPosition: $payload->position,
            );
        } catch (MenuItemNotFoundException) {
            throw new NotFoundHttpException;
        } catch (DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $normalized = $this->normalizer->normalize($item);

        return new JsonResponse($normalized);
    }
}
