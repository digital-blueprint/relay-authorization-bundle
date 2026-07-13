<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\AuthorizationBundle\Rest\AvailableResourceClassActionProvider;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractAuthorizationServiceTestCase;
use Dbp\Relay\AuthorizationBundle\Tests\TestResources;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;

class AvailableResourceClassActionsProviderAuthorizationServiceTest extends AbstractAuthorizationServiceTestCase
{
    private DataProviderTester $availableResourceClassActionsProviderTester;

    protected function setUp(): void
    {
        parent::setUp();

        $provider = new AvailableResourceClassActionProvider($this->internalResourceActionGrantService,
            $this->authorizationService);
        $this->availableResourceClassActionsProviderTester = DataProviderTester::create($provider,
            AvailableResourceClassAction::class,
            ['AuthorizationAvailableResourceClassAction:output']);
    }

    public function testGetAvailableResourceClassActionsCollection(): void
    {
        $group1 = $this->testEntityManager->addUserGroup();
        $group2 = $this->testEntityManager->addUserGroup();
        $group3 = $this->testEntityManager->addUserGroup();

        $this->testEntityManager->addGroupMember($group1, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addGroupMember($group2, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addGroupMember($group3, self::ANOTHER_USER_IDENTIFIER.'_3');

        // noise:
        $group4 = $this->testEntityManager->addUserGroup();
        $this->testEntityManager->addGroupMember($group4, self::ANOTHER_USER_IDENTIFIER.'_4');
        // -----

        $resource = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier');
        $resource2_1 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS_2, 'resourceIdentifier_2');
        $resource2_2 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS_2, 'resourceIdentifier_3');
        $resource3_coll = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS_3, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $resourceGroup = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);

        $this->testEntityManager->addResourceToResourceGroup(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resource->getResourceIdentifier());

