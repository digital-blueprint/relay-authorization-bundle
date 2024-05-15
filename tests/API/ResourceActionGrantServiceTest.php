<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\API;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractTestCase;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;

class ResourceActionGrantServiceTest extends AbstractTestCase
{
    private ResourceActionGrantService $resourceActionGrantService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resourceActionGrantService = new ResourceActionGrantService(
            $this->authorizationService);
    }

    public function testAddResource(): void
    {
        $this->resourceActionGrantService->addResource(
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

    public function testRemoveResource(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $resourceActionGrant = $this->testEntityManager->addResourceActionGrant(
            $resource, AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER, null);

        $this->assertEquals($resource->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier())->getIdentifier());

        $this->resourceActionGrantService->removeResource('resourceClass', 'resourceIdentifier');

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier()));
    }

    public function testGetGrantedResourceItemActions(): void
    {
        $ANOTHER_USER_IDENTIFIER = self::CURRENT_USER_IDENTIFIER.'_2';

        $resource = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier');

        $this->assertCount(0, $resourceActionGrants);

        $manageResourceGrant = $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource, 'write', self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource, 'read', $ANOTHER_USER_IDENTIFIER);

        // the grant of $ANOTHER_USER_IDENTIFIER must not be returned
        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass');
        $this->assertCount(2, $resourceActionGrants);
        $this->assertEquals($resource->getResourceIdentifier(), $resourceActionGrants[0]->getResourceIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $resourceActionGrants[0]->getAction());
        $this->assertEquals($resource->getResourceIdentifier(), $resourceActionGrants[1]->getResourceIdentifier());
        $this->assertEquals('write', $resourceActionGrants[1]->getAction());

        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier');
        $this->assertCount(2, $resourceActionGrants);

        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier', [AuthorizationService::MANAGE_ACTION, 'write']);
        $this->assertCount(2, $resourceActionGrants);

        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier', [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier', [AuthorizationService::MANAGE_ACTION, 'read']);
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier', ['write']);
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier', ['read']);
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', null, [AuthorizationService::MANAGE_ACTION, 'write']);
        $this->assertCount(2, $resourceActionGrants);

        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', null, [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', null, ['write']);
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', null, ['read']);
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass_2');
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier_2');
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier_2', [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(0, $resourceActionGrants);

        // log in $ANOTHER_USER_IDENTIFIER
        TestAuthorizationService::setUp($this->authorizationService, $ANOTHER_USER_IDENTIFIER);
        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier');
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier', ['read']);
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceItemActions(
            'resourceClass', 'resourceIdentifier', [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(0, $resourceActionGrants);
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

        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceCollectionActions(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $this->assertCount(0, $resourceActionGrants);

        $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceCollectionActions(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($resource->getResourceIdentifier(), $resourceActionGrants[0]->getResourceIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $resourceActionGrants[0]->getAction());

        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceCollectionActions(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, [ResourceActionGrantService::MANAGE_ACTION]);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($resource->getResourceIdentifier(), $resourceActionGrants[0]->getResourceIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $resourceActionGrants[0]->getAction());

        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceCollectionActions(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, ['write']);
        $this->assertCount(0, $resourceActionGrants);

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $resourceActionGrants = $this->resourceActionGrantService->getGrantedResourceCollectionActions(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $this->assertCount(0, $resourceActionGrants);
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
