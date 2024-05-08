<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\TestUtils;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Helper\AuthorizationUuidBinaryType;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;

class TestResourceActionGrantServiceFactory
{
    private static bool $haveCustomTypesBeenAdded = false;

    public static function createTestResourceActionGrantService(string $currentUserIdentifier = TestAuthorizationService::TEST_USER_IDENTIFIER,
        array $currentUserAttributes = []): ResourceActionGrantService
    {
        $entityManager = self::createEntityManager();
        $internalResourceActionGrantService = new InternalResourceActionGrantService($entityManager);
        $authorizationService = new AuthorizationService(
            $internalResourceActionGrantService, new GroupService($entityManager));
        TestAuthorizationService::setUp($authorizationService, $currentUserIdentifier, $currentUserAttributes);
        $authorizationService->setConfig(self::getTestConfig());

        return new ResourceActionGrantService($authorizationService);
    }

    private static function createEntityManager(): EntityManagerInterface
    {
        try {
            if (!self::$haveCustomTypesBeenAdded) {
                Type::addType('relay_authorization_uuid_binary', AuthorizationUuidBinaryType::class);
                self::$haveCustomTypesBeenAdded = true;
            }

            $config = ORMSetup::createAnnotationMetadataConfiguration([__DIR__.'/../../src/Entity'], true);
            $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
            $connection = DriverManager::getConnection(
                [
                    'driver' => 'pdo_sqlite',
                    'memory' => true,
                ], $config
            );

            $connection->executeQuery('CREATE TABLE authorization_group_members (identifier BINARY(16) NOT NULL, parent_group_identifier BINARY(16) DEFAULT NULL, child_group_identifier BINARY(16) DEFAULT NULL, user_identifier VARCHAR(40) DEFAULT NULL, PRIMARY KEY(identifier))');
            $connection->executeQuery('CREATE TABLE authorization_groups (identifier BINARY(16) NOT NULL, name VARCHAR(64) NOT NULL, PRIMARY KEY(identifier))');
            $connection->executeQuery('CREATE TABLE authorization_resource_action_grants (identifier BINARY(16) NOT NULL, authorization_resource_identifier BINARY(16) DEFAULT NULL, group_identifier BINARY(16) DEFAULT NULL, action VARCHAR(40) NOT NULL, user_identifier VARCHAR(40) DEFAULT NULL, dynamic_group_identifier VARCHAR(40) DEFAULT NULL, PRIMARY KEY(identifier))');
            $connection->executeQuery('CREATE TABLE authorization_resources (identifier BINARY(16) NOT NULL, resource_class VARCHAR(40) NOT NULL, resource_identifier VARCHAR(40) DEFAULT NULL, PRIMARY KEY(identifier))');

            return new EntityManager($connection, $config);
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    private static function getTestConfig(): array
    {
        return [
            'database_url' => 'sqlite:///:memory:',
            'create_groups_policy' => 'false',
        ];
    }
}
