<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\DependencyInjection;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Helper\AuthorizationUuidBinaryType;
use Dbp\Relay\CoreBundle\Extension\ExtensionTrait;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class DbpRelayAuthorizationExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait;

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
    }

    public function prepend(ContainerBuilder $container): void
    {
        $configs = $container->getExtensionConfig($this->getAlias());
        $config = $this->processConfiguration(new Configuration(), $configs);

        foreach (['doctrine', 'doctrine_migrations'] as $extKey) {
            if (!$container->hasExtension($extKey)) {
                throw new \Exception("'".$this->getAlias()."' requires the '$extKey' bundle to be loaded!");
            }
        }

        $container->prependExtensionConfig('doctrine', [
            'dbal' => [
                'connections' => [
                    'dbp_relay_authorization_bundle' => [
                        'url' => $config[Configuration::DATABASE_URL] ?? '',
                    ],
                ],
            ],
            'orm' => [
                'entity_managers' => [
                    'dbp_relay_authorization_bundle' => [
                        'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                        'connection' => 'dbp_relay_authorization_bundle',
                        'mappings' => [
                            'dbp_relay_authorization' => [
                                'type' => 'attribute',
                                'dir' => __DIR__.'/../Entity',
                                'prefix' => 'Dbp\Relay\AuthorizationBundle\Entity',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->registerEntityManager($container, 'dbp_relay_authorization_bundle');

        $container->prependExtensionConfig('doctrine_migrations', [
            'migrations_paths' => [
                'Dbp\Relay\AuthorizationBundle\Migrations' => __DIR__.'/../Migrations',
            ],
        ]);
    }
}
