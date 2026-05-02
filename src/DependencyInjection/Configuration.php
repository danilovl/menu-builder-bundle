<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(MenuBuilderExtension::ALIAS);
        $root = $treeBuilder->getRootNode();

        $root
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('storage')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('driver')
                            ->values(['doctrine', 'cache', 'redis'])
                            ->defaultValue('doctrine')
                        ->end()
                        ->scalarNode('redis_dsn')->defaultNull()->end()
                        ->scalarNode('pool')->defaultValue('cache.app')->end()
                        ->scalarNode('key_prefix')->defaultValue('danilovl_menu_builder.')->end()
                        ->integerNode('ttl')->defaultNull()->end()
                    ->end()
                ->end()
                ->booleanNode('soft_delete')->defaultTrue()->end()
                ->arrayNode('api')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('prefix')->defaultValue('/api/menu')->end()
                        ->integerNode('public_cache_ttl')->defaultValue(0)->end()
                    ->end()
                ->end()
                ->arrayNode('route_catalog')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('preload_limit')->defaultValue(500)->min(0)->end()
                    ->end()
                ->end()
                ->arrayNode('render')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('default_template')->defaultValue('@MenuBuilder/menu/default.html.twig')->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
