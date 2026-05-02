<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Service;

use Danilovl\MenuBuilderBundle\Model\MenuItemInterface;
use DateTimeImmutable;
use DateTimeInterface;

readonly class MenuExporter
{
    public function __construct(private MenuManager $menus) {}

    /**
     * @return array{menu: string, exportedAt: string, items: array<int, array<string, mixed>>}
     */
    public function export(string $menuName): array
    {
        $tree = $this->menus->getAllItems($menuName);
        $items = array_map(function (MenuItemInterface $item): array {
            return $this->serialize($item);
        }, $tree);

        $exportedAt = (new DateTimeImmutable)->format(DateTimeInterface::ATOM);

        return [
            'menu' => $menuName,
            'exportedAt' => $exportedAt,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(MenuItemInterface $item): array
    {
        $children = [];
        foreach ($item->getChildren() as $child) {
            $children[] = $this->serialize($child);
        }

        return [
            'label' => $item->getLabel(),
            'uri' => $item->getUri(),
            'route' => $item->getRoute(),
            'routeParams' => $item->getRouteParams(),
            'icon' => $item->getIcon(),
            'iconImage' => $item->getIconImage(),
            'attributes' => $item->getAttributes(),
            'position' => $item->getPosition(),
            'active' => $item->isActive(),
            'isDisplayed' => $item->isDisplayed(),
            'requiredRoles' => $item->getRequiredRoles(),
            'allowedUsers' => $item->getAllowedUsers(),
            'target' => $item->getTarget(),
            'visibility' => $item->getVisibility(),
            'translations' => $item->getTranslations(),
            'dependentActiveRoutes' => $item->getDependentActiveRoutes(),
            'labelTranslated' => $item->isLabelTranslated(),
            'translationDomain' => $item->getTranslationDomain(),
            'children' => $children,
        ];
    }
}
