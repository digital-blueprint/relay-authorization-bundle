<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\TestUtils;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TestResourceActionGrantServiceFactory
{
    public static function createTestEntityManager(ContainerInterface $container): TestEntityManager
    {
        return new TestEntityManager($container);
    }

    public static function createTestResourceActionGrantService(EntityManagerInterface $entityManager,
        string $currentUserIdentifier = TestAuthorizationService::TEST_USER_IDENTIFIER, array $currentUserAttributes = [],
        ?EventDispatcher $eventDispatcher = null): ResourceActionGrantService
    {
        $eventDispatcher ??= new EventDispatcher();

        return new ResourceActionGrantService(self::createTestAuthorizationService(
            $entityManager, $eventDispatcher, null, null,
            $currentUserIdentifier, $currentUserAttributes));
    }

    public static function login(ResourceActionGrantService $resourceActionGrantService,
        ?string $currentUserIdentifier = TestAuthorizationService::TEST_USER_IDENTIFIER,
        array $currentUserAttributes = [], bool $isServiceAccount = false): void
    {
        TestAuthorizationService::setUp($resourceActionGrantService->getAuthorizationService(),
            $currentUserIdentifier, $currentUserAttributes, isServiceAccount: $isServiceAccount);
    }

    public static function logout(ResourceActionGrantService $resourceActionGrantService,
        array $defaultUserAttributes = []): void
    {
        TestAuthorizationService::setUp($resourceActionGrantService->getAuthorizationService(),
            null, $defaultUserAttributes, isAuthenticated: false);
    }

    public static function createTestAuthorizationService(
        EntityManagerInterface $entityManager, EventDispatcherInterface $eventDispatcher,
        ?InternalResourceActionGrantService $internalResourceActionGrantService = null, ?array $testConfig = null,
        ?string $currentUserIdentifier = TestAuthorizationService::TEST_USER_IDENTIFIER,
        array $currentUserAttributes = [], bool $isServiceAccount = false): AuthorizationService
    {
        $internalResourceActionGrantService ??= new InternalResourceActionGrantService($entityManager, $eventDispatcher);
        $authorizationService = new AuthorizationService(
            $internalResourceActionGrantService, new GroupService($entityManager), $entityManager);
        TestAuthorizationService::setUp($authorizationService, $currentUserIdentifier,
            $currentUserAttributes, isServiceAccount: $isServiceAccount);
        $authorizationService->setConfig($testConfig ?? self::getTestConfig());
        $authorizationService->setCache(new ArrayAdapter());
        $authorizationService->setLogger(new NullLogger());

        return $authorizationService;
    }

    private static function getTestConfig(): array
    {
        return [
            'database_url' => 'sqlite:///:memory:',
            'create_groups_policy' => 'false',
        ];
    }
}
