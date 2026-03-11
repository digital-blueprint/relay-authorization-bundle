<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestResourceActionGrantServiceFactory;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;

abstract class AbstractAuthorizationServiceTestCase extends AbstractInternalResourceActionGrantServiceTestCase
{
    protected ?AuthorizationService $authorizationService = null;

    protected function setUp(): void
    {
        parent::setUp();

        //            $this->internalResourceActionGrantService->setAvailableResourceClassActions(self::TEST_RESOURCE_CLASS,
        //                TestResources::TEST_RESOURCE_ITEM_ACTIONS,
        //                TestResources::TEST_RESOURCE_COLLECTION_ACTIONS);
        //            $this->internalResourceActionGrantService->setAvailableResourceClassActions(self::TEST_COLLECTION_RESOURCE_CLASS,
        //                TestResources::TEST_RESOURCE_ITEM_ACTIONS,
        //                TestResources::TEST_RESOURCE_COLLECTION_ACTIONS);
        //            $this->internalResourceActionGrantService->setAvailableResourceClassActions(self::TEST_RESOURCE_CLASS_2,
        //                TestResources::TEST_RESOURCE_2_ITEM_ACTIONS,
        //                TestResources::TEST_RESOURCE_2_COLLECTION_ACTIONS);
        //            $this->internalResourceActionGrantService->setAvailableResourceClassActions(self::TEST_RESOURCE_CLASS_3,
        //                TestResources::TEST_RESOURCE_3_ITEM_ACTIONS,
        //                TestResources::TEST_RESOURCE_3_COLLECTION_ACTIONS);

        $availableResourceClassActions = [
            self::TEST_RESOURCE_CLASS => [
                TestResources::TEST_RESOURCE_ITEM_ACTIONS,
                TestResources::TEST_RESOURCE_COLLECTION_ACTIONS,
            ],
            self::TEST_COLLECTION_RESOURCE_CLASS => [
                TestResources::TEST_RESOURCE_ITEM_ACTIONS,
                TestResources::TEST_RESOURCE_COLLECTION_ACTIONS,
            ],
            self::TEST_RESOURCE_CLASS_2 => [
                TestResources::TEST_RESOURCE_2_ITEM_ACTIONS,
                TestResources::TEST_RESOURCE_2_COLLECTION_ACTIONS,
            ],
            self::TEST_RESOURCE_CLASS_3 => [
                TestResources::TEST_RESOURCE_3_ITEM_ACTIONS,
                TestResources::TEST_RESOURCE_3_COLLECTION_ACTIONS,
            ],
        ];

        $this->authorizationService = TestResourceActionGrantServiceFactory::createTestAuthorizationService(
            $this->testEntityManager->getEntityManager(),
            $this->eventDispatcher,
            $this->internalResourceActionGrantService,
            $this->getTestConfig(),
            $availableResourceClassActions,
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
        return array_filter($results, $where, $passInKeyToo ? ARRAY_FILTER_USE_BOTH : 0);
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
        $group = $this->testEntityManager->addGroup($groupName);
        $manageGroupGrant = $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::GROUP_RESOURCE_CLASS, $group->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        return [$group, $manageGroupGrant];
    }
}
