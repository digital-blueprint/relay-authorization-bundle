<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Service;

use Dbp\Relay\AuthorizationBundle\Entity\Resource;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Doctrine\ORM\EntityManagerInterface;
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
    private const GETTING_RESOURCE_ACTION_GRANT_ITEM_FAILED_ERROR_ID = 'authorization:getting-resource-action-grant-item-failed';
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

    public function getResource(string $identifier, array $options)
    {
        try {
            return $this->entityManager
                ->getRepository(Resource::class)
                ->find($identifier);
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get resource item!',
                self::GETTING_RESOURCE_ITEM_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    public function getResourcesUserIsAuthorizedToRead(int $currentPageNumber, int $maxNumItemsPerPage, string $userIdentifier)
    {
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
        // (user_identifier = '4E2E6724637AAE47' AND action != 'manage')
        // OR resource_identifier IN (SELECT resource_identifier FROM `authorization_resource_action_grants` WHERE user_identifier = '4E2E6724637AAE47' AND action = 'manage')

        try {
            $SUBQUERY_RESOURCE_ALIAS = 'r_sub';
            $subqueryBuilder = $this->entityManager->createQueryBuilder();
            $subqueryBuilder->select($SUBQUERY_RESOURCE_ALIAS.'.authorizationResourceIdentifier')
                ->from(ResourceActionGrant::class, $SUBQUERY_RESOURCE_ALIAS)
                ->where($subqueryBuilder->expr()->eq($SUBQUERY_RESOURCE_ALIAS.'.userIdentifier', ':userIdentifier'))
                ->andWhere($subqueryBuilder->expr()->eq($SUBQUERY_RESOURCE_ALIAS.'.action', ':action'));

            $RESOURCE_ALIAS = 'r';
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder->select($RESOURCE_ALIAS)
                ->from(ResourceActionGrant::class, $RESOURCE_ALIAS)
                ->where($queryBuilder->expr()->eq($RESOURCE_ALIAS.'.userIdentifier', ':userIdentifier'))
                ->andWhere($queryBuilder->expr()->neq($RESOURCE_ALIAS.'.action', ':action'))
                ->orWhere($queryBuilder->expr()->in($RESOURCE_ALIAS.'.authorizationResourceIdentifier', $subqueryBuilder->getDQL()))
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
        $resourceActionGrant = new ResourceActionGrant();
        try {
            $resource = new Resource();
            $resource->setIdentifier(Uuid::uuid7()->toString());
            $resource->setResourceClass($resourceClass);
            $resource->setResourceIdentifier($resourceIdentifier);

            $this->entityManager->getConnection()->beginTransaction();
            $this->entityManager->persist($resource);
            $this->entityManager->flush();

            $resourceActionGrant->setAuthorizationResourceIdentifier($resource->getIdentifier());
            $resourceActionGrant->setAction(self::MANAGE_ACTION);
            $resourceActionGrant->setUserIdentifier($userIdentifier);
            $this->addResourceActionGrant($resourceActionGrant);

            $this->entityManager->getConnection()->commit();
        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollback();
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Resource could not be added!',
                self::ADDING_RESOURCE_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
        }

        return $resourceActionGrant;
    }

    /**
     * @throws ApiError
     */
    public function isUserResourceManagerOf(string $userIdentifier, string $authorizationResourceIdentifier): bool
    {
        $resourceActionGrants = $this->getResourceActionGrantsForAuthorizationResourceIdentifier(
            $authorizationResourceIdentifier, [self::MANAGE_ACTION], $userIdentifier);

        return count($resourceActionGrants) > 0;
    }

    /**
     * @throws ApiError
     */
    public function removeResource(string $resourceClass, string $resourceIdentifier): void
    {
        try {
            $this->entityManager->getConnection()->beginTransaction();

            $RESOURCE_ALIAS = 'r';
            $subqueryBuilder = $this->entityManager->createQueryBuilder();
            $subqueryBuilder
                ->select($RESOURCE_ALIAS.'.identifier')
                ->from(Resource::class, $RESOURCE_ALIAS)
                ->where($subqueryBuilder->expr()->eq($RESOURCE_ALIAS.'.resourceClass', ':resourceClass'))
                ->andWhere($subqueryBuilder->expr()->eq($RESOURCE_ALIAS.'.resourceIdentifier', ':resourceIdentifier'))
                ->setParameter(':resourceClass', $resourceClass)
                ->setParameter(':resourceIdentifier', $resourceIdentifier);

            $RESOURCE_ACTION_GRANT_ALIAS = 'rag';
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder
                ->delete(ResourceActionGrant::class, $RESOURCE_ACTION_GRANT_ALIAS)
                ->where($queryBuilder->expr()->eq($RESOURCE_ACTION_GRANT_ALIAS.'.authorizationResourceIdentifier', ':authorizationResourceIdentifier'))
                ->setParameter(':authorizationResourceIdentifier', $subqueryBuilder->getDQL())
                ->getQuery()
                ->execute();

            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder
                ->delete(Resource::class, 'r2')
                ->where($queryBuilder->expr()->eq('r2.identifier', ':identifier'))
                ->setParameter(':identifier', $subqueryBuilder->getDQL())
                ->getQuery()
                ->execute();

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

    /**
     * @return ResourceActionGrant[]
     *
     * @throws ApiError
     */
    private function getResourceActionGrantsInternal(
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?string $authorizationResourceIdentifier = null,
        ?array $actions = null, ?string $userIdentifier = null, int $currentPageNumber = 1, int $maxNumItemsPerPage = 1024): array
    {
        try {
            if ($authorizationResourceIdentifier === null) {
                $RESOURCE_ALIAS = 'r';
                $subqueryBuilder = $this->entityManager->createQueryBuilder();
                $subqueryBuilder
                    ->select($RESOURCE_ALIAS.'.identifier')
                    ->from(Resource::class, $RESOURCE_ALIAS)
                    ->where($subqueryBuilder->expr()->eq($RESOURCE_ALIAS.'.resourceClass', ':resourceClass'))
                    ->setParameter(':resourceClass', $resourceClass);
                if ($resourceIdentifier !== null) {
                    switch ($resourceIdentifier) {
                        case self::IS_NULL:
                            $subqueryBuilder
                                ->andWhere($subqueryBuilder->expr()->isNull($RESOURCE_ALIAS.'.resourceIdentifier'));
                            break;
                        case self::IS_NOT_NULL:
                            $subqueryBuilder
                                ->andWhere($subqueryBuilder->expr()->isNotNull($RESOURCE_ALIAS.'.resourceIdentifier'));
                            break;
                        default:
                            $subqueryBuilder
                                ->andWhere($subqueryBuilder->expr()->eq($RESOURCE_ALIAS.'.resourceIdentifier', ':resourceIdentifier'))
                                ->setParameter(':resourceIdentifier', $resourceIdentifier);
                    }
                }
            }

            $RESOURCE_ACTION_GRANT_ALIAS = 'rag';
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder->select(ResourceActionGrant::class, $RESOURCE_ACTION_GRANT_ALIAS)
                ->where($queryBuilder->expr()->eq($RESOURCE_ACTION_GRANT_ALIAS.'.authorizationResourceIdentifier', ':authorizationResourceIdentifier'))
                ->setParameter(':authorizationResourceIdentifier',
                    $authorizationResourceIdentifier === null ? $subqueryBuilder->getDQL() : $authorizationResourceIdentifier);
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
    private function validateResource(Resource $resource): void
    {
        if ($resource->getResourceClass() === null) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'resource action invalid: \'resourceClass\' is required', self::RESOURCE_INVALID_ERROR_ID, ['resourceClass']);
        }
    }
}
