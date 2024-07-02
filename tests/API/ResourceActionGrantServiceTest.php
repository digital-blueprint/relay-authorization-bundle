<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\API;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractAuthorizationServiceTestCase;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;

class ResourceActionGrantServiceTest extends AbstractAuthorizationServiceTestCase
{
    private ResourceActionGrantService $resourceActionGrantService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resourceActionGrantService = new ResourceActionGrantService(
            $this->authorizationService);
    }

    public function testRegisterResource(): void
    {
        $this->resourceActionGrantService->registerResource(
            'resourceClass', 'resourceIdentifier');

        $resourcePersistence = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier');
        $this->assertEquals($resourcePersistence->getIdentifier(), $resourcePersistence->getIdentifier());
        $this->assertEquals('resourceIdentifier', $resourcePersistence->getResourceIdentifier());
        $this->assertEquals('resourceClass', $resourcePersistence->getResourceClass());

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrant(
            $resourcePersistence->getIdentifier(), ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->assertSame($resourcePersistence->getIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getIdentifier());
        $this->assertSame(ResourceActionGrantService::MANAGE_ACTION, $resourceActionGrantPersistence->getAction());
        $this->assertSame(self::CURRENT_USER_IDENTIFIER, $resourceActionGrantPersistence->getUserIdentifier());
    }

    public function testDeregisterResource(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $resourceActionGrant = $this->testEntityManager->addResourceActionGrant(
            $resource, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER, null);

        $this->assertEquals($resource->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier())->getIdentifier());

        $this->resourceActionGrantService->deregisterResource('resourceClass', 'resourceIdentifier');

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier()));
    }

    public function testDeregisterResources(): void
    {
        $resource1 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier1');
        $resource2 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier2');
        $resource3 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier3');

        $resourceActionGrant1 = $this->testEntityManager->addResourceActionGrant(
            $resource1, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant2 = $this->testEntityManager->addResourceActionGrant(
            $resource2, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant3 = $this->testEntityManager->addResourceActionGrant(
            $resource3, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertEquals($resource1->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource1->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant1->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant1->getIdentifier())->getIdentifier());
        $this->assertEquals($resource2->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource2->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant2->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant2->getIdentifier())->getIdentifier());
        $this->assertEquals($resource3->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource3->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant3->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant3->getIdentifier())->getIdentifier());

        $this->resourceActionGrantService->deregisterResources('resourceClass', ['resourceIdentifier2', 'resourceIdentifier3']);

        $this->assertNotNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource1->getIdentifier()));
        $this->assertNotNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant1->getIdentifier()));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource3->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant3->getIdentifier()));
    }

    public function testGetGrantedResourceItemActions(): void
    {
        $resource1 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $resource2 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier_2');

        $resourceActions = $this->resourceActionGrantService->getGrantedItemActionsForCurrentUser(
            'resourceClass', 'resourceIdentifier');
        $this->assertEmpty($resourceActions);

        $this->testEntityManager->addResourceActionGrant($resource1,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1, 'write', self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1, 'read', self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2,
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2, 'read', self::CURRENT_USER_IDENTIFIER);

        $resourceActions = $this->resourceActionGrantService->getGrantedItemActionsForCurrentUser(
            'resourceClass', 'resourceIdentifier');
        $this->assertCount(2, $resourceActions);
        $this->assertContains(ResourceActionGrantService::MANAGE_ACTION, $resourceActions);
        $this->assertContains('write', $resourceActions);

        $resourceActions = $this->resourceActionGrantService->getGrantedItemActionsForCurrentUser(
            'resourceClass', 'foo');
        $this->assertEmpty($resourceActions);

        // -----------------------------------------------------------------
        // another user:
        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $resourceActions = $this->resourceActionGrantService->getGrantedItemActionsForCurrentUser(
            'resourceClass', 'resourceIdentifier_2');
        $this->assertEquals([ResourceActionGrantService::MANAGE_ACTION], $resourceActions);
    }

    public function testGetGrantedResourceItemActionsPage(): void
    {
        $resource1 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $resource2 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier_2');

        $resourceItemActionsPage = $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
            'resourceClass');
        $this->assertEmpty($resourceItemActionsPage);

        $this->testEntityManager->addResourceActionGrant($resource1,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1, 'write', self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1, 'read', self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2,
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2, 'read', self::CURRENT_USER_IDENTIFIER);

        $resourceItemActionsPage = $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
            'resourceClass');
        $this->assertCount(2, $resourceItemActionsPage);
        $this->assertCount(1, $this->selectWhere($resourceItemActionsPage, function ($resourceActions, $resourceIdentifier) use ($resource1) {
            return count($resourceActions) === 2
                && $resourceIdentifier === $resource1->getResourceIdentifier()
                && in_array(ResourceActionGrantService::MANAGE_ACTION, $resourceActions, true)
                && in_array('write', $resourceActions, true);
        }, true));
        $this->assertCount(1, $this->selectWhere($resourceItemActionsPage, function ($resourceActions, $resourceIdentifier) use ($resource2) {
            return count($resourceActions) === 1
                && $resourceIdentifier === $resource2->getResourceIdentifier()
                && in_array('read', $resourceActions, true);
        }, true));

        $resourceItemActionsPage = $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
            'resourceClass', [ResourceActionGrantService::MANAGE_ACTION, 'write']);
        $this->assertCount(1, $resourceItemActionsPage);
        $this->assertCount(1, $this->selectWhere($resourceItemActionsPage, function ($resourceActions, $resourceIdentifier) use ($resource1) {
            return count($resourceActions) === 2
                && $resourceIdentifier === $resource1->getResourceIdentifier()
                && in_array(ResourceActionGrantService::MANAGE_ACTION, $resourceActions, true)
                && in_array('write', $resourceActions, true);
        }, true));

        $resourceItemActionsPage = $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
            'resourceClass', [ResourceActionGrantService::MANAGE_ACTION]);
        $this->assertCount(1, $resourceItemActionsPage);
        $this->assertCount(1, $this->selectWhere($resourceItemActionsPage, function ($resourceActions, $resourceIdentifier) use ($resource1) {
            return count($resourceActions) === 2
                && $resourceIdentifier === $resource1->getResourceIdentifier()
                && in_array(ResourceActionGrantService::MANAGE_ACTION, $resourceActions, true)
                && in_array('write', $resourceActions, true);
        }, true));

        $resourceItemActionsPage = $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
            'resourceClass', ['write']);
        $this->assertCount(1, $resourceItemActionsPage);
        $this->assertCount(1, $this->selectWhere($resourceItemActionsPage, function ($resourceActions, $resourceIdentifier) use ($resource1) {
            return count($resourceActions) === 2
                && $resourceIdentifier === $resource1->getResourceIdentifier()
                && in_array(ResourceActionGrantService::MANAGE_ACTION, $resourceActions, true)
                && in_array('write', $resourceActions, true);
        }, true));

        $resourceItemActionsPage = $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
            'resourceClass', ['delete']);
        $this->assertCount(0, $resourceItemActionsPage);

        $resourceItemActionsPage = $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
            'resourceClass_2');
        $this->assertCount(0, $resourceItemActionsPage);

        // -----------------------------------------------------------------
        // another user:
        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $resourceItemActionsPage = $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
            'resourceClass');
        $this->assertCount(2, $resourceItemActionsPage);
        $this->assertCount(1, $this->selectWhere($resourceItemActionsPage, function ($resourceActions, $resourceIdentifier) use ($resource2) {
            return count($resourceActions) === 1
                && $resourceIdentifier === $resource2->getResourceIdentifier()
                && in_array(ResourceActionGrantService::MANAGE_ACTION, $resourceActions, true);
        }, true));
        $this->assertCount(1, $this->selectWhere($resourceItemActionsPage, function ($resourceActions, $resourceIdentifier) use ($resource1) {
            return count($resourceActions) === 1
                && $resourceIdentifier === $resource1->getResourceIdentifier()
                && in_array('read', $resourceActions, true);
        }, true));

        $resourceItemActionsPage = $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
            'resourceClass', [ResourceActionGrantService::MANAGE_ACTION]);
        $this->assertCount(1, $resourceItemActionsPage);
        $this->assertCount(1, $this->selectWhere($resourceItemActionsPage, function ($resourceActions, $resourceIdentifier) use ($resource2) {
            return $resourceIdentifier === $resource2->getResourceIdentifier()
                && $resourceActions === [ResourceActionGrantService::MANAGE_ACTION];
        }, true));
    }

    public function testIsCurrentUserGrantedAnyOfItemActions(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');

        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            'resourceClass', 'resourceIdentifier', [ResourceActionGrantService::MANAGE_ACTION]));

        $this->testEntityManager->addResourceActionGrant($resource,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            'resourceClass', 'resourceIdentifier', [ResourceActionGrantService::MANAGE_ACTION]));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            'resourceClass', 'resourceIdentifier', ['read']));
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            'resourceClass', 'resourceIdentifier', [ResourceActionGrantService::MANAGE_ACTION, 'read']));

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            'resourceClass', 'resourceIdentifier', [ResourceActionGrantService::MANAGE_ACTION]));
    }

    public function testIsUserGrantedAnyOfItemActions(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');

        $this->assertFalse($this->resourceActionGrantService->isUserGrantedAnyOfItemActions(
            self::CURRENT_USER_IDENTIFIER, 'resourceClass', 'resourceIdentifier', [ResourceActionGrantService::MANAGE_ACTION]));

        $this->testEntityManager->addResourceActionGrant($resource,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertTrue($this->resourceActionGrantService->isUserGrantedAnyOfItemActions(
            self::CURRENT_USER_IDENTIFIER, 'resourceClass', 'resourceIdentifier', [ResourceActionGrantService::MANAGE_ACTION]));
        $this->assertFalse($this->resourceActionGrantService->isUserGrantedAnyOfItemActions(
            self::CURRENT_USER_IDENTIFIER, 'resourceClass', 'resourceIdentifier', ['read']));
        $this->assertTrue($this->resourceActionGrantService->isUserGrantedAnyOfItemActions(
            self::CURRENT_USER_IDENTIFIER, 'resourceClass', 'resourceIdentifier', [ResourceActionGrantService::MANAGE_ACTION, 'read']));

        $this->assertFalse($this->resourceActionGrantService->isUserGrantedAnyOfItemActions(
            self::CURRENT_USER_IDENTIFIER.'_2', 'resourceClass', 'resourceIdentifier', [ResourceActionGrantService::MANAGE_ACTION]));

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $this->assertTrue($this->resourceActionGrantService->isUserGrantedAnyOfItemActions(
            self::CURRENT_USER_IDENTIFIER, 'resourceClass', 'resourceIdentifier', [ResourceActionGrantService::MANAGE_ACTION]));
    }

    public function testGetGrantedResourceCollectionActions(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);

        $resourceCollectionActions = $this->resourceActionGrantService->getGrantedCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertEmpty($resourceCollectionActions);

        $this->testEntityManager->addResourceActionGrant($resource,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $resourceCollectionActions = $this->resourceActionGrantService->getGrantedCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertEquals([ResourceActionGrantService::MANAGE_ACTION], $resourceCollectionActions);

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $resourceCollectionActions = $this->resourceActionGrantService->getGrantedCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertEmpty($resourceCollectionActions);
    }

    public function testIsCurrentUserGrantedAnyOfCollectionActions(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource('resourceClass', null);

        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedAnyOfCollectionActions(
            'resourceClass', [ResourceActionGrantService::MANAGE_ACTION]));

        $this->testEntityManager->addResourceActionGrant($resource,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedAnyOfCollectionActions(
            'resourceClass', [ResourceActionGrantService::MANAGE_ACTION]));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedAnyOfCollectionActions(
            'resourceClass', ['read']));
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedAnyOfCollectionActions(
            'resourceClass', ['read', ResourceActionGrantService::MANAGE_ACTION]));

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedAnyOfCollectionActions(
            'resourceClass', ['read', ResourceActionGrantService::MANAGE_ACTION]));
    }
}