        $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2_1,
            AuthorizationService::MANAGE_ACTION, null, $group2);
        $this->testEntityManager->addResourceActionGrant($resource2_2,
            AuthorizationService::MANAGE_ACTION, null, null, 'students');
        $this->testEntityManager->addResourceActionGrant($resource3_coll,
            TestResources::CREATE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource3_coll,
            TestResources::CREATE_ACTION, null, null, 'students');
        $this->testEntityManager->addResourceActionGrant($resource3_coll,
            TestResources::CREATE_ACTION, null, $group1);

        $this->testEntityManager->addResourceActionGrant($resourceGroup,
            TestResources::WRITE_ACTION,
            userGroup: $group3
        );

        // TEST_RESOURCE_CLASS:  4 item actions (read, write, update, delete) + 3 collection actions (create, read, update) = 7
        // TEST_RESOURCE_CLASS_2: 2 item actions (update, delete) + 3 collection actions (read, create, delete_all)      = 5
        // TEST_RESOURCE_CLASS_3: 1 item action  (write)          + 2 collection actions (read, create)                  = 3

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $userAttributes);

        // CURRENT_USER has access to TEST_RESOURCE_CLASS, TEST_RESOURCE_CLASS_2 and TEST_RESOURCE_CLASS_3 => 7+5+3 = 15
        $collection = $this->availableResourceClassActionsProviderTester->getCollection();
        $this->assertCount(15, $collection);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS,
            TestResources::TEST_RESOURCE_ITEM_ACTIONS, AvailableResourceClassAction::ITEM_ACTION_TYPE);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS,
            TestResources::TEST_RESOURCE_COLLECTION_ACTIONS, AvailableResourceClassAction::COLLECTION_ACTION_TYPE);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS_2,
            TestResources::TEST_RESOURCE_2_ITEM_ACTIONS, AvailableResourceClassAction::ITEM_ACTION_TYPE);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS_2,
            TestResources::TEST_RESOURCE_2_COLLECTION_ACTIONS, AvailableResourceClassAction::COLLECTION_ACTION_TYPE);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS_3,
            TestResources::TEST_RESOURCE_3_ITEM_ACTIONS, AvailableResourceClassAction::ITEM_ACTION_TYPE);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS_3,
            TestResources::TEST_RESOURCE_3_COLLECTION_ACTIONS, AvailableResourceClassAction::COLLECTION_ACTION_TYPE);

        // test pagination: 15 total actions, perPage=10 → page 1 has 10, page 2 has 5
        $page1 = $this->availableResourceClassActionsProviderTester->getCollection(['page' => 1, 'perPage' => 10]);
        $this->assertCount(10, $page1);

        $page2 = $this->availableResourceClassActionsProviderTester->getCollection(['page' => 2, 'perPage' => 10]);
        $this->assertCount(5, $page2);

        $this->assertCount(15, array_merge($page1, $page2));

        // ANOTHER_USER is member of group1 => access to TEST_RESOURCE_CLASS_3 only => 3
        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $collection = $this->availableResourceClassActionsProviderTester->getCollection();
        $this->assertCount(3, $collection);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS_3,
            TestResources::TEST_RESOURCE_3_ITEM_ACTIONS, AvailableResourceClassAction::ITEM_ACTION_TYPE);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS_3,
            TestResources::TEST_RESOURCE_3_COLLECTION_ACTIONS, AvailableResourceClassAction::COLLECTION_ACTION_TYPE);

        // ANOTHER_USER_2 is student => access to TEST_RESOURCE_CLASS_2 and TEST_RESOURCE_CLASS_3 => 5+3 = 8
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2', $userAttributes);
        $collection = $this->availableResourceClassActionsProviderTester->getCollection();
        $this->assertCount(8, $collection);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS_2,
            TestResources::TEST_RESOURCE_2_ITEM_ACTIONS, AvailableResourceClassAction::ITEM_ACTION_TYPE);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS_2,
            TestResources::TEST_RESOURCE_2_COLLECTION_ACTIONS, AvailableResourceClassAction::COLLECTION_ACTION_TYPE);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS_3,
            TestResources::TEST_RESOURCE_3_ITEM_ACTIONS, AvailableResourceClassAction::ITEM_ACTION_TYPE);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS_3,
            TestResources::TEST_RESOURCE_3_COLLECTION_ACTIONS, AvailableResourceClassAction::COLLECTION_ACTION_TYPE);

        // ANOTHER_USER_3 is member of group3 => access to TEST_RESOURCE_CLASS (via resource group) => 7
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_3');
        $collection = $this->availableResourceClassActionsProviderTester->getCollection();
        $this->assertCount(7, $collection);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS,
            TestResources::TEST_RESOURCE_ITEM_ACTIONS, AvailableResourceClassAction::ITEM_ACTION_TYPE);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS,
            TestResources::TEST_RESOURCE_COLLECTION_ACTIONS, AvailableResourceClassAction::COLLECTION_ACTION_TYPE);

        // ANOTHER_USER_4 has no grants => 0
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_4');
        $collection = $this->availableResourceClassActionsProviderTester->getCollection();
        $this->assertCount(0, $collection);

        // anonymous => 0
        $this->login(null);
        $collection = $this->availableResourceClassActionsProviderTester->getCollection();
        $this->assertCount(0, $collection);
    }

    public function testGetAvailableResourceClassActionsCollectionWithResourceClassFilter(): void
    {
        $group2 = $this->testEntityManager->addUserGroup();
        $this->testEntityManager->addGroupMember($group2, self::CURRENT_USER_IDENTIFIER);

        $resource = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier');
        $resource2 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS_2, 'resourceIdentifier_2');
        $resource3_coll = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS_3, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);

        $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, null, $group2);
        $this->testEntityManager->addResourceActionGrant($resource3_coll,
            TestResources::CREATE_ACTION, self::CURRENT_USER_IDENTIFIER);

        // CURRENT_USER has access to all three resource classes
        $this->login(self::CURRENT_USER_IDENTIFIER);

        // filter by TEST_RESOURCE_CLASS_2 only => 2 item + 3 collection = 5 actions
        $collection = $this->availableResourceClassActionsProviderTester->getCollection([
            'resourceClass' => TestResources::TEST_RESOURCE_CLASS_2,
        ]);
        $this->assertCount(5, $collection);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS_2,
            TestResources::TEST_RESOURCE_2_ITEM_ACTIONS, AvailableResourceClassAction::ITEM_ACTION_TYPE);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS_2,
            TestResources::TEST_RESOURCE_2_COLLECTION_ACTIONS, AvailableResourceClassAction::COLLECTION_ACTION_TYPE);

        // filter by TEST_RESOURCE_CLASS_3 only => 1 item + 2 collection = 3 actions
        $collection = $this->availableResourceClassActionsProviderTester->getCollection([
            'resourceClass' => TestResources::TEST_RESOURCE_CLASS_3,
        ]);
        $this->assertCount(3, $collection);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS_3,
            TestResources::TEST_RESOURCE_3_ITEM_ACTIONS, AvailableResourceClassAction::ITEM_ACTION_TYPE);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS_3,
            TestResources::TEST_RESOURCE_3_COLLECTION_ACTIONS, AvailableResourceClassAction::COLLECTION_ACTION_TYPE);

        // filter by TEST_RESOURCE_CLASS only => 4 item + 3 collection = 7 actions
        $collection = $this->availableResourceClassActionsProviderTester->getCollection([
            'resourceClass' => TestResources::TEST_RESOURCE_CLASS,
        ]);
        $this->assertCount(7, $collection);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS,
            TestResources::TEST_RESOURCE_ITEM_ACTIONS, AvailableResourceClassAction::ITEM_ACTION_TYPE);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS,
            TestResources::TEST_RESOURCE_COLLECTION_ACTIONS, AvailableResourceClassAction::COLLECTION_ACTION_TYPE);

        // filter by resource class the user has no access to => 0
        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $collection = $this->availableResourceClassActionsProviderTester->getCollection([
            'resourceClass' => TestResources::TEST_RESOURCE_CLASS_2,
        ]);
        $this->assertCount(0, $collection);
    }

    public function testGetAvailableResourceClassActionsCollectionWithResourceIdentifierFilter(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier');
        $resource2 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS_2, 'resourceIdentifier_2');
        $resource3_coll = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS_3, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);

        $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource3_coll,
            TestResources::CREATE_ACTION, self::CURRENT_USER_IDENTIFIER);

        // CURRENT_USER has access to all three resource classes
        $this->login(self::CURRENT_USER_IDENTIFIER);

        // filter by a specific item resource identifier => only item actions (ITEM_ACTION_TYPE) returned
        // TEST_RESOURCE_CLASS: 4 item actions + TEST_RESOURCE_CLASS_2: 2 item actions + TEST_RESOURCE_CLASS_3: 1 item action = 7
        $collection = $this->availableResourceClassActionsProviderTester->getCollection([
            'resourceIdentifier' => 'someItemIdentifier',
        ]);
        $this->assertCount(7, $collection);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS,
            TestResources::TEST_RESOURCE_ITEM_ACTIONS, AvailableResourceClassAction::ITEM_ACTION_TYPE);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS_2,
            TestResources::TEST_RESOURCE_2_ITEM_ACTIONS, AvailableResourceClassAction::ITEM_ACTION_TYPE);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS_3,
            TestResources::TEST_RESOURCE_3_ITEM_ACTIONS, AvailableResourceClassAction::ITEM_ACTION_TYPE);
        foreach ($collection as $action) {
            $this->assertSame(AvailableResourceClassAction::ITEM_ACTION_TYPE, $action->getActionType());
        }

        // filter by the collection resource identifier => only collection actions (COLLECTION_ACTION_TYPE) returned
        // TEST_RESOURCE_CLASS: 3 + TEST_RESOURCE_CLASS_2: 3 + TEST_RESOURCE_CLASS_3: 2 = 8
        $collection = $this->availableResourceClassActionsProviderTester->getCollection([
            'resourceIdentifier' => InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER,
        ]);
        $this->assertCount(8, $collection);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS,
            TestResources::TEST_RESOURCE_COLLECTION_ACTIONS, AvailableResourceClassAction::COLLECTION_ACTION_TYPE);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS_2,
            TestResources::TEST_RESOURCE_2_COLLECTION_ACTIONS, AvailableResourceClassAction::COLLECTION_ACTION_TYPE);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS_3,
            TestResources::TEST_RESOURCE_3_COLLECTION_ACTIONS, AvailableResourceClassAction::COLLECTION_ACTION_TYPE);
        foreach ($collection as $action) {
            $this->assertSame(AvailableResourceClassAction::COLLECTION_ACTION_TYPE, $action->getActionType());
        }

        // combine resourceClass and resourceIdentifier filters:
        // TEST_RESOURCE_CLASS_2, item identifier => 2 item actions (update, delete)
        $collection = $this->availableResourceClassActionsProviderTester->getCollection([
            'resourceClass' => TestResources::TEST_RESOURCE_CLASS_2,
            'resourceIdentifier' => 'someItemIdentifier',
        ]);
        $this->assertCount(2, $collection);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS_2,
            TestResources::TEST_RESOURCE_2_ITEM_ACTIONS, AvailableResourceClassAction::ITEM_ACTION_TYPE);

        // TEST_RESOURCE_CLASS_2, collection identifier => 3 collection actions (read, create, delete_all)
        $collection = $this->availableResourceClassActionsProviderTester->getCollection([
            'resourceClass' => TestResources::TEST_RESOURCE_CLASS_2,
            'resourceIdentifier' => InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER,
        ]);
        $this->assertCount(3, $collection);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS_2,
            TestResources::TEST_RESOURCE_2_COLLECTION_ACTIONS, AvailableResourceClassAction::COLLECTION_ACTION_TYPE);
    }

    protected function getTestConfig(): array
    {
        $config = parent::getTestConfig();
        $config[Configuration::DYNAMIC_GROUPS] = [
            [
                Configuration::IDENTIFIER => 'students',
                Configuration::IS_CURRENT_USER_GROUP_MEMBER_EXPRESSION => 'user.get("IS_STUDENT")',
            ],
        ];

        return $config;
    }

    protected function getDefaultUserAttributes(): array
    {
        $defaultUserAttributes = parent::getDefaultUserAttributes();
        $defaultUserAttributes['IS_STUDENT'] = false;

        return $defaultUserAttributes;
    }

    /**
     * Asserts that for each action name in $actions there is exactly one AvailableResourceClassAction
     * in $collection matching the given resourceClass, action name, and actionType.
     *
     * @param AvailableResourceClassAction[] $collection
     */
    private function assertContainsActions(array $collection, string $resourceClass, array $actions, int $actionType): void
    {
        foreach (array_keys($actions) as $action) {
            $matches = $this->selectWhere($collection,
                function (AvailableResourceClassAction $item) use ($resourceClass, $action, $actionType) {
                    return $item->getResourceClass() === $resourceClass
                        && $item->getAction() === $action
                        && $item->getActionType() === $actionType;
                });
            $this->assertCount(1, $matches,
                "Expected exactly one AvailableResourceClassAction for resourceClass='$resourceClass', action='$action', actionType=$actionType");
        }
    }
}
