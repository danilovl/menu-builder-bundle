<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\DependencyInjection;

use InvalidArgumentException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\{ContainerBuilder, Definition};
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Extension\Extension;

class MenuBuilderExtension extends Extension
{
    public const string ALIAS = 'danilovl_menu_builder';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration;
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('danilovl_menu_builder.api.prefix', $config['api']['prefix']);

        $configDef = new Definition(MenuBuilderConfig::class, [
            $config['storage']['driver'],
            $config['storage']['redis_dsn'],
            $config['storage']['ttl'],
            $config['storage']['key_prefix'],
            $config['storage']['pool'],
            $config['soft_delete'],
            $config['api']['prefix'],
            $config['api']['public_cache_ttl'],
            $config['route_catalog']['preload_limit'],
            $config['render']['default_template'],
        ]);
        $configDef->setPublic(false);
        $container->setDefinition(MenuBuilderConfig::class, $configDef);

        $fileLocator = new FileLocator(__DIR__ . '/../Resources/config');
        $loader = new YamlFileLoader($container, $fileLocator);
        $loader->load('services.yaml');

        $storageServiceId = match ($config['storage']['driver']) {
            'doctrine' => 'Danilovl\MenuBuilderBundle\Storage\DoctrineStorage',
            'cache' => 'Danilovl\MenuBuilderBundle\Storage\CacheStorage',
            'redis' => 'Danilovl\MenuBuilderBundle\Storage\RedisStorage',
            default => throw new InvalidArgumentException('Unsupported storage driver'),
        };

        $container
            ->setAlias('Danilovl\MenuBuilderBundle\Storage\StorageInterface', $storageServiceId)
            ->setPublic(false);
    }

    public function getAlias(): string
    {
        return self::ALIAS;
    }
}
