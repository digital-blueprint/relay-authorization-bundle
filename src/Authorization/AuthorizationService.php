<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Authorization;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\AuthorizationBundle\Entity\GrantedActions;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Entity\Role;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroup;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroupMember;
use Dbp\Relay\AuthorizationBundle\Helper\UuidUtils;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Service\UserGroupService;
use Dbp\Relay\CoreBundle\Authorization\AbstractAuthorizationService;
use Dbp\Relay\CoreBundle\Authorization\AuthorizationException;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
class AuthorizationService extends AbstractAuthorizationService implements LoggerAwareInterface, ResetInterface
{
    use LoggerAwareTrait;

    public const OWN_ACTION = 'own';

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
            'de' => 'Mitglieder*innen hinzufügen',
        ],
        self::DELETE_GROUP_MEMBERS_GROUP_ACTION => [
            'en' => 'Remove members',
            'de' => 'Mitglieder*innen entfernen',
        ],
    ];
    public const GROUP_COLLECTION_ACTIONS = [
        self::CREATE_GROUPS_ACTION => [
            'en' => 'Create',
            'de' => 'Erstellen',
        ],
    ];

    public const MAX_NUM_RESULTS_DEFAULT = InternalResourceActionGrantService::MAX_NUM_RESULTS_DEFAULT;
    public const GROUP_SEARCH_FILTER = 'search';
    public const GET_CHILD_GROUP_CANDIDATES_FOR_GROUP_IDENTIFIER_FILTER = 'getChildGroupCandidatesForGroupIdentifier';
    public const DYNAMIC_GROUP_IDENTIFIER_EVERYBODY = 'everybody';
    public const RESOURCE_COLLECTION_OWNER_POLICY_PREFIX = '@';
    public const COLLECTION_RESOURCE_IDENTIFIER = InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER;

    public const RESOURCE_RESOURCE_TYPE = InternalResourceActionGrantService::RESOURCE_RESOURCE_TYPE;
    public const RESOURCE_GROUP_RESOURCE_TYPE = InternalResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE;

    public const OWNER_ROLE_IDENTIFIER = '019f7e71-f6d3-7589-916c-ead4b141f5ba';
    public const OWN_ITEM_ACTION_UUID = '019ecac3-2eea-7297-ae1a-486de6fca628';
    public const OWN_COLLECTION_ACTION_UUID = '019ecac3-d095-7ae7-b10b-30bff78040a7';

    private const OWN_ACTION_NAMES = [
        'en' => 'Own',
        'de' => 'Eigentümer*in sein',
    ];

    private const OWNER_ROLE_NAMES = [
        'en' => 'Owner',
        'de' => 'Eigentümer*in',
    ];

    private ?array $config = null;

    /**
     * Adds or updates builtin actions and roles (owner and user group actions/roles).
     *
     * @throws \Throwable
     */
    public static function addOrUpdateActionsAndRoles(EntityManagerInterface $entityManager, ?LoggerInterface $logger = null): void
    {
        /**
         * Owner actions and roles.
         */
        InternalResourceActionGrantService::addOrUpdateAvailableResourceClassActionStatic(
            $entityManager,
            null,
            self::OWN_ACTION,
            AvailableResourceClassAction::ITEM_ACTION_TYPE,
            self::OWN_ACTION_NAMES,
            identifier: self::OWN_ITEM_ACTION_UUID,
            logger: $logger
        );
        InternalResourceActionGrantService::addOrUpdateAvailableResourceClassActionStatic(
            $entityManager,
            null,
            self::OWN_ACTION,
            AvailableResourceClassAction::COLLECTION_ACTION_TYPE,
            self::OWN_ACTION_NAMES,
            identifier: self::OWN_COLLECTION_ACTION_UUID,
            logger: $logger
        );
        InternalResourceActionGrantService::addOrUpdateRoleStatic($entityManager,
            self::OWNER_ROLE_NAMES,
            [
                // works for both item and collection resources:
                ResourceActionGrantService::createRoleAction(
                    null, ResourceActionGrantService::OWN_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    null, ResourceActionGrantService::OWN_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE),
            ],
            identifier: ResourceActionGrantService::OWNER_ROLE_IDENTIFIER
        );

        /**
         * User group actions and roles.
         */
        InternalResourceActionGrantService::addOrUpdateAvailableResourceClassActionsStatic($entityManager,
            AuthorizationService::GROUP_RESOURCE_CLASS,
            AuthorizationService::GROUP_ITEM_ACTIONS,
            AuthorizationService::GROUP_COLLECTION_ACTIONS);

        InternalResourceActionGrantService::addOrUpdateRoleStatic(
            $entityManager,
            [
                'en' => 'Reader',
                'de' => 'Leser*in',
            ],
            [
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::GROUP_RESOURCE_CLASS, AuthorizationService::READ_GROUP_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
            ],
        );
        InternalResourceActionGrantService::addOrUpdateRoleStatic(
            $entityManager,
            [
                'en' => 'Editor',
                'de' => 'Bearbeiter*in',
            ],
            [
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::GROUP_RESOURCE_CLASS, AuthorizationService::READ_GROUP_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::GROUP_RESOURCE_CLASS, AuthorizationService::UPDATE_GROUP_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::GROUP_RESOURCE_CLASS, AuthorizationService::ADD_GROUP_MEMBERS_GROUP_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::GROUP_RESOURCE_CLASS, AuthorizationService::DELETE_GROUP_MEMBERS_GROUP_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
            ],
        );
        InternalResourceActionGrantService::addOrUpdateRoleStatic(
            $entityManager,
            [
                'en' => 'Manager',
                'de' => 'Manager*in',
            ],
            [
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::GROUP_RESOURCE_CLASS, AuthorizationService::READ_GROUP_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::GROUP_RESOURCE_CLASS, AuthorizationService::UPDATE_GROUP_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::GROUP_RESOURCE_CLASS, AuthorizationService::DELETE_GROUP_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::GROUP_RESOURCE_CLASS, AuthorizationService::ADD_GROUP_MEMBERS_GROUP_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::GROUP_RESOURCE_CLASS, AuthorizationService::DELETE_GROUP_MEMBERS_GROUP_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
            ],
        );
        InternalResourceActionGrantService::addOrUpdateRoleStatic(
            $entityManager,
            [
                'en' => 'Creator',
                'de' => 'Ersteller*in',
            ],
            [
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::GROUP_RESOURCE_CLASS, AuthorizationService::CREATE_GROUPS_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE),
            ],
        );
    }

    public function __construct(
        private readonly InternalResourceActionGrantService $internalResourceActionGrantService,
        private readonly UserGroupService $userGroupService,
        private bool $debug = false)
    {
        parent::__construct();
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->internalResourceActionGrantService->getEntityManager();
    }

    public function getUserGroupService(): UserGroupService
    {
        return $this->userGroupService;
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
    public function addOrUpdateAvailableResourceClassActions(string $resourceClass,
        array $itemActions, array $collectionActions): array
    {
        return $this->internalResourceActionGrantService->addOrUpdateAvailableResourceClassActions(
            $resourceClass, $itemActions, $collectionActions);
    }

    /**
     * @throws ApiError
     */
    public function addOrUpdateRole(array $localizedRoleNames, array $roleActions, ?string $identifier = null): Role
    {
        return $this->internalResourceActionGrantService->addOrUpdateRole($localizedRoleNames, $roleActions, $identifier);
    }

    /**
     * @throws ApiError
     */
    public function isCurrentUserMemberOfDynamicGroup(string $dynamicUserGroupIdentifier): bool
    {
        try {
            return $this->isGrantedRole(self::toIsCurrentUserMemberOfDynamicGroupPolicyName($dynamicUserGroupIdentifier));
        } catch (\Throwable $throwable) {
            if ($throwable instanceof AuthorizationException
                && $throwable->getCode() === AuthorizationException::ROLE_UNDEFINED) {
                throw new \RuntimeException('Dynamic user group is undefined: '.$dynamicUserGroupIdentifier);
            }
            throw new \RuntimeException('Error checking if current user is member of dynamic group: '.
                $dynamicUserGroupIdentifier, previous: $throwable);
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
        // TODO: find a better criteria to determine whether the current user is authorized to read a dynamic group
        // maybe add another policy to the dynamic group configuration that defines whether the current user
        // is authorized to read/use the dynamic group
        return array_filter($this->getRoleNames(), function ($policyName) {
            return self::isCurrentUserMemberOfDynamicGroupPolicyName($policyName);
        });
    }

    /**
     * @throws ApiError
     */
    public function addUserGroupMemberIfGranted(UserGroupMember $userGroupMember): UserGroupMember
    {
        $this->assertIsGrantedUserGroupAction(
            $userGroupMember->getUserGroup(),
            self::ADD_GROUP_MEMBERS_GROUP_ACTION
        );

        return $this->userGroupService->addUserGroupMember($userGroupMember); // takes care of validation
    }

    /**
     * @throws ApiError
     */
    public function removeUserGroupMemberIfGranted(UserGroupMember $userGroupMember): void
    {
        $this->assertIsGrantedUserGroupAction(
            $userGroupMember->getUserGroup(),
            self::DELETE_GROUP_MEMBERS_GROUP_ACTION
        );

        $this->userGroupService->removeUserGroupMember($userGroupMember);
    }

    /**
     * @throws ApiError
     */
    public function getUserGroupMemberByIdentifierIfGranted(string $identifier): UserGroupMember
    {
        $userGroupMember = $this->userGroupService->getUserGroupMemberByIdentifier($identifier);
        if (false === $this->isCurrentUserAuthorizedToReadGroup($userGroupMember->getUserGroup())) {
            // don't distinguish between not found and not being granted
            // to avoid leaking information about the existence of the group member
            $this->userGroupService->throwUserGroupMemberNotFound($identifier);
        }

        return $userGroupMember;
    }

    /**
     * @throws ApiError|\Throwable
     */
    public function addUserGroupIfGranted(UserGroup $userGroup): UserGroup
    {
        if (false === $this->isCurrentUserGranted(self::GROUP_RESOURCE_CLASS,
            self::COLLECTION_RESOURCE_IDENTIFIER, self::CREATE_GROUPS_ACTION)) {
            throw new ApiError(Response::HTTP_FORBIDDEN);
        }

        $connection = $this->getEntityManager()->getConnection();
        $connection->beginTransaction();

        try {
            $userGroup = $this->userGroupService->addUserGroup($userGroup);

            $this->addResourceActionGrantByResourceClassAndIdentifier(
                self::GROUP_RESOURCE_CLASS, $userGroup->getIdentifier(),
                action: self::OWN_ACTION,
                userIdentifier: $this->getUserIdentifier()
            );

            $connection->commit();
        } catch (\Throwable $throwable) {
            try {
                $connection->rollBack(); // prevent unaccessible user group
            } catch (\Throwable) {
                // ignore rollback errors
            }

            throw $throwable;
        }

        return $userGroup;
    }

    /**
     * @throws ApiError
     */
    public function updateUserGroupIfGranted(UserGroup $userGroup): UserGroup
    {
        $this->assertIsGrantedUserGroupAction($userGroup, self::UPDATE_GROUP_ACTION);

        return $this->userGroupService->updateUserGroup($userGroup);
    }

    /**
     * @throws ApiError|\Throwable
     */
    public function removeUserGroupIfGranted(UserGroup $userGroup): void
    {
        $this->assertIsGrantedUserGroupAction($userGroup, self::DELETE_GROUP_ACTION);

        $connection = $this->getEntityManager()->getConnection();
        $connection->beginTransaction();

        try {
            $this->userGroupService->removeUserGroup($userGroup);

            $this->internalResourceActionGrantService->removeAuthorizationResourcesByResourceClassAndIdentifier(
                self::GROUP_RESOURCE_CLASS, $userGroup->getIdentifier());

            $connection->commit();
        } catch (\Throwable $throwable) {
            try {
                $connection->rollBack(); // avoid orphaned user group grants
            } catch (\Throwable) {
                // ignore rollback errors
            }

            throw $throwable;
        }
    }

    /**
     * @throws ApiError
     */
    public function getUserGroupByIdentifierIfGranted(string $identifier): UserGroup
    {
        $userGroup = $this->userGroupService->getUserGroupByIdentifier($identifier);
        if (false === $this->isCurrentUserAuthorizedToReadGroup($userGroup)) {
            // don't distinguish between not found and not being granted
            // to avoid leaking information about the existence of the group
            $this->userGroupService->throwUserGroupNotFound($identifier);
        }

        return $userGroup;
    }

    /**
     * @return UserGroup[]
     */
    public function getUserGroupsCurrentUserIsAuthorizedToRead(int $firstResultIndex, int $maxNumResults, array $filters = []): array
    {
        $GROUP_ALIAS = 'ug';
        $AUTHORIZATION_RESOURCE_ALIAS = 'arm';
        $RESOURCE_ACTION_GRANT_ALIAS = 'rag';
        $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS = 'arca';

        try {
            $queryBuilder = $this->internalResourceActionGrantService->getEntityManager()->createQueryBuilder();
            $queryBuilder->select($GROUP_ALIAS)
                ->from(UserGroup::class, $GROUP_ALIAS)
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
                    [self::OWN_ACTION, self::READ_GROUP_ACTION]));

            if ($groupNameLike = $filters[self::GROUP_SEARCH_FILTER] ?? null) {
                $queryBuilder
                    ->andWhere("$GROUP_ALIAS.name LIKE :groupNameLike")
                    ->setParameter(':groupNameLike', "%{$groupNameLike}%");
            }
            if ($getChildGroupCandidatesForGroupIdentifierFilter =
                $filters[self::GET_CHILD_GROUP_CANDIDATES_FOR_GROUP_IDENTIFIER_FILTER] ?? null) {
                $binaryChildGroupCandidateIdentifiers = $this->userGroupService->getDisallowedChildGroupIdentifiersBinaryFor(
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
                    $this->userGroupService->getUserGroupsUserIsMemberOf($userIdentifier) : [],
                $this->getDynamicGroupsCurrentUserIsMemberOf());

            return $queryBuilder->getQuery()
                ->setFirstResult($firstResultIndex)
                ->setMaxResults($maxNumResults)
                ->execute();
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to get groups: '.$throwable->getMessage(), ['exception' => $throwable]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'failed to get user groups',
                UserGroupService::GETTING_USER_GROUP_COLLECTION_FAILED_ERROR_ID);
        }
    }

    /**
     * NOTE: as opposed to addResourceActionGrantIfGranted(), this method does not check if the current user is authorized to add the grant.
     *
     * @throws ApiError
     */
    public function addResourceActionGrantByResourceClassAndIdentifier(
        string $resourceClass,
        string $resourceIdentifier,
        int $resourceType = self::RESOURCE_RESOURCE_TYPE,
        ?string $action = null,
        ?string $roleIdentifier = null,
        ?string $userIdentifier = null,
        ?string $userGroupIdentifier = null,
        ?string $dynamicUserGroupIdentifier = null,
        ?bool $shareable = null): ResourceActionGrant
    {
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass($resourceClass);
        $resourceActionGrant->setResourceIdentifier($resourceIdentifier);
        $resourceActionGrant->setResourceType($resourceType);

        $resourceActionGrant->setAction($action);
        $resourceActionGrant->setRole($roleIdentifier !== null ?
            $this->getRoleByIdentifier($roleIdentifier) : null);
        $resourceActionGrant->setUserIdentifier($userIdentifier);
        $resourceActionGrant->setUserGroup(null !== $userGroupIdentifier ?
            $this->userGroupService->getUserGroupByIdentifier($userGroupIdentifier) : null);
        if (null !== $dynamicUserGroupIdentifier) {
            $this->validateDynamicUserGroup($dynamicUserGroupIdentifier);
            $resourceActionGrant->setDynamicUserGroupIdentifier($dynamicUserGroupIdentifier);
        }
        if (null !== $shareable) {
            $resourceActionGrant->setShareable($shareable);
        }
        $resourceActionGrant->setCreatorId($this->getUserIdentifier());

        return $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
    }

    public function addResourceActionGrantIfGranted(ResourceActionGrant $resourceActionGrant): ResourceActionGrant
    {
        $resourceClass = $resourceActionGrant->getResourceClass();
        $resourceIdentifier = $resourceActionGrant->getResourceIdentifier();
        $resourceType = $resourceActionGrant->getResourceType();

        if (null !== $resourceActionGrant->getDynamicUserGroupIdentifier()) {
            $this->validateDynamicUserGroup($resourceActionGrant->getDynamicUserGroupIdentifier());
        }

        if (null === $resourceClass) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'resourceClass is required',
                InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ERROR_ID);
        }
        if (null === $resourceIdentifier) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'resourceIdentifier is required',
                InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ERROR_ID);
        }

        $authorizationResource =
            $this->internalResourceActionGrantService->getAuthorizationResourceByResourceClassAndIdentifier(
                $resourceClass,
                $resourceIdentifier,
                $resourceType
            );

        if (null !== $authorizationResource) {
            $resourceActionGrant->setAuthorizationResource($authorizationResource);

            if (false === $this->isCurrentUserAuthorizedToAddGrant(
                $resourceActionGrant,
                $this->isCurrentUserResourceOwner($resourceActionGrant)
            )
            ) {
                throw new ApiError(Response::HTTP_FORBIDDEN);
            }
        } else {
            // authorization resource does not exist yet -> check if the user is allowed to create it
            $authorizationResource = new AuthorizationResource();
            $authorizationResource->setResourceClass($resourceClass);
            $authorizationResource->setResourceIdentifier($resourceIdentifier);
            $authorizationResource->setResourceType($resourceType);

            if (false === $this->isResourcePermissionDefined($resourceClass)
                || false === $this->isGrantedResourcePermission($resourceClass, $authorizationResource)
            ) {
                throw new ApiError(Response::HTTP_FORBIDDEN);
            }

            // ensure that the first grant is an owner grant
            if (self::OWN_ACTION !== $resourceActionGrant->getAction()
                && self::OWNER_ROLE_IDENTIFIER !== $resourceActionGrant->getRole()?->getIdentifier()
                && null === $resourceActionGrant->getShareOf()) {
                throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                    'first grant needs to be an owner grant',
                    InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ERROR_ID);
            }
        }

        $resourceActionGrant->setCreatorId($this->getUserIdentifier());

        return $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
    }

    /**
     * @throws ApiError
     */
    public function removeResourceActionGrantIfGranted(ResourceActionGrant $resourceActionGrant): void
    {
        if (false === $this->isCurrentUserAuthorizedToRemoveGrant(
            $resourceActionGrant,
            $this->isCurrentUserResourceOwner($resourceActionGrant))) {
            throw new ApiError(Response::HTTP_FORBIDDEN);
        }

        $this->internalResourceActionGrantService->removeResourceActionGrant($resourceActionGrant);
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
    public function removeGrantsForResource(
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?int $resourceType = self::RESOURCE_RESOURCE_TYPE): void
    {
        $this->internalResourceActionGrantService->removeGrantsByResourceClassAndIdentifier(
            $resourceClass, $resourceIdentifier, $resourceType);
    }

    /**
     * @throws ApiError
     */
    public function removeResource(
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?int $resourceType = self::RESOURCE_RESOURCE_TYPE): void
    {
        $this->internalResourceActionGrantService->removeAuthorizationResourcesByResourceClassAndIdentifier(
            $resourceClass, $resourceIdentifier, $resourceType);
    }

    /**
     * @throws ApiError
     */
    public function removeResources(
        ?string $resourceClass, array $resourceIdentifiers, ?int $resourceType = self::RESOURCE_RESOURCE_TYPE): void
    {
        $this->internalResourceActionGrantService->removeAuthorizationResourcesByResourceClassAndIdentifier(
            $resourceClass, $resourceIdentifiers, $resourceType);
    }

    public function addResourceToGroupResource(string $resourceClass, string $resourceGroupResourceIdentifier,
        string $resourceIdentifier, int $resourceType = self::RESOURCE_RESOURCE_TYPE): void
    {
        $this->internalResourceActionGrantService->addResourceToResourceGroup(
            $resourceClass, $resourceGroupResourceIdentifier,
            $resourceIdentifier, $resourceType);
    }

    public function removeResourceFromGroupResource(string $resourceClass, string $resourceGroupResourceIdentifier,
        string $resourceIdentifier, int $resourceType = self::RESOURCE_RESOURCE_TYPE): void
    {
        $this->internalResourceActionGrantService->removeResourceFromGroupResource(
            $resourceClass, $resourceGroupResourceIdentifier,
            $resourceIdentifier, $resourceType);
    }

    /**
     * @return ResourceActionGrant[]
     *
     * @throws ApiError
     */
    public function getResourceActionGrantsForResourceClassAndIdentifier(
        string $resourceClass, string $resourceIdentifier, int $resourceType = self::RESOURCE_RESOURCE_TYPE,
        ?string $whereIsGrantedAction = null): array
    {
        return $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            $resourceClass, $resourceIdentifier, $resourceType,
            whereIsGrantedAction: $whereIsGrantedAction
        );
    }

    /**
     * @throws ApiError
     */
    public function getGrantedActionsForCurrentUser(string $resourceClass, string $resourceIdentifier,
        int $resourceType = self::RESOURCE_RESOURCE_TYPE): ?GrantedActions
    {
        $currentUserIdentifier = $this->getUserIdentifier();

        return $this->internalResourceActionGrantService->getGrantedActionsForResource(
            $resourceClass, $resourceIdentifier, $resourceType,
            userIdentifier: $currentUserIdentifier ?: InternalResourceActionGrantService::FALSE,
            groupIdentifiers: $currentUserIdentifier !== null ? $this->userGroupService->getUserGroupsUserIsMemberOf($currentUserIdentifier) : [],
            dynamicUserGroupIdentifiers: $this->getDynamicGroupsCurrentUserIsMemberOf(),
            maxNumResults: null
        );
    }

    /**
     * @return GrantedActions[]
     *
     * @throws ApiError
     */
    public function getGrantedActionsCollectionForCurrentUser(
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?int $resourceType = self::RESOURCE_RESOURCE_TYPE,
        ?string $whereIsGrantedAction = null, bool $excludeCollectionResources = true,
        int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        $currentUserIdentifier = $this->getUserIdentifier();

        if ($whereIsGrantedAction === null) {
            $whereActionsContainAnyOf = null;
        } elseif ($whereIsGrantedAction === self::OWN_ACTION) {
            $whereActionsContainAnyOf = [self::OWN_ACTION];
        } else {
            $whereActionsContainAnyOf = [$whereIsGrantedAction, self::OWN_ACTION];
        }

        return $this->internalResourceActionGrantService->getGrantedActionsForResourcePage(
            $resourceClass, $resourceIdentifier, $resourceType,
            whereAuthorizationResourceActionsContainAnyOf: $whereActionsContainAnyOf,
            userIdentifier: $currentUserIdentifier ?: InternalResourceActionGrantService::FALSE,
            groupIdentifiers: $currentUserIdentifier !== null ?
                $this->userGroupService->getUserGroupsUserIsMemberOf($currentUserIdentifier) : [],
            dynamicUserGroupIdentifiers: $this->getDynamicGroupsCurrentUserIsMemberOf(),
            firstResultIndex: $firstResultIndex,
            maxNumResults: $maxNumResults,
            options: [InternalResourceActionGrantService::EXCLUDE_COLLECTION_RESOURCE_OPTION => $excludeCollectionResources]);
    }

    /**
     * @throws ApiError
     */
    public function isCurrentUserGranted(string $resourceClass, string $resourceIdentifier, string $action,
        int $resourceType = self::RESOURCE_RESOURCE_TYPE): bool
    {
        $grantedActions = $this->getGrantedActionsForCurrentUser(
            $resourceClass, $resourceIdentifier, $resourceType)?->getActions() ?? [];

        return in_array($action, $grantedActions, true)
            || (in_array(self::OWN_ACTION, $grantedActions, true)
                && (self::OWN_ACTION === $action
                    || $this->internalResourceActionGrantService->isAvailableResourceClassAction(
                        $resourceClass, $action, $resourceIdentifier)
                )
            );
    }

    /**
     * @return AvailableResourceClassAction[]
     */
    public function getAvailableResourceClassActionsUserMayGrant(
        string $resourceClass, string $resourceIdentifier, int $resourceType = self::RESOURCE_RESOURCE_TYPE,
        int $firstItemIndex = 0, int $maxNumItemsPerPage = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        $grantedActions = $this->getGrantedActionsForCurrentUser(
            $resourceClass, $resourceIdentifier, $resourceType)?->getActions() ?? [];

        return $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            $resourceClass,
            AvailableResourceClassAction::getActionTypeForResourceIdentifier($resourceIdentifier),
            whereActionsIn: in_array(AuthorizationService::OWN_ACTION, $grantedActions, true) ?
                null : $grantedActions,
            firstItemIndex: $firstItemIndex,
            maxNumItemsPerPage: $maxNumItemsPerPage
        );
    }

    /**
     * @throws ApiError
     */
    public function getRoleByIdentifier(string $id): Role
    {
        return $this->internalResourceActionGrantService->getRoleByIdentifier($id);
    }

    public function getRolesCurrentUserMayGrant(
        string $resourceClass, string $resourceIdentifier, int $resourceType = self::RESOURCE_RESOURCE_TYPE,
        int $firstItemIndex = 0, int $maxNumItemsPerPage = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        $grantedActions = $this->getGrantedActionsForCurrentUser(
            $resourceClass, $resourceIdentifier, $resourceType)?->getActions() ?? [];

        return $this->internalResourceActionGrantService->getRoles(
            $resourceClass,
            AvailableResourceClassAction::getActionTypeForResourceIdentifier($resourceIdentifier),
            whereActionsAreASubsetOrEqual: in_array(AuthorizationService::OWN_ACTION, $grantedActions, true) ?
                null : $grantedActions,
            firstItemIndex: $firstItemIndex,
            maxNumItemsPerPage: $maxNumItemsPerPage
        );
    }

    /**
     * @return string[]
     */
    public function getResourceClassesCurrentUserIsAuthorizedToRead(
        int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        return $this->getResourceActionGrantsCurrentUserIsAuthorizedToReadInternal(
            InternalResourceActionGrantService::GET_RESOURCE_CLASSES,
            firstResultIndex: $firstResultIndex,
            maxNumResults: $maxNumResults);
    }

    /**
     * @throws ApiError
     */
    public function getResourceActionGrantByIdentifierIfGranted(string $identifier): ?ResourceActionGrant
    {
        $resourceActionGrant = $this->internalResourceActionGrantService->getResourceActionGrantByIdentifier($identifier);
        if ($resourceActionGrant === null
            || false === $this->isCurrentUserAuthorizedToReadGrant($resourceActionGrant)) {
            // don't distinguish between not found and not being granted
            // to avoid leaking information about the existence of the grant
            return null;
        }

        $this->setGrantedActions($resourceActionGrant);

        return $resourceActionGrant;
    }

    /**
     * @param string|null $resourceIdentifier null matches any resource identifier
     *
     * @return ResourceActionGrant[]
     */
    public function getResourceActionGrantsCurrentUserIsAuthorizedToRead(
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?int $resourceType = null,
        int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        return $this->getResourceActionGrantsCurrentUserIsAuthorizedToReadInternal(
            InternalResourceActionGrantService::GET_RESOURCE_ACTION_GRANTS,
            $resourceClass, $resourceIdentifier, $resourceType,
            firstResultIndex: $firstResultIndex,
            maxNumResults: $maxNumResults);
    }

    /**
     * Updates the resource collection owner policy grants in the grants table according to the current bundle configuration.
     * It ensures that there is one owner grant per resource class defined in the config, whose grant holders are
     * defined by the Configuration::RESOURCE_COLLECTION_OWNER_POLICY.
     */
    public function updateResourceCollectionOwnerPolicyGrants(): void
    {
        $manageResourceCollectionPolicyNames = [];
        foreach ($this->config[Configuration::RESOURCE_COLLECTION_OWNER_POLICIES] ?? [] as $resourceClassConfig) {
            $manageResourceCollectionPolicyNames[] =
                self::toManageResourceCollectionPolicyName($resourceClassConfig[Configuration::RESOURCE_CLASS]);
        }
        $manageResourceCollectionPolicyNames[] =
            self::toManageResourceCollectionPolicyName(self::GROUP_RESOURCE_CLASS);

        $lastResourceClass = null;
        $resourceClassGrantsMap = [];
        foreach ($this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            resourceIdentifier: self::COLLECTION_RESOURCE_IDENTIFIER,
            resourceType: self::RESOURCE_RESOURCE_TYPE) as $resourceActionGrant) {
            $resourceClass = $resourceActionGrant->getResourceClass();
            if ($lastResourceClass !== $resourceClass) {
                $lastResourceClass = $resourceClass;
                $resourceClassGrantsMap[$resourceClass]['other_grants'] = [];
            }
            $dynamicUserGroupIdentifier = $resourceActionGrant->getDynamicUserGroupIdentifier();
            if ($dynamicUserGroupIdentifier !== null
                && self::isManageResourceCollectionPolicyName($dynamicUserGroupIdentifier)) {
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
                // the resource collection owner policy grant is present in the DB
                if (false === $isPolicyPresentInConfig) {
                    // however, the resource collection owner policy is not present in config anymore
                    if (empty($resourceClassGrants['other_grants'])) {
                        // (A) no other grants -> delete the authorization resource from DB
                        $this->internalResourceActionGrantService->removeAuthorizationResourcesByResourceClassAndIdentifier(
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
                } // (C) otherwise we are done, since the resource collection owner policy is still present in config
            } else {
                // the policy grant is not present in DB (however, the authorization resource is)
                if ($isPolicyPresentInConfig) {
                    // (D) the resource collection owner policy is present in config -> auto-add the policy grant to DB
                    $otherGrant = $resourceClassGrants['other_grants'][0];
                    $this->addResourceActionGrantByResourceClassAndIdentifier($otherGrant->getResourceClass(),
                        $otherGrant->getResourceIdentifier(),
                        action: self::OWN_ACTION,
                        dynamicUserGroupIdentifier: $manageResourceCollectionPolicyName
                    );
                } // the resource collection owner policy is not present in config -> nothing to do
            }
        }

        // remaining policies, i.e. policies of resource classes for which no collection grants are present in DB
        foreach ($manageResourceCollectionPolicyNames as $manageResourceCollectionPolicyName) {
            $this->addResourceActionGrantByResourceClassAndIdentifier(
                self::toResourceClass($manageResourceCollectionPolicyName), self::COLLECTION_RESOURCE_IDENTIFIER,
                action: self::OWN_ACTION,
                dynamicUserGroupIdentifier: $manageResourceCollectionPolicyName
            );
        }
    }

    /**
     * @throws ApiError
     */
    private function isCurrentUserAuthorizedToAddGrant(ResourceActionGrant $resourceActionGrant, bool $isResourceOwner): bool
    {
        if ($isResourceOwner) {
            // DESIGN NOTE: resource owners can add arbitrary new grants, but are not allowed to share grants (see isCurrentUserAuthorizedToShareGrant())
            return $resourceActionGrant->getShareOf() === null;
        } elseif (null !== ($grantToShare = $resourceActionGrant->getShareOf())) { // shared grant
            return $this->doesCurrentUserHold($grantToShare)
                && $grantToShare->getAuthorizationResourceIdentifier() === $resourceActionGrant->getAuthorizationResourceIdentifier()
                && $grantToShare->getShareable()
                && ((null !== $resourceActionGrant->getAction() && $grantToShare->getAction() === $resourceActionGrant->getAction())
                    || (null !== $resourceActionGrant->getRole()
                        && null !== $grantToShare->getRole()
                        && in_array(
                            $grantToShare->getRole(),
                            $this->internalResourceActionGrantService->getRolesWhereActionsAreASupersetOrEqual(
                                $resourceActionGrant->getRole()->getIdentifier()),
                            true)));
        }

        return false;
    }

    /**
     * @throws ApiError
     */
    private function isCurrentUserAuthorizedToRemoveGrant(ResourceActionGrant $resourceActionGrant, bool $isResourceOwner): bool
    {
        if ($resourceActionGrant->isInherited()) {
            // inherited grants cannot be removed
            return false;
        } elseif ($isResourceOwner) {
            // resource owners can remove any grants
            return true;
        } elseif (null !== $resourceActionGrant->getShareOf()) { // shared grant
            // current user holds the original grant that the grant to remove is a share of
            return $this->doesCurrentUserHold($resourceActionGrant->getShareOf());
        }

        return false;
    }

    /**
     * @throws ApiError
     */
    private function isCurrentUserAuthorizedToShareGrant(ResourceActionGrant $resourceActionGrant, bool $isResourceOwner): bool
    {
        // DESIGN NOTE: resource owners are generally not allowed to share grants, but  to create arbitrary new grants for the resource
        return false === $isResourceOwner
            && $resourceActionGrant->getShareable()
            && $this->doesCurrentUserHold($resourceActionGrant);
    }

    private function setGrantedActions(ResourceActionGrant $resourceActionGrant, ?bool $isResourceOwner = null): void
    {
        $isResourceOwner ??= $this->isCurrentUserResourceOwner($resourceActionGrant);

        $grantedActions = [];
        if ($isResourceOwner) {
            $grantedActions[] = 'create_grants';
        }
        if ($this->isCurrentUserAuthorizedToRemoveGrant($resourceActionGrant, $isResourceOwner)) {
            $grantedActions[] = 'delete';
        }
        if ($this->isCurrentUserAuthorizedToShareGrant($resourceActionGrant, $isResourceOwner)) {
            $grantedActions[] = 'share';
        }
        $resourceActionGrant->setGrantedActions($grantedActions);
    }

    private function isCurrentUserAuthorizedToReadGroup(UserGroup $userGroup): bool
    {
        return $this->isCurrentUserGranted(self::GROUP_RESOURCE_CLASS,
            $userGroup->getIdentifier(), self::READ_GROUP_ACTION);
    }

    private function isCurrentUserAuthorizedToReadGrant(ResourceActionGrant $resourceActionGrant): bool
    {
        return $this->doesCurrentUserHold($resourceActionGrant)
            || $this->doesCurrentUserHaveAManageGrantForAuthorizationResource(
                $resourceActionGrant->getResourceClass(), $resourceActionGrant->getResourceIdentifier());
    }

    /**
     * @param string|null $resourceIdentifier null matches any resource identifier
     *
     * @return ResourceActionGrant[]|AuthorizationResource[]|string[]
     *
     * @throws ApiError
     */
    private function getResourceActionGrantsCurrentUserIsAuthorizedToReadInternal(
        string $get = InternalResourceActionGrantService::GET_RESOURCE_ACTION_GRANTS,
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?int $resourceType = null,
        int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        $EXPANDED_RESOURCE_ALIAS = InternalResourceActionGrantService::EXPANDED_RESOURCE_ALIAS;

        $userIdentifier = $this->getUserIdentifier() !== null ? $this->getUserIdentifier() : InternalResourceActionGrantService::FALSE;
        $groupIdentifiers = $this->getUserIdentifier() !== null ? $this->userGroupService->getUserGroupsUserIsMemberOf($userIdentifier) : [];
        $dynamicUserGroupIdentifiers = $this->getDynamicGroupsCurrentUserIsMemberOf();

        // Get all grants
        // * that the user is a holder of (personally or by static/dynamic group)
        // * from all resources that the user owns
        try {
            // create a subquery getting the authorization resource IDs that the user owns:
            $ownedAuthorizationResourceIdentifiersBinary = $this->internalResourceActionGrantService->get(
                InternalResourceActionGrantService::GET_AUTHORIZATION_RESOURCE_IDENTIFIERS,
                $resourceClass, $resourceIdentifier, $resourceType,
                actions: [AuthorizationService::OWN_ACTION],
                userIdentifier: $userIdentifier,
                groupIdentifiers: $groupIdentifiers,
                dynamicUserGroupIdentifiers: $dynamicUserGroupIdentifiers);

            $options = [
                InternalResourceActionGrantService::ADDITIONAL_CRITERIA_OPTION => [
                    "OR $EXPANDED_RESOURCE_ALIAS.effective_authorization_resource_identifier IN (:orAuthorizationResourceIdentifiersIn)",
                    ['orAuthorizationResourceIdentifiersIn' => $ownedAuthorizationResourceIdentifiersBinary],
                    ['orAuthorizationResourceIdentifiersIn' => ArrayParameterType::BINARY],
                ],
            ];

            foreach ($ownedAuthorizationResourceIdentifiersBinary as $binaryIdentifier) {
                $ownedAuthorizationResourceIdentifiers[UuidUtils::toStringUuid($binaryIdentifier)] = true;
            }

            $results = $this->internalResourceActionGrantService->get($get,
                $resourceClass, $resourceIdentifier, $resourceType,
                userIdentifier: $userIdentifier,
                groupIdentifiers: $groupIdentifiers,
                dynamicUserGroupIdentifiers: $dynamicUserGroupIdentifiers,
                firstResultIndex: $firstResultIndex,
                maxNumResults: $maxNumResults,
                options: $options);

            switch ($get) {
                case InternalResourceActionGrantService::GET_RESOURCE_ACTION_GRANTS:
                    /** @var ResourceActionGrant $resourceActionGrant */
                    foreach ($results as $resourceActionGrant) {
                        $this->setGrantedActions(
                            $resourceActionGrant,
                            isset($ownedAuthorizationResourceIdentifiers[$resourceActionGrant->getAuthorizationResourceIdentifier()])
                        );
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
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to get resource action grant collection!',
                InternalResourceActionGrantService::GETTING_RESOURCE_ACTION_GRANT_COLLECTION_FAILED_ERROR_ID,
                ['message' => $e->getMessage()]);
        }
    }

    private function doesCurrentUserHaveAManageGrantForAuthorizationResource(
        string $resourceClass, string $resourceIdentifier): bool
    {
        return in_array(AuthorizationService::OWN_ACTION,
            $this->getGrantedActionsForCurrentUser($resourceClass, $resourceIdentifier)?->getActions() ?? [],
            true);
    }

    /**
     * @throws ApiError
     */
    private function doesCurrentUserHold(ResourceActionGrant $resourceActionGrant): bool
    {
        $userIdentifier = $this->getUserIdentifier();

        return ($userIdentifier !== null
                && ($resourceActionGrant->getUserIdentifier() === $userIdentifier
                    || ($resourceActionGrant->getUserGroup() !== null
                        && $this->userGroupService->isUserMemberOfUserGroup($userIdentifier, $resourceActionGrant->getUserGroup()->getIdentifier()))))
            || ($resourceActionGrant->getDynamicUserGroupIdentifier() !== null
                && $this->isCurrentUserMemberOfDynamicGroup($resourceActionGrant->getDynamicUserGroupIdentifier()));
    }

    /**
     * @throws ApiError
     */
    private function isCurrentUserResourceOwner(ResourceActionGrant $resourceActionGrant): bool
    {
        $resourceClass = $resourceActionGrant->getResourceClass();
        $resourceIdentifier = $resourceActionGrant->getResourceIdentifier();
        $resourceType = $resourceActionGrant->getResourceType();

        if (null === $resourceClass || null === $resourceIdentifier) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'resourceClass and resourceIdentifier are required',
                InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ERROR_ID);
        }

        return $this->isCurrentUserGranted($resourceClass, $resourceIdentifier, self::OWN_ACTION, $resourceType);
    }

    private function configure(): void
    {
        $roles = [];
        foreach ($this->config[Configuration::RESOURCE_COLLECTION_OWNER_POLICIES] ?? [] as $resourceClassConfig) {
            $roles[self::toManageResourceCollectionPolicyName($resourceClassConfig[Configuration::RESOURCE_CLASS])] =
                $resourceClassConfig[Configuration::POLICY];
        }
        $roles[self::toManageResourceCollectionPolicyName(self::GROUP_RESOURCE_CLASS)] =
            $this->config[Configuration::CREATE_GROUPS_POLICY];

        foreach ($this->config[Configuration::DYNAMIC_GROUPS] ?? [] as $dynamicUserGroup) {
            $roles[self::toIsCurrentUserMemberOfDynamicGroupPolicyName($dynamicUserGroup[Configuration::IDENTIFIER])] =
                $dynamicUserGroup[Configuration::IS_CURRENT_USER_GROUP_MEMBER_EXPRESSION];
        }
        $roles[self::toIsCurrentUserMemberOfDynamicGroupPolicyName(
            self::DYNAMIC_GROUP_IDENTIFIER_EVERYBODY)] = 'true';

        $resourcePermissions = [];
        foreach ($this->config[Configuration::ADD_RESOURCE_POLICIES] ?? [] as $resourceClassConfig) {
            $resourcePermissions[$resourceClassConfig[Configuration::RESOURCE_CLASS]] =
                $resourceClassConfig[Configuration::POLICY];
        }

        $this->setUpAccessControlPolicies($roles, $resourcePermissions);
    }

    /**
     * @throws ApiError
     */
    private function assertIsGrantedUserGroupAction(?UserGroup $userGroup, string $requiredAction): void
    {
        // NOTE: validation is not done here
        if (null !== ($userGroupIdentifier = $userGroup?->getIdentifier())) {
            $grantedActions = $this->getGrantedActionsForCurrentUser(self::GROUP_RESOURCE_CLASS,
                $userGroupIdentifier)?->getActions() ?? [];

            if (false === in_array($requiredAction, $grantedActions, true)) {
                // throw 403 forbidden only if the user can see the group to avoid leaking information about the existence of the group
                if (false === in_array(self::READ_GROUP_ACTION, $grantedActions, true)) {
                    throw new ApiError(Response::HTTP_NOT_FOUND);
                }
                throw new ApiError(Response::HTTP_FORBIDDEN);
            }
        }
    }

    /**
     * @throws ApiError
     */
    private function validateDynamicUserGroup(?string $dynamicUserGroupIdentifier): void
    {
        if (false === $this->isRoleDefined(
            self::toIsCurrentUserMemberOfDynamicGroupPolicyName($dynamicUserGroupIdentifier)
        )) {
            throw ApiError::withDetails(
                Response::HTTP_BAD_REQUEST,
                'Dynamic user group identifier is undefined',
                InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ERROR_ID
            );
        }
    }

    private static function addGrantHolderCriteria(QueryBuilder $queryBuilder, string $RESOURCE_ACTION_GRANT_ALIAS,
        ?string $userIdentifier, ?array $userGroupIdentifiers, ?array $dynamicUserGroupIdentifiers): void
    {
        $orClause = $queryBuilder->expr()->orX();
        if ($userIdentifier !== null) {
            if ($userIdentifier === InternalResourceActionGrantService::FALSE) {
                $orClause
                    ->add('0 = 1');
            } else {
                $orClause
                    ->add($queryBuilder->expr()->eq("$RESOURCE_ACTION_GRANT_ALIAS.userIdentifier", ':userIdentifier'));
                $queryBuilder->setParameter(':userIdentifier', $userIdentifier);
            }
        }
        if ($userGroupIdentifiers !== null) {
            if ([] === $userGroupIdentifiers) {
                $orClause
                    ->add('0 = 1');
            } else {
                $orClause
                    ->add($queryBuilder->expr()->in("IDENTITY($RESOURCE_ACTION_GRANT_ALIAS.userGroup)", ':userGroupIdentifiers'));
                $queryBuilder->setParameter(':userGroupIdentifiers',
                    UuidUtils::toBinaryUuids($userGroupIdentifiers), ArrayParameterType::BINARY);
            }
        }
        if ($dynamicUserGroupIdentifiers !== null) {
            if ([] === $dynamicUserGroupIdentifiers) {
                $orClause
                    ->add('0 = 1');
            } else {
                $orClause
                    ->add($queryBuilder->expr()->in("$RESOURCE_ACTION_GRANT_ALIAS.dynamicUserGroupIdentifier", ':dynamicUserGroupIdentifiers'));
                $queryBuilder->setParameter(':dynamicUserGroupIdentifiers', $dynamicUserGroupIdentifiers);
            }
        }
        if ($orClause->count() > 0) {
            $queryBuilder->andWhere($orClause);
        }
    }

    private static function toManageResourceCollectionPolicyName(string $resourceClass): string
    {
        return self::RESOURCE_COLLECTION_OWNER_POLICY_PREFIX.$resourceClass;
    }

    private static function toResourceClass(string $manageResourceCollectionPolicyName): string
    {
        return substr($manageResourceCollectionPolicyName, strlen(self::RESOURCE_COLLECTION_OWNER_POLICY_PREFIX));
    }

    private static function isManageResourceCollectionPolicyName(string $dynamicUserGroupIdentifier): bool
    {
        return str_starts_with($dynamicUserGroupIdentifier, self::RESOURCE_COLLECTION_OWNER_POLICY_PREFIX);
    }

    private static function toIsCurrentUserMemberOfDynamicGroupPolicyName(string $dynamicUserGroupIdentifier): string
    {
        return $dynamicUserGroupIdentifier;
    }

    private static function isCurrentUserMemberOfDynamicGroupPolicyName(string $dynamicUserGroupIdentifier): bool
    {
        return false === str_starts_with($dynamicUserGroupIdentifier, self::RESOURCE_COLLECTION_OWNER_POLICY_PREFIX);
    }
}
