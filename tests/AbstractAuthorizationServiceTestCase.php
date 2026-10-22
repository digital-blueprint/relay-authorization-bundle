<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroup;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Service\UserGroupService;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestResourceActionGrantServiceFactory;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;

abstract class AbstractAuthorizationServiceTestCase extends AbstractInternalResourceActionGrantServiceTestCase
{
    protected const TEST_GROUP_NAME = 'Testgroup';

    protected ?AuthorizationService $authorizationService = null;
    protected ?UserGroupService $userGroupService = null;
    protected ?array $testConfig = null;

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

        $this->userGroupService = $this->authorizationService->getUserGroupService();
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
        return array_merge([
            Configuration::CREATE_GROUPS_POLICY => 'user.get("MAY_CREATE_GROUPS")',
        ], $this->testConfig ?? []);
    }

    protected function getDefaultUserAttributes(): array
    {
        return [
            'MAY_CREATE_GROUPS' => false,
        ];
    }

    protected function addUserGroupAndOwnerGrantForCurrentUser(string $groupName = self::TEST_GROUP_NAME): array
    {
        $userGroup = $this->testEntityManager->addUserGroup($groupName);
        $manageGroupGrant = $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::GROUP_RESOURCE_CLASS, $userGroup->getIdentifier(),
            action: AuthorizationService::OWN_ACTION,
            userIdentifier: $this->authorizationService->getUserIdentifier()
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
                        || ($item->getResourceClass() === null && $item->getAction() === AuthorizationService::OWN_ACTION))
                        && $item->getAction() === $action
                        && $item->getActionType() === $actionType;
                });
            $this->assertCount(1, $matches,
                "Expected exactly one AvailableResourceClassAction for resourceClass='$resourceClass', action='$action', actionType=$actionType");
        }
    }

    protected function getResourceActionGrantFromDB(string $identifier): ?ResourceActionGrant
    {
        return $this->testEntityManager->getResourceActionGrantByIdentifier($identifier);
    }

    protected function addResource(
        string $resourceClass = self::TEST_RESOURCE_CLASS,
        string $resourceIdentifier = self::TEST_RESOURCE_IDENTIFIER,
        int $resourceType = InternalResourceActionGrantService::RESOURCE_RESOURCE_TYPE): AuthorizationResource
    {
        return $this->testEntityManager->addAuthorizationResource(
            $resourceClass, $resourceIdentifier, $resourceType);
    }

    protected function addResourceAndOwnerGrantToTestDB(string $resourceClass = self::TEST_RESOURCE_CLASS,
        string $resourceIdentifier = self::TEST_RESOURCE_IDENTIFIER,
        string $userIdentifier = self::CURRENT_USER_IDENTIFIER): ResourceActionGrant
    {
        return $this->addResourceAndGrant($resourceClass, $resourceIdentifier,
            AuthorizationService::OWN_ACTION, $userIdentifier);
    }

    protected function addResourceAndGrant(string $resourceClass = self::TEST_RESOURCE_CLASS,
        string $resourceIdentifier = self::TEST_RESOURCE_IDENTIFIER,
        string $action = 'action',
        string $userIdentifier = self::CURRENT_USER_IDENTIFIER): ResourceActionGrant
    {
        $resource = $this->addResource($resourceClass, $resourceIdentifier);

        return $this->testEntityManager->addResourceActionGrant($resource, $action, $userIdentifier);
    }

    protected function addResourceActionGrantToTestDB(AuthorizationResource $resource, string $action,
        string $userIdentifier = self::CURRENT_USER_IDENTIFIER,
        ?UserGroup $userGroup = null,
        ?string $dynamicUserGroupIdentifier = null,
        ?bool $shareable = null,
        ?ResourceActionGrant $shareOf = null): ResourceActionGrant
    {
        return $this->testEntityManager->addResourceActionGrant(
            $resource, $action,
            userIdentifier: $userIdentifier,
            userGroup: $userGroup,
            dynamicUserGroupIdentifier: $dynamicUserGroupIdentifier,
            shareable: $shareable,
            shareOf: $shareOf);
    }

    protected function addGrant(AuthorizationResource $resource,
        ?string $action = null,
        ?string $userIdentifier = self::CURRENT_USER_IDENTIFIER,
        ?string $roleIdentifier = null,
        ?bool $shareable = null
    ): ResourceActionGrant {
        return $this->testEntityManager->addResourceActionGrant($resource,
            action: $action,
            userIdentifier: $userIdentifier,
            roleIdentifier: $roleIdentifier,
            shareable: $shareable
        );
    }

    protected function addManageGrant(AuthorizationResource $resource,
        string $userIdentifier = self::CURRENT_USER_IDENTIFIER): ResourceActionGrant
    {
        return $this->addGrant(
            $resource, AuthorizationService::OWN_ACTION, $userIdentifier);
    }
}
