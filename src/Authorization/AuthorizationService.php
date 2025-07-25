<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Authorization;

use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\GrantedActions;
use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Entity\GroupMember;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Event\GetAvailableResourceClassActionsEvent;
use Dbp\Relay\AuthorizationBundle\Helper\AuthorizationUuidBinaryType;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Service\UserAttributeProvider;
use Dbp\Relay\CoreBundle\Authorization\AbstractAuthorizationService;
use Dbp\Relay\CoreBundle\Authorization\AuthorizationException;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Psr\Cache\CacheItemPoolInterface;
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
    public const UPDATE_GROUP_ACTION = 'update';
    public const DELETE_GROUP_ACTION = 'delete';
    public const ADD_GROUP_MEMBERS_GROUP_ACTION = 'add_members';
    public const DELETE_GROUP_MEMBERS_GROUP_ACTION = 'delete_members';

    public const GROUP_RESOURCE_CLASS = 'DbpRelayAuthorizationGroup';

    public const DYNAMIC_GROUP_UNDEFINED_ERROR_ID = 'authorization:dynamic-group-undefined';

    public const MAX_NUM_RESULTS_DEFAULT = 1024;
    public const SEARCH_FILTER = 'search';
    public const GET_CHILD_GROUP_CANDIDATES_FOR_GROUP_IDENTIFIER_FILTER = 'getChildGroupCandidatesForGroupIdentifier';
    public const DYNAMIC_GROUP_IDENTIFIER_EVERYBODY = 'everybody';
    public const MANAGE_RESOURCE_COLLECTION_POLICY_PREFIX = '@';

    public const IS_NULL = InternalResourceActionGrantService::IS_NULL;

    private const WERE_MANAGE_COLLECTION_GRANTS_WRITTEN_TO_DB_CACHE_KEY = 'registeredManageResourceCollectionGrants';

    private const GET_RESOURCE_ACTION_GRANTS = 'rag';
    private const GET_AUTHORIZATION_RESOURCES = 'ar';
    private const GET_RESOURCE_CLASSES = 'rc';

    private ?CacheItemPoolInterface $cachePool = null;
    private ?array $config = null;

    /**
     * @var string[][]
     */
    private array $grantedAuthorizationResourceActionsCache = [];

    public static function getSubscribedEvents(): array
    {
        return [
            GetAvailableResourceClassActionsEvent::class => 'onGetAvailableResourceClassActionsEvent',
        ];
    }

    public function __construct(
        private readonly InternalResourceActionGrantService $resourceActionGrantService,
        private readonly GroupService $groupService,
        private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    /**
     * @internal For testing only
     */
    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    public function setConfig(array $config): void
    {
        parent::setConfig($config);

        $this->config = $config;
        $this->tryConfigure();
    }

    public function setCache(?CacheItemPoolInterface $cachePool): void
    {
        $this->cachePool = $cachePool;
        $this->tryConfigure();
    }

    /**
     * @internal For testing only
     */
    public function getCache(): ?CacheItemPoolInterface
    {
        return $this->cachePool;
    }

    /**
     * @internal For testing only
     */
    public function clearRequestCache(): void
    {
        $this->grantedAuthorizationResourceActionsCache = [];
    }

    public function onGetAvailableResourceClassActionsEvent(GetAvailableResourceClassActionsEvent $event): void
    {
        switch ($event->getResourceClass()) {
            case self::GROUP_RESOURCE_CLASS:
                $event->setItemActions([
                    self::READ_GROUP_ACTION,
                    self::UPDATE_GROUP_ACTION,
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
            return $this->isGrantedRole(self::toIsCurrentUserMemberOfDynamicGroupPolicyName($dynamicGroupIdentifier));
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
        foreach ($this->getRoleNames() as $policyName) {
            if (self::isCurrentUserMemberOfDynamicGroupPolicyName($policyName)
                && $this->isGrantedRole($policyName)) {
                $currentUsersDynamicGroups[] = self::toDynamicGroupIdentifier($policyName);
            }
        }

        return $currentUsersDynamicGroups;
    }

    /**
     * @return string[]
     */
    public function getDynamicGroupsCurrentUserIsAuthorizedToRead(): array
    {
        return array_filter($this->getRoleNames(), function ($policyName) {
            return self::isCurrentUserMemberOfDynamicGroupPolicyName($policyName);
        });
    }

    /**
     * @throws ApiError
     */
    public function registerResource(string $resourceClass, string $resourceIdentifier, ?string $userIdentifier = null): void
    {
        $this->assertResourceClassNotReserved($resourceClass);
        $this->assertDoesNotContainReservedCharacters($resourceClass);
        $this->assertDoesNotContainReservedCharacters($resourceIdentifier);

        $userIdentifier ??= $this->getUserIdentifier();
        if ($userIdentifier === null) {
            throw ApiError::withDetails(Response::HTTP_FORBIDDEN,
                'a user identifier is required to register a resource. are you a system account client?');
        }

        $this->resourceActionGrantService->addResourceAndManageResourceGrantFor(
            $resourceClass, $resourceIdentifier, $userIdentifier);
    }

    /**
     * @throws ApiError
     */
    public function deregisterResource(string $resourceClass, string $resourceIdentifier): void
    {
        $this->assertResourceClassNotReserved($resourceClass);

        $this->resourceActionGrantService->removeAuthorizationResourceByResourceClassAndIdentifier($resourceClass, $resourceIdentifier);
    }

    /**
     * @throws ApiError
     */
    public function deregisterResources(string $resourceClass, array $resourceIdentifiers): void
    {
        $this->assertResourceClassNotReserved($resourceClass);

        $this->resourceActionGrantService->removeAuthorizationResourcesByResourceClassAndIdentifier($resourceClass, $resourceIdentifiers);
    }

    /**
     * @parram string[]|null $whereActionsContainAnyOf
     *
     * @return string[]
     *
     * @throws ApiError
     */
    public function getResourceItemActionsForCurrentUser(string $resourceClass, string $resourceIdentifier): array
    {
        return $this->getResourceItemActionsForCurrentUserInternal($resourceClass, $resourceIdentifier);
    }

    /**
     * @param string|null $resourceIdentifier If null, the action is considered a resource collection action
     *
     * @throws ApiError
     */
    public function isCurrentUserGranted(string $resourceClass, ?string $resourceIdentifier, string $action): bool
    {
        $grantedActions = $resourceIdentifier !== null ?
            $this->getResourceItemActionsForCurrentUserInternal($resourceClass, $resourceIdentifier) :
            $this->getResourceCollectionActionsForCurrentUserInternal($resourceClass);

        if (in_array($action, $grantedActions, true)) {
            // the current user has the respective action grant -> done
            return true;
        } elseif (in_array(self::MANAGE_ACTION, $grantedActions, true)) {
            // the current user has a manage grant -> check if the requested action is available at all
            $availableActions =
                $this->resourceActionGrantService->getAvailableResourceClassActions($resourceClass)[
                $resourceIdentifier !== null ? 0 : 1];

            return in_array($action, $availableActions, true);
        }

        return false;
    }

    /**
     * @return string[][]
     *
     * @throws ApiError
     */
    public function getResourceItemActionsPageForCurrentUser(string $resourceClass,
        ?string $whereIsGrantedAction = null, int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        $resourceActions = [];
        $currentResourceIdentifier = null;
        $currentUserIdentifier = $this->getUserIdentifier();
        $whereActionsContainAnyOf = null;
        if ($whereIsGrantedAction === self::MANAGE_ACTION) {
            $whereActionsContainAnyOf = [self::MANAGE_ACTION];
        } elseif ($whereIsGrantedAction !== null) {
            // if the requested action is not available, it can't be granted either
            $availableActions = $this->resourceActionGrantService->getAvailableResourceClassActions($resourceClass)[0];
            if (in_array($whereIsGrantedAction, $availableActions, true)) {
                $whereActionsContainAnyOf = [$whereIsGrantedAction, self::MANAGE_ACTION];
            } else {
                return [];
            }
        }

        // since grants for all resource items are requested, we get the groups the user is member of beforehand
        // let the db do the pagination (probably more efficient)
        foreach ($this->resourceActionGrantService->getResourceActionGrantsForAuthorizationResourcePage(
            $resourceClass, InternalResourceActionGrantService::ITEM_ACTIONS_TYPE, $whereActionsContainAnyOf,
            $currentUserIdentifier,
            $currentUserIdentifier !== null ?
                self::nullIfEmpty($this->groupService->getGroupsUserIsMemberOf($currentUserIdentifier)) : null,
            self::nullIfEmpty($this->getDynamicGroupsCurrentUserIsMemberOf()),
            $firstResultIndex, $maxNumResults) as $resourceActionGrant) {
            // since we get grants for resource items (and not collections) we rely on the resource identifier not to be null
            if ($currentResourceIdentifier !== $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier()) {
                $currentResourceIdentifier = $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier();
                $resourceActions[$currentResourceIdentifier] = [];
            }
            $resourceActions[$currentResourceIdentifier][] = $resourceActionGrant->getAction();
        }

        return $resourceActions;
    }

    /**
     * @return string[]
     *
     * @throws ApiError
     */
    public function getResourceCollectionActionsForCurrentUser(string $resourceClass): array
    {
        return $this->getResourceCollectionActionsForCurrentUserInternal($resourceClass);
    }

    /**
     * @throws ApiError
     */
    public function addGroup(string $groupIdentifier): ResourceActionGrant
    {
        return $this->resourceActionGrantService->addResourceAndManageResourceGrantFor(
            self::GROUP_RESOURCE_CLASS, $groupIdentifier, $this->getUserIdentifier());
    }

    /**
     * @throws ApiError
     */
    public function removeGroup(string $groupIdentifier): void
    {
        $this->resourceActionGrantService->removeAuthorizationResourceByResourceClassAndIdentifier(self::GROUP_RESOURCE_CLASS, $groupIdentifier);
    }

    /**
     * @return Group[]
     */
    public function getGroupsCurrentUserIsAuthorizedToRead(int $firstResultIndex, int $maxNumResults, array $filters = []): array
    {
        $GROUP_ALIAS = 'g';
        $AUTHORIZATION_RESOURCE_ALIAS = 'ar';

        $userIdentifier = $this->getUserIdentifier();
        $queryBuilder = $this->resourceActionGrantService->createAuthorizationResourceQueryBuilder($GROUP_ALIAS,
            self::GROUP_RESOURCE_CLASS, InternalResourceActionGrantService::IS_NOT_NULL,
            [self::MANAGE_ACTION, self::READ_GROUP_ACTION],
            $userIdentifier,
            $userIdentifier !== null ? self::nullIfEmpty($this->groupService->getGroupsUserIsMemberOf($userIdentifier)) : null,
            self::nullIfEmpty($this->getDynamicGroupsCurrentUserIsMemberOf()));

        $queryBuilder
            ->innerJoin(Group::class, $GROUP_ALIAS, Join::WITH,
                "unhex(replace($AUTHORIZATION_RESOURCE_ALIAS.resourceIdentifier, '-', '')) = $GROUP_ALIAS.identifier");
        if ($groupNameFilter = $filters[self::SEARCH_FILTER] ?? null) {
            $queryBuilder
                ->andWhere($this->entityManager->getExpressionBuilder()->like("$GROUP_ALIAS.name", ':groupNameLike'))
                ->setParameter(':groupNameLike', "%$groupNameFilter%");
        }
        if ($getChildGroupCandidatesForGroupIdentifierFilter =
            $filters[self::GET_CHILD_GROUP_CANDIDATES_FOR_GROUP_IDENTIFIER_FILTER] ?? null) {
            $binaryChildGroupCandidateIdentifiers = $this->groupService->getDisallowedChildGroupIdentifiersBinaryFor(
                $getChildGroupCandidatesForGroupIdentifierFilter);
            $queryBuilder
                ->andWhere($this->entityManager->getExpressionBuilder()->notIn(
                    "$GROUP_ALIAS.identifier", ':getChildGroupCandidatesForGroupIdentifierFilter'))
                ->setParameter(':getChildGroupCandidatesForGroupIdentifierFilter',
                    $binaryChildGroupCandidateIdentifiers,
                    ArrayParameterType::BINARY);
        }

        return $queryBuilder
            ->getQuery()
            ->setFirstResult($firstResultIndex)
            ->setMaxResults($maxNumResults)
            ->getResult();
    }

    public function isCurrentUserAuthorizedToAddGroups(): bool
    {
        return $this->isCurrentUserGranted(self::GROUP_RESOURCE_CLASS,
            null, self::CREATE_GROUPS_ACTION);
    }

    public function isCurrentUserAuthorizedToRemoveGroup(Group $group): bool
    {
        return $this->isCurrentUserGranted(self::GROUP_RESOURCE_CLASS,
            $group->getIdentifier(), self::DELETE_GROUP_ACTION);
    }

    public function isCurrentUserAuthorizedToUpdateGroup(Group $group): bool
    {
        return $this->isCurrentUserGranted(self::GROUP_RESOURCE_CLASS,
            $group->getIdentifier(), self::UPDATE_GROUP_ACTION);
    }

    public function isCurrentUserAuthorizedToReadGroup(Group $group): bool
    {
        return $this->isCurrentUserGranted(self::GROUP_RESOURCE_CLASS,
            $group->getIdentifier(), self::READ_GROUP_ACTION);
    }

    public function isCurrentUserAuthorizedToAddGroupMember(GroupMember $groupMember): bool
    {
        return $this->isCurrentUserGranted(self::GROUP_RESOURCE_CLASS,
            $groupMember->getGroup()->getIdentifier(),
            self::ADD_GROUP_MEMBERS_GROUP_ACTION);
    }

    public function isCurrentUserAuthorizedToRemoveGroupMember(GroupMember $groupMember): bool
    {
        return $this->isCurrentUserGranted(self::GROUP_RESOURCE_CLASS,
            $groupMember->getGroup()->getIdentifier(),
            self::DELETE_GROUP_MEMBERS_GROUP_ACTION);
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
        return $this->isCurrentUsersGrant($resourceActionGrant)
            || $this->doesCurrentUserHaveAManageGrantForAuthorizationResource(
                $resourceActionGrant->getAuthorizationResource()->getIdentifier());
    }

    public function isCurrentUserAuthorizedToReadResource(AuthorizationResource $item): bool
    {
        return !empty($this->getResourceActionsForAuthorizationResourceForCurrentUser($item->getIdentifier()));
    }

    public function getAuthorizationResource(string $identifier): ?AuthorizationResource
    {
        $authorizationResource = $this->resourceActionGrantService->getAuthorizationResource($identifier);
        $authorizationResource?->setWritable($this->doesCurrentUserHaveAManageGrantForAuthorizationResource($identifier));

        return $authorizationResource;
    }

    /**
     * @return string[]
     */
    public function getResourceClassesCurrentUserIsAuthorizedToRead(int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        return array_map(function ($authorizationResource) {
            return $authorizationResource->getResourceClass();
        }, $this->getResourceActionGrantsCurrentUserIsAuthorizedToReadInternal(self::GET_RESOURCE_CLASSES,
            null, null, $firstResultIndex, $maxNumResults));
    }

    /**
     * @return AuthorizationResource[]
     */
    public function getAuthorizationResourcesCurrentUserIsAuthorizedToRead(?string $resourceClass = null,
        int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        return $this->getResourceActionGrantsCurrentUserIsAuthorizedToReadInternal(self::GET_AUTHORIZATION_RESOURCES,
            $resourceClass, null, $firstResultIndex, $maxNumResults);
    }

    /**
     * @return ResourceActionGrant[]
     */
    public function getResourceActionGrantsUserIsAuthorizedToRead(?string $resourceClass = null, ?string $resourceIdentifier = null,
        int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        return $this->getResourceActionGrantsCurrentUserIsAuthorizedToReadInternal(self::GET_RESOURCE_ACTION_GRANTS,
            $resourceClass, $resourceIdentifier, $firstResultIndex, $maxNumResults);
    }

    private static function toManageResourceCollectionPolicyName(string $resourceClass): string
    {
        return self::MANAGE_RESOURCE_COLLECTION_POLICY_PREFIX.$resourceClass;
    }

    private static function toResourceClass(string $manageResourceCollectionPolicyName): string
    {
        return substr($manageResourceCollectionPolicyName, strlen(self::MANAGE_RESOURCE_COLLECTION_POLICY_PREFIX));
    }

    private static function isManageResourceCollectionPolicyName(string $dynamicGroupIdentifier): bool
    {
        return str_starts_with($dynamicGroupIdentifier, self::MANAGE_RESOURCE_COLLECTION_POLICY_PREFIX);
    }

    private static function toIsCurrentUserMemberOfDynamicGroupPolicyName(string $dynamicGroupIdentifier): string
    {
        return $dynamicGroupIdentifier;
    }

    private static function isCurrentUserMemberOfDynamicGroupPolicyName(string $dynamicGroupIdentifier): bool
    {
        return !str_starts_with($dynamicGroupIdentifier, self::MANAGE_RESOURCE_COLLECTION_POLICY_PREFIX);
    }

    private static function toDynamicGroupIdentifier(string $isCurrentUserMemberOfDynamicGroupPolicyName): string
    {
        return $isCurrentUserMemberOfDynamicGroupPolicyName;
    }

    private static function nullIfEmpty(array $array): ?array
    {
        return empty($array) ? null : $array;
    }

    /**
     * @return ResourceActionGrant[]|AuthorizationResource[]
     *
     * @throws ApiError
     */
    private function getResourceActionGrantsCurrentUserIsAuthorizedToReadInternal(string $get = self::GET_RESOURCE_ACTION_GRANTS,
        ?string $resourceClass = null, ?string $resourceIdentifier = null, int $firstResultIndex = 0, int $maxNumResults = 1024): array
    {
        $AUTHORIZATION_RESOURCE_ALIAS = InternalResourceActionGrantService::AUTHORIZATION_RESOURCE_ALIAS;
        $RESOURCE_ACTION_GRANT_ALIAS = InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_ALIAS;

        $userIdentifier = $this->getUserIdentifier();
        $groupIdentifiers = $userIdentifier !== null ? self::nullIfEmpty($this->groupService->getGroupsUserIsMemberOf($userIdentifier)) : null;
        $dynamicGroupIdentifiers = self::nullIfEmpty($this->getDynamicGroupsCurrentUserIsMemberOf());

        // Get all grants
        // * that the user is a holder of (personally or by static/dynamic group)
        // * from all resources that the user manages
        try {
            // create a subquery getting the authorization resource IDs that the user manages:
            $managedAuthorizationResourceIdentifiersBinary = $this->resourceActionGrantService->createResourceActionGrantQueryBuilder(
                InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_AUTHORIZATION_RESOURCE_IDENTIFIER_ALIAS,
                $resourceClass, $resourceIdentifier, [AuthorizationService::MANAGE_ACTION],
                $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers)->getQuery()->getSingleColumnResult();

            $queryBuilder = $get === self::GET_RESOURCE_ACTION_GRANTS ?
                $this->resourceActionGrantService->createResourceActionGrantQueryBuilder(
                    InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_ALIAS,
                    $resourceClass, $resourceIdentifier, null,
                    $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers) :
                $this->resourceActionGrantService->createAuthorizationResourceQueryBuilder(
                    InternalResourceActionGrantService::AUTHORIZATION_RESOURCE_ALIAS, $resourceClass, $resourceIdentifier, null,
                    $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers);

            switch ($get) {
                case self::GET_RESOURCE_ACTION_GRANTS:
                    $queryBuilder->orderBy("$RESOURCE_ACTION_GRANT_ALIAS.authorizationResource");
                    break;
                case self::GET_RESOURCE_CLASSES:
                    $queryBuilder->groupBy("$AUTHORIZATION_RESOURCE_ALIAS.resourceClass");
                    break;
            }

            $resultEntities = $queryBuilder
                ->andWhere($queryBuilder->expr()->neq("$RESOURCE_ACTION_GRANT_ALIAS.action", ':manageAction'))
                ->orWhere($queryBuilder->expr()->in("$RESOURCE_ACTION_GRANT_ALIAS.authorizationResource", ':authorizationResourceIdentifiers'))
                ->setParameter(':authorizationResourceIdentifiers', $managedAuthorizationResourceIdentifiersBinary, ArrayParameterType::BINARY)
                ->setParameter(':manageAction', AuthorizationService::MANAGE_ACTION)
                ->getQuery()
                ->setFirstResult($firstResultIndex)
                ->setMaxResults($maxNumResults)
                ->getResult();

            if ($get === self::GET_AUTHORIZATION_RESOURCES) {
                $managedAuthorizationResourceIdentifiers = [];
                foreach ($managedAuthorizationResourceIdentifiersBinary as $authorizationResourceIdentifierBinary) {
                    $managedAuthorizationResourceIdentifiers[
                    AuthorizationUuidBinaryType::toStringUuid($authorizationResourceIdentifierBinary)] = true;
                }

                foreach ($resultEntities as $authorizationResource) {
                    $authorizationResource->setWritable(
                        isset($managedAuthorizationResourceIdentifiers[$authorizationResource->getIdentifier()]));
                }
            }

            return $resultEntities;
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to get resource action grant collection!',
                InternalResourceActionGrantService::GETTING_RESOURCE_ACTION_GRANT_COLLECTION_FAILED_ERROR_ID,
                ['message' => $e->getMessage()]);
        }
    }

    /**
     * @return string[]
     */
    private function getResourceItemActionsForCurrentUserInternal(string $resourceClass, string $resourceIdentifier): array
    {
        return $this->getResourceActionsForCurrentUser(
            function () use ($resourceClass, $resourceIdentifier) {
                return $this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
                    $resourceClass, $resourceIdentifier, $this->getUserIdentifier(),
                    InternalResourceActionGrantService::IS_NOT_NULL,
                    InternalResourceActionGrantService::IS_NOT_NULL);
            });
    }

    /**
     * @parram string[]|null $whereActionsContainAnyOf
     *
     * @return string[]
     *
     * @throws ApiError
     */
    private function getResourceCollectionActionsForCurrentUserInternal(string $resourceClass): array
    {
        return $this->getResourceActionsForCurrentUser(
            function () use ($resourceClass) {
                return $this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
                    $resourceClass, InternalResourceActionGrantService::IS_NULL, $this->getUserIdentifier(),
                    InternalResourceActionGrantService::IS_NOT_NULL, InternalResourceActionGrantService::IS_NOT_NULL);
            });
    }

    /**
     * @parram string[]|null $whereActionsContainAnyOf
     *
     * @return string[]
     */
    private function getResourceActionsForAuthorizationResourceForCurrentUser(string $authorizationResourceIdentifier): array
    {
        if (($resourceItemActions = $this->grantedAuthorizationResourceActionsCache[$authorizationResourceIdentifier] ?? null) === null) {
            $resourceItemActions = $this->getResourceActionsForCurrentUser(function () use ($authorizationResourceIdentifier) {
                return $this->resourceActionGrantService->getResourceActionGrantsForAuthorizationResourceIdentifier(
                    $authorizationResourceIdentifier, $this->getUserIdentifier(),
                    InternalResourceActionGrantService::IS_NOT_NULL, InternalResourceActionGrantService::IS_NOT_NULL);
            });

            $this->grantedAuthorizationResourceActionsCache[$authorizationResourceIdentifier] = $resourceItemActions;
        }

        return $resourceItemActions;
    }

    private function doesCurrentUserHaveAManageGrantForAuthorizationResource(
        string $authorizationResourceIdentifier): bool
    {
        return in_array(AuthorizationService::MANAGE_ACTION,
            $this->getResourceActionsForAuthorizationResourceForCurrentUser($authorizationResourceIdentifier), true);
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

    /**
     * @return string[]
     */
    private function getResourceActionsForCurrentUser(callable $getResourceActionGrantsCallback): array
    {
        $resourceActions = [];
        foreach ($getResourceActionGrantsCallback() as $resourceActionGrant) {
            if ($this->isCurrentUsersGrant($resourceActionGrant)) {
                $resourceActions[$resourceActionGrant->getAction()] = null; // deduplication
            }
        }

        return array_keys($resourceActions);
    }

    /**
     * @throws ApiError
     */
    private function isCurrentUsersGrant(ResourceActionGrant $resourceActionGrant): bool
    {
        $userIdentifier = $this->getUserIdentifier();

        return ($userIdentifier !== null
                && ($resourceActionGrant->getUserIdentifier() === $userIdentifier
                    || ($resourceActionGrant->getGroup() !== null
                        && $this->groupService->isUserMemberOfGroup($userIdentifier, $resourceActionGrant->getGroup()->getIdentifier()))))
            || ($resourceActionGrant->getDynamicGroupIdentifier() !== null
                && $this->isCurrentUserMemberOfDynamicGroup($resourceActionGrant->getDynamicGroupIdentifier()));
    }

    private function tryConfigure(): void
    {
        if ($this->config !== null && $this->cachePool !== null) {
            $policies = [];

            $manageResourceCollectionPolicyNames = [];
            foreach ($this->config[Configuration::RESOURCE_CLASSES] ?? [] as $resourceClassConfig) {
                $policies[$manageResourceCollectionPolicyName = self::toManageResourceCollectionPolicyName($resourceClassConfig[Configuration::IDENTIFIER])] =
                    $resourceClassConfig[Configuration::MANAGE_RESOURCE_COLLECTION_POLICY];
                $manageResourceCollectionPolicyNames[] = $manageResourceCollectionPolicyName;
            }
            $manageGroupCollectionPolicyName = self::toManageResourceCollectionPolicyName(self::GROUP_RESOURCE_CLASS);
            $policies[$manageGroupCollectionPolicyName] = $this->config[Configuration::CREATE_GROUPS_POLICY];
            $manageResourceCollectionPolicyNames[] = $manageGroupCollectionPolicyName;

            foreach ($this->config[Configuration::DYNAMIC_GROUPS] ?? [] as $dynamicGroup) {
                $policies[self::toIsCurrentUserMemberOfDynamicGroupPolicyName($dynamicGroup[Configuration::IDENTIFIER])] =
                    $dynamicGroup[Configuration::IS_CURRENT_USER_GROUP_MEMBER_EXPRESSION];
            }
            $policies[self::toIsCurrentUserMemberOfDynamicGroupPolicyName(self::DYNAMIC_GROUP_IDENTIFIER_EVERYBODY)] = 'true';

            $this->setUpAccessControlPolicies($policies);

            $cacheItem = $this->cachePool->getItem(self::WERE_MANAGE_COLLECTION_GRANTS_WRITTEN_TO_DB_CACHE_KEY);
            if (false === $cacheItem->isHit()) {
                try {
                    $this->updateManageResourceCollectionPolicyGrants($manageResourceCollectionPolicyNames);
                    $cacheItem->set(true);
                    $this->cachePool->save($cacheItem);
                } catch (\Exception) {
                    // ignore db errors which may occur, when setConfig is called when no database is available
                    // e.g. calling Symfony commands locally
                }
            }
        }
    }

    /**
     * @param string[] $manageResourceCollectionPolicyNames
     */
    private function updateManageResourceCollectionPolicyGrants(array $manageResourceCollectionPolicyNames): void
    {
        $lastResourceClass = null;
        $resourceClassGrantsMap = [];
        foreach ($this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(null,
            InternalResourceActionGrantService::IS_NULL, null, null,
            null, 0, 1024,
            [InternalResourceActionGrantService::ORDER_BY_AUTHORIZATION_RESOURCE_OPTION => true]) as $resourceActionGrant) {
            $resourceClass = $resourceActionGrant->getAuthorizationResource()->getResourceClass();
            if ($lastResourceClass !== $resourceClass) {
                $lastResourceClass = $resourceClass;
                $resourceClassGrantsMap[$resourceClass]['other_grants'] = [];
            }
            $dynamicGroupIdentifier = $resourceActionGrant->getDynamicGroupIdentifier();
            if ($dynamicGroupIdentifier !== null
                && self::isManageResourceCollectionPolicyName($dynamicGroupIdentifier)) {
                $resourceClassGrantsMap[$resourceClass]['policy_grant'] = $resourceActionGrant;
            } else {
                $resourceClassGrantsMap[$resourceClass]['other_grants'][] = $resourceActionGrant;
            }
        }

        foreach ($resourceClassGrantsMap as $resourceClass => $resourceClassGrants) {
            $manageResourceCollectionPolicyName = self::toManageResourceCollectionPolicyName($resourceClass);
            $key = array_search($manageResourceCollectionPolicyName, $manageResourceCollectionPolicyNames, true);
            $isPolicyPresentInConfig = $key !== false;
            if ($key !== false) {
                unset($manageResourceCollectionPolicyNames[$key]);
            }
            if ($policyGrant = $resourceClassGrants['policy_grant'] ?? null) {
                // the manage resource collection policy grant is present in the DB
                if (!$isPolicyPresentInConfig) {
                    // however, the manage resource collection policy is not present in config anymore
                    if (empty($resourceClassGrants['other_grants'])) {
                        // (A) no other grants -> delete the authorization resource from DB
                        $this->resourceActionGrantService->removeAuthorizationResource($policyGrant->getAuthorizationResource());
                        // WORKAROUND for doctrine ORM disregarding ON DELETE CASCADE which auto-removes grants
                        // on parent authorization resource removal. on next persist+flush it mis-interprets the removed authorization resource
                        // it finds under the grant it still manages (but which was automatically deleted in the DB) as a new entity:
                        // 'A new entity was found through the relationship 'Dbp\\Relay\\AuthorizationBundle\\Entity\\ResourceActionGrant#authorizationResource'
                        // that was not configured to cascade persist operations for entity: ...':
                        // (https://github.com/doctrine/orm/issues/11539)
                        $policyGrant->setAuthorizationResource(null);
                    } else {
                        // (B) there are other (not auto-added) grants -> just remove the auto-added policy grant from DB
                        $this->resourceActionGrantService->removeResourceActionGrant($policyGrant);
                    }
                } // (C) otherwise we are done, since the manage resource collection policy is still present in config
            } else {
                // the policy grant is not present in DB (however the authorization resource is)
                if ($isPolicyPresentInConfig) {
                    // (D) the manage resource collection policy is present in config -> auto-add the policy grant to DB
                    $authorizationResource = $this->resourceActionGrantService->getAuthorizationResource($resourceClassGrants['other_grants'][0]->getAuthorizationResource()->getIdentifier());
                    $resourceActionGrant = new ResourceActionGrant();
                    $resourceActionGrant->setAuthorizationResource($authorizationResource);
                    $resourceActionGrant->setAction(self::MANAGE_ACTION);
                    $resourceActionGrant->setDynamicGroupIdentifier($manageResourceCollectionPolicyName);
                    $this->resourceActionGrantService->addResourceActionGrant($resourceActionGrant);
                } // the manage resource collection policy is not present in config -> nothing to do
            }
        }

        // remaining policies, i.e. policies of resource classes for which no collection grants are present in DB
        foreach ($manageResourceCollectionPolicyNames as $manageResourceCollectionPolicyName) {
            $resourceClass = self::toResourceClass($manageResourceCollectionPolicyName);
            $authorizationResource = $this->resourceActionGrantService->getAuthorizationResourceByResourceClassAndIdentifier(
                $resourceClass, null);
            if ($authorizationResource === null) {
                // no authorization resource is present in DB (as expected) -> auto-add authorization resource and policy grant to DB
                $this->resourceActionGrantService->addResourceAndManageResourceGrantFor($resourceClass, null, null, null, $manageResourceCollectionPolicyName);
            } else {
                // orphan authorization resource is already present in DB -> auto-add the policy grant to DB
                $resourceActionGrant = new ResourceActionGrant();
                $resourceActionGrant->setAuthorizationResource($authorizationResource);
                $resourceActionGrant->setAction(self::MANAGE_ACTION);
                $resourceActionGrant->setDynamicGroupIdentifier($manageResourceCollectionPolicyName);
                $this->resourceActionGrantService->addResourceActionGrant($resourceActionGrant);
            }
        }
    }

    /**
     * @throws ApiError
     */
    public function addResourceActionGrant(string $resourceClass, string $resourceIdentifier, string $action,
        ?string $userIdentifier = null, ?string $groupIdentifier = null, ?string $dynamicGroupIdentifier = null): void
    {
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass($resourceClass);
        $resourceActionGrant->setResourceIdentifier($resourceIdentifier);
        $resourceActionGrant->setAction($action);
        $resourceActionGrant->setUserIdentifier($userIdentifier);
        $resourceActionGrant->setGroup($groupIdentifier !== null ? $this->groupService->getGroup($groupIdentifier) : null);
        $resourceActionGrant->setDynamicGroupIdentifier($dynamicGroupIdentifier);

        $this->resourceActionGrantService->ensureAuthorizationResource($resourceActionGrant);
        $this->resourceActionGrantService->addResourceActionGrant($resourceActionGrant);
    }

    private function assertDoesNotContainReservedCharacters(string $identifier): void
    {
        if (str_contains($identifier, UserAttributeProvider::SEPARATOR)) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                sprintf("resource class and identifier must not contain the reserved character '%s'",
                    UserAttributeProvider::SEPARATOR));
        }
        if (str_contains($identifier, GrantedActions::ID_SEPARATOR)) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                sprintf("resource class and identifier must not contain the reserved character '%s'",
                    GrantedActions::ID_SEPARATOR));
        }
    }
}
