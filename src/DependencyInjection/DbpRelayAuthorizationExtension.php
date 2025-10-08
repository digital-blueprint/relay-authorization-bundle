<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\DependencyInjection;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Helper\AuthorizationUuidBinaryType;
use Dbp\Relay\CoreBundle\Doctrine\DoctrineConfiguration;
use Dbp\Relay\CoreBundle\Extension\ExtensionTrait;
use DoctrineExtensions\Query\Mysql\Replace;
use DoctrineExtensions\Query\Mysql\Unhex;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class DbpRelayAuthorizationExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait;

    public const AUTHORIZATION_ENTITY_MANAGER_ID = 'dbp_relay_authorization_bundle';
    public const AUTHORIZATION_DB_CONNECTION_ID = 'dbp_relay_authorization_bundle';

    public function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.yaml');

        $typeDefinition = $container->getParameter('doctrine.dbal.connection_factory.types');
        $typeDefinition['relay_authorization_uuid_binary'] = ['class' => AuthorizationUuidBinaryType::class];
        $container->setParameter('doctrine.dbal.connection_factory.types', $typeDefinition);

        $definition = $container->getDefinition(AuthorizationService::class);
        $definition->addMethodCall('setConfig', [$mergedConfig]);

        $this->addResourceClassDirectory($container, __DIR__.'/../Entity');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $configs = $container->getExtensionConfig($this->getAlias());
        $config = $this->processConfiguration(new Configuration(), $configs);

        DoctrineConfiguration::prependEntityManagerConfig($container, self::AUTHORIZATION_ENTITY_MANAGER_ID,
            $config[Configuration::DATABASE_URL] ?? '',
            __DIR__.'/../Entity',
            'Dbp\Relay\AuthorizationBundle\Entity',
            self::AUTHORIZATION_DB_CONNECTION_ID);
        DoctrineConfiguration::prependMigrationsConfig($container,
            __DIR__.'/../Migrations',
            'Dbp\Relay\AuthorizationBundle\Migrations');

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'entity_managers' => [
                    self::AUTHORIZATION_ENTITY_MANAGER_ID => [
                        'dql' => [
                            'string_functions' => [
                                'unhex' => Unhex::class,
                                'replace' => Replace::class,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
