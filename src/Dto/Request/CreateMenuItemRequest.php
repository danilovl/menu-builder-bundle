<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateMenuItemRequest
{
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
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $menuName,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $label,
        public ?string $uri = null,
        public ?string $route = null,
        public array $routeParams = [],
        public ?string $icon = null,
        #[Assert\Length(max: 1_024)]
        public ?string $iconImage = null,
        public array $attributes = [],
        public ?int $position = null,
        public bool $active = true,
        public bool $isDisplayed = true,
        public array $requiredRoles = [],
        public array $allowedUsers = [],
        public ?string $target = null,
        public string $visibility = 'always',
        public array $translations = [],
        public array $dependentActiveRoutes = [],
        public bool $labelTranslated = false,
        public ?string $translationDomain = null,
        public string|int|null $parentId = null,
        public string $type = 'link',
        public int $column = 0,
        public array $cssClasses = [],
        public ?string $publishedAt = null,
        public ?string $unpublishedAt = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
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
            'visibility' => $this->visibility,
            'translations' => $this->translations,
            'dependentActiveRoutes' => $this->dependentActiveRoutes,
            'labelTranslated' => $this->labelTranslated,
            'translationDomain' => $this->translationDomain,
            'parentId' => $this->parentId,
            'type' => $this->type,
            'column' => $this->column,
            'cssClasses' => $this->cssClasses,
            'publishedAt' => $this->publishedAt,
            'unpublishedAt' => $this->unpublishedAt,
        ];
    }
}
