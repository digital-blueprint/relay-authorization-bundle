<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Service;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassActionName;
use Dbp\Relay\AuthorizationBundle\Entity\GrantedActions;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceGroupMember;
use Dbp\Relay\AuthorizationBundle\Entity\Role;
use Dbp\Relay\AuthorizationBundle\Entity\RoleAction;
use Dbp\Relay\AuthorizationBundle\Entity\RoleName;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroup;
use Dbp\Relay\AuthorizationBundle\Event\ResourceActionGrantAddedEvent;
use Dbp\Relay\AuthorizationBundle\Helper\AuthorizationUuidBinaryType;
use Dbp\Relay\AuthorizationBundle\Helper\UuidUtils;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
class InternalResourceActionGrantService implements LoggerAwareInterface, ResetInterface
{
    use LoggerAwareTrait;

    public const MAX_NUM_RESULTS_DEFAULT = 1024;

    public const MANAGE_ITEM_ACTION_UUID = '019ecac3-2eea-7297-ae1a-486de6fca628';
    public const MANAGE_COLLECTION_ACTION_UUID = '019ecac3-d095-7ae7-b10b-30bff78040a7';

    public const MANAGER_ROLE_IDENTIFIER = '019f7e71-f6d3-7589-916c-ead4b141f5ba';

    public const COLLECTION_RESOURCE_IDENTIFIER = 'null';
    public const IS_NOT_NULL = '@@@ __is_not_null__ @@@';
    public const FALSE = '@@@ __false__ @@@';

    public const ADDITIONAL_CRITERIA_OPTION = 'additional_criteria';
    public const EXCLUDE_COLLECTION_RESOURCE_OPTION = 'exclude_collection_resource';

    public const RESOURCE_RESOURCE_TYPE = AuthorizationResource::RESOURCE_RESOURCE_TYPE;
    public const RESOURCE_GROUP_RESOURCE_TYPE = AuthorizationResource::RESOURCE_GROUP_RESOURCE_TYPE;

    public const RESOURCE_TYPES = [
        self::RESOURCE_RESOURCE_TYPE,
        self::RESOURCE_GROUP_RESOURCE_TYPE,
    ];

    public const RESOURCE_ACTION_GRANT_ALIAS = 'rag';
    public const RESOURCE_ALIAS = 'r';
    public const EXPANDED_RESOURCE_ALIAS = 'r_rgm';

    private const RESOURCE_GROUP_MEMBER_ALIAS = 'rgm';
    private const AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS = 'arca';
    private const ROLE_ALIAS = 'r';
    private const ROLE_ACTION_ALIAS = 'ra';

    public const GET_GRANTED_ACTIONS = 'granted actions';
    public const GET_RESOURCE_ACTION_GRANTS = 'resource action grants';
    public const GET_AUTHORIZATION_RESOURCE_IDENTIFIERS = 'authorization resource identifiers';
    public const GET_RESOURCE_CLASSES = 'resource classes';

    public const GETTING_RESOURCE_ACTION_GRANT_COLLECTION_FAILED_ERROR_ID = 'authorization:getting-resource-action-grant-collection-failed';

    private const ADDING_RESOURCE_ACTION_GRANT_FAILED_ERROR_ID = 'authorization:adding-resource-action-grant-failed';
    private const REMOVING_RESOURCE_ACTION_GRANT_FAILED_ERROR_ID = 'authorization:removing-resource-action-grant-failed';
    public const GETTING_RESOURCE_ACTION_GRANT_ITEM_FAILED_ERROR_ID = 'authorization:getting-resource-action-grant-item-failed';
    private const ADDING_RESOURCE_FAILED_ERROR_ID = 'authorization:adding-resource-failed';
    private const ADDING_AVAILABLE_RESOURCE_CLASS_ACTIONS_FAILED_ERROR_ID = 'authorization:adding-available-resource-class-actions-failed';
    private const REMOVING_RESOURCE_FAILED_ERROR_ID = 'authorization:removing-resource-failed';
    public const ADDING_RESOURCE_TO_GROUP_RESOURCE_FAILED_ERROR_ID = 'authorization:adding-resource-to-group-resource-failed';
    private const REMOVING_RESOURCE_FROM_GROUP_RESOURCE_FAILED_ERROR_ID = 'authorization:removing-resource-from-group-resource-failed';
    private const GETTING_RESOURCE_ITEM_FAILED_ERROR_ID = 'authorization:getting-resource-item-failed';
    private const AUTHORIZATION_RESOURCE_NOT_FOUND_ERROR_ID = 'authorization:authorization-resource-not-found';
    public const RESOURCE_ACTION_GRANT_INVALID_ERROR_ID = 'authorization:resource-action-grant-invalid';
    private const ADDING_ROLE_FAILED_ERROR_ID = 'authorization:adding-role-failed';
    private const GETTING_ROLE_ITEM_FAILED_ERROR_ID = 'authorization:getting-role-item-failed';
    private const GETTING_ROLE_COLLECTION_FAILED_ERROR_ID = 'authorization:getting-role-collection-failed';
    private const GETTING_AVAILABLE_RESOURCE_CLASS_ACTION_COLLECTION_FAILED_ERROR_ID = 'authorization:getting-available-resource-class-action-collection-failed';

