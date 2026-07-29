<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Rest\ResourceActionGrantProcessor;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Tests\TestResources;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProcessorTester;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\UuidV7;

class ResourceActionGrantProcessorTest extends AbstractResourceActionGrantControllerAuthorizationServiceTestCase
{
    private DataProcessorTester $resourceActionGrantProcessorTester;

    protected function setUp(): void
    {
        parent::setUp();

        $resourceActionGrantProcessor = new ResourceActionGrantProcessor(
            $this->internalResourceActionGrantService, $this->authorizationService);
        $this->resourceActionGrantProcessorTester = DataProcessorTester::create(
            $resourceActionGrantProcessor, ResourceActionGrant::class);
    }

    public function testAddResourceActionGrantWithAction(): void
    {
        $this->addResourceAndManageGrantToTestDB();
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass(TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $resourceActionGrant->setResourceIdentifier(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $resourceActionGrant->setAction(TestResources::READ_ACTION);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);
        $this->assertTrue(UuidV7::isValid($resourceActionGrant->getIdentifier()));
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_CLASS, $resourceActionGrant->getResourceClass());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $resourceActionGrant->getResourceIdentifier());
        $this->assertEquals(TestResources::READ_ACTION, $resourceActionGrant->getAction());
        $this->assertEquals(null, $resourceActionGrant->getRole());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $resourceActionGrant->getUserIdentifier());
        $this->assertEquals(null, $resourceActionGrant->getUserGroup());
        $this->assertEquals(null, $resourceActionGrant->getDynamicUserGroupIdentifier());

        $resourceActionGrantItem = $this->getResourceActionGrantFromDB($resourceActionGrant->getIdentifier());
        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantItem->getIdentifier());
        $this->assertEquals($resourceActionGrant->getResourceClass(), $resourceActionGrantItem->getResourceClass());
        $this->assertEquals($resourceActionGrant->getResourceIdentifier(), $resourceActionGrantItem->getResourceIdentifier());
        $this->assertEquals($resourceActionGrant->getResourceType(), $resourceActionGrantItem->getResourceType());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantItem->getAction());
        $this->assertEquals($resourceActionGrant->getRole(), $resourceActionGrantItem->getRole());
        $this->assertEquals($resourceActionGrant->getUserIdentifier(), $resourceActionGrantItem->getUserIdentifier());
        $this->assertEquals($resourceActionGrant->getUserGroup(), $resourceActionGrantItem->getUserGroup());
        $this->assertEquals($resourceActionGrant->getDynamicUserGroupIdentifier(), $resourceActionGrantItem->getDynamicUserGroupIdentifier());
    }

    public function testAddResourceActionGrantWithRole(): void
    {
        $roleReader = $this->addRoleReader();

        $manageResourceGrant = $this->addResourceAndManageGrantToTestDB();
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass($manageResourceGrant->getResourceClass());
        $resourceActionGrant->setResourceIdentifier($manageResourceGrant->getResourceIdentifier());
        $resourceActionGrant->setRole($roleReader);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);
        $this->assertTrue(UuidV7::isValid($resourceActionGrant->getIdentifier()));
        $this->assertEquals($manageResourceGrant->getResourceClass(), $resourceActionGrant->getResourceClass());
        $this->assertEquals($manageResourceGrant->getResourceIdentifier(), $resourceActionGrant->getResourceIdentifier());
        $this->assertEquals($roleReader, $resourceActionGrant->getRole());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $resourceActionGrant->getUserIdentifier());
        $this->assertEquals(null, $resourceActionGrant->getUserGroup());
        $this->assertEquals(null, $resourceActionGrant->getDynamicUserGroupIdentifier());

        $resourceActionGrantItem = $this->getResourceActionGrantFromDB($resourceActionGrant->getIdentifier());
        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantItem->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantItem->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantItem->getAction());
        $this->assertEquals($resourceActionGrant->getRole(), $resourceActionGrantItem->getRole());
        $this->assertEquals($resourceActionGrant->getUserIdentifier(), $resourceActionGrantItem->getUserIdentifier());
        $this->assertEquals($resourceActionGrant->getUserGroup(), $resourceActionGrantItem->getUserGroup());
        $this->assertEquals($resourceActionGrant->getDynamicUserGroupIdentifier(), $resourceActionGrantItem->getDynamicUserGroupIdentifier());
    }

    public function testResourceActionGrantAddedEvent(): void
    {
        $manageResourceGrant = $this->addResourceAndManageGrantToTestDB();

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($manageResourceGrant->getAuthorizationResource());
        $resourceActionGrant->setAction(AuthorizationService::MANAGE_ACTION);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);
        $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);

        $event = $this->testResourceActionGrantAddedEventSubscriber->getEvent();
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $event->getResourceActionGrant()->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $event->getResourceActionGrant()->getResourceIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $event->getResourceActionGrant()->getAction());
        $this->assertNull($event->getResourceActionGrant()->getRole());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $event->getResourceActionGrant()->getUserIdentifier());
        $this->assertNull($event->getResourceActionGrant()->getDynamicUserGroupIdentifier());
        $this->assertNull($event->getResourceActionGrant()->getUserGroup());

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($manageResourceGrant->getAuthorizationResource());
        $resourceActionGrant->setAction(TestResources::READ_ACTION);
        $resourceActionGrant->setDynamicUserGroupIdentifier(AuthorizationService::DYNAMIC_GROUP_IDENTIFIER_EVERYBODY);
        $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);

        $event = $this->testResourceActionGrantAddedEventSubscriber->getEvent();
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $event->getResourceActionGrant()->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $event->getResourceActionGrant()->getResourceIdentifier());
        $this->assertEquals(TestResources::READ_ACTION, $event->getResourceActionGrant()->getAction());
        $this->assertNull($event->getResourceActionGrant()->getRole());
        $this->assertNull($event->getResourceActionGrant()->getUserIdentifier());
        $this->assertEquals(AuthorizationService::DYNAMIC_GROUP_IDENTIFIER_EVERYBODY, $event->getResourceActionGrant()->getDynamicUserGroupIdentifier());
        $this->assertNull($event->getResourceActionGrant()->getUserGroup());

        // test with a group grant holder and a resource collection grant
        $userGroup = $this->testEntityManager->addUserGroup();
        $this->authorizationService->addUserGroup($userGroup->getIdentifier());
        $this->addResourceAndManageGrantToTestDB(
            resourceClass: self::TEST_RESOURCE_CLASS,
            resourceIdentifier: AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass(self::TEST_RESOURCE_CLASS);
        $resourceActionGrant->setResourceIdentifier(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $resourceActionGrant->setAction(TestResources::CREATE_ACTION);
        $resourceActionGrant->setUserGroup($userGroup);
        $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);

        $event = $this->testResourceActionGrantAddedEventSubscriber->getEvent();
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $event->getResourceActionGrant()->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $event->getResourceActionGrant()->getResourceIdentifier());
        $this->assertEquals(TestResources::CREATE_ACTION, $event->getResourceActionGrant()->getAction());
        $this->assertNull($event->getResourceActionGrant()->getRole());
        $this->assertNull($event->getResourceActionGrant()->getUserIdentifier());
        $this->assertNull($event->getResourceActionGrant()->getDynamicUserGroupIdentifier());
        $this->assertEquals($userGroup->getIdentifier(), $event->getResourceActionGrant()->getUserGroup()->getIdentifier());
    }

    public function testAddResourceActionGrantWithResourceClassAndIdentifierCollection(): void
    {
        $this->addResourceAndManageGrantToTestDB(
            TestEntityManager::DEFAULT_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER
        );
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass(TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $resourceActionGrant->setResourceIdentifier(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $resourceActionGrant->setAction('create');
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);
        $resourceActionGrantItem = $this->getResourceActionGrantFromDB($resourceActionGrant->getIdentifier());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantItem->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantItem->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantItem->getAction());
        $this->assertEquals($resourceActionGrant->getUserIdentifier(), $resourceActionGrantItem->getUserIdentifier());
    }

    public function testAddResourceActionGrantForDynamicUserGroupEverybody(): void
    {
        $this->addResourceAndManageGrantToTestDB();

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass(TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $resourceActionGrant->setResourceIdentifier(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $resourceActionGrant->setAction(TestResources::READ_ACTION);
        $resourceActionGrant->setDynamicUserGroupIdentifier('everybody');

        $resourceActionGrant = $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);
        $resourceActionGrantItem = $this->getResourceActionGrantFromDB($resourceActionGrant->getIdentifier());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantItem->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantItem->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantItem->getAction());
        $this->assertEquals($resourceActionGrant->getDynamicUserGroupIdentifier(), $resourceActionGrantItem->getDynamicUserGroupIdentifier());
    }

    public function testAddResourceActionGrantShareAction(): void
    {
        $manageGrant = $this->addResourceAndManageGrantToTestDB(userIdentifier: self::CURRENT_USER_IDENTIFIER);

        $readGrant = $this->addGrant($manageGrant->getAuthorizationResource(),
            action: TestResources::READ_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER,
            shareable: true
        );

        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $sharedResourceActionGrant = new ResourceActionGrant();
        $sharedResourceActionGrant->setResourceClass(TestResources::TEST_RESOURCE_CLASS);
        $sharedResourceActionGrant->setResourceIdentifier(self::TEST_RESOURCE_IDENTIFIER);
        $sharedResourceActionGrant->setAction(TestResources::READ_ACTION);
        $sharedResourceActionGrant->setShareable(false);
        $sharedResourceActionGrant->setUserIdentifier(self::ANOTHER_USER_IDENTIFIER.'_2');

        $sharedResourceActionGrant = $this->resourceActionGrantProcessorTester->addItem($sharedResourceActionGrant);
        $this->assertTrue(UuidV7::isValid($sharedResourceActionGrant->getIdentifier()));
        $this->assertEquals($manageGrant->getResourceClass(), $sharedResourceActionGrant->getResourceClass());
        $this->assertEquals($manageGrant->getResourceIdentifier(), $sharedResourceActionGrant->getResourceIdentifier());
        $this->assertEquals(TestResources::READ_ACTION, $sharedResourceActionGrant->getAction());
        $this->assertEquals(self::ANOTHER_USER_IDENTIFIER.'_2', $sharedResourceActionGrant->getUserIdentifier());
        $this->assertEquals(false, $sharedResourceActionGrant->getShareable());
        $this->assertEquals($readGrant->getIdentifier(), $sharedResourceActionGrant->getShareOf()->getIdentifier());
    }

    public function testAddResourceActionGrantShareRole(): void
    {
        // creating a share of a role where the actions are equal to the actions of the original role is allowed
        $manageGrant = $this->addResourceAndManageGrantToTestDB(userIdentifier: self::CURRENT_USER_IDENTIFIER);

        $roleEditor = $this->addRoleEditor();

        $roleGrant = $this->addGrant($manageGrant->getAuthorizationResource(),
            roleIdentifier: $roleEditor->getIdentifier(),
            userIdentifier: self::ANOTHER_USER_IDENTIFIER,
            shareable: true
        );

        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $sharedResourceActionGrant = new ResourceActionGrant();
        $sharedResourceActionGrant->setResourceClass(TestResources::TEST_RESOURCE_CLASS);
        $sharedResourceActionGrant->setResourceIdentifier(self::TEST_RESOURCE_IDENTIFIER);
        $sharedResourceActionGrant->setRole($roleEditor);
        $sharedResourceActionGrant->setShareable(false);
        $sharedResourceActionGrant->setUserIdentifier(self::ANOTHER_USER_IDENTIFIER.'_2');

        $sharedResourceActionGrant = $this->resourceActionGrantProcessorTester->addItem($sharedResourceActionGrant);
        $this->assertTrue(UuidV7::isValid($sharedResourceActionGrant->getIdentifier()));
        $this->assertEquals($manageGrant->getResourceClass(), $sharedResourceActionGrant->getResourceClass());
        $this->assertEquals($manageGrant->getResourceIdentifier(), $sharedResourceActionGrant->getResourceIdentifier());
        $this->assertEquals($roleEditor, $sharedResourceActionGrant->getRole());
        $this->assertEquals(self::ANOTHER_USER_IDENTIFIER.'_2', $sharedResourceActionGrant->getUserIdentifier());
        $this->assertEquals(false, $sharedResourceActionGrant->getShareable());
        $this->assertEquals($roleGrant->getIdentifier(), $sharedResourceActionGrant->getShareOf()->getIdentifier());
    }

    public function testAddResourceActionGrantShareRoleSubset(): void
    {
        // creating a share of a role where the actions are a subset of the actions of the original role is allowed
        $manageGrant = $this->addResourceAndManageGrantToTestDB(userIdentifier: self::CURRENT_USER_IDENTIFIER);

        $roleEditor = $this->addRoleEditor();
        $roleReader = $this->addRoleReader();

        $roleGrant = $this->addGrant($manageGrant->getAuthorizationResource(),
            userIdentifier: self::ANOTHER_USER_IDENTIFIER,
            roleIdentifier: $roleEditor->getIdentifier(),
            shareable: true
        );

        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $sharedResourceActionGrant = new ResourceActionGrant();
        $sharedResourceActionGrant->setResourceClass(TestResources::TEST_RESOURCE_CLASS);
        $sharedResourceActionGrant->setResourceIdentifier(self::TEST_RESOURCE_IDENTIFIER);
        $sharedResourceActionGrant->setRole($roleReader);
        $sharedResourceActionGrant->setShareable(false);
        $sharedResourceActionGrant->setUserIdentifier(self::ANOTHER_USER_IDENTIFIER.'_2');

        $sharedResourceActionGrant = $this->resourceActionGrantProcessorTester->addItem($sharedResourceActionGrant);
        $this->assertTrue(UuidV7::isValid($sharedResourceActionGrant->getIdentifier()));
        $this->assertEquals($manageGrant->getResourceClass(), $sharedResourceActionGrant->getResourceClass());
        $this->assertEquals($manageGrant->getResourceIdentifier(), $sharedResourceActionGrant->getResourceIdentifier());
        $this->assertEquals($roleReader, $sharedResourceActionGrant->getRole());
        $this->assertEquals(self::ANOTHER_USER_IDENTIFIER.'_2', $sharedResourceActionGrant->getUserIdentifier());
        $this->assertEquals(false, $sharedResourceActionGrant->getShareable());
        $this->assertEquals($roleGrant->getIdentifier(), $sharedResourceActionGrant->getShareOf()->getIdentifier());
    }

    public function testAddResourceActionGrantShareRoleForbiddenWrongRole(): void
    {
        // creating a share of a role where the actions are
        // NOT a subset or equal to the actions of the original role is FORBIDDEN
        $manageGrant = $this->addResourceAndManageGrantToTestDB(userIdentifier: self::CURRENT_USER_IDENTIFIER);

        $roleEditor = $this->addRoleEditor();
        $roleReader = $this->addRoleReader();

        $this->addGrant($manageGrant->getAuthorizationResource(),
            userIdentifier: self::ANOTHER_USER_IDENTIFIER,
            roleIdentifier: $roleReader->getIdentifier(),
            shareable: true
        );

        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $sharedResourceActionGrant = new ResourceActionGrant();
        $sharedResourceActionGrant->setResourceClass(TestResources::TEST_RESOURCE_CLASS);
        $sharedResourceActionGrant->setResourceIdentifier(self::TEST_RESOURCE_IDENTIFIER);
        $sharedResourceActionGrant->setRole($roleEditor); // editor is a superset of reader -> forbidden
        $sharedResourceActionGrant->setShareable(false);
        $sharedResourceActionGrant->setUserIdentifier(self::ANOTHER_USER_IDENTIFIER.'_2');

        try {
            $this->resourceActionGrantProcessorTester->addItem($sharedResourceActionGrant);
            $this->fail('ApiError not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testAddResourceActionGrantShareActionForbiddenWrongAction(): void
    {
        $this->addResourceAndManageGrantToTestDB(userIdentifier: self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass(TestResources::TEST_RESOURCE_CLASS);
        $resourceActionGrant->setResourceIdentifier(self::TEST_RESOURCE_IDENTIFIER);
        $resourceActionGrant->setAction(TestResources::READ_ACTION);
        $resourceActionGrant->setShareable(true);
        $resourceActionGrant->setUserIdentifier(self::ANOTHER_USER_IDENTIFIER);

        $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);
        $this->testEntityManager->clear();

        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $sharedResourceActionGrant = new ResourceActionGrant();
        $sharedResourceActionGrant->setResourceClass(TestResources::TEST_RESOURCE_CLASS);
        $sharedResourceActionGrant->setResourceIdentifier(self::TEST_RESOURCE_IDENTIFIER);
        $sharedResourceActionGrant->setAction(TestResources::WRITE_ACTION); // not the same action as the shareable grant
        $sharedResourceActionGrant->setShareable(false);
        $sharedResourceActionGrant->setUserIdentifier(self::ANOTHER_USER_IDENTIFIER.'_2');

        try {
            $this->resourceActionGrantProcessorTester->addItem($sharedResourceActionGrant);
            $this->fail('ApiError not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testAddResourceActionGrantBadRequestResourceClassMissing(): void
    {
        $this->addResourceAndManageGrantToTestDB();
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceIdentifier(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $resourceActionGrant->setAction('read');
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);
            $this->fail('ApiError not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(
                InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ERROR_ID,
                $apiError->getErrorId());
        }
    }

    public function testAddResourceActionGrantBadRequestResourceIdentifierMissing(): void
    {
        $this->addResourceAndManageGrantToTestDB();
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass(TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $resourceActionGrant->setAction('read');
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);
            $this->fail('ApiError not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(
                InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ERROR_ID,
                $apiError->getErrorId());
        }
    }

    public function testAddResourceActionGrantItemForbiddenNotFound(): void
    {
        // current user has no grants for the resource -> throw not found to avoid information disclosure
        $manageResourceGrant = $this->addResourceAndManageGrantToTestDB(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            self::CURRENT_USER_IDENTIFIER.'_2');

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($manageResourceGrant->getAuthorizationResource());
        $resourceActionGrant->setAction(TestResources::WRITE_ACTION);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_NOT_FOUND, $apiError->getStatusCode());
        }
    }

    public function testAddResourceActionGrantItemForbidden(): void
    {
        // the user is allowed to see the resource, however, they are neither resource manager
        // nor is the grant they have shareable -> throw 403
        $manageResourceGrant = $this->addResourceAndManageGrantToTestDB(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            self::CURRENT_USER_IDENTIFIER.'_2'
        );
        $this->addGrant($manageResourceGrant->getAuthorizationResource(),
            TestResources::READ_ACTION,
            self::CURRENT_USER_IDENTIFIER,
            shareable: false // default
        );

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($manageResourceGrant->getAuthorizationResource());
        $resourceActionGrant->setAction(TestResources::READ_ACTION);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testRemoveResourceActionGrantManage(): void
    {
        $manageResourceGrant = $this->addResourceAndManageGrantToTestDB();
        $this->assertNotNull($this->getResourceActionGrantFromDB($manageResourceGrant->getIdentifier()));

        $this->resourceActionGrantProcessorTester->removeItem($manageResourceGrant->getIdentifier(), $manageResourceGrant);
        $this->assertNull($this->getResourceActionGrantFromDB($manageResourceGrant->getIdentifier()));
    }

    public function testRemoveResourceActionGrant(): void
    {
        // resource manager is allowed to remove any grants for a resource
        $manageResourceGrant = $this->addResourceAndManageGrantToTestDB();
        $resourceActionGrant = $this->addResourceActionGrantToTestDB($manageResourceGrant->getAuthorizationResource(),
            TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->resourceActionGrantProcessorTester->removeItem($resourceActionGrant->getIdentifier(), $resourceActionGrant);
        $this->assertNull($this->getResourceActionGrantFromDB($resourceActionGrant->getIdentifier()));
    }

    public function testRemoveResourceActionGrantShare(): void
    {
        // holders of shareable grants are allowed to remove any shares of their grants
        $manageResourceGrant = $this->addResourceAndManageGrantToTestDB();
        $resourceActionGrant = $this->addResourceActionGrantToTestDB($manageResourceGrant->getAuthorizationResource(),
            TestResources::READ_ACTION, self::ANOTHER_USER_IDENTIFIER,
            shareable: true);
        $resourceActionGrantShared = $this->addResourceActionGrantToTestDB($manageResourceGrant->getAuthorizationResource(),
            TestResources::READ_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER.'_2',
            shareOf: $resourceActionGrant);

        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $this->resourceActionGrantProcessorTester->removeItem(
            $resourceActionGrantShared->getIdentifier(), $resourceActionGrantShared);
        $this->assertNull($this->getResourceActionGrantFromDB($resourceActionGrantShared->getIdentifier()));
    }

    public function testRemoveResourceActionGrantItemForbiddenNotFound(): void
    {
        // current user has no grants for the resource -> throw not found to avoid information disclosure
        $manageResourceGrant = $this->addResourceAndManageGrantToTestDB(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            self::CURRENT_USER_IDENTIFIER.'_2');

        try {
            $this->resourceActionGrantProcessorTester->removeItem(
                $manageResourceGrant->getIdentifier(), $manageResourceGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_NOT_FOUND, $apiError->getStatusCode());
        }
    }

    public function testRemoveResourceActionGrantItemForbidden(): void
    {
        // current user has a grant for the resource but is not the resource manager,
        // nor is the grant to remove a share of the current user's grant -> forbidden
        $manageResourceGrant = $this->addResourceAndManageGrantToTestDB(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            self::ANOTHER_USER_IDENTIFIER);
        $resourceActionGrant = $this->addGrant(
            $manageResourceGrant->getAuthorizationResource(),
            TestResources::READ_ACTION,
            self::CURRENT_USER_IDENTIFIER);

        try {
            $this->resourceActionGrantProcessorTester->removeItem($resourceActionGrant->getIdentifier(), $resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }
}
