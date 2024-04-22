<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Authorization;

use Dbp\Relay\AuthorizationBundle\Entity\Resource;
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
            $resourceActionGrant->getResource()->getIdentifier());
    }

    public function isCurrentUserAuthorizedToRemoveGrant(ResourceActionGrant $resourceActionGrant): bool
    {
        return $this->isCurrentUserResourceManagerOf(
            $resourceActionGrant->getResource()->getIdentifier());
    }

    public function isCurrentUserAuthorizedToReadGrant(ResourceActionGrant $resourceActionGrant): bool
    {
        $currentUserIdentifier = $this->getUserIdentifier();

        return
            ($currentUserIdentifier !== null
                && $resourceActionGrant->getUserIdentifier() === $currentUserIdentifier)
            || $this->isCurrentUserResourceManagerOf(
                $resourceActionGrant->getResource()->getIdentifier());
    }

    public function isCurrentUserAuthorizedToReadResource(Resource $item): bool
    {
        return count($this->resourceActionGrantService->getResourceActionGrantsForAuthorizationResourceIdentifier(
            $item->getIdentifier(), null, $this->getUserIdentifier())) > 0;
    }

    public function getResourcesCurrentUserIsAuthorizedToRead(int $currentPageNumber, int $maxNumItemsPerPage): array
    {
        $currentUserIdentifier = $this->getUserIdentifier();

        return $currentUserIdentifier !== null ? $this->resourceActionGrantService->getResources(
            null, null, null, $currentUserIdentifier,
            $currentPageNumber, $maxNumItemsPerPage) : [];
    }

    /**
     * @return ResourceActionGrant[]
     */
    public function getResourceActionGrantsUserIsAuthorizedToRead(int $currentPageNumber, int $maxNumItemsPerPage): array
    {
        $currentUserIdentifier = $this->getUserIdentifier();

        return $currentUserIdentifier !== null ? $this->resourceActionGrantService->getResourceActionGrantsUserIsAuthorizedToRead(
            $currentPageNumber, $maxNumItemsPerPage, $currentUserIdentifier) : [];
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