    public static function getAvailableResourceClassActionStatic(EntityManagerInterface $entityManager,
        ?string $resourceClass, string $action, int $actionType): ?AvailableResourceClassAction
    {
        try {
            return $entityManager->getRepository(AvailableResourceClassAction::class)
                ->findOneBy([
                    'action' => $action,
                    'resourceClass' => $action === AuthorizationService::MANAGE_ACTION ? null : $resourceClass,
                    'actionType' => $actionType,
                ]);
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public static function addOrUpdateAvailableResourceClassActionsStatic(EntityManagerInterface $entityManager,
        ?string $resourceClass, array $itemActions, array $collectionActions, ?LoggerInterface $logger = null): array
    {
        $availableItemActions = [];
        foreach ($itemActions as $action => $actionNames) {
            $availableItemActions[] = self::addOrUpdateAvailableResourceClassActionInternal(
                $entityManager, $resourceClass, $action,
                AvailableResourceClassAction::ITEM_ACTION_TYPE,
                $actionNames,
                logger: $logger);
        }
        $availableCollectionActions = [];
        foreach ($collectionActions as $collectionAction => $actionNames) {
            $availableCollectionActions[] = self::addOrUpdateAvailableResourceClassActionInternal(
                $entityManager, $resourceClass, $collectionAction,
                AvailableResourceClassAction::COLLECTION_ACTION_TYPE,
                $actionNames,
                logger: $logger);
        }

        return [
            AvailableResourceClassAction::ITEM_ACTION_TYPE => $availableItemActions,
            AvailableResourceClassAction::COLLECTION_ACTION_TYPE => $availableCollectionActions,
        ];
    }

    public static function ensureManageActionsAndRoleAreAvailable(EntityManagerInterface $entityManager, ?LoggerInterface $logger = null): void
    {
        $manageActionNames = [
            'en' => 'Manage',
            'de' => 'Verwalten',
        ];
        self::addOrUpdateAvailableResourceClassActionInternal(
            $entityManager,
            null,
            AuthorizationService::MANAGE_ACTION,
            AvailableResourceClassAction::ITEM_ACTION_TYPE,
            $manageActionNames,
            identifier: self::MANAGE_ITEM_ACTION_UUID,
            logger: $logger
        );
        self::addOrUpdateAvailableResourceClassActionInternal(
            $entityManager,
            null,
            AuthorizationService::MANAGE_ACTION,
            AvailableResourceClassAction::COLLECTION_ACTION_TYPE,
            $manageActionNames,
            identifier: self::MANAGE_COLLECTION_ACTION_UUID,
            logger: $logger
        );
        self::addOrUpdateRoleStatic($entityManager,
            [
                'en' => 'Manager',
                'de' => 'Verwalter',
            ],
            [
                // works for both item and collection resources:
                ResourceActionGrantService::createRoleAction(
                    null, ResourceActionGrantService::MANAGE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    null, ResourceActionGrantService::MANAGE_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE),
            ],
            identifier: ResourceActionGrantService::MANAGER_ROLE_IDENTIFIER
        );
    }

    /**
     * @throws ApiError
     */
    public static function addOrUpdateRoleStatic(EntityManagerInterface $entityManager,
        array $localizedRoleNames, array $roleActions, ?string $identifier = null, ?LoggerInterface $logger = null): Role
    {
        $role = null !== $identifier ?
            $entityManager->getRepository(Role::class)->find($identifier) : null;
        if (null !== $role) {
            $role->getRoleNames()->clear();
            $role->getRoleActions()->clear();
            $entityManager->persist($role);
            $entityManager->flush();
        } else {
            $role = new Role();
            $role->setIdentifier($identifier ?? Uuid::v7()->toRfc4122());
        }

        foreach ($localizedRoleNames as $languageTag => $name) {
            $roleName = new RoleName();
            $roleName->setRole($role);
            $roleName->setLanguageTag($languageTag);
            $roleName->setName($name);
            $role->getRoleNames()->add($roleName);
        }
        foreach ($roleActions as $roleActionData) {
            $roleAction = new RoleAction();
            $roleAction->setRole($role);
            $resourceClass = $roleActionData['resourceClass'] ?? null;
            $action = $roleActionData['action'] ?? null;
            $actionType = $roleActionData['actionType'] ?? null;
            if (null === $action || null === $actionType || ($action !== AuthorizationService::MANAGE_ACTION && null === $resourceClass)) {
                throw new \RuntimeException('adding role failed: resource action is invalid');
            }

            $availableResourceClassAction = self::getAvailableResourceClassActionStatic(
                $entityManager, $resourceClass, $action, $actionType);
            if (null === $availableResourceClassAction) {
                throw new \RuntimeException(
                    "adding role failed: resource action '$action' (action type: '.$actionType.') is not defined for resource class '$resourceClass'");
            }
            $roleAction->setAvailableResourceClassAction($availableResourceClassAction);
            $role->getRoleActions()->add($roleAction);
        }

        try {
            $entityManager->persist($role);
            $entityManager->flush();
        } catch (\Throwable $throwable) {
            $logger?->error('Failed to add/update role: '.$throwable->getMessage(), [
                'exception' => $throwable,
                'identifier' => $identifier,
            ]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Role could not be added!',
                self::ADDING_ROLE_FAILED_ERROR_ID);
        }

        return $role;
    }

    /**
     * @throw ApiError
     */
    private static function addOrUpdateAvailableResourceClassActionInternal(
        EntityManagerInterface $entityManager,
        ?string $resourceClass, string $action, int $actionType, array $actionNames,
        ?string $identifier = null, ?LoggerInterface $logger = null): AvailableResourceClassAction
    {
        try {
            $availableResourceClassAction = $identifier !== null ?
                $entityManager->getRepository(AvailableResourceClassAction::class)
                    ->find($identifier) :
                $entityManager->getRepository(AvailableResourceClassAction::class)
                    ->findOneBy([
                        'resourceClass' => $resourceClass,
                        'action' => $action,
                        'actionType' => $actionType,
                    ]);
            if (null === $availableResourceClassAction) {
                $availableResourceClassAction = new AvailableResourceClassAction();
                $availableResourceClassAction->setIdentifier(
                    $identifier ?? Uuid::v7()->toRfc4122());
                $availableResourceClassAction->setResourceClass($resourceClass);
                $availableResourceClassAction->setAction($action);
                $availableResourceClassAction->setActionType($actionType);
                $entityManager->persist($availableResourceClassAction);
            }

            $existingByLanguageTag = [];
            foreach ($availableResourceClassAction->getNames() as $existingName) {
                $existingByLanguageTag[$existingName->getLanguageTag()] = $existingName;
            }

            // Update or create names for the provided language tags
            foreach ($actionNames as $languageTag => $name) {
                if (isset($existingByLanguageTag[$languageTag])) {
                    $existingByLanguageTag[$languageTag]->setName($name);
                // no need to persist if it's already managed (it is, because it's attached via the collection)
                } else {
                    $newChild = new AvailableResourceClassActionName();
                    $newChild->setAvailableResourceClassAction($availableResourceClassAction);
                    $newChild->setLanguageTag($languageTag);
                    $newChild->setName($name);

                    $availableResourceClassAction->getNames()->add($newChild);
                    $entityManager->persist($newChild);
                }
            }

            // Optional: remove children that are no longer present in $actionNames
            // (keeps DB clean if you sometimes pass a smaller set of languages)
            $wantedLangs = array_keys($actionNames);
            foreach ($availableResourceClassAction->getNames() as $existingName) {
                if (!in_array($existingName->getLanguageTag(), $wantedLangs, true)) {
                    $entityManager->remove($existingName);
                }
            }

            $entityManager->flush();
        } catch (\Throwable $throwable) {
            $logger?->error('Failed to add/update available resource class actions: '.$throwable->getMessage(), [
                'exception' => $throwable,
                'resourceClass' => $resourceClass,
                'actionType' => $actionType,
            ]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                // 'Available resource class actions could not be added!',
                sprintf('Failed to add/update available resource class %s action %s: %s', $resourceClass, $action, $throwable->getMessage()),
                self::ADDING_AVAILABLE_RESOURCE_CLASS_ACTIONS_FAILED_ERROR_ID);
        }

        return $availableResourceClassAction;
    }

    private array $isAvailableResourceClassActionsRequestCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    public function reset(): void
    {
        $this->isAvailableResourceClassActionsRequestCache = [];
    }

    /**
     * @throws ApiError
     */
    public function addOrUpdateAvailableResourceClassActions(string $resourceClass,
        array $itemActions, array $collectionActions): array
    {
        return self::addOrUpdateAvailableResourceClassActionsStatic($this->entityManager,
            $resourceClass, $itemActions, $collectionActions,
            logger: $this->logger);
    }

    /**
     * @throws ApiError
     */
    public function addOrUpdateRole(array $localizedRoleNames, array $roleActions, ?string $identifier = null): Role
    {
        return self::addOrUpdateRoleStatic($this->entityManager, $localizedRoleNames, $roleActions, $identifier,
            logger: $this->logger);
    }

    /**
     * @throws ApiError
     */
    public function getRoleByIdentifier(string $identifier): Role
    {
        try {
            $role = UuidV7::isValid($identifier) ?
                $this->entityManager->getRepository(Role::class)->find($identifier) :
                null;
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to get role by identifier: '.$throwable->getMessage(), [
                'identifier' => $identifier,
                'exception' => $throwable,
            ]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get role',
                self::GETTING_ROLE_ITEM_FAILED_ERROR_ID);
        }

        if (null === $role) {
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND, 'Role not found', self::GETTING_ROLE_ITEM_FAILED_ERROR_ID);
        }

