<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Service;

use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

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
        $kernel = self::bootKernel();

        if ('test' !== $kernel->getEnvironment()) {
            throw new \RuntimeException('Execution only in Test environment possible!');
        }

        $this->initDatabase($kernel);

        $entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->testEntityManager = new TestEntityManager($entityManager);
        $this->internalResourceActionGrantService = self::createInternalResourceActionGrantService(
            $this->testEntityManager->getEntityManager());
    }

    private function initDatabase(KernelInterface $kernel): void
    {
        $entityManager = $kernel->getContainer()->get('doctrine.orm.entity_manager');
        $metaData = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->updateSchema($metaData);
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
            $resource->getIdentifier(), InternalResourceActionGrantService::MANAGE_ACTION, 'userIdentifier', null);

        $this->assertEquals($resource->getIdentifier(),
            $this->testEntityManager->getResource($resource->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant->getIdentifier(),
            $this->testEntityManager->getResourceActionGrant($resourceActionGrant->getIdentifier())->getIdentifier());

        $this->internalResourceActionGrantService->removeResource('resourceClass', 'resourceIdentifier');

        $this->assertNull($this->testEntityManager->getResource($resource->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrant($resourceActionGrant->getIdentifier()));
    }

    public function testIsUserResourceManagerOf(): void
    {
        $resource = $this->testEntityManager->addResource('resourceClass', 'resourceIdentifier');
        $this->assertFalse($this->internalResourceActionGrantService->isUserResourceManagerOf(
            'userIdentifier', $resource->getIdentifier()));

        $resourceActionGrant = $this->testEntityManager->addResourceActionGrant($resource->getIdentifier(),
            InternalResourceActionGrantService::MANAGE_ACTION, 'userIdentifier');

        $this->assertNotNull($this->testEntityManager->getResourceActionGrant($resourceActionGrant->getIdentifier()));

        $this->assertTrue($this->internalResourceActionGrantService->isUserResourceManagerOf(
            'userIdentifier', $resource->getIdentifier()));
    }

    public function testGetResourceActionGrantsForResourceClassAndIdentifier(): void
    {
        $resource = $this->testEntityManager->addResource('resourceClass', 'resourceIdentifier');
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier');

        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrant = $this->testEntityManager->addResourceActionGrant($resource->getIdentifier(),
            InternalResourceActionGrantService::MANAGE_ACTION, 'userIdentifier');

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier');

        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());
        $this->assertEquals($resource->getIdentifier(), $resourceActionGrants[0]->getAuthorizationResourceIdentifier());
        $this->assertEquals(InternalResourceActionGrantService::MANAGE_ACTION, $resourceActionGrants[0]->getAction());
        $this->assertEquals('userIdentifier', $resourceActionGrants[0]->getUserIdentifier());

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier();
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass');
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', [InternalResourceActionGrantService::MANAGE_ACTION]);
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', null, 'userIdentifier');
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            'resourceClass', 'resourceIdentifier', [InternalResourceActionGrantService::MANAGE_ACTION], 'userIdentifier');
        $this->assertCount(1, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            null, null, [InternalResourceActionGrantService::MANAGE_ACTION]);
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
            'resourceClass', 'resourceIdentifier', [InternalResourceActionGrantService::MANAGE_ACTION], 'userIdentifier_2');
        $this->assertCount(0, $resourceActionGrants);
    }
}
