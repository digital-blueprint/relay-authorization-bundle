<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;

abstract class AbstractResourceActionGrantControllerTest extends AbstractTest
{
    protected function getResourceActionGrant(string $identifier): ?ResourceActionGrant
    {
        return $this->testEntityManager->getResourceActionGrantByIdentifier($identifier);
    }

    protected function addResource(string $resourceClass = 'resourceClass',
        string $resourceIdentifier = 'resourceIdentifier'): AuthorizationResource
    {
        return $this->testEntityManager->addAuthorizationResource($resourceClass, $resourceIdentifier);
    }

    protected function addResourceAndManageGrant(string $resourceClass = 'resourceClass',
        string $resourceIdentifier = 'resourceIdentifier',
        string $userIdentifier = self::CURRENT_USER_IDENTIFIER): ResourceActionGrant
    {
        return $this->addResourceAndGrant($resourceClass, $resourceIdentifier,
            AuthorizationService::MANAGE_ACTION, $userIdentifier);
    }

    protected function addResourceAndGrant(string $resourceClass = 'resourceClass',
        string $resourceIdentifier = 'resourceIdentifier',
        string $action = 'action',
        string $userIdentifier = self::CURRENT_USER_IDENTIFIER): ResourceActionGrant
    {
        $resource = $this->addResource($resourceClass, $resourceIdentifier);

        return $this->testEntityManager->addResourceActionGrant($resource, $action, $userIdentifier);
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
