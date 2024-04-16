<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Authorization;

use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Service\ResourceActionGrantService;
use Dbp\Relay\CoreBundle\Authorization\AbstractAuthorizationService;

class AuthorizationService extends AbstractAuthorizationService
{
    private ResourceActionGrantService $resourceActionGrantService;

    public function __construct(ResourceActionGrantService $resourceActionGrantService)
    {
        $this->resourceActionGrantService = $resourceActionGrantService;
    }

    public function isCurrentUserAuthorizedToAddGrant(ResourceActionGrant $resourceActionGrant): bool
    {
        return $this->isCurrentUserResourceManagerOf(
            $resourceActionGrant->getNamespace(), $resourceActionGrant->getResourceIdentifier());
    }

    public function isCurrentUserAuthorizedToRemoveGrant(ResourceActionGrant $resourceActionGrant): bool
    {
        return $this->isCurrentUserResourceManagerOf(
            $resourceActionGrant->getNamespace(), $resourceActionGrant->getResourceIdentifier());
    }

    public function isCurrentUserAuthorizedToReadGrant(ResourceActionGrant $resourceActionGrant): bool
    {
        return $resourceActionGrant->getUserIdentifier() === $this->getUserIdentifier()
            || $this->isCurrentUserResourceManagerOf(
                $resourceActionGrant->getNamespace(), $resourceActionGrant->getResourceIdentifier());
    }

    private function isCurrentUserResourceManagerOf(string $namespace, string $resourceIdentifier): bool
    {
        return $this->resourceActionGrantService->isUserResourceManagerOf($this->getUserIdentifier(),
            $namespace, $resourceIdentifier);
    }
}
