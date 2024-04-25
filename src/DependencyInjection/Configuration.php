<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const CREATE_GROUPS_POLICY = 'create_groups_policy';
    public const RESOURCE_CLASSES = 'resource_classes';
    public const DATABASE_URL = 'database_url';
    public const RESOURCE_CLASS_IDENTIFIER = 'identifier';
    public const MANAGE_RESOURCE_COLLECTION_POLICY = 'manage_resource_collection_policy';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_authorization');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode(self::DATABASE_URL)
                  ->isRequired()
                  ->info('The database DSN')
                  ->cannotBeEmpty()
                ->end()
                ->scalarNode(self::CREATE_GROUPS_POLICY)
                    ->defaultValue('false')
                    ->info('The policy that determines whether the currently logger-in user is authorized to create new groups')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode(self::RESOURCE_CLASSES)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode(self::RESOURCE_CLASS_IDENTIFIER)
                                ->cannotBeEmpty()
                                ->isRequired()
                                ->info('The resource class identifier')
                            ->end()
                            ->scalarNode(self::MANAGE_RESOURCE_COLLECTION_POLICY)
                                ->cannotBeEmpty()
                                ->info('The policy that determines whether the currently logger-in user is authorized to manage this resource class\' resource collection.')
                                ->defaultValue('false')
                            ->end()
                        ->end()
                    ->end()
               ->end()
            ->end();

        return $treeBuilder;
    }
}
