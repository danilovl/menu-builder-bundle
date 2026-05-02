<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Model;

use DateTimeImmutable;
use InvalidArgumentException;
use DateTimeInterface;

class DetachedMenuItem implements MenuItemInterface
{
    /**
     * @var array<int, self>
     */
    private array $children = [];

    private ?self $parent = null;

    private MenuItemVisibility $visibility;

    private MenuItemType $type;

    /**
     * @param array<string, mixed> $routeParams
     * @param array<string, mixed> $attributes
     * @param array<int, string> $requiredRoles
     * @param array<int, string> $allowedUsers
     * @param array<string, array{label?: string, uri?: string}> $translations
     * @param array<int, string> $dependentActiveRoutes
     * @param array<int, string> $cssClasses
     */
    public function __construct(
        private int|string $id,
        private string $menuName,
        private string $label,
        private ?string $uri = null,
        private ?string $route = null,
        private array $routeParams = [],
        private ?string $icon = null,
        private ?string $iconImage = null,
        private array $attributes = [],
        private int $position = 0,
        private bool $active = true,
        private bool $isDisplayed = true,
        private array $requiredRoles = [],
        private array $allowedUsers = [],
        private ?string $target = null,
        string|MenuItemVisibility $visibility = MenuItemVisibility::ALWAYS,
        private array $translations = [],
        private array $dependentActiveRoutes = [],
        private bool $labelTranslated = false,
        private ?string $translationDomain = null,
        string|MenuItemType $type = MenuItemType::LINK,
        private int $column = 0,
        private array $cssClasses = [],
        private ?DateTimeImmutable $publishedAt = null,
        private ?DateTimeImmutable $unpublishedAt = null
    ) {
        $this->visibility = $visibility instanceof MenuItemVisibility
            ? $visibility
            : MenuItemVisibility::tryFrom($visibility) ?? MenuItemVisibility::ALWAYS;

        $this->type = $type instanceof MenuItemType
            ? $type
            : MenuItemType::tryFrom($type) ?? MenuItemType::LINK;
    }

    public static function create(string $menuName, string $label): self
    {
        $id = uniqid();

        return new self($id, $menuName, $label);
    }

