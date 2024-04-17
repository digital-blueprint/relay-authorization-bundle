<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Authorization;

use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\CoreBundle\Authorization\AbstractAuthorizationService;

/**
 * @internal
 */
class AuthorizationService extends AbstractAuthorizationService
{
    private InternalResourceActionGrantService $resourceActionGrantService;

    public function __construct(InternalResourceActionGrantService $resourceActionGrantService)
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
