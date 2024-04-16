<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Service;

use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class GroupService
{
    private const ADDING_GROUP_FAILED_ERROR_ID = 'authorization:adding-group-failed';
    private const REMOVING_GROUP_FAILED_ERROR_ID = 'authorization:removing-group-failed';
    private const GROUP_INVALID_ERROR_ID = 'authorization:group-invalid';
    private const GETTING_GROUP_COLLECTION_FAILED_ERROR_ID = 'authorization:getting-group-collection-failed';
    private const GETTING_GROUP_ITEM_FAILED_ERROR_ID = 'authorization:getting-group-item-failed';

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @throws ApiError
     */
    public function getGroup(string $identifier, array $options): ?Group
    {
        try {
            return $this->entityManager
                ->getRepository(Group::class)
                ->find($identifier);
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get group item!',
                self::GETTING_GROUP_ITEM_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    /**
     * @return Group[]
     *
     * @throws ApiError
     */
    public function getGroups(int $currentPageNumber, int $maxNumItemsPerPage, array $filters, array $options): array
    {
        $ENTITY_ALIAS = 'g';

        try {
            $queryBuilder = $this->entityManager->createQueryBuilder()
                ->select($ENTITY_ALIAS)
                ->from(Group::class, $ENTITY_ALIAS);

            return $queryBuilder
                ->getQuery()
                ->setFirstResult(Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage))
                ->setMaxResults($maxNumItemsPerPage)
                ->getResult();
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get group collection!',
                self::GETTING_GROUP_COLLECTION_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    /**
     * @throws ApiError
     */
    public function addGroup(Group $group): Group
    {
        $this->validateGroup($group);

        $group->setIdentifier((string) Uuid::v4());
        dump($group);
        try {
            $this->entityManager->persist($group);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Resource action could not be added!',
                self::ADDING_GROUP_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }

        return $group;
    }

    /**
     * @throws ApiError
     */
    public function removeGroup(Group $group)
    {
        try {
            $this->entityManager->remove($group);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Group could not be removed!',
                self::REMOVING_GROUP_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    /**
     * @throws ApiError
     */
    private function validateGroup(Group $group)
    {
        if ($group->getName() === null) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'resource action invalid: \'namespace\' is required', self::GROUP_INVALID_ERROR_ID, ['namespace']);
        }
    }
}
