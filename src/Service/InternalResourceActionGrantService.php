<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Service;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Helper\AuthorizationUuidBinaryType;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class InternalResourceActionGrantService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const IS_NULL = '@@@ __is_null__ @@@';
    public const IS_NOT_NULL = '@@@ __is_not_null__ @@@';

    private const ADDING_RESOURCE_ACTION_GRANT_FAILED_ERROR_ID = 'authorization:adding-resource-action-grant-failed';
    private const REMOVING_RESOURCE_ACTION_GRANT_FAILED_ERROR_ID = 'authorization:removing-resource-action-grant-failed';
    private const RESOURCE_ACTION_INVALID_ERROR_ID = 'authorization:resource-action-grant-invalid';
    private const GETTING_RESOURCE_ACTION_GRANT_COLLECTION_FAILED_ERROR_ID = 'authorization:getting-resource-action-grant-collection-failed';
    public const GETTING_RESOURCE_ACTION_GRANT_ITEM_FAILED_ERROR_ID = 'authorization:getting-resource-action-grant-item-failed';
    private const ADDING_RESOURCE_FAILED_ERROR_ID = 'authorization:adding-resource-failed';
    private const REMOVING_RESOURCE_FAILED_ERROR_ID = 'authorization:removing-resource-failed';
    private const GETTING_RESOURCE_COLLECTION_FAILED_ERROR_ID = 'authorization:getting-resource-collection-failed';
    private const GETTING_RESOURCE_ITEM_FAILED_ERROR_ID = 'authorization:getting-resource-item-failed';
    private const RESOURCE_INVALID_ERROR_ID = 'authorization:resource-invalid';

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    public function getResource(string $identifier)
    {
        try {
            return $this->entityManager
                ->getRepository(AuthorizationResource::class)
                ->find($identifier);
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get resource item!',
                self::GETTING_RESOURCE_ITEM_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
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
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get resource action item!',
                self::GETTING_RESOURCE_ACTION_GRANT_ITEM_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    /**
     * @return ResourceActionGrant[]
     *
     * @throws ApiError
     */
    public function getResourceActionGrantsUserIsAuthorizedToRead(
        int $firstResultIndex, int $maxNumResults, string $userIdentifier): array
    {
        // Get all resource action grants
        // * that the user has
        // * for all resources that the user manages

        // SELECT * FROM `authorization_resource_action_grants` WHERE
        // (user_identifier = '<user identifier>' AND action != 'manage')
        // OR resource_identifier IN (SELECT resource_identifier FROM
        // `authorization_resource_action_grants` WHERE user_identifier = '<user identifier>' AND action = 'manage')

        try {
            $SUBQUERY_RESOURCE_ALIAS = 'r_sub';
            $subqueryBuilder = $this->entityManager->createQueryBuilder();
            $subqueryBuilder->select('IDENTITY('.$SUBQUERY_RESOURCE_ALIAS.'.authorizationResource)')
                ->from(ResourceActionGrant::class, $SUBQUERY_RESOURCE_ALIAS)
                ->where($subqueryBuilder->expr()->eq($SUBQUERY_RESOURCE_ALIAS.'.userIdentifier', ':userIdentifier'))
                ->andWhere($subqueryBuilder->expr()->eq($SUBQUERY_RESOURCE_ALIAS.'.action', ':action'));

            $RESOURCE_ALIAS = 'r';
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder->select($RESOURCE_ALIAS)
                ->from(ResourceActionGrant::class, $RESOURCE_ALIAS)
                ->where($queryBuilder->expr()->eq($RESOURCE_ALIAS.'.userIdentifier', ':userIdentifier'))
                ->andWhere($queryBuilder->expr()->neq($RESOURCE_ALIAS.'.action', ':action'))
                ->orWhere($queryBuilder->expr()->in($RESOURCE_ALIAS.'.authorizationResource', $subqueryBuilder->getDQL()))
                ->setParameter(':action', AuthorizationService::MANAGE_ACTION)
                ->setParameter(':userIdentifier', $userIdentifier);

            return $queryBuilder
                ->getQuery()
                ->setFirstResult($firstResultIndex)
                ->setMaxResults($maxNumResults)
                ->getResult();
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to get resource action grant collection!',
                self::GETTING_RESOURCE_ACTION_GRANT_COLLECTION_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }
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
    public function removeResourceActionGrant(ResourceActionGrant $resourceAction): void
    {
        try {
            $this->entityManager->remove($resourceAction);
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

            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Resource could not be added!',
                self::ADDING_RESOURCE_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }

        return $resourceActionGrant;
    }

    /**
     * @throws ApiError
     */
    public function removeResource(string $resourceClass, string $resourceIdentifier): void
    {
        $this->removeResourcesInternal($resourceClass, $resourceIdentifier);
    }

    /**
     * @param string[] $resourceIdentifiers
     *
     * @throws ApiError
     */
    public function removeResources(string $resourceClass, array $resourceIdentifiers): void
    {
        $this->removeResourcesInternal($resourceClass, $resourceIdentifiers);
    }

    /**
     * @param string[]|string|null $groupIdentifiers
     * @param string[]|string|null $dynamicGroupIdentifiers
     *
     * @return ResourceActionGrant[]
     *
     * @throws ApiError
     */
    public function getResourceActionGrantsForAuthorizationResourceIdentifier(
        ?string $authorizationResourceIdentifier = null, ?array $actions = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, int $maxNumResults = 1024): array
    {
        return $this->getResourceActionGrantsInternal(
            null, null, $authorizationResourceIdentifier, $actions,
            $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers, $firstResultIndex, $maxNumResults);
    }

    /**
     * @param string[]|string|null $groupIdentifiers
     * @param string[]|string|null $dynamicGroupIdentifiers
     *
     * @return ResourceActionGrant[]
     *
     * @throws ApiError
     */
    public function getResourceActionGrantsForResourceClassAndIdentifier(
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?array $actions = null,
        ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, int $maxNumResults = 1024): array
    {
        return $this->getResourceActionGrantsInternal(
            $resourceClass, $resourceIdentifier, null, $actions,
            $userIdentifier, $groupIdentifiers, $dynamicGroupIdentifiers, $firstResultIndex, $maxNumResults);
    }

    public function getResources(
        ?string $resourceClass = null, ?string $resourceIdentifier = null,
        ?array $actions = null, ?string $userIdentifier = null, int $firstResultIndex = 0, int $maxNumResults = 1024): array
    {
        $RESOURCE_ALIAS = 'r';
        $RESOURCE_ACTION_GRANT_ALIAS = 'rag';

        try {
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder
                ->select($RESOURCE_ALIAS)
                ->from(AuthorizationResource::class, $RESOURCE_ALIAS)
                ->groupBy($RESOURCE_ALIAS.'.identifier')
                ->innerJoin(ResourceActionGrant::class, $RESOURCE_ACTION_GRANT_ALIAS, Join::WITH,
                    $RESOURCE_ACTION_GRANT_ALIAS.'.authorizationResource = '.$RESOURCE_ALIAS.'.identifier');
            if ($resourceClass !== null) {
                $queryBuilder
                    ->where($queryBuilder->expr()->eq($RESOURCE_ALIAS.'.resourceClass', ':resourceClass'))
                    ->setParameter(':resourceClass', $resourceClass);
            }
            if ($resourceIdentifier !== null) {
                switch ($resourceIdentifier) {
                    case self::IS_NULL:
                        $queryBuilder
                            ->andWhere($queryBuilder->expr()->isNull($RESOURCE_ALIAS.'.resourceIdentifier'));
                        break;
                    case self::IS_NOT_NULL:
                        $queryBuilder
                            ->andWhere($queryBuilder->expr()->isNotNull($RESOURCE_ALIAS.'.resourceIdentifier'));
                        break;
                    default:
                        $queryBuilder
                            ->andWhere($queryBuilder->expr()->eq($RESOURCE_ALIAS.'.resourceIdentifier', ':resourceIdentifier'))
                            ->setParameter(':resourceIdentifier', $resourceIdentifier);
                }
            }
            if ($actions !== null) {
                if (count($actions) === 1) {
                    $queryBuilder
                        ->andWhere($queryBuilder->expr()->eq($RESOURCE_ACTION_GRANT_ALIAS.'.action', ':action'))
                        ->setParameter(':action', $actions[0]);
                } else {
                    $queryBuilder
                        ->andWhere($queryBuilder->expr()->in($RESOURCE_ACTION_GRANT_ALIAS.'.action', ':action'))
                        ->setParameter(':action', $actions);
                }
            }
            if ($userIdentifier !== null) {
                $queryBuilder
                    ->andWhere($queryBuilder->expr()->eq($RESOURCE_ACTION_GRANT_ALIAS.'.userIdentifier', ':userIdentifier'))
                    ->setParameter(':userIdentifier', $userIdentifier);
            }

            return $queryBuilder
                ->getQuery()
                ->setFirstResult($firstResultIndex)
                ->setMaxResults($maxNumResults)
                ->getResult();
        } catch (ApiError $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to get resource action grant collection!',
                self::GETTING_RESOURCE_COLLECTION_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    /**
     * Since exclusively userIdentifier, or group or dynamicGroupIdentifier are set in a ResourceAction grant,
     * we combine them with an OR conjunction.
     *
     * @param string[]|string|null $groupIdentifiers
     * @param string[]|string|null $dynamicGroupIdentifiers
     *
     * @return ResourceActionGrant[]
     *
     * @throws ApiError
     */
    private function getResourceActionGrantsInternal(
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?string $authorizationResourceIdentifier = null,
        ?array $actions = null, ?string $userIdentifier = null, mixed $groupIdentifiers = null, mixed $dynamicGroupIdentifiers = null,
        int $firstResultIndex = 0, int $maxNumResults = 1024): array
    {
        $RESOURCE_ALIAS = 'r';
        $RESOURCE_ACTION_GRANT_ALIAS = 'rag';

        try {
            $queryBuilder = $this->entityManager->createQueryBuilder();
            if ($authorizationResourceIdentifier === null) {
                $queryBuilder
                    ->select($RESOURCE_ACTION_GRANT_ALIAS)
                    ->from(ResourceActionGrant::class, $RESOURCE_ACTION_GRANT_ALIAS)
                    ->innerJoin(AuthorizationResource::class, $RESOURCE_ALIAS, Join::WITH,
                        $RESOURCE_ACTION_GRANT_ALIAS.'.authorizationResource = '.$RESOURCE_ALIAS.'.identifier');
                if ($resourceClass !== null) {
                    $queryBuilder
                        ->where($queryBuilder->expr()->eq($RESOURCE_ALIAS.'.resourceClass', ':resourceClass'))
                        ->setParameter(':resourceClass', $resourceClass);
                }
                if ($resourceIdentifier !== null) {
                    switch ($resourceIdentifier) {
                        case self::IS_NULL:
                            $queryBuilder
                                ->andWhere($queryBuilder->expr()->isNull($RESOURCE_ALIAS.'.resourceIdentifier'));
                            break;
                        case self::IS_NOT_NULL:
                            $queryBuilder
                                ->andWhere($queryBuilder->expr()->isNotNull($RESOURCE_ALIAS.'.resourceIdentifier'));
                            break;
                        default:
                            $queryBuilder
                                ->andWhere($queryBuilder->expr()->eq($RESOURCE_ALIAS.'.resourceIdentifier', ':resourceIdentifier'))
                                ->setParameter(':resourceIdentifier', $resourceIdentifier);
                    }
                }
            } else {
                $queryBuilder
                    ->select($RESOURCE_ACTION_GRANT_ALIAS)
                    ->from(ResourceActionGrant::class, $RESOURCE_ACTION_GRANT_ALIAS)
                    ->where($queryBuilder->expr()->eq($RESOURCE_ACTION_GRANT_ALIAS.'.authorizationResource', ':authorizationResourceIdentifier'))
                    ->setParameter(':authorizationResourceIdentifier', $authorizationResourceIdentifier, AuthorizationUuidBinaryType::NAME);
            }

            if ($actions !== null) {
                if (count($actions) === 1) {
                    $queryBuilder
                        ->andWhere($queryBuilder->expr()->eq($RESOURCE_ACTION_GRANT_ALIAS.'.action', ':action'))
                        ->setParameter(':action', $actions[0]);
                } else {
                    $queryBuilder
                        ->andWhere($queryBuilder->expr()->in($RESOURCE_ACTION_GRANT_ALIAS.'.action', ':action'))
                        ->setParameter(':action', $actions);
                }
            }

            $orClause = $queryBuilder->expr()->orX();
            if ($userIdentifier !== null) {
                $orClause
                    ->add($queryBuilder->expr()->eq($RESOURCE_ACTION_GRANT_ALIAS.'.userIdentifier', ':userIdentifier'));
                $queryBuilder->setParameter(':userIdentifier', $userIdentifier);
            }
            if ($groupIdentifiers !== null) {
                if ($groupIdentifiers === self::IS_NOT_NULL) {
                    $orClause
                        ->add($queryBuilder->expr()->isNotNull($RESOURCE_ACTION_GRANT_ALIAS.'.group'));
                } else {
                    // There seem to be issues with doctrine and arrays of binary parameters:
                    // https://github.com/ramsey/uuid-doctrine/issues/18
                    // https://github.com/ramsey/uuid-doctrine/issues/164
                    $orClause
                        ->add($queryBuilder->expr()->in('IDENTITY('.$RESOURCE_ACTION_GRANT_ALIAS.'.group)', ':groupIdentifiers'));
                    $queryBuilder->setParameter(':groupIdentifiers',
                        AuthorizationUuidBinaryType::toBinaryUuids($groupIdentifiers), ArrayParameterType::BINARY);
                }
            }
            if ($dynamicGroupIdentifiers !== null) {
                if ($dynamicGroupIdentifiers === self::IS_NOT_NULL) {
                    $orClause
                        ->add($queryBuilder->expr()->isNotNull($RESOURCE_ACTION_GRANT_ALIAS.'.dynamicGroupIdentifier'));
                } else {
                    $orClause
                        ->add($queryBuilder->expr()->in($RESOURCE_ACTION_GRANT_ALIAS.'.dynamicGroupIdentifier', ':dynamicGroupIdentifiers'));
                    $queryBuilder->setParameter(':dynamicGroupIdentifiers', $dynamicGroupIdentifiers);
                }
            }
            if ($orClause->count() > 0) {
                $queryBuilder->andWhere($orClause);
            }

            return $queryBuilder
                ->getQuery()
                ->setFirstResult($firstResultIndex)
                ->setMaxResults($maxNumResults)
                ->getResult();
        } catch (ApiError $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to get resource action grant collection!',
                self::GETTING_RESOURCE_ACTION_GRANT_COLLECTION_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    /**
     * @throws ApiError
     */
    private function validateResourceActionGrant(ResourceActionGrant $resourceAction): void
    {
        if ($resourceAction->getAction() === null) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'resource action invalid: \'action\' is required', self::RESOURCE_ACTION_INVALID_ERROR_ID, ['action']);
        }
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
}
