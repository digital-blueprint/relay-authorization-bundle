<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Authorization;

use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Entity\GroupMember;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Helper\AuthorizationUuidBinaryType;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\CoreBundle\Authorization\AbstractAuthorizationService;
use Dbp\Relay\CoreBundle\Authorization\AuthorizationException;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
class AuthorizationService extends AbstractAuthorizationService implements LoggerAwareInterface, ResetInterface
{
    use LoggerAwareTrait;

    public const MANAGE_ACTION = 'manage';

    public const GROUP_RESOURCE_CLASS = 'DbpRelayAuthorizationGroup';

    public const CREATE_GROUPS_ACTION = 'create';

    public const READ_GROUP_ACTION = 'read';
    public const UPDATE_GROUP_ACTION = 'update';
    public const DELETE_GROUP_ACTION = 'delete';
    public const ADD_GROUP_MEMBERS_GROUP_ACTION = 'add_members';
    public const DELETE_GROUP_MEMBERS_GROUP_ACTION = 'delete_members';

    public const GROUP_ITEM_ACTIONS = [
        self::READ_GROUP_ACTION => [
            'en' => 'Read',
            'de' => 'Lesen',
        ],
        self::UPDATE_GROUP_ACTION => [
            'en' => 'Update',
            'de' => 'Aktualisieren',
        ],
        self::DELETE_GROUP_ACTION => [
            'en' => 'Delete',
            'de' => 'Löschen',
        ],
        self::ADD_GROUP_MEMBERS_GROUP_ACTION => [
            'en' => 'Add members',
            'de' => 'Mitglieder hinzufügen',
        ],
        self::DELETE_GROUP_MEMBERS_GROUP_ACTION => [
            'en' => 'Delete members',
            'de' => 'Mitglieder löschen',
        ],
    ];
    public const GROUP_COLLECTION_ACTIONS = [
        self::CREATE_GROUPS_ACTION => [
            'en' => 'Create',
            'de' => 'Erstellen',
        ],
    ];

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

