<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractAuthorizationServiceTestCase;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;

abstract class AbstractResourceActionGrantControllerAuthorizationServiceTestCase extends AbstractAuthorizationServiceTestCase
{
    protected function getResourceActionGrant(string $identifier): ?ResourceActionGrant
    {
        return $this->testEntityManager->getResourceActionGrantByIdentifier($identifier);
    }

    protected function addResource(string $resourceClass = TestEntityManager::DEFAULT_RESOURCE_CLASS,
        ?string $resourceIdentifier = TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER): AuthorizationResource
    {
        return $this->testEntityManager->addAuthorizationResource($resourceClass, $resourceIdentifier);
    }

    protected function addResourceAndManageGrant(string $resourceClass = TestEntityManager::DEFAULT_RESOURCE_CLASS,
        ?string $resourceIdentifier = TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER,
        string $userIdentifier = self::CURRENT_USER_IDENTIFIER): ResourceActionGrant
    {
        return $this->addResourceAndGrant($resourceClass, $resourceIdentifier,
            AuthorizationService::MANAGE_ACTION, $userIdentifier);
    }

    protected function addResourceAndGrant(string $resourceClass = TestEntityManager::DEFAULT_RESOURCE_CLASS,
        ?string $resourceIdentifier = TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER,
        string $action = 'action',
        string $userIdentifier = self::CURRENT_USER_IDENTIFIER): ResourceActionGrant
    {
        $resource = $this->addResource($resourceClass, $resourceIdentifier);

        return $this->testEntityManager->addResourceActionGrant($resource, $action, $userIdentifier);
    }

    protected function addResourceActionGrant(AuthorizationResource $resource, string $action,
        string $userIdentifier = self::CURRENT_USER_IDENTIFIER,
        ?Group $group = null, ?string $dynamicGroupIdentifier = null): ResourceActionGrant
    {
        return $this->testEntityManager->addResourceActionGrant(
            $resource, $action, $userIdentifier, $group, $dynamicGroupIdentifier);
    }

    protected function addGrant(AuthorizationResource $resource,
        string $action = 'action',
        string $userIdentifier = self::CURRENT_USER_IDENTIFIER): ResourceActionGrant
    {
        return $this->testEntityManager->addResourceActionGrant($resource, $action, $userIdentifier);
    }

    protected function addManageGrant(AuthorizationResource $resource,
        string $userIdentifier = self::CURRENT_USER_IDENTIFIER): ResourceActionGrant
    {
        return $this->addGrant(
            $resource, AuthorizationService::MANAGE_ACTION, $userIdentifier);
    }
}
