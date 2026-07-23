<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\AuthorizationBundle\Entity\GrantedActions;
use Dbp\Relay\AuthorizationBundle\Rest\Common;
use Dbp\Relay\AuthorizationBundle\Rest\GrantedActionsProvider;
use Dbp\Relay\AuthorizationBundle\Tests\TestResources;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;

class GrantedActionsProviderTest extends AbstractResourceActionGrantControllerAuthorizationServiceTestCase
{
    private ?DataProviderTester $grantedActionsProviderTester = null;

    protected function setUp(): void
    {
        parent::setUp();

        $provider = new GrantedActionsProvider($this->authorizationService);
        $this->grantedActionsProviderTester = DataProviderTester::create($provider,
            GrantedActions::class,
            ['AuthorizationGrantedActions:output']);
    }

    public function testGetGrantedActionsItem(): void
    {
        $roleEditor = $this->internalResourceActionGrantService->addOrUpdateRole([],
            [
                ResourceActionGrantService::createRoleAction(TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(TestResources::TEST_RESOURCE_CLASS, TestResources::WRITE_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
            ]
        );

        $manageGrant = $this->addResourceAndManageGrant();
        $this->testEntityManager->addResourceActionGrant($manageGrant->getAuthorizationResource(),
            action: TestResources::READ_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($manageGrant->getAuthorizationResource(),
            action: TestResources::UPDATE_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($manageGrant->getAuthorizationResource(),
            userIdentifier: self::ANOTHER_USER_IDENTIFIER,
            role: $roleEditor);
        $this->testEntityManager->addResourceActionGrant($manageGrant->getAuthorizationResource(),
            action: TestResources::DELETE_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER.'_2');

        // --------------------------------------------------------------------------
        // current user:
        $assert = function (array $grantedActionsCollection): void {
            $this->assertCount(1, $grantedActionsCollection);
            $grantedActions = reset($grantedActionsCollection);
            assert($grantedActions instanceof GrantedActions);
            $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
            $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
            $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());
        };

        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
            ]);
        $this->internalResourceActionGrantService->reset();
        $assert($grantedActionsCollection);

        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => AuthorizationService::RESOURCE_RESOURCE_TYPE,
            ]);
        $this->internalResourceActionGrantService->reset();
        $assert($grantedActionsCollection);

        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertEmpty($grantedActionsCollection);

