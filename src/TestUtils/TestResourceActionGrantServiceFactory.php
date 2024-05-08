<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\TestUtils;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Symfony\Component\HttpKernel\KernelInterface;

class TestResourceActionGrantServiceFactory
{
    public static function createTestResourceActionGrantService(KernelInterface $kernel,
        string $currentUserIdentifier = TestAuthorizationService::TEST_USER_IDENTIFIER,
        array $currentUserAttributes = []): ResourceActionGrantService
    {
        $testEntityManager = new TestEntityManager($kernel);
        $internalResourceActionGrantService = new InternalResourceActionGrantService(
            $testEntityManager->getEntityManager());
        $authorizationService = new AuthorizationService(
            $internalResourceActionGrantService, new GroupService($testEntityManager->getEntityManager()));
        TestAuthorizationService::setUp($authorizationService, $currentUserIdentifier, $currentUserAttributes);
        $authorizationService->setConfig(self::getTestConfig());

        return new ResourceActionGrantService($authorizationService);
    }

    private static function getTestConfig(): array
    {
        return [
            'database_url' => 'sqlite:///:memory:',
        ];
    }
}
