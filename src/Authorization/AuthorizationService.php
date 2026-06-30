<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Authorization;

use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Entity\GroupMember;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Entity\Role;
use Dbp\Relay\AuthorizationBundle\Helper\UuidUtils;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\CoreBundle\Authorization\AbstractAuthorizationService;
use Dbp\Relay\CoreBundle\Authorization\AuthorizationException;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
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

    public const MAX_NUM_RESULTS_DEFAULT = InternalResourceActionGrantService::MAX_NUM_RESULTS_DEFAULT;
    public const GROUP_SEARCH_FILTER = 'search';
    public const GET_CHILD_GROUP_CANDIDATES_FOR_GROUP_IDENTIFIER_FILTER = 'getChildGroupCandidatesForGroupIdentifier';
    public const DYNAMIC_GROUP_IDENTIFIER_EVERYBODY = 'everybody';
    public const MANAGE_RESOURCE_COLLECTION_POLICY_PREFIX = '@';
    public const COLLECTION_RESOURCE_IDENTIFIER = InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER;

    private ?array $config = null;

    public function __construct(
        private readonly InternalResourceActionGrantService $internalResourceActionGrantService,
        private readonly GroupService $groupService,
        private bool $debug = false)
    {
        parent::__construct();
    }

    public function setConfig(array $config): void
    {
        parent::setConfig($config);

        $this->config = $config;
        $this->configure();
    }

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
        if ($this->debug) { // appease linter
            assert($this->logger !== null);
        }
    }

    /**
     * For testing purposes.
     */
    public function reset(): void
    {
    }

    /**
     * @param array<string, array<string, string>> $itemActions       A mapping from item action names to their localized names
     * @param array<string, array<string, string>> $collectionActions A mapping from collection action names to their localized names
     */
    public function setAvailableResourceClassActions(string $resourceClass,
        array $itemActions, array $collectionActions): void
    {
        $this->internalResourceActionGrantService->ensureManageActionsAreAvailable();
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
            }
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                sprintf('failed to determine if current user is member of dynamic group \'%s\': %s',
                    $dynamicGroupIdentifier, $authorizationException->getMessage()));
        }
    }

    /**
     * @return string[]
     */
    public function getDynamicGroupsCurrentUserIsMemberOf(): array
    {
        return array_values(
            array_filter($this->getRoleNames(),
                function (string $policyName): bool {
                    return $this->isGrantedRole($policyName);
                }
            )
        );
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
    public function addResourceActionGrant(string $resourceClass, string $resourceIdentifier, string $action,
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

        $this->internalResourceActionGrantService->removeAuthorizationResourceByResourceClassAndIdentifier(
            $resourceClass, $resourceIdentifier);
    }

    /**
     * @throws ApiError
     */
    public function removeGrantsForResources(string $resourceClass, array $resourceIdentifiers): void
    {
        $this->assertResourceClassNotReserved($resourceClass);

        $this->internalResourceActionGrantService->removeAuthorizationResourcesByResourceClassAndIdentifier(
            $resourceClass, $resourceIdentifiers);
    }

    public function addResourceToGroupResource(string $groupResourceClass, string $groupResourceIdentifier,
        string $targetResourceClass, string $targetResourceIdentifier): void
    {
        $this->internalResourceActionGrantService->addResourceToGroupResource(
            $groupResourceClass, $groupResourceIdentifier,
            $targetResourceClass, $targetResourceIdentifier);
    }

    public function removeResourceFromGroupResource(string $sourceResourceClass, string $sourceResourceIdentifier,
        string $targetResourceClass, string $targetResourceIdentifier): void
    {
        $this->internalResourceActionGrantService->removeResourceFromGroupResource(
            $sourceResourceClass, $sourceResourceIdentifier,
            $targetResourceClass, $targetResourceIdentifier);
    }

    /**
     * @return ResourceActionGrant[]
     *
     * @throws ApiError
     */
    public function getResourceActionGrantsForResourceClassAndIdentifier(
        string $resourceClass, string $resourceIdentifier): array
    {
        return $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            $resourceClass, $resourceIdentifier);
    }

    /**
     * @parram string[]|null $whereActionsContainAnyOf
     *
     * @return string[]
     *
     * @throws ApiError
     */
    public function getGrantedResourceActionsForCurrentUser(string $resourceClass, string $resourceIdentifier): array
    {
        return $this->getGrantedResourceActionsForCurrentUserInternal($resourceClass, $resourceIdentifier);
    }

    /**
     * @throws ApiError
     */
    public function isCurrentUserGranted(string $resourceClass, string $resourceIdentifier, string $action): bool
    {
        $grantedActions = $this->getGrantedResourceActionsForCurrentUserInternal(
            $resourceClass, $resourceIdentifier);

        if (in_array($action, $grantedActions, true)) {
            // the current user has the respective action grant -> done
            return true;
        } elseif (in_array(self::MANAGE_ACTION, $grantedActions, true)) {
            // the current user has a manage grant -> check if the requested action is available at all
            $availableActions =
                $this->internalResourceActionGrantService->getAvailableResourceClassActions($resourceClass)[
                $resourceIdentifier !== self::COLLECTION_RESOURCE_IDENTIFIER ? 0 : 1];

            return array_key_exists($action, $availableActions);
        }

        return false;
    }

    /**
     * @return string[][]
     *
     * @throws ApiError
     */
    public function getGrantedResourceActionsPageForCurrentUser(string $resourceClass,
        ?string $whereIsGrantedAction = null, bool $excludeCollectionResource = true,
        int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        $resourceActions = [];
        $currentUserIdentifier = $this->getUserIdentifier();
        $whereActionsContainAnyOf = null;
        if ($whereIsGrantedAction === self::MANAGE_ACTION) {
            $whereActionsContainAnyOf = [self::MANAGE_ACTION];
        } elseif ($whereIsGrantedAction !== null) {
            // if the requested action is not available, it can't be granted either
            // we might overthink this, to still return granted actions for resources where the user has a manage grant
            $availableActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions($resourceClass)[0];
            if (array_key_exists($whereIsGrantedAction, $availableActions)) {
                $whereActionsContainAnyOf = [$whereIsGrantedAction, self::MANAGE_ACTION];
            } else {
                return [];
            }
        }

        return $this->internalResourceActionGrantService->getGrantedActionsForResourcePage(
            $resourceClass,
            $whereActionsContainAnyOf,
            $currentUserIdentifier ?: InternalResourceActionGrantService::FALSE,
            $currentUserIdentifier !== null ?
                $this->groupService->getGroupsUserIsMemberOf($currentUserIdentifier) : [],
            $this->getDynamicGroupsCurrentUserIsMemberOf(),
            $firstResultIndex, $maxNumResults,
            [InternalResourceActionGrantService::EXCLUDE_COLLECTION_RESOURCE_OPTION => $excludeCollectionResource]);
    }

    /**
     * @throws ApiError
     */
    public function addGroup(string $groupIdentifier): ResourceActionGrant
    {
        return $this->internalResourceActionGrantService->addResourceActionGrantByResourceClassAndIdentifier(
            self::GROUP_RESOURCE_CLASS, $groupIdentifier,
            self::MANAGE_ACTION, $this->getUserIdentifier());
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
        $GROUP_ALIAS = 'g';
        $AUTHORIZATION_RESOURCE_ALIAS = 'arm';
        $RESOURCE_ACTION_GRANT_ALIAS = 'rag';
        $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS = 'arca';

        $queryBuilder = $this->internalResourceActionGrantService->getEntityManager()->createQueryBuilder();
        $queryBuilder->select($GROUP_ALIAS)
            ->from(Group::class, $GROUP_ALIAS)
            ->innerJoin(AuthorizationResource::class, $AUTHORIZATION_RESOURCE_ALIAS, Join::WITH,
                "UNHEX(REPLACE($AUTHORIZATION_RESOURCE_ALIAS.resourceIdentifier, '-', '')) = $GROUP_ALIAS.identifier"
            )
            ->innerJoin(ResourceActionGrant::class, $RESOURCE_ACTION_GRANT_ALIAS, Join::WITH,
                "$RESOURCE_ACTION_GRANT_ALIAS.authorizationResource = $AUTHORIZATION_RESOURCE_ALIAS.identifier"
            )
            ->innerJoin(AvailableResourceClassAction::class, $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS, Join::WITH,
                "$RESOURCE_ACTION_GRANT_ALIAS.availableResourceClassAction = $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.identifier"
            )
            ->andWhere("$AUTHORIZATION_RESOURCE_ALIAS.resourceClass = :resourceClass")
            ->setParameter(':resourceClass', self::GROUP_RESOURCE_CLASS)
            ->andWhere("$AUTHORIZATION_RESOURCE_ALIAS.resourceIdentifier IS NOT NULL") // group items only
            ->andWhere($queryBuilder->expr()->in(
                "$AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.action",
                [self::MANAGE_ACTION, self::READ_GROUP_ACTION]));

        if ($groupNameLike = $filters[self::GROUP_SEARCH_FILTER] ?? null) {
            $queryBuilder
                ->andWhere("$GROUP_ALIAS.name LIKE :groupNameLike")
                ->setParameter(':groupNameLike', "%{$groupNameLike}%");
        }
        if ($getChildGroupCandidatesForGroupIdentifierFilter =
            $filters[self::GET_CHILD_GROUP_CANDIDATES_FOR_GROUP_IDENTIFIER_FILTER] ?? null) {
            $binaryChildGroupCandidateIdentifiers = $this->groupService->getDisallowedChildGroupIdentifiersBinaryFor(
                $getChildGroupCandidatesForGroupIdentifierFilter);
            $queryBuilder
                ->andWhere($queryBuilder->expr()->notIn(
                    "$GROUP_ALIAS.identifier", ':getChildGroupCandidatesForGroupIdentifierFilter'))
                ->setParameter(':getChildGroupCandidatesForGroupIdentifierFilter',
                    $binaryChildGroupCandidateIdentifiers, ArrayParameterType::BINARY);
        }

        $userIdentifier = $this->getUserIdentifier();
        self::addGrantHolderCriteria($queryBuilder, $RESOURCE_ACTION_GRANT_ALIAS,
            $userIdentifier !== null ? $userIdentifier : InternalResourceActionGrantService::FALSE,
            $userIdentifier !== null ?
                $this->groupService->getGroupsUserIsMemberOf($userIdentifier) : [],
            $this->getDynamicGroupsCurrentUserIsMemberOf());

        try {
            return $queryBuilder->getQuery()
                ->setFirstResult($firstResultIndex)
                ->setMaxResults($maxNumResults)
                ->execute();
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to get groups: '.$throwable->getMessage(), ['exception' => $throwable]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to get groups!');
        }
    }

    public function isCurrentUserAuthorizedToAddGroups(): bool
    {
        return $this->isCurrentUserGranted(self::GROUP_RESOURCE_CLASS,
            self::COLLECTION_RESOURCE_IDENTIFIER, self::CREATE_GROUPS_ACTION);
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
        foreach ($this->getResourceActionGrantsCurrentUserHolds(
            authorizationResourceIdentifier: $resourceActionGrant->getAuthorizationResource()->getIdentifier()) as $currentUsersResourceActionGrant) {
            if ($currentUsersResourceActionGrant->getAction() === self::MANAGE_ACTION
                || ($currentUsersResourceActionGrant === $resourceActionGrant->getShareOf()
                    && $currentUsersResourceActionGrant->getShareable()
                    && $currentUsersResourceActionGrant->getAction() === $resourceActionGrant->getAction())) {
                return true;
            }
        }

        return false;
    }

    public function isCurrentUserAuthorizedToRemoveGrant(ResourceActionGrant $resourceActionGrant): bool
    {
        foreach ($this->getResourceActionGrantsCurrentUserHolds(
            authorizationResourceIdentifier: $resourceActionGrant->getAuthorizationResource()->getIdentifier()) as $currentUsersResourceActionGrant) {
            if ($currentUsersResourceActionGrant->getAction() === self::MANAGE_ACTION
                || ($currentUsersResourceActionGrant === $resourceActionGrant->getShareOf()
                    && $currentUsersResourceActionGrant->getAction() === $resourceActionGrant->getAction())) {
                return true;
            }
        }

        return false;
    }

    public function isCurrentUserAuthorizedToReadGrant(ResourceActionGrant $resourceActionGrant): bool
    {
        return $this->doesCurrentUserHold($resourceActionGrant)
            || $this->doesCurrentUserHaveAManageGrantForAuthorizationResource(
                $resourceActionGrant->getAuthorizationResource()->getIdentifier());
    }

    /**
     * @return string[]
     */
    public function getResourceClassesCurrentUserIsAuthorizedToRead(
        int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        return $this->getResourceActionGrantsCurrentUserIsAuthorizedToReadInternal(
            InternalResourceActionGrantService::GET_RESOURCE_CLASSES,
            null, null, $firstResultIndex, $maxNumResults);
    }

    /**
     * @param string|null $resourceClass      null matches any resource class
     * @param string|null $resourceIdentifier null matches any resource identifier
     *
     * @return AuthorizationResource[]
     */
    public function getAuthorizationResourcesCurrentUserIsAuthorizedToRead(
        ?string $resourceClass = null, ?string $resourceIdentifier = null,
        int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        return $this->getResourceActionGrantsCurrentUserIsAuthorizedToReadInternal(
            InternalResourceActionGrantService::GET_AUTHORIZATION_RESOURCES,
            $resourceClass, $resourceIdentifier, $firstResultIndex, $maxNumResults);
    }

    /**
     * @throws ApiError
     */
    public function getResourceActionGrantByIdentifier(string $identifier): ?ResourceActionGrant
    {
        $resourceActionGrant = $this->internalResourceActionGrantService->getResourceActionGrantByIdentifier($identifier);
        if ($resourceActionGrant !== null) {
            $resourceActionGrant->setGrantedActions(
                $this->isCurrentUserAuthorizedToRemoveGrant($resourceActionGrant) ? ['delete'] : []
            );
        }

        return $resourceActionGrant;
    }

    /**
     * @param string|null $resourceIdentifier null matches any resource identifier
     *
     * @return ResourceActionGrant[]
     */
    public function getResourceActionGrantsCurrentUserIsAuthorizedToRead(
        ?string $resourceClass = null, ?string $resourceIdentifier = null,
        int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        return $this->getResourceActionGrantsCurrentUserIsAuthorizedToReadInternal(InternalResourceActionGrantService::GET_RESOURCE_ACTION_GRANTS,
            $resourceClass, $resourceIdentifier, $firstResultIndex, $maxNumResults);
    }

    /**
     * Updates the manage resource collection policy grants in the grants table according to the current bundle configuration.
     * It ensures that there is one manage grant per resource class defined in the config, whose grant holders are
     * defined by the Configuration::MANAGE_RESOURCE_COLLECTION_POLICY.
     */
    public function updateManageResourceCollectionPolicyGrants(): void
    {
        $manageResourceCollectionPolicyNames = [];
        foreach ($this->config[Configuration::RESOURCE_CLASSES] ?? [] as $resourceClassConfig) {
            $manageResourceCollectionPolicyNames[] =
                self::toManageResourceCollectionPolicyName($resourceClassConfig[Configuration::IDENTIFIER]);
        }
        $manageResourceCollectionPolicyNames[] =
            self::toManageResourceCollectionPolicyName(self::GROUP_RESOURCE_CLASS);

        $lastResourceClass = null;
        $resourceClassGrantsMap = [];
        foreach ($this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            null, self::COLLECTION_RESOURCE_IDENTIFIER) as $resourceActionGrant) {
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
                if (false === $isPolicyPresentInConfig) {
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
                // the policy grant is not present in DB (however, the authorization resource is)
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
                self::toResourceClass($manageResourceCollectionPolicyName), self::COLLECTION_RESOURCE_IDENTIFIER,
                self::MANAGE_ACTION, null, null, $manageResourceCollectionPolicyName);
        }
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
        return false === str_starts_with($dynamicGroupIdentifier, self::MANAGE_RESOURCE_COLLECTION_POLICY_PREFIX);
    }

    /**
     * @param string|null $resourceIdentifier null matches any resource identifier
     *
     * @return ResourceActionGrant[]|AuthorizationResource[]|string[]
     *
     * @throws ApiError
     */
    private function getResourceActionGrantsCurrentUserIsAuthorizedToReadInternal(string $get = InternalResourceActionGrantService::GET_RESOURCE_ACTION_GRANTS,
        ?string $resourceClass = null, ?string $resourceIdentifier = null,
        int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        $AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS =
            InternalResourceActionGrantService::AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS;

        $userIdentifier = $this->getUserIdentifier() !== null ? $this->getUserIdentifier() : InternalResourceActionGrantService::FALSE;
        $groupIdentifiers = $this->getUserIdentifier() !== null ? $this->groupService->getGroupsUserIsMemberOf($userIdentifier) : [];
        $dynamicGroupIdentifiers = $this->getDynamicGroupsCurrentUserIsMemberOf();

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

            foreach ($managedAuthorizationResourceIdentifiersBinary as $binaryIdentifier) {
                $managedAuthorizationResourceIdentifiers[UuidUtils::toStringUuid($binaryIdentifier)] = true;
            }

            $results = $this->internalResourceActionGrantService->get($get,
                $resourceClass, $resourceIdentifier, null, null,
                $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers,
                $firstResultIndex, $maxNumResults, $options);

            switch ($get) {
                case InternalResourceActionGrantService::GET_RESOURCE_ACTION_GRANTS:
                    /** @var ResourceActionGrant $resourceActionGrant */
                    foreach ($results as $resourceActionGrant) {
                        // if the current user manages the grant's resource and the grant is not inherited, they may delete it
                        $resourceActionGrant->setGrantedActions(
                            isset($managedAuthorizationResourceIdentifiers[$resourceActionGrant->getAuthorizationResourceIdentifier()])
                            && false === $resourceActionGrant->isInherited() ? ['delete'] : []);
                    }
                    break;

                case InternalResourceActionGrantService::GET_AUTHORIZATION_RESOURCES:
                    /** @var AuthorizationResource $authorizationResource */
                    foreach ($results as $authorizationResource) {
                        $isCurrentUserResourceManager =
                            isset($managedAuthorizationResourceIdentifiers[$authorizationResource->getIdentifier()]);
                        $authorizationResource->setGrantedActions($isCurrentUserResourceManager ? ['add_grants'] : []);

                        /** @var ResourceActionGrant $resourceActionGrant */
                        foreach ($authorizationResource->getResourceActionGrants() as $resourceActionGrant) {
                            // if the current user manages the grant's resource and the grant is not inherited, they may delete it
                            $resourceActionGrant->setGrantedActions(
                                $isCurrentUserResourceManager && false === $resourceActionGrant->isInherited() ?
                                    ['delete'] : []);
                        }
                    }
                    break;

                case InternalResourceActionGrantService::GET_RESOURCE_CLASSES:
                    // nothing to do
                    break;

                default:
                    throw new \RuntimeException(sprintf('Unexpected get type "%s"', $get));
            }

            return $results;
        } catch (\Exception $e) {
            dump($e);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to get resource action grant collection!',
                InternalResourceActionGrantService::GETTING_RESOURCE_ACTION_GRANT_COLLECTION_FAILED_ERROR_ID,
                ['message' => $e->getMessage()]);
        }
    }

    private function doesCurrentUserHaveAManageGrantForAuthorizationResource(
        string $authorizationResourceIdentifier): bool
    {
        return in_array(AuthorizationService::MANAGE_ACTION,
            $this->getGrantedResourceActionsForCurrentUserInternal(
                authorizationResourceIdentifier: $authorizationResourceIdentifier), true);
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
     * @return ResourceActionGrant[]
     */
    private function getResourceActionGrantsCurrentUserHolds(?string $resourceClass = null, ?string $resourceIdentifier = null,
        ?string $authorizationResourceIdentifier = null): array
    {
        $currentUserIdentifier = $this->getUserIdentifier();

        return $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            $resourceClass, $resourceIdentifier, $authorizationResourceIdentifier,
            $currentUserIdentifier ?: InternalResourceActionGrantService::FALSE,
            $currentUserIdentifier !== null ? $this->groupService->getGroupsUserIsMemberOf($currentUserIdentifier) : [],
            $this->getDynamicGroupsCurrentUserIsMemberOf());
    }

    /**
     * @return string[]
     */
    private function getGrantedResourceActionsForCurrentUserInternal(?string $resourceClass = null, ?string $resourceIdentifier = null,
        ?string $authorizationResourceIdentifier = null): array
    {
        $currentUserIdentifier = $this->getUserIdentifier();

        return $this->internalResourceActionGrantService->getGrantedActionsForResource(
            $resourceClass, $resourceIdentifier, $authorizationResourceIdentifier,
            $currentUserIdentifier ?: InternalResourceActionGrantService::FALSE,
            $currentUserIdentifier !== null ? $this->groupService->getGroupsUserIsMemberOf($currentUserIdentifier) : [],
            $this->getDynamicGroupsCurrentUserIsMemberOf()
        );
    }

    /**
     * @throws ApiError
     */
    private function doesCurrentUserHold(ResourceActionGrant $resourceActionGrant): bool
    {
        $userIdentifier = $this->getUserIdentifier();

        return ($userIdentifier !== null
                && ($resourceActionGrant->getUserIdentifier() === $userIdentifier
                    || ($resourceActionGrant->getGroup() !== null
                        && $this->groupService->isUserMemberOfGroup($userIdentifier, $resourceActionGrant->getGroup()->getIdentifier()))))
            || ($resourceActionGrant->getDynamicGroupIdentifier() !== null
                && $this->isCurrentUserMemberOfDynamicGroup($resourceActionGrant->getDynamicGroupIdentifier()));
    }

    private function configure(): void
    {
        $policies = [];
        foreach ($this->config[Configuration::RESOURCE_CLASSES] ?? [] as $resourceClassConfig) {
            $policies[self::toManageResourceCollectionPolicyName($resourceClassConfig[Configuration::IDENTIFIER])] =
                $resourceClassConfig[Configuration::MANAGE_RESOURCE_COLLECTION_POLICY];
        }
        $policies[self::toManageResourceCollectionPolicyName(self::GROUP_RESOURCE_CLASS)] =
            $this->config[Configuration::CREATE_GROUPS_POLICY];

        foreach ($this->config[Configuration::DYNAMIC_GROUPS] ?? [] as $dynamicGroup) {
            $policies[self::toIsCurrentUserMemberOfDynamicGroupPolicyName($dynamicGroup[Configuration::IDENTIFIER])] =
                $dynamicGroup[Configuration::IS_CURRENT_USER_GROUP_MEMBER_EXPRESSION];
        }
        $policies[self::toIsCurrentUserMemberOfDynamicGroupPolicyName(
            self::DYNAMIC_GROUP_IDENTIFIER_EVERYBODY)] = 'true';

        $this->setUpAccessControlPolicies($policies);
    }

    private static function addGrantHolderCriteria(QueryBuilder $queryBuilder, string $RESOURCE_ACTION_GRANT_ALIAS,
        ?string $userIdentifier, ?array $groupIdentifiers, ?array $dynamicGroupIdentifiers): void
    {
        $orClause = $queryBuilder->expr()->orX();
        if ($userIdentifier !== null) {
            if ($userIdentifier === InternalResourceActionGrantService::FALSE) {
                $orClause
                    ->add('false');
            } else {
                $orClause
                    ->add($queryBuilder->expr()->eq("$RESOURCE_ACTION_GRANT_ALIAS.userIdentifier", ':userIdentifier'));
                $queryBuilder->setParameter(':userIdentifier', $userIdentifier);
            }
        }
        if ($groupIdentifiers !== null) {
            $orClause
                ->add($queryBuilder->expr()->in("IDENTITY($RESOURCE_ACTION_GRANT_ALIAS.group)", ':groupIdentifiers'));
            $queryBuilder->setParameter(':groupIdentifiers',
                UuidUtils::toBinaryUuids($groupIdentifiers), ArrayParameterType::BINARY);
        }
        if ($dynamicGroupIdentifiers !== null) {
            $orClause
                ->add($queryBuilder->expr()->in("$RESOURCE_ACTION_GRANT_ALIAS.dynamicGroupIdentifier", ':dynamicGroupIdentifiers'));
            $queryBuilder->setParameter(':dynamicGroupIdentifiers', $dynamicGroupIdentifiers);
        }
        if ($orClause->count() > 0) {
            $queryBuilder->andWhere($orClause);
        }
    }

    public function addRole(array $localizedRoleNames, array $roleActions): Role
    {
        return $this->internalResourceActionGrantService->addRole($localizedRoleNames, $roleActions);
    }
}
