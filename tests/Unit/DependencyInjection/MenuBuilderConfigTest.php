<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\DependencyInjection;

use Danilovl\MenuBuilderBundle\DependencyInjection\MenuBuilderConfig;
use PHPUnit\Framework\TestCase;

final class MenuBuilderConfigTest extends TestCase
{
    public function testConstructorAssignsAllProperties(): void
    {
        $config = new MenuBuilderConfig(
            storageDriver: 'array',
            storageRedisDsn: 'redis://localhost:6379',
            storageTtl: 600,
            storageKeyPrefix: 'menu_builder.',
            storagePool: 'cache.app',
            softDelete: true,
            apiPrefix: '/api/menu',
            apiPublicCacheTtl: 30,
            routeCatalogPreloadLimit: 200,
            renderDefaultTemplate: '@MenuBuilder/menu/default.html.twig',
        );

        $this->assertSame('array', $config->storageDriver);
        $this->assertSame('redis://localhost:6379', $config->storageRedisDsn);
        $this->assertSame(600, $config->storageTtl);
        $this->assertSame('menu_builder.', $config->storageKeyPrefix);
        $this->assertSame('cache.app', $config->storagePool);
        $this->assertTrue($config->softDelete);
        $this->assertSame('/api/menu', $config->apiPrefix);
        $this->assertSame(30, $config->apiPublicCacheTtl);
        $this->assertSame(200, $config->routeCatalogPreloadLimit);
        $this->assertSame('@MenuBuilder/menu/default.html.twig', $config->renderDefaultTemplate);
    }

    public function testNullableFieldsAcceptNull(): void
    {
        $config = new MenuBuilderConfig(
            storageDriver: 'doctrine',
            storageRedisDsn: null,
            storageTtl: null,
            storageKeyPrefix: 'mb.',
            storagePool: 'cache.app',
            softDelete: false,
            apiPrefix: '/api',
            apiPublicCacheTtl: 0,
            routeCatalogPreloadLimit: 0,
            renderDefaultTemplate: 'template.html.twig',
        );

        $this->assertNull($config->storageRedisDsn);
        $this->assertNull($config->storageTtl);
        $this->assertFalse($config->softDelete);
    }
}
