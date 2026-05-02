<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\DependencyInjection;

readonly class MenuBuilderConfig
{
    public function __construct(
        public string $storageDriver,
        public ?string $storageRedisDsn,
        public ?int $storageTtl,
        public string $storageKeyPrefix,
        public string $storagePool,
        public bool $softDelete,
        public string $apiPrefix,
        public int $apiPublicCacheTtl,
        public int $routeCatalogPreloadLimit,
        public string $renderDefaultTemplate
    ) {}
}
