<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\DependencyInjection;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Service\UserAttributeProvider;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const DATABASE_URL = 'database_url';
    public const CREATE_GROUPS_POLICY = 'create_groups_policy';
    public const MANAGE_RESOURCE_COLLECTION_POLICIES = 'manage_resource_collection_policies';
    public const ADD_RESOURCE_POLICIES = 'add_resource_policies';
    public const RESOURCE_CLASS = 'resource_class';
    public const POLICY = 'policy';
    public const DYNAMIC_GROUPS = 'dynamic_groups';
    public const IDENTIFIER = 'identifier';
    public const IS_CURRENT_USER_GROUP_MEMBER_EXPRESSION = 'is_user_group_member';

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
                ->arrayNode(self::DYNAMIC_GROUPS)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode(self::IDENTIFIER)
                                ->cannotBeEmpty()
                                ->isRequired()
                                ->validate()
                                    ->ifTrue(function ($value) {
                                        return strlen($value) > 40;
                                    })
                                    ->thenInvalid('The max length of dynamic user group identifiers is 40')
                                ->end()
                                ->validate()
                                    ->ifTrue(function ($value) {
                                        return str_starts_with($value, '@');
                                    })
                                    ->thenInvalid('Dynamic group identifiers must not start with a @ character')
                                ->end()
                                ->validate()
                                ->ifTrue(function ($value) {
                                    return $value === AuthorizationService::DYNAMIC_GROUP_IDENTIFIER_EVERYBODY;
                                })
                                ->thenInvalid('Dynamic user group identifier \''.AuthorizationService::DYNAMIC_GROUP_IDENTIFIER_EVERYBODY.'\' is reserved')
                                ->end()
                                ->info('The dynamic user group identifier')
                            ->end()
                            ->scalarNode(self::IS_CURRENT_USER_GROUP_MEMBER_EXPRESSION)
                                ->cannotBeEmpty()
                                ->isRequired()
                                ->info('The expression defining whether the current user is a member of this dynamic user group, or not')
                           ->end()
                       ->end()
                    ->end()
                ->end()
                ->arrayNode(self::MANAGE_RESOURCE_COLLECTION_POLICIES)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode(self::RESOURCE_CLASS)
                                ->cannotBeEmpty()
                                ->isRequired()
                                ->validate()
                                ->ifTrue(function ($value) {
                                    return str_contains($value, UserAttributeProvider::SEPARATOR);
                                })
                                ->thenInvalid('Resource class must not contain reserved character \''.UserAttributeProvider::SEPARATOR.'\'')
                                ->end()
                                ->info('The resource class for which the policy applies')
                            ->end()
                            ->scalarNode(self::POLICY)
                                ->cannotBeEmpty()
                                ->info('The policy that determines whether the currently logger-in user is authorized to manage this resource class\' resource collection.')
                                ->defaultValue('false')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode(self::ADD_RESOURCE_POLICIES)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode(self::RESOURCE_CLASS)
                                ->cannotBeEmpty()
                                ->isRequired()
                                ->validate()
                                ->ifTrue(function ($value) {
                                    return str_contains($value, UserAttributeProvider::SEPARATOR);
                                })
                                ->thenInvalid('Resource class must not contain reserved character \''.UserAttributeProvider::SEPARATOR.'\'')
                                ->end()
                                ->info('The resource class for which the policy applies')
                            ->end()
                            ->scalarNode(self::POLICY)
                                ->cannotBeEmpty()
                                ->info('The policy that determines whether the currently logger-in user is authorized to add the given resource (of resource class).')
                                ->defaultValue('false')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
