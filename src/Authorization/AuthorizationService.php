<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Authorization;

use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Entity\GroupMember;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\CoreBundle\Authorization\AbstractAuthorizationService;
use Dbp\Relay\CoreBundle\Exception\ApiError;

/**
 * @internal
 */
class AuthorizationService extends AbstractAuthorizationService
{
    public const GROUP_RESOURCE_CLASS = 'DbpRelayAuthorizationGroup';
    private InternalResourceActionGrantService $resourceActionGrantService;

    public function __construct(InternalResourceActionGrantService $resourceActionGrantService)
    {
        $this->resourceActionGrantService = $resourceActionGrantService;
    }

    /**
     * @throws ApiError
     */
    public function addGroup(string $groupIdentifier): void
    {
        $this->resourceActionGrantService->addResourceAndManageResourceGrantForUser(
            self::GROUP_RESOURCE_CLASS, $groupIdentifier, $this->getUserIdentifier());
    }

    /**
     * @throws ApiError
     */
    public function removeGroup(string $groupIdentifier): void
    {
        $this->resourceActionGrantService->removeResource(self::GROUP_RESOURCE_CLASS, $groupIdentifier);
    }

    public function isCurrentUserAuthorizedToAddGroup(Group $group): bool
    {
        return $this->doesCurrentUserHaveAManageGrantForResourceCollection(self::GROUP_RESOURCE_CLASS);
    }

    public function isCurrentUserAuthorizedToRemoveGroup(Group $group): bool
    {
        return $this->doesCurrentUserHaveAManageGrantForResource(
            self::GROUP_RESOURCE_CLASS, $group->getIdentifier());
    }

    public function isCurrentUserAuthorizedToReadGroup(Group $group): bool
    {
        return $this->doesCurrentUserHaveAManageGrantForResource(
            self::GROUP_RESOURCE_CLASS, $group->getIdentifier());
    }

    public function isCurrentUserAuthorizedToAddGroupMember(GroupMember $groupMember): bool
    {
        return $this->doesCurrentUserHaveAManageGrantForResource(
            self::GROUP_RESOURCE_CLASS, $groupMember->getGroup()->getIdentifier());
    }

    public function isCurrentUserAuthorizedToRemoveGroupMember(GroupMember $groupMember): bool
    {
        return $this->doesCurrentUserHaveAManageGrantForResource(
            self::GROUP_RESOURCE_CLASS, $groupMember->getGroup()->getIdentifier());
    }

    public function isCurrentUserAuthorizedToAddGrant(ResourceActionGrant $resourceActionGrant): bool
    {
        return $this->doesCurrentUserHaveAManageGrantForAuthorizationResource(
            $resourceActionGrant->getAuthorizationResource()->getIdentifier());
    }

    public function isCurrentUserAuthorizedToRemoveGrant(ResourceActionGrant $resourceActionGrant): bool
    {
        return $this->doesCurrentUserHaveAManageGrantForAuthorizationResource(
            $resourceActionGrant->getAuthorizationResource()->getIdentifier());
    }

    public function isCurrentUserAuthorizedToReadGrant(ResourceActionGrant $resourceActionGrant): bool
    {
        $currentUserIdentifier = $this->getUserIdentifier();

        return
            ($currentUserIdentifier !== null
                && $resourceActionGrant->getUserIdentifier() === $currentUserIdentifier)
            || $this->doesCurrentUserHaveAManageGrantForAuthorizationResource(
                $resourceActionGrant->getAuthorizationResource()->getIdentifier());
    }

    public function isCurrentUserAuthorizedToReadResource(AuthorizationResource $item): bool
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

    private function doesCurrentUserHaveAManageGrantForAuthorizationResource(
        string $authorizationResourceIdentifier): bool
    {
        $currentUserIdentifier = $this->getUserIdentifier();

        return
            $currentUserIdentifier !== null
            && $this->resourceActionGrantService->doesUserHaveAManageGrantForAuthorizationResource(
                $currentUserIdentifier, $authorizationResourceIdentifier);
    }

    private function doesCurrentUserHaveAManageGrantForResource(
        string $resourceClass, string $resourceIdentifier): bool
    {
        $currentUserIdentifier = $this->getUserIdentifier();

        return
            $currentUserIdentifier !== null
            && $this->resourceActionGrantService->doesUserHaveAManageGrantForResource(
                $currentUserIdentifier, $resourceClass, $resourceIdentifier);
    }

    private function doesCurrentUserHaveAManageGrantForResourceCollection(
        string $resourceClass): bool
    {
        $currentUserIdentifier = $this->getUserIdentifier();

        return
            $currentUserIdentifier !== null
            && $this->resourceActionGrantService->doesUserHaveAManageGrantForResource(
                $currentUserIdentifier, $resourceClass, null);
    }
}
