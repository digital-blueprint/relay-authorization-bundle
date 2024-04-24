<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Service;

use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Helper\AuthorizationUuidBinaryType;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class InternalResourceActionGrantService
{
    public const MANAGE_ACTION = 'manage';

    public const IS_NULL = 'is_null';
    public const IS_NOT_NULL = 'is_not_null';

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

    public function getResource(string $identifier, array $options)
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
    public function getResourceActionGrant(string $identifier, array $options): ?ResourceActionGrant
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
        int $currentPageNumber, int $maxNumItemsPerPage, string $userIdentifier): array
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
                ->setParameter(':action', self::MANAGE_ACTION)
                ->setParameter(':userIdentifier', $userIdentifier);

            return $queryBuilder
                ->getQuery()
                ->setFirstResult(Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage))
                ->setMaxResults($maxNumItemsPerPage)
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
            $resourceActionGrant->setAction(self::MANAGE_ACTION);
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
    public function doesUserHaveAManageGrantForResourceByAuthorizationResourceIdentifier(
        string $userIdentifier, string $authorizationResourceIdentifier): bool
    {
        $resourceActionGrants = $this->getResourceActionGrantsForAuthorizationResourceIdentifier(
            $authorizationResourceIdentifier, [self::MANAGE_ACTION], $userIdentifier);

        return count($resourceActionGrants) > 0;
    }

    /**
     * @throws ApiError
     */
    public function doesUserHaveAManageGrantForResourceByResourceClassAndIdentifier(
        string $userIdentifier, string $resourceClass, string $resourceIdentifier): bool
    {
        $resourceActionGrants = $this->getResourceActionGrantsForResourceClassAndIdentifier(
            $resourceClass, $resourceIdentifier, [self::MANAGE_ACTION], $userIdentifier);

        return count($resourceActionGrants) > 0;
    }

    /**
     * @throws ApiError
     */
    public function removeResource(string $resourceClass, string $resourceIdentifier): void
    {
        try {
            $this->entityManager->getConnection()->beginTransaction();

            $resource = $this->entityManager->getRepository(AuthorizationResource::class)->findOneBy([
                'resourceClass' => $resourceClass,
                'resourceIdentifier' => $resourceIdentifier,
            ]);

            // don't fail if the resource is not found for whatever reason
            // consider using cascade remove (requires adding a '$resourceActionGrants' property to AuthorizationResource which inverses
            // '$authorizationResource' under ResourceActionGrant
            if ($resource !== null) {
                $RESOURCE_ACTION_GRANT_ALIAS = 'rag';
                $queryBuilder = $this->entityManager->createQueryBuilder();
                $queryBuilder
                    ->delete(ResourceActionGrant::class, $RESOURCE_ACTION_GRANT_ALIAS)
                    ->where($queryBuilder->expr()->eq($RESOURCE_ACTION_GRANT_ALIAS.'.authorizationResource', ':authorizationResourceIdentifier'))
                    ->setParameter(':authorizationResourceIdentifier', $resource->getIdentifier(), AuthorizationUuidBinaryType::NAME)
                    ->getQuery()
                    ->execute();

                $this->entityManager->remove($resource);
                $this->entityManager->flush();
            }

            $this->entityManager->getConnection()->commit();
        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollback();
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Resource could not be removed!', self::REMOVING_RESOURCE_FAILED_ERROR_ID,
                ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    public function getResourceActionGrantsForAuthorizationResourceIdentifier(?string $authorizationResourceIdentifier = null,
        ?array $actions = null, ?string $userIdentifier = null, int $currentPageNumber = 1, int $maxNumItemsPerPage = 1024): array
    {
        return $this->getResourceActionGrantsInternal(
            null, null, $authorizationResourceIdentifier, $actions, $userIdentifier, $currentPageNumber, $maxNumItemsPerPage);
    }

    /**
     * @return ResourceActionGrant[]
     *
     * @throws ApiError
     */
    public function getResourceActionGrantsForResourceClassAndIdentifier(?string $resourceClass = null, ?string $resourceIdentifier = null,
        ?array $actions = null, ?string $userIdentifier = null, int $currentPageNumber = 1, int $maxNumItemsPerPage = 1024): array
    {
        return $this->getResourceActionGrantsInternal(
            $resourceClass, $resourceIdentifier, null, $actions, $userIdentifier, $currentPageNumber, $maxNumItemsPerPage);
    }

    public function getResources(
        ?string $resourceClass = null, ?string $resourceIdentifier = null,
        ?array $actions = null, ?string $userIdentifier = null, int $currentPageNumber = 1, int $maxNumItemsPerPage = 1024): array
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
                ->setFirstResult(Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage))
                ->setMaxResults($maxNumItemsPerPage)
                ->getResult();
        } catch (ApiError $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to get resource action grant collection!',
                self::GETTING_RESOURCE_ACTION_GRANT_COLLECTION_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    /**
     * @return ResourceActionGrant[]
     *
     * @throws ApiError
     */
    private function getResourceActionGrantsInternal(
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?string $authorizationResourceIdentifier = null,
        ?array $actions = null, ?string $userIdentifier = null, int $currentPageNumber = 1, int $maxNumItemsPerPage = 1024): array
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
            if ($userIdentifier !== null) {
                $queryBuilder
                    ->andWhere($queryBuilder->expr()->eq($RESOURCE_ACTION_GRANT_ALIAS.'.userIdentifier', ':userIdentifier'))
                    ->setParameter(':userIdentifier', $userIdentifier);
            }

            return $queryBuilder
                ->getQuery()
                ->setFirstResult(Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage))
                ->setMaxResults($maxNumItemsPerPage)
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
}
