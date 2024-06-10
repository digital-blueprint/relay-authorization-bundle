<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Tests\EventSubscriber\TestGetAvailableResourceClassActionsEventSubscriber;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

abstract class AbstractTestCase extends WebTestCase
{
    protected const CURRENT_USER_IDENTIFIER = 'userIdentifier';
    protected const ANOTHER_USER_IDENTIFIER = 'anotherUserIdentifier';

    protected TestEntityManager $testEntityManager;
    protected AuthorizationService $authorizationService;
    protected InternalResourceActionGrantService $internalResourceActionGrantService;

    protected function setUp(): void
    {
        $this->testEntityManager = new TestEntityManager(self::bootKernel());

        $eventDispatcher = new EventDispatcher();
        $this->internalResourceActionGrantService = new InternalResourceActionGrantService(
            $this->testEntityManager->getEntityManager(), $eventDispatcher);
        $this->authorizationService = new AuthorizationService(
            $this->internalResourceActionGrantService, new GroupService($this->testEntityManager->getEntityManager()));
        TestAuthorizationService::setUp($this->authorizationService,
            self::CURRENT_USER_IDENTIFIER, $this->getDefaultUserAttributes());
        $this->authorizationService->setConfig($this->getTestConfig());
        $eventDispatcher->addSubscriber(new TestGetAvailableResourceClassActionsEventSubscriber());
        $eventDispatcher->addSubscriber($this->authorizationService);
    }

    protected function login(string $userIdentifier, ?array $userAttributes = null): void
    {
        TestAuthorizationService::setUp($this->authorizationService, $userIdentifier,
            $userAttributes ?? $this->getDefaultUserAttributes());
    }

    protected function selectWhere(array $results, callable $where): array
    {
        return array_filter($results, $where);
    }

    protected function containsResource(array $resources, mixed $resource): bool
    {
        foreach ($resources as $resource) {
            if ($resource->getIdentifier() === $resource->getIdentifier()) {
                return true;
            }
        }

        return false;
    }

    protected function getTestConfig(): array
    {
        return [
            'create_groups_policy' => 'user.get("MAY_CREATE_GROUPS")',
        ];
    }

    protected function getDefaultUserAttributes(): array
    {
        return [
            'MAY_CREATE_GROUPS' => false,
        ];
    }
}
