<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Rest\ResourceActionGrantProcessor;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Tests\EventSubscriber\TestGetAvailableResourceClassActionsEventSubscriber;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProcessorTester;
use Symfony\Component\HttpFoundation\Response;

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

    public function testCreateResourceActionGrant(): void
    {
        $manageResourceGrant = $this->addResourceAndManageGrant();
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($manageResourceGrant->getAuthorizationResource());
        $resourceActionGrant->setAction('read');
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);
        $resourceActionGrantItem = $this->getResourceActionGrant($resourceActionGrant->getIdentifier());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantItem->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantItem->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantItem->getAction());
        $this->assertEquals($resourceActionGrant->getUserIdentifier(), $resourceActionGrantItem->getUserIdentifier());
    }

    public function testResourceActionGrantAddedEvent(): void
    {
        $manageResourceGrant = $this->addResourceAndManageGrant();

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($manageResourceGrant->getAuthorizationResource());
        $resourceActionGrant->setAction(AuthorizationService::MANAGE_ACTION);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);
        $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);

        $event = $this->testResourceActionGrantAddedEventSubscriber->getEvent();
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $event->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $event->getResourceIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $event->getAction());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $event->getUserIdentifier());
        $this->assertNull($event->getDynamicGroupIdentifier());
        $this->assertNull($event->getGroupIdentifier());

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($manageResourceGrant->getAuthorizationResource());
        $resourceActionGrant->setAction(TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION);
        $resourceActionGrant->setDynamicGroupIdentifier(AuthorizationService::DYNAMIC_GROUP_IDENTIFIER_EVERYBODY);
        $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);

        $event = $this->testResourceActionGrantAddedEventSubscriber->getEvent();
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $event->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $event->getResourceIdentifier());
        $this->assertEquals(TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, $event->getAction());
        $this->assertNull($event->getUserIdentifier());
        $this->assertEquals(AuthorizationService::DYNAMIC_GROUP_IDENTIFIER_EVERYBODY, $event->getDynamicGroupIdentifier());
        $this->assertNull($event->getGroupIdentifier());

        // test with a group grant holder and a resource collection grant
        $group = $this->testEntityManager->addGroup();
        $this->authorizationService->addGroup($group->getIdentifier());
        $this->addResourceAndManageGrant(
            resourceClass: self::TEST_RESOURCE_CLASS,
            resourceIdentifier: null);

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass(self::TEST_RESOURCE_CLASS);
        $resourceActionGrant->setAction(TestGetAvailableResourceClassActionsEventSubscriber::CREATE_ACTION);
        $resourceActionGrant->setGroup($group);
        $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);

        $event = $this->testResourceActionGrantAddedEventSubscriber->getEvent();
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $event->getResourceClass());
        $this->assertNull($event->getResourceIdentifier());
        $this->assertEquals(TestGetAvailableResourceClassActionsEventSubscriber::CREATE_ACTION, $event->getAction());
        $this->assertNull($event->getUserIdentifier());
        $this->assertNull($event->getDynamicGroupIdentifier());
        $this->assertEquals($group->getIdentifier(), $event->getGroupIdentifier());
    }

    public function testCreateResourceActionGrantWithResourceClassAndIdentifierItem(): void
    {
        $this->addResourceAndManageGrant();
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass(TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $resourceActionGrant->setResourceIdentifier(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $resourceActionGrant->setAction('read');
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);
        $resourceActionGrantItem = $this->getResourceActionGrant($resourceActionGrant->getIdentifier());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantItem->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantItem->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantItem->getAction());
        $this->assertEquals($resourceActionGrant->getUserIdentifier(), $resourceActionGrantItem->getUserIdentifier());
    }

    public function testCreateResourceActionGrantWithResourceClassAndIdentifierCollection(): void
    {
        $this->addResourceAndManageGrant(TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass(TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $resourceActionGrant->setAction('create');
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);
        $resourceActionGrantItem = $this->getResourceActionGrant($resourceActionGrant->getIdentifier());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantItem->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantItem->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantItem->getAction());
        $this->assertEquals($resourceActionGrant->getUserIdentifier(), $resourceActionGrantItem->getUserIdentifier());
    }

    public function testCreateResourceActionGrantWithForDynamicGroupEverybody(): void
    {
        $this->addResourceAndManageGrant();
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass(TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $resourceActionGrant->setResourceIdentifier(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $resourceActionGrant->setAction('read');
        $resourceActionGrant->setDynamicGroupIdentifier('everybody');

        $resourceActionGrant = $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);
        $resourceActionGrantItem = $this->getResourceActionGrant($resourceActionGrant->getIdentifier());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantItem->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantItem->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantItem->getAction());
        $this->assertEquals($resourceActionGrant->getDynamicGroupIdentifier(), $resourceActionGrantItem->getDynamicGroupIdentifier());
    }

    public function testCreateResourceActionGrantAuthorizationResourceMissing(): void
    {
        $this->addResourceAndManageGrant();
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
                InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_AUTHORIZATION_RESOURCE_MISSING,
                $apiError->getErrorId());
        }
    }

    public function testCreateResourceActionGrantItemForbidden1(): void
    {
        // you need to be a resource manager to be authorized to create grants for it
        $manageResourceGrant = $this->addResourceAndManageGrant('resourceClass', 'resourceIdentifier',
            self::CURRENT_USER_IDENTIFIER.'_2');
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($manageResourceGrant->getAuthorizationResource());
        $resourceActionGrant->setAction('write');
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testCreateResourceActionGrantItemForbidden2(): void
    {
        // a read grant is not enough to create new grants for the resource
        $manageResourceGrant = $this->addResourceAndManageGrant('resourceClass', 'resourceIdentifier',
            self::CURRENT_USER_IDENTIFIER.'_2');
        $this->addGrant($manageResourceGrant->getAuthorizationResource(), 'read', self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($manageResourceGrant->getAuthorizationResource());
        $resourceActionGrant->setAction('write');
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testDeleteResourceActionGrant(): void
    {
        $manageResourceGrant = $this->addResourceAndManageGrant();
        $this->assertNotNull($this->getResourceActionGrant($manageResourceGrant->getIdentifier()));

        $this->resourceActionGrantProcessorTester->removeItem($manageResourceGrant->getIdentifier(), $manageResourceGrant);
        $this->assertNull($this->getResourceActionGrant($manageResourceGrant->getIdentifier()));
    }

    public function testDeleteResourceActionGrantItemForbidden(): void
    {
        // only the resource manager is authorized to delete grants for a resource
        $manageResourceGrant = $this->addResourceAndManageGrant('resourceClass', 'resourceIdentifier',
            self::ANOTHER_USER_IDENTIFIER);
        $resourceActionGrant = $this->addGrant($manageResourceGrant->getAuthorizationResource(),
            'read', self::CURRENT_USER_IDENTIFIER);

        try {
            $this->resourceActionGrantProcessorTester->removeItem($resourceActionGrant->getIdentifier(), $resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }
}
