<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\API;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractTestCase;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;

class ResourceActionGrantServiceTest extends AbstractTestCase
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

        $resourcePersistence = $this->testEntityManager->getAuthorizationResourceByClassAndIdentifier(
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
            $resource, AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER, null);

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
            $resource1, AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant2 = $this->testEntityManager->addResourceActionGrant(
            $resource2, AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant3 = $this->testEntityManager->addResourceActionGrant(
            $resource3, AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

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

        $resourceActions = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier');
        $this->assertCount(0, $resourceActions);

        $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1, 'write', self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1, 'read', self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2, 'read', self::CURRENT_USER_IDENTIFIER);

        $resourceActions = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass');
        $this->assertCount(2, $resourceActions);
        $this->assertCount(1, $this->selectWhere($resourceActions, function ($resourceActions) use ($resource1) {
            return count($resourceActions->getActions()) === 2
                && $resourceActions->getResourceIdentifier() === $resource1->getResourceIdentifier()
                && in_array(AuthorizationService::MANAGE_ACTION, $resourceActions->getActions(), true)
                && in_array('write', $resourceActions->getActions(), true);
        }));
        $this->assertCount(1, $this->selectWhere($resourceActions, function ($resourceActions) use ($resource2) {
            return count($resourceActions->getActions()) === 1
                && $resourceActions->getResourceIdentifier() === $resource2->getResourceIdentifier()
                && in_array('read', $resourceActions->getActions(), true);
        }));

        $resourceActions = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier');
        $this->assertCount(1, $resourceActions);
        $this->assertCount(2, $resourceActions[0]->getActions());
        $this->assertContains(AuthorizationService::MANAGE_ACTION, $resourceActions[0]->getActions());
        $this->assertContains('write', $resourceActions[0]->getActions());

        $resourceActions = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier', [AuthorizationService::MANAGE_ACTION, 'write']);
        $this->assertCount(1, $resourceActions);
        $this->assertCount(2, $resourceActions[0]->getActions());

        $resourceActions = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier', [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $resourceActions);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceActions[0]->getActions());

        $resourceActions = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier', [AuthorizationService::MANAGE_ACTION, 'read']);
        $this->assertCount(1, $resourceActions);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceActions[0]->getActions());

        $resourceActions = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier', ['write']);
        $this->assertCount(1, $resourceActions);
        $this->assertEquals(['write'], $resourceActions[0]->getActions());

        $resourceActions = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier', ['read']);
        $this->assertCount(0, $resourceActions);

        $resourceActions = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', null, [AuthorizationService::MANAGE_ACTION, 'write']);
        $this->assertCount(1, $resourceActions);
        $this->assertCount(2, $resourceActions[0]->getActions());
        $this->assertContains(AuthorizationService::MANAGE_ACTION, $resourceActions[0]->getActions());
        $this->assertContains('write', $resourceActions[0]->getActions());

        $resourceActions = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', null, [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $resourceActions);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceActions[0]->getActions());

        $resourceActions = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', null, ['write']);
        $this->assertCount(1, $resourceActions);
        $this->assertEquals($resource1->getResourceIdentifier(), $resourceActions[0]->getResourceIdentifier());
        $this->assertEquals(['write'], $resourceActions[0]->getActions());

        $resourceActions = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', null, ['delete']);
        $this->assertCount(0, $resourceActions);

        $resourceActions = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass_2');
        $this->assertCount(0, $resourceActions);

        $resourceActions = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'foo');
        $this->assertCount(0, $resourceActions);

        $resourceActions = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'foo', [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(0, $resourceActions);

        // -----------------------------------------------------------------
        // another user:
        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $resourceActions = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass');
        $this->assertCount(2, $resourceActions);
        $this->assertCount(1, $this->selectWhere($resourceActions, function ($resourceActions) use ($resource2) {
            return count($resourceActions->getActions()) === 1
                && $resourceActions->getResourceIdentifier() === $resource2->getResourceIdentifier()
                && in_array(AuthorizationService::MANAGE_ACTION, $resourceActions->getActions(), true);
        }));
        $this->assertCount(1, $this->selectWhere($resourceActions, function ($resourceActions) use ($resource1) {
            return count($resourceActions->getActions()) === 1
                && $resourceActions->getResourceIdentifier() === $resource1->getResourceIdentifier()
                && in_array('read', $resourceActions->getActions(), true);
        }));

        $resourceActions = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier_2');
        $this->assertCount(1, $resourceActions);
        $this->assertEquals($resource2->getResourceIdentifier(), $resourceActions[0]->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceActions[0]->getActions());

        $resourceActions = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier_2', [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $resourceActions);
        $this->assertEquals($resource2->getResourceIdentifier(), $resourceActions[0]->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceActions[0]->getActions());

        $resourceActions = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', null, [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $resourceActions);
        $this->assertEquals($resource2->getResourceIdentifier(), $resourceActions[0]->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceActions[0]->getActions());
    }

    public function testHasGrantedResourceItemActions(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $this->assertFalse($this->resourceActionGrantService->hasGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier'));

        $this->testEntityManager->addResourceActionGrant($resource,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->assertTrue($this->resourceActionGrantService->hasGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier'));
        $this->assertTrue($this->resourceActionGrantService->hasGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier', [ResourceActionGrantService::MANAGE_ACTION]));
        $this->assertFalse($this->resourceActionGrantService->hasGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier', ['read']));

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $this->assertFalse($this->resourceActionGrantService->hasGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier'));
    }

    public function testHasUserGrantedResourceItemActions(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $this->assertFalse($this->resourceActionGrantService->hasGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier'));

        $this->testEntityManager->addResourceActionGrant($resource,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->assertTrue($this->resourceActionGrantService->hasUserGrantedResourceItemActions(
            self::CURRENT_USER_IDENTIFIER, 'resourceClass', 'resourceIdentifier'));
        $this->assertTrue($this->resourceActionGrantService->hasUserGrantedResourceItemActions(
            self::CURRENT_USER_IDENTIFIER, 'resourceClass', 'resourceIdentifier', [ResourceActionGrantService::MANAGE_ACTION]));
        $this->assertFalse($this->resourceActionGrantService->hasUserGrantedResourceItemActions(
            self::CURRENT_USER_IDENTIFIER, 'resourceClass', 'resourceIdentifier', ['read']));
        $this->assertFalse($this->resourceActionGrantService->hasUserGrantedResourceItemActions(
            self::CURRENT_USER_IDENTIFIER.'_2', 'resourceClass', 'resourceIdentifier'));

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $this->assertTrue($this->resourceActionGrantService->hasUserGrantedResourceItemActions(
            self::CURRENT_USER_IDENTIFIER, 'resourceClass', 'resourceIdentifier'));
    }

    public function testGetGrantedResourceCollectionActions(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);

        $resourceCollectionActions = $this->resourceActionGrantService->getGrantedResourceCollectionActions(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $this->assertNull($resourceCollectionActions);

        $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $resourceCollectionActions = $this->resourceActionGrantService->getGrantedResourceCollectionActions(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $this->assertEquals($resource->getResourceIdentifier(), $resourceCollectionActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceCollectionActions->getActions());

        $resourceCollectionActions = $this->resourceActionGrantService->getGrantedResourceCollectionActions(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, [ResourceActionGrantService::MANAGE_ACTION]);
        $this->assertEquals($resource->getResourceIdentifier(), $resourceCollectionActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceCollectionActions->getActions());

        $resourceCollectionActions = $this->resourceActionGrantService->getGrantedResourceCollectionActions(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, ['write']);
        $this->assertNull($resourceCollectionActions);

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $resourceCollectionActions = $this->resourceActionGrantService->getGrantedResourceCollectionActions(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $this->assertNull($resourceCollectionActions);
    }

    public function testHasGrantedResourceCollectionActions(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource('resourceClass', null);
        $this->assertFalse($this->resourceActionGrantService->hasGrantedResourceCollectionActions(
            'resourceClass'));

        $this->testEntityManager->addResourceActionGrant($resource,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->assertTrue($this->resourceActionGrantService->hasGrantedResourceCollectionActions(
            'resourceClass'));
        $this->assertTrue($this->resourceActionGrantService->hasGrantedResourceCollectionActions(
            'resourceClass', [ResourceActionGrantService::MANAGE_ACTION]));
        $this->assertFalse($this->resourceActionGrantService->hasGrantedResourceCollectionActions(
            'resourceClass', ['read']));

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $this->assertFalse($this->resourceActionGrantService->hasGrantedResourceCollectionActions(
            'resourceClass'));
    }
}
