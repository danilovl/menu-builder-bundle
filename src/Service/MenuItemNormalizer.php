<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Service;

use DateTimeInterface;
use Danilovl\MenuBuilderBundle\Model\MenuItemInterface;
use Danilovl\MenuBuilderBundle\Security\UriSafety;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class MenuItemNormalizer
{
    public function __construct(private ?TranslatorInterface $translator = null) {}

    /**
     * @param array<int, MenuItemInterface> $tree
     * @return array<int, array<string, mixed>>
     */
    public function normalizeTree(array $tree, ?string $previewLocale = null, bool $sanitizeUris = false): array
    {
        $result = [];
        foreach ($tree as $item) {
            $result[] = $this->normalize($item, $previewLocale, $sanitizeUris);
        }

        return $result;
    }

    /**
     * @return array{
     *     id: int|string|null,
     *     menuName: string,
     *     label: string,
     *     resolvedLabel: string,
     *     uri: string|null,
     *     route: string|null,
     *     routeParams: array<string, mixed>,
     *     icon: string|null,
     *     iconImage: string|null,
     *     attributes: array<string, mixed>,
     *     position: int,
     *     active: bool,
     *     isDisplayed: bool,
     *     requiredRoles: array<int, string>,
     *     allowedUsers: array<int, string>,
     *     target: string|null,
     *     visibility: string,
     *     translations: array<string, array{label?: string, uri?: string}>,
     *     dependentActiveRoutes: array<int, string>,
     *     labelTranslated: bool,
     *     translationDomain: string|null,
     *     type: string,
     *     column: int,
     *     cssClasses: array<int, string>,
     *     publishedAt: string|null,
     *     unpublishedAt: string|null,
     *     parentId: int|string|null,
     *     hasChildren: bool,
     *     children: array<int, array<string, mixed>>,
     * }
     */
    public function normalize(MenuItemInterface $item, ?string $previewLocale = null, bool $sanitizeUris = false): array
    {
        $children = [];
        foreach ($item->getChildren() as $child) {
            $children[] = $this->normalize($child, $previewLocale, $sanitizeUris);
        }

        $uri = $item->getUri();
        $translations = $item->getTranslations();
        if ($sanitizeUris) {
            $uri = self::sanitizeUri($uri);
            $translations = self::sanitizeTranslations($translations);
        }

        $resolvedLabel = $this->resolveLabel($item, $previewLocale);
        $publishedAt = $item->getPublishedAt()?->format(DateTimeInterface::ATOM);
        $unpublishedAt = $item->getUnpublishedAt()?->format(DateTimeInterface::ATOM);
        $parentId = $item->getParent()?->getId();

        return [
            'id' => $item->getId(),
            'menuName' => $item->getMenuName(),
            'label' => $item->getLabel(),
            'resolvedLabel' => $resolvedLabel,
            'uri' => $uri,
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
            'translations' => $translations,
            'dependentActiveRoutes' => $item->getDependentActiveRoutes(),
            'labelTranslated' => $item->isLabelTranslated(),
            'translationDomain' => $item->getTranslationDomain(),
            'type' => $item->getType(),
            'column' => $item->getColumn(),
            'cssClasses' => $item->getCssClasses(),
            'publishedAt' => $publishedAt,
            'unpublishedAt' => $unpublishedAt,
            'parentId' => $parentId,
            'hasChildren' => $item->hasChildren(),
            'children' => $children,
        ];
    }

    private static function sanitizeUri(?string $uri): ?string
    {
        if ($uri === null || $uri === '') {
            return $uri;
        }

        return UriSafety::isSafe($uri) ? $uri : null;
    }

    /**
     * @param array<string, array{label?: string, uri?: string}> $translations
     * @return array<string, array{label?: string, uri?: string}>
     */
    private static function sanitizeTranslations(array $translations): array
    {
        foreach ($translations as $locale => $translation) {
            if (isset($translation['uri']) && !UriSafety::isSafe($translation['uri'])) {
                unset($translation['uri']);
                $translations[$locale] = $translation;
            }
        }

        return $translations;
    }

    private function resolveLabel(MenuItemInterface $item, ?string $previewLocale): string
    {
        if ($previewLocale !== null) {
            $raw = $item->getLabelForLocale($previewLocale);
        } else {
            $raw = $item->getLabel();
        }

        if (!$item->isLabelTranslated() || $this->translator === null) {
            return $raw;
        }

        $domain = $item->getTranslationDomain();

        return $this->translator->trans(
            id: $raw,
            parameters: [],
            domain: $domain,
            locale: $previewLocale,
        );
    }
}
