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
            $resourceActionGrant->getAuthorizationResourceIdentifier());
    }

    public function isCurrentUserAuthorizedToRemoveGrant(ResourceActionGrant $resourceActionGrant): bool
    {
        return $this->isCurrentUserResourceManagerOf(
            $resourceActionGrant->getAuthorizationResourceIdentifier());
    }

    public function isCurrentUserAuthorizedToReadGrant(ResourceActionGrant $resourceActionGrant): bool
    {
        $currentUserIdentifier = $this->getUserIdentifier();

        return
            ($currentUserIdentifier !== null
                && $resourceActionGrant->getUserIdentifier() === $currentUserIdentifier)
            || $this->isCurrentUserResourceManagerOf(
                $resourceActionGrant->getAuthorizationResourceIdentifier());
    }

    private function isCurrentUserResourceManagerOf(string $authorizationResourceIdentifier): bool
    {
        $currentUserIdentifier = $this->getUserIdentifier();

        return
            $currentUserIdentifier !== null
            && $this->resourceActionGrantService->isUserResourceManagerOf($currentUserIdentifier,
                $authorizationResourceIdentifier);
    }
}
