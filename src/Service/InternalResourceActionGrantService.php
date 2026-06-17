<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Service;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassActionName;
use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Entity\GroupAuthorizationResourceMember;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Entity\Role;
use Dbp\Relay\AuthorizationBundle\Entity\RoleAction;
use Dbp\Relay\AuthorizationBundle\Entity\RoleName;
use Dbp\Relay\AuthorizationBundle\Event\ResourceActionGrantAddedEvent;
use Dbp\Relay\AuthorizationBundle\Helper\AuthorizationUuidBinaryType;
use Dbp\Relay\AuthorizationBundle\Helper\UuidUtils;
use Dbp\Relay\AuthorizationBundle\Rest\Common;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

/**
 * @internal
 */
class InternalResourceActionGrantService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const MAX_NUM_RESULTS_DEFAULT = 1024;

    public const MANAGE_ITEM_ACTION_UUID = '019ecac3-2eea-7297-ae1a-486de6fca628';
    public const MANAGE_COLLECTION_ACTION_UUID = '019ecac3-d095-7ae7-b10b-30bff78040a7';

    public const COLLECTION_RESOURCE_IDENTIFIER = 'null';
    public const IS_NOT_NULL = '@@@ __is_not_null__ @@@';

    public const GROUP_BY_RESOURCE_CLASS_OPTION = 'group_by_resource_class';
    public const SELECT_OPTION = 'select';
    public const ADDITIONAL_JOIN_STATEMENTS_OPTION = 'additional_join_statements';
    public const ADDITIONAL_CRITERIA_OPTION = 'additional_criteria';
    public const EXCLUDE_COLLECTION_RESOURCE_OPTION = 'exclude_collection_resource';

    public const RESOURCE_ACTION_GRANT_ALIAS = 'rag';
    public const AUTHORIZATION_RESOURCE_ALIAS = 'ar';
    public const AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS = 'ar_garm';

    public const GET_RESOURCE_ACTION_GRANTS = 'resource action grants';
    public const GET_AUTHORIZATION_RESOURCES = 'authorization resources';
    public const GET_AUTHORIZATION_RESOURCE_IDENTIFIERS = 'authorization resource identifiers';

    public const GETTING_RESOURCE_ACTION_GRANT_COLLECTION_FAILED_ERROR_ID = 'authorization:getting-resource-action-grant-collection-failed';

    private const ADDING_RESOURCE_ACTION_GRANT_FAILED_ERROR_ID = 'authorization:adding-resource-action-grant-failed';
    private const REMOVING_RESOURCE_ACTION_GRANT_FAILED_ERROR_ID = 'authorization:removing-resource-action-grant-failed';
    public const RESOURCE_ACTION_GRANT_INVALID_ACTION_MISSING_ERROR_ID = 'authorization:resource-action-grant-invalid-action-missing';
    public const RESOURCE_ACTION_GRANT_INVALID_ACTION_UNDEFINED_ERROR_ID = 'authorization:resource_action_grant-invalid-action-undefined';
    public const GETTING_RESOURCE_ACTION_GRANT_ITEM_FAILED_ERROR_ID = 'authorization:getting-resource-action-grant-item-failed';
    private const ADDING_RESOURCE_FAILED_ERROR_ID = 'authorization:adding-resource-failed';
    private const ADDING_AVAILABLE_RESOURCE_CLASS_ACTIONS_FAILED_ERROR_ID = 'authorization:adding-available-resource-class-actions-failed';
    private const REMOVING_RESOURCE_FAILED_ERROR_ID = 'authorization:removing-resource-failed';
    public const ADDING_RESOURCE_TO_GROUP_RESOURCE_FAILED_ERROR_ID = 'authorization:adding-resource-to-group-resource-failed';
    private const REMOVING_RESOURCE_FROM_GROUP_RESOURCE_FAILED_ERROR_ID = 'authorization:removing-resource-from-group-resource-failed';
    private const GETTING_RESOURCE_COLLECTION_FAILED_ERROR_ID = 'authorization:getting-resource-collection-failed';
    private const GETTING_RESOURCE_ITEM_FAILED_ERROR_ID = 'authorization:getting-resource-item-failed';
    private const AUTHORIZATION_RESOURCE_NOT_FOUND_ERROR_ID = 'authorization:authorization-resource-not-found';
    public const RESOURCE_ACTION_GRANT_INVALID_AUTHORIZATION_RESOURCE_MISSING_ERROR_ID =
        'authorization:resource-action-grant-invalid-authorization-resource-missing';
    private const ADDING_ROLE_FAILED_ERROR_ID = 'authorization:adding-role-failed';
    private const GETTING_ROLE_ITEM_FAILED_ERROR_ID = 'authorization:getting-role-item-failed';
    private const GETTING_ROLE_COLLECTION_FAILED_ERROR_ID = 'authorization:getting-role-collection-failed';

    private const GROUP_AUTHORIZATION_RESOURCE_MEMBER_ALIAS = 'garm';
    private const AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS = 'arca';

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

    public static function updateAvailableResourceClassActionsStatic(EntityManagerInterface $entityManager,
        string $resourceClass, array $itemActions, array $collectionActions): void
    {
        self::updateAvailableResourceClassActionsInternal($entityManager,
            $resourceClass, $itemActions, AvailableResourceClassAction::ITEM_ACTION_TYPE);
        self::updateAvailableResourceClassActionsInternal($entityManager,
            $resourceClass, $collectionActions, AvailableResourceClassAction::COLLECTION_ACTION_TYPE);
    }

    /**
     * @throw ApiError
     */
    public static function updateAvailableResourceClassActionsInternal(EntityManagerInterface $entityManager,
        string $resourceClass, array $availableActions, int $actionType): void
    {
        try {
            foreach ($availableActions as $action => $actionNames) {
                $availableResourceClassAction = $entityManager->getRepository(AvailableResourceClassAction::class)
                    ->findOneBy([
                        'resourceClass' => $resourceClass,
                        'action' => $action,
                        'actionType' => $actionType,
                    ]);
                if (null === $availableResourceClassAction) {
                    $availableResourceClassAction = new AvailableResourceClassAction();
                    $availableResourceClassAction->setIdentifier(Uuid::v7()->toRfc4122());
                    $availableResourceClassAction->setResourceClass($resourceClass);
                    $availableResourceClassAction->setAction($action);
                    $availableResourceClassAction->setActionType($actionType);
                }

                $names = [];
                foreach ($actionNames as $languageTag => $name) {
                    $availableGroupResourceActionName = $entityManager->getRepository(AvailableResourceClassActionName::class)
                        ->findOneBy([
                            'availableResourceClassAction' => $availableResourceClassAction,
                            'languageTag' => $languageTag,
                        ]);
                    if (null === $availableGroupResourceActionName) {
                        $availableGroupResourceActionName = new AvailableResourceClassActionName();
                        $availableGroupResourceActionName->setAvailableResourceClassAction($availableResourceClassAction);
                        $availableGroupResourceActionName->setLanguageTag($languageTag);
                    }
                    $availableGroupResourceActionName->setName($name);
                    $names[] = $availableGroupResourceActionName;
                }
                $availableResourceClassAction->setNames(new ArrayCollection($names));
                $entityManager->persist($availableResourceClassAction);
            }
            $entityManager->flush();
        } catch (\Throwable $throwable) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Available resource class actions could not be added!',
                self::ADDING_AVAILABLE_RESOURCE_CLASS_ACTIONS_FAILED_ERROR_ID);
        }
    }

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * @throws ApiError
     */
    public function setAvailableResourceClassActions(string $resourceClass,
        array $itemActions, array $collectionActions): void
    {
        self::updateAvailableResourceClassActionsStatic($this->entityManager,
            $resourceClass, $itemActions, $collectionActions);
    }

    public function ensureManageActionsAreAvailable(): void
    {
        if (null === $this->entityManager->getRepository(AvailableResourceClassAction::class)
                ->find(self::MANAGE_ITEM_ACTION_UUID)) {
            $manageItemAction = new AvailableResourceClassAction();
            $manageItemAction->setIdentifier(self::MANAGE_ITEM_ACTION_UUID);
            $manageItemAction->setAction(AuthorizationService::MANAGE_ACTION);
            $manageItemAction->setActionType(AvailableResourceClassAction::ITEM_ACTION_TYPE);
            $this->entityManager->persist($manageItemAction);
        }
        if (null === $this->entityManager->getRepository(AvailableResourceClassAction::class)
                ->find(self::MANAGE_COLLECTION_ACTION_UUID)) {
            $manageCollectionAction = new AvailableResourceClassAction();
            $manageCollectionAction->setIdentifier(self::MANAGE_COLLECTION_ACTION_UUID);
            $manageCollectionAction->setAction(AuthorizationService::MANAGE_ACTION);
            $manageCollectionAction->setActionType(AvailableResourceClassAction::COLLECTION_ACTION_TYPE);
            $this->entityManager->persist($manageCollectionAction);
        }
        $this->entityManager->flush();
    }

    /**
     * @throws ApiError
     */
    public function addRole(array $localizedRoleNames, array $roleActions): Role
    {
        $role = new Role();
        $role->setIdentifier(Uuid::v7()->toRfc4122());
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

            $availableResourceClassAction = $this->getAvailableResourceClassAction($resourceClass, $action, $actionType);
            if (null === $availableResourceClassAction) {
                throw new \RuntimeException(
                    "adding role failed: resource action '$action' (action type: '.$actionType.') is not defined for resource class '$resourceClass'");
            }
            $roleAction->setAvailableResourceClassAction($availableResourceClassAction);
            $role->getRoleActions()->add($roleAction);
        }

        try {
            $this->entityManager->persist($role);
            $this->entityManager->flush();
        } catch (\Throwable $throwable) {
            dump($throwable);
            $this->logger->error('Failed to add role: '.$throwable->getMessage(), ['exception' => $throwable]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Role could not be added!',
                self::ADDING_ROLE_FAILED_ERROR_ID);
        }

        return $role;
    }

    /**
     * @throws ApiError
     */
    public function getRoleByIdentifier(string $identifier): ?Role
    {
        try {
            return UuidV7::isValid($identifier) ?
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
    }

    /**
     * @return Role[]
     *
     * @throws ApiError
     */
    public function getRoles(int $firstItemIndex = 0, int $maxNumItemsPerPage = self::MAX_NUM_RESULTS_DEFAULT, array $filters = []): array
    {
        $ROLE_ALIAS = 'r';
        $ROLE_ACTION_ALIAS = 'ra';
        $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS = 'arca';

        $resourceClass = $filters[Common::RESOURCE_CLASS_QUERY_PARAMETER] ?? null;
        $actionType = $filters[Common::ACTION_TYPE_QUERY_PARAMETER] ?? null;

        try {
            // only get roles that have at least one role action for the given resource class and action type
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder->select($ROLE_ALIAS)
                ->from(Role::class, $ROLE_ALIAS)
                ->innerJoin(RoleAction::class, $ROLE_ACTION_ALIAS,
                    Join::WITH, "$ROLE_ALIAS.identifier = $ROLE_ACTION_ALIAS.role")
                ->innerJoin(AvailableResourceClassAction::class, $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS,
                    Join::WITH, "
                    $ROLE_ACTION_ALIAS.availableResourceClassAction = $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.identifier");

            if (null !== $resourceClass) {
                $queryBuilder
                    ->where($queryBuilder->expr()->eq($AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.'.resourceClass', ':resourceClass'))
                    ->setParameter(':resourceClass', $resourceClass);
            }
            if (null !== $actionType) {
                $queryBuilder
                    ->andWhere($queryBuilder->expr()->eq($AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.'.actionType', ':actionType'))
                    ->setParameter(':actionType', $actionType);
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
     * @throws ApiError
     */
    public function addResourceActionGrant(ResourceActionGrant $resourceActionGrant,
        ?string $currentUserIdentifier): ResourceActionGrant
    {
        return $this->addResourceActionGrantInternal($resourceActionGrant, $currentUserIdentifier);
    }

    /**
     * @throws ApiError
     */
    public function ensureAuthorizationResource(ResourceActionGrant $resourceActionGrant): void
    {
        if ($resourceActionGrant->getAuthorizationResource() === null) {
            if ($resourceActionGrant->getResourceClass() === null) {
                throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                    'Either authorization resource IRI or resource class/identifier must be provided',
                    self::RESOURCE_ACTION_GRANT_INVALID_AUTHORIZATION_RESOURCE_MISSING_ERROR_ID);
            }
            $authorizationResource = $this->getAuthorizationResourceByResourceClassAndIdentifier(
                $resourceActionGrant->getResourceClass(), $resourceActionGrant->getResourceIdentifier());
            if ($authorizationResource === null) {
                throw ApiError::withDetails(Response::HTTP_NOT_FOUND,
                    'authorization resource with given resource class and identifier not found', self::AUTHORIZATION_RESOURCE_NOT_FOUND_ERROR_ID);
            }
            $resourceActionGrant->setAuthorizationResource($authorizationResource);
        }
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
     * Parameters with null values will not be filtered on.
     *
     * @throws ApiError
     */
    public function removeResourceActionGrants(?string $resourceClass = null, ?string $resourceIdentifier = null,
        ?array $actions = null, ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null): void
    {
        $RESOURCE_ACTION_GRANT_ALIAS = self::RESOURCE_ACTION_GRANT_ALIAS;
        $AUTHORIZATION_RESOURCE_ALIAS = self::AUTHORIZATION_RESOURCE_ALIAS;
        $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS = self::AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS;

        $parameterValues = [];
        $parameterTypes = [];
        $authorizationResourceCriteria = $this->getAuthorizationResourceCriteria($AUTHORIZATION_RESOURCE_ALIAS,
            $resourceClass, $resourceIdentifier, null, $parameterValues, $parameterTypes);
        $actionCriteria = $this->getActionCriteria($AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS,
            $actions, $parameterValues, $parameterTypes);
        $grantHolderCriteria = $this->getGrantHolderCriteria($RESOURCE_ACTION_GRANT_ALIAS,
            $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers, $parameterValues, $parameterTypes);

        // NOTE: sqlite doesn't support DELETE ... FROM ... JOIN .... that's why we use a subquery
        $sql = "
            DELETE FROM authorization_resource_action_grants 
            WHERE identifier in (
                SELECT $RESOURCE_ACTION_GRANT_ALIAS.identifier 
                FROM authorization_resource_action_grants $RESOURCE_ACTION_GRANT_ALIAS 
                INNER JOIN authorization_resources $AUTHORIZATION_RESOURCE_ALIAS
                ON $RESOURCE_ACTION_GRANT_ALIAS.authorization_resource_identifier = $AUTHORIZATION_RESOURCE_ALIAS.identifier 
                INNER JOIN authorization_available_resource_class_actions $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS
                ON $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.identifier = $RESOURCE_ACTION_GRANT_ALIAS.available_resource_class_action_identifier
                WHERE $authorizationResourceCriteria 
                AND $actionCriteria 
                AND $grantHolderCriteria)
        ";

        try {
            $this->entityManager->getConnection()->executeQuery($sql, $parameterValues, $parameterTypes);
        } catch (\Throwable $throwable) {
            dump($throwable->getMessage());
            $this->logger->error('Failed to remove resource action grants: '.$throwable->getMessage(), ['exception' => $throwable]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Resource action grants could not be removed!', self::REMOVING_RESOURCE_ACTION_GRANT_FAILED_ERROR_ID);
        }
    }

    /**
     * @parram string|null $resourceIdentifier null refers to the collection of the respective resource class.
     *
     * @throws ApiError
     */
    public function addResourceActionGrantByResourceClassAndIdentifier(
        string $resourceClass, string $resourceIdentifier,
        string $action, ?string $userIdentifier, ?Group $group = null, ?string $dynamicGroupIdentifier = null,
        bool $shareable = false, ?string $currentUserIdentifier = null): ResourceActionGrant
    {
        $connection = $this->entityManager->getConnection();
        try {
            $connection->beginTransaction();

            $resourceActionGrant = new ResourceActionGrant();
            $resourceActionGrant->setAuthorizationResource(
                $this->getOrCreateAuthorizationResource($resourceClass, $resourceIdentifier)
            );
            $resourceActionGrant->setAction($action);
            $resourceActionGrant->setUserIdentifier($userIdentifier);
            $resourceActionGrant->setGroup($group);
            $resourceActionGrant->setDynamicGroupIdentifier($dynamicGroupIdentifier);
            $resourceActionGrant->setShareable($shareable);

            $this->addResourceActionGrantInternal($resourceActionGrant, $currentUserIdentifier);

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
     * @throws ApiError
     */
    public function removeAuthorizationResourceByResourceClassAndIdentifier(string $resourceClass, string $resourceIdentifier): void
    {
        $this->removeResourcesInternal($resourceClass, $resourceIdentifier);
    }

    /**
     * @param string[] $resourceIdentifiers
     *
     * @throws ApiError
     */
    public function removeAuthorizationResourcesByResourceClassAndIdentifier(string $resourceClass, array $resourceIdentifiers): void
    {
        $this->removeResourcesInternal($resourceClass, $resourceIdentifiers);
    }

    /**
     * @throws ApiError
     */
    public function addResourceToGroupResource(string $groupResourceClass, string $groupResourceIdentifier,
        string $resourceClass, string $resourceIdentifier): GroupAuthorizationResourceMember
    {
        // TODO: prevent circular references
        if ($groupResourceClass === $resourceClass && $groupResourceIdentifier === $resourceIdentifier) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'Group resource and member resource must not be identical',
                self::ADDING_RESOURCE_TO_GROUP_RESOURCE_FAILED_ERROR_ID);
        }

        $groupAuthorizationResourceMember = new GroupAuthorizationResourceMember();
        $groupAuthorizationResourceMember->setIdentifier(Uuid::v7()->toRfc4122());
        $groupAuthorizationResourceMember->setGroupAuthorizationResource(
            $this->getOrCreateAuthorizationResource($groupResourceClass, $groupResourceIdentifier)
        );
        $groupAuthorizationResourceMember->setMemberAuthorizationResource(
            $this->getOrCreateAuthorizationResource($resourceClass, $resourceIdentifier)
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

    public function removeResourceFromGroupResource(string $groupResourceClass, string $groupResourceIdentifier,
        string $resourceClass, string $resourceIdentifier): void
    {
        $GROUP_AUTHORIZATION_RESOURCE_MEMBER_ALIAS = self::GROUP_AUTHORIZATION_RESOURCE_MEMBER_ALIAS;
        $GROUP_AUTHORIZATION_RESOURCE_ALIAS = 'gar';
        $MEMBER_AUTHORIZATION_RESOURCE_ALIAS = 'mar';

        $innerQueryBuilder = $this->entityManager->createQueryBuilder();
        $innerQueryBuilder->select(self::GROUP_AUTHORIZATION_RESOURCE_MEMBER_ALIAS.'.identifier')
            ->from(GroupAuthorizationResourceMember::class, self::GROUP_AUTHORIZATION_RESOURCE_MEMBER_ALIAS)
            ->innerJoin(AuthorizationResource::class, $GROUP_AUTHORIZATION_RESOURCE_ALIAS, Join::WITH,
                "$GROUP_AUTHORIZATION_RESOURCE_MEMBER_ALIAS.groupAuthorizationResource = $GROUP_AUTHORIZATION_RESOURCE_ALIAS.identifier")
            ->innerJoin(AuthorizationResource::class, $MEMBER_AUTHORIZATION_RESOURCE_ALIAS, Join::WITH,
                "$GROUP_AUTHORIZATION_RESOURCE_MEMBER_ALIAS.memberAuthorizationResource = $MEMBER_AUTHORIZATION_RESOURCE_ALIAS.identifier")
            ->where($innerQueryBuilder->expr()->eq($GROUP_AUTHORIZATION_RESOURCE_ALIAS.'.resourceClass', ':groupResourceClass'))
            ->setParameter(':groupResourceClass', $groupResourceClass)
            ->andWhere($innerQueryBuilder->expr()->eq($MEMBER_AUTHORIZATION_RESOURCE_ALIAS.'.resourceClass', ':memberResourceClass'))
            ->setParameter(':memberResourceClass', $resourceClass)
            ->andWhere($innerQueryBuilder->expr()->eq($GROUP_AUTHORIZATION_RESOURCE_ALIAS.'.resourceIdentifier', ':sourceResourceIdentifier'))
            ->setParameter(':sourceResourceIdentifier', $groupResourceIdentifier)
            ->andWhere($innerQueryBuilder->expr()->eq($MEMBER_AUTHORIZATION_RESOURCE_ALIAS.'.resourceIdentifier', ':targetResourceIdentifier'))
            ->setParameter(':targetResourceIdentifier', $resourceIdentifier);

        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->delete(GroupAuthorizationResourceMember::class, self::GROUP_AUTHORIZATION_RESOURCE_MEMBER_ALIAS.'_2')
            ->where($queryBuilder->expr()->in(self::GROUP_AUTHORIZATION_RESOURCE_MEMBER_ALIAS.'_2.identifier', $innerQueryBuilder->getDQL()));

        $queryBuilder->setParameters($innerQueryBuilder->getParameters()); // doctrine forgets the parameters of the inner query builder...

        try {
            $queryBuilder->getQuery()->execute();
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to remove resource from group resource: '.$throwable->getMessage(), ['exception' => $throwable]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to remove resource from group resource!',
                self::REMOVING_RESOURCE_FROM_GROUP_RESOURCE_FAILED_ERROR_ID);
        }
    }

    public function getAuthorizationResourceByResourceClassAndIdentifier(string $resourceClass, ?string $resourceIdentifier): ?AuthorizationResource
    {
        try {
            return $this->entityManager
                ->getRepository(AuthorizationResource::class)
                ->findOneBy([
                    'resourceClass' => $resourceClass,
                    'resourceIdentifier' => $resourceIdentifier,
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
        ?string $resourceClass = null, ?string $resourceIdentifier = null, mixed $authorizationResourceIdentifiers = null,
        ?array $actions = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, ?int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT, array $options = []): array
    {
        return $this->getInternal($get, $resourceClass, $resourceIdentifier,
            $authorizationResourceIdentifiers, $actions,
            $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers, $firstResultIndex, $maxNumResults, $options);
    }

    /**
     *  NOTE: The grant holder criteria (userIdentifier, groupIdentifiers, dynamicGroupIdentifiers) is logically combined
     *  with an OR conjunction.
     *
     * @param string[]|string|null $groupIdentifiers
     * @param string[]|string|null $dynamicGroupIdentifiers
     *
     * @return ResourceActionGrant[]|AuthorizationResource[]
     *
     * @throws ApiError
     */
    public function getResourceActionGrantsForAuthorizationResourceIdentifier(
        string $authorizationResourceIdentifier,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, ?int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT, array $options = []): array
    {
        return $this->getInternal(self::GET_RESOURCE_ACTION_GRANTS,
            null, null, $authorizationResourceIdentifier, null,
            $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers, $firstResultIndex, $maxNumResults, $options);
    }

    /**
     * Parameters with null values will not be filtered on.
     * NOTE: The grant holder criteria (userIdentifier, groupIdentifiers, dynamicGroupIdentifiers) is logically combined
     * with an OR conjunction.
     *
     * @param string[]|string|null $groupIdentifiers
     * @param string[]|string|null $dynamicGroupIdentifiers
     *
     * @return ResourceActionGrant[]|AuthorizationResource[]
     *
     * @throws ApiError
     */
    public function getResourceActionGrantsForResourceClassAndIdentifier(
        ?string $resourceClass = null, ?string $resourceIdentifier = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, ?int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT, array $options = []): array
    {
        return $this->getInternal(self::GET_RESOURCE_ACTION_GRANTS,
            $resourceClass, $resourceIdentifier, null, null,
            $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers,
            $firstResultIndex, $maxNumResults, $options);
    }

    /**
     * Gets all resource action grants for one resource item page defined by the first result
     * index and the maximum number of result (page) items ordered by resource.
     *
     * Parameters with null values will not be filtered on.
     * NOTE: The grant holder criteria (userIdentifier, groupIdentifiers, dynamicGroupIdentifiers) is logically combined
     * with an OR conjunction.
     *
     * @return ResourceActionGrant[]
     *
     * @throws ApiError
     */
    public function getResourceActionGrantsForResourceItemPage(string $resourceClass,
        ?array $whereAuthorizationResourceActionsContainAnyOf = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, ?int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT, array $options = []): array
    {
        // * doctrine does not yet support joins with subqueries (SELECT ... INNER JOIN (SELECT ...))
        // * our current MySQL version doesn't yet support 'LIMIT & IN/ALL/ANY/SOME subquery' (SELECT ... WHERE foo IN (SELECT .... LIMIT 10)
        // -> we use two separate queries for now
        try {
            // first get the requested page of authorization resource ids
            $authorizationResourceIdPage = $this->getInternal(
                self::GET_AUTHORIZATION_RESOURCE_IDENTIFIERS,
                $resourceClass, null, null,
                $whereAuthorizationResourceActionsContainAnyOf,
                $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers,
                $firstResultIndex, $maxNumResults, $options);

            // then get ALL grants for the authorization resource ids page
            return $this->getInternal(
                self::GET_RESOURCE_ACTION_GRANTS,
                authorizationResourceIdentifiers: $authorizationResourceIdPage,
                userIdentifier: $userIdentifier, groupIdentifiers: $groupIdentifiers,
                dynamicGroupIdentifiers: $dynamicGroupIdentifiers,
                maxNumResults: null
            );
        } catch (\Exception $exception) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to get resource action grant collection!',
                self::GETTING_RESOURCE_ACTION_GRANT_COLLECTION_FAILED_ERROR_ID, ['message' => $exception->getMessage()]);
        }
    }

    private function getInternal(string $get,
        ?string $resourceClass = null, ?string $resourceIdentifier = null, mixed $authorizationResourceIdentifiers = null,
        ?array $actions = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, ?int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT, array $options = []): array
    {
        $GET_TYPE_RESOURCE_ACTION_GRANTS = 'rag';
        $GET_TYPE_AUTHORIZATION_RESOURCES = 'ars';
        $GET_TYPE_AUTHORIZATION_RESOURCE_WITH_RESOURCE_ACTION_GRANTS = 'ar';
        $GET_TYPE_AUTHORIZATION_RESOURCE_IDENTIFIERS = 'ari';

        $getType = match ($get) {
            self::GET_RESOURCE_ACTION_GRANTS => $GET_TYPE_RESOURCE_ACTION_GRANTS,
            self::GET_AUTHORIZATION_RESOURCES => $resourceIdentifier !== null ? $GET_TYPE_AUTHORIZATION_RESOURCE_WITH_RESOURCE_ACTION_GRANTS : $GET_TYPE_AUTHORIZATION_RESOURCES,
            self::GET_AUTHORIZATION_RESOURCE_IDENTIFIERS => $GET_TYPE_AUTHORIZATION_RESOURCE_IDENTIFIERS,
            default => throw new \InvalidArgumentException('Undefined get: '.$get),
        };
        $getInternal = $getType === $GET_TYPE_AUTHORIZATION_RESOURCE_WITH_RESOURCE_ACTION_GRANTS ?
            self::GET_RESOURCE_ACTION_GRANTS : $get;

        [$sql, $parameterValues, $parameterTypes] = $this->getQueryInternal(
            $getInternal,
            $resourceClass, $resourceIdentifier, $authorizationResourceIdentifiers,
            $actions,
            $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers,
            $firstResultIndex, $maxNumResults, $options);

        try {
            $results = [];
            /** @var AuthorizationResource|null $currentAuthorizationResource */
            $currentAuthorizationResource = null;
            foreach ($this->entityManager->getConnection()->executeQuery($sql, $parameterValues, $parameterTypes)->fetchAllAssociative() as $row) {
                switch ($getType) {
                    case $GET_TYPE_AUTHORIZATION_RESOURCES:
                        $results[] = $this->hydrateAuthorizationResource($row);
                        break;

                    case $GET_TYPE_RESOURCE_ACTION_GRANTS:
                    case $GET_TYPE_AUTHORIZATION_RESOURCE_WITH_RESOURCE_ACTION_GRANTS:
                        $resourceActionGrant = $this->hydrateResourceActionGrant($row);
                        if ($getType === $GET_TYPE_AUTHORIZATION_RESOURCE_WITH_RESOURCE_ACTION_GRANTS) {
                            if ($currentAuthorizationResource?->getIdentifier() !==
                                UuidUtils::toStringUuid($row['effective_authorization_resource_identifier'])) {
                                $currentAuthorizationResource = $this->hydrateAuthorizationResource($row);
                                $results[] = $currentAuthorizationResource;
                            }
                            $currentAuthorizationResource->getResourceActionGrants()->add($resourceActionGrant);
                        } else {
                            $results[] = $resourceActionGrant;
                        }
                        break;

                    case $GET_TYPE_AUTHORIZATION_RESOURCE_IDENTIFIERS:
                        $results[] = $row['effective_authorization_resource_identifier'];
                        break;

                    default:
                        throw new \InvalidArgumentException('Undefined get: '.$get);
                }
            }
        } catch (\Throwable $throwable) {
            $this->logger->error("Failed to get $get: ".$throwable->getMessage(), ['exception' => $throwable]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                "Failed to get $get",
                $get === self::GET_RESOURCE_ACTION_GRANTS ?
                    self::GETTING_RESOURCE_ACTION_GRANT_COLLECTION_FAILED_ERROR_ID :
                    self::GETTING_RESOURCE_COLLECTION_FAILED_ERROR_ID);
        }

        return $results;
    }

    private function hydrateAuthorizationResource(array $row): AuthorizationResource
    {
        $currentAuthorizationResource = new AuthorizationResource();
        $currentAuthorizationResource->setIdentifier(
            UuidUtils::toStringUuid($row['effective_authorization_resource_identifier']));
        $currentAuthorizationResource->setResourceClass($row['effective_resource_class']);
        $currentAuthorizationResource->setResourceIdentifier($row['effective_resource_identifier']);

        return $currentAuthorizationResource;
    }

    /**
     * @param string[]|string|null $groupIdentifiers
     * @param string[]|string|null $dynamicGroupIdentifiers
     *
     * @throws ApiError
     */
    private function getQueryInternal(string $get,
        ?string $resourceClass = null, ?string $resourceIdentifier = null, mixed $authorizationResourceIdentifiers = null,
        ?array $actions = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, ?int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT, array $options = []): array
    {
        $RESOURCE_ACTION_GRANT_ALIAS = self::RESOURCE_ACTION_GRANT_ALIAS;
        $AUTHORIZATION_RESOURCE_ALIAS = self::AUTHORIZATION_RESOURCE_ALIAS;
        $AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS = self::AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS;
        $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS = self::AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS;

        $parameterValues = [];
        $parameterTypes = [];

        $selectResourceActionGrants = "DISTINCT
            $RESOURCE_ACTION_GRANT_ALIAS.identifier, 
            $RESOURCE_ACTION_GRANT_ALIAS.authorization_resource_identifier, 
            $RESOURCE_ACTION_GRANT_ALIAS.user_identifier,
            $RESOURCE_ACTION_GRANT_ALIAS.group_identifier, 
            $RESOURCE_ACTION_GRANT_ALIAS.dynamic_group_identifier, 
            $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.action,
            $AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS.effective_resource_class, 
            $AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS.effective_resource_identifier,
            $AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS.effective_authorization_resource_identifier";
        $selectAuthorizationResources = "DISTINCT
            $AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS.effective_authorization_resource_identifier,
            $AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS.effective_resource_class,
            $AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS.effective_resource_identifier";
        $selectAuthorizationResourceIdentifiers = "DISTINCT
            $AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS.effective_authorization_resource_identifier";

        $select = $options[self::SELECT_OPTION] ?? null;
        $select ??= match ($get) {
            self::GET_RESOURCE_ACTION_GRANTS => $selectResourceActionGrants,
            self::GET_AUTHORIZATION_RESOURCES => $selectAuthorizationResources,
            self::GET_AUTHORIZATION_RESOURCE_IDENTIFIERS => $selectAuthorizationResourceIdentifiers,
            default => throw new \InvalidArgumentException('Undefined get: '.$get),
        };

        $authorizationResourceCriteria = $this->getAuthorizationResourceCriteria($AUTHORIZATION_RESOURCE_ALIAS,
            $resourceClass, $resourceIdentifier, $authorizationResourceIdentifiers,
            $parameterValues, $parameterTypes, $options);

        $actionCriteria = $this->getActionCriteria($AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS,
            $actions, $parameterValues, $parameterTypes);

        $grantHolderCriteria = $this->getGrantHolderCriteria($RESOURCE_ACTION_GRANT_ALIAS,
            $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers,
            $parameterValues, $parameterTypes);

        $groupBy = '';
        if ($options[self::GROUP_BY_RESOURCE_CLASS_OPTION] ?? false) {
            $groupBy = "GROUP BY $AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS.effective_resource_class";
        }

        $orderBy = '';
        if ($get === self::GET_RESOURCE_ACTION_GRANTS) {
            $orderBy = "ORDER BY $AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS.effective_authorization_resource_identifier";
        }

        $additionalJoinStatements = $options[self::ADDITIONAL_JOIN_STATEMENTS_OPTION] ?? '';

        $additionalCriteria = '';
        if ($additionalCriteriaOption = ($options[self::ADDITIONAL_CRITERIA_OPTION] ?? null)) {
            $additionalCriteria = $additionalCriteriaOption[0];
            $parameterValues = array_merge($parameterValues, $additionalCriteriaOption[1] ?? []);
            $parameterTypes = array_merge($parameterTypes, $additionalCriteriaOption[2] ?? []);
        }

        $actionsAvailabilityCriteria = "
            (
                $AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS.effective_resource_identifier = 'null' 
                AND $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.action_type = 1
            ) OR (
                $AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS.effective_resource_identifier != 'null' 
                AND $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.action_type = 0
            )
        ";

        $limitAndOffset = ($maxNumResults !== null ? "LIMIT $maxNumResults" : '').
            ($firstResultIndex > 0 ? " OFFSET $firstResultIndex" : '');

        $sql = "SELECT $select
                FROM authorization_resource_action_grants $RESOURCE_ACTION_GRANT_ALIAS
                INNER JOIN (
                    WITH RECURSIVE cte AS (
                        SELECT ar_garm_0.identifier,
                               ar_garm_0.group_authorization_resource_identifier, 
                               ar_garm_0.member_authorization_resource_identifier,
                               $AUTHORIZATION_RESOURCE_ALIAS.identifier AS effective_authorization_resource_identifier,
                               $AUTHORIZATION_RESOURCE_ALIAS.resource_class AS effective_resource_class,
                               $AUTHORIZATION_RESOURCE_ALIAS.resource_identifier AS effective_resource_identifier
                        FROM authorization_resources $AUTHORIZATION_RESOURCE_ALIAS
                        LEFT JOIN authorization_group_resource_members ar_garm_0
                        ON ar_garm_0.member_authorization_resource_identifier = $AUTHORIZATION_RESOURCE_ALIAS.identifier
                        WHERE $authorizationResourceCriteria
                        UNION ALL
                        SELECT ar_garm_n.identifier, ar_garm_n.group_authorization_resource_identifier, ar_garm_n.member_authorization_resource_identifier,
                               cte.effective_authorization_resource_identifier, cte.effective_resource_class, cte.effective_resource_identifier
                        FROM authorization_group_resource_members ar_garm_n
                        INNER JOIN cte ON ar_garm_n.member_authorization_resource_identifier = cte.group_authorization_resource_identifier
                    )
                    SELECT group_authorization_resource_identifier, cte.effective_authorization_resource_identifier,
                           cte.effective_resource_class, cte.effective_resource_identifier FROM cte
                ) AS $AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS
                    ON $RESOURCE_ACTION_GRANT_ALIAS.authorization_resource_identifier = $AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS.group_authorization_resource_identifier
                        OR $RESOURCE_ACTION_GRANT_ALIAS.authorization_resource_identifier = $AUTHORIZATION_RESOURCE_GROUP_AUTHORIZATION_RESOURCE_MEMBER_JOIN_ALIAS.effective_authorization_resource_identifier
                INNER JOIN authorization_available_resource_class_actions $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS 
                    ON $AVAILABLE_RESOURCE_CLASS_ACTION_ALIAS.identifier = $RESOURCE_ACTION_GRANT_ALIAS.available_resource_class_action_identifier
                $additionalJoinStatements
                WHERE (
                    ($actionCriteria)
                    AND ($grantHolderCriteria)
                    AND ($actionsAvailabilityCriteria)
                )
                $additionalCriteria
                $groupBy
                $orderBy
                $limitAndOffset
        ";

        return [$sql, $parameterValues, $parameterTypes];
    }

    /**
     * @throws ApiError
     */
    private function validateResourceActionGrant(ResourceActionGrant $resourceActionGrant): void
    {
        if ($resourceActionGrant->getAuthorizationResource() === null) {
            throw new \RuntimeException('resource action grant is invalid: authorization resource must not be null');
        }

        $action = $resourceActionGrant->getAction();
        if (!$action) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'resource action grant is invalid: \'action\' is required', self::RESOURCE_ACTION_GRANT_INVALID_ACTION_MISSING_ERROR_ID, ['action']);
        }

        $availableResourceClassAction = $this->getAvailableResourceClassAction(
            $actionResourceClass = $resourceActionGrant->getActionResourceClass(),
            $action,
            $resourceActionGrant->getActionType()
        );

        if (null === $availableResourceClassAction) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                "resource action is invalid: action '$action' is not defined for resource class '$actionResourceClass'",
                self::RESOURCE_ACTION_GRANT_INVALID_ACTION_UNDEFINED_ERROR_ID, [$action]);
        }
        $resourceActionGrant->setAvailableResourceClassAction($availableResourceClassAction);
    }

    public function getAvailableResourceClassActions(string $resourceClass): array
    {
        $itemActions = [];
        $collectionActions = [];

        /** @var AvailableResourceClassAction $availableResourceClassAction */
        foreach ($this->entityManager->getRepository(AvailableResourceClassAction::class)->findBy([
            'resourceClass' => $resourceClass,
        ]) as $availableResourceClassAction) {
            $names = [];
            /** @var AvailableResourceClassActionName $availableResourceClassActionName */
            foreach ($availableResourceClassAction->getNames() as $availableResourceClassActionName) {
                $names[$availableResourceClassActionName->getLanguageTag()] = $availableResourceClassActionName->getName();
            }
            if ($availableResourceClassAction->getActionType() === AvailableResourceClassAction::ITEM_ACTION_TYPE) {
                $itemActions[$availableResourceClassAction->getAction()] = $names;
            } elseif ($availableResourceClassAction->getActionType() === AvailableResourceClassAction::COLLECTION_ACTION_TYPE) {
                $collectionActions[$availableResourceClassAction->getAction()] = $names;
            }
        }

        // DESIGN NOTE: we require at least one custom action to be defined for a resource class to be 'available'
        if ([] !== $itemActions || [] !== $collectionActions) {
            $itemActions[AuthorizationService::MANAGE_ACTION] = [
                'en' => 'Manage',
                'de' => 'Verwalten',
            ];
            $collectionActions[AuthorizationService::MANAGE_ACTION] = [
                'en' => 'Manage',
                'de' => 'Verwalten',
            ];
        }

        return [$itemActions, $collectionActions];
    }

    /**
     * @param string|array|null $resourceIdentifiers
     */
    private function removeResourcesInternal(string $resourceClass, mixed $resourceIdentifiers): void
    {
        try {
            $RESOURCE_ALIAS = 'r';
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder
                ->delete(AuthorizationResource::class, $RESOURCE_ALIAS)
                ->where($queryBuilder->expr()->eq("$RESOURCE_ALIAS.resourceClass", ':resourceClass'))
                ->setParameter(':resourceClass', $resourceClass);

            if (is_array($resourceIdentifiers)) {
                $queryBuilder
                    ->andWhere($queryBuilder->expr()->in("$RESOURCE_ALIAS.resourceIdentifier", ':resourceIdentifiers'))
                    ->setParameter(':resourceIdentifiers', $resourceIdentifiers);
            } elseif (is_string($resourceIdentifiers)) {
                $queryBuilder
                    ->andWhere($queryBuilder->expr()->eq("$RESOURCE_ALIAS.resourceIdentifier", ':resourceIdentifier'))
                    ->setParameter(':resourceIdentifier', $resourceIdentifiers);
            } else {
                $queryBuilder
                    ->andWhere($queryBuilder->expr()->isNull("$RESOURCE_ALIAS.resourceIdentifier"));
            }
            $queryBuilder->getQuery()->execute();
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Resource could not be removed!', self::REMOVING_RESOURCE_FAILED_ERROR_ID,
                ['message' => $e->getMessage()]);
        }
    }

    /**
     * @throws ApiError
     */
    private function getOrCreateAuthorizationResource(string $resourceClass, ?string $resourceIdentifier): AuthorizationResource
    {
        if (($authorizationResource = $this->getAuthorizationResourceByResourceClassAndIdentifier($resourceClass, $resourceIdentifier)) === null) {
            $this->validateResourceClassAndIdentifier($resourceClass, $resourceIdentifier);
            try {
                $authorizationResource = new AuthorizationResource();
                $authorizationResource->setIdentifier(Uuid::v7()->toRfc4122());
                $authorizationResource->setResourceClass($resourceClass);
                $authorizationResource->setResourceIdentifier($resourceIdentifier);

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
    private function addResourceActionGrantInternal(ResourceActionGrant $resourceActionGrant,
        ?string $currentUserIdentifier): ResourceActionGrant
    {
        $this->validateResourceActionGrant($resourceActionGrant);

        $resourceActionGrant->setIdentifier(Uuid::v7()->toRfc4122());
        $resourceActionGrant->setCreatorId($currentUserIdentifier);
        $resourceActionGrant->setDateCreated(new \DateTime());
        try {
            $this->entityManager->persist($resourceActionGrant);
            $this->entityManager->flush();
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to add resource action grant: '.$throwable->getMessage(), ['exception' => $throwable]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Resource action grant could not be added!',
                self::ADDING_RESOURCE_ACTION_GRANT_FAILED_ERROR_ID);
        }

        $this->eventDispatcher->dispatch(new ResourceActionGrantAddedEvent(
            $resourceActionGrant->getAuthorizationResource()->getResourceClass(),
            $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier(),
            $resourceActionGrant->getAction(),
            $resourceActionGrant->getUserIdentifier(),
            $resourceActionGrant->getGroup()?->getIdentifier(),
            $resourceActionGrant->getDynamicGroupIdentifier()));

        return $resourceActionGrant;
    }

    private function getAuthorizationResourceCriteria(string $authorizationResourceAlias,
        ?string $resourceClass, ?string $resourceIdentifier, mixed $authorizationResourceIdentifiers,
        array &$parameterValues, array &$parameterTypes, array $options = []): string
    {
        $resourceClassCriteria = 'true';
        if ($resourceClass !== null) {
            $resourceClassCriteria = "$authorizationResourceAlias.resource_class = :resourceClass";
            $parameterValues['resourceClass'] = $resourceClass;
        }

        $resourceIdentifierCriteria = 'true';
        if ($resourceIdentifier !== null) {
            if ($options[self::EXCLUDE_COLLECTION_RESOURCE_OPTION] ?? false) {
                $COLLECTION_RESOURCE_IDENTIFIER = self::COLLECTION_RESOURCE_IDENTIFIER;
                $resourceIdentifierCriteria = "$authorizationResourceAlias.resource_identifier != '$COLLECTION_RESOURCE_IDENTIFIER'";
            } else {
                $resourceIdentifierCriteria = "$authorizationResourceAlias.resource_identifier = :resourceIdentifier";
                $parameterValues['resourceIdentifier'] = $resourceIdentifier;
            }
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

        return "($resourceClassCriteria AND $resourceIdentifierCriteria AND $authorizationResourceIdentifierCriteria)";
    }

    private function getActionCriteria(string $alias,
        ?array $actions, array &$parameterValues, array &$parameterTypes): string
    {
        $actionCriteria = 'true';
        if (false === empty($actions)) {
            $actionCriteria = "$alias.action IN (:actions)";
            $parameterValues['actions'] = $actions;
            $parameterTypes['actions'] = ArrayParameterType::STRING;
        }

        return $actionCriteria;
    }

    private function getGrantHolderCriteria(string $resource_action_grant_alias,
        ?string $userIdentifier, mixed $groupIdentifiers, mixed $dynamicGroupIdentifiers,
        array &$parameterValues, array &$parameterTypes): string
    {
        $userCriteria = null;
        if ($userIdentifier !== null) {
            $userCriteria = "$resource_action_grant_alias.user_identifier = :userIdentifier";
            $parameterValues['userIdentifier'] = $userIdentifier;
        }

        $groupCriteria = null;
        if ($groupIdentifiers !== null) {
            if ($groupIdentifiers === self::IS_NOT_NULL) {
                $groupCriteria = "$resource_action_grant_alias.group_identifier is not null";
            } else {
                assert(is_array($groupIdentifiers));
                $groupCriteria = "$resource_action_grant_alias.group_identifier IN (:groupIdentifiers)";
                $parameterValues['groupIdentifiers'] = UuidUtils::toBinaryUuids($groupIdentifiers);
                $parameterTypes['groupIdentifiers'] = ArrayParameterType::BINARY;
            }
        }

        $dynamicGroupCriteria = null;
        if ($dynamicGroupIdentifiers !== null) {
            if ($dynamicGroupIdentifiers === self::IS_NOT_NULL) {
                $dynamicGroupCriteria = "$resource_action_grant_alias.dynamic_group_identifier is not null";
            } else {
                assert(is_array($dynamicGroupIdentifiers));
                $dynamicGroupCriteria = "$resource_action_grant_alias.dynamic_group_identifier IN (:dynamicGroupIdentifiers)";
                $parameterValues['dynamicGroupIdentifiers'] = $dynamicGroupIdentifiers;
                $parameterTypes['dynamicGroupIdentifiers'] = ArrayParameterType::STRING;
            }
        }

        // NOTE: the grant holder criteria is logically combined with an OR conjunction
        $grantHolderCriteria = null;
        foreach ([$userCriteria, $groupCriteria, $dynamicGroupCriteria] as $criteria) {
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
        $resourceActionGrant->setResourceClass($row['effective_resource_class']);
        $resourceActionGrant->setResourceIdentifier($row['effective_resource_identifier']);
        $resourceActionGrant->setAuthorizationResourceIdentifier(
            UuidUtils::toStringUuid($row['effective_authorization_resource_identifier']));
        $resourceActionGrant->setAction($row['action']);
        $resourceActionGrant->setUserIdentifier($row['user_identifier']);
        $resourceActionGrant->setGroup($row['group_identifier'] ?
            $this->entityManager->getRepository(Group::class)->find(
                UuidUtils::toStringUuid($row['group_identifier'])) : null);
        $resourceActionGrant->setDynamicGroupIdentifier($row['dynamic_group_identifier']);

        return $resourceActionGrant;
    }
}
