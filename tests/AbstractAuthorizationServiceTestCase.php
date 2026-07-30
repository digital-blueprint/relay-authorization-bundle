<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestResourceActionGrantServiceFactory;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;

abstract class AbstractAuthorizationServiceTestCase extends AbstractInternalResourceActionGrantServiceTestCase
{
    protected ?AuthorizationService $authorizationService = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorizationService = TestResourceActionGrantServiceFactory::createTestAuthorizationService(
            $this->testEntityManager->getEntityManager(),
            $this->eventDispatcher,
            $this->internalResourceActionGrantService,
            $this->getTestConfig(),
            TestResources::getAvailableResourceClassActions(),
            self::CURRENT_USER_IDENTIFIER,
            $this->getDefaultUserAttributes());
    }

    protected function login(?string $userIdentifier, ?array $userAttributes = null): void
    {
        TestAuthorizationService::setUp($this->authorizationService, $userIdentifier,
            $userAttributes ?? $this->getDefaultUserAttributes());
    }

    protected function selectWhere(array $results, callable $where, bool $passInKeyToo = false): array
    {
        return array_values(
            array_filter($results, $where, $passInKeyToo ? ARRAY_FILTER_USE_BOTH : 0)
        );
    }

    protected function containsResource(array $resources, mixed $resource): bool
    {
        foreach ($resources as $res) {
            if ($resource->getIdentifier() === $res->getIdentifier()) {
                return true;
            }
        }

        return false;
    }

    protected function getTestConfig(): array
    {
        return [
            Configuration::CREATE_GROUPS_POLICY => 'user.get("MAY_CREATE_GROUPS")',
        ];
    }

    protected function getDefaultUserAttributes(): array
    {
        return [
            'MAY_CREATE_GROUPS' => false,
        ];
    }

    protected function addGroupAndManageGroupGrantForCurrentUser(string $groupName = 'Testgroup'): array
    {
        $userGroup = $this->testEntityManager->addUserGroup($groupName);
        $manageGroupGrant = $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::GROUP_RESOURCE_CLASS, $userGroup->getIdentifier(),
            action: AuthorizationService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );

        return [$userGroup, $manageGroupGrant];
    }

    /**
     * Asserts that for each action name in $actions there is exactly one AvailableResourceClassAction
     * in $collection matching the given resourceClass, action name, and actionType.
     *
     * @param AvailableResourceClassAction[] $collection
     */
    protected function assertContainsActions(array $collection, string $resourceClass, array $actions, int $actionType): void
    {
        foreach (array_keys($actions) as $action) {
            $matches = $this->selectWhere($collection,
                function (AvailableResourceClassAction $item) use ($resourceClass, $action, $actionType) {
                    return ($item->getResourceClass() === $resourceClass
                        || ($item->getResourceClass() === null && $item->getAction() === AuthorizationService::MANAGE_ACTION))
                        && $item->getAction() === $action
                        && $item->getActionType() === $actionType;
                });
            $this->assertCount(1, $matches,
                "Expected exactly one AvailableResourceClassAction for resourceClass='$resourceClass', action='$action', actionType=$actionType");
        }
    }
}
