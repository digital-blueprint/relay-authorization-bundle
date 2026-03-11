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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TestResourceActionGrantServiceFactory
{
    public static function createTestEntityManager(ContainerInterface $container,
        ?array $testConfig = null, array $availableResourceClassActions = []): TestEntityManager
    {
        $testEntityManager = new TestEntityManager($container);

        if ($testConfig !== null || false === empty($availableResourceClassActions)) {
            $authorizationService = $container->get(AuthorizationService::class);
            assert($authorizationService instanceof AuthorizationService);
            if ($testConfig !== null) {
                $authorizationService->setConfig($testConfig);
            }
            self::setAvailableResourceClassActions($availableResourceClassActions, $authorizationService);
        }

        return $testEntityManager;
    }

    public static function createTestResourceActionGrantService(EntityManagerInterface $entityManager,
        ?array $testConfig = null, array $availableResourceClassActions = [],
        string $currentUserIdentifier = TestAuthorizationService::TEST_USER_IDENTIFIER, array $currentUserAttributes = [],
        ?EventDispatcher $eventDispatcher = null): ResourceActionGrantService
    {
        $eventDispatcher ??= new EventDispatcher();

        return new ResourceActionGrantService(self::createTestAuthorizationService(
            $entityManager, $eventDispatcher, null, $testConfig,
            $availableResourceClassActions,
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
        array $availableResourceClassActions = [],
        ?string $currentUserIdentifier = TestAuthorizationService::TEST_USER_IDENTIFIER,
        array $currentUserAttributes = [], bool $isServiceAccount = false): AuthorizationService
    {
        $internalResourceActionGrantService ??= new InternalResourceActionGrantService($entityManager, $eventDispatcher);
        $authorizationService = new AuthorizationService(
            $internalResourceActionGrantService, new GroupService($entityManager));
        TestAuthorizationService::setUp($authorizationService, $currentUserIdentifier,
            $currentUserAttributes, isServiceAccount: $isServiceAccount);
        $authorizationService->setConfig($testConfig ?? self::getTestConfig());
        $authorizationService->setLogger(new NullLogger());
        self::setAvailableResourceClassActions($availableResourceClassActions, $authorizationService);

        return $authorizationService;
    }

    private static function getTestConfig(): array
    {
        return [
            'database_url' => 'sqlite:///:memory:',
            'create_groups_policy' => 'false',
        ];
    }

    private static function setAvailableResourceClassActions(array $availableResourceClassActions, AuthorizationService $authorizationService): void
    {
        foreach ($availableResourceClassActions as $resourceClass => $actions) {
            $authorizationService->setAvailableResourceClassActions($resourceClass,
                $actions[0] ?? [],
                $actions[1] ?? []);
        }
        $authorizationService->updateManageResourceCollectionPolicyGrants();
    }
}
