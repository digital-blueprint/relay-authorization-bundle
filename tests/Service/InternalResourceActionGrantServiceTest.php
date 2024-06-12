<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Service;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractTestCase;
use Dbp\Relay\AuthorizationBundle\Tests\EventSubscriber\TestGetAvailableResourceClassActionsEventSubscriber;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Component\HttpFoundation\Response;

class InternalResourceActionGrantServiceTest extends AbstractTestCase
{
    public function testAddResourceAndManageResourceGrantForUser(): void
    {
        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceAndManageResourceGrantForUser(
            'resourceClass', 'resourceIdentifier', 'userIdentifier');

        $resourcePersistence = $this->testEntityManager->getAuthorizationResourceByIdentifier($resourceActionGrant->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourcePersistence->getIdentifier(), $resourceActionGrant->getAuthorizationResource()->getIdentifier());
        $this->assertEquals('resourceIdentifier', $resourcePersistence->getResourceIdentifier());
        $this->assertEquals('resourceClass', $resourcePersistence->getResourceClass());

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());
        $this->assertSame($resourceActionGrant->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());
        $this->assertSame($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getIdentifier());
        $this->assertSame($resourceActionGrant->getAction(), $resourceActionGrantPersistence->getAction());
        $this->assertSame($resourceActionGrant->getUserIdentifier(), $resourceActionGrantPersistence->getUserIdentifier());
    }

    public function testAddResourceActionGrant(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($authorizationResource);
        $resourceActionGrant->setAction(AuthorizationService::MANAGE_ACTION);
        $resourceActionGrant->setUserIdentifier('userIdentifier');

        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
        $resourceActionGrantPeristence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantPeristence->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantPeristence->getAction());
        $this->assertEquals($resourceActionGrant->getUserIdentifier(), $resourceActionGrantPeristence->getUserIdentifier());
        $this->assertEquals($resourceActionGrant->getGroup(), $resourceActionGrantPeristence->getGroup());
        $this->assertEquals($resourceActionGrant->getDynamicGroupIdentifier(), $resourceActionGrantPeristence->getDynamicGroupIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantPeristence->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getResourceClass(), $resourceActionGrantPeristence->getAuthorizationResource()->getResourceClass());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getResourceIdentifier(), $resourceActionGrantPeristence->getAuthorizationResource()->getResourceIdentifier());

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($authorizationResource);
        $resourceActionGrant->setAction(TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION);
        $resourceActionGrant->setUserIdentifier('userIdentifier');

        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
        $resourceActionGrantPeristence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantPeristence->getIdentifier());

        $authorizationGroupResource = $this->testEntityManager->addAuthorizationResource('resourceClass', null);
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($authorizationGroupResource);
        $resourceActionGrant->setAction(TestGetAvailableResourceClassActionsEventSubscriber::CREATE_ACTION);
        $resourceActionGrant->setUserIdentifier('userIdentifier');

        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
        $resourceActionGrantPeristence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantPeristence->getIdentifier());
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

    public function testRemoveResource(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $this->assertEquals($resource->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource->getIdentifier())->getIdentifier());

        $this->internalResourceActionGrantService->removeAuthorizationResource('resourceClass', 'resourceIdentifier');

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource->getIdentifier()));
    }

    public function testRemoveResourceAndItsGrants(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $group = $this->testEntityManager->addGroup();

        $this->assertEquals($resource->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource->getIdentifier())->getIdentifier());
        $this->assertEquals($group->getIdentifier(),
            $this->testEntityManager->getGroup($group->getIdentifier())->getIdentifier());

        $resourceActionGrant = $this->testEntityManager->addResourceActionGrant(
            $resource, AuthorizationService::MANAGE_ACTION, 'userIdentifier');
        $resourceActionGrantGroup = $this->testEntityManager->addResourceActionGrant(
            $resource, AuthorizationService::MANAGE_ACTION, null, $group);

        $this->assertEquals($resourceActionGrant->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrantGroup->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrantGroup->getIdentifier())->getIdentifier());

        $this->internalResourceActionGrantService->removeAuthorizationResource('resourceClass', 'resourceIdentifier');

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource->getIdentifier()));
        // assert that group has not been deleted alongside with group grant
        $this->assertEquals($group->getIdentifier(),
            $this->testEntityManager->getGroup($group->getIdentifier())->getIdentifier());

        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrantGroup->getIdentifier()));
    }

    public function testRemoveResourceActionGrant(): void
    {
        $resourceActionGrant = $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            'resourceClass', 'resourceIdentifier', 'read', self::CURRENT_USER_IDENTIFIER);

        $this->internalResourceActionGrantService->removeResourceActionGrant($resourceActionGrant);

        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier()));
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
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceClass2Resource));

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
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceClass2Resource));

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
            'resourceClass', 'resourceIdentifier', [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant1_1));

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', ['read', AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(2, $resourceActionGrants);
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant1_1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant1_2));

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', []);
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', null, self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant1_1));

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', [AuthorizationService::MANAGE_ACTION], 'userIdentifier');
        $this->assertCount(1, $resourceActionGrants);
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant1_1));

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            null, null, [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(4, $resourceActionGrants);
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant1_1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant2_1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrantCollection_1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceClass2Resource));

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            null, null, null, self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(3, $resourceActionGrants);
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant1_1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrant2_2));
        $this->assertTrue($this->containsResource($resourceActionGrants, $resourceActionGrantCollection_1));

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', [AuthorizationService::MANAGE_ACTION], 'userIdentifier_2');
        $this->assertCount(0, $resourceActionGrants);

        // test pagination:
        $resourceActionGrantPage1 = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            null, null, null, self::CURRENT_USER_IDENTIFIER, null, null, 0, 2);
        $this->assertCount(2, $resourceActionGrantPage1);

        $resourceActionGrantPage2 = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            null, null, null, self::CURRENT_USER_IDENTIFIER, null, null, 2, 2);
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
            'resourceClass', 'resourceIdentifier', [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', null, self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($userResourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', null, null, [$group->getIdentifier()]);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($groupResourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', null, null, null, ['dynamicGroup']);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($dynamicGroupRsourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());

        // user, group and dynamic group ID criteria is combined with OR conjunction
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', null,
            self::CURRENT_USER_IDENTIFIER, [$group->getIdentifier()], ['dynamicGroup']);
        $this->assertCount(3, $resourceActionGrants);
        $this->assertEquals($userResourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());
        $this->assertEquals($groupResourceActionGrant->getIdentifier(), $resourceActionGrants[1]->getIdentifier());
        $this->assertEquals($dynamicGroupRsourceActionGrant->getIdentifier(), $resourceActionGrants[2]->getIdentifier());

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', null,
            null, [$group->getIdentifier()], ['dynamicGroup']);
        $this->assertCount(2, $resourceActionGrants);
        $this->assertEquals($groupResourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());
        $this->assertEquals($dynamicGroupRsourceActionGrant->getIdentifier(), $resourceActionGrants[1]->getIdentifier());

        // test pagination:
        $resourceActionGrantPage1 = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', null,
            self::CURRENT_USER_IDENTIFIER, [$group->getIdentifier()], ['dynamicGroup'], 0, 2);
        $this->assertCount(2, $resourceActionGrantPage1);
        $this->assertEquals($userResourceActionGrant->getIdentifier(), $resourceActionGrantPage1[0]->getIdentifier());
        $this->assertEquals($groupResourceActionGrant->getIdentifier(), $resourceActionGrantPage1[1]->getIdentifier());

        $resourceActionGrantPage2 = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', null,
            self::CURRENT_USER_IDENTIFIER, [$group->getIdentifier()], ['dynamicGroup'], 2, 2);
        $this->assertCount(1, $resourceActionGrantPage2);
        $this->assertEquals($dynamicGroupRsourceActionGrant->getIdentifier(), $resourceActionGrantPage2[0]->getIdentifier());
    }

    public function testGetAuthorizationResourcesForResourceClassAndIdentifierUserGrantsOnly(): void
    {
        $resource1 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $resource2 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier2');
        $resource3 = $this->testEntityManager->addAuthorizationResource('resourceClass_2', 'resourceIdentifier3');
        $resource4 = $this->testEntityManager->addAuthorizationResource('resourceClass_2', 'resourceIdentifier4');
        $resourceCollection = $this->testEntityManager->addAuthorizationResource('resourceClass_2', null);

        $resourceActionGrant1_1 = $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant1_2 = $this->testEntityManager->addResourceActionGrant($resource1,
            'read', self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant2_1 = $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant2_2 = $this->testEntityManager->addResourceActionGrant($resource2,
            'write', self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant3_1 = $this->testEntityManager->addResourceActionGrant($resource3,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $resourceActionGrant3_2 = $this->testEntityManager->addResourceActionGrant($resource3,
            'delete', self::ANOTHER_USER_IDENTIFIER.'_2');
        $resourceActionGrant4_1 = $this->testEntityManager->addResourceActionGrant($resource4,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $resourceActionGrantCollection_1 = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $resourceActionGrantCollection_2 = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            'create', self::CURRENT_USER_IDENTIFIER);

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            null, null, null, self::CURRENT_USER_IDENTIFIER, null, null, 0, 1024);
        $this->assertCount(3, $authorizationResources);
        $this->assertCount(1, $this->selectWhere($authorizationResources, function ($resource) use ($resource1) {
            return $resource->getIdentifier() === $resource1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($authorizationResources, function ($resource) use ($resource2) {
            return $resource->getIdentifier() === $resource2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($authorizationResources, function ($resource) use ($resourceCollection) {
            return $resource->getIdentifier() === $resourceCollection->getIdentifier();
        }));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            null, null, null, self::ANOTHER_USER_IDENTIFIER, null, null, 0, 1024);
        $this->assertCount(3, $authorizationResources);
        $this->assertCount(1, $this->selectWhere($authorizationResources, function ($resource) use ($resource3) {
            return $resource->getIdentifier() === $resource3->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($authorizationResources, function ($resource) use ($resource4) {
            return $resource->getIdentifier() === $resource4->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($authorizationResources, function ($resource) use ($resourceCollection) {
            return $resource->getIdentifier() === $resourceCollection->getIdentifier();
        }));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            null, null, null, self::ANOTHER_USER_IDENTIFIER.'_2', null, null, 0, 1024);
        $this->assertCount(1, $authorizationResources);
        $this->assertCount(1, $this->selectWhere($authorizationResources, function ($resource) use ($resource3) {
            return $resource->getIdentifier() === $resource3->getIdentifier();
        }));
    }

    public function testGetAuthorizationResourcesForResourceClassAndIdentifierWithGroupGrants(): void
    {
        $group1 = $this->testEntityManager->addGroup();
        $group2 = $this->testEntityManager->addGroup();

        $resource1 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $resource2 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier2');
        $resource3 = $this->testEntityManager->addAuthorizationResource('resourceClass_2', 'resourceIdentifier');
        $resource4 = $this->testEntityManager->addAuthorizationResource('resourceClass_2', 'resourceIdentifier3');
        $resourceCollection = $this->testEntityManager->addAuthorizationResource('resourceClass_2', null);

        $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1,
            'read', null, $group1);
        $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, null, $group2);
        $this->testEntityManager->addResourceActionGrant($resource2,
            'write', null, null, 'dynamic_group_1');
        $this->testEntityManager->addResourceActionGrant($resource3,
            AuthorizationService::MANAGE_ACTION, 'dynamic_group_2');
        $this->testEntityManager->addResourceActionGrant($resource3,
            'delete', null, $group1);
        $this->testEntityManager->addResourceActionGrant($resource4,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            AuthorizationService::MANAGE_ACTION, null, $group1);
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            'create', self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            'create', null, null, 'dynamic_group_1');

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier();
        $this->assertCount(5, $authorizationResources);
        $this->assertTrue($this->containsResource($authorizationResources, $resource1));
        $this->assertTrue($this->containsResource($authorizationResources, $resource2));
        $this->assertTrue($this->containsResource($authorizationResources, $resource3));
        $this->assertTrue($this->containsResource($authorizationResources, $resource4));
        $this->assertTrue($this->containsResource($authorizationResources, $resourceCollection));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            'resourceClass');
        $this->assertCount(2, $authorizationResources);
        $this->assertTrue($this->containsResource($authorizationResources, $resource1));
        $this->assertTrue($this->containsResource($authorizationResources, $resource2));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            'resourceClass_2');
        $this->assertTrue($this->containsResource($authorizationResources, $resource3));
        $this->assertTrue($this->containsResource($authorizationResources, $resource4));
        $this->assertTrue($this->containsResource($authorizationResources, $resourceCollection));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier2');
        $this->assertCount(1, $authorizationResources);
        $this->assertTrue($this->containsResource($authorizationResources, $resource2));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            null, 'resourceIdentifier');
        $this->assertCount(2, $authorizationResources);
        $this->assertTrue($this->containsResource($authorizationResources, $resource2));
        $this->assertTrue($this->containsResource($authorizationResources, $resource4));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier2', [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $authorizationResources);
        $this->assertTrue($this->containsResource($authorizationResources, $resource2));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier2', []);
        $this->assertCount(0, $authorizationResources);

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            null, null, [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(5, $authorizationResources);
        $this->assertTrue($this->containsResource($authorizationResources, $resource1));
        $this->assertTrue($this->containsResource($authorizationResources, $resource2));
        $this->assertTrue($this->containsResource($authorizationResources, $resource3));
        $this->assertTrue($this->containsResource($authorizationResources, $resource4));
        $this->assertTrue($this->containsResource($authorizationResources, $resourceCollection));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            null, null, ['create', 'read']);
        $this->assertCount(2, $authorizationResources);
        $this->assertTrue($this->containsResource($authorizationResources, $resource1));
        $this->assertTrue($this->containsResource($authorizationResources, $resourceCollection));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            null, null, null, self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(2, $authorizationResources);
        $this->assertTrue($this->containsResource($authorizationResources, $resource1));
        $this->assertTrue($this->containsResource($authorizationResources, $resourceCollection));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            null, null, ['create'], self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(1, $authorizationResources);
        $this->assertTrue($this->containsResource($authorizationResources, $resourceCollection));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            null, null, null, 'nobody');
        $this->assertCount(0, $authorizationResources);

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            null, null, null, null, [$group1->getIdentifier()]);
        $this->assertCount(3, $authorizationResources);
        $this->assertTrue($this->containsResource($authorizationResources, $resource1));
        $this->assertTrue($this->containsResource($authorizationResources, $resource3));
        $this->assertTrue($this->containsResource($authorizationResources, $resourceCollection));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            null, null, null, null, [$group1->getIdentifier(), $group2->getIdentifier()]);
        $this->assertCount(4, $authorizationResources);
        $this->assertTrue($this->containsResource($authorizationResources, $resource1));
        $this->assertTrue($this->containsResource($authorizationResources, $resource2));
        $this->assertTrue($this->containsResource($authorizationResources, $resource3));
        $this->assertTrue($this->containsResource($authorizationResources, $resourceCollection));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            null, null, null, null, []);
        $this->assertCount(0, $authorizationResources);

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            null, null, null, null, null, ['dynamic_group_1']);
        $this->assertCount(2, $authorizationResources);
        $this->assertTrue($this->containsResource($authorizationResources, $resource2));
        $this->assertTrue($this->containsResource($authorizationResources, $resourceCollection));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            null, null, null, null, null, ['dynamic_group_1', 'dynamic_group_2']);
        $this->assertCount(2, $authorizationResources);
        $this->assertTrue($this->containsResource($authorizationResources, $resource2));
        $this->assertTrue($this->containsResource($authorizationResources, $resource3));
        $this->assertTrue($this->containsResource($authorizationResources, $resourceCollection));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            null, null, null, null, null, []);
        $this->assertCount(0, $authorizationResources);

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesForResourceClassAndIdentifier(
            null, null, null, self::ANOTHER_USER_IDENTIFIER, [$group2->getIdentifier()], ['dynamic_group_2']);
        $this->assertCount(2, $authorizationResources);
        $this->assertTrue($this->containsResource($authorizationResources, $resource2));
        $this->assertTrue($this->containsResource($authorizationResources, $resource3));
        $this->assertTrue($this->containsResource($authorizationResources, $resource4));
    }

    public function testGetResourceActionGrantsUserIsAuthorizedToRead(): void
    {
        $group1 = $this->testEntityManager->addGroup();
        $group2 = $this->testEntityManager->addGroup();

        $resource1 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $resource2 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier2');
        $resource3 = $this->testEntityManager->addAuthorizationResource('resourceClass_2', 'resourceIdentifier');
        $resource4 = $this->testEntityManager->addAuthorizationResource('resourceClass_2', 'resourceIdentifier3');
        $resourceCollection = $this->testEntityManager->addAuthorizationResource('resourceClass_2', null);

        $rag_1_manage_u1 = $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $rag_1_delete_u1 = $this->testEntityManager->addResourceActionGrant($resource1,
            'delete', self::CURRENT_USER_IDENTIFIER);
        $rag_1_read_g1 = $this->testEntityManager->addResourceActionGrant($resource1,
            'read', null, $group1);
        $rag_2_manage_g2 = $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, null, $group2);
        $rag_2_write_dg1 = $this->testEntityManager->addResourceActionGrant($resource2,
            'write', null, null, 'dynamic_group_1');
        $rag_3_manage_dg2 = $this->testEntityManager->addResourceActionGrant($resource3,
            AuthorizationService::MANAGE_ACTION, null, null, 'dynamic_group_2');
        $rag_3_delete_g1 = $this->testEntityManager->addResourceActionGrant($resource3,
            'delete', null, $group1);
        $rag_4_manage_u2 = $this->testEntityManager->addResourceActionGrant($resource4,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $rag_collection_manage_g1 = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            AuthorizationService::MANAGE_ACTION, null, $group1);
        $rag_collection_create_u1 = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            'create', self::CURRENT_USER_IDENTIFIER);
        $rag_collection_create_dg1 = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            'create', null, null, 'dynamic_group_1');

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsUserIsAuthorizedToRead();
        $this->assertCount(11, $resourceActionGrants);
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_1_manage_u1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_1_delete_u1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_1_read_g1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_2_manage_g2));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_2_write_dg1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_3_manage_dg2));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_3_delete_g1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_4_manage_u2));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_collection_manage_g1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_collection_create_u1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_collection_create_dg1));

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsUserIsAuthorizedToRead(
            null, null, self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(4, $resourceActionGrants);
        // my grants:
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_1_manage_u1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_1_delete_u1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_collection_create_u1));
        // extra grants of resources I manage:
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_1_read_g1));

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsUserIsAuthorizedToRead(
            null, null, null, [$group2->getIdentifier()]);
        $this->assertCount(2, $resourceActionGrants);
        // my grants:
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_2_manage_g2));
        // extra grants of resources I manage:
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_2_write_dg1));

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsUserIsAuthorizedToRead(
            null, null, null, [$group1->getIdentifier()]);
        $this->assertCount(5, $resourceActionGrants);
        // my grants:
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_1_read_g1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_3_delete_g1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_collection_manage_g1));
        // extra grants of resources I manage:
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_collection_create_dg1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_collection_create_u1));

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsUserIsAuthorizedToRead(
            null, null, null, null, ['dynamic_group_2']);
        $this->assertCount(2, $resourceActionGrants);
        // my grants:
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_3_manage_dg2));
        // extra grants of resources I manage:
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_3_delete_g1));

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsUserIsAuthorizedToRead(
            null, null, null, null, ['dynamic_group_1']);
        $this->assertCount(2, $resourceActionGrants);
        // my grants:
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_2_write_dg1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_collection_create_dg1));

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsUserIsAuthorizedToRead(
            null, null, self::ANOTHER_USER_IDENTIFIER, [$group2->getIdentifier()], ['dynamic_group_2']);
        $this->assertCount(5, $resourceActionGrants);
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_2_manage_g2));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_2_write_dg1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_3_manage_dg2));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_3_delete_g1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_4_manage_u2));

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsUserIsAuthorizedToRead(
            'resourceClass', null, self::ANOTHER_USER_IDENTIFIER, [$group2->getIdentifier()], ['dynamic_group_2']);
        $this->assertCount(2, $resourceActionGrants);
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_2_manage_g2));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_2_write_dg1));

        // test pagination:
        $resourceActionGrantPage1 = $this->internalResourceActionGrantService->getResourceActionGrantsUserIsAuthorizedToRead(
            null, null, null, null, null, 0, 5);
        $this->assertCount(5, $resourceActionGrantPage1);
        $resourceActionGrantPage2 = $this->internalResourceActionGrantService->getResourceActionGrantsUserIsAuthorizedToRead(
            null, null, null, null, null, 5, 5);
        $this->assertCount(5, $resourceActionGrantPage2);
        $resourceActionGrantPage3 = $this->internalResourceActionGrantService->getResourceActionGrantsUserIsAuthorizedToRead(
            null, null, null, null, null, 10, 5);
        $this->assertCount(1, $resourceActionGrantPage3);

        $resourceActionGrants = array_merge($resourceActionGrantPage1, $resourceActionGrantPage2, $resourceActionGrantPage3);
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_1_manage_u1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_1_delete_u1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_1_read_g1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_2_manage_g2));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_2_write_dg1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_3_manage_dg2));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_3_delete_g1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_4_manage_u2));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_collection_manage_g1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_collection_create_u1));
        $this->assertTrue($this->containsResource($resourceActionGrants, $rag_collection_create_dg1));
    }

    public function testGetAuthorizationResourcesUserIsAuthorizedToRead(): void
    {
        $group1 = $this->testEntityManager->addGroup();
        $group2 = $this->testEntityManager->addGroup();

        $resource1 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $resource2 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier2');
        $resource3 = $this->testEntityManager->addAuthorizationResource('resourceClass_2', 'resourceIdentifier');
        $resource4 = $this->testEntityManager->addAuthorizationResource('resourceClass_2', 'resourceIdentifier3');
        $resourceCollection = $this->testEntityManager->addAuthorizationResource('resourceClass_2', null);

        $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1,
            'read', null, $group1);
        $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, null, $group2);
        $this->testEntityManager->addResourceActionGrant($resource2,
            'write', null, null, 'dynamic_group_1');
        $this->testEntityManager->addResourceActionGrant($resource3,
            AuthorizationService::MANAGE_ACTION, null, null, 'dynamic_group_2');
        $this->testEntityManager->addResourceActionGrant($resource3,
            'delete', null, $group1);
        $this->testEntityManager->addResourceActionGrant($resource4,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            AuthorizationService::MANAGE_ACTION, null, $group1);
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            'create', self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            'create', null, null, 'dynamic_group_1');

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesUserIsAuthorizedToRead();
        $this->assertCount(5, $authorizationResources);
        $this->assertTrue($this->containsResource($authorizationResources, $resource1));
        $this->assertTrue($this->containsResource($authorizationResources, $resource2));
        $this->assertTrue($this->containsResource($authorizationResources, $resource3));
        $this->assertTrue($this->containsResource($authorizationResources, $resource4));
        $this->assertTrue($this->containsResource($authorizationResources, $resourceCollection));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesUserIsAuthorizedToRead(
            null, self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(2, $authorizationResources);
        $this->assertTrue($this->containsResource($authorizationResources, $resource1));
        $this->assertTrue($this->containsResource($authorizationResources, $resourceCollection));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesUserIsAuthorizedToRead(
            null, null, [$group2->getIdentifier()]);
        $this->assertCount(1, $authorizationResources);
        $this->assertTrue($this->containsResource($authorizationResources, $resource2));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesUserIsAuthorizedToRead(
            null, null, null, ['dynamic_group_2']);
        $this->assertCount(1, $authorizationResources);
        $this->assertTrue($this->containsResource($authorizationResources, $resource3));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesUserIsAuthorizedToRead(
            null, self::ANOTHER_USER_IDENTIFIER, [$group2->getIdentifier()], ['dynamic_group_2']);
        $this->assertCount(3, $authorizationResources);
        $this->assertTrue($this->containsResource($authorizationResources, $resource2));
        $this->assertTrue($this->containsResource($authorizationResources, $resource3));
        $this->assertTrue($this->containsResource($authorizationResources, $resource4));

        $authorizationResources = $this->internalResourceActionGrantService->getAuthorizationResourcesUserIsAuthorizedToRead(
            'resourceClass', self::ANOTHER_USER_IDENTIFIER, [$group2->getIdentifier()], ['dynamic_group_2']);
        $this->assertCount(1, $authorizationResources);
        $this->assertTrue($this->containsResource($authorizationResources, $resource2));

        // test pagination:
        $authorizationResourcePage1 = $this->internalResourceActionGrantService->getAuthorizationResourcesUserIsAuthorizedToRead(
            null, null, null, null, 0, 3);
        $this->assertCount(3, $authorizationResourcePage1);
        $authorizationResourcePage2 = $this->internalResourceActionGrantService->getAuthorizationResourcesUserIsAuthorizedToRead(
            null, null, null, null, 3, 3);
        $this->assertCount(2, $authorizationResourcePage2);

        $authorizationResources = array_merge($authorizationResourcePage1, $authorizationResourcePage2);
        $this->assertTrue($this->containsResource($authorizationResources, $resource1));
        $this->assertTrue($this->containsResource($authorizationResources, $resource2));
        $this->assertTrue($this->containsResource($authorizationResources, $resource3));
        $this->assertTrue($this->containsResource($authorizationResources, $resource4));
        $this->assertTrue($this->containsResource($authorizationResources, $resourceCollection));
    }

    public function testGetResourceClassesUserIsAuthorizedToRead(): void
    {
        $group1 = $this->testEntityManager->addGroup();
        $group2 = $this->testEntityManager->addGroup();
        $group3 = $this->testEntityManager->addGroup();

        $resource1 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $resource2 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier2');
        $resource3 = $this->testEntityManager->addAuthorizationResource('resourceClass_2', 'resourceIdentifier');
        $resource4 = $this->testEntityManager->addAuthorizationResource('resourceClass_2', 'resourceIdentifier3');
        $resourceCollection = $this->testEntityManager->addAuthorizationResource('resourceClass_3', null);

        $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, null, $group2);
        $this->testEntityManager->addResourceActionGrant($resource2,
            'write', null, null, 'dynamic_group_1');
        $this->testEntityManager->addResourceActionGrant($resource3,
            AuthorizationService::MANAGE_ACTION, null, null, 'dynamic_group_2');
        $this->testEntityManager->addResourceActionGrant($resource3,
            'delete', null, $group1);
        $this->testEntityManager->addResourceActionGrant($resource4,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            AuthorizationService::MANAGE_ACTION, null, $group1);
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            'create', self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            'create', null, null, 'dynamic_group_1');

        $resourceClasses = $this->internalResourceActionGrantService->getResourceClassesUserIsAuthorizedToRead();
        $this->assertCount(3, $resourceClasses);
        $this->assertContains('resourceClass', $resourceClasses);
        $this->assertContains('resourceClass_2', $resourceClasses);
        $this->assertContains('resourceClass_3', $resourceClasses);

        $resourceClasses = $this->internalResourceActionGrantService->getResourceClassesUserIsAuthorizedToRead(
            self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(2, $resourceClasses);
        $this->assertContains('resourceClass', $resourceClasses);
        $this->assertContains('resourceClass_3', $resourceClasses);

        $resourceClasses = $this->internalResourceActionGrantService->getResourceClassesUserIsAuthorizedToRead(
            null, [$group1->getIdentifier()]);
        $this->assertCount(2, $resourceClasses);
        $this->assertContains('resourceClass_2', $resourceClasses);
        $this->assertContains('resourceClass_3', $resourceClasses);

        $resourceClasses = $this->internalResourceActionGrantService->getResourceClassesUserIsAuthorizedToRead(
            null, [$group2->getIdentifier()]);
        $this->assertCount(1, $resourceClasses);
        $this->assertContains('resourceClass', $resourceClasses);

        $resourceClasses = $this->internalResourceActionGrantService->getResourceClassesUserIsAuthorizedToRead(
            null, null, ['dynamic_group_1']);
        $this->assertCount(2, $resourceClasses);
        $this->assertContains('resourceClass', $resourceClasses);
        $this->assertContains('resourceClass_3', $resourceClasses);

        $resourceClasses = $this->internalResourceActionGrantService->getResourceClassesUserIsAuthorizedToRead(
            null, null, ['dynamic_group_2']);
        $this->assertCount(1, $resourceClasses);
        $this->assertContains('resourceClass_2', $resourceClasses);

        $resourceClasses = $this->internalResourceActionGrantService->getResourceClassesUserIsAuthorizedToRead(
            self::ANOTHER_USER_IDENTIFIER, [$group2->getIdentifier()], ['dynamic_group_2']);
        $this->assertCount(2, $resourceClasses);
        $this->assertContains('resourceClass', $resourceClasses);
        $this->assertContains('resourceClass_2', $resourceClasses);

        $resourceClasses = $this->internalResourceActionGrantService->getResourceClassesUserIsAuthorizedToRead(
            'foo', [$group3->getIdentifier()], ['baz']);
        $this->assertCount(0, $resourceClasses);
    }
}
