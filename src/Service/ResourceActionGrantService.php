<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Service;

use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class ResourceActionGrantService
{
    public const MANAGE_ACTION = 'manage';

    private const ADDING_RESOURCE_ACTION_GRANT_FAILED_ERROR_ID = 'authorization:adding-resource-action-grant-failed';
    private const REMOVING_RESOURCE_ACTION_GRANT_FAILED_ERROR_ID = 'authorization:removing-resource-action-grant-failed';
    private const RESOURCE_ACTION_INVALID_ERROR_ID = 'authorization:resource-action-grant-invalid';
    private const GETTING_RESOURCE_ACTION_GRANT_COLLECTION_FAILED_ERROR_ID = 'authorization:getting-resource-action-grant-collection-failed';
    private const GETTING_RESOURCE_ACTION_GRANT_ITEM_FAILED_ERROR_ID = 'authorization:getting-resource-action-grant-item-failed';

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
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
        int $currentPageNumber, int $maxNumItemsPerPage, string $userIdentifier, ?string $namespace = null): array
    {
        // Get all resource action grants
        // * that the user has
        // * for all resources that the user manages

        // SELECT * FROM `authorization_resource_action_grants` WHERE
        // (user_identifier = '4E2E6724637AAE47' AND action != 'manage')
        // OR resource_identifier IN (SELECT resource_identifier FROM `authorization_resource_action_grants` WHERE user_identifier = '4E2E6724637AAE47' AND action = 'manage')

        try {
            $SUBQUERY_ENTITY_ALIAS = 'r_sub';
            $subqueryBuilder = $this->entityManager->createQueryBuilder();
            $subqueryBuilder->select($SUBQUERY_ENTITY_ALIAS.'.resourceIdentifier')
                ->from(ResourceActionGrant::class, $SUBQUERY_ENTITY_ALIAS)
                ->where($subqueryBuilder->expr()->eq($SUBQUERY_ENTITY_ALIAS.'.userIdentifier', ':userIdentifier'))
                ->andWhere($subqueryBuilder->expr()->eq($SUBQUERY_ENTITY_ALIAS.'.action', ':action'));

            $ENTITY_ALIAS = 'r';
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder->select($ENTITY_ALIAS)
                ->from(ResourceActionGrant::class, $ENTITY_ALIAS)
                ->where($queryBuilder->expr()->eq($ENTITY_ALIAS.'.userIdentifier', ':userIdentifier'))
                ->andWhere($queryBuilder->expr()->neq($ENTITY_ALIAS.'.action', ':action'))
                ->orWhere($queryBuilder->expr()->in($ENTITY_ALIAS.'.resourceIdentifier', $subqueryBuilder->getDQL()))
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
    public function addResourceActionGrant(ResourceActionGrant $resourceAction): ResourceActionGrant
    {
        $this->validateResourceActionGrant($resourceAction);

        $resourceAction->setIdentifier(Uuid::uuid7()->toString());
        dump($resourceAction);
        try {
            $this->entityManager->persist($resourceAction);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Resource action grant could not be added!',
                self::ADDING_RESOURCE_ACTION_GRANT_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }

        return $resourceAction;
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

    public function addResourceManager(string $userIdentifier, string $namespace, string $resourceIdentifier): void
    {
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setNamespace($namespace);
        $resourceActionGrant->setResourceIdentifier($resourceIdentifier);
        $resourceActionGrant->setAction(self::MANAGE_ACTION);
        $resourceActionGrant->setUserIdentifier($userIdentifier);

        $this->addResourceActionGrant($resourceActionGrant);
    }

    public function isUserResourceManagerOf(string $userIdentifier, string $namespace, string $resourceIdentifier): bool
    {
        $resourceActionGrants = $this->getResourceActionGrantsInternal($namespace, $resourceIdentifier,
            self::MANAGE_ACTION, $userIdentifier);

        return count($resourceActionGrants) > 0;
    }

    public function removeResource(string $namespace, string $resourceIdentifier): void
    {
        $ENTITY_ALIAS = 'r';

        try {
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder->delete(ResourceActionGrant::class, $ENTITY_ALIAS)
                ->where($queryBuilder->expr()->eq($ENTITY_ALIAS.'.namespace', ':namespace'))
                ->andWhere($queryBuilder->expr()->eq($ENTITY_ALIAS.'.resourceIdentifier', ':resourceIdentifier'))
                ->setParameter(':namespace', $namespace)
                ->setParameter(':resourceIdentifier', $resourceIdentifier)
                ->getQuery()
                ->execute();
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Resource could not be removed!', self::REMOVING_RESOURCE_ACTION_GRANT_FAILED_ERROR_ID,
                ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    private function getResourceActionGrantsInternal(
        ?string $namespace = null, ?string $resourceIdentifier = null,
        ?string $action = null, ?string $userIdentifier = null,
        int $currentPageNumber = 1, int $maxNumItemsPerPage = 1024): array
    {
        // TODO: groups
        $ENTITY_ALIAS = 'r';

        try {
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder->select($ENTITY_ALIAS)
                ->from(ResourceActionGrant::class, $ENTITY_ALIAS);
            if ($namespace !== null) {
                $queryBuilder->andWhere($queryBuilder->expr()->eq($ENTITY_ALIAS.'.namespace', ':namespace'))
                    ->setParameter(':namespace', $namespace);
            }
            if ($resourceIdentifier !== null) {
                $queryBuilder->andWhere($queryBuilder->expr()->eq($ENTITY_ALIAS.'.resourceIdentifier', ':resourceIdentifier'))
                    ->setParameter(':resourceIdentifier', $resourceIdentifier);
            }
            if ($action !== null) {
                $queryBuilder->andWhere($queryBuilder->expr()->eq($ENTITY_ALIAS.'.action', ':action'))
                    ->setParameter(':action', $action);
            }
            if ($userIdentifier !== null) {
                $queryBuilder->andWhere($queryBuilder->expr()->eq($ENTITY_ALIAS.'.userIdentifier', ':userIdentifier'))
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
        if ($resourceAction->getNamespace() === null) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'resource action invalid: \'namespace\' is required', self::RESOURCE_ACTION_INVALID_ERROR_ID, ['namespace']);
        }
        if ($resourceAction->getAction() === null) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'resource action invalid: \'action\' is required', self::RESOURCE_ACTION_INVALID_ERROR_ID, ['action']);
        }
    }
}