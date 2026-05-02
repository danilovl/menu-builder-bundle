<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class MenuItem
{
    /**
     * @param array<int, string> $requiredRoles
     * @param array<int, string> $allowedUsers
     * @param array<int, string> $cssClasses
     */
    public function __construct(
        public string $menu,
        public string $label,
        public ?string $route = null,
        public int $position = 0,
        public ?string $icon = null,
        public ?string $iconImage = null,
        public string $type = 'link',
        public string $visibility = 'always',
        public array $requiredRoles = [],
        public array $allowedUsers = [],
        public array $cssClasses = [],
        public bool $labelTranslated = false,
        public ?string $translationDomain = null,
        public ?string $parentLabel = null
    ) {}
}
