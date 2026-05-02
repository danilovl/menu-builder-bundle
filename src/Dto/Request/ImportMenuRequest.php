<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Dto\Request;

readonly class ImportMenuRequest
{
    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function __construct(
        public ?string $menu = null,
        public ?string $overrideMenuName = null,
        public array $items = []
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'menu' => $this->menu,
            'items' => $this->items,
        ];
    }
}
