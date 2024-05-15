<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Authorization;

use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Entity\GroupMember;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\CoreBundle\Authorization\AbstractAuthorizationService;
use Dbp\Relay\CoreBundle\Authorization\AuthorizationException;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class AuthorizationService extends AbstractAuthorizationService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const MANAGE_ACTION = 'manage';

    public const READ_GROUP_ACTION = 'read';
    public const CREATE_GROUPS_ACTION = 'create';
    public const DELETE_GROUP_ACTION = 'delete';
    public const ADD_GROUP_MEMBERS_GROUP_ACTION = 'add_members';
    public const DELETE_GROUP_MEMBERS_GROUP_ACTION = 'delete_members';

    public const GROUP_RESOURCE_CLASS = 'DbpRelayAuthorizationGroup';

    public const DYNAMIC_GROUP_UNDEFINED_ERROR_ID = 'authorization:dynamic-group-undefined';

    private InternalResourceActionGrantService $resourceActionGrantService;
    private GroupService $groupService;

    private static function getManageResourceCollectionPolicyName(string $resourceClass): string
    {
        return $resourceClass;
    }

    private static function getIsCurrentUserMemberOfDynamicGroupAttributeName(string $dynamicGroupIdentifier): string
    {
        return $dynamicGroupIdentifier;
    }

    private static function getDynamicGroupIdentifierFromAttributeName(string $attributeName): string
    {
        return $attributeName;
    }

    public function __construct(InternalResourceActionGrantService $resourceActionGrantService, GroupService $groupService)
    {
        parent::__construct();

        $this->resourceActionGrantService = $resourceActionGrantService;
        $this->groupService = $groupService;
    }

    public function setConfig(array $config): void
    {
        $policies = [];
        $policies[self::getManageResourceCollectionPolicyName(self::GROUP_RESOURCE_CLASS)] = $config[Configuration::CREATE_GROUPS_POLICY];
        foreach ($config[Configuration::RESOURCE_CLASSES] ?? [] as $resourceClassConfig) {
            $policies[self::getManageResourceCollectionPolicyName($resourceClassConfig[Configuration::IDENTIFIER])] =
                $resourceClassConfig[Configuration::MANAGE_RESOURCE_COLLECTION_POLICY];
        }

        $attributes = [];
        foreach ($config[Configuration::DYNAMIC_GROUPS] ?? [] as $dynamicGroup) {
            $attributes[self::getIsCurrentUserMemberOfDynamicGroupAttributeName($dynamicGroup[Configuration::IDENTIFIER])] =
                $dynamicGroup[Configuration::IS_CURRENT_USER_GROUP_MEMBER_EXPRESSION];
        }

        $this->configure($policies, $attributes);
    }

    /**
     * @throws ApiError
     */
    public function isCurrentUserMemberOfDynamicGroup(string $dynamicGroupIdentifier): bool
    {
        try {
            return $this->getAttribute(self::getIsCurrentUserMemberOfDynamicGroupAttributeName($dynamicGroupIdentifier));
        } catch (AuthorizationException $authorizationException) {
            if ($authorizationException->getCode() === AuthorizationException::ATTRIBUTE_UNDEFINED) {
                throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                    sprintf('dynamic group \'%s\' is undefined', $dynamicGroupIdentifier),
                    self::DYNAMIC_GROUP_UNDEFINED_ERROR_ID);
            } else {
                throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                    sprintf('failed to determine if current user is member of dynamic group \'%s\': %s',
                        $dynamicGroupIdentifier, $authorizationException->getMessage()));
            }
        }
    }

    /**
     * @return string[]
     */
    public function getDynamicGroupsCurrentUserIsMemberOf(): array
    {
        $currentUsersDynamicGroups = [];
        foreach ($this->getAttributeNames() as $attributeName) {
            if ($this->getAttribute($attributeName)) {
                $currentUsersDynamicGroups[] = self::getDynamicGroupIdentifierFromAttributeName($attributeName);
            }
        }

        return $currentUsersDynamicGroups;
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
     * @parram string|null $resourceIdentifier null matches any resource identifier
     *
     * @return ResourceActionGrant[]
     *
     * @throws ApiError
     */
    public function getResourceItemActionGrantsForUser(?string $userIdentifier, string $resourceClass, ?string $resourceIdentifier = null,
        ?array $actions = null, int $currentPageNumber = 1, int $maxNumItemsPerPage = 1024): array
    {
        if ($userIdentifier === null) {
            return [];
        }

        if ($resourceIdentifier !== null) {
            return $this->getGrantsForResourceItemForUser($userIdentifier, $resourceClass, $resourceIdentifier, $actions,
                $currentPageNumber, $maxNumItemsPerPage);
        } else {
            return $this->getGrantsForAllResourceItemsForUser($userIdentifier, $resourceClass, $actions,
                $currentPageNumber, $maxNumItemsPerPage);
        }
    }

    /**
     * @parram string|null $resourceIdentifier null matches any resource identifier
     *
     * @return ResourceActionGrant[]
     *
     * @throws ApiError
     */
    public function getResourceItemActionGrantsForCurrentUser(string $resourceClass, ?string $resourceIdentifier = null,
        ?array $actions = null, int $currentPageNumber = 1, int $maxNumItemsPerPage = 1024): array
    {
        return $this->getResourceItemActionGrantsForUser($this->getCurrentUserIdentifier(false),
            $resourceClass, $resourceIdentifier, $actions, $currentPageNumber, $maxNumItemsPerPage);
    }

    public function getResourceCollectionActionGrantsForCurrentUser(string $resourceClass, ?array $actions,
        int $currentPageNumber = 1, int $maxNumItemsPerPage = 1024): array
    {
        $this->assertResouceClassNotReserved($resourceClass);
        $currentUserIdentifier = $this->getCurrentUserIdentifier(false);

        $currentUsersGrants = [];
        if ($currentUserIdentifier !== null) {
            $currentUsersGrants = $this->getResourceActionGrantPageForUser(
                function (int $pageNumber, int $pageSize) use ($currentUserIdentifier, $resourceClass, $actions) {
                    return $this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
                        $resourceClass, InternalResourceActionGrantService::IS_NULL, $actions, $currentUserIdentifier,
                        InternalResourceActionGrantService::IS_NOT_NULL, InternalResourceActionGrantService::IS_NOT_NULL,
                        $pageNumber, $pageSize);
                }, $currentUserIdentifier, $currentPageNumber, $maxNumItemsPerPage);

            // if:
            // * the current page is not yet full
            // * and the manage action or all actions are requested
            // * and no manage action grant was found in the database
            // * and there is a manage resource collection policy defined, which evaluates to true
            // then: add a manage resource collection grant to the list of grants
            if (count($currentUsersGrants) < $maxNumItemsPerPage
                && ($actions === null /* any action */ || in_array(self::MANAGE_ACTION, $actions, true))) {
                $foundManageCollectionGrant = false;
                foreach ($currentUsersGrants as $currentUsersGrant) {
                    if ($currentUsersGrant->getAuthorizationResource()->getResourceIdentifier() === null
                        && $currentUsersGrant->getAction() === self::MANAGE_ACTION) {
                        $foundManageCollectionGrant = true;
                    }
                }
                if (!$foundManageCollectionGrant) {
                    try {
                        if ($this->isGranted(self::getManageResourceCollectionPolicyName($resourceClass))) {
                            $authorizationResource = new AuthorizationResource();
                            $authorizationResource->setResourceClass($resourceClass);
                            $resourceActionGrant = new ResourceActionGrant();
                            $resourceActionGrant->setAuthorizationResource($authorizationResource);
                            $resourceActionGrant->setAction(self::MANAGE_ACTION);
                            $resourceActionGrant->setUserIdentifier($currentUserIdentifier);
                            $currentUsersGrants[] = $resourceActionGrant;
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

        return $currentUsersGrants;
    }

    /**
     * @throws ApiError
     */
    public function addGroup(string $groupIdentifier): ResourceActionGrant
    {
        return $this->resourceActionGrantService->addResourceAndManageResourceGrantForUser(
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
        return $this->doesCurrentUserHaveAGrantForResourceCollectionToManageOr(self::CREATE_GROUPS_ACTION,
            self::GROUP_RESOURCE_CLASS)
            || $this->isGranted(self::getManageResourceCollectionPolicyName(self::GROUP_RESOURCE_CLASS));
    }

    public function isCurrentUserAuthorizedToRemoveGroup(Group $group): bool
    {
        return $this->doesCurrentUserHaveAGrantForResourceItemToManageOr(self::DELETE_GROUP_ACTION,
            self::GROUP_RESOURCE_CLASS, $group->getIdentifier());
    }

    public function isCurrentUserAuthorizedToReadGroup(Group $group): bool
    {
        return $this->doesCurrentUserHaveAGrantForResourceItemToManageOr(self::READ_GROUP_ACTION,
            self::GROUP_RESOURCE_CLASS, $group->getIdentifier());
    }

    public function isCurrentUserAuthorizedToAddGroupMember(GroupMember $groupMember): bool
    {
        return $this->doesCurrentUserHaveAGrantForResourceItemToManageOr(self::ADD_GROUP_MEMBERS_GROUP_ACTION,
            self::GROUP_RESOURCE_CLASS, $groupMember->getGroup()->getIdentifier());
    }

    public function isCurrentUserAuthorizedToRemoveGroupMember(GroupMember $groupMember): bool
    {
        return $this->doesCurrentUserHaveAGrantForResourceItemToManageOr(self::DELETE_GROUP_MEMBERS_GROUP_ACTION,
            self::GROUP_RESOURCE_CLASS, $groupMember->getGroup()->getIdentifier());
    }

    public function isCurrentUserAuthorizedToReadGroupMember(GroupMember $item)
    {
        return $this->isCurrentUserAuthorizedToReadGroup($item->getGroup());
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
        return count($this->getGrantsForAuthorizationResourceForUser(
            $this->getUserIdentifier(), $item->getIdentifier(), null, 1, 1)) > 0;
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

    /**
     * @return ResourceActionGrant[]
     */
    private function getGrantsForResourceItemForUser(string $userIdentifier, string $resourceClass, string $resourceIdentifier,
        ?array $actions = null, int $currentPageNumber = 1, int $maxNumItemsPerPage = 1024): array
    {
        return $this->getResourceActionGrantPageForUser(
            function (int $pageNumber, int $pageSize) use ($userIdentifier, $resourceClass, $resourceIdentifier, $actions) {
                return $this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
                    $resourceClass, $resourceIdentifier, $actions, $userIdentifier,
                    InternalResourceActionGrantService::IS_NOT_NULL, InternalResourceActionGrantService::IS_NOT_NULL,
                    $pageNumber, $pageSize);
            }, $userIdentifier, $currentPageNumber, $maxNumItemsPerPage);
    }

    /**
     * @return ResourceActionGrant[]
     */
    private function getGrantsForAllResourceItemsForUser(string $userIdentifier, string $resourceClass,
        ?array $actions = null, int $currentPageNumber = 1, int $maxNumItemsPerPage = 1024): array
    {
        // since grants for all resource items are requested, we get the groups the user is member of beforehand
        // let the db do the pagination (probably more efficient)
        return $this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            $resourceClass, InternalResourceActionGrantService::IS_NOT_NULL, $actions, $userIdentifier,
            $this->groupService->getGroupsUserIsMemberOf($userIdentifier), $this->getDynamicGroupsCurrentUserIsMemberOf(),
            $currentPageNumber, $maxNumItemsPerPage);
    }

    /**
     * @return ResourceActionGrant[]
     */
    private function getGrantsForAuthorizationResourceForUser(string $userIdentifier, string $authorizationResourceIdentifier, ?array $actions,
        int $currentPageNumber = 1, int $maxNumItemsPerPage = 1024): array
    {
        return $this->getResourceActionGrantPageForUser(function (int $pageNumber, int $pageSize) use ($userIdentifier, $authorizationResourceIdentifier, $actions) {
            return $this->resourceActionGrantService->getResourceActionGrantsForAuthorizationResourceIdentifier(
                $authorizationResourceIdentifier, $actions, $userIdentifier,
                InternalResourceActionGrantService::IS_NOT_NULL, InternalResourceActionGrantService::IS_NOT_NULL,
                $pageNumber, $pageSize);
        }, $userIdentifier, $currentPageNumber, $maxNumItemsPerPage);
    }

    private function doesCurrentUserHaveAManageGrantForAuthorizationResource(
        string $authorizationResourceIdentifier): bool
    {
        $currentUserIdentifier = $this->getUserIdentifier();

        return
            $currentUserIdentifier !== null
            && count($this->getGrantsForAuthorizationResourceForUser($currentUserIdentifier, $authorizationResourceIdentifier,
                [AuthorizationService::MANAGE_ACTION], 1, 1)) > 0;
    }

    private function doesCurrentUserHaveAGrantForResourceItemToManageOr(
        string $action, string $resourceClass, string $resourceIdentifier): bool
    {
        $currentUserIdentifier = $this->getUserIdentifier();

        return
            $currentUserIdentifier !== null
            && count($this->getGrantsForResourceItemForUser($currentUserIdentifier, $resourceClass, $resourceIdentifier,
                [self::MANAGE_ACTION, $action], 1, 1)) > 0;
    }

    private function doesCurrentUserHaveAGrantForResourceCollectionToManageOr(
        string $action, string $resourceClass): bool
    {
        $currentUserIdentifier = $this->getUserIdentifier();

        return
            $currentUserIdentifier !== null
            && count($this->getGrantsForResourceItemForUser($currentUserIdentifier, $resourceClass,
                InternalResourceActionGrantService::IS_NULL, [self::MANAGE_ACTION, $action],
                1, 1)) > 0;
    }

    /**
     * @throws ApiError
     */
    private function assertResouceClassNotReserved(string $resourceClass): void
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

    private function getResourceActionGrantPageForUser(callable $getGrantsCallback, string $userIdentifier, int $currentPageNumber, int $maxNumItemsPerPage): array
    {
        $resultPage = [];
        if ($maxNumItemsPerPage > 0) {
            $currentUsersGrantsIndex = 0;
            $firstItemIndex = Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage);
            $grantPageNumberToGet = 1;
            $done = false;
            while (($grants = $getGrantsCallback($grantPageNumberToGet, 1024)) !== [] && !$done) {
                foreach ($grants as $grant) {
                    if ($this->isUsersGrant($grant, $userIdentifier)) {
                        if ($currentUsersGrantsIndex >= $firstItemIndex) {
                            $resultPage[] = $grant;
                        }
                        ++$currentUsersGrantsIndex;
                        if (count($resultPage) === $maxNumItemsPerPage) {
                            $done = true;
                            break;
                        }
                    }
                }
                ++$grantPageNumberToGet;
            }
        }

        return $resultPage;
    }

    private function isUsersGrant(ResourceActionGrant $grant, string $userIdentifier): bool
    {
        return $grant->getUserIdentifier() === $userIdentifier
            || ($grant->getGroup() !== null && $this->groupService->isUserMemberOfGroup($userIdentifier, $grant->getGroup()->getIdentifier()))
            || ($grant->getDynamicGroupIdentifier() !== null && $this->isCurrentUserMemberOfDynamicGroup($grant->getDynamicGroupIdentifier()));
    }
}