    public static function fromItem(MenuItemInterface $item): self
    {
        return new self(
            id: $item->getId() ?? uniqid(),
            menuName: $item->getMenuName(),
            label: $item->getLabel(),
            uri: $item->getUri(),
            route: $item->getRoute(),
            routeParams: $item->getRouteParams(),
            icon: $item->getIcon(),
            iconImage: $item->getIconImage(),
            attributes: $item->getAttributes(),
            position: $item->getPosition(),
            active: $item->isActive(),
            isDisplayed: $item->isDisplayed(),
            requiredRoles: $item->getRequiredRoles(),
            allowedUsers: $item->getAllowedUsers(),
            target: $item->getTarget(),
            visibility: $item->getVisibility(),
            translations: $item->getTranslations(),
            dependentActiveRoutes: $item->getDependentActiveRoutes(),
            labelTranslated: $item->isLabelTranslated(),
            translationDomain: $item->getTranslationDomain(),
            type: $item->getType(),
            column: $item->getColumn(),
            cssClasses: $item->getCssClasses(),
            publishedAt: $item->getPublishedAt(),
            unpublishedAt: $item->getUnpublishedAt()
        );
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getMenuName(): string
    {
        return $this->menuName;
    }

    public function setMenuName(string $name): self
    {
        $this->menuName = $name;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function setUri(?string $uri): self
    {
        $this->uri = $uri;

        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(?string $route): self
    {
        $this->route = $route;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function setRouteParams(array $params): self
    {
        $this->routeParams = $params;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function getIconImage(): ?string
    {
        return $this->iconImage;
    }

    public function setIconImage(?string $iconImage): self
    {
        $this->iconImage = $iconImage === '' ? null : $iconImage;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function setAttributes(array $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function isDisplayed(): bool
    {
        return $this->isDisplayed;
    }

    public function setIsDisplayed(bool $displayed): self
    {
        $this->isDisplayed = $displayed;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getRequiredRoles(): array
    {
        return $this->requiredRoles;
    }

    /**
     * @param array<int, string> $roles
     */
    public function setRequiredRoles(array $roles): self
    {
        $this->requiredRoles = $roles;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedUsers(): array
    {
        return $this->allowedUsers;
    }

    /**
     * @param array<int, string> $users
     */
    public function setAllowedUsers(array $users): self
    {
        $this->allowedUsers = $users;

        return $this;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function setTarget(?string $target): self
    {
        $this->target = $target === '' ? null : $target;

        return $this;
    }

    public function getVisibility(): string
    {
        return $this->visibility->value;
    }

    public function getVisibilityEnum(): MenuItemVisibility
    {
        return $this->visibility;
    }

    public function setVisibility(string|MenuItemVisibility $visibility): self
    {
        if ($visibility instanceof MenuItemVisibility) {
            $this->visibility = $visibility;

            return $this;
        }

        $resolved = MenuItemVisibility::tryFrom($visibility);
        if ($resolved === null) {
            throw new InvalidArgumentException('Invalid visibility: ' . $visibility);
        }

        $this->visibility = $resolved;

        return $this;
    }

    /**
     * @return array<string, array{label?: string, uri?: string}>
     */
    public function getTranslations(): array
    {
        return $this->translations;
    }

    /**
     * @param array<string, array{label?: string, uri?: string}> $translations
     */
    public function setTranslations(array $translations): self
    {
        $clean = [];
        foreach ($translations as $locale => $data) {
            if ($locale === '') {
                continue;
            }

            $entry = [];
            if (isset($data['label']) && $data['label'] !== '') {
                $entry['label'] = $data['label'];
            }
            if (isset($data['uri']) && $data['uri'] !== '') {
                $entry['uri'] = $data['uri'];
            }
            if ($entry !== []) {
                $clean[$locale] = $entry;
            }
        }

        $this->translations = $clean;

        return $this;
    }

    public function getLabelForLocale(string $locale): string
    {
        return $this->translations[$locale]['label'] ?? $this->label;
    }

    public function getUriForLocale(string $locale): ?string
    {
        return $this->translations[$locale]['uri'] ?? $this->uri;
    }

    /**
     * @return array<int, string>
     */
    public function getDependentActiveRoutes(): array
    {
        return $this->dependentActiveRoutes;
    }

    /**
     * @param array<int, string> $routes
     */
    public function setDependentActiveRoutes(array $routes): self
    {
        $clean = [];
        foreach ($routes as $value) {
            if ($value === '') {
                continue;
            }
            if (in_array($value, $clean, true)) {
                continue;
            }

            $clean[] = $value;
        }

        $this->dependentActiveRoutes = $clean;

        return $this;
    }

    public function isActiveForRoute(?string $currentRoute): bool
    {
        if ($currentRoute === null || $currentRoute === '') {
            return false;
        }
        if ($this->route === $currentRoute) {
            return true;
        }

        return in_array($currentRoute, $this->dependentActiveRoutes, true);
    }

    public function getType(): string
    {
        return $this->type->value;
    }

    public function setType(string $type): self
    {
        $resolved = MenuItemType::tryFrom($type);
        if ($resolved === null) {
            throw new InvalidArgumentException('Invalid type: ' . $type);
        }

        $this->type = $resolved;

        return $this;
    }

    public function getColumn(): int
    {
        return $this->column;
    }

    public function setColumn(int $column): self
    {
        $this->column = max(0, $column);

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getCssClasses(): array
    {
        return $this->cssClasses;
    }

    /**
     * @param array<int, string> $classes
     */
    public function setCssClasses(array $classes): self
    {
        $clean = [];
        foreach ($classes as $value) {
            if ($value === '') {
                continue;
            }
            $trimmed = mb_trim($value);
            if ($trimmed === '' || in_array($trimmed, $clean, true)) {
                continue;
            }

            $clean[] = $trimmed;
        }

        $this->cssClasses = $clean;

        return $this;
    }

    public function getPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getUnpublishedAt(): ?DateTimeImmutable
    {
        return $this->unpublishedAt;
    }

    public function setUnpublishedAt(?DateTimeImmutable $unpublishedAt): self
    {
        $this->unpublishedAt = $unpublishedAt;

        return $this;
    }

    public function isPublishedAt(DateTimeImmutable $now): bool
    {
        if ($this->publishedAt !== null && $now < $this->publishedAt) {
            return false;
        }

        return !($this->unpublishedAt !== null && $now >= $this->unpublishedAt);
    }

    public function isLabelTranslated(): bool
    {
        return $this->labelTranslated;
    }

    public function setLabelTranslated(bool $value): self
    {
        $this->labelTranslated = $value;

        return $this;
    }

    public function getTranslationDomain(): ?string
    {
        return $this->translationDomain;
    }

    public function setTranslationDomain(?string $domain): self
    {
        $this->translationDomain = $domain === null || $domain === '' ? null : $domain;

        return $this;
    }

    public function getParent(): ?MenuItemInterface
    {
        return $this->parent;
    }

    public function setParent(?MenuItemInterface $parent): self
    {
        if ($parent !== null && !$parent instanceof self) {
            throw new InvalidArgumentException('Parent must be ' . self::class);
        }

        $this->parent = $parent;

        return $this;
    }

    /**
     * @return iterable<self>
     */
    public function getChildren(): iterable
    {
        return $this->children;
    }

    public function addChild(MenuItemInterface $child): self
    {
        if (!$child instanceof self) {
            throw new InvalidArgumentException('Child must be ' . self::class);
        }

        $this->children[] = $child;
        $child->setParent($this);

        return $this;
    }

    /**
     * @param iterable<self> $children
     */
    public function addChildren(iterable $children): self
    {
        foreach ($children as $child) {
            $this->addChild($child);
        }

        return $this;
    }

    public function removeChild(MenuItemInterface $child): self
    {
        if (!$child instanceof self) {
            return $this;
        }

        $childId = $child->getId();
        $filtered = array_filter($this->children, static function (self $node) use ($childId): bool {
            return $node->getId() !== $childId;
        });
        $this->children = array_values($filtered);

        return $this;
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $children = array_map(static function (self $node): array {
            return $node->toArray();
        }, $this->children);

        return [
            'id' => $this->id,
            'menuName' => $this->menuName,
            'label' => $this->label,
            'uri' => $this->uri,
            'route' => $this->route,
            'routeParams' => $this->routeParams,
            'icon' => $this->icon,
            'iconImage' => $this->iconImage,
            'attributes' => $this->attributes,
            'position' => $this->position,
            'active' => $this->active,
            'isDisplayed' => $this->isDisplayed,
            'requiredRoles' => $this->requiredRoles,
            'allowedUsers' => $this->allowedUsers,
            'target' => $this->target,
            'visibility' => $this->visibility->value,
            'translations' => $this->translations,
            'dependentActiveRoutes' => $this->dependentActiveRoutes,
            'labelTranslated' => $this->labelTranslated,
            'translationDomain' => $this->translationDomain,
            'type' => $this->type->value,
            'column' => $this->column,
            'cssClasses' => $this->cssClasses,
            'publishedAt' => $this->publishedAt?->format(DateTimeInterface::ATOM),
            'unpublishedAt' => $this->unpublishedAt?->format(DateTimeInterface::ATOM),
            'children' => $children,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, ?self $parent = null): self
    {
        $publishedAt = null;
        $unpublishedAt = null;
        $rawPublishedAt = $data['publishedAt'] ?? null;
        if (is_string($rawPublishedAt) && $rawPublishedAt !== '') {
            $publishedAt = new DateTimeImmutable($rawPublishedAt);
        }
        $rawUnpublishedAt = $data['unpublishedAt'] ?? null;
        if (is_string($rawUnpublishedAt) && $rawUnpublishedAt !== '') {
            $unpublishedAt = new DateTimeImmutable($rawUnpublishedAt);
        }

        $rawVisibility = $data['visibility'] ?? MenuItemVisibility::ALWAYS->value;
        if (!is_string($rawVisibility) && !$rawVisibility instanceof MenuItemVisibility) {
            throw new InvalidArgumentException('visibility must be string or MenuItemVisibility');
        }
        $rawType = $data['type'] ?? MenuItemType::LINK->value;
        if (!is_string($rawType) && !$rawType instanceof MenuItemType) {
            throw new InvalidArgumentException('type must be string or MenuItemType');
        }

        $item = new self(
            id: self::requireId($data, 'id'),
            menuName: self::requireString($data, 'menuName'),
            label: self::requireString($data, 'label'),
            uri: self::optionalString($data, 'uri'),
            route: self::optionalString($data, 'route'),
            routeParams: self::stringKeyedArray($data, 'routeParams'),
            icon: self::optionalString($data, 'icon'),
            iconImage: self::optionalString($data, 'iconImage'),
            attributes: self::stringKeyedArray($data, 'attributes'),
            position: self::optionalInt($data, 'position', 0),
            active: self::optionalBool($data, 'active', true),
            isDisplayed: self::optionalBool($data, 'isDisplayed', true),
            requiredRoles: self::stringList($data, 'requiredRoles'),
            allowedUsers: self::stringList($data, 'allowedUsers'),
            target: self::optionalString($data, 'target'),
            visibility: $rawVisibility,
            translations: self::translationsFromArray($data),
            dependentActiveRoutes: self::stringList($data, 'dependentActiveRoutes'),
            labelTranslated: self::optionalBool($data, 'labelTranslated', false),
            translationDomain: self::optionalString($data, 'translationDomain'),
            type: $rawType,
            column: self::optionalInt($data, 'column', 0),
            cssClasses: self::stringList($data, 'cssClasses'),
            publishedAt: $publishedAt,
            unpublishedAt: $unpublishedAt,
        );

        if ($parent !== null) {
            $item->parent = $parent;
        }

        $rawChildren = $data['children'] ?? [];
        if (is_iterable($rawChildren)) {
            foreach ($rawChildren as $childData) {
                if (!is_array($childData)) {
                    continue;
                }
                /** @var array<string, mixed> $childData */
                $item->children[] = self::fromArray($childData, $item);
            }
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function requireId(array $data, string $key): int|string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException(sprintf('"%s" must be a string or int', $key));
        }
        if (is_string($value) && $value === '') {
            throw new InvalidArgumentException(sprintf('"%s" must be a non-empty string', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function requireString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException(sprintf('"%s" must be a non-empty string', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf('"%s" must be a string or null', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function optionalInt(array $data, string $key, int $default): int
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return $default;
        }
        if (!is_int($value)) {
            throw new InvalidArgumentException(sprintf('"%s" must be an int', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function optionalBool(array $data, string $key, bool $default): bool
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return $default;
        }
        if (!is_bool($value)) {
            throw new InvalidArgumentException(sprintf('"%s" must be a bool', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function stringKeyedArray(array $data, string $key): array
    {
        $value = $data[$key] ?? [];
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf('"%s" must be an array', $key));
        }
        $clean = [];
        foreach ($value as $k => $v) {
            if (!is_string($k)) {
                throw new InvalidArgumentException(sprintf('"%s" must be a string-keyed array', $key));
            }
            $clean[$k] = $v;
        }

        return $clean;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    private static function stringList(array $data, string $key): array
    {
        $value = $data[$key] ?? [];
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf('"%s" must be an array', $key));
        }
        $clean = [];
        foreach ($value as $v) {
            if (!is_string($v)) {
                throw new InvalidArgumentException(sprintf('"%s" must contain only strings', $key));
            }
            $clean[] = $v;
        }

        return $clean;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, array{label?: string, uri?: string}>
     */
    private static function translationsFromArray(array $data): array
    {
        $value = $data['translations'] ?? [];
        if (!is_array($value)) {
            throw new InvalidArgumentException('"translations" must be an array');
        }
        $clean = [];
        foreach ($value as $locale => $entry) {
            if (!is_string($locale) || $locale === '' || !is_array($entry)) {
                continue;
            }
            $node = [];
            if (isset($entry['label']) && is_string($entry['label']) && $entry['label'] !== '') {
                $node['label'] = $entry['label'];
            }
            if (isset($entry['uri']) && is_string($entry['uri']) && $entry['uri'] !== '') {
                $node['uri'] = $entry['uri'];
            }
            if ($node !== []) {
                $clean[$locale] = $node;
            }
        }

        return $clean;
    }
}
