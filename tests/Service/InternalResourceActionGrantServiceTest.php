<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Service;

use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class InternalResourceActionGrantServiceTest extends WebTestCase
{
    private InternalResourceActionGrantService $internalResourceActionGrantService;
    private TestEntityManager $entityManager;

    public static function createInternalResourceActionGrantService(EntityManager $entityManager): InternalResourceActionGrantService
    {
        return new InternalResourceActionGrantService($entityManager);
    }

    protected function setUp(): void
    {
        $this->entityManager = TestEntityManager::create();
        $this->internalResourceActionGrantService = self::createInternalResourceActionGrantService(
            $this->entityManager->getEntityManager());
    }

    public function testAddResourceActionGrant(): void
    {
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setNamespace('namespace');
        $resourceActionGrant->setResourceIdentifier('resourceIdentifier');
        $resourceActionGrant->setAction('action');
        $resourceActionGrant->setUserIdentifier('userIdentifier');
        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);

        $resourceActionGrantPersistence = $this->entityManager->getResourceActionGrant($resourceActionGrant->getIdentifier());

        $this->assertSame($resourceActionGrant->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());
        $this->assertSame($resourceActionGrant->getNamespace(), $resourceActionGrantPersistence->getNamespace());
        $this->assertSame($resourceActionGrant->getResourceIdentifier(), $resourceActionGrantPersistence->getResourceIdentifier());
        $this->assertSame($resourceActionGrant->getAction(), $resourceActionGrantPersistence->getAction());
        $this->assertSame($resourceActionGrant->getUserIdentifier(), $resourceActionGrantPersistence->getUserIdentifier());
        $this->assertSame($resourceActionGrant->getGroupIdentifier(), $resourceActionGrantPersistence->getGroupIdentifier());
    }
}
