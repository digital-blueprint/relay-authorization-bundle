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
        $roleEditor = $this->internalResourceActionGrantService->addRole([],
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

        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
            ]);
        $this->assertCount(1, $grantedActionsCollection);
        $grantedActions = reset($grantedActionsCollection);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        // --------------------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
            ]);
        $this->assertCount(1, $grantedActionsCollection);
        $grantedActions = reset($grantedActionsCollection);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
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
        $this->assertCount(1, $grantedActionsCollection);
        $grantedActions = reset($grantedActionsCollection);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
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
        $this->assertCount(0, $grantedActionsCollection);

        // --------------------------------------------------------------------------
        $this->login(null);
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
            ]);
        $this->assertCount(0, $grantedActionsCollection);
    }

    public function testGetGrantedActionsItemCollectionResource(): void
    {
        $roleCreator = $this->internalResourceActionGrantService->addRole([],
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
            ]);
        $this->assertCount(1, $grantedActionsCollection);
        $grantedActions = reset($grantedActionsCollection);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            $grantedActions->getResourceIdentifier()
        );
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            ]);
        $this->assertCount(1, $grantedActionsCollection);
        $grantedActions = reset($grantedActionsCollection);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            $grantedActions->getResourceIdentifier()
        );
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
        $this->assertEquals([TestResources::CREATE_ACTION], $grantedActions->getActions());

        // --------------------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            ]);
        $this->assertCount(1, $grantedActionsCollection);
        $grantedActions = reset($grantedActionsCollection);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            $grantedActions->getResourceIdentifier()
        );
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
        $this->assertEquals([TestResources::CREATE_ACTION], $grantedActions->getActions());

        // --------------------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            ]);
        $this->assertCount(1, $grantedActionsCollection);
        $grantedActions = reset($grantedActionsCollection);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            $grantedActions->getResourceIdentifier()
        );
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
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
        $this->assertCount(0, $grantedActionsCollection);

        // --------------------------------------------------------------------------
        $this->login(null);
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            ]);
        $this->assertCount(0, $grantedActionsCollection);
    }

    public function testGetGrantedActionsCollection(): void
    {
        $roleEditor = $this->internalResourceActionGrantService->addRole([],
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
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            uriVariables: [
                Common::RESOURCE_CLASS_URI_VARIABLE_NAME => TestResources::TEST_RESOURCE_CLASS,
            ]
        );
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
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
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
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
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
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
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
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        // -------------------------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            uriVariables: [
                Common::RESOURCE_CLASS_URI_VARIABLE_NAME => TestResources::TEST_RESOURCE_CLASS,
            ]
        );
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
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
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
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
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
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
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
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
        $this->assertEquals([TestResources::CREATE_ACTION], $grantedActions->getActions());

        // -------------------------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            uriVariables: [
                Common::RESOURCE_CLASS_URI_VARIABLE_NAME => TestResources::TEST_RESOURCE_CLASS,
            ]
        );
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
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
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
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
        $this->assertEquals([TestResources::UPDATE_ACTION], $grantedActions->getActions());

        // --------------------------------------------------------------------------
        $this->login('foo');
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            uriVariables: [
                Common::RESOURCE_CLASS_URI_VARIABLE_NAME => TestResources::TEST_RESOURCE_CLASS,
            ]
        );
        $this->assertEmpty($grantedActionsCollection);

        // --------------------------------------------------------------------------
        $this->login(null);
        $grantedActionsCollection = $this->grantedActionsProviderTester->getCollection(
            uriVariables: [
                Common::RESOURCE_CLASS_URI_VARIABLE_NAME => TestResources::TEST_RESOURCE_CLASS,
            ]
        );
        $this->assertEmpty($grantedActionsCollection);
    }

    public function testGetGrantedActionsCollectionPagination(): void
    {
        $roleEditor = $this->internalResourceActionGrantService->addRole([],
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
                Pagination::CURRENT_PAGE_NUMBER_PARAMETER_NAME => 1,
                Pagination::MAX_NUM_ITEMS_PER_PAGE_PARAMETER_NAME => 3,
            ],
        );
        $this->assertCount(3, $grantedActionsPage1);
        $grantedActionsPage2 = $this->grantedActionsProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Pagination::CURRENT_PAGE_NUMBER_PARAMETER_NAME => 2,
                Pagination::MAX_NUM_ITEMS_PER_PAGE_PARAMETER_NAME => 3,
            ],
        );
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
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
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
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
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
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
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
        $this->assertCount(0, $grantedActions->getOtherResourceTypeActions());
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
        $this->assertEmpty($grantedActionsCollection);
    }
}
