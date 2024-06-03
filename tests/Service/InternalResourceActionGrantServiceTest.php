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

        $this->internalResourceActionGrantService->removeResource('resourceClass', 'resourceIdentifier');

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

        $this->internalResourceActionGrantService->removeResource('resourceClass', 'resourceIdentifier');

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
        $resource = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier');

        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrant = $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, 'userIdentifier');

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier');
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());
        $this->assertEquals($resource->getIdentifier(), $resourceActionGrants[0]->getAuthorizationResource()->getIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $resourceActionGrants[0]->getAction());
        $this->assertEquals('userIdentifier', $resourceActionGrants[0]->getUserIdentifier());

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier();
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass');
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', null, 'userIdentifier');
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', [AuthorizationService::MANAGE_ACTION], 'userIdentifier');
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            null, null, [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            null, null, null, 'userIdentifier');
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass_2');
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier_2');
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', ['read']);
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', [AuthorizationService::MANAGE_ACTION], 'userIdentifier_2');
        $this->assertCount(0, $resourceActionGrants);
    }

    public function testGetResourceActionGrantsForResourceClassAndIdentifierWithGroupGrants(): void
    {
        $group = $this->testEntityManager->addGroup();
        $this->testEntityManager->addGroupMember($group, self::ANOTHER_USER_IDENTIFIER);

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
    }
}
