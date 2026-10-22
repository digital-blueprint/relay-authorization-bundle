<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\TestUtils;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Service\UserGroupService;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TestResourceActionGrantServiceFactory
{
    /**
     * Sets up the in-memory database and returns a TestEntityManager to access it.
     *
     * Meant to be used with kernel tests and unit tests which need a database.
     */
    public static function createTestEntityManager(ContainerInterface $container): TestEntityManager
    {
        return new TestEntityManager($container);
    }

    /**
     * Sets up the container's ResourceActionsGrant service.
     *
     * Meant to be used with kernel tests.
     */
    public static function setUpResourceActionGrantService(
        ContainerInterface $container,
        ?array $testConfig = null,
        array $availableResourceClassActions = []): void
    {
        $resourceActionGrantService = $container->get(ResourceActionGrantService::class);
        assert($resourceActionGrantService instanceof ResourceActionGrantService);
        $authorizationService = $resourceActionGrantService->getAuthorizationService();
        if ($testConfig !== null) {
            $authorizationService->setConfig($testConfig);
        }

        self::ensureActionsAndRoles($authorizationService, $availableResourceClassActions);
    }

    /**
     * Creates and sets up a ResourceActionGrantService for testing.
     *
     *  Meant to be used with unit tests which need a database.
     */
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

    /**
     * Creates and sets up an AuthorizationService for testing.
     */
    public static function createTestAuthorizationService(
        EntityManagerInterface $entityManager, EventDispatcherInterface $eventDispatcher,
        ?InternalResourceActionGrantService $internalResourceActionGrantService = null, ?array $testConfig = null,
        array $availableResourceClassActions = [],
        ?string $currentUserIdentifier = TestAuthorizationService::TEST_USER_IDENTIFIER,
        array $currentUserAttributes = [], bool $isServiceAccount = false): AuthorizationService
    {
        $internalResourceActionGrantService ??= new InternalResourceActionGrantService(
            $entityManager, $eventDispatcher);
        $userGroupService = new UserGroupService($entityManager);
        $userGroupService->setLogger(new NullLogger());
        $authorizationService = new AuthorizationService(
            $internalResourceActionGrantService, $userGroupService);
        TestAuthorizationService::setUp($authorizationService, $currentUserIdentifier,
            $currentUserAttributes, isServiceAccount: $isServiceAccount);
        $authorizationService->setConfig($testConfig ?? self::getTestConfig());
        $authorizationService->setLogger(new NullLogger());

        self::ensureActionsAndRoles($authorizationService, $availableResourceClassActions);

        return $authorizationService;
    }

    private static function getTestConfig(): array
    {
        return [
            'database_url' => 'sqlite:///:memory:',
            'create_groups_policy' => 'false',
        ];
    }

    private static function ensureActionsAndRoles(AuthorizationService $authorizationService, array $availableResourceClassActions): void
    {
        AuthorizationService::addOrUpdateActionsAndRoles($authorizationService->getEntityManager());
        foreach ($availableResourceClassActions as $resourceClass => $actions) {
            $authorizationService->addOrUpdateAvailableResourceClassActions($resourceClass,
                $actions[0] ?? [],
                $actions[1] ?? []);
        }
        $authorizationService->updateResourceCollectionOwnerPolicyGrants();
    }
}
