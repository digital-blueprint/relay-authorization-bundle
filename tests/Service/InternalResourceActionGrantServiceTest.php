<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Service;

use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class InternalResourceActionGrantServiceTest extends WebTestCase
{
    private InternalResourceActionGrantService $internalResourceActionGrantService;
    private TestEntityManager $testEntityManager;

    public static function createInternalResourceActionGrantService(EntityManager $entityManager): InternalResourceActionGrantService
    {
        return new InternalResourceActionGrantService($entityManager);
    }

    protected function setUp(): void
    {
        $this->testEntityManager = TestEntityManager::create();
        $this->internalResourceActionGrantService = self::createInternalResourceActionGrantService(
            $this->testEntityManager->getEntityManager());
    }

    public function testAddResourceAndManageResourceGrantForUser(): void
    {
        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceAndManageResourceGrantForUser(
            'resourceClass', 'resourceIdentifier', 'userIdentifier');

        $resourcePersistence = $this->testEntityManager->getResource($resourceActionGrant->getAuthorizationResourceIdentifier());
        $this->assertEquals($resourcePersistence->getIdentifier(), $resourceActionGrant->getAuthorizationResourceIdentifier());
        $this->assertEquals('resourceIdentifier', $resourcePersistence->getResourceIdentifier());
        $this->assertEquals('resourceClass', $resourcePersistence->getResourceClass());

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrant($resourceActionGrant->getIdentifier());
        $this->assertSame($resourceActionGrant->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());
        $this->assertSame($resourceActionGrant->getAuthorizationResourceIdentifier(), $resourceActionGrantPersistence->getAuthorizationResourceIdentifier());
        $this->assertSame($resourceActionGrant->getAction(), $resourceActionGrantPersistence->getAction());
        $this->assertSame($resourceActionGrant->getUserIdentifier(), $resourceActionGrantPersistence->getUserIdentifier());
        $this->assertSame($resourceActionGrant->getGroupIdentifier(), $resourceActionGrantPersistence->getGroupIdentifier());
    }

    public function testRemoveResource(): void
    {
        $resource = $this->testEntityManager->addResource('resourceClass', 'resourceIdentifier');
        $resourceActionGrant = $this->testEntityManager->addResourceActionGrant(
            $resource->getIdentifier(), 'manage', 'userIdentifier', null);

        $this->assertEquals($resource->getIdentifier(),
            $this->testEntityManager->getResource($resource->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant->getIdentifier(),
            $this->testEntityManager->getResourceActionGrant($resourceActionGrant->getIdentifier())->getIdentifier());

        $this->internalResourceActionGrantService->removeResource('resourceClass', 'resourceIdentifier');

        //        $this->testEntityManager->deleteResource($resource->getIdentifier());
        //        $this->testEntityManager->deleteResourceActionGrant($resourceActionGrant->getIdentifier());

        $this->assertNull($this->testEntityManager->getResource($resource->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrant($resourceActionGrant->getIdentifier()));
    }
}