        return $role;
    }

    /**
     * Returns all roles whose set of actions is a superset of / equal to
     * the actions of the role identified by $roleIdentifier.
     *
     * @return Role[]
     *
     * @throws ApiError
     */
    public function getRolesWhereActionsAreASupersetOrEqual(string $roleIdentifier,
        int $firstItemIndex = 0, int $maxNumItemsPerPage = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        // Validate that the given role exists (throws HTTP_NOT_FOUND if it doesn't)
        $this->getRoleByIdentifier($roleIdentifier);

        $ROLE_ALIAS = self::ROLE_ALIAS;
        $ROLE_ACTION_GIVEN_ALIAS = self::ROLE_ACTION_ALIAS.'_given';
        $ROLE_ACTION_CANIDATE_ALIAS = self::ROLE_ACTION_ALIAS.'_candidate';

        try {
            $queryBuilder = $this->entityManager->createQueryBuilder();

            return $queryBuilder->select($ROLE_ALIAS)
                ->from(Role::class, $ROLE_ALIAS)
                ->where(
                    $queryBuilder->expr()->not(
                        $queryBuilder->expr()->exists(
                            $this->entityManager->createQueryBuilder()
                                ->select('1')
                                ->from(RoleAction::class, $ROLE_ACTION_GIVEN_ALIAS)
                                ->where("$ROLE_ACTION_GIVEN_ALIAS.role = :givenRole")
                                ->andWhere(
                                    $queryBuilder->expr()->not(
                                        $queryBuilder->expr()->exists(
                                            $this->entityManager->createQueryBuilder()
                                                ->select('1')
                                                ->from(RoleAction::class, $ROLE_ACTION_CANIDATE_ALIAS)
                                                ->where("$ROLE_ACTION_CANIDATE_ALIAS.role = $ROLE_ALIAS.identifier")
                                                ->andWhere("$ROLE_ACTION_CANIDATE_ALIAS.availableResourceClassAction = $ROLE_ACTION_GIVEN_ALIAS.availableResourceClassAction")
                                                ->getDQL()
                                        )
                                    )
                                )
                                ->getDQL()
                        )
                    )
                )
                ->setParameter(':givenRole', $roleIdentifier, AuthorizationUuidBinaryType::NAME)
                ->getQuery()
                ->setFirstResult($firstItemIndex)
                ->setMaxResults($maxNumItemsPerPage)
                ->getResult();
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to get superset roles: '.$throwable->getMessage(), [
                'exception' => $throwable,
                'roleIdentifier' => $roleIdentifier,
            ]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get roles',
                self::GETTING_ROLE_COLLECTION_FAILED_ERROR_ID);
        }
    }

    /**
     * @return Role[]
     *
     * @throws ApiError
     */
    public function getRoles(string $resourceClass, int $actionType,
        ?array $whereActionsAreASubsetOrEqual = null,
        int $firstItemIndex = 0, int $maxNumItemsPerPage = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        $ROLE_ALIAS = self::ROLE_ALIAS;
        $ROLE_ACTION_ALIAS = self::ROLE_ACTION_ALIAS;
        $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS = self::AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS;

        try {
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder->select($ROLE_ALIAS)
                ->from(Role::class, $ROLE_ALIAS)
                ->innerJoin(RoleAction::class, $ROLE_ACTION_ALIAS,
                    Join::WITH, "$ROLE_ALIAS.identifier = $ROLE_ACTION_ALIAS.role")
                ->innerJoin(AvailableResourceClassAction::class, $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS,
                    Join::WITH, "
                    $ROLE_ACTION_ALIAS.availableResourceClassAction = $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.identifier");

            // NOTE: currently, we don't check if the resource class actually exists and return the manager role
            // even if the resource class doesn't exist
            $or = $queryBuilder->expr()->orX();
            $or->add($queryBuilder->expr()->isNull($AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.'.resourceClass'));
            $or->add($queryBuilder->expr()->eq($AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.'.resourceClass', ':resourceClass'));
            $queryBuilder
                ->andWhere($or)
                ->setParameter(':resourceClass', $resourceClass)
                ->andWhere($queryBuilder->expr()->eq($AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.'.actionType', ':actionType'))
                ->setParameter(':actionType', $actionType);

            if (null !== $whereActionsAreASubsetOrEqual) {
                if ([] === $whereActionsAreASubsetOrEqual) {
                    $queryBuilder->andWhere('0 = 1'); // no roles can have no actions, so return empty result
                } else {
                    $subSubSelectBuilder = $this->entityManager->createQueryBuilder();
                    $subSubSelectBuilder->select('arca_candidate.identifier')
                        ->from(AvailableResourceClassAction::class, 'arca_candidate')
                        ->where('arca_candidate.action IN (:whereActionsAreASubsetOf)');

                    $subSelectBuilder = $this->entityManager->createQueryBuilder();
                    $subSelectBuilder->select('1')
                        ->from(RoleAction::class, 'ra_candidate')
                        ->where("ra_candidate.role = $ROLE_ALIAS.identifier")
                        ->andWhere($subSelectBuilder->expr()->notIn('ra_candidate.availableResourceClassAction',
                            $subSubSelectBuilder->getDQL()));

                    $queryBuilder->andWhere(
                        $queryBuilder->expr()->not(
                            $queryBuilder->expr()->exists($subSelectBuilder->getDQL())
                        )
                    )->setParameter(':whereActionsAreASubsetOf', $whereActionsAreASubsetOrEqual, ArrayParameterType::STRING);
                }
            }

            return $queryBuilder
                ->getQuery()
                ->setFirstResult($firstItemIndex)
                ->setMaxResults($maxNumItemsPerPage)
                ->getResult();
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to get roles: '.$throwable->getMessage(), [
                'exception' => $throwable,
                'resourceClass' => $resourceClass,
                'actionType' => $actionType,
            ]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get roles',
                self::GETTING_ROLE_COLLECTION_FAILED_ERROR_ID);
        }
    }

    /**
     * @return AvailableResourceClassAction[]
     */
    public function getAvailableResourceClassActions(string $resourceClass, int $actionType,
        ?array $whereActionsIn = null,
        int $firstItemIndex = 0, int $maxNumItemsPerPage = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS = self::AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS;

        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->select($AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS)
            ->from(AvailableResourceClassAction::class, $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS);

        // NOTE: currently, we don't check if the resource class actually exists and return the manage actions
        // even if the resource class doesn't exist
        $or = $queryBuilder->expr()->orX();
        $or->add($queryBuilder->expr()->isNull($AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.'.resourceClass'));
        $or->add($queryBuilder->expr()->eq($AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.'.resourceClass', ':resourceClass'));
        $queryBuilder
            ->andWhere($or)
            ->setParameter(':resourceClass', $resourceClass)
            ->andWhere($queryBuilder->expr()->eq($AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.'.actionType', ':actionType'))
            ->setParameter(':actionType', $actionType);

        if (null !== $whereActionsIn) {
            if ([] === $whereActionsIn) {
                $queryBuilder
                    ->andWhere('1 = 0'); // no actions will be returned
            } else {
                $queryBuilder
                    ->andWhere($queryBuilder->expr()->in(
                        $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.'.action', ':whereActionsIn'))
                    ->setParameter(':whereActionsIn', $whereActionsIn);
            }
        }

        try {
            return $queryBuilder
                ->setFirstResult($firstItemIndex)
                ->setMaxResults($maxNumItemsPerPage)
                ->getQuery()
                ->getResult();
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to get available resource class actions: '.$throwable->getMessage(), ['exception' => $throwable]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to get available resource class actions!',
                self::GETTING_AVAILABLE_RESOURCE_CLASS_ACTION_COLLECTION_FAILED_ERROR_ID);
        }
    }

    public function isAvailableResourceClassAction(
        string $resourceClass, string $action, string $resourceIdentifier): bool
    {
        $criteria = [
            'resourceClass' => $resourceClass,
            'actionType' => AvailableResourceClassAction::getActionTypeForResourceIdentifier($resourceIdentifier),
        ];

        // DESIGN NOTE: we require at least one action to be defined for a resource class to be 'available'
        if ($action !== AuthorizationService::MANAGE_ACTION) {
            $criteria['action'] = $action;
        }

        $cacheKey = hash('sha256', json_encode($criteria));

        if (null === ($isAvailable = $this->isAvailableResourceClassActionsRequestCache[$cacheKey] ?? null)) {
            $isAvailable = [] !==
                $this->entityManager->getRepository(AvailableResourceClassAction::class)->findBy($criteria);
            $this->isAvailableResourceClassActionsRequestCache[$cacheKey] = $isAvailable;
        }

        return $isAvailable;
    }

    /**
     * @throws ApiError
     */
    public function addResourceActionGrant(ResourceActionGrant $resourceActionGrant): ResourceActionGrant
    {
        return $this->addResourceActionGrantInternal($resourceActionGrant);
    }

    /**
     * @throws ApiError
     */
    public function throwResourceNotFound(): void
    {
        throw ApiError::withDetails(Response::HTTP_NOT_FOUND,
            'authorization resource not found', self::AUTHORIZATION_RESOURCE_NOT_FOUND_ERROR_ID);
    }

    /**
     * @throws ApiError
     */
    public function removeResourceActionGrant(ResourceActionGrant $resourceActionGrant): void
    {
        try {
            $this->entityManager->remove($resourceActionGrant);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Resource action grant could not be removed!',
                self::REMOVING_RESOURCE_ACTION_GRANT_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    public function removeResourceActionGrantByIdentifier(string $identifier): void
    {
        $resourceActionGrant = $this->getResourceActionGrantByIdentifier($identifier);
        if ($resourceActionGrant !== null) {
            $this->removeResourceActionGrant($resourceActionGrant);
        }
    }

    /**
     * @parram string|null $resourceIdentifier null refers to the collection of the respective resource class.
     *
     * @throws ApiError
     */
    public function addResourceActionGrantByResourceClassAndIdentifier(
        string $resourceClass, string $resourceIdentifier, int $resourceType = self::RESOURCE_RESOURCE_TYPE,
        ?string $action = null, ?string $roleIdentifier = null,
        ?string $userIdentifier = null, ?UserGroup $userGroup = null, ?string $dynamicUserGroupIdentifier = null,
        ?bool $shareable = null, ?string $currentUserIdentifier = null): ResourceActionGrant
    {
        $connection = $this->entityManager->getConnection();
        try {
            $connection->beginTransaction();

            $resourceActionGrant = new ResourceActionGrant();
            $resourceActionGrant->setAuthorizationResource(
                $this->getOrCreateAuthorizationResource($resourceClass, $resourceIdentifier, $resourceType)
            );
            $resourceActionGrant->setAction($action);
            $resourceActionGrant->setRole($roleIdentifier !== null ?
                $this->getRoleByIdentifier($roleIdentifier) :
                null);
            $resourceActionGrant->setUserIdentifier($userIdentifier);
            $resourceActionGrant->setUserGroup($userGroup);
            $resourceActionGrant->setDynamicUserGroupIdentifier($dynamicUserGroupIdentifier);
            if (null !== $shareable) {
                $resourceActionGrant->setShareable($shareable);
            }
            $resourceActionGrant->setCreatorId($currentUserIdentifier);

            $this->addResourceActionGrantInternal($resourceActionGrant);

            $connection->commit();
        } catch (\Throwable $throwable) {
            if ($connection->isTransactionActive()) {
                $connection->rollback();
            }
            if ($throwable instanceof ApiError) {
                throw $throwable;
            }
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Resource could not be added! '.$throwable->getMessage(),
                self::ADDING_RESOURCE_FAILED_ERROR_ID, ['message' => $throwable->getMessage()]);
        }

        return $resourceActionGrant;
    }

    /**
     * @param string|array|null $resourceIdentifier
     *
     * @throws ApiError
     */
    public function removeGrantsByResourceClassAndIdentifier(
        ?string $resourceClass = null, mixed $resourceIdentifier = null, ?int $resourceType = self::RESOURCE_RESOURCE_TYPE): void
    {
        $this->removeResourcesInternal(true, $resourceClass, $resourceIdentifier, $resourceType);
    }

    /**
     * @param string|array|null $resourceIdentifier
     *
     * @throws ApiError
     */
    public function removeAuthorizationResourcesByResourceClassAndIdentifier(
        ?string $resourceClass = null, mixed $resourceIdentifier = null, ?int $resourceType = self::RESOURCE_RESOURCE_TYPE): void
    {
        $this->removeResourcesInternal(false, $resourceClass, $resourceIdentifier, $resourceType);
    }

    /**
     * @throws ApiError
     */
    public function addResourceToResourceGroup(string $resourceClass, string $resourceGroupResourceIdentifier,
        string $resourceIdentifier, int $resourceType = self::RESOURCE_RESOURCE_TYPE): ResourceGroupMember
    {
        if (AvailableResourceClassAction::getActionTypeForResourceIdentifier($resourceGroupResourceIdentifier) !==
            AvailableResourceClassAction::getActionTypeForResourceIdentifier($resourceIdentifier)) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'Resource group and resource be both of the same type (collection or item)!',
                self::ADDING_RESOURCE_TO_GROUP_RESOURCE_FAILED_ERROR_ID);
        }

        $resourceGroupResourceType = self::RESOURCE_GROUP_RESOURCE_TYPE;

        // TODO: prevent circular references
        if ($resourceGroupResourceIdentifier === $resourceIdentifier
            && $resourceGroupResourceType === $resourceType) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'Group resource and member resource must not be identical',
                self::ADDING_RESOURCE_TO_GROUP_RESOURCE_FAILED_ERROR_ID);
        }

        $groupAuthorizationResourceMember = new ResourceGroupMember();
        $groupAuthorizationResourceMember->setIdentifier(Uuid::v7()->toRfc4122());
        $groupAuthorizationResourceMember->setGroupAuthorizationResource(
            $this->getOrCreateAuthorizationResource(
                $resourceClass, $resourceGroupResourceIdentifier, $resourceGroupResourceType)
        );
        $groupAuthorizationResourceMember->setMemberAuthorizationResource(
            $this->getOrCreateAuthorizationResource($resourceClass, $resourceIdentifier, $resourceType)
        );

        try {
            $this->entityManager->persist($groupAuthorizationResourceMember);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to add resource to group resource!',
                self::ADDING_RESOURCE_TO_GROUP_RESOURCE_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
        }

        return $groupAuthorizationResourceMember;
    }

    public function removeResourceFromGroupResource(string $resourceClass, string $resourceGroupResourceIdentifier,
        string $resourceIdentifier, int $resourceType = self::RESOURCE_RESOURCE_TYPE): void
    {
        $RESOURCE_GROUP_MEMBER_ALIAS = self::RESOURCE_GROUP_MEMBER_ALIAS;
        $GROUP_AUTHORIZATION_RESOURCE_ALIAS = 'gar';
        $MEMBER_AUTHORIZATION_RESOURCE_ALIAS = 'mar';

        $innerQueryBuilder = $this->entityManager->createQueryBuilder();
        $innerQueryBuilder->select(self::RESOURCE_GROUP_MEMBER_ALIAS.'.identifier')
            ->from(ResourceGroupMember::class, self::RESOURCE_GROUP_MEMBER_ALIAS)
            ->innerJoin(AuthorizationResource::class, $GROUP_AUTHORIZATION_RESOURCE_ALIAS, Join::WITH,
                "$RESOURCE_GROUP_MEMBER_ALIAS.groupAuthorizationResource = $GROUP_AUTHORIZATION_RESOURCE_ALIAS.identifier")
            ->innerJoin(AuthorizationResource::class, $MEMBER_AUTHORIZATION_RESOURCE_ALIAS, Join::WITH,
                "$RESOURCE_GROUP_MEMBER_ALIAS.memberAuthorizationResource = $MEMBER_AUTHORIZATION_RESOURCE_ALIAS.identifier")
            ->where($innerQueryBuilder->expr()->eq($GROUP_AUTHORIZATION_RESOURCE_ALIAS.'.resourceClass', ':resourceClass'))
            ->andWhere($innerQueryBuilder->expr()->eq($MEMBER_AUTHORIZATION_RESOURCE_ALIAS.'.resourceClass', ':resourceClass'))
            ->setParameter(':resourceClass', $resourceClass)
            ->andWhere($innerQueryBuilder->expr()->eq($GROUP_AUTHORIZATION_RESOURCE_ALIAS.'.resourceIdentifier', ':resourceGroupResourceIdentifier'))
            ->setParameter(':resourceGroupResourceIdentifier', $resourceGroupResourceIdentifier)
            ->andWhere($innerQueryBuilder->expr()->eq($MEMBER_AUTHORIZATION_RESOURCE_ALIAS.'.resourceIdentifier', ':resourceIdentifier'))
            ->setParameter(':resourceIdentifier', $resourceIdentifier)
            ->andWhere($innerQueryBuilder->expr()->eq($GROUP_AUTHORIZATION_RESOURCE_ALIAS.'.resourceType', ':groupResourceType'))
            ->setParameter(':groupResourceType', self::RESOURCE_GROUP_RESOURCE_TYPE)
            ->andWhere($innerQueryBuilder->expr()->eq($MEMBER_AUTHORIZATION_RESOURCE_ALIAS.'.resourceType', ':resourceType'))
            ->setParameter(':resourceType', $resourceType)
        ;

        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->delete(ResourceGroupMember::class, self::RESOURCE_GROUP_MEMBER_ALIAS.'_2')
            ->where($queryBuilder->expr()->in(self::RESOURCE_GROUP_MEMBER_ALIAS.'_2.identifier', $innerQueryBuilder->getDQL()));

        $queryBuilder->setParameters($innerQueryBuilder->getParameters()); // doctrine forgets the parameters of the inner query builder...

        try {
            $queryBuilder->getQuery()->execute();
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to remove resource from group resource: '.$throwable->getMessage(), ['exception' => $throwable]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to remove resource from group resource!',
                self::REMOVING_RESOURCE_FROM_GROUP_RESOURCE_FAILED_ERROR_ID);
        }
    }

    public function getAuthorizationResourceByResourceClassAndIdentifier(
        string $resourceClass, string $resourceIdentifier, int $resourceType = self::RESOURCE_RESOURCE_TYPE): ?AuthorizationResource
    {
        try {
            return $this->entityManager
                ->getRepository(AuthorizationResource::class)
                ->findOneBy([
                    'resourceClass' => $resourceClass,
                    'resourceIdentifier' => $resourceIdentifier,
                    'resourceType' => $resourceType,
                ]);
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get resource item!',
                self::GETTING_RESOURCE_ITEM_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
        }
    }

    /**
     * @throws ApiError
     */
    public function getResourceActionGrantByIdentifier(string $identifier): ?ResourceActionGrant
    {
        try {
            return UuidV7::isValid($identifier) ? $this->entityManager
                ->getRepository(ResourceActionGrant::class)
                ->find($identifier) : null;
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get resource action item!',
                self::GETTING_RESOURCE_ACTION_GRANT_ITEM_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
        }
    }

    /**
     * @return ResourceActionGrant[]|AuthorizationResource[]|string[]
     */
    public function get(string $get = self::GET_RESOURCE_ACTION_GRANTS,
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?int $resourceType = null,
        ?array $actions = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicUserGroupIdentifiers = null,
        int $firstResultIndex = 0, ?int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT, array $options = []): array
    {
        return $this->getInternal($get, $resourceClass, $resourceIdentifier, $resourceType,
            whereActionIn: $actions,
            userIdentifier: $userIdentifier,
            groupIdentifiers: $groupIdentifiers,
            dynamicUserGroupIdentifiers: $dynamicUserGroupIdentifiers,
            firstResultIndex: $firstResultIndex,
            maxNumResults: $maxNumResults,
            options: $options);
    }

    /**
     * Parameters with null values will not be filtered on.
     * NOTE: The grant holder criteria (userIdentifier, groupIdentifiers, dynamicGroupIdentifiers) is logically combined
     * with an OR conjunction.
     *
     * @param string[]|string|null $groupIdentifiers
     * @param string[]|string|null $dynamicUserGroupIdentifiers
     *
     * @return ResourceActionGrant[]|AuthorizationResource[]
     *
     * @throws ApiError
     */
    public function getResourceActionGrantsForResource(
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?int $resourceType = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicUserGroupIdentifiers = null,
        int $firstResultIndex = 0, ?int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT, array $options = []): array
    {
        return $this->getInternal(self::GET_RESOURCE_ACTION_GRANTS,
            $resourceClass, $resourceIdentifier, $resourceType,
            userIdentifier: $userIdentifier,
            groupIdentifiers: $groupIdentifiers,
            dynamicUserGroupIdentifiers: $dynamicUserGroupIdentifiers,
            firstResultIndex: $firstResultIndex,
            maxNumResults: $maxNumResults,
            options: $options);
    }

    /**
     * Parameters with null values will not be filtered on.
     * NOTE: The grant holder criteria (userIdentifier, groupIdentifiers, dynamicGroupIdentifiers) is logically combined
     * with an OR conjunction.
     *
     * @param string[]|string|null $groupIdentifiers
     * @param string[]|string|null $dynamicUserGroupIdentifiers
     *
     * @throws ApiError
     */
    public function getGrantedActionsForResource(
        string $resourceClass, string $resourceIdentifier, int $resourceType = self::RESOURCE_RESOURCE_TYPE,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicUserGroupIdentifiers = null,
        int $firstResultIndex = 0, ?int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT, array $options = []): ?GrantedActions
    {
        return $this->getInternal(self::GET_GRANTED_ACTIONS,
            $resourceClass, $resourceIdentifier, $resourceType,
            userIdentifier: $userIdentifier,
            groupIdentifiers: $groupIdentifiers,
            dynamicUserGroupIdentifiers: $dynamicUserGroupIdentifiers,
            firstResultIndex: $firstResultIndex,
            maxNumResults: $maxNumResults,
            options: $options)[0] ?? null;
    }

    /**
     * Gets all resource action grants for one resource item page defined by the first result
     * index and the maximum number of result (page) items ordered by resource.
     *
     * Parameters with null values will not be filtered on.
     * NOTE: The grant holder criteria (userIdentifier, groupIdentifiers, dynamicGroupIdentifiers) is logically combined
     * with an OR conjunction.
     *
     * @return GrantedActions[]
     *
     * @throws ApiError
     */
    public function getGrantedActionsForResourcePage(
        ?string $resourceClass = null,
        ?string $resourceIdentifier = null,
        ?int $resourceType = self::RESOURCE_RESOURCE_TYPE,
        ?array $whereAuthorizationResourceActionsContainAnyOf = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicUserGroupIdentifiers = null,
        int $firstResultIndex = 0, ?int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT, array $options = []): array
    {
        // * doctrine does not yet support joins with subqueries (SELECT ... INNER JOIN (SELECT ...))
        // * our current MySQL version doesn't yet support 'LIMIT & IN/ALL/ANY/SOME subquery' (SELECT ... WHERE foo IN (SELECT .... LIMIT 10)
        // -> we use two separate queries for now
        try {
            // first get the requested page of authorization resource ids
            $authorizationResourceIdPage = $this->getInternal(
                self::GET_AUTHORIZATION_RESOURCE_IDENTIFIERS,
                $resourceClass, $resourceIdentifier, $resourceType,
                whereActionIn: $whereAuthorizationResourceActionsContainAnyOf,
                userIdentifier: $userIdentifier,
                groupIdentifiers: $groupIdentifiers,
                dynamicUserGroupIdentifiers: $dynamicUserGroupIdentifiers,
                firstResultIndex: $firstResultIndex,
                maxNumResults: $maxNumResults,
                options: $options);

            // then get ALL granted actions for the authorization resource ids page
            return $this->getInternal(
                self::GET_GRANTED_ACTIONS,
                authorizationResourceIdentifiers: $authorizationResourceIdPage,
                userIdentifier: $userIdentifier,
                groupIdentifiers: $groupIdentifiers,
                dynamicUserGroupIdentifiers: $dynamicUserGroupIdentifiers,
                maxNumResults: null
            );
        } catch (\Throwable $throwable) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to get resource action grant collection!',
                self::GETTING_RESOURCE_ACTION_GRANT_COLLECTION_FAILED_ERROR_ID, ['message' => $throwable->getMessage()]);
        }
    }

    private function getInternal(string $get,
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?int $resourceType = null,
        mixed $authorizationResourceIdentifiers = null,
        ?array $whereActionIn = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicUserGroupIdentifiers = null,
        int $firstResultIndex = 0, ?int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT, array $options = []): array
    {
        [$sql, $parameterValues, $parameterTypes] = $this->getQueryInternal(
            $get,
            $resourceClass, $resourceIdentifier, $resourceType,
            authorizationResourceIdentifiers: $authorizationResourceIdentifiers,
            actions: $whereActionIn,
            userIdentifier: $userIdentifier,
            groupIdentifiers: $groupIdentifiers,
            dynamicUserGroupIdentifiers: $dynamicUserGroupIdentifiers,
            firstResultIndex: $firstResultIndex,
            maxNumResults: $maxNumResults,
            options: $options);

        try {
            $results = [];
            $grantedActionsEntity = null;
            foreach ($this->entityManager->getConnection()->executeQuery(
                $sql, $parameterValues, $parameterTypes)->fetchAllAssociative() as $row) {
                switch ($get) {
                    case self::GET_GRANTED_ACTIONS:
                        $effectiveResourceIdentifier = $row['effective_resource_identifier'];
                        $effectiveResourceClass = $row['effective_resource_class'];
                        $effectiveResourceType = (int) $row['effective_resource_type'];
                        $actionResourceClass = $row['action_resource_class'];
                        $action = $row['action'];
                        $actionType = $row['action_type'];

                        if ($effectiveResourceClass !== $grantedActionsEntity?->getResourceClass()
                            || $effectiveResourceIdentifier !== $grantedActionsEntity?->getResourceIdentifier()
                            || $effectiveResourceType !== $grantedActionsEntity?->getResourceType()) {
                            $grantedActionsEntity = new GrantedActions();
                            $grantedActionsEntity->setResourceClass($effectiveResourceClass);
                            $grantedActionsEntity->setResourceIdentifier($effectiveResourceIdentifier);
                            $grantedActionsEntity->setResourceType($effectiveResourceType);
                            $results[] = $grantedActionsEntity;
                        }

                        if (($action === AuthorizationService::MANAGE_ACTION || $actionResourceClass === $effectiveResourceClass)
                            && ($actionType ===
                                AvailableResourceClassAction::getActionTypeForResourceIdentifier($effectiveResourceIdentifier))
                        ) {
                            $grantedActionsEntity->addAction($action);
                        }
                        break;

                    case self::GET_RESOURCE_ACTION_GRANTS:
                        $results[] = $this->hydrateResourceActionGrant($row);
                        break;

                    case self::GET_AUTHORIZATION_RESOURCE_IDENTIFIERS:
                        // NOTE: if actions (other than manage) are required to be granted for returned recources,
                        // we check if those actions are even available for the resource class and type,
                        // and otherwise we filter them out.
                        // (note that we can ignore manage, since it is always available)
                        $nonManageActions = array_filter($whereActionIn ?? [], fn ($action) => $action !== AuthorizationService::MANAGE_ACTION);
                        if ([] === $nonManageActions) {
                            $acceptResource = true;
                        } else {
                            $acceptResource = false;
                            foreach ($nonManageActions as $otherAction) {
                                if ($this->isAvailableResourceClassAction(
                                    $row['effective_resource_class'], $otherAction, $row['effective_resource_identifier'])) {
                                    $acceptResource = true;
                                    break;
                                }
                            }
                        }
                        if ($acceptResource) {
                            $results[] = $row['effective_authorization_resource_identifier'];
                        }
                        break;

                    case self::GET_RESOURCE_CLASSES:
                        $results[] = $row['effective_resource_class'];
                        break;

                    default:
                        throw new \InvalidArgumentException('Undefined get: '.$get);
                }
            }
        } catch (\Throwable $throwable) {
            $this->logger->error("Failed to get $get: ".$throwable->getMessage(), ['exception' => $throwable]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                "Failed to get $get",
                self::GETTING_RESOURCE_ACTION_GRANT_COLLECTION_FAILED_ERROR_ID
            );
        }

        return $results;
    }

    /**
     * @param string[]|string|null $groupIdentifiers
     * @param string[]|string|null $dynamicUserGroupIdentifiers
     *
     * @throws ApiError
     */
    private function getQueryInternal(string $get,
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?int $resourceType = null,
        mixed $authorizationResourceIdentifiers = null,
        ?array $actions = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicUserGroupIdentifiers = null,
        int $firstResultIndex = 0, ?int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT, array $options = []): array
    {
        $RESOURCE_ACTION_GRANT_ALIAS = self::RESOURCE_ACTION_GRANT_ALIAS;
        $AUTHORIZATION_RESOURCE_ALIAS = self::RESOURCE_ALIAS;
        $EXPANDED_RESOURCE_ALIAS = self::EXPANDED_RESOURCE_ALIAS;
        $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS = self::AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS;
        $ROLE_ACTION_ALIAS = self::ROLE_ACTION_ALIAS;
        $COLLECTION_RESOURCE_IDENTIFIER = self::COLLECTION_RESOURCE_IDENTIFIER;

        $parameterValues = [];
        $parameterTypes = [];

        $actionsAvailabilityCriteria = 'true';
        $groupByStatement = '';
        // we order the results to make pagination results deterministic
        $orderByStatement = '';

        switch ($get) {
            case self::GET_GRANTED_ACTIONS:
                $select = "DISTINCT
                    $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.identifier,
                    $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.action,
                    $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.resource_class as action_resource_class,
                    $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.action_type,
                    $EXPANDED_RESOURCE_ALIAS.effective_resource_class,
                    $EXPANDED_RESOURCE_ALIAS.effective_resource_identifier,
                    $EXPANDED_RESOURCE_ALIAS.effective_resource_type";

                $actionsAvailabilityCriteria = "
                (
                    $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.resource_class IS NULL
                    OR $EXPANDED_RESOURCE_ALIAS.effective_resource_class = $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.resource_class
                ) AND (
                    (
                        $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.action_type = 0
                        AND $EXPANDED_RESOURCE_ALIAS.effective_resource_identifier != '$COLLECTION_RESOURCE_IDENTIFIER'
                    ) OR (
                        $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.action_type = 1
                        AND $EXPANDED_RESOURCE_ALIAS.effective_resource_identifier = '$COLLECTION_RESOURCE_IDENTIFIER'
                    )
                )";

                $orderByStatement = "ORDER BY
                     $EXPANDED_RESOURCE_ALIAS.effective_authorization_resource_identifier";
                break;

            case self::GET_RESOURCE_ACTION_GRANTS:
                $select = "
                    $RESOURCE_ACTION_GRANT_ALIAS.identifier,
                    $RESOURCE_ACTION_GRANT_ALIAS.authorization_resource_identifier,
                    $RESOURCE_ACTION_GRANT_ALIAS.user_identifier,
                    $RESOURCE_ACTION_GRANT_ALIAS.user_group_identifier,
                    $RESOURCE_ACTION_GRANT_ALIAS.dynamic_user_group_identifier,
                    $RESOURCE_ACTION_GRANT_ALIAS.role_identifier,
                    $RESOURCE_ACTION_GRANT_ALIAS.creator_id,
                    $RESOURCE_ACTION_GRANT_ALIAS.date_created,
                    $RESOURCE_ACTION_GRANT_ALIAS.shareable,
                    $RESOURCE_ACTION_GRANT_ALIAS.share_of_identifier,
                    $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.action,
                    $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.resource_class as action_resource_class,
                    $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.action_type,
                    $EXPANDED_RESOURCE_ALIAS.resource_class,
                    $EXPANDED_RESOURCE_ALIAS.resource_identifier,
                    $EXPANDED_RESOURCE_ALIAS.resource_type,
                    $EXPANDED_RESOURCE_ALIAS.effective_resource_class,
                    $EXPANDED_RESOURCE_ALIAS.effective_resource_identifier,
                    $EXPANDED_RESOURCE_ALIAS.effective_resource_type,
                    $EXPANDED_RESOURCE_ALIAS.effective_authorization_resource_identifier";

                $groupByStatement = "GROUP BY
                    $RESOURCE_ACTION_GRANT_ALIAS.identifier,
                    $RESOURCE_ACTION_GRANT_ALIAS.user_identifier,
                    $RESOURCE_ACTION_GRANT_ALIAS.user_group_identifier,
                    $RESOURCE_ACTION_GRANT_ALIAS.dynamic_user_group_identifier,
                    $RESOURCE_ACTION_GRANT_ALIAS.role_identifier,
                    $RESOURCE_ACTION_GRANT_ALIAS.available_resource_class_action_identifier";

                $orderByStatement = "ORDER BY
                    $EXPANDED_RESOURCE_ALIAS.effective_authorization_resource_identifier,
                    $RESOURCE_ACTION_GRANT_ALIAS.identifier";
                break;

            case self::GET_AUTHORIZATION_RESOURCE_IDENTIFIERS:
                $select = "DISTINCT
                    $EXPANDED_RESOURCE_ALIAS.effective_authorization_resource_identifier,
                    $EXPANDED_RESOURCE_ALIAS.effective_resource_class,
                    $EXPANDED_RESOURCE_ALIAS.effective_resource_identifier";

                $orderByStatement = "ORDER BY
                     $EXPANDED_RESOURCE_ALIAS.effective_authorization_resource_identifier";
                break;

            case self::GET_RESOURCE_CLASSES:
                $select = "DISTINCT
                    $EXPANDED_RESOURCE_ALIAS.effective_resource_class";
                break;

            default:
                throw new \InvalidArgumentException('Undefined get: '.$get);
        }

        $authorizationResourceCriteria = $this->getAuthorizationResourceCriteria($AUTHORIZATION_RESOURCE_ALIAS,
            $resourceClass, $resourceIdentifier, $resourceType, $authorizationResourceIdentifiers,
            $parameterValues, $parameterTypes, $options);

        $grantHolderCriteria = $this->getGrantHolderCriteria($RESOURCE_ACTION_GRANT_ALIAS,
            $userIdentifier, $groupIdentifiers, $dynamicUserGroupIdentifiers,
            $parameterValues, $parameterTypes);

        $actionCriteria = $this->getActionCriteria($AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS,
            $actions, $parameterValues, $parameterTypes);

        $additionalCriteria = '';
        if ($additionalCriteriaOption = ($options[self::ADDITIONAL_CRITERIA_OPTION] ?? null)) {
            $additionalCriteria = $additionalCriteriaOption[0];
            $parameterValues = array_merge($parameterValues, $additionalCriteriaOption[1] ?? []);
            $parameterTypes = array_merge($parameterTypes, $additionalCriteriaOption[2] ?? []);
        }

        $limitAndOffsetStatement = ($maxNumResults !== null ? "LIMIT $maxNumResults" : '').
            ($firstResultIndex > 0 ? " OFFSET $firstResultIndex" : '');

        $sql = "SELECT $select
                FROM authorization_resource_action_grants $RESOURCE_ACTION_GRANT_ALIAS
                INNER JOIN (
                    WITH RECURSIVE cte AS (
                        SELECT $AUTHORIZATION_RESOURCE_ALIAS.identifier as authorization_resource_identifier,
                               $AUTHORIZATION_RESOURCE_ALIAS.resource_class, 
                               $AUTHORIZATION_RESOURCE_ALIAS.resource_identifier,
                               $AUTHORIZATION_RESOURCE_ALIAS.resource_type,
                               $AUTHORIZATION_RESOURCE_ALIAS.identifier AS effective_authorization_resource_identifier,
                               $AUTHORIZATION_RESOURCE_ALIAS.resource_class AS effective_resource_class,
                               $AUTHORIZATION_RESOURCE_ALIAS.resource_identifier AS effective_resource_identifier,
                               $AUTHORIZATION_RESOURCE_ALIAS.resource_type AS effective_resource_type
                        FROM authorization_resources $AUTHORIZATION_RESOURCE_ALIAS
                        WHERE $authorizationResourceCriteria
                        UNION ALL
                        SELECT ar_rgm_n.group_authorization_resource_identifier as authorization_resource_identifier,
                               ar_2.resource_class, 
                               ar_2.resource_identifier,
                               ar_2.resource_type,
                               cte.effective_authorization_resource_identifier,
                               cte.effective_resource_class,
                               cte.effective_resource_identifier,
                               cte.effective_resource_type
                        FROM authorization_resource_group_members ar_rgm_n
                        INNER JOIN authorization_resources ar_2
                            ON ar_2.identifier = ar_rgm_n.group_authorization_resource_identifier
                        INNER JOIN cte
                            ON ar_rgm_n.member_authorization_resource_identifier = cte.authorization_resource_identifier
                    )
                    SELECT cte.authorization_resource_identifier,
                           cte.resource_class,
                           cte.resource_identifier,
                           cte.resource_type,
                           cte.effective_authorization_resource_identifier,
                           cte.effective_resource_class,
                           cte.effective_resource_identifier,
                           cte.effective_resource_type FROM cte
                ) AS $EXPANDED_RESOURCE_ALIAS
                    ON $RESOURCE_ACTION_GRANT_ALIAS.authorization_resource_identifier = $EXPANDED_RESOURCE_ALIAS.authorization_resource_identifier
                        OR $RESOURCE_ACTION_GRANT_ALIAS.authorization_resource_identifier = $EXPANDED_RESOURCE_ALIAS.effective_authorization_resource_identifier
                LEFT JOIN authorization_role_actions $ROLE_ACTION_ALIAS
                        ON $ROLE_ACTION_ALIAS.role_identifier = $RESOURCE_ACTION_GRANT_ALIAS.role_identifier
                JOIN authorization_available_resource_class_actions $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS
                        ON $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.identifier = COALESCE(
                            $RESOURCE_ACTION_GRANT_ALIAS.available_resource_class_action_identifier,
                            $ROLE_ACTION_ALIAS.available_resource_class_action_identifier)
                WHERE (
                    ($actionCriteria)
                    AND ($grantHolderCriteria)
                    AND ($actionsAvailabilityCriteria)
                )
                $additionalCriteria
                $groupByStatement
                $orderByStatement
                $limitAndOffsetStatement
        ";

        return [$sql, $parameterValues, $parameterTypes];
    }

    /**
     * @throws ApiError
     */
    private function validateResourceActionGrant(ResourceActionGrant $resourceActionGrant): void
    {
        $providedGrantHolderIdentifiers = array_filter([
            $resourceActionGrant->getUserIdentifier() !== null,
            $resourceActionGrant->getUserGroup() !== null,
            $resourceActionGrant->getDynamicUserGroupIdentifier() !== null,
        ]);
        if (1 !== count($providedGrantHolderIdentifiers)) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'Exactly one of userIdentifier, userGroup or dynamicUserGroupIdentifier must be provided',
                self::RESOURCE_ACTION_GRANT_INVALID_ERROR_ID);
        }

        if ($resourceActionGrant->getAuthorizationResource() === null) {
            throw new \RuntimeException('resource action grant is invalid: authorization resource must not be null');
        }

        $providedAllowedActionsIdentifiers = array_filter([
            $resourceActionGrant->getAction() !== null,
            $resourceActionGrant->getRole() !== null,
        ]);
        if (1 !== count($providedAllowedActionsIdentifiers)) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'Exactly one of \'action\' or \'role\' must be provided',
                self::RESOURCE_ACTION_GRANT_INVALID_ERROR_ID);
        }

        if ($action = $resourceActionGrant->getAction()) {
            $availableResourceClassAction = $this->getAvailableResourceClassAction(
                $resourceActionGrant->getResourceClass(),
                $action,
                AvailableResourceClassAction::getActionTypeForResourceIdentifier($resourceActionGrant->getResourceIdentifier())
            );

            if (null === $availableResourceClassAction) {
                throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                    "resource action is invalid: action '$action' is not defined for resource class '".
                    $resourceActionGrant->getResourceClass()."'",
                    self::RESOURCE_ACTION_GRANT_INVALID_ERROR_ID, [
                        $action,
                        $resourceActionGrant->getResourceClass(),
                    ]
                );
            }
            $resourceActionGrant->setAvailableResourceClassAction($availableResourceClassAction);
        }
    }

    /**
     * @param string|array|null $resourceIdentifiers
     */
    private function removeResourcesInternal(bool $removeGrantsOnly, ?string $resourceClass, mixed $resourceIdentifiers, ?int $resourceType): void
    {
        try {
            $RESOURCE_ACTION_GRANT_ALIAS = self::RESOURCE_ACTION_GRANT_ALIAS;
            $RESOURCE_ALIAS_INNER = self::RESOURCE_ALIAS.'2';
            $RESOURCE_ALIAS = self::RESOURCE_ALIAS;

            // NOTE: sqlite doesn't support DELETE with JOIN, so we use a subquery instead
            $resourceQueryBuilder = $this->entityManager->createQueryBuilder()
                ->select("$RESOURCE_ALIAS_INNER.identifier")
                ->from(AuthorizationResource::class, $RESOURCE_ALIAS_INNER);

            if (null !== $resourceClass) {
                $resourceQueryBuilder
                    ->andWhere("$RESOURCE_ALIAS_INNER.resourceClass = :resourceClass")
                    ->setParameter('resourceClass', $resourceClass);
            }
            if (null !== $resourceType) {
                $resourceQueryBuilder
                    ->andWhere("$RESOURCE_ALIAS_INNER.resourceType = :resourceType")
                    ->setParameter('resourceType', $resourceType);
            }
            if (null !== $resourceIdentifiers) {
                if (is_array($resourceIdentifiers)) {
                    if ([] === $resourceIdentifiers) {
                        $resourceQueryBuilder->andWhere('1=0');
                    } else {
                        $resourceQueryBuilder
                            ->andWhere("$RESOURCE_ALIAS_INNER.resourceIdentifier IN (:resourceIdentifiers)")
                            ->setParameter('resourceIdentifiers', $resourceIdentifiers);
                    }
                } elseif (is_string($resourceIdentifiers)) {
                    $resourceQueryBuilder
                        ->andWhere("$RESOURCE_ALIAS_INNER.resourceIdentifier = :resourceIdentifier")
                        ->setParameter('resourceIdentifier', $resourceIdentifiers);
                }
            }

            $queryBuilder = $this->entityManager->createQueryBuilder();
            if ($removeGrantsOnly) {
                $queryBuilder
                    ->delete(ResourceActionGrant::class, $RESOURCE_ACTION_GRANT_ALIAS)
                    ->where($queryBuilder->expr()->in(
                        "$RESOURCE_ACTION_GRANT_ALIAS.authorizationResource",
                        $resourceQueryBuilder->getDQL()))
                    ->setParameters($resourceQueryBuilder->getParameters());
            } else {
                $queryBuilder
                    ->delete(AuthorizationResource::class, $RESOURCE_ALIAS)
                    ->where($queryBuilder->expr()->in("$RESOURCE_ALIAS.identifier", $resourceQueryBuilder->getDQL()))
                    ->setParameters($resourceQueryBuilder->getParameters());
            }

            $queryBuilder->getQuery()->execute();
        } catch (\Throwable $throwable) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Resource could not be removed!', self::REMOVING_RESOURCE_FAILED_ERROR_ID,
                ['message' => $throwable->getMessage()]);
        }
    }

    /**
     * @throws ApiError
     */
    private function getOrCreateAuthorizationResource(string $resourceClass, string $resourceIdentifier,
        int $resourceType): AuthorizationResource
    {
        if (null === ($authorizationResource =
                $this->getAuthorizationResourceByResourceClassAndIdentifier($resourceClass, $resourceIdentifier, $resourceType))) {
            $this->validateResourceClassAndIdentifier($resourceClass, $resourceIdentifier);
            try {
                $authorizationResource = new AuthorizationResource();
                $authorizationResource->setIdentifier(Uuid::v7()->toRfc4122());
                $authorizationResource->setResourceClass($resourceClass);
                $authorizationResource->setResourceIdentifier($resourceIdentifier);
                $authorizationResource->setResourceType($resourceType);

                $this->entityManager->persist($authorizationResource);
                $this->entityManager->flush();

                return $authorizationResource;
            } catch (\Throwable $throwable) {
                $this->logger->error('Failed to add resource: '.$throwable->getMessage(), ['exception' => $throwable]);
                throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Resource could not be added! '.$throwable->getMessage(),
                    self::ADDING_RESOURCE_FAILED_ERROR_ID);
            }
        }

        return $authorizationResource;
    }

    /**
     * @param string|null $resourceClass May be null for manage action
     */
    private function getAvailableResourceClassAction(
        ?string $resourceClass,
        string $action,
        int $actionType): ?AvailableResourceClassAction
    {
        return self::getAvailableResourceClassActionStatic($this->entityManager,
            $resourceClass, $action, $actionType);
    }

    /**
     * @throws ApiError
     */
    private function validateResourceClassAndIdentifier(string $resourceClass, string $resourceIdentifier): void
    {
        if (str_contains($resourceClass, UserAttributeProvider::SEPARATOR)) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                sprintf("resource class must not contain the reserved character '%s'",
                    UserAttributeProvider::SEPARATOR));
        }
        if (str_contains($resourceIdentifier, UserAttributeProvider::SEPARATOR)) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                sprintf("resource identifier must not contain the reserved character '%s'",
                    UserAttributeProvider::SEPARATOR));
        }
    }

    /**
     * @throws ApiError
     */
    private function addResourceActionGrantInternal(ResourceActionGrant $resourceActionGrant): ResourceActionGrant
    {
        $this->validateResourceActionGrant($resourceActionGrant);

        $resourceActionGrant->setIdentifier(Uuid::v7()->toRfc4122());
        $resourceActionGrant->setDateCreated(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        try {
            $this->entityManager->persist($resourceActionGrant);
            $this->entityManager->flush();
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to add resource action grant: '.$throwable->getMessage(), ['exception' => $throwable]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Resource action grant could not be added!',
                self::ADDING_RESOURCE_ACTION_GRANT_FAILED_ERROR_ID);
        }

        $this->eventDispatcher->dispatch(new ResourceActionGrantAddedEvent($resourceActionGrant));

        return $resourceActionGrant;
    }

    private function getAuthorizationResourceCriteria(string $authorizationResourceAlias,
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?int $resourceType = null,
        mixed $authorizationResourceIdentifiers = null,
        array &$parameterValues = [], array &$parameterTypes = [], array $options = []): string
    {
        $COLLECTION_RESOURCE_IDENTIFIER = self::COLLECTION_RESOURCE_IDENTIFIER;

        $resourceClassCriteria = 'true';
        if ($resourceClass !== null) {
            $resourceClassCriteria = "$authorizationResourceAlias.resource_class = :resourceClass";
            $parameterValues['resourceClass'] = $resourceClass;
        }

        $resourceIdentifierCriteria = 'true';
        if ($resourceIdentifier !== null) {
            $resourceIdentifierCriteria = "$authorizationResourceAlias.resource_identifier = :resourceIdentifier";
            $parameterValues['resourceIdentifier'] = $resourceIdentifier;
        }

        $excludeCollectionResourceIdentifierCriteria = 'true';
        if ($options[self::EXCLUDE_COLLECTION_RESOURCE_OPTION] ?? false) {
            $excludeCollectionResourceIdentifierCriteria =
                "$authorizationResourceAlias.resource_identifier != '$COLLECTION_RESOURCE_IDENTIFIER'";
        }

        $authorizationResourceIdentifierCriteria = 'true';
        if ($authorizationResourceIdentifiers !== null) {
            if (is_array($authorizationResourceIdentifiers)) {
                $authorizationResourceIdentifierCriteria = "$authorizationResourceAlias.identifier IN (:authorizationResourceIdentifiers)";
                $parameterValues['authorizationResourceIdentifiers'] = $authorizationResourceIdentifiers;
                $parameterTypes['authorizationResourceIdentifiers'] = ArrayParameterType::BINARY;
            } else {
                $authorizationResourceIdentifierCriteria = "$authorizationResourceAlias.identifier = :authorizationResourceIdentifier";
                $parameterValues['authorizationResourceIdentifier'] = $authorizationResourceIdentifiers;
                $parameterTypes['authorizationResourceIdentifier'] = AuthorizationUuidBinaryType::NAME;
            }
        }

        $resourceTypeCriteria = 'true';
        if ($resourceType !== null) {
            switch ($resourceType) {
                case self::RESOURCE_RESOURCE_TYPE:
                    $resourceTypeCriteria =
                        $authorizationResourceAlias.'.'.AuthorizationResource::RESOURCE_TYPE_COLUMN.' = '.AuthorizationResource::RESOURCE_RESOURCE_TYPE;
                    break;
                case self::RESOURCE_GROUP_RESOURCE_TYPE:
                    $resourceTypeCriteria =
                        $authorizationResourceAlias.'.'.AuthorizationResource::RESOURCE_TYPE_COLUMN.' = '.AuthorizationResource::RESOURCE_GROUP_RESOURCE_TYPE;
                    break;
            }
        }

        return "($resourceClassCriteria AND $resourceIdentifierCriteria
            AND $excludeCollectionResourceIdentifierCriteria
            AND $authorizationResourceIdentifierCriteria AND $resourceTypeCriteria)";
    }

    private function getActionCriteria(string $alias,
        ?array $actions, array &$parameterValues, array &$parameterTypes): string
    {
        $actionCriteria = 'true';
        if (null !== $actions) {
            if ([] === $actions) {
                $actionCriteria = 'false';
            } else {
                $actionCriteria = "$alias.action IN (:actions)";
                $parameterValues['actions'] = $actions;
                $parameterTypes['actions'] = ArrayParameterType::STRING;
            }
        }

        return $actionCriteria;
    }

    private function getGrantHolderCriteria(string $resource_action_grant_alias,
        ?string $userIdentifier, mixed $userGroupIdentifiers, mixed $dynamicUserGroupIdentifiers,
        array &$parameterValues, array &$parameterTypes): string
    {
        $userCriteria = null;
        if ($userIdentifier !== null) {
            if ($userIdentifier === self::FALSE) {
                $userCriteria = 'false';
            } else {
                $userCriteria = "$resource_action_grant_alias.user_identifier = :userIdentifier";
                $parameterValues['userIdentifier'] = $userIdentifier;
            }
        }

        $userGroupCriteria = null;
        if ($userGroupIdentifiers !== null) {
            if ($userGroupIdentifiers === self::IS_NOT_NULL) {
                $userGroupCriteria = "$resource_action_grant_alias.user_group_identifier is not null";
            } else {
                assert(is_array($userGroupIdentifiers));
                if ([] === $userGroupIdentifiers) {
                    $userGroupCriteria = 'false';
                } else {
                    $userGroupCriteria = "$resource_action_grant_alias.user_group_identifier IN (:groupIdentifiers)";
                    $parameterValues['groupIdentifiers'] = UuidUtils::toBinaryUuids($userGroupIdentifiers);
                    $parameterTypes['groupIdentifiers'] = ArrayParameterType::BINARY;
                }
            }
        }

        $dynamicUserGroupCriteria = null;
        if ($dynamicUserGroupIdentifiers !== null) {
            if ($dynamicUserGroupIdentifiers === self::IS_NOT_NULL) {
                $dynamicUserGroupCriteria = "$resource_action_grant_alias.dynamic_user_group_identifier is not null";
            } else {
                assert(is_array($dynamicUserGroupIdentifiers));
                if ([] === $dynamicUserGroupIdentifiers) {
                    $dynamicUserGroupCriteria = 'false';
                } else {
                    $dynamicUserGroupCriteria = "$resource_action_grant_alias.dynamic_user_group_identifier IN (:dynamicGroupIdentifiers)";
                    $parameterValues['dynamicGroupIdentifiers'] = $dynamicUserGroupIdentifiers;
                    $parameterTypes['dynamicGroupIdentifiers'] = ArrayParameterType::STRING;
                }
            }
        }

        // NOTE: the grant holder criteria is logically combined with an OR conjunction
        $grantHolderCriteria = null;
        foreach ([$userCriteria, $userGroupCriteria, $dynamicUserGroupCriteria] as $criteria) {
            if ($criteria !== null) {
                $grantHolderCriteria .= ($grantHolderCriteria === null ? '(' : ' OR ').$criteria;
            }
        }
        if ($grantHolderCriteria !== null) {
            $grantHolderCriteria .= ')';
        } else {
            $grantHolderCriteria = 'true';
        }

        return $grantHolderCriteria;
    }

    private function hydrateResourceActionGrant(array $row): ResourceActionGrant
    {
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setIdentifier(
            UuidUtils::toStringUuid($row['identifier']));
        $resourceActionGrant->setIsInherited(
            $row['authorization_resource_identifier'] !== $row['effective_authorization_resource_identifier']);
        // NOTE: we don't hydrate the full authorization resource here, since we probably won't need it
        $resourceActionGrant->setResourceClass($row['resource_class']);
        $resourceActionGrant->setResourceIdentifier($row['resource_identifier']);
        $resourceActionGrant->setResourceType((int) $row['resource_type']);
        $resourceActionGrant->setAuthorizationResourceIdentifier(
            UuidUtils::toStringUuid($row['authorization_resource_identifier']));
        if ($roleIdentifier = $row['role_identifier']) {
            $resourceActionGrant->setRole(
                $this->entityManager->getRepository(Role::class)->find(UuidUtils::toStringUuid($roleIdentifier))
            );
        } else {
            $resourceActionGrant->setAction($row['action']);
        }
        $resourceActionGrant->setUserIdentifier($row['user_identifier']);
        $resourceActionGrant->setUserGroup(($groupIdentifier = $row['user_group_identifier']) ?
            $this->entityManager->getRepository(UserGroup::class)->find(
                UuidUtils::toStringUuid($groupIdentifier)) : null);
        $resourceActionGrant->setDynamicUserGroupIdentifier($row['dynamic_user_group_identifier']);
        $resourceActionGrant->setCreatorId($row['creator_id']);
        $resourceActionGrant->setDateCreated(new \DateTimeImmutable($row['date_created'], new \DateTimeZone('UTC')));
        $resourceActionGrant->setShareable((bool) $row['shareable']);
        if ($shareOfIdentifier = $row['share_of_identifier']) {
            $resourceActionGrant->setShareOf(
                $this->entityManager->getRepository(ResourceActionGrant::class)->find(UuidUtils::toStringUuid($shareOfIdentifier))
            );
        }

        return $resourceActionGrant;
    }
}
