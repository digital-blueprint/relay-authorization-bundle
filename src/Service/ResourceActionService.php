<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Service;

use Dbp\Relay\AuthorizationBundle\Entity\ResourceAction;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
class ResourceActionService
{
    private const ADDING_RESOURCE_ACTION_FAILED_ERROR_ID = 'authorization:adding-resource-action-failed';
    private const REMOVING_RESOURCE_ACTION_FAILED_ERROR_ID = 'authorization:removing-resource-action-failed';
    private const REMOVING_RESOURCE_FAILED_ERROR_ID = 'authorization:removing-resource-failed';
    private const RESOURCE_ACTION_INVALID_ERROR_ID = 'authorization:resource-action-invalid';
    private const GETTING_RESOURCE_ACTION_COLLECTION_FAILED_ERROR_ID = 'authorization:getting-resource-action-collection-failed';
    private const GETTING_RESOURCE_ACTION_ITEM_FAILED_ERROR_ID = 'authorization:getting-resource-action-item-failed';

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @throws ApiError
     */
    public function getResourceAction(string $identifier, array $options): ?ResourceAction
    {
        try {
            return $this->entityManager
                ->getRepository(ResourceAction::class)
                ->find($identifier);
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get resource action item!',
                self::GETTING_RESOURCE_ACTION_ITEM_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    /**
     * @return ResourceAction[]
     *
     * @throws ApiError
     */
    public function getResourceActions(int $currentPageNumber, int $maxNumItemsPerPage, array $options): array
    {
        $ENTITY_ALIAS = 'r';

        try {
            $queryBuilder = $this->entityManager->createQueryBuilder()
                ->select($ENTITY_ALIAS)
                ->from(ResourceAction::class, $ENTITY_ALIAS);

            return $queryBuilder
                ->getQuery()
                ->setFirstResult(Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage))
                ->setMaxResults($maxNumItemsPerPage)
                ->getResult();
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get resource action item!',
                self::GETTING_RESOURCE_ACTION_COLLECTION_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    /**
     * @throws ApiError
     */
    public function addResourceAction(ResourceAction $resourceAction): ResourceAction
    {
        $this->validateResourceAction($resourceAction);

        $resourceAction->setIdentifier((string) Uuid::v4());
        dump($resourceAction);
        try {
            $this->entityManager->persist($resourceAction);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Resource action could not be added!',
                self::ADDING_RESOURCE_ACTION_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }

        return $resourceAction;
    }

    /**
     * @throws ApiError
     */
    public function removeResourceAction(ResourceAction $resourceAction): void
    {
        try {
            $this->entityManager->remove($resourceAction);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Resource action could not be removed!',
                self::REMOVING_RESOURCE_ACTION_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    /**
     * @throws ApiError
     */
    public function removeResource(string $resourceIdentifier): void
    {
        $ENTITY_ALIAS = 'r';

        try {
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder->delete(ResourceAction::class, $ENTITY_ALIAS)
                ->where($queryBuilder->expr()->eq($ENTITY_ALIAS.'.resourceIdentifier', '?1'))
                ->setParameter(1, $resourceIdentifier)
                ->getQuery()
                ->execute();
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Resource could not be removed!', self::REMOVING_RESOURCE_FAILED_ERROR_ID,
                ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    /**
     * @throws ApiError
     */
    private function validateResourceAction(ResourceAction $resourceAction)
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
