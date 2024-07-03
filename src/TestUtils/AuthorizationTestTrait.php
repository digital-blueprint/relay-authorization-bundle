<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\TestUtils;

use ApiPlatform\Symfony\Bundle\Test\Client;
use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;

trait AuthorizationTestTrait
{
    public function setUpTestEntityManager(Client $client): void
    {
        $container = $client->getContainer();
        $entityManger = TestResourceActionGrantServiceFactory::createTestEntityManager($client->getKernel());
        $resourceActionGrantService = $container->get(ResourceActionGrantService::class);
        $resourceActionGrantService->setEntityManager($entityManger->getEntityManager());
    }
}
