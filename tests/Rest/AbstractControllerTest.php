<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractControllerTest extends WebTestCase
{
    protected const CURRENT_USER_IDENTIFIER = 'userIdentifier';
    protected TestEntityManager $testEntityManager;
    protected AuthorizationService $authorizationService;
    protected InternalResourceActionGrantService $internalResourceActionGrantService;

    protected function setUp(): void
    {
        $this->testEntityManager = new TestEntityManager(self::bootKernel());
        $this->internalResourceActionGrantService = new InternalResourceActionGrantService($this->testEntityManager->getEntityManager());
        $this->authorizationService = new AuthorizationService($this->internalResourceActionGrantService);
        TestAuthorizationService::setUp($this->authorizationService, self::CURRENT_USER_IDENTIFIER);
    }

    protected function login(string $userIdentifier): void
    {
        TestAuthorizationService::setUp($this->authorizationService, $userIdentifier);
    }
}
