<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Dto;

readonly class RouteInfo
{
    /**
     * @param string[] $methods
     */
    public function __construct(
        public string $name,
        public string $path,
        public array $methods
    ) {}

    /**
     * @return array{name: string, path: string, methods: string[]}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
            'methods' => $this->methods,
        ];
    }
}
