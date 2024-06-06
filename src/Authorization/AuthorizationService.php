<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Authorization;

use Dbp\Relay\AuthorizationBundle\API\ResourceAction;
use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Entity\GroupMember;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Event\GetAvailableResourceClassActionsEvent;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\CoreBundle\Authorization\AbstractAuthorizationService;
use Dbp\Relay\CoreBundle\Authorization\AuthorizationException;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class AuthorizationService extends AbstractAuthorizationService implements LoggerAwareInterface, EventSubscriberInterface
{
    use LoggerAwareTrait;

    public const MANAGE_ACTION = 'manage';

    public const CREATE_GROUPS_ACTION = 'create';

    public const READ_GROUP_ACTION = 'read';
    public const DELETE_GROUP_ACTION = 'delete';
    public const ADD_GROUP_MEMBERS_GROUP_ACTION = 'add_members';
    public const DELETE_GROUP_MEMBERS_GROUP_ACTION = 'delete_members';

    public const GROUP_RESOURCE_CLASS = 'DbpRelayAuthorizationGroup';

    public const DYNAMIC_GROUP_UNDEFINED_ERROR_ID = 'authorization:dynamic-group-undefined';

    private InternalResourceActionGrantService $resourceActionGrantService;
    private GroupService $groupService;

    public static function getSubscribedEvents()
    {
        return [
            GetAvailableResourceClassActionsEvent::class => 'onGetAvailableResourceClassActionsEvent',
        ];
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

    public function onGetAvailableResourceClassActionsEvent(GetAvailableResourceClassActionsEvent $event): void
    {
        switch ($event->getResourceClass()) {
            case self::GROUP_RESOURCE_CLASS:
                $event->setItemActions([
                    self::READ_GROUP_ACTION,
                    self::DELETE_GROUP_ACTION,
                    self::ADD_GROUP_MEMBERS_GROUP_ACTION,
                    self::DELETE_GROUP_MEMBERS_GROUP_ACTION,
                ]);
                $event->setCollectionActions([self::CREATE_GROUPS_ACTION]);
                break;
            default:
                break;
        }
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
        $this->assertResourceClassNotReserved($resourceClass);

        $this->resourceActionGrantService->addResourceAndManageResourceGrantForUser(
            $resourceClass, $resourceIdentifier, $this->getCurrentUserIdentifier(true));
    }

    /**
     * @throws ApiError
     */
    public function removeResource(string $resourceClass, string $resourceIdentifier): void
    {
        $this->assertResourceClassNotReserved($resourceClass);

        $this->resourceActionGrantService->removeResource($resourceClass, $resourceIdentifier);
    }

    /**
     * @throws ApiError
     */
    public function removeResources(string $resourceClass, array $resourceIdentifiers): void
    {
        $this->assertResourceClassNotReserved($resourceClass);

        $this->resourceActionGrantService->removeResources($resourceClass, $resourceIdentifiers);
    }

    /**
     * @parram string|null $resourceIdentifier null matches any resource identifier
     *
     * @return ResourceAction[]
     *
     * @throws ApiError
     */
    public function getResourceItemActionsForUser(?string $userIdentifier, string $resourceClass, ?string $resourceIdentifier = null,
        ?array $actions = null, int $firstResultIndex = 0, int $maxNumResults = 1024): array
    {
        if ($resourceIdentifier !== null) {
            return $this->getResourceActionsForResourceItemForUser($userIdentifier, $resourceClass, $resourceIdentifier, $actions,
                $firstResultIndex, $maxNumResults);
        } else {
            return $this->getResourceActionsForResourceItemsForUser($userIdentifier, $resourceClass, $actions,
                $firstResultIndex, $maxNumResults);
        }
    }

    /**
     * @parram string|null $resourceIdentifier null matches any resource identifier
     *
     * @return ResourceAction[]
     *
     * @throws ApiError
     */
    public function getResourceItemActionsForCurrentUser(string $resourceClass, ?string $resourceIdentifier = null,
        ?array $actions = null, int $firstResultIndex = 0, int $maxNumResults = 1024): array
    {
        return $this->getResourceItemActionsForUser($this->getCurrentUserIdentifier(false),
            $resourceClass, $resourceIdentifier, $actions, $firstResultIndex, $maxNumResults);
    }

    /**
     * @return ResourceAction[]
     *
     * @throws ApiError
     */
    public function getResourceCollectionActionsForCurrentUser(string $resourceClass, ?array $actions,
        int $firstResultIndex = 0, int $maxNumResults = 1024): array
    {
        $this->assertResourceClassNotReserved($resourceClass);
        $currentUserIdentifier = $this->getCurrentUserIdentifier(false);

        $currentUsersResourceActions = $this->getResourceActionsForUser(
            function (int $firstResultIndex, int $maxNumResults) use ($currentUserIdentifier, $resourceClass, $actions) {
                return $this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
                    $resourceClass, InternalResourceActionGrantService::IS_NULL, $actions, $currentUserIdentifier,
                    InternalResourceActionGrantService::IS_NOT_NULL, InternalResourceActionGrantService::IS_NOT_NULL,
                    $firstResultIndex, $maxNumResults);
            }, $currentUserIdentifier, $firstResultIndex, $maxNumResults);

        // if:
        // * the current page is not yet full
        // * and the manage action or all actions are requested
        // * and no manage action grant was found in the database
        // * and there is a manage resource collection policy defined, which evaluates to true
        // then: add a manage resource collection grant to the list of grants
        if (count($currentUsersResourceActions) < $maxNumResults
            && ($actions === null /* any action */ || in_array(self::MANAGE_ACTION, $actions, true))) {
            $foundManageCollectionGrant = false;
            foreach ($currentUsersResourceActions as $currentUsersGrant) {
                if ($currentUsersGrant->getResourceIdentifier() === null
                    && $currentUsersGrant->getAction() === self::MANAGE_ACTION) {
                    $foundManageCollectionGrant = true;
                }
            }
            if (!$foundManageCollectionGrant) {
                try {
                    if ($this->isGranted(self::getManageResourceCollectionPolicyName($resourceClass))) {
                        $currentUsersResourceActions[] =
                            new ResourceAction(null, self::MANAGE_ACTION);
                    }
                } catch (AuthorizationException $authorizationException) {
                    // policy undefined is fine - there's just no policy configured for this resource class
                    if ($authorizationException->getCode() !== AuthorizationException::POLICY_UNDEFINED) {
                        throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, $authorizationException->getMessage());
                    }
                }
            }
        }

        return $currentUsersResourceActions;
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

    public function isCurrentUserAuthorizedToReadGroupMember(GroupMember $item): bool
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
        return $this->isUsersGrant($resourceActionGrant, $this->getUserIdentifier())
            || $this->doesCurrentUserHaveAManageGrantForAuthorizationResource(
                $resourceActionGrant->getAuthorizationResource()->getIdentifier());
    }

    public function isCurrentUserAuthorizedToReadResource(AuthorizationResource $item): bool
    {
        return !empty($this->getResourceActionsForAuthorizationResourceForUser(
            $this->getUserIdentifier(), $item->getIdentifier(), null, 0, 1));
    }

    /**
     * @return AuthorizationResource[]
     */
    public function getAuthorizationResourcesCurrentUserIsAuthorizedToRead(?string $resourceClass, int $firstResultIndex, int $maxNumResults): array
    {
        $userIdentifier = $this->getUserIdentifier();

        // since grants for all resource items are requested, we get the groups the user is member of beforehand
        // let the db do the pagination (probably more efficient)
        return $this->resourceActionGrantService->getAuthorizationResourcesUserIsAuthorizedToRead(
            $resourceClass, null, $userIdentifier,
            $userIdentifier !== null ? self::nullIfEmpty($this->groupService->getGroupsUserIsMemberOf($userIdentifier)) : null,
            self::nullIfEmpty($this->getDynamicGroupsCurrentUserIsMemberOf()),
            $firstResultIndex, $maxNumResults);
    }

    /**
     * @return ResourceActionGrant[]
     */
    public function getResourceActionGrantsUserIsAuthorizedToRead(?string $resourceClass, ?string $resourceIdentifier,
        int $firstResultIndex, int $maxNumResults): array
    {
        $userIdentifier = $this->getUserIdentifier();

        // since grants for all resource items are requested, we get the groups the user is member of beforehand
        // let the db do the pagination (probably more efficient)
        return $this->resourceActionGrantService->getResourceActionGrantsUserIsAuthorizedToRead(
            $resourceClass, $resourceIdentifier, $userIdentifier,
            $userIdentifier !== null ? self::nullIfEmpty($this->groupService->getGroupsUserIsMemberOf($userIdentifier)) : null,
            self::nullIfEmpty($this->getDynamicGroupsCurrentUserIsMemberOf()),
            $firstResultIndex, $maxNumResults);
    }

    /**
     * @return Group[]
     */
    public function getGroupsCurrentUserIsAuthorizedToRead(int $firstResultIndex, int $maxNumResults): array
    {
        $groupIdentifiers = array_map(function ($resourceAction) {
            return $resourceAction->getResourceIdentifier();
        }, $this->getResourceActionsForResourceItemsForUser($this->getUserIdentifier(), self::GROUP_RESOURCE_CLASS,
            [self::MANAGE_ACTION, self::READ_GROUP_ACTION], $firstResultIndex, $maxNumResults));

        return $this->groupService->getGroupsByIdentifiers($groupIdentifiers, 0, $maxNumResults);
    }

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

    private static function nullIfEmpty(array $array): ?array
    {
        return empty($array) ? null : $array;
    }

    private static function toResourceAction(ResourceActionGrant $resourceActionGrant): ResourceAction
    {
        return new ResourceAction(
            $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier(),
            $resourceActionGrant->getAction());
    }

    private static function createUniqueKey(ResourceAction $resourceAction): string
    {
        return $resourceAction->getResourceIdentifier().$resourceAction->getAction();
    }

    /**
     * @return ResourceAction[]
     */
    private function getResourceActionsForResourceItemForUser(?string $userIdentifier, string $resourceClass, string $resourceIdentifier,
        ?array $actions = null, int $firstResultIndex = 0, int $maxNumResults = 1024): array
    {
        return $this->getResourceActionsForUser(
            function (int $firstResultIndex, int $maxNumResults) use ($userIdentifier, $resourceClass, $resourceIdentifier, $actions) {
                return $this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
                    $resourceClass, $resourceIdentifier, $actions, $userIdentifier,
                    InternalResourceActionGrantService::IS_NOT_NULL, InternalResourceActionGrantService::IS_NOT_NULL,
                    $firstResultIndex, $maxNumResults);
            }, $userIdentifier, $firstResultIndex, $maxNumResults);
    }

    /**
     * @return ResourceAction[]
     */
    private function getResourceActionsForResourceItemsForUser(?string $userIdentifier, string $resourceClass,
        ?array $actions = null, int $firstResultIndex = 0, int $maxNumResults = 1024): array
    {
        // since grants for all resource items are requested, we get the groups the user is member of beforehand
        // let the db do the pagination (probably more efficient)
        return array_map(function ($resourceActionGrant) {
            return self::toResourceAction($resourceActionGrant);
        }, $this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            $resourceClass, InternalResourceActionGrantService::IS_NOT_NULL, $actions, $userIdentifier,
            $userIdentifier !== null ? self::nullIfEmpty($this->groupService->getGroupsUserIsMemberOf($userIdentifier)) : null,
            self::nullIfEmpty($this->getDynamicGroupsCurrentUserIsMemberOf()),
            $firstResultIndex, $maxNumResults, true));
    }

    /**
     * @return ResourceAction[]
     */
    private function getResourceActionsForAuthorizationResourceForUser(?string $userIdentifier, string $authorizationResourceIdentifier, ?array $actions,
        int $firstResultIndex = 0, int $maxNumResults = 1024): array
    {
        return $this->getResourceActionsForUser(function (int $firstResultIndex, int $maxNumResults) use ($userIdentifier, $authorizationResourceIdentifier, $actions) {
            return $this->resourceActionGrantService->getResourceActionGrantsForAuthorizationResourceIdentifier(
                $authorizationResourceIdentifier, $actions, $userIdentifier,
                InternalResourceActionGrantService::IS_NOT_NULL, InternalResourceActionGrantService::IS_NOT_NULL,
                $firstResultIndex, $maxNumResults);
        }, $userIdentifier, $firstResultIndex, $maxNumResults);
    }

    private function doesCurrentUserHaveAManageGrantForAuthorizationResource(
        string $authorizationResourceIdentifier): bool
    {
        return !empty($this->getResourceActionsForAuthorizationResourceForUser($this->getUserIdentifier(),
            $authorizationResourceIdentifier, [AuthorizationService::MANAGE_ACTION], 0, 1));
    }

    private function doesCurrentUserHaveAGrantForResourceItemToManageOr(
        string $action, string $resourceClass, string $resourceIdentifier): bool
    {
        return !empty($this->getResourceActionsForResourceItemForUser($this->getUserIdentifier(), $resourceClass, $resourceIdentifier,
            [self::MANAGE_ACTION, $action], 0, 1));
    }

    private function doesCurrentUserHaveAGrantForResourceCollectionToManageOr(
        string $action, string $resourceClass): bool
    {
        return !empty($this->getResourceActionsForResourceItemForUser($this->getUserIdentifier(), $resourceClass,
            InternalResourceActionGrantService::IS_NULL, [self::MANAGE_ACTION, $action],
            0, 1));
    }

    /**
     * @throws ApiError
     */
    private function assertResourceClassNotReserved(string $resourceClass): void
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

    /**
     * @return ResourceAction[]
     */
    private function getResourceActionsForUser(callable $getResourceActionGrantsCallback, ?string $userIdentifier, int $firstResultIndex, int $maxNumResults): array
    {
        $resourceActionResultPage = [];
        if ($maxNumResults > 0) {
            $allResourceActions = [];
            $firstGrantIndexToGet = 0;
            $maxNumGrantsToGet = 1024;
            $done = false;
            while (!$done && ($resourceActionGrants = $getResourceActionGrantsCallback($firstGrantIndexToGet, $maxNumGrantsToGet)) !== []) {
                foreach ($resourceActionGrants as $resourceActionGrant) {
                    if ($this->isUsersGrant($resourceActionGrant, $userIdentifier)) {
                        $resourceAction = self::toResourceAction($resourceActionGrant);
                        $resourceActionKey = self::createUniqueKey($resourceAction);
                        if (!isset($allResourceActions[$resourceActionKey])) {
                            if (count($allResourceActions) >= $firstResultIndex) {
                                $resourceActionResultPage[] = $resourceAction;
                            }
                            if (count($resourceActionResultPage) === $maxNumResults) {
                                $done = true;
                                break;
                            }
                            $allResourceActions[$resourceActionKey] = $resourceAction;
                        }
                    }
                }
                $firstGrantIndexToGet += $maxNumGrantsToGet;
            }
        }

        return $resourceActionResultPage;
    }

    private function isUsersGrant(ResourceActionGrant $grant, ?string $userIdentifier): bool
    {
        return ($userIdentifier !== null
            && ($grant->getUserIdentifier() === $userIdentifier
            || ($grant->getGroup() !== null && $this->groupService->isUserMemberOfGroup($userIdentifier, $grant->getGroup()->getIdentifier()))))
            || ($grant->getDynamicGroupIdentifier() !== null && $this->isCurrentUserMemberOfDynamicGroup($grant->getDynamicGroupIdentifier()));
    }
}
