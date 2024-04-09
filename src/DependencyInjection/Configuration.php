<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_authorization');

        $treeBuilder->getRootNode()
            ->children()
               ->scalarNode('database_url')
                  ->isRequired()
                  ->info('The database DSN')
                  ->cannotBeEmpty()
               ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
