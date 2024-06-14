<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Authorization;

use Dbp\Relay\AuthorizationBundle\API\ResourceActions;
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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use DoctrineExtensions\Query\Mysql\Replace;
use DoctrineExtensions\Query\Mysql\Unhex;
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

    public const MAX_NUM_RESULTS_DEFAULT = 1024;
    public const SEARCH_FILTER = 'search';

    private InternalResourceActionGrantService $resourceActionGrantService;
    private GroupService $groupService;
    private EntityManagerInterface $entityManager;

    public static function getSubscribedEvents()
    {
        return [
            GetAvailableResourceClassActionsEvent::class => 'onGetAvailableResourceClassActionsEvent',
        ];
    }

    public function __construct(InternalResourceActionGrantService $resourceActionGrantService, GroupService $groupService,
        EntityManagerInterface $entityManager)
    {
        parent::__construct();

        $this->resourceActionGrantService = $resourceActionGrantService;
        $this->groupService = $groupService;
        $this->entityManager = $entityManager;
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
     * @return string[]
     */
    public function getDynamicGroupsCurrentUserIsAuthorizedToRead(): array
    {
        return $this->getAttributeNames();
    }

    /**
     * @throws ApiError
     */
    public function registerResource(string $resourceClass, string $resourceIdentifier): void
    {
        $this->assertResourceClassNotReserved($resourceClass);

        $this->resourceActionGrantService->addResourceAndManageResourceGrantForUser(
            $resourceClass, $resourceIdentifier, $this->getCurrentUserIdentifier(true));
    }

    /**
     * @throws ApiError
     */
    public function deregisterResource(string $resourceClass, string $resourceIdentifier): void
    {
        $this->assertResourceClassNotReserved($resourceClass);

        $this->resourceActionGrantService->removeAuthorizationResource($resourceClass, $resourceIdentifier);
    }

    /**
     * @throws ApiError
     */
    public function removeResources(string $resourceClass, array $resourceIdentifiers): void
    {
        $this->assertResourceClassNotReserved($resourceClass);

        $this->resourceActionGrantService->removeAuthorizationResources($resourceClass, $resourceIdentifiers);
    }

    /**
     * @parram string|null $resourceIdentifier null matches any resource identifier
     *
     * @throws ApiError
     */
    public function getResourceItemActionsForUser(?string $userIdentifier, string $resourceClass, string $resourceIdentifier,
        ?array $actions = null): ?ResourceActions
    {
        return $this->getResourceItemActionsForUserInternal($userIdentifier, $resourceClass, $resourceIdentifier, $actions);
    }

    /**
     * @parram string|null $resourceIdentifier null matches any resource identifier
     *
     * @throws ApiError
     */
    public function getResourceItemActionsForCurrentUser(string $resourceClass, string $resourceIdentifier,
        ?array $actions = null): ?ResourceActions
    {
        return $this->getResourceItemActionsForUserInternal($this->getCurrentUserIdentifier(false),
            $resourceClass, $resourceIdentifier, $actions);
    }

    /**
     * @parram string|null $resourceIdentifier null matches any resource identifier
     *
     * @return ResourceActions[]
     *
     * @throws ApiError
     */
    public function getResourceItemActionsPageForCurrentUser(string $resourceClass,
        ?array $actions = null, int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        return $this->getResourceItemActionsPageForForUserInternal($this->getCurrentUserIdentifier(false),
            $resourceClass, $actions, $firstResultIndex, $maxNumResults);
    }

    /**
     * @throws ApiError
     */
    public function getResourceCollectionActionsForCurrentUser(string $resourceClass, ?array $actions): ?ResourceActions
    {
        $this->assertResourceClassNotReserved($resourceClass);
        $currentUserIdentifier = $this->getCurrentUserIdentifier(false);

        $currentUsersResourceActions = $this->getResourceActionsForUser(
            function (int $firstResultIndex, int $maxNumResults) use ($currentUserIdentifier, $resourceClass, $actions) {
                return $this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
                    $resourceClass, InternalResourceActionGrantService::IS_NULL, $actions, $currentUserIdentifier,
                    InternalResourceActionGrantService::IS_NOT_NULL, InternalResourceActionGrantService::IS_NOT_NULL,
                    $firstResultIndex, $maxNumResults, [InternalResourceActionGrantService::ORDER_BY_AUTHORIZATION_RESOURCE_OPTION => true]);
            }, $currentUserIdentifier);

        assert(count($currentUsersResourceActions) <= 1);

        $resourceCollectionActions = $currentUsersResourceActions[0] ?? null;

        // if:
        // * the manage action or all actions (includes manage action) are requested
        // * and no manage action grant was found in the database
        // * and there is a manage resource collection policy defined, which evaluates to true
        // then: add a manage resource collection grant to the list of grants
        if (($actions === null /* any action */ || in_array(self::MANAGE_ACTION, $actions, true))
        && ($resourceCollectionActions === null || !in_array(self::MANAGE_ACTION, $resourceCollectionActions->getActions(), true))) {
            try {
                $policyName = self::getManageResourceCollectionPolicyName($resourceClass);
                if ($this->isPolicyDefined($policyName) && $this->isGranted($policyName)) {
                    $resourceCollectionActions = $resourceCollectionActions ?:
                        new ResourceActions(null);
                    $resourceCollectionActions->addAction(self::MANAGE_ACTION);
                }
            } catch (AuthorizationException $authorizationException) {
                throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, $authorizationException->getMessage());
            }
        }

        return $resourceCollectionActions;
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
        $this->resourceActionGrantService->removeAuthorizationResource(self::GROUP_RESOURCE_CLASS, $groupIdentifier);
    }

    /**
     * @return Group[]
     */
    public function getGroupsCurrentUserIsAuthorizedToRead(int $firstResultIndex, int $maxNumResults, array $filters = []): array
    {
        $GROUP_ALIAS = 'g';
        $AUTHORIZATION_RESOURCE_ALIAS = 'ar';
        /**
         * NOTE: sqlite3, which is used as in-memory test database, does support
         * the 'unhex' function only from version 3.41.1.
         */
        $this->entityManager->getConfiguration()->addCustomStringFunction('UNHEX', Unhex::class);
        $this->entityManager->getConfiguration()->addCustomStringFunction('REPLACE', Replace::class);

        $userIdentifier = $this->getUserIdentifier();
        $queryBuilder = $this->resourceActionGrantService->createAuthorizationResourceQueryBuilder($GROUP_ALIAS,
            self::GROUP_RESOURCE_CLASS, InternalResourceActionGrantService::IS_NOT_NULL,
            [self::MANAGE_ACTION, self::READ_GROUP_ACTION],
            $userIdentifier,
            $userIdentifier !== null ? self::nullIfEmpty($this->groupService->getGroupsUserIsMemberOf($userIdentifier)) : null,
            self::nullIfEmpty($this->getDynamicGroupsCurrentUserIsMemberOf()));

        $queryBuilder
            ->innerJoin(Group::class, $GROUP_ALIAS, Join::WITH,
                "UNHEX(REPLACE($AUTHORIZATION_RESOURCE_ALIAS.resourceIdentifier, '-', '')) = $GROUP_ALIAS.identifier");
        if ($groupNameFilter = $filters[self::SEARCH_FILTER] ?? null) {
            $queryBuilder
                ->andWhere($this->entityManager->getExpressionBuilder()->like("$GROUP_ALIAS.name", ':groupNameLike'))
                ->setParameter(':groupNameLike', "%$groupNameFilter%");
        }

        return $queryBuilder
            ->getQuery()
            ->setFirstResult($firstResultIndex)
            ->setMaxResults($maxNumResults)
            ->getResult();
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
     * @return string[]
     */
    public function getResourceClassesCurrentUserIsAuthorizedToRead(mixed $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        // TODO: add resource classes for which the 'manage collection policies' from config evaluate to true for the current user
        $userIdentifier = $this->getUserIdentifier();

        // we get the groups the user is member of beforehand and let the db do the pagination (probably more efficient)
        return $this->resourceActionGrantService->getResourceClassesUserIsAuthorizedToRead($userIdentifier,
            $userIdentifier !== null ? self::nullIfEmpty($this->groupService->getGroupsUserIsMemberOf($userIdentifier)) : null,
            self::nullIfEmpty($this->getDynamicGroupsCurrentUserIsMemberOf()),
            $firstResultIndex, $maxNumResults);
    }

    /**
     * @return AuthorizationResource[]
     */
    public function getAuthorizationResourcesCurrentUserIsAuthorizedToRead(?string $resourceClass, int $firstResultIndex, int $maxNumResults): array
    {
        // TODO: add authorization resources for which the 'manage collection policies' from config evaluate to true for the current user
        $userIdentifier = $this->getUserIdentifier();

        // since grants for all resource items are requested, we get the groups the user is member of beforehand
        // let the db do the pagination (probably more efficient)
        return $this->resourceActionGrantService->getAuthorizationResourcesUserIsAuthorizedToRead(
            $resourceClass, $userIdentifier,
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

    private function getResourceItemActionsForUserInternal(?string $userIdentifier, string $resourceClass, string $resourceIdentifier,
        ?array $actions = null): ?ResourceActions
    {
        return $this->getResourceActionsForUser(
            function (int $firstResultIndex, int $maxNumResults) use ($userIdentifier, $resourceClass, $resourceIdentifier, $actions) {
                return $this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
                    $resourceClass, $resourceIdentifier, $actions, $userIdentifier,
                    InternalResourceActionGrantService::IS_NOT_NULL, InternalResourceActionGrantService::IS_NOT_NULL,
                    $firstResultIndex, $maxNumResults, [InternalResourceActionGrantService::ORDER_BY_AUTHORIZATION_RESOURCE_OPTION => true]);
            }, $userIdentifier)[0] ?? null;
    }

    /**
     * @return ResourceActions[]
     */
    private function getResourceItemActionsPageForForUserInternal(?string $userIdentifier, string $resourceClass,
        ?array $actions = null, int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        $resourceActions = [];
        $currentResourceActions = null;
        // since grants for all resource items are requested, we get the groups the user is member of beforehand
        // let the db do the pagination (probably more efficient)
        foreach ($this->resourceActionGrantService->getResourceActionGrantsForAuthorizationResourcePage(
            $resourceClass, InternalResourceActionGrantService::ITEM_ACTIONS_TYPE, $actions, $userIdentifier,
            $userIdentifier !== null ? self::nullIfEmpty($this->groupService->getGroupsUserIsMemberOf($userIdentifier)) : null,
            self::nullIfEmpty($this->getDynamicGroupsCurrentUserIsMemberOf()),
            $firstResultIndex, $maxNumResults) as $resourceActionGrant) {
            if ($currentResourceActions === null
                || $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() !== $currentResourceActions->getResourceIdentifier()) {
                $currentResourceActions = new ResourceActions($resourceActionGrant->getAuthorizationResource()->getResourceIdentifier());
                $resourceActions[] = $currentResourceActions;
            }
            $currentResourceActions->addAction($resourceActionGrant->getAction());
        }

        return $resourceActions;
    }

    /**
     * @return ResourceActions[]
     */
    private function getResourceActionsForAuthorizationResourceForUser(?string $userIdentifier, string $authorizationResourceIdentifier, ?array $actions,
        int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        return $this->getResourceActionsForUser(function (int $firstResultIndex, int $maxNumResults) use ($userIdentifier, $authorizationResourceIdentifier, $actions) {
            return $this->resourceActionGrantService->getResourceActionGrantsForAuthorizationResourceIdentifier(
                $authorizationResourceIdentifier, $actions, $userIdentifier,
                InternalResourceActionGrantService::IS_NOT_NULL, InternalResourceActionGrantService::IS_NOT_NULL,
                $firstResultIndex, $maxNumResults, [InternalResourceActionGrantService::ORDER_BY_AUTHORIZATION_RESOURCE_OPTION => true]);
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
        return $this->getResourceItemActionsForUserInternal($this->getUserIdentifier(), $resourceClass, $resourceIdentifier,
            [self::MANAGE_ACTION, $action]) !== null;
    }

    private function doesCurrentUserHaveAGrantForResourceCollectionToManageOr(
        string $action, string $resourceClass): bool
    {
        return $this->getResourceItemActionsForUserInternal($this->getUserIdentifier(), $resourceClass,
            InternalResourceActionGrantService::IS_NULL, [self::MANAGE_ACTION, $action]) !== null;
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
     * @return ResourceActions[]
     */
    private function getResourceActionsForUser(callable $getResourceActionGrantsCallback, ?string $userIdentifier, int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        $resourceActionResultPage = [];
        if ($maxNumResults > 0) {
            $firstGrantIndexToGet = 0;
            $maxNumGrantsToGet = 1024;
            $done = false;
            $currentResourceActionsIndex = 0;
            $currentResourceActions = null;
            $currentAuthorizationResourceIdentifier = null;
            while (!$done && ($resourceActionGrants = $getResourceActionGrantsCallback($firstGrantIndexToGet, $maxNumGrantsToGet)) !== []) {
                foreach ($resourceActionGrants as $resourceActionGrant) {
                    if ($this->isUsersGrant($resourceActionGrant, $userIdentifier)) {
                        if ($currentAuthorizationResourceIdentifier === null
                            || $currentAuthorizationResourceIdentifier !== $resourceActionGrant->getAuthorizationResource()->getIdentifier()) {
                            $currentAuthorizationResourceIdentifier = $resourceActionGrant->getAuthorizationResource()->getIdentifier();

                            if (count($resourceActionResultPage) === $maxNumResults) {
                                $done = true;
                                break;
                            }
                            if ($currentResourceActionsIndex >= $firstResultIndex) {
                                $currentResourceActions = new ResourceActions($resourceActionGrant->getAuthorizationResource()->getResourceIdentifier());
                                $resourceActionResultPage[] = $currentResourceActions;
                            }
                            ++$currentResourceActionsIndex;
                        }
                        if ($currentResourceActions !== null) {
                            $currentResourceActions->addAction($resourceActionGrant->getAction());
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
