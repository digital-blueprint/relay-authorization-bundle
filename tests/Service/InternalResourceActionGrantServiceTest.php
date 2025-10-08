<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Service;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractInternalResourceActionGrantServiceTestCase;
use Dbp\Relay\AuthorizationBundle\Tests\EventSubscriber\TestGetAvailableResourceClassActionsEventSubscriber;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class InternalResourceActionGrantServiceTest extends AbstractInternalResourceActionGrantServiceTestCase
{
    public function testAddResourceActionGrantByResourceClassAndIdentifier(): void
    {
        // resource item, user grant
        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrantByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER, AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertTrue(Uuid::isValid($resourceActionGrant->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getResourceIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $resourceActionGrant->getAction());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $resourceActionGrant->getUserIdentifier());
        $this->assertEquals(null, $resourceActionGrant->getGroup());
        $this->assertEquals(null, $resourceActionGrant->getDynamicGroupIdentifier());
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getAuthorizationResource()->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier());
        $this->assertTrue(Uuid::isValid($resourceActionGrant->getAuthorizationResource()->getIdentifier()));

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());
        $this->assertEquals($resourceActionGrant->getResourceClass(), $resourceActionGrantPersistence->getResourceClass());
        $this->assertEquals($resourceActionGrant->getResourceIdentifier(), $resourceActionGrantPersistence->getResourceIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantPersistence->getAction());
        $this->assertEquals($resourceActionGrant->getUserIdentifier(), $resourceActionGrantPersistence->getUserIdentifier());
        $this->assertEquals($resourceActionGrant->getGroup(), $resourceActionGrantPersistence->getGroup());
        $this->assertEquals($resourceActionGrant->getDynamicGroupIdentifier(), $resourceActionGrantPersistence->getDynamicGroupIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getResourceClass(), $resourceActionGrantPersistence->getAuthorizationResource()->getResourceClass());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getResourceIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getResourceIdentifier());

        $authorizationResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals($authorizationResource->getIdentifier(), $resourceActionGrant->getAuthorizationResource()->getIdentifier());
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $authorizationResource->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $authorizationResource->getResourceIdentifier());

        // resource collection, dynamic group grant
        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrantByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, null, AuthorizationService::MANAGE_ACTION, null, null, 'everybody');

        $this->assertTrue(Uuid::isValid($resourceActionGrant->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getResourceClass());
        $this->assertEquals(null, $resourceActionGrant->getResourceIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $resourceActionGrant->getAction());
        $this->assertEquals(null, $resourceActionGrant->getUserIdentifier());
        $this->assertEquals(null, $resourceActionGrant->getGroup());
        $this->assertEquals('everybody', $resourceActionGrant->getDynamicGroupIdentifier());
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getAuthorizationResource()->getResourceClass());
        $this->assertEquals(null, $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier());
        $this->assertTrue(Uuid::isValid($resourceActionGrant->getAuthorizationResource()->getIdentifier()));

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());
        $this->assertEquals($resourceActionGrant->getResourceClass(), $resourceActionGrantPersistence->getResourceClass());
        $this->assertEquals($resourceActionGrant->getResourceIdentifier(), $resourceActionGrantPersistence->getResourceIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantPersistence->getAction());
        $this->assertEquals($resourceActionGrant->getUserIdentifier(), $resourceActionGrantPersistence->getUserIdentifier());
        $this->assertEquals($resourceActionGrant->getGroup(), $resourceActionGrantPersistence->getGroup());
        $this->assertEquals($resourceActionGrant->getDynamicGroupIdentifier(), $resourceActionGrantPersistence->getDynamicGroupIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getResourceClass(), $resourceActionGrantPersistence->getAuthorizationResource()->getResourceClass());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getResourceIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getResourceIdentifier());

        $authorizationResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, null);
        $this->assertEquals($authorizationResource->getIdentifier(), $resourceActionGrant->getAuthorizationResource()->getIdentifier());
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $authorizationResource->getResourceClass());
        $this->assertEquals(null, $authorizationResource->getResourceIdentifier());

        $group = $this->testEntityManager->addGroup();
        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrantByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER,
            TestGetAvailableResourceClassActionsEventSubscriber::UPDATE_ACTION, null, $group);

        $this->assertTrue(Uuid::isValid($resourceActionGrant->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS_2, $resourceActionGrant->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getResourceIdentifier());
        $this->assertEquals(TestGetAvailableResourceClassActionsEventSubscriber::UPDATE_ACTION, $resourceActionGrant->getAction());
        $this->assertEquals(null, $resourceActionGrant->getUserIdentifier());
        $this->assertEquals($group, $resourceActionGrant->getGroup());
        $this->assertEquals(null, $resourceActionGrant->getDynamicGroupIdentifier());
        $this->assertEquals(self::TEST_RESOURCE_CLASS_2, $resourceActionGrant->getAuthorizationResource()->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier());
        $this->assertTrue(Uuid::isValid($resourceActionGrant->getAuthorizationResource()->getIdentifier()));

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());
        $this->assertEquals($resourceActionGrant->getResourceClass(), $resourceActionGrantPersistence->getResourceClass());
        $this->assertEquals($resourceActionGrant->getResourceIdentifier(), $resourceActionGrantPersistence->getResourceIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantPersistence->getAction());
        $this->assertEquals($resourceActionGrant->getUserIdentifier(), $resourceActionGrantPersistence->getUserIdentifier());
        $this->assertEquals($resourceActionGrant->getGroup(), $resourceActionGrantPersistence->getGroup());
        $this->assertEquals($resourceActionGrant->getDynamicGroupIdentifier(), $resourceActionGrantPersistence->getDynamicGroupIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getResourceClass(), $resourceActionGrantPersistence->getAuthorizationResource()->getResourceClass());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getResourceIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getResourceIdentifier());
    }

    public function testAddResourceActionGrant(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($authorizationResource);
        $resourceActionGrant->setAction(AuthorizationService::MANAGE_ACTION);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
        $this->assertTrue(Uuid::isValid($resourceActionGrant->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getResourceIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $resourceActionGrant->getAction());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $resourceActionGrant->getUserIdentifier());
        $this->assertEquals(null, $resourceActionGrant->getGroup());
        $this->assertEquals(null, $resourceActionGrant->getDynamicGroupIdentifier());
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getAuthorizationResource()->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier());
        $this->assertTrue(Uuid::isValid($resourceActionGrant->getAuthorizationResource()->getIdentifier()));

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());
        $this->assertEquals($resourceActionGrant->getResourceClass(), $resourceActionGrantPersistence->getResourceClass());
        $this->assertEquals($resourceActionGrant->getResourceIdentifier(), $resourceActionGrantPersistence->getResourceIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantPersistence->getAction());
        $this->assertEquals($resourceActionGrant->getUserIdentifier(), $resourceActionGrantPersistence->getUserIdentifier());
        $this->assertEquals($resourceActionGrant->getGroup(), $resourceActionGrantPersistence->getGroup());
        $this->assertEquals($resourceActionGrant->getDynamicGroupIdentifier(), $resourceActionGrantPersistence->getDynamicGroupIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getResourceClass(), $resourceActionGrantPersistence->getAuthorizationResource()->getResourceClass());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getResourceIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getResourceIdentifier());

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($authorizationResource);
        $resourceActionGrant->setAction(TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION);

        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
        $this->assertEquals(TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, $resourceActionGrant->getAction());

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());
        $this->assertEquals(TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, $resourceActionGrantPersistence->getAction());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());

        $authorizationGroupResource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, null);

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($authorizationGroupResource);
        $resourceActionGrant->setAction(TestGetAvailableResourceClassActionsEventSubscriber::CREATE_ACTION);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
        $this->assertEquals(null, $resourceActionGrant->getResourceIdentifier());

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());
        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());
        $this->assertEquals(null, $resourceActionGrantPersistence->getResourceIdentifier());
    }

    public function testAddResourceInvalidActionMissing(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($authorizationResource);
        $resourceActionGrant->setUserIdentifier('userIdentifier');

        try {
            $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ACTION_MISSING_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testAddResourceInvalidActionUndefined(): void
    {
        $itemResource = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($itemResource);
        // action is only defined for resource collections -> fail
        $resourceActionGrant->setAction(TestGetAvailableResourceClassActionsEventSubscriber::CREATE_ACTION);
        $resourceActionGrant->setUserIdentifier('userIdentifier');

        try {
            $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ACTION_UNDEFINED_ERROR_ID, $apiError->getErrorId());
        }

        $collectionResource = $this->testEntityManager->addAuthorizationResource('resourceClass', null);

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($collectionResource);
        // action is only defined for resource items -> fail
        $resourceActionGrant->setAction(TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION);
        $resourceActionGrant->setUserIdentifier('userIdentifier');

        try {
            $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ACTION_UNDEFINED_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testRemoveAuthorizationResource(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $this->assertEquals($authorizationResource->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($authorizationResource->getIdentifier())->getIdentifier());

        $this->internalResourceActionGrantService->removeAuthorizationResource($authorizationResource);

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($authorizationResource->getIdentifier()));
    }

    public function testRemoveAuthorizationResourceCascadeDelete(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $this->assertEquals($authorizationResource->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($authorizationResource->getIdentifier())->getIdentifier());

        $resourceActionGrant = $this->testEntityManager->addResourceActionGrant(
            $authorizationResource, AuthorizationService::MANAGE_ACTION, 'userIdentifier');
        $this->assertEquals($resourceActionGrant->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier())->getIdentifier());

        $this->internalResourceActionGrantService->removeAuthorizationResource($authorizationResource);

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($authorizationResource->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier()));
    }

    public function testRemoveAuthorizationResourceByResourceClassAndIdentifier(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $this->assertEquals($authorizationResource->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($authorizationResource->getIdentifier())->getIdentifier());

        $this->internalResourceActionGrantService->removeAuthorizationResourceByResourceClassAndIdentifier('resourceClass', 'resourceIdentifier');

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($authorizationResource->getIdentifier()));
    }

    public function testRemoveAuthorizationResourceByResourceClassAndIdentifierCascadeDelete(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $group = $this->testEntityManager->addGroup();

        $this->assertEquals($authorizationResource->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($authorizationResource->getIdentifier())->getIdentifier());
        $this->assertEquals($group->getIdentifier(),
            $this->testEntityManager->getGroup($group->getIdentifier())->getIdentifier());

        $resourceActionGrant = $this->testEntityManager->addResourceActionGrant(
            $authorizationResource, AuthorizationService::MANAGE_ACTION, 'userIdentifier');
        $resourceActionGrantGroup = $this->testEntityManager->addResourceActionGrant(
            $authorizationResource, AuthorizationService::MANAGE_ACTION, null, $group);

        $this->assertEquals($resourceActionGrant->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrantGroup->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrantGroup->getIdentifier())->getIdentifier());

        $this->internalResourceActionGrantService->removeAuthorizationResourceByResourceClassAndIdentifier('resourceClass', 'resourceIdentifier');

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($authorizationResource->getIdentifier()));
        // assert that group has not been deleted alongside with group grant
        $this->assertEquals($group->getIdentifier(),
            $this->testEntityManager->getGroup($group->getIdentifier())->getIdentifier());

        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrantGroup->getIdentifier()));
    }

    public function testRemoveResourceActionGrant(): void
    {
        $resourceActionGrant = $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->internalResourceActionGrantService->removeResourceActionGrant($resourceActionGrant);

        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier()));
    }

    public function testRemoveResourceActionGrants(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        // add some noise
        $authorizationResource2 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceActionGrant($authorizationResource2,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($authorizationResource2,
            TestGetAvailableResourceClassActionsEventSubscriber::UPDATE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        // end noise

        $this->testEntityManager->addResourceActionGrant($authorizationResource,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($authorizationResource,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($authorizationResource,
            TestGetAvailableResourceClassActionsEventSubscriber::UPDATE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(3, $resourceActionGrants);

        $this->internalResourceActionGrantService->removeResourceActionGrants(self::TEST_RESOURCE_CLASS);
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(0, $resourceActionGrants);

        $this->testEntityManager->addResourceActionGrant($authorizationResource,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($authorizationResource,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($authorizationResource,
            TestGetAvailableResourceClassActionsEventSubscriber::UPDATE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(3, $resourceActionGrants);

        $this->internalResourceActionGrantService->removeResourceActionGrants(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER);
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(0, $resourceActionGrants);

        $this->testEntityManager->addResourceActionGrant($authorizationResource,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($authorizationResource,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $updateGrant = $this->testEntityManager->addResourceActionGrant($authorizationResource,
            TestGetAvailableResourceClassActionsEventSubscriber::UPDATE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->internalResourceActionGrantService->removeResourceActionGrants(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER, [TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION]);
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($updateGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());

        $this->internalResourceActionGrantService->removeResourceActionGrants(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER, [TestGetAvailableResourceClassActionsEventSubscriber::UPDATE_ACTION]);
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(0, $resourceActionGrants);

        $this->testEntityManager->addResourceActionGrant($authorizationResource,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
        $anotherUserGrant = $this->testEntityManager->addResourceActionGrant($authorizationResource,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($authorizationResource,
            TestGetAvailableResourceClassActionsEventSubscriber::UPDATE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->internalResourceActionGrantService->removeResourceActionGrants(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER, userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($anotherUserGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());

        $this->internalResourceActionGrantService->removeResourceActionGrants(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER, userIdentifier: self::ANOTHER_USER_IDENTIFIER);
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(0, $resourceActionGrants);

        $this->testEntityManager->addResourceActionGrant($authorizationResource,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
        $anotherUserGrant = $this->testEntityManager->addResourceActionGrant($authorizationResource,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $updateGrant = $this->testEntityManager->addResourceActionGrant($authorizationResource,
            TestGetAvailableResourceClassActionsEventSubscriber::UPDATE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->internalResourceActionGrantService->removeResourceActionGrants(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER, [TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION],
            self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(2, $resourceActionGrants);
        $this->assertCount(1, $this->selectWhere($resourceActionGrants, function (ResourceActionGrant $arg) use ($anotherUserGrant) {
            return $arg->getIdentifier() === $anotherUserGrant->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($resourceActionGrants, function (ResourceActionGrant $arg) use ($updateGrant) {
            return $arg->getIdentifier() === $updateGrant->getIdentifier();
        }));
    }

    public function testGetResourceActionGrantsForResourceClassAndIdentifierUserGrantsOnly(): void
    {
        $resource1 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $resource2 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier_2');
        $resourceCollection = $this->testEntityManager->addAuthorizationResource('resourceClass', null);
        $resourceClass2Resource = $this->testEntityManager->addAuthorizationResource('resourceClass_2', 'resourceIdentifier');

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier();
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrant1_1 = $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant1_2 = $this->testEntityManager->addResourceActionGrant($resource1,
            'read', self::ANOTHER_USER_IDENTIFIER);
        $resourceActionGrant2_1 = $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $resourceActionGrant2_2 = $this->testEntityManager->addResourceActionGrant($resource2,
            'delete', self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrantCollection_1 = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrantCollection_2 = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            'create', self::ANOTHER_USER_IDENTIFIER.'_2');
        $resourceClass2ResourceActionGrant = $this->testEntityManager->addResourceActionGrant($resourceClass2Resource,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER.'_2');

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier();
        $this->assertCount(7, $resourceActionGrants);
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant1_1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant1_2));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant2_1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant2_2));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrantCollection_1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrantCollection_2));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceClass2ResourceActionGrant));

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass');
        $this->assertCount(6, $resourceActionGrants);
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant1_1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant1_2));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant2_1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant2_2));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrantCollection_1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrantCollection_2));

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass_2');
        $this->assertCount(1, $resourceActionGrants);
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceClass2ResourceActionGrant));

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass_3');
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier');
        $this->assertCount(2, $resourceActionGrants);
        $this->assertEquals($resourceActionGrant1_1->getIdentifier(), $resourceActionGrants[0]->getIdentifier());
        $this->assertEquals($resource1->getIdentifier(), $resourceActionGrants[0]->getAuthorizationResource()->getIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $resourceActionGrants[0]->getAction());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $resourceActionGrants[0]->getUserIdentifier());
        $this->assertEquals($resourceActionGrant1_2->getIdentifier(), $resourceActionGrants[1]->getIdentifier());
        $this->assertEquals($resource1->getIdentifier(), $resourceActionGrants[1]->getAuthorizationResource()->getIdentifier());
        $this->assertEquals('read', $resourceActionGrants[1]->getAction());
        $this->assertEquals(self::ANOTHER_USER_IDENTIFIER, $resourceActionGrants[1]->getUserIdentifier());

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier_2');
        $this->assertCount(2, $resourceActionGrants);
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant2_1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant2_2));

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', InternalResourceActionGrantService::IS_NULL);
        $this->assertCount(2, $resourceActionGrants);
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrantCollection_1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrantCollection_2));

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier_3');
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant1_1));

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            null, null, self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(3, $resourceActionGrants);
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant1_1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant2_2));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrantCollection_1));

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', 'userIdentifier_2');
        $this->assertCount(0, $resourceActionGrants);

        // test pagination:
        $resourceActionGrantPage1 = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            null, null, self::CURRENT_USER_IDENTIFIER, null, null, 0, 2);
        $this->assertCount(2, $resourceActionGrantPage1);

        $resourceActionGrantPage2 = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            null, null, self::CURRENT_USER_IDENTIFIER, null, null, 2, 2);
        $this->assertCount(1, $resourceActionGrantPage2);

        $resourceActionGrants = array_merge($resourceActionGrantPage1, $resourceActionGrantPage2);
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant1_1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant2_2));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrantCollection_1));
    }

    public function testGetResourceActionGrantsForResourceClassAndIdentifierWithGroupGrants(): void
    {
        $group = $this->testEntityManager->addGroup();

        $resource = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $userResourceActionGrant = $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $groupResourceActionGrant = $this->testEntityManager->addResourceActionGrant($resource,
            'read', null, $group);
        $dynamicGroupRsourceActionGrant = $this->testEntityManager->addResourceActionGrant($resource,
            'read', null, null, 'dynamicGroup');

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier');
        $this->assertCount(3, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($userResourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', null, [$group->getIdentifier()]);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($groupResourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', null, null, ['dynamicGroup']);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($dynamicGroupRsourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());

        // user, group and dynamic group ID criteria is combined with OR conjunction
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier',
            self::CURRENT_USER_IDENTIFIER, [$group->getIdentifier()], ['dynamicGroup']);
        $this->assertCount(3, $resourceActionGrants);
        $this->assertEquals($userResourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());
        $this->assertEquals($groupResourceActionGrant->getIdentifier(), $resourceActionGrants[1]->getIdentifier());
        $this->assertEquals($dynamicGroupRsourceActionGrant->getIdentifier(), $resourceActionGrants[2]->getIdentifier());

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier',
            null, [$group->getIdentifier()], ['dynamicGroup']);
        $this->assertCount(2, $resourceActionGrants);
        $this->assertEquals($groupResourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());
        $this->assertEquals($dynamicGroupRsourceActionGrant->getIdentifier(), $resourceActionGrants[1]->getIdentifier());

        // test pagination:
        $resourceActionGrantPage1 = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier',
            self::CURRENT_USER_IDENTIFIER, [$group->getIdentifier()], ['dynamicGroup'], 0, 2);
        $this->assertCount(2, $resourceActionGrantPage1);
        $this->assertEquals($userResourceActionGrant->getIdentifier(), $resourceActionGrantPage1[0]->getIdentifier());
        $this->assertEquals($groupResourceActionGrant->getIdentifier(), $resourceActionGrantPage1[1]->getIdentifier());

        $resourceActionGrantPage2 = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier',
            self::CURRENT_USER_IDENTIFIER, [$group->getIdentifier()], ['dynamicGroup'], 2, 2);
        $this->assertCount(1, $resourceActionGrantPage2);
        $this->assertEquals($dynamicGroupRsourceActionGrant->getIdentifier(), $resourceActionGrantPage2[0]->getIdentifier());
    }

    public function testAddGrantInheritance(): void
    {
        $grantInheritance = $this->internalResourceActionGrantService->addGrantInheritance(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER.'_parent');

        $this->assertTrue(Uuid::isValid($grantInheritance->getIdentifier()));
        $this->assertTrue(Uuid::isValid($grantInheritance->getSourceAuthorizationResource()->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantInheritance->getSourceAuthorizationResource()->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantInheritance->getSourceAuthorizationResource()->getResourceIdentifier());
        $this->assertTrue(Uuid::isValid($grantInheritance->getTargetAuthorizationResource()->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS_2, $grantInheritance->getTargetAuthorizationResource()->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER.'_parent', $grantInheritance->getTargetAuthorizationResource()->getResourceIdentifier());

        $grantInheritancePersistence = $this->testEntityManager->getGrantInheritance($grantInheritance->getIdentifier());
        $this->assertEquals($grantInheritance->getIdentifier(), $grantInheritancePersistence->getIdentifier());
        $this->assertEquals($grantInheritance->getSourceAuthorizationResource()->getIdentifier(),
            $grantInheritancePersistence->getSourceAuthorizationResource()->getIdentifier());
        $this->assertEquals($grantInheritance->getTargetAuthorizationResource()->getIdentifier(),
            $grantInheritancePersistence->getTargetAuthorizationResource()->getIdentifier());

        $sourceAuthorizationResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals($grantInheritance->getSourceAuthorizationResource()->getIdentifier(),
            $sourceAuthorizationResource->getIdentifier());

        $targetAuthorizationResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER.'_parent');
        $this->assertEquals($grantInheritance->getTargetAuthorizationResource()->getIdentifier(),
            $targetAuthorizationResource->getIdentifier());
    }

    public function testRemoveGrantInheritance(): void
    {
        $grantInheritance = $this->internalResourceActionGrantService->addGrantInheritance(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER.'_parent');

        $this->assertNotNull($this->testEntityManager->getGrantInheritance($grantInheritance->getIdentifier()));

        $this->internalResourceActionGrantService->removeGrantInheritance(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER.'_parent');

        $this->assertNull($this->testEntityManager->getGrantInheritance($grantInheritance->getIdentifier()));

        // source is a collection resource
        $grantInheritance = $this->internalResourceActionGrantService->addGrantInheritance(
            self::TEST_RESOURCE_CLASS, null,
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER.'_parent');

        $this->assertNotNull($this->testEntityManager->getGrantInheritance($grantInheritance->getIdentifier()));

        $this->internalResourceActionGrantService->removeGrantInheritance(
            self::TEST_RESOURCE_CLASS, null,
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER.'_parent');

        $this->assertNull($this->testEntityManager->getGrantInheritance($grantInheritance->getIdentifier()));

        // target is a collection resource
        $grantInheritance = $this->internalResourceActionGrantService->addGrantInheritance(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            self::TEST_RESOURCE_CLASS_2, null);

        $this->assertNotNull($this->testEntityManager->getGrantInheritance($grantInheritance->getIdentifier()));

        $this->internalResourceActionGrantService->removeGrantInheritance(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            self::TEST_RESOURCE_CLASS_2, null);

        $this->assertNull($this->testEntityManager->getGrantInheritance($grantInheritance->getIdentifier()));

        // both are collection resources
        $grantInheritance = $this->internalResourceActionGrantService->addGrantInheritance(
            self::TEST_RESOURCE_CLASS, null,
            self::TEST_RESOURCE_CLASS_2, null);

        $this->assertNotNull($this->testEntityManager->getGrantInheritance($grantInheritance->getIdentifier()));

        $this->internalResourceActionGrantService->removeGrantInheritance(
            self::TEST_RESOURCE_CLASS, null,
            self::TEST_RESOURCE_CLASS_2, null);

        $this->assertNull($this->testEntityManager->getGrantInheritance($grantInheritance->getIdentifier()));

        // same resource class
        $grantInheritance = $this->internalResourceActionGrantService->addGrantInheritance(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER.'_parent');

        $this->assertNotNull($this->testEntityManager->getGrantInheritance($grantInheritance->getIdentifier()));

        $this->internalResourceActionGrantService->removeGrantInheritance(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER.'_parent');

        $this->assertNull($this->testEntityManager->getGrantInheritance($grantInheritance->getIdentifier()));
    }

    public function testAddGrantInheritanceErrorResourcesIdentical(): void
    {
        try {
            $this->internalResourceActionGrantService->addGrantInheritance(
                self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
                self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
            $this->fail('Expected ApiError not thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(InternalResourceActionGrantService::ADDING_GRANT_INHERITANCE_FAILED_ERROR_ID, $apiError->getErrorId());
        }
    }
}