    public function __construct(
        private readonly InternalResourceActionGrantService $internalResourceActionGrantService,
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
     * For testing purposes.
     */
    public function reset(): void
    {
        $this->grantedAuthorizationResourceActionsCache = [];
        $this->cachePool->clear();
    }

    /**
     * @param array<string, array<string, string>> $itemActions       A mapping from item action names to their localized names
     * @param array<string, array<string, string>> $collectionActions A mapping from collection action names to their localized names
     */
    public function setAvailableResourceClassActions(string $resourceClass,
        array $itemActions, array $collectionActions): void
    {
        $this->internalResourceActionGrantService->setAvailableResourceClassActions(
            $resourceClass, $itemActions, $collectionActions);
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
    public function addResourceActionGrant(string $resourceClass, ?string $resourceIdentifier, string $action,
        ?string $userIdentifier = null, ?string $groupIdentifier = null, ?string $dynamicGroupIdentifier = null): void
    {
        $this->assertResourceClassNotReserved($resourceClass);

        $this->internalResourceActionGrantService->addResourceActionGrantByResourceClassAndIdentifier(
            $resourceClass, $resourceIdentifier, $action, $userIdentifier,
            $groupIdentifier !== null ? $this->groupService->getGroup($groupIdentifier) : null,
            $dynamicGroupIdentifier);
    }

    /**
     * @throws ApiError
     */
    public function removeResourceActionGrant(string $identifier): void
    {
        $this->internalResourceActionGrantService->removeResourceActionGrantByIdentifier($identifier);
    }

    /**
     * @throws ApiError
     */
    public function removeGrantsForResource(string $resourceClass, string $resourceIdentifier): void
    {
        $this->assertResourceClassNotReserved($resourceClass);

        $this->internalResourceActionGrantService->removeAuthorizationResourceByResourceClassAndIdentifier($resourceClass, $resourceIdentifier);
    }

    /**
     * @throws ApiError
     */
    public function removeGrantsForResources(string $resourceClass, array $resourceIdentifiers): void
    {
        $this->assertResourceClassNotReserved($resourceClass);

        $this->internalResourceActionGrantService->removeAuthorizationResourcesByResourceClassAndIdentifier($resourceClass, $resourceIdentifiers);
    }

    public function addResourceToGroupResource(string $groupResourceClass, ?string $groupResourceIdentifier,
        string $targetResourceClass, ?string $targetResourceIdentifier): void
    {
        $this->internalResourceActionGrantService->addResourceToGroupResource(
            $groupResourceClass, $groupResourceIdentifier,
            $targetResourceClass, $targetResourceIdentifier);
    }

    public function removeResourceFromGroupResource(string $sourceResourceClass, ?string $sourceResourceIdentifier,
        string $targetResourceClass, ?string $targetResourceIdentifier): void
    {
        $this->internalResourceActionGrantService->removeResourceFromGroupResource(
            $sourceResourceClass, $sourceResourceIdentifier,
            $targetResourceClass, $targetResourceIdentifier);
    }

    /**
     * @param bool $ignoreActionAvailability if true, grants are returned if the granted action is not available for the resource class
     *
     * @return ResourceActionGrant[]
     *
     * @throws ApiError
     */
    public function getResourceActionGrantsForResourceClassAndIdentifier(
        string $resourceClass, ?string $resourceIdentifier, bool $ignoreActionAvailability = false): array
    {
        $options = [];
        if ($ignoreActionAvailability) {
            $options[InternalResourceActionGrantService::IGNORE_ACTION_AVAILABILITY_OPTION] = true;
        }

        return $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            $resourceClass,
            $resourceIdentifier === null ? InternalResourceActionGrantService::IS_NULL : $resourceIdentifier,
            options: $options);
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
                $this->internalResourceActionGrantService->getAvailableResourceClassActions($resourceClass)[
                $resourceIdentifier !== null ? 0 : 1];

            return array_key_exists($action, $availableActions);
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
            $availableActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions($resourceClass)[0];
            if (array_key_exists($whereIsGrantedAction, $availableActions)) {
                $whereActionsContainAnyOf = [$whereIsGrantedAction, self::MANAGE_ACTION];
            } else {
                return [];
            }
        }

        // since grants for all resource items are requested, we get the groups the user is member of beforehand
        // let the db do the pagination (probably more efficient)
        foreach ($this->internalResourceActionGrantService->getResourceActionGrantsForAuthorizationResourcePage(
            $resourceClass, AvailableResourceClassAction::ITEM_ACTION_TYPE, $whereActionsContainAnyOf,
            $currentUserIdentifier,
            $currentUserIdentifier !== null ?
                self::nullIfEmpty($this->groupService->getGroupsUserIsMemberOf($currentUserIdentifier)) : null,
            self::nullIfEmpty($this->getDynamicGroupsCurrentUserIsMemberOf()),
            $firstResultIndex, $maxNumResults) as $resourceActionGrant) {
            // since we get grants for resource items (and not collections), we rely on the resource identifier not to be null
            if ($currentResourceIdentifier !== $resourceActionGrant->getResourceIdentifier()) {
                $currentResourceIdentifier = $resourceActionGrant->getResourceIdentifier();
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
        return $this->internalResourceActionGrantService->addResourceActionGrantByResourceClassAndIdentifier(
            self::GROUP_RESOURCE_CLASS, $groupIdentifier, self::MANAGE_ACTION, $this->getUserIdentifier());
    }

    /**
     * @throws ApiError
     */
    public function removeGroup(string $groupIdentifier): void
    {
        $this->internalResourceActionGrantService->removeAuthorizationResourceByResourceClassAndIdentifier(self::GROUP_RESOURCE_CLASS, $groupIdentifier);
    }

    /**
     * @return Group[]
     */
    public function getGroupsCurrentUserIsAuthorizedToRead(int $firstResultIndex, int $maxNumResults, array $filters = []): array
    {
        $AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS =
            InternalResourceActionGrantService::AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS;
        $GROUP_ALIAS = 'g';

        $userIdentifier = $this->getUserIdentifier();

        $groupParameterValues = [];
        $groupParameterTypes = [];
        $groupNameCriteria = 'true';
        if ($groupNameLike = $filters[self::SEARCH_FILTER] ?? null) {
            $groupNameCriteria = "$GROUP_ALIAS.name LIKE :groupNameLike";
            $groupParameterValues['groupNameLike'] = "%{$groupNameLike}%";
        }
        $childGroupCandidateCriteria = 'true';
        if ($getChildGroupCandidatesForGroupIdentifierFilter =
            $filters[self::GET_CHILD_GROUP_CANDIDATES_FOR_GROUP_IDENTIFIER_FILTER] ?? null) {
            $binaryChildGroupCandidateIdentifiers = $this->groupService->getDisallowedChildGroupIdentifiersBinaryFor(
                $getChildGroupCandidatesForGroupIdentifierFilter);
            $childGroupCandidateCriteria = "$GROUP_ALIAS.identifier NOT IN (:getChildGroupCandidatesForGroupIdentifierFilter)";
            $groupParameterValues['getChildGroupCandidatesForGroupIdentifierFilter'] = $binaryChildGroupCandidateIdentifiers;
            $groupParameterTypes['getChildGroupCandidatesForGroupIdentifierFilter'] = ArrayParameterType::BINARY;
        }

        $options = [
            InternalResourceActionGrantService::SELECT_OPTION => "$GROUP_ALIAS.*",
            InternalResourceActionGrantService::ADDITIONAL_JOIN_STATEMENTS_OPTION => "INNER JOIN authorization_groups $GROUP_ALIAS
                 ON UNHEX(REPLACE($AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS.effective_resource_identifier, '-', '')) = $GROUP_ALIAS.identifier",
            InternalResourceActionGrantService::ADDITIONAL_CRITERIA_OPTION => [
                "AND $groupNameCriteria AND $childGroupCandidateCriteria",
                $groupParameterValues,
                $groupParameterTypes,
            ],
        ];

        [$sql, $parameterValues, $parameterTypes] = $this->internalResourceActionGrantService->getResourceActionGrantQuery(
            InternalResourceActionGrantService::GET_AUTHORIZATION_RESOURCES,
            self::GROUP_RESOURCE_CLASS, InternalResourceActionGrantService::IS_NOT_NULL,
            null, [self::MANAGE_ACTION, self::READ_GROUP_ACTION], $userIdentifier,
            $userIdentifier !== null ? self::nullIfEmpty($this->groupService->getGroupsUserIsMemberOf($userIdentifier)) : null,
            self::nullIfEmpty($this->getDynamicGroupsCurrentUserIsMemberOf()), $firstResultIndex, $maxNumResults,
            $options);

        try {
            $groups = [];
            foreach ($this->entityManager->getConnection()->executeQuery($sql, $parameterValues, $parameterTypes)
                         ->fetchAllAssociative() as $row) {
                $group = new Group();
                $group->setIdentifier(AuthorizationUuidBinaryType::toStringUuid($row['identifier']));
                $group->setName($row['name']);
                $groups[] = $group;
            }

            return $groups;
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to get groups: '.$throwable->getMessage(), ['exception' => $throwable]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to get groups!');
        }
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
        $AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS =
            InternalResourceActionGrantService::AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS;

        $userIdentifier = $this->getUserIdentifier();
        $groupIdentifiers = $userIdentifier !== null ? self::nullIfEmpty($this->groupService->getGroupsUserIsMemberOf($userIdentifier)) : null;
        $dynamicGroupIdentifiers = self::nullIfEmpty($this->getDynamicGroupsCurrentUserIsMemberOf());

        // Get all grants
        // * that the user is a holder of (personally or by static/dynamic group)
        // * from all resources that the user manages
        try {
            // create a subquery getting the authorization resource IDs that the user manages:
            $managedAuthorizationResourceIdentifiersBinary = $this->internalResourceActionGrantService->get(
                InternalResourceActionGrantService::GET_AUTHORIZATION_RESOURCE_IDENTIFIERS,
                $resourceClass, $resourceIdentifier, null, [AuthorizationService::MANAGE_ACTION],
                $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers);

            $options = [
                InternalResourceActionGrantService::ADDITIONAL_CRITERIA_OPTION => [
                    "OR $AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS.effective_authorization_resource_identifier IN (:orAuthorizationResourceIdentifiersIn)",
                    ['orAuthorizationResourceIdentifiersIn' => $managedAuthorizationResourceIdentifiersBinary],
                    ['orAuthorizationResourceIdentifiersIn' => ArrayParameterType::BINARY],
                ],
            ];
            if ($get === self::GET_RESOURCE_CLASSES) {
                $options[InternalResourceActionGrantService::GROUP_BY_RESOURCE_CLASS_OPTION] = true;
            }

            return $this->internalResourceActionGrantService->get($get === self::GET_RESOURCE_ACTION_GRANTS ?
                InternalResourceActionGrantService::GET_RESOURCE_ACTION_GRANTS :
                InternalResourceActionGrantService::GET_AUTHORIZATION_RESOURCES,
                $resourceClass, $resourceIdentifier, null, null,
                $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers,
                $firstResultIndex, $maxNumResults, $options);
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
                return $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
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
                return $this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
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
                return $this->internalResourceActionGrantService->getResourceActionGrantsForAuthorizationResourceIdentifier(
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
                if (($action = $resourceActionGrant->getAction()) === self::MANAGE_ACTION) {
                    $resourceActions = [self::MANAGE_ACTION => null]; // manage implies all actions
                    break;
                } else {
                    $resourceActions[$action] = null; // deduplication
                }
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
        foreach ($this->internalResourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(null,
            InternalResourceActionGrantService::IS_NULL) as $resourceActionGrant) {
            $resourceClass = $resourceActionGrant->getResourceClass();
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
            /** @var ?ResourceActionGrant $policyGrant */
            if ($policyGrant = $resourceClassGrants['policy_grant'] ?? null) {
                // the manage resource collection policy grant is present in the DB
                if (!$isPolicyPresentInConfig) {
                    // however, the manage resource collection policy is not present in config anymore
                    if (empty($resourceClassGrants['other_grants'])) {
                        // (A) no other grants -> delete the authorization resource from DB
                        $this->internalResourceActionGrantService->removeAuthorizationResourceByResourceClassAndIdentifier(
                            $policyGrant->getResourceClass(), $policyGrant->getResourceIdentifier());
                        // WORKAROUND for doctrine ORM disregarding ON DELETE CASCADE which auto-removes grants
                        // on parent authorization resource removal. on next persist+flush it mis-interprets the removed authorization resource
                        // it finds under the grant it still manages (but which was automatically deleted in the DB) as a new entity:
                        // 'A new entity was found through the relationship 'Dbp\\Relay\\AuthorizationBundle\\Entity\\ResourceActionGrant#authorizationResource'
                        // that was not configured to cascade persist operations for entity: ...':
                        // (https://github.com/doctrine/orm/issues/11539)
                        $policyGrant->setAuthorizationResource(null);
                    } else {
                        // (B) there are other (not auto-added) grants -> just remove the auto-added policy grant from DB
                        $this->internalResourceActionGrantService->removeResourceActionGrantByIdentifier($policyGrant->getIdentifier());
                    }
                } // (C) otherwise we are done, since the manage resource collection policy is still present in config
            } else {
                // the policy grant is not present in DB (however the authorization resource is)
                if ($isPolicyPresentInConfig) {
                    // (D) the manage resource collection policy is present in config -> auto-add the policy grant to DB
                    $otherGrant = $resourceClassGrants['other_grants'][0];
                    $this->internalResourceActionGrantService->addResourceActionGrantByResourceClassAndIdentifier($otherGrant->getResourceClass(),
                        $otherGrant->getResourceIdentifier(), self::MANAGE_ACTION,
                        null, null, $manageResourceCollectionPolicyName);
                } // the manage resource collection policy is not present in config -> nothing to do
            }
        }

        // remaining policies, i.e. policies of resource classes for which no collection grants are present in DB
        foreach ($manageResourceCollectionPolicyNames as $manageResourceCollectionPolicyName) {
            $this->internalResourceActionGrantService->addResourceActionGrantByResourceClassAndIdentifier(
                self::toResourceClass($manageResourceCollectionPolicyName), null,
                self::MANAGE_ACTION, null, null, $manageResourceCollectionPolicyName);
        }
    }
}
