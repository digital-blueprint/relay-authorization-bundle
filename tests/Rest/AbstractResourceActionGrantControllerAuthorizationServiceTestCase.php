<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroup;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractAuthorizationServiceTestCase;

abstract class AbstractResourceActionGrantControllerAuthorizationServiceTestCase extends AbstractAuthorizationServiceTestCase
{
    protected function getResourceActionGrantFromDB(string $identifier): ?ResourceActionGrant
    {
        return $this->testEntityManager->getResourceActionGrantByIdentifier($identifier);
    }

    protected function addResource(
        string $resourceClass = self::TEST_RESOURCE_CLASS,
        string $resourceIdentifier = self::TEST_RESOURCE_IDENTIFIER,
        int $resourceType = InternalResourceActionGrantService::RESOURCE_RESOURCE_TYPE): AuthorizationResource
    {
        return $this->testEntityManager->addAuthorizationResource(
            $resourceClass, $resourceIdentifier, $resourceType);
    }

    protected function addResourceAndManageGrantToTestDB(string $resourceClass = self::TEST_RESOURCE_CLASS,
        string $resourceIdentifier = self::TEST_RESOURCE_IDENTIFIER,
        string $userIdentifier = self::CURRENT_USER_IDENTIFIER): ResourceActionGrant
    {
        return $this->addResourceAndGrant($resourceClass, $resourceIdentifier,
            AuthorizationService::MANAGE_ACTION, $userIdentifier);
    }

    protected function addResourceAndGrant(string $resourceClass = self::TEST_RESOURCE_CLASS,
        string $resourceIdentifier = self::TEST_RESOURCE_IDENTIFIER,
        string $action = 'action',
        string $userIdentifier = self::CURRENT_USER_IDENTIFIER): ResourceActionGrant
    {
        $resource = $this->addResource($resourceClass, $resourceIdentifier);

        return $this->testEntityManager->addResourceActionGrant($resource, $action, $userIdentifier);
    }

    protected function addResourceActionGrantToTestDB(AuthorizationResource $resource, string $action,
        string $userIdentifier = self::CURRENT_USER_IDENTIFIER,
        ?UserGroup $userGroup = null,
        ?string $dynamicUserGroupIdentifier = null,
        ?bool $shareable = null,
        ?ResourceActionGrant $shareOf = null): ResourceActionGrant
    {
        return $this->testEntityManager->addResourceActionGrant(
            $resource, $action,
            userIdentifier: $userIdentifier,
            userGroup: $userGroup,
            dynamicUserGroupIdentifier: $dynamicUserGroupIdentifier,
            shareable: $shareable,
            shareOf: $shareOf);
    }

    protected function addGrant(AuthorizationResource $resource,
        ?string $action = null,
        ?string $userIdentifier = self::CURRENT_USER_IDENTIFIER,
        ?string $roleIdentifier = null,
        ?bool $shareable = null
    ): ResourceActionGrant {
        return $this->testEntityManager->addResourceActionGrant($resource,
            action: $action,
            userIdentifier: $userIdentifier,
            roleIdentifier: $roleIdentifier,
            shareable: $shareable
        );
    }

    protected function addManageGrant(AuthorizationResource $resource,
        string $userIdentifier = self::CURRENT_USER_IDENTIFIER): ResourceActionGrant
    {
        return $this->addGrant(
            $resource, AuthorizationService::MANAGE_ACTION, $userIdentifier);
    }
}
