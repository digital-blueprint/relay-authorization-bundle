<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\TestUtils;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class TestResourceActionGrantServiceFactory
{
    public static function createTestEntityManager(KernelInterface $kernel): TestEntityManager
    {
        return new TestEntityManager($kernel);
    }

    public static function createTestResourceActionGrantService(EntityManagerInterface $entityManager,
        string $currentUserIdentifier = TestAuthorizationService::TEST_USER_IDENTIFIER, array $currentUserAttributes = [],
        ?EventSubscriberInterface $eventSubscriber = null): ResourceActionGrantService
    {
        $eventDispatcher = new EventDispatcher();
        $internalResourceActionGrantService = new InternalResourceActionGrantService($entityManager, $eventDispatcher);
        $authorizationService = new AuthorizationService(
            $internalResourceActionGrantService, new GroupService($entityManager));
        TestAuthorizationService::setUp($authorizationService, $currentUserIdentifier, $currentUserAttributes);
        $authorizationService->setConfig(self::getTestConfig());

        if ($eventSubscriber !== null) {
            $eventDispatcher->addSubscriber($eventSubscriber);
        }
        $eventDispatcher->addSubscriber($authorizationService);

        return new ResourceActionGrantService($authorizationService);
    }

    private static function getTestConfig(): array
    {
        return [
            'database_url' => 'sqlite:///:memory:',
            'create_groups_policy' => 'false',
        ];
    }
    //    private static function createEntityManager(): EntityManagerInterface
    //    {
    //        try {
    //            if (!Type::hasType('relay_authorization_uuid_binary')) {
    //                Type::addType('relay_authorization_uuid_binary', AuthorizationUuidBinaryType::class);
    //            }
    //
    //            $config = ORMSetup::createAnnotationMetadataConfiguration([__DIR__.'/../../src/Entity'], true);
    //            $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
    //            $connection = DriverManager::getConnection(
    //                [
    //                    'driver' => 'pdo_sqlite',
    //                    'memory' => true,
    //                ], $config
    //            );
    //
    //            $connection->executeQuery('CREATE TABLE authorization_group_members (identifier BINARY(16) NOT NULL, parent_group_identifier BINARY(16) DEFAULT NULL, child_group_identifier BINARY(16) DEFAULT NULL, user_identifier VARCHAR(40) DEFAULT NULL, PRIMARY KEY(identifier))');
    //            $connection->executeQuery('CREATE TABLE authorization_groups (identifier BINARY(16) NOT NULL, name VARCHAR(64) NOT NULL, PRIMARY KEY(identifier))');
    //            $connection->executeQuery('CREATE TABLE authorization_resource_action_grants (identifier BINARY(16) NOT NULL, authorization_resource_identifier BINARY(16) DEFAULT NULL, group_identifier BINARY(16) DEFAULT NULL, action VARCHAR(40) NOT NULL, user_identifier VARCHAR(40) DEFAULT NULL, dynamic_group_identifier VARCHAR(40) DEFAULT NULL, PRIMARY KEY(identifier))');
    //            $connection->executeQuery('CREATE TABLE authorization_resources (identifier BINARY(16) NOT NULL, resource_class VARCHAR(40) NOT NULL, resource_identifier VARCHAR(40) DEFAULT NULL, PRIMARY KEY(identifier))');
    //
    //            return new EntityManager($connection, $config);
    //        } catch (\Exception $exception) {
    //            throw new \RuntimeException($exception->getMessage());
    //        }
    //    }
}
