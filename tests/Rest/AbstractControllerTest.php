<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\Resource;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractControllerTest extends WebTestCase
{
    protected const CURRENT_USER_IDENTIFIER = 'userIdentifier';

    protected InternalResourceActionGrantService $internalResourceActionGrantService;
    protected TestEntityManager $testEntityManager;
    protected AuthorizationService $authorizationService;

    protected function setUp(): void
    {
        $this->testEntityManager = new TestEntityManager(self::bootKernel());
        $this->internalResourceActionGrantService = new InternalResourceActionGrantService($this->testEntityManager->getEntityManager());
        $this->authorizationService = new AuthorizationService($this->internalResourceActionGrantService);
        TestAuthorizationService::setUp($this->authorizationService, self::CURRENT_USER_IDENTIFIER);
    }

    protected function getResourceActionGrant(string $identifier): ?ResourceActionGrant
    {
        return $this->testEntityManager->getResourceActionGrant($identifier);
    }

    protected function addResource(string $resourceClass = 'resourceClass',
        string $resourceIdentifier = 'resourceIdentifier'): Resource
    {
        return $this->testEntityManager->addResource($resourceClass, $resourceIdentifier);
    }

    protected function addResourceAndManageGrant(string $resourceClass = 'resourceClass',
        string $resourceIdentifier = 'resourceIdentifier',
        string $userIdentifier = self::CURRENT_USER_IDENTIFIER): ResourceActionGrant
    {
        return $this->addResourceAndGrant($resourceClass, $resourceIdentifier,
            InternalResourceActionGrantService::MANAGE_ACTION, $userIdentifier);
    }

    protected function addResourceAndGrant(string $resourceClass = 'resourceClass',
        string $resourceIdentifier = 'resourceIdentifier',
        string $action = 'action',
        string $userIdentifier = self::CURRENT_USER_IDENTIFIER): ResourceActionGrant
    {
        $resource = $this->addResource($resourceClass, $resourceIdentifier);

        return $this->testEntityManager->addResourceActionGrant($resource, $action, $userIdentifier);
    }

    protected function addGrant(Resource $resource,
        string $action = 'action',
        string $userIdentifier = self::CURRENT_USER_IDENTIFIER): ResourceActionGrant
    {
        return $this->testEntityManager->addResourceActionGrant($resource, $action, $userIdentifier);
    }

    protected function addManageGrant(Resource $resource,
        string $userIdentifier = self::CURRENT_USER_IDENTIFIER): ResourceActionGrant
    {
        return $this->addGrant(
            $resource, InternalResourceActionGrantService::MANAGE_ACTION, $userIdentifier);
    }
}
