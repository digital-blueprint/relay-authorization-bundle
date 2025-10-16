<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Service;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\GrantedActions;
use Dbp\Relay\AuthorizationBundle\Entity\GrantInheritance;
use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Event\GetAvailableResourceClassActionsEvent;
use Dbp\Relay\AuthorizationBundle\Event\ResourceActionGrantAddedEvent;
use Dbp\Relay\AuthorizationBundle\Helper\AuthorizationUuidBinaryType;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
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

    public const GROUP_BY_RESOURCE_CLASS_OPTION = 'group_by_resource_class';
    public const SELECT_OPTION = 'select';
    public const ADDITIONAL_JOIN_STATEMENTS_OPTION = 'additional_join_statements';
    public const ADDITIONAL_CRITERIA_OPTION = 'additional_criteria';

    public const RESOURCE_ACTION_GRANT_ALIAS = 'rag';
    public const AUTHORIZATION_RESOURCE_ALIAS = 'ar';
    public const AUTHORIZATION_RESOURCE_GRANT_INHERITANCE_JOIN_ALIAS = 'ar_gi';

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
    private const REMOVING_RESOURCE_FAILED_ERROR_ID = 'authorization:removing-resource-failed';
    public const ADDING_GRANT_INHERITANCE_FAILED_ERROR_ID = 'authorization:adding-grant-inheritance-failed';
    private const REMOVING_GRANT_INHERITANCE_FAILED_ERROR_ID = 'authorization:removing-grant-inheritance-failed';
    private const GETTING_RESOURCE_COLLECTION_FAILED_ERROR_ID = 'authorization:getting-resource-collection-failed';
    private const GETTING_RESOURCE_ITEM_FAILED_ERROR_ID = 'authorization:getting-resource-item-failed';
    private const AUTHORIZATION_RESOURCE_NOT_FOUND_ERROR_ID = 'authorization:authorization-resource-not-found';
    public const RESOURCE_ACTION_GRANT_INVALID_AUTHORIZATION_RESOURCE_MISSING =
        'authorization:resource-action-grant-invalid-authorization-resource-missing';

    public const AUTHORIZATION_RESOURCE_IDENTIFIER_ALIAS = self::AUTHORIZATION_RESOURCE_ALIAS.'.identifier';
    private const GRANT_INHERITANCE_ALIAS = 'gi';

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
    public function addResourceActionGrant(ResourceActionGrant $resourceActionGrant): ResourceActionGrant
    {
        return $this->addResourceActionGrantInternal($resourceActionGrant);
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
                    self::RESOURCE_ACTION_GRANT_INVALID_AUTHORIZATION_RESOURCE_MISSING);
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

        $parameterValues = [];
        $parameterTypes = [];
        $authorizationResourceCriteria = $this->getAuthorizationResourceCriteria($AUTHORIZATION_RESOURCE_ALIAS,
            $resourceClass, $resourceIdentifier, null, $parameterValues, $parameterTypes);
        $actionCriteria = $this->getActionCriteria($RESOURCE_ACTION_GRANT_ALIAS,
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
                WHERE $authorizationResourceCriteria 
                AND $actionCriteria 
                AND $grantHolderCriteria)";

        try {
            $this->entityManager->getConnection()->executeQuery($sql, $parameterValues, $parameterTypes);
        } catch (\Throwable $throwable) {
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
    public function addResourceActionGrantByResourceClassAndIdentifier(string $resourceClass, ?string $resourceIdentifier, string $action,
        ?string $userIdentifier, ?Group $group = null, ?string $dynamicGroupIdentifier = null): ResourceActionGrant
    {
        $connection = $this->entityManager->getConnection();
        try {
            $connection->beginTransaction();

            $resourceActionGrant = new ResourceActionGrant();
            $resourceActionGrant->setAuthorizationResource($this->getOrCreateAuthorizationResource($resourceClass, $resourceIdentifier));
            $resourceActionGrant->setAction($action);
            $resourceActionGrant->setUserIdentifier($userIdentifier);
            $resourceActionGrant->setGroup($group);
            $resourceActionGrant->setDynamicGroupIdentifier($dynamicGroupIdentifier);

            $this->addResourceActionGrantInternal($resourceActionGrant);

            $connection->commit();
        } catch (\Throwable $throwable) {
            if ($connection->isTransactionActive()) {
                $connection->rollback();
            }
            if ($throwable instanceof ApiError) {
                throw $throwable;
            }
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Resource could not be added!',
                self::ADDING_RESOURCE_FAILED_ERROR_ID, ['message' => $throwable->getMessage()]);
        }

        return $resourceActionGrant;
    }

    /**
     * @throws ApiError
     */
    public function removeAuthorizationResourceByResourceClassAndIdentifier(string $resourceClass, ?string $resourceIdentifier): void
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
    public function addGrantInheritance(string $sourceResourceClass, ?string $sourceResourceIdentifier,
        string $targetResourceClass, ?string $targetResourceIdentifier): GrantInheritance
    {
        if ($sourceResourceClass === $targetResourceClass && $sourceResourceIdentifier === $targetResourceIdentifier) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'Grant inheritance source and target resource must not be identical',
                self::ADDING_GRANT_INHERITANCE_FAILED_ERROR_ID);
        }

        $grantInheritance = new GrantInheritance();
        $grantInheritance->setIdentifier(Uuid::uuid7()->toString());
        $grantInheritance->setSourceAuthorizationResource(
            $this->getOrCreateAuthorizationResource($sourceResourceClass, $sourceResourceIdentifier)
        );
        $grantInheritance->setTargetAuthorizationResource(
            $this->getOrCreateAuthorizationResource($targetResourceClass, $targetResourceIdentifier)
        );

        try {
            $this->entityManager->persist($grantInheritance);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to add grant inheritance!',
                self::ADDING_GRANT_INHERITANCE_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
        }

        return $grantInheritance;
    }

    public function removeGrantInheritance(string $sourceResourceClass, ?string $sourceResourceIdentifier,
        string $targetResourceClass, ?string $targetResourceIdentifier): void
    {
        $GRANT_INHERITANCE_ALIAS = self::GRANT_INHERITANCE_ALIAS;
        $SOURCE_AUTHORIZATION_RESOURCE_ALIAS = 'sar';
        $TARGET_AUTHORIZATION_RESOURCE_ALIAS = 'tar';

        $innerQueryBuilder = $this->entityManager->createQueryBuilder();
        $innerQueryBuilder->select(self::GRANT_INHERITANCE_ALIAS.'.identifier')
            ->from(GrantInheritance::class, self::GRANT_INHERITANCE_ALIAS)
            ->innerJoin(AuthorizationResource::class, $SOURCE_AUTHORIZATION_RESOURCE_ALIAS, Join::WITH,
                "$GRANT_INHERITANCE_ALIAS.sourceAuthorizationResource = $SOURCE_AUTHORIZATION_RESOURCE_ALIAS.identifier")
            ->innerJoin(AuthorizationResource::class, $TARGET_AUTHORIZATION_RESOURCE_ALIAS, Join::WITH,
                "$GRANT_INHERITANCE_ALIAS.targetAuthorizationResource = $TARGET_AUTHORIZATION_RESOURCE_ALIAS.identifier")
            ->where($innerQueryBuilder->expr()->eq($SOURCE_AUTHORIZATION_RESOURCE_ALIAS.'.resourceClass', ':sourceResourceClass'))
            ->setParameter(':sourceResourceClass', $sourceResourceClass)
            ->andWhere($innerQueryBuilder->expr()->eq($TARGET_AUTHORIZATION_RESOURCE_ALIAS.'.resourceClass', ':targetResourceClass'))
            ->setParameter(':targetResourceClass', $targetResourceClass);
        if ($sourceResourceIdentifier !== null) {
            $innerQueryBuilder
                ->andWhere($innerQueryBuilder->expr()->eq($SOURCE_AUTHORIZATION_RESOURCE_ALIAS.'.resourceIdentifier', ':sourceResourceIdentifier'))
                ->setParameter(':sourceResourceIdentifier', $sourceResourceIdentifier);
        } else {
            $innerQueryBuilder
                ->andWhere($innerQueryBuilder->expr()->isNull($SOURCE_AUTHORIZATION_RESOURCE_ALIAS.'.resourceIdentifier'));
        }
        if ($targetResourceIdentifier !== null) {
            $innerQueryBuilder
                ->andWhere($innerQueryBuilder->expr()->eq($TARGET_AUTHORIZATION_RESOURCE_ALIAS.'.resourceIdentifier', ':targetResourceIdentifier'))
                ->setParameter(':targetResourceIdentifier', $targetResourceIdentifier);
        } else {
            $innerQueryBuilder
                ->andWhere($innerQueryBuilder->expr()->isNull($TARGET_AUTHORIZATION_RESOURCE_ALIAS.'.resourceIdentifier'));
        }

        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->delete(GrantInheritance::class, self::GRANT_INHERITANCE_ALIAS.'_2')
            ->where($queryBuilder->expr()->in(self::GRANT_INHERITANCE_ALIAS.'_2.identifier', $innerQueryBuilder->getDQL()));

        $queryBuilder->setParameters($innerQueryBuilder->getParameters()); // doctrine forgets the parameters of the inner query builder...

        try {
            $queryBuilder->getQuery()->execute();
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to remove grant inheritance: '.$throwable->getMessage(), ['exception' => $throwable]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to remove grant inheritance!',
                self::REMOVING_GRANT_INHERITANCE_FAILED_ERROR_ID);
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
            return Uuid::isValid($identifier) ? $this->entityManager
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
        int $firstResultIndex = 0, int $maxNumResults = 1024, array $options = []): array
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
        int $firstResultIndex = 0, int $maxNumResults = 1024, array $options = []): array
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
        int $firstResultIndex = 0, int $maxNumResults = 1024, array $options = []): array
    {
        return $this->getInternal(self::GET_RESOURCE_ACTION_GRANTS,
            $resourceClass, $resourceIdentifier, null, null,
            $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers,
            $firstResultIndex, $maxNumResults, $options);
    }

    /**
     * Gets all resource action grants for an authorization resource subset (page) defined by the first result
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
    public function getResourceActionGrantsForAuthorizationResourcePage(string $resourceClass,
        string $actionsType = self::ITEM_ACTIONS_TYPE, ?array $whereAuthorizationResourceActionsContainAnyOf = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, int $maxNumResults = 1024): array
    {
        // * doctrine does not yet support joins with subqueries (SELECT ... INNER JOIN (SELECT ...))
        // * our current MySQL version doesn't yet support 'LIMIT & IN/ALL/ANY/SOME subquery' (SELECT ... WHERE foo IN (SELECT .... LIMIT 10)
        // -> we use two separate queries for now
        try {
            // first get the requested page of authorization resource ids
            $authorizationResourceIdPage = $this->getInternal(
                self::GET_AUTHORIZATION_RESOURCE_IDENTIFIERS, $resourceClass,
                $actionsType === self::COLLECTION_ACTIONS_TYPE ? self::IS_NULL : self::IS_NOT_NULL,
                null, $whereAuthorizationResourceActionsContainAnyOf,
                $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers,
                $firstResultIndex, $maxNumResults);

            // then get all grants for the authorization resource ids page
            return $this->getInternal(
                self::GET_RESOURCE_ACTION_GRANTS,
                authorizationResourceIdentifiers: $authorizationResourceIdPage,
                userIdentifier: $userIdentifier, groupIdentifiers: $groupIdentifiers,
                dynamicGroupIdentifiers: $dynamicGroupIdentifiers);
        } catch (\Exception $exception) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to get resource action grant collection!',
                self::GETTING_RESOURCE_ACTION_GRANT_COLLECTION_FAILED_ERROR_ID, ['message' => $exception->getMessage()]);
        }
    }

    public function getResourceActionGrantQuery(
        string $get = self::GET_RESOURCE_ACTION_GRANTS,
        ?string $resourceClass = null, ?string $resourceIdentifier = null, mixed $authorizationResourceIdentifiers = null,
        ?array $actions = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, int $maxNumResults = 1024, array $options = []
    ): array {
        return $this->getQueryInternal($get, $resourceClass, $resourceIdentifier,
            $authorizationResourceIdentifiers, $actions,
            $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers,
            $firstResultIndex, $maxNumResults, $options);
    }

    private function getInternal(string $get,
        ?string $resourceClass = null, ?string $resourceIdentifier = null, mixed $authorizationResourceIdentifiers = null,
        ?array $actions = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, int $maxNumResults = 1024, array $options = []): array
    {
        [$sql, $parameterValues, $parameterTypes] = $this->getQueryInternal($get, $resourceClass, $resourceIdentifier,
            $authorizationResourceIdentifiers, $actions,
            $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers,
            $firstResultIndex, $maxNumResults, $options);

        try {
            $results = [];
            foreach ($this->entityManager->getConnection()->executeQuery($sql, $parameterValues, $parameterTypes)->fetchAllAssociative() as $row) {
                switch ($get) {
                    case self::GET_RESOURCE_ACTION_GRANTS:
                        $resourceActionGrant = new ResourceActionGrant();
                        $resourceActionGrant->setIdentifier(AuthorizationUuidBinaryType::toStringUuid($row['identifier']));
                        // NOTE: we don't hydrate the full authorization resource here, since we probably won't need it
                        $resourceActionGrant->setResourceClass($row['effective_resource_class']);
                        $resourceActionGrant->setResourceIdentifier($row['effective_resource_identifier']);
                        $resourceActionGrant->setAction($row['action']);
                        $resourceActionGrant->setUserIdentifier($row['user_identifier']);
                        $resourceActionGrant->setGroup($row['group_identifier'] ?
                            $this->entityManager->getRepository(Group::class)->find(AuthorizationUuidBinaryType::toStringUuid($row['group_identifier'])) : null);
                        $resourceActionGrant->setDynamicGroupIdentifier($row['dynamic_group_identifier']);
                        $results[] = $resourceActionGrant;
                        break;

                    case self::GET_AUTHORIZATION_RESOURCES:
                        $authorizationResource = new AuthorizationResource();
                        $authorizationResource->setIdentifier(
                            AuthorizationUuidBinaryType::toStringUuid($row['effective_authorization_resource_identifier']));
                        $authorizationResource->setResourceClass($row['effective_resource_class']);
                        $authorizationResource->setResourceIdentifier($row['effective_resource_identifier']);
                        $results[] = $authorizationResource;
                        break;

                    case self::GET_AUTHORIZATION_RESOURCE_IDENTIFIERS:
                        $results[] = $row['effective_authorization_resource_identifier'];
                        break;

                    default:
                        throw new \InvalidArgumentException('Undefined get: '.$get);
                }
            }

            return $results;
        } catch (\Throwable $throwable) {
            $this->logger->error("Failed to get $get: ".$throwable->getMessage(), ['exception' => $throwable]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                "Failed to get $get",
                $get === self::GET_RESOURCE_ACTION_GRANTS ?
                    self::GETTING_RESOURCE_ACTION_GRANT_COLLECTION_FAILED_ERROR_ID :
                    self::GETTING_RESOURCE_COLLECTION_FAILED_ERROR_ID);
        }
    }

    /**
     * @param string[]|string|null $groupIdentifiers
     * @param string[]|string|null $dynamicGroupIdentifiers
     *
     * @throws ApiError
     */
    private function getQueryInternal(string $get = self::GET_RESOURCE_ACTION_GRANTS,
        ?string $resourceClass = null, ?string $resourceIdentifier = null, mixed $authorizationResourceIdentifiers = null,
        ?array $actions = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, int $maxNumResults = 1024, array $options = []): array
    {
        $RESOURCE_ACTION_GRANT_ALIAS = self::RESOURCE_ACTION_GRANT_ALIAS;
        $AUTHORIZATION_RESOURCE_ALIAS = self::AUTHORIZATION_RESOURCE_ALIAS;
        $AUTHORIZATION_RESOURCE_GRANT_INHERITANCE_JOIN_ALIAS = self::AUTHORIZATION_RESOURCE_GRANT_INHERITANCE_JOIN_ALIAS;

        $parameterValues = [];
        $parameterTypes = [];

        $select = $options[self::SELECT_OPTION] ?? null;
        $select ??= match ($get) {
            self::GET_RESOURCE_ACTION_GRANTS => "DISTINCT $RESOURCE_ACTION_GRANT_ALIAS.identifier, $RESOURCE_ACTION_GRANT_ALIAS.action, $RESOURCE_ACTION_GRANT_ALIAS.user_identifier, $RESOURCE_ACTION_GRANT_ALIAS.group_identifier, $RESOURCE_ACTION_GRANT_ALIAS.dynamic_group_identifier, $AUTHORIZATION_RESOURCE_GRANT_INHERITANCE_JOIN_ALIAS.effective_resource_class, $AUTHORIZATION_RESOURCE_GRANT_INHERITANCE_JOIN_ALIAS.effective_resource_identifier",
            self::GET_AUTHORIZATION_RESOURCES => "DISTINCT $AUTHORIZATION_RESOURCE_GRANT_INHERITANCE_JOIN_ALIAS.effective_authorization_resource_identifier, $AUTHORIZATION_RESOURCE_GRANT_INHERITANCE_JOIN_ALIAS.effective_resource_class, $AUTHORIZATION_RESOURCE_GRANT_INHERITANCE_JOIN_ALIAS.effective_resource_identifier",
            self::GET_AUTHORIZATION_RESOURCE_IDENTIFIERS => "DISTINCT $AUTHORIZATION_RESOURCE_GRANT_INHERITANCE_JOIN_ALIAS.effective_authorization_resource_identifier",
            default => throw new \InvalidArgumentException('Undefined get: '.$get),
        };

        $authorizationResourceCriteria = $this->getAuthorizationResourceCriteria($AUTHORIZATION_RESOURCE_ALIAS,
            $resourceClass, $resourceIdentifier, $authorizationResourceIdentifiers,
            $parameterValues, $parameterTypes);

        $actionCriteria = $this->getActionCriteria($RESOURCE_ACTION_GRANT_ALIAS, $actions, $parameterValues, $parameterTypes);

        $grantHolderCriteria = $this->getGrantHolderCriteria($RESOURCE_ACTION_GRANT_ALIAS,
            $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers,
            $parameterValues, $parameterTypes);

        $groupBy = '';
        if ($options[self::GROUP_BY_RESOURCE_CLASS_OPTION] ?? false) {
            $groupBy = 'GROUP BY effective_resource_class';
        }

        $orderBy = '';
        if ($get === self::GET_RESOURCE_ACTION_GRANTS) {
            $orderBy = "ORDER BY $AUTHORIZATION_RESOURCE_GRANT_INHERITANCE_JOIN_ALIAS.effective_authorization_resource_identifier";
        }

        $additionalJoinStatements = $options[self::ADDITIONAL_JOIN_STATEMENTS_OPTION] ?? '';

        $additionalCriteria = '';
        if ($additionalCriteriaOption = ($options[self::ADDITIONAL_CRITERIA_OPTION] ?? null)) {
            $additionalCriteria = $additionalCriteriaOption[0];
            $parameterValues = array_merge($parameterValues, $additionalCriteriaOption[1] ?? []);
            $parameterTypes = array_merge($parameterTypes, $additionalCriteriaOption[2] ?? []);
        }

        $sql = "SELECT $select
                FROM authorization_resource_action_grants rag
                INNER JOIN (
                    WITH RECURSIVE cte AS (
                        SELECT ar_gi_0.identifier, ar_gi_0.source_authorization_resource_identifier, ar_gi_0.target_authorization_resource_identifier,
                               ar.identifier AS effective_authorization_resource_identifier,
                               ar.resource_class AS effective_resource_class,
                               ar.resource_identifier AS effective_resource_identifier
                        FROM authorization_resources ar
                        LEFT JOIN authorization_grant_inheritances ar_gi_0
                        ON ar_gi_0.target_authorization_resource_identifier = ar.identifier
                        WHERE $authorizationResourceCriteria
                        UNION ALL
                        SELECT ar_gi_n.identifier, ar_gi_n.source_authorization_resource_identifier, ar_gi_n.target_authorization_resource_identifier,
                               cte.effective_authorization_resource_identifier, cte.effective_resource_class, cte.effective_resource_identifier
                        FROM authorization_grant_inheritances ar_gi_n
                        INNER JOIN cte ON ar_gi_n.target_authorization_resource_identifier = cte.source_authorization_resource_identifier)
                    SELECT source_authorization_resource_identifier, cte.effective_authorization_resource_identifier,
                           cte.effective_resource_class, cte.effective_resource_identifier FROM cte) AS ar_gi
                ON $RESOURCE_ACTION_GRANT_ALIAS.authorization_resource_identifier = $AUTHORIZATION_RESOURCE_GRANT_INHERITANCE_JOIN_ALIAS.source_authorization_resource_identifier
                OR $RESOURCE_ACTION_GRANT_ALIAS.authorization_resource_identifier = $AUTHORIZATION_RESOURCE_GRANT_INHERITANCE_JOIN_ALIAS.effective_authorization_resource_identifier
                $additionalJoinStatements
                WHERE ($actionCriteria
                AND $grantHolderCriteria)
                $additionalCriteria
                $groupBy
                $orderBy
                LIMIT $maxNumResults OFFSET $firstResultIndex";

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
        if ($action === null || $action === '') {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'resource action grant is invalid: \'action\' is required', self::RESOURCE_ACTION_GRANT_INVALID_ACTION_MISSING_ERROR_ID, ['action']);
        }
        [$itemActions, $collectionActions] = $this->getAvailableResourceClassActions(
            $resourceActionGrant->getAuthorizationResource()->getResourceClass());

        if ($resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() !== null) {
            $actionsToCheck = array_keys($itemActions ?? []);
        } else {
            $actionsToCheck = array_keys($collectionActions ?? []);
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
        if ($itemActions !== null) {
            $itemActions[AuthorizationService::MANAGE_ACTION] = [
                'en' => 'Manage',
                'de' => 'Verwalten',
            ];
        }
        $collectionActions = $getActionsEvent->getCollectionActions();
        if ($collectionActions !== null) {
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
                $authorizationResource->setIdentifier(Uuid::uuid7()->toString());
                $authorizationResource->setResourceClass($resourceClass);
                $authorizationResource->setResourceIdentifier($resourceIdentifier);

                $this->entityManager->persist($authorizationResource);
                $this->entityManager->flush();

                return $authorizationResource;
            } catch (\Exception $e) {
                throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Resource could not be added!',
                    self::ADDING_RESOURCE_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            }
        }

        return $authorizationResource;
    }

    /**
     * @throws ApiError
     */
    private function validateResourceClassAndIdentifier(string $resourceClass, ?string $resourceIdentifier): void
    {
        if (str_contains($resourceClass, UserAttributeProvider::SEPARATOR)) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                sprintf("resource class must not contain the reserved character '%s'",
                    UserAttributeProvider::SEPARATOR));
        }
        if (str_contains($resourceClass, GrantedActions::ID_SEPARATOR)) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                sprintf("resource class must not contain the reserved character '%s'",
                    GrantedActions::ID_SEPARATOR));
        }

        if ($resourceIdentifier && str_contains($resourceIdentifier, UserAttributeProvider::SEPARATOR)) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                sprintf("resource identifier must not contain the reserved character '%s'",
                    UserAttributeProvider::SEPARATOR));
        }
        if ($resourceIdentifier && str_contains($resourceIdentifier, GrantedActions::ID_SEPARATOR)) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                sprintf("resource identifier must not contain the reserved character '%s'",
                    GrantedActions::ID_SEPARATOR));
        }
    }

    /**
     * @throws ApiError
     */
    private function addResourceActionGrantInternal(ResourceActionGrant $resourceActionGrant): ResourceActionGrant
    {
        $this->validateResourceActionGrant($resourceActionGrant);

        $resourceActionGrant->setIdentifier(Uuid::uuid7()->toString());
        try {
            $this->entityManager->persist($resourceActionGrant);
            $this->entityManager->flush();
        } catch (ApiError $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Resource action grant could not be added!',
                self::ADDING_RESOURCE_ACTION_GRANT_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
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

    private function getAuthorizationResourceCriteria(string $authorization_resource_alias,
        ?string $resourceClass, ?string $resourceIdentifier, mixed $authorizationResourceIdentifiers,
        array &$parameterValues, array &$parameterTypes): string
    {
        $resourceClassCriteria = 'true';
        if ($resourceClass !== null) {
            $resourceClassCriteria = "$authorization_resource_alias.resource_class = :resourceClass";
            $parameterValues['resourceClass'] = $resourceClass;
        }

        $resourceIdentifierCriteria = 'true';
        if ($resourceIdentifier !== null) {
            switch ($resourceIdentifier) {
                case self::IS_NULL:
                    $resourceIdentifierCriteria = "$authorization_resource_alias.resource_identifier is null";
                    break;
                case self::IS_NOT_NULL:
                    $resourceIdentifierCriteria = "$authorization_resource_alias.resource_identifier is not null";
                    break;
                default:
                    $resourceIdentifierCriteria = "$authorization_resource_alias.resource_identifier = :resourceIdentifier";
                    $parameterValues['resourceIdentifier'] = $resourceIdentifier;
                    break;
            }
        }
        $authorizationResourceIdentifierCriteria = 'true';
        if ($authorizationResourceIdentifiers !== null) {
            if (is_array($authorizationResourceIdentifiers)) {
                $authorizationResourceIdentifierCriteria = "$authorization_resource_alias.identifier IN (:authorizationResourceIdentifiers)";
                $parameterValues['authorizationResourceIdentifiers'] = $authorizationResourceIdentifiers;
                $parameterTypes['authorizationResourceIdentifiers'] = ArrayParameterType::BINARY;
            } else {
                $authorizationResourceIdentifierCriteria = "$authorization_resource_alias.identifier = :authorizationResourceIdentifier";
                $parameterValues['authorizationResourceIdentifier'] = $authorizationResourceIdentifiers;
                $parameterTypes['authorizationResourceIdentifier'] = AuthorizationUuidBinaryType::NAME;
            }
        }

        return "($resourceClassCriteria AND $resourceIdentifierCriteria AND $authorizationResourceIdentifierCriteria)";
    }

    private function getActionCriteria(string $resource_action_grant_alias,
        ?array $actions, array &$parameterValues, array &$parameterTypes): string
    {
        $actionCriteria = 'true';
        if (false === empty($actions)) {
            $actionCriteria = "$resource_action_grant_alias.action IN (:actions)";
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
                $parameterValues['groupIdentifiers'] = AuthorizationUuidBinaryType::toBinaryUuids($groupIdentifiers);
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
}
