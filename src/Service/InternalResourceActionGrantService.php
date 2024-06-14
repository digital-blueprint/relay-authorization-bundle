<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Service;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Event\GetAvailableResourceClassActionsEvent;
use Dbp\Relay\AuthorizationBundle\Helper\AuthorizationUuidBinaryType;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class InternalResourceActionGrantService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const IS_NULL = '@@@ __is_null__ @@@';
    public const IS_NOT_NULL = '@@@ __is_not_null__ @@@';

    public const ITEM_ACTIONS_TYPE = 'ia';
    public const COLLECTION_ACTIONS_TYPE = 'ca';

    public const ONLY_GET_UNIQUE_RESOURCE_ACTIONS_OPTION = 'only_get_unique_resource_actions';
    public const ORDER_BY_AUTHORIZATION_RESOURCE_OPTION = 'order_by_authorization_resource';

    private const ADDING_RESOURCE_ACTION_GRANT_FAILED_ERROR_ID = 'authorization:adding-resource-action-grant-failed';
    private const REMOVING_RESOURCE_ACTION_GRANT_FAILED_ERROR_ID = 'authorization:removing-resource-action-grant-failed';
    public const RESOURCE_ACTION_GRANT_INVALID_ACTION_MISSING_ERROR_ID = 'authorization:resource-action-grant-invalid-action-missing';
    public const RESOURCE_ACTION_GRANT_INVALID_ACTION_UNDEFINED_ERROR_ID = 'authorization:resource_action_grant-invalid-action-undefined';
    private const GETTING_RESOURCE_ACTION_GRANT_COLLECTION_FAILED_ERROR_ID = 'authorization:getting-resource-action-grant-collection-failed';
    public const GETTING_RESOURCE_ACTION_GRANT_ITEM_FAILED_ERROR_ID = 'authorization:getting-resource-action-grant-item-failed';
    private const ADDING_RESOURCE_FAILED_ERROR_ID = 'authorization:adding-resource-failed';
    private const REMOVING_RESOURCE_FAILED_ERROR_ID = 'authorization:removing-resource-failed';
    private const GETTING_RESOURCE_COLLECTION_FAILED_ERROR_ID = 'authorization:getting-resource-collection-failed';
    private const GETTING_RESOURCE_ITEM_FAILED_ERROR_ID = 'authorization:getting-resource-item-failed';
    private const RESOURCE_INVALID_ERROR_ID = 'authorization:resource-invalid';

    private const RESOURCE_ACTION_GRANT_ALIAS = 'rag';
    private const AUTHORIZATION_RESOURCE_ALIAS = 'ar';
    private const AUTHORIZATION_RESOURCE_IDENTIFIER_ALIAS = self::AUTHORIZATION_RESOURCE_ALIAS.'.identifier';

    private const GET_RESOURCE_ACTION_GRANTS = 'rag';
    private const GET_AUTHORIZATION_RESOURCES = 'ar';
    private const GET_RESOURCE_CLASSES = 'rc';

    private EntityManagerInterface $entityManager;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(EntityManagerInterface $entityManager, EventDispatcherInterface $eventDispatcher)
    {
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * @throws ApiError
     */
    public function addResourceActionGrant(ResourceActionGrant $resourceActionGrant): ResourceActionGrant
    {
        $this->validateResourceActionGrant($resourceActionGrant);

        $resourceActionGrant->setIdentifier(Uuid::uuid7()->toString());
        try {
            $this->entityManager->persist($resourceActionGrant);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Resource action grant could not be added!',
                self::ADDING_RESOURCE_ACTION_GRANT_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }

        return $resourceActionGrant;
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

    /**
     * @parram string|null $resourceIdentifier null refers to the collection of the respective resource class.
     *
     * @throws ApiError
     */
    public function addResourceAndManageResourceGrantForUser(string $resourceClass, ?string $resourceIdentifier, string $userIdentifier): ResourceActionGrant
    {
        try {
            $resource = new AuthorizationResource();
            $resource->setIdentifier(Uuid::uuid7()->toString());
            $resource->setResourceClass($resourceClass);
            $resource->setResourceIdentifier($resourceIdentifier);

            $this->entityManager->getConnection()->beginTransaction();

            $this->entityManager->persist($resource);
            $this->entityManager->flush();

            $resourceActionGrant = new ResourceActionGrant();
            $resourceActionGrant->setAuthorizationResource($resource);
            $resourceActionGrant->setAction(AuthorizationService::MANAGE_ACTION);
            $resourceActionGrant->setUserIdentifier($userIdentifier);
            $this->addResourceActionGrant($resourceActionGrant);

            $this->entityManager->getConnection()->commit();
        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollback();

            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Resource could not be added!',
                self::ADDING_RESOURCE_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
        }

        return $resourceActionGrant;
    }

    /**
     * @throws ApiError
     */
    public function removeAuthorizationResource(string $resourceClass, string $resourceIdentifier): void
    {
        $this->removeResourcesInternal($resourceClass, $resourceIdentifier);
    }

    /**
     * @param string[] $resourceIdentifiers
     *
     * @throws ApiError
     */
    public function removeAuthorizationResources(string $resourceClass, array $resourceIdentifiers): void
    {
        $this->removeResourcesInternal($resourceClass, $resourceIdentifiers);
    }

    public function getAuthorizationResource(string $identifier)
    {
        try {
            return $this->entityManager
                ->getRepository(AuthorizationResource::class)
                ->find($identifier);
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get resource item!',
                self::GETTING_RESOURCE_ITEM_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
        }
    }

    /**
     * @throws ApiError
     */
    public function getResourceActionGrant(string $identifier): ?ResourceActionGrant
    {
        try {
            return $this->entityManager
                ->getRepository(ResourceActionGrant::class)
                ->find($identifier);
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get resource action item!',
                self::GETTING_RESOURCE_ACTION_GRANT_ITEM_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
        }
    }

    /**
     * @return AuthorizationResource[]
     *
     * @throws ApiError
     */
    public function getAuthorizationResourcesUserIsAuthorizedToRead(?string $resourceClass = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, int $maxNumResults = 1024): array
    {
        return $this->getResourceActionGrantsUserIsAuthorizedToReadInternal(self::GET_AUTHORIZATION_RESOURCES,
            $resourceClass, null, $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers,
            $firstResultIndex, $maxNumResults);
    }

    /**
     * @return string[]
     *
     * @throws ApiError
     */
    public function getResourceClassesUserIsAuthorizedToRead(
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, int $maxNumResults = 1024, array $options = []): array
    {
        return array_map(function ($authorizationResource) {
            return $authorizationResource->getResourceClass();
        }, $this->getResourceActionGrantsUserIsAuthorizedToReadInternal(self::GET_RESOURCE_CLASSES,
            null, null, $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers,
            $firstResultIndex, $maxNumResults));
    }

    /**
     * @return ResourceActionGrant[]
     *
     * @throws ApiError
     */
    public function getResourceActionGrantsUserIsAuthorizedToRead(
        ?string $resourceClass = null, ?string $resourceIdentifier = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, int $maxNumResults = 1024): array
    {
        return $this->getResourceActionGrantsUserIsAuthorizedToReadInternal(self::GET_RESOURCE_ACTION_GRANTS,
            $resourceClass, $resourceIdentifier, $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers,
            $firstResultIndex, $maxNumResults);
    }

    //    /**
    //     * @return Group[]
    //     *
    //     * @throws ApiError
    //     */
    //    public function getGroupsUserIsAuthorizedToRead(
    //        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
    //        int $firstResultIndex = 0, int $maxNumResults = 1024, array $filters = []): array
    //    {
    //        $AUTHORIZATION_RESOURCE_ALIAS = self::AUTHORIZATION_RESOURCE_ALIAS;
    //
    //        $GROUP_ALIAS = 'g';
    //        $actionsType = self::ITEM_ACTIONS_TYPE;
    //        $resourceClass = AuthorizationService::GROUP_RESOURCE_CLASS;
    //        $actions = [AuthorizationService::MANAGE_ACTION, AuthorizationService::READ_GROUP_ACTION];
    //
    //        $this->entityManager->getConfiguration()->addCustomStringFunction('UNHEX', Unhex::class);
    //        $this->entityManager->getConfiguration()->addCustomStringFunction('REPLACE', Replace::class);
    //
    //        // first get the requested page of authorization resource ids
    //        $authorizationResourceIdPageQueryBuilder = $this->createAuthorizationResourceQueryBuilderInternal(
    //            $GROUP_ALIAS, $resourceClass,
    //            $actionsType === self::COLLECTION_ACTIONS_TYPE ? self::IS_NULL : self::IS_NOT_NULL,
    //            null, $actions,
    //            $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers);
    //
    //        $authorizationResourceIdPageQueryBuilder
    //            ->innerJoin(Group::class, $GROUP_ALIAS, Join::WITH,
    //                "UNHEX(REPLACE($AUTHORIZATION_RESOURCE_ALIAS.resourceIdentifier, '-', '')) = $GROUP_ALIAS.identifier");
    //        if ($groupNameFilter = $filters[GroupService::SEARCH_FILTER_OPTION] ?? null) {
    //            $authorizationResourceIdPageQueryBuilder
    //                ->andWhere($this->entityManager->getExpressionBuilder()->like("$GROUP_ALIAS.name", ':groupNameLike'))
    //                ->setParameter(':groupNameLike', "%$groupNameFilter%");
    //        }
    //
    //        return $authorizationResourceIdPageQueryBuilder
    //            ->getQuery()
    //            ->setFirstResult($firstResultIndex)
    //            ->setMaxResults($maxNumResults)
    //            ->getResult();
    //    }

    /**
     * @param string[]|string|null $groupIdentifiers
     * @param string[]|string|null $dynamicGroupIdentifiers
     *
     * @return ResourceActionGrant[]|AuthorizationResource[]
     *
     * @throws ApiError
     */
    public function getResourceActionGrantsForAuthorizationResourceIdentifier(
        ?string $authorizationResourceIdentifier = null, ?array $actions = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, int $maxNumResults = 1024, array $options = []): array
    {
        return $this->getResourceActionGrantsInternal(
            null, null, $authorizationResourceIdentifier, $actions,
            $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers, $firstResultIndex, $maxNumResults, $options);
    }

    /**
     * @param string[]|string|null $groupIdentifiers
     * @param string[]|string|null $dynamicGroupIdentifiers
     *
     * @return ResourceActionGrant[]|AuthorizationResource[]
     *
     * @throws ApiError
     */
    public function getResourceActionGrantsForResourceClassAndIdentifier(
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?array $actions = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, int $maxNumResults = 1024, array $options = []): array
    {
        return $this->getResourceActionGrantsInternal(
            $resourceClass, $resourceIdentifier, null, $actions,
            $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers, $firstResultIndex, $maxNumResults, $options);
    }

    /**
     * @param string[]|string|null $groupIdentifiers
     * @param string[]|string|null $dynamicGroupIdentifiers
     *
     * @return AuthorizationResource[]
     *
     * @throws ApiError
     */
    public function getAuthorizationResourcesForResourceClassAndIdentifier(
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?array $actions = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, int $maxNumResults = 1024, array $options = []): array
    {
        try {
            return $this->createAuthorizationResourceQueryBuilderInternal(self::AUTHORIZATION_RESOURCE_ALIAS,
                $resourceClass, $resourceIdentifier, null,
                $actions, $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers)
                ->getQuery()
                ->setFirstResult($firstResultIndex)
                ->setMaxResults($maxNumResults)
                ->getResult();
        } catch (\Exception $exception) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to get resource action grant collection!',
                self::GETTING_RESOURCE_COLLECTION_FAILED_ERROR_ID, ['message' => $exception->getMessage()]);
        }
    }

    /**
     * Gets all resource action grants for an authorization resource subset (page) defined by the first result
     * index and the maximum number of result (page) items ordered by resource.
     *
     * @return ResourceActionGrant[]
     *
     * @throws ApiError
     */
    public function getResourceActionGrantsForAuthorizationResourcePage(string $resourceClass,
        string $actionsType = self::ITEM_ACTIONS_TYPE, ?array $actions = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, int $maxNumResults = 1024): array
    {
        // * doctrine does not yet support joins with subqueries (SELECT ... INNER JOIN (SELECT ...))
        // * our current MySQL version doesn't yet support 'LIMIT & IN/ALL/ANY/SOME subquery' (SELECT ... WHERE foo IN (SELECT .... LIMIT 10)
        // -> we use two separate queries for now
        try {
            // first get the requested page of authorization resource ids
            $authorizationResourceIdPage = $this->createAuthorizationResourceQueryBuilderInternal(
                self::AUTHORIZATION_RESOURCE_IDENTIFIER_ALIAS, $resourceClass,
                $actionsType === self::COLLECTION_ACTIONS_TYPE ? self::IS_NULL : self::IS_NOT_NULL,
                null, $actions, $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers)
                ->getQuery()
                ->setFirstResult($firstResultIndex)
                ->setMaxResults($maxNumResults)
                ->getSingleColumnResult();

            // then get all grants for the authorization resource ids page
            $RESOURCE_ACTION_GRANT_ALIAS = self::RESOURCE_ACTION_GRANT_ALIAS;
            $resourceActionGrantQueryBuilder = $this->createResourceActionGrantQueryBuilder(
                $RESOURCE_ACTION_GRANT_ALIAS, $resourceClass,
                $actionsType === self::COLLECTION_ACTIONS_TYPE ? self::IS_NULL : self::IS_NOT_NULL,
                null, $actions, $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers);

            $resourceActionGrantQueryBuilder
                ->andWhere($resourceActionGrantQueryBuilder->expr()->in(
                    "$RESOURCE_ACTION_GRANT_ALIAS.authorizationResource", ':authorizationResourceIdPage'))
                ->setParameter('authorizationResourceIdPage', $authorizationResourceIdPage, ArrayParameterType::BINARY)
                ->orderBy("$RESOURCE_ACTION_GRANT_ALIAS.authorizationResource");

            return $resourceActionGrantQueryBuilder
                ->getQuery()
                ->getResult();
        } catch (\Exception $exception) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to get resource action grant collection!',
                self::GETTING_RESOURCE_ACTION_GRANT_COLLECTION_FAILED_ERROR_ID, ['message' => $exception->getMessage()]);
        }
    }

    public function createAuthorizationResourceQueryBuilder(string $select = self::AUTHORIZATION_RESOURCE_ALIAS,
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?array $actions = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null): QueryBuilder
    {
        return $this->createAuthorizationResourceQueryBuilderInternal($select, $resourceClass, $resourceIdentifier,
            null, $actions, $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers);
    }

    /**
     * @return ResourceActionGrant[]|AuthorizationResource[]
     *
     * @throws ApiError
     */
    private function getResourceActionGrantsUserIsAuthorizedToReadInternal(string $get = self::GET_RESOURCE_ACTION_GRANTS,
        ?string $resourceClass = null, ?string $resourceIdentifier = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, int $maxNumResults = 1024): array
    {
        // Get all grants
        // * that the user is a holder of (personally or by (dynamic) group)
        // * from all resources that the user manages
        try {
            // create a subquery getting the authorization resource IDs that the user manages:
            $subqueryBuilder = $this->createResourceActionGrantQueryBuilder(
                'IDENTITY('.self::RESOURCE_ACTION_GRANT_ALIAS.'.authorizationResource)',
                $resourceClass, $resourceIdentifier, null, [AuthorizationService::MANAGE_ACTION],
                $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers);

            // NOTE: resource action grant alias for subquery and main query must differ
            $RESOURCE_ACTION_GRANT_ALIAS = self::RESOURCE_ACTION_GRANT_ALIAS.'_2';
            $AUTHORIZATION_RESOURCE_ALIAS = self::AUTHORIZATION_RESOURCE_ALIAS.'_2';

            $queryBuilder = $this->entityManager->createQueryBuilder();
            if ($get === self::GET_RESOURCE_ACTION_GRANTS) {
                $queryBuilder
                    ->select($RESOURCE_ACTION_GRANT_ALIAS)
                    ->from(ResourceActionGrant::class, $RESOURCE_ACTION_GRANT_ALIAS);
            } else {
                $queryBuilder
                    ->select($AUTHORIZATION_RESOURCE_ALIAS)
                    ->from(ResourceActionGrant::class, $RESOURCE_ACTION_GRANT_ALIAS)
                    ->innerJoin(AuthorizationResource::class, $AUTHORIZATION_RESOURCE_ALIAS, Join::WITH,
                        "$RESOURCE_ACTION_GRANT_ALIAS.authorizationResource = $AUTHORIZATION_RESOURCE_ALIAS.identifier");
                if ($get === self::GET_AUTHORIZATION_RESOURCES) {
                    // groupBy is required for setMaxResults to work properly, because of possible duplicates in the joined collection
                    $queryBuilder
                        ->groupBy("$AUTHORIZATION_RESOURCE_ALIAS.identifier");
                } elseif ($get === self::GET_RESOURCE_CLASSES) {
                    $queryBuilder
                        ->groupBy("$AUTHORIZATION_RESOURCE_ALIAS.resourceClass");
                }
            }
            self::addGrantHolderCriteria($queryBuilder, $RESOURCE_ACTION_GRANT_ALIAS,
                $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers);
            $queryBuilder
                ->andWhere($queryBuilder->expr()->neq("$RESOURCE_ACTION_GRANT_ALIAS.action", ':manageAction'))
                ->orWhere($queryBuilder->expr()->in("$RESOURCE_ACTION_GRANT_ALIAS.authorizationResource", $subqueryBuilder->getDQL()))
                ->setParameters($subqueryBuilder->getParameters()) // NOTE: bound subquery parameters are lost when using getDQL()
                ->setParameter(':manageAction', AuthorizationService::MANAGE_ACTION);

            return $queryBuilder
                ->getQuery()
                ->setFirstResult($firstResultIndex)
                ->setMaxResults($maxNumResults)
                ->getResult();
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to get resource action grant collection!',
                self::GETTING_RESOURCE_ACTION_GRANT_COLLECTION_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Since exclusively userIdentifier, or group or dynamicGroupIdentifier are set in a ResourceAction grant,
     * we combine them with an OR conjunction.
     *
     * @param string[]|string|null $groupIdentifiers
     * @param string[]|string|null $dynamicGroupIdentifiers
     *
     * @return ResourceActionGrant[]|AuthorizationResource[]
     *
     * @throws ApiError
     */
    private function getResourceActionGrantsInternal(
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?string $authorizationResourceIdentifier = null,
        ?array $actions = null, ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, int $maxNumResults = 1024, array $options = []): array
    {
        $RESOURCE_ACTION_GRANT_ALIAS = self::RESOURCE_ACTION_GRANT_ALIAS;
        try {
            $queryBuilder = $this->createResourceActionGrantQueryBuilder(
                $RESOURCE_ACTION_GRANT_ALIAS,
                $resourceClass, $resourceIdentifier, $authorizationResourceIdentifier,
                $actions, $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers);

            if ($options[self::ONLY_GET_UNIQUE_RESOURCE_ACTIONS_OPTION] ?? false) {
                $queryBuilder
                    ->addGroupBy("$RESOURCE_ACTION_GRANT_ALIAS.action")
                    ->addGroupBy("$RESOURCE_ACTION_GRANT_ALIAS.authorizationResource");
            }
            if ($options[self::ORDER_BY_AUTHORIZATION_RESOURCE_OPTION] ?? false) {
                $queryBuilder
                    ->orderBy("$RESOURCE_ACTION_GRANT_ALIAS.authorizationResource");
            }

            return $queryBuilder
                ->getQuery()
                ->setFirstResult($firstResultIndex)
                ->setMaxResults($maxNumResults)
                ->getResult();
        } catch (ApiError $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to get resource action grant collection!',
                self::GETTING_RESOURCE_ACTION_GRANT_COLLECTION_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
        }
    }

    private function createAuthorizationResourceQueryBuilderInternal(string $select = self::AUTHORIZATION_RESOURCE_ALIAS,
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?string $authorizationResourceIdentifier = null,
        ?array $actions = null, ?string $userIdentifier = null, mixed $groupIdentifiers = null,
        mixed $dynamicGroupIdentifiers = null): QueryBuilder
    {
        try {
            return $this->createResourceActionGrantQueryBuilder($select,
                $resourceClass, $resourceIdentifier, $authorizationResourceIdentifier,
                $actions, $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers)
                ->groupBy(self::AUTHORIZATION_RESOURCE_IDENTIFIER_ALIAS);
        } catch (\Exception $exception) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to get resource action grant collection!',
                self::GETTING_RESOURCE_COLLECTION_FAILED_ERROR_ID, ['message' => $exception->getMessage()]);
        }
    }

    private function createResourceActionGrantQueryBuilder(string $select = self::RESOURCE_ACTION_GRANT_ALIAS,
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?string $authorizationResourceIdentifier = null,
        ?array $actions = null, ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null): QueryBuilder
    {
        $AUTHORIZATION_RESOURCE_ALIAS = self::AUTHORIZATION_RESOURCE_ALIAS;
        $RESOURCE_ACTION_GRANT_ALIAS = self::RESOURCE_ACTION_GRANT_ALIAS;

        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->select($select)
            ->from(ResourceActionGrant::class, $RESOURCE_ACTION_GRANT_ALIAS);

        if ($authorizationResourceIdentifier !== null) {
            $queryBuilder
                ->where($queryBuilder->expr()->eq("$RESOURCE_ACTION_GRANT_ALIAS.authorizationResource", ':authorizationResourceIdentifier'))
                ->setParameter(':authorizationResourceIdentifier', $authorizationResourceIdentifier, AuthorizationUuidBinaryType::NAME);
        } elseif ($resourceClass !== null || $resourceIdentifier !== null
            || $select === self::AUTHORIZATION_RESOURCE_ALIAS || $select === self::AUTHORIZATION_RESOURCE_IDENTIFIER_ALIAS) {
            $queryBuilder
                ->innerJoin(AuthorizationResource::class, $AUTHORIZATION_RESOURCE_ALIAS, Join::WITH,
                    "$RESOURCE_ACTION_GRANT_ALIAS.authorizationResource = $AUTHORIZATION_RESOURCE_ALIAS.identifier");
            self::addAuthorizationResourceCriteria($queryBuilder, $AUTHORIZATION_RESOURCE_ALIAS, $resourceClass, $resourceIdentifier);
        }

        if ($actions !== null) {
            if (count($actions) === 1) {
                $queryBuilder
                    ->andWhere($queryBuilder->expr()->eq("$RESOURCE_ACTION_GRANT_ALIAS.action", ':action'))
                    ->setParameter(':action', $actions[0]);
            } else {
                $queryBuilder
                    ->andWhere($queryBuilder->expr()->in("$RESOURCE_ACTION_GRANT_ALIAS.action", ':action'))
                    ->setParameter(':action', $actions);
            }
        }

        self::addGrantHolderCriteria($queryBuilder, $RESOURCE_ACTION_GRANT_ALIAS, $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers);

        return $queryBuilder;
    }

    /**
     * @throws ApiError
     */
    private function validateResourceActionGrant(ResourceActionGrant $resourceActionGrant): void
    {
        $action = $resourceActionGrant->getAction();
        if ($action === null) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'resource action grant is invalid: \'action\' is required', self::RESOURCE_ACTION_GRANT_INVALID_ACTION_MISSING_ERROR_ID, ['action']);
        }
        [$itemActions, $collectionActions] = $this->getAvailableResourceClassActions(
            $resourceActionGrant->getAuthorizationResource()->getResourceClass());

        if ($resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() !== null) {
            $actionsToCheck = &$itemActions;
        } else {
            $actionsToCheck = &$collectionActions;
        }
        if (!in_array($action, $actionsToCheck, true)) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                "resource action is invalid: action '$action' is not defined for this resource class", self::RESOURCE_ACTION_GRANT_INVALID_ACTION_UNDEFINED_ERROR_ID, [$action]);
        }
    }

    public function getAvailableResourceClassActions(string $resourceClass): array
    {
        $getActionsEvent = new GetAvailableResourceClassActionsEvent($resourceClass);
        $this->eventDispatcher->dispatch($getActionsEvent);

        $itemActions = $getActionsEvent->getItemActions();
        if ($itemActions !== null
            && !in_array(AuthorizationService::MANAGE_ACTION, $itemActions, true)) {
            $itemActions[] = AuthorizationService::MANAGE_ACTION;
        }
        $collectionActions = $getActionsEvent->getCollectionActions();
        if ($collectionActions !== null
            && !in_array(AuthorizationService::MANAGE_ACTION, $collectionActions, true)) {
            $collectionActions[] = AuthorizationService::MANAGE_ACTION;
        }

        return [$itemActions, $collectionActions];
    }

    /**
     * @throws ApiError
     */
    private function validateResource(AuthorizationResource $resource): void
    {
        if ($resource->getResourceClass() === null) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'resource action invalid: \'resourceClass\' is required', self::RESOURCE_INVALID_ERROR_ID, ['resourceClass']);
        }
    }

    /**
     * @param string|array $resourceIdentifiers
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
            } else {
                $queryBuilder
                    ->andWhere($queryBuilder->expr()->eq("$RESOURCE_ALIAS.resourceIdentifier", ':resourceIdentifier'))
                    ->setParameter(':resourceIdentifier', $resourceIdentifiers);
            }
            $queryBuilder->getQuery()->execute();
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Resource could not be removed!', self::REMOVING_RESOURCE_FAILED_ERROR_ID,
                ['message' => $e->getMessage()]);
        }
    }

    private static function addGrantHolderCriteria(QueryBuilder $queryBuilder, string $RESOURCE_ACTION_GRANT_ALIAS, ?string $userIdentifier, mixed $groupIdentifiers, mixed $dynamicGroupIdentifiers): void
    {
        $orClause = $queryBuilder->expr()->orX();
        if ($userIdentifier !== null) {
            $orClause
                ->add($queryBuilder->expr()->eq("$RESOURCE_ACTION_GRANT_ALIAS.userIdentifier", ':userIdentifier'));
            $queryBuilder->setParameter(':userIdentifier', $userIdentifier);
        }
        if ($groupIdentifiers !== null) {
            if ($groupIdentifiers === self::IS_NOT_NULL) {
                $orClause
                    ->add($queryBuilder->expr()->isNotNull("$RESOURCE_ACTION_GRANT_ALIAS.group"));
            } else {
                // There seem to be issues with doctrine and arrays of binary parameters:
                // https://github.com/ramsey/uuid-doctrine/issues/18
                // https://github.com/ramsey/uuid-doctrine/issues/164
                $orClause
                    ->add($queryBuilder->expr()->in("IDENTITY($RESOURCE_ACTION_GRANT_ALIAS.group)", ':groupIdentifiers'));
                $queryBuilder->setParameter(':groupIdentifiers',
                    AuthorizationUuidBinaryType::toBinaryUuids($groupIdentifiers), ArrayParameterType::BINARY);
            }
        }
        if ($dynamicGroupIdentifiers !== null) {
            if ($dynamicGroupIdentifiers === self::IS_NOT_NULL) {
                $orClause
                    ->add($queryBuilder->expr()->isNotNull("$RESOURCE_ACTION_GRANT_ALIAS.dynamicGroupIdentifier"));
            } else {
                $orClause
                    ->add($queryBuilder->expr()->in("$RESOURCE_ACTION_GRANT_ALIAS.dynamicGroupIdentifier", ':dynamicGroupIdentifiers'));
                $queryBuilder->setParameter(':dynamicGroupIdentifiers', $dynamicGroupIdentifiers);
            }
        }
        if ($orClause->count() > 0) {
            $queryBuilder->andWhere($orClause);
        }
    }

    private static function addAuthorizationResourceCriteria(QueryBuilder $queryBuilder, string $AUTHORIZATION_RESOURCE_ALIAS,
        ?string $resourceClass, ?string $resourceIdentifier): void
    {
        if ($resourceClass !== null) {
            $queryBuilder
                ->where($queryBuilder->expr()->eq("$AUTHORIZATION_RESOURCE_ALIAS.resourceClass", ':resourceClass'))
                ->setParameter(':resourceClass', $resourceClass);
        }
        if ($resourceIdentifier !== null) {
            switch ($resourceIdentifier) {
                case self::IS_NULL:
                    $queryBuilder
                        ->andWhere($queryBuilder->expr()->isNull("$AUTHORIZATION_RESOURCE_ALIAS.resourceIdentifier"));
                    break;
                case self::IS_NOT_NULL:
                    $queryBuilder
                        ->andWhere($queryBuilder->expr()->isNotNull("$AUTHORIZATION_RESOURCE_ALIAS.resourceIdentifier"));
                    break;
                default:
                    $queryBuilder
                        ->andWhere($queryBuilder->expr()->eq("$AUTHORIZATION_RESOURCE_ALIAS.resourceIdentifier", ':resourceIdentifier'))
                        ->setParameter(':resourceIdentifier', $resourceIdentifier);
            }
        }
    }
}