        // --------------------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(1, $grantedActionsCollection);
        $grantedActions = reset($grantedActionsCollection);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::UPDATE_ACTION,
            TestResources::READ_ACTION,
            TestResources::WRITE_ACTION,
        ], $grantedActions->getActions());

        // --------------------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(1, $grantedActionsCollection);
        $grantedActions = reset($grantedActionsCollection);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::DELETE_ACTION,
        ], $grantedActions->getActions());

        // --------------------------------------------------------------------------
        $this->login('foo');
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(0, $grantedActionsCollection);

        // --------------------------------------------------------------------------
        $this->login(null);
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(0, $grantedActionsCollection);
    }

    public function testGetGrantedActionsItemWithResourceGroup(): void
    {
        $roleEditor = $this->internalResourceActionGrantService->addOrUpdateRole([],
            [
                ResourceActionGrantService::createRoleAction(TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(TestResources::TEST_RESOURCE_CLASS, TestResources::WRITE_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
            ]
        );

        $resource = $this->addResource(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER
        );
        $resourceGroup = $this->addResource(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE
        );

        $this->testEntityManager->addResourceToResourceGroup(TestResources::TEST_RESOURCE_CLASS,
            $resourceGroup->getResourceIdentifier(), $resource->getResourceIdentifier());

        $this->addGrant($resource,
            action: AuthorizationService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $this->addGrant($resourceGroup,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER,
            role: $roleEditor);
        $this->addGrant($resource,
            action: TestResources::DELETE_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER.'_2'
        );
        $this->addGrant($resource,
            action: TestResources::READ_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER.'_2'
        );

        // --------------------------------------------------------------------------
        // current user:
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(1, $grantedActionsCollection);
        $grantedActions = reset($grantedActionsCollection);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            AuthorizationService::MANAGE_ACTION,
        ], $grantedActions->getActions());

        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_GROUP_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertEmpty($grantedActionsCollection);

        // --------------------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(1, $grantedActionsCollection);
        $grantedActions = reset($grantedActionsCollection);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
            TestResources::WRITE_ACTION,
        ], $grantedActions->getActions());

        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(0, $grantedActionsCollection);

        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_GROUP_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(1, $grantedActionsCollection);
        $grantedActions = reset($grantedActionsCollection);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_GROUP_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
            TestResources::WRITE_ACTION,
        ], $grantedActions->getActions());

        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_GROUP_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => AuthorizationService::RESOURCE_RESOURCE_TYPE,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(0, $grantedActionsCollection);

        // --------------------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');

        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(1, $grantedActionsCollection);
        $grantedActions = reset($grantedActionsCollection);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
            TestResources::DELETE_ACTION,
        ], $grantedActions->getActions());

        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_GROUP_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(0, $grantedActionsCollection);

        // --------------------------------------------------------------------------
        $this->login('foo');
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(0, $grantedActionsCollection);

        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(0, $grantedActionsCollection);

        // --------------------------------------------------------------------------
        $this->login(null);
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(0, $grantedActionsCollection);

        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(0, $grantedActionsCollection);
    }

    public function testGetGrantedActionsItemCollectionResource(): void
    {
        $roleCreator = $this->internalResourceActionGrantService->addOrUpdateRole([],
            [
                ResourceActionGrantService::createRoleAction(TestResources::TEST_RESOURCE_CLASS, TestResources::CREATE_ACTION, AvailableResourceClassAction::COLLECTION_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(TestResources::TEST_RESOURCE_CLASS, TestResources::UPDATE_ACTION, AvailableResourceClassAction::COLLECTION_ACTION_TYPE),
            ]
        );

        $manageGrant = $this->addResourceAndManageGrant(
            TestResources::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($manageGrant->getAuthorizationResource(),
            action: TestResources::READ_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($manageGrant->getAuthorizationResource(),
            action: TestResources::CREATE_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($manageGrant->getAuthorizationResource(),
            userIdentifier: self::ANOTHER_USER_IDENTIFIER.'_2',
            role: $roleCreator
        );
        $this->testEntityManager->addResourceActionGrant($manageGrant->getAuthorizationResource(),
            action: TestResources::UPDATE_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER.'_3'
        );

        // --------------------------------------------------------------------------
        // current user:
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
                Common::EXCLUDE_COLLECTION_RESOURCES_QUERY_PARAMETER => false,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(1, $grantedActionsCollection);
        $grantedActions = reset($grantedActionsCollection);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            $grantedActions->getResourceIdentifier()
        );
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        // exclude collection resources (default):
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(0, $grantedActionsCollection);

        // exclude collection resources (explicit):
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
                Common::EXCLUDE_COLLECTION_RESOURCES_QUERY_PARAMETER => true,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(0, $grantedActionsCollection);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
                Common::EXCLUDE_COLLECTION_RESOURCES_QUERY_PARAMETER => false,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(1, $grantedActionsCollection);
        $grantedActions = reset($grantedActionsCollection);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            $grantedActions->getResourceIdentifier()
        );
        $this->assertEquals([TestResources::CREATE_ACTION], $grantedActions->getActions());

        // --------------------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
                Common::EXCLUDE_COLLECTION_RESOURCES_QUERY_PARAMETER => false,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(1, $grantedActionsCollection);
        $grantedActions = reset($grantedActionsCollection);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            $grantedActions->getResourceIdentifier()
        );
        $this->assertEquals([TestResources::CREATE_ACTION], $grantedActions->getActions());

        // --------------------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
                Common::EXCLUDE_COLLECTION_RESOURCES_QUERY_PARAMETER => false,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(1, $grantedActionsCollection);
        $grantedActions = reset($grantedActionsCollection);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            $grantedActions->getResourceIdentifier()
        );
        $this->assertIsPermutationOf([
            TestResources::CREATE_ACTION,
            TestResources::UPDATE_ACTION,
        ], $grantedActions->getActions());

        // --------------------------------------------------------------------------
        $this->login('foo');
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(0, $grantedActionsCollection);

        // --------------------------------------------------------------------------
        $this->login(null);
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            ]);
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(0, $grantedActionsCollection);
    }

    public function testGetGrantedActionsCollection(): void
    {
        $roleEditor = $this->internalResourceActionGrantService->addOrUpdateRole([],
            [
                ResourceActionGrantService::createRoleAction(TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(TestResources::TEST_RESOURCE_CLASS, TestResources::WRITE_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
            ]
        );

        $res1_manage = $this->addResourceAndManageGrant(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            self::CURRENT_USER_IDENTIFIER
        );
        $res2_manage = $this->addResourceAndManageGrant(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_2',
            self::ANOTHER_USER_IDENTIFIER
        );
        $res3_manage = $this->addResourceAndManageGrant(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_3',
            self::ANOTHER_USER_IDENTIFIER.'_2'
        );
        $resColl_manage = $this->addResourceAndManageGrant(
            TestResources::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            self::CURRENT_USER_IDENTIFIER
        );

        $this->testEntityManager->addResourceActionGrant($res1_manage->getAuthorizationResource(),
            action: TestResources::READ_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER);

        $this->testEntityManager->addResourceActionGrant($res2_manage->getAuthorizationResource(),
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
            role: $roleEditor);
        $this->testEntityManager->addResourceActionGrant($res2_manage->getAuthorizationResource(),
            action: TestResources::UPDATE_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER.'_2');

        $this->testEntityManager->addResourceActionGrant($res3_manage->getAuthorizationResource(),
            action: TestResources::DELETE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($res3_manage->getAuthorizationResource(),
            action: TestResources::READ_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER);

        $this->testEntityManager->addResourceActionGrant($resColl_manage->getAuthorizationResource(),
            action: TestResources::CREATE_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER);

        // ----------------------------------------------------------------------------------
        // current user:
        // ----------------------------------------------------------------------------------
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::EXCLUDE_COLLECTION_RESOURCES_QUERY_PARAMETER => false,
            ]
        );
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(4, $grantedActionsCollection);
        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_2';
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER.'_2', $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
            TestResources::WRITE_ACTION,
        ], $grantedActions->getActions());

        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_3';
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER.'_3', $grantedActions->getResourceIdentifier());
        $this->assertEquals([TestResources::DELETE_ACTION], $grantedActions->getActions());

        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER;
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        // excluding the collection resource grants (default behavior)
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
            ]
        );
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(3, $grantedActionsCollection);
        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_2';
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER.'_2', $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
            TestResources::WRITE_ACTION,
        ], $grantedActions->getActions());

        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_3';
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER.'_3', $grantedActions->getResourceIdentifier());
        $this->assertEquals([TestResources::DELETE_ACTION], $grantedActions->getActions());

        // excluding the collection resource grants (explicitly)
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::EXCLUDE_COLLECTION_RESOURCES_QUERY_PARAMETER => true,
            ]
        );
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(3, $grantedActionsCollection);

        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_2';
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER.'_2', $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
            TestResources::WRITE_ACTION,
        ], $grantedActions->getActions());

        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_3';
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER.'_3', $grantedActions->getResourceIdentifier());
        $this->assertEquals([TestResources::DELETE_ACTION], $grantedActions->getActions());

        // 'where is granted action' filter (with manage action) and not excluding the collection resource grants
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::EXCLUDE_COLLECTION_RESOURCES_QUERY_PARAMETER => false,
                Common::WHERE_IS_GRANTED_ACTION_QUERY_PARAMETER => AuthorizationService::MANAGE_ACTION,
            ]
        );
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(2, $grantedActionsCollection);
        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER;
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        // 'where is granted action' filter and excluding the collection resource grants:
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::WHERE_IS_GRANTED_ACTION_QUERY_PARAMETER => AuthorizationService::MANAGE_ACTION,
            ]
        );
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(1, $grantedActionsCollection);
        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        // 'where is granted action' filter (delete action) and not excluding the collection resource grants
        // should contain all resources where the user has delete or manage rights (manage implies delete)
        // NOTE: the collection resource grants should not be included because delete is not available for the collection resource
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::EXCLUDE_COLLECTION_RESOURCES_QUERY_PARAMETER => false,
                Common::WHERE_IS_GRANTED_ACTION_QUERY_PARAMETER => TestResources::DELETE_ACTION,
            ]
        );
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(2, $grantedActionsCollection);
        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_3';
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER.'_3', $grantedActions->getResourceIdentifier());
        $this->assertEquals([TestResources::DELETE_ACTION], $grantedActions->getActions());

        // -------------------------------------------------------------------------------
        // another user:
        // -------------------------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::EXCLUDE_COLLECTION_RESOURCES_QUERY_PARAMETER => false,
            ]
        );
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(4, $grantedActionsCollection);

        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([TestResources::READ_ACTION], $grantedActions->getActions());

        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_2';
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER.'_2', $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_3';
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER.'_3', $grantedActions->getResourceIdentifier());
        $this->assertEquals([TestResources::READ_ACTION], $grantedActions->getActions());

        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER;
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([TestResources::CREATE_ACTION], $grantedActions->getActions());

        // -------------------------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
            ]
        );
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(2, $grantedActionsCollection);
        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_3';
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER.'_3', $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_2';
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER.'_2', $grantedActions->getResourceIdentifier());
        $this->assertEquals([TestResources::UPDATE_ACTION], $grantedActions->getActions());

        // --------------------------------------------------------------------------
        $this->login('foo');
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
            ]
        );
        $this->internalResourceActionGrantService->reset();
        $this->assertEmpty($grantedActionsCollection);

        // --------------------------------------------------------------------------
        $this->login(null);
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
            ]
        );
        $this->internalResourceActionGrantService->reset();
        $this->assertEmpty($grantedActionsCollection);
    }

    public function testGetGrantedActionsCollectionPagination(): void
    {
        $roleEditor = $this->internalResourceActionGrantService->addOrUpdateRole([],
            [
                ResourceActionGrantService::createRoleAction(TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(TestResources::TEST_RESOURCE_CLASS, TestResources::WRITE_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
            ]
        );

        $res1_manage = $this->addResourceAndManageGrant(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            self::CURRENT_USER_IDENTIFIER
        );
        $res2_manage = $this->addResourceAndManageGrant(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_2',
            self::ANOTHER_USER_IDENTIFIER
        );
        $res3_manage = $this->addResourceAndManageGrant(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_3',
            self::ANOTHER_USER_IDENTIFIER.'_2'
        );
        $resColl_manage = $this->addResourceAndManageGrant(
            TestResources::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            self::CURRENT_USER_IDENTIFIER
        );

        $this->testEntityManager->addResourceActionGrant($res1_manage->getAuthorizationResource(),
            action: TestResources::READ_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER);

        $this->testEntityManager->addResourceActionGrant($res2_manage->getAuthorizationResource(),
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
            role: $roleEditor);
        $this->testEntityManager->addResourceActionGrant($res2_manage->getAuthorizationResource(),
            action: TestResources::UPDATE_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER.'_2');

        $this->testEntityManager->addResourceActionGrant($res3_manage->getAuthorizationResource(),
            action: TestResources::DELETE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($res3_manage->getAuthorizationResource(),
            action: TestResources::READ_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER);

        $this->testEntityManager->addResourceActionGrant($resColl_manage->getAuthorizationResource(),
            action: TestResources::CREATE_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER);

        $grantedActionsPage1 = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::EXCLUDE_COLLECTION_RESOURCES_QUERY_PARAMETER => false,
                Pagination::CURRENT_PAGE_NUMBER_PARAMETER_NAME => 1,
                Pagination::MAX_NUM_ITEMS_PER_PAGE_PARAMETER_NAME => 3,
            ],
        );
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(3, $grantedActionsPage1);
        $grantedActionsPage2 = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::EXCLUDE_COLLECTION_RESOURCES_QUERY_PARAMETER => false,
                Pagination::CURRENT_PAGE_NUMBER_PARAMETER_NAME => 2,
                Pagination::MAX_NUM_ITEMS_PER_PAGE_PARAMETER_NAME => 3,
            ],
        );
        $this->internalResourceActionGrantService->reset();
        $this->assertCount(1, $grantedActionsPage2);
        $grantedActionsCollection = array_merge($grantedActionsPage1, $grantedActionsPage2);

        $this->assertCount(4, $grantedActionsCollection);
        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_2';
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER.'_2', $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
            TestResources::WRITE_ACTION,
        ], $grantedActions->getActions());

        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_3';
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER.'_3', $grantedActions->getResourceIdentifier());
        $this->assertEquals([TestResources::DELETE_ACTION], $grantedActions->getActions());

        $grantedActions = $this->selectWhere($grantedActionsCollection,
            function (GrantedActions $grantedActions) {
                return $grantedActions->getResourceIdentifier() === AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER;
            }
        );
        $this->assertCount(1, $grantedActions);
        $grantedActions = reset($grantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        // --------------------------------------------------------------------------
        $this->login('foo');
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Pagination::CURRENT_PAGE_NUMBER_PARAMETER_NAME => 1,
                Pagination::MAX_NUM_ITEMS_PER_PAGE_PARAMETER_NAME => 3,
            ],
        );
        $this->internalResourceActionGrantService->reset();
        $this->assertEmpty($grantedActionsCollection);

        // --------------------------------------------------------------------------
        $this->login(null);
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Pagination::CURRENT_PAGE_NUMBER_PARAMETER_NAME => 1,
                Pagination::MAX_NUM_ITEMS_PER_PAGE_PARAMETER_NAME => 3,
            ],
        );
        $this->internalResourceActionGrantService->reset();
        $this->assertEmpty($grantedActionsCollection);
    }
}
