<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\API;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ResourceActionGrantServiceTest extends WebTestCase
{
    private const CURRENT_USER_IDENTIFIER = 'userIdentifier';

    private ResourceActionGrantService $resourceActionGrantService;
    private TestEntityManager $testEntityManager;
    private AuthorizationService $authorizationService;

    protected function setUp(): void
    {
        $this->testEntityManager = new TestEntityManager(self::bootKernel());
        $internalResourceActionGrantService = new InternalResourceActionGrantService(
            $this->testEntityManager->getEntityManager());
        $this->authorizationService = new AuthorizationService($internalResourceActionGrantService);
        TestAuthorizationService::setUp($this->authorizationService, self::CURRENT_USER_IDENTIFIER);
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

    public function testGetResourceActionGrantsForResourceClassAndIdentifier(): void
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
}
