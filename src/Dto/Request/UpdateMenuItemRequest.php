<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

readonly class UpdateMenuItemRequest
{
    /**
     * @param array<string, mixed>|null $routeParams
     * @param array<string, mixed>|null $attributes
     * @param array<int, string>|null $requiredRoles
     * @param array<int, string>|null $allowedUsers
     * @param array<string, array{label?: string, uri?: string}>|null $translations
     * @param array<int, string>|null $dependentActiveRoutes
     * @param array<int, string>|null $cssClasses
     */
    public function __construct(
        #[Assert\Length(max: 255)]
        public ?string $label = null,
        public ?string $uri = null,
        public ?string $route = null,
        public ?array $routeParams = null,
        public ?string $icon = null,
        #[Assert\Length(max: 1_024)]
        public ?string $iconImage = null,
        public ?array $attributes = null,
        public ?int $position = null,
        public ?bool $active = null,
        public ?bool $isDisplayed = null,
        public ?array $requiredRoles = null,
        public ?array $allowedUsers = null,
        public ?string $target = null,
        public ?string $visibility = null,
        public ?array $translations = null,
        public ?array $dependentActiveRoutes = null,
        public ?bool $labelTranslated = null,
        public ?string $translationDomain = null,
        public ?string $type = null,
        public ?int $column = null,
        public ?array $cssClasses = null,
        public ?string $publishedAt = null,
        public ?string $unpublishedAt = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];
        $properties = [
            'label',
            'uri',
            'route',
            'routeParams',
            'icon',
            'iconImage',
            'attributes',
            'position',
            'active',
            'isDisplayed',
            'requiredRoles',
            'allowedUsers',
            'target',
            'visibility',
            'translations',
            'dependentActiveRoutes',
            'labelTranslated',
            'translationDomain',
            'type',
            'column',
            'cssClasses',
            'publishedAt',
            'unpublishedAt'
        ];

        foreach ($properties as $property) {
            $value = $this->{$property};
            if ($value !== null) {
                $data[$property] = $value;
            }
        }

        return $data;
    }
}
