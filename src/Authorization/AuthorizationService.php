<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Authorization;

use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Entity\GroupMember;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\CoreBundle\Authorization\AbstractAuthorizationService;
use Dbp\Relay\CoreBundle\Authorization\AuthorizationException;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class AuthorizationService extends AbstractAuthorizationService
{
    public const MANAGE_ACTION = 'manage';

    private const READ_GROUP_ACTION = 'read';
    private const CREATE_GROUPS_ACTION = 'create';
    private const DELETE_GROUP_ACTION = 'delete';
    private const ADD_GROUP_MEMBERS_GROUP_ACTION = 'add_members';
    private const DELETE_GROUP_MEMBERS_GROUP_ACTION = 'delete_members';

    public const GROUP_RESOURCE_CLASS = 'DbpRelayAuthorizationGroup';

    private InternalResourceActionGrantService $resourceActionGrantService;

    public function __construct(InternalResourceActionGrantService $resourceActionGrantService)
    {
        parent::__construct();

        $this->resourceActionGrantService = $resourceActionGrantService;
    }

    public function setConfig(array $config)
    {
        $policies = [];
        $policies[self::GROUP_RESOURCE_CLASS] = $config[Configuration::CREATE_GROUPS_POLICY];
        foreach ($config[Configuration::RESOURCE_CLASSES] ?? [] as $resourceClassConfig) {
            $policies[$resourceClassConfig[Configuration::RESOURCE_CLASS_IDENTIFIER]] =
                $resourceClassConfig[Configuration::MANAGE_RESOURCE_COLLECTION_POLICY];
        }

        $this->configure($policies);
    }

    /**
     * @throws ApiError
     */
    public function addResource(string $resourceClass, string $resourceIdentifier): void
    {
        $this->assertResouceClassNotReserved($resourceClass);

        $this->resourceActionGrantService->addResourceAndManageResourceGrantForUser(
            $resourceClass, $resourceIdentifier, $this->getCurrentUserIdentifier(true));
    }

    /**
     * Deletes all resource action grants for the given resource.
     *
     * @throws ApiError
     */
    public function removeResource(string $resourceClass, string $resourceIdentifier): void
    {
        $this->assertResouceClassNotReserved($resourceClass);

        $this->resourceActionGrantService->removeResource($resourceClass, $resourceIdentifier);
    }

    /**
     * @parram string|null $resourceIdentifier null refers to the collection of the respective resource class.
     *
     * @return ResourceActionGrant[]
     *
     * @throws ApiError
     */
    public function getResourceItemActionGrants(string $resourceClass, ?string $resourceIdentifier = null,
        ?array $actions = null, int $currentPageNumber = 1, int $maxNumItemsPerPage = 1024): array
    {
        $this->assertResouceClassNotReserved($resourceClass);
        $currentUserIdentifier = $this->getCurrentUserIdentifier(false);

        return $currentUserIdentifier !== null ?
            $this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
                $resourceClass, $resourceIdentifier, $actions, $currentUserIdentifier, $currentPageNumber, $maxNumItemsPerPage) :
            [];
    }

    public function getResourceCollectionActionGrants(string $resourceClass, ?array $actions, int $currentPageNumber, int $maxNumItemsPerPage)
    {
        $this->assertResouceClassNotReserved($resourceClass);
        $currentUserIdentifier = $this->getCurrentUserIdentifier(false);

        $grants = [];
        if ($currentUserIdentifier !== null) {
            $grants = $this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
                $resourceClass, InternalResourceActionGrantService::IS_NULL, $actions,
                $currentUserIdentifier, $currentPageNumber, $maxNumItemsPerPage);

            // if
            // * the current page is not yet full
            // * and the manage action/or all actions are requested
            // * and no manage action grant was found in the database
            // * and there is a manage resource collection policy defined, which evaluates to true
            // then add a manage resource collection grant to the list of grants
            if (count($grants) < $maxNumItemsPerPage
                && ($actions === null /* any action */ || in_array(self::MANAGE_ACTION, $actions, true))) {
                $foundManageCollectionGrant = false;
                foreach ($grants as $grant) {
                    if ($grant->getAuthorizationResource()->getResourceIdentifier() === null && $grant->getAction() === self::MANAGE_ACTION) {
                        $foundManageCollectionGrant = true;
                    }
                }
                if (!$foundManageCollectionGrant) {
                    try {
                        if ($this->isGranted($resourceClass)) {
                            $authorizationResource = new AuthorizationResource();
                            $authorizationResource->setResourceClass($resourceClass);
                            $resourceActionGrant = new ResourceActionGrant();
                            $resourceActionGrant->setAuthorizationResource($authorizationResource);
                            $resourceActionGrant->setAction(self::MANAGE_ACTION);
                            $resourceActionGrant->setUserIdentifier($currentUserIdentifier);
                            $grants[] = $resourceActionGrant;
                        }
                    } catch (AuthorizationException $authorizationException) {
                        // policy undefined is fine - there's just no policy configured for this resource class
                        if ($authorizationException->getCode() !== AuthorizationException::POLICY_UNDEFINED) {
                            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, $authorizationException->getMessage());
                        }
                    }
                }
            }
        }

        return $grants;
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
        return $this->isCurrentUserAuthorizedToManageOr(self::CREATE_GROUPS_ACTION,
            self::GROUP_RESOURCE_CLASS, InternalResourceActionGrantService::IS_NULL)
            || $this->isGranted(self::GROUP_RESOURCE_CLASS);
    }

    public function isCurrentUserAuthorizedToRemoveGroup(Group $group): bool
    {
        return $this->isCurrentUserAuthorizedToManageOr(self::DELETE_GROUP_ACTION,
            self::GROUP_RESOURCE_CLASS, $group->getIdentifier());
    }

    public function isCurrentUserAuthorizedToReadGroup(Group $group): bool
    {
        return $this->isCurrentUserAuthorizedToManageOr(self::READ_GROUP_ACTION,
            self::GROUP_RESOURCE_CLASS, $group->getIdentifier());
    }

    public function isCurrentUserAuthorizedToAddGroupMember(GroupMember $groupMember): bool
    {
        return $this->isCurrentUserAuthorizedToManageOr(self::ADD_GROUP_MEMBERS_GROUP_ACTION,
            self::GROUP_RESOURCE_CLASS, $groupMember->getGroup()->getIdentifier());
    }

    public function isCurrentUserAuthorizedToRemoveGroupMember(GroupMember $groupMember): bool
    {
        return $this->isCurrentUserAuthorizedToManageOr(self::DELETE_GROUP_MEMBERS_GROUP_ACTION,
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
            && count($this->resourceActionGrantService->getResourceActionGrantsForAuthorizationResourceIdentifier(
                $authorizationResourceIdentifier, [AuthorizationService::MANAGE_ACTION], $currentUserIdentifier)) > 0;
    }

    private function isCurrentUserAuthorizedToManageOr(string $action,
        string $resourceClass, ?string $resourceIdentifier): bool
    {
        $currentUserIdentifier = $this->getUserIdentifier();

        return
            $currentUserIdentifier !== null
            && count($this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
                $resourceClass, $resourceIdentifier, [self::MANAGE_ACTION, $action], $currentUserIdentifier)) > 0;
    }

    /**
     * @throws ApiError
     */
    private function assertResouceClassNotReserved(string $resourceClass)
    {
        if ($resourceClass === self::GROUP_RESOURCE_CLASS) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'The resource class \''.$resourceClass.'\' is reserved.');
        }
    }

    private function getCurrentUserIdentifier(bool $throwIfNull): ?string
    {
        $currentUserIdentifier = $this->getUserIdentifier();
        if ($currentUserIdentifier === null && $throwIfNull) {
            throw ApiError::withDetails(Response::HTTP_FORBIDDEN,
                'a user identifier is required for authorization. are you a system account client?');
        }

        return $currentUserIdentifier;
    }
}
