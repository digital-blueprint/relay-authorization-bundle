<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\API;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractAuthorizationServiceTestCase;
use Dbp\Relay\AuthorizationBundle\Tests\TestResources;
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
        $this->resourceActionGrantService->addResourceActionGrant(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $resourcePersistence = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals($resourcePersistence->getIdentifier(), $resourcePersistence->getIdentifier());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourcePersistence->getResourceIdentifier());
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourcePersistence->getResourceClass());

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrant(
            $resourcePersistence->getIdentifier(), ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->assertSame($resourcePersistence->getIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getIdentifier());
        $this->assertSame(ResourceActionGrantService::MANAGE_ACTION, $resourceActionGrantPersistence->getAction());
        $this->assertSame(self::CURRENT_USER_IDENTIFIER, $resourceActionGrantPersistence->getUserIdentifier());
    }

    public function testAddAvailableResourceClassActions(): void
    {
        $resourceClass = 'MyResourceClass';
        $itemActions = [
            'view' => [
                'en' => 'View',
                'fr' => 'Voir',
            ],
            'edit' => [
                'en' => 'Edit',
                'fr' => 'Éditer',
            ],
        ];
        $collectionActions = [
            'create' => [
                'en' => 'Create',
                'fr' => 'Créer',
            ],
        ];
        $this->resourceActionGrantService->addAvailableResourceClassActions($resourceClass, $itemActions, $collectionActions);

        [$retrievedItemActions, $retrievedCollectionActions] =
            $this->internalResourceActionGrantService->getAvailableResourceClassActions($resourceClass);

        $this->assertArrayHasKey(AuthorizationService::MANAGE_ACTION, $retrievedItemActions);
        $this->assertArrayHasKey(AuthorizationService::MANAGE_ACTION, $retrievedCollectionActions);
        unset($retrievedItemActions[AuthorizationService::MANAGE_ACTION]);
        unset($retrievedCollectionActions[AuthorizationService::MANAGE_ACTION]);

        $this->assertEquals($itemActions, $retrievedItemActions);
        $this->assertEquals($collectionActions, $retrievedCollectionActions);
    }

    public function testAddResourceActionGrant(): void
    {
        $action = TestResources::WRITE_ACTION;
        $userIdentifier = self::ANOTHER_USER_IDENTIFIER;

        $this->resourceActionGrantService->addResourceActionGrant(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER, $action, $userIdentifier);

        $resourcePersistence = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrant($resourcePersistence->getIdentifier(), $action, $userIdentifier);
        $this->assertSame($resourcePersistence->getIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getIdentifier());
        $this->assertSame($action, $resourceActionGrantPersistence->getAction());
        $this->assertSame($userIdentifier, $resourceActionGrantPersistence->getUserIdentifier());
    }

    public function testDeregisterResource(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $resourceActionGrant = $this->testEntityManager->addResourceActionGrant(
            $resource, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER, null);

        $this->assertEquals($resource->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier())->getIdentifier());

        $this->resourceActionGrantService->removeGrantsForResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier()));
    }

    public function testDeregisterResources(): void
    {
        $resource1 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, 'resourceIdentifier1');
        $resource2 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, 'resourceIdentifier2');
        $resource3 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, 'resourceIdentifier3');

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

        $this->resourceActionGrantService->removeGrantsForResources(self::TEST_RESOURCE_CLASS, ['resourceIdentifier2', 'resourceIdentifier3']);

        $this->assertNotNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource1->getIdentifier()));
        $this->assertNotNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant1->getIdentifier()));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource3->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant3->getIdentifier()));
    }

    public function testGetGrantedResourceItemActions(): void
    {
        $resource1 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $resource2 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, 'resourceIdentifier_2');

        $resourceActions = $this->resourceActionGrantService->getGrantedItemActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEmpty($resourceActions);

        $this->testEntityManager->addResourceActionGrant($resource1,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1, 'write', self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1, 'read', self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2,
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2, 'read', self::CURRENT_USER_IDENTIFIER);

        $resourceActions = $this->resourceActionGrantService->getGrantedItemActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(1, $resourceActions);
        $this->assertContains(ResourceActionGrantService::MANAGE_ACTION, $resourceActions);

        $resourceActions = $this->resourceActionGrantService->getGrantedItemActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, 'foo');
        $this->assertEmpty($resourceActions);

        // -----------------------------------------------------------------
        // another user:
        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $resourceActions = $this->resourceActionGrantService->getGrantedItemActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, 'resourceIdentifier_2');
        $this->assertEquals([ResourceActionGrantService::MANAGE_ACTION], $resourceActions);
    }

    public function testGetGrantedResourceItemActionsPage(): void
    {
        $resource1 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $resource2 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, 'resourceIdentifier_2');

        $resourceItemActionsPage = $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertEmpty($resourceItemActionsPage);

        $this->testEntityManager->addResourceActionGrant($resource1,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1, 'write', self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1, 'read', self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2,
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2, 'read', self::CURRENT_USER_IDENTIFIER);

        $resourceItemActionsPage = $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
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
            self::TEST_RESOURCE_CLASS, ResourceActionGrantService::MANAGE_ACTION);
        $this->assertCount(1, $resourceItemActionsPage);
        $this->assertCount(1, $this->selectWhere($resourceItemActionsPage, function ($resourceActions, $resourceIdentifier) use ($resource1) {
            return count($resourceActions) === 2
                && $resourceIdentifier === $resource1->getResourceIdentifier()
                && in_array(ResourceActionGrantService::MANAGE_ACTION, $resourceActions, true)
                && in_array('write', $resourceActions, true);
        }, true));
        $resourceItemActionsPage = $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, 'write');
        $this->assertCount(1, $resourceItemActionsPage);
        $this->assertCount(1, $this->selectWhere($resourceItemActionsPage, function ($resourceActions, $resourceIdentifier) use ($resource1) {
            return count($resourceActions) === 2
                && $resourceIdentifier === $resource1->getResourceIdentifier()
                && in_array(ResourceActionGrantService::MANAGE_ACTION, $resourceActions, true)
                && in_array('write', $resourceActions, true);
        }, true));
        $resourceItemActionsPage = $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, 'delete');
        $this->assertCount(1, $resourceItemActionsPage);
        $this->assertCount(1, $this->selectWhere($resourceItemActionsPage, function ($resourceActions, $resourceIdentifier) use ($resource1) {
            return count($resourceActions) === 2
                && $resourceIdentifier === $resource1->getResourceIdentifier()
                && in_array(ResourceActionGrantService::MANAGE_ACTION, $resourceActions, true)
                && in_array('write', $resourceActions, true);
        }, true));

        $resourceItemActionsPage = $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
            'resourceClass_2');
        $this->assertCount(0, $resourceItemActionsPage);

        // -----------------------------------------------------------------
        // another user:
        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $resourceItemActionsPage = $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
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
            self::TEST_RESOURCE_CLASS, ResourceActionGrantService::MANAGE_ACTION);
        $this->assertCount(1, $resourceItemActionsPage);
        $this->assertCount(1, $this->selectWhere($resourceItemActionsPage, function ($resourceActions, $resourceIdentifier) use ($resource2) {
            return $resourceIdentifier === $resource2->getResourceIdentifier()
                && $resourceActions === [ResourceActionGrantService::MANAGE_ACTION];
        }, true));

        $resourceItemActionsPage = $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, 'read');
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
            self::TEST_RESOURCE_CLASS, 'write');
        $this->assertCount(1, $resourceItemActionsPage);
        $this->assertCount(1, $this->selectWhere($resourceItemActionsPage, function ($resourceActions, $resourceIdentifier) use ($resource2) {
            return count($resourceActions) === 1
                && $resourceIdentifier === $resource2->getResourceIdentifier()
                && in_array(ResourceActionGrantService::MANAGE_ACTION, $resourceActions, true);
        }, true));
    }

    public function testIsCurrentUserGrantedItemActionWithManageGrant(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            'foo'));

        $this->testEntityManager->addResourceActionGrant($resource,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            'foo'));

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            'foo'));
    }

    public function testIsCurrentUserGrantedItemActionWithReadGrant(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            'foo'));

        $this->testEntityManager->addResourceActionGrant($resource,
            TestResources::READ_ACTION,
            self::CURRENT_USER_IDENTIFIER);

        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            'foo'));

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            'foo'));
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

    public function testIsCurrentUserGrantedCollectionActionWithManageGrant(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, null);

        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedCollectionAction(
            self::TEST_RESOURCE_CLASS,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedCollectionAction(
            self::TEST_RESOURCE_CLASS,
            TestResources::CREATE_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedCollectionAction(
            self::TEST_RESOURCE_CLASS,
            'foo'));

        $this->testEntityManager->addResourceActionGrant($resource,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedCollectionAction(
            self::TEST_RESOURCE_CLASS,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedCollectionAction(
            self::TEST_RESOURCE_CLASS,
            TestResources::CREATE_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedCollectionAction(
            self::TEST_RESOURCE_CLASS,
            'foo'));

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedCollectionAction(
            self::TEST_RESOURCE_CLASS,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedCollectionAction(
            self::TEST_RESOURCE_CLASS,
            TestResources::CREATE_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedCollectionAction(
            self::TEST_RESOURCE_CLASS,
            'foo'));
    }

    public function testIsCurrentUserGrantedCollectionActionWithReadGrant(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, null);

        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedCollectionAction(
            self::TEST_RESOURCE_CLASS, ResourceActionGrantService::MANAGE_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedCollectionAction(
            self::TEST_RESOURCE_CLASS, TestResources::READ_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedCollectionAction(
            self::TEST_RESOURCE_CLASS, 'foo'));

        $this->testEntityManager->addResourceActionGrant($resource,
            TestResources::READ_ACTION,
            self::CURRENT_USER_IDENTIFIER);

        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedCollectionAction(
            self::TEST_RESOURCE_CLASS,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedCollectionAction(
            self::TEST_RESOURCE_CLASS,
            TestResources::READ_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedCollectionAction(
            self::TEST_RESOURCE_CLASS,
            'foo'));

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedCollectionAction(
            self::TEST_RESOURCE_CLASS,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedCollectionAction(
            self::TEST_RESOURCE_CLASS,
            TestResources::READ_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedCollectionAction(
            self::TEST_RESOURCE_CLASS,
            'foo'));
    }

    public function testRemoveResourceActionGrant(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $rag = $this->testEntityManager->addResourceActionGrant($authorizationResource,
            TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertNotNull($this->testEntityManager->getResourceActionGrantByIdentifier($rag->getIdentifier()));
        $this->resourceActionGrantService->removeResourceActionGrant($rag->getIdentifier());
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($rag->getIdentifier()));
    }
}
