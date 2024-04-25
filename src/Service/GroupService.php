<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Service;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Entity\GroupMember;
use Dbp\Relay\AuthorizationBundle\Helper\AuthorizationUuidBinaryType;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class GroupService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const ADDING_GROUP_FAILED_ERROR_ID = 'authorization:adding-group-failed';
    private const REMOVING_GROUP_FAILED_ERROR_ID = 'authorization:removing-group-failed';
    private const GROUP_INVALID_ERROR_ID = 'authorization:group-invalid';
    private const GETTING_GROUP_COLLECTION_FAILED_ERROR_ID = 'authorization:getting-group-collection-failed';
    private const GETTING_GROUP_ITEM_FAILED_ERROR_ID = 'authorization:getting-group-item-failed';
    private const REMOVING_GROUP_MEMBER_FAILED_ERROR_ID = 'authorization:removing-group-member-failed';
    private const ADDING_GROUP_MEMBER_FAILED_ERROR_ID = 'authorization:adding-group-member-failed';
    private const GROUP_MEMBER_INVALID_ERROR_ID = 'authorization:group-member-invalid';
    private const GETTING_GROUP_MEMBER_ITEM_FAILED_ERROR_ID = 'authorization:getting-group-member-item-failed';
    private const GETTING_GROUP_MEMBER_COLLECTION_FAILED_ERROR_ID = 'authorization:getting-group-member-collection-failed';

    private EntityManagerInterface $entityManager;
    private AuthorizationService $authorizationService;

    public function __construct(EntityManagerInterface $entityManager, AuthorizationService $authorizationService)
    {
        $this->entityManager = $entityManager;
        $this->authorizationService = $authorizationService;
    }

    /**
     * @throws ApiError
     */
    public function isUserMemberOfGroup(string $userIdentifier, string $groupIdentifier): bool
    {
        $resultSetMapping = new ResultSetMapping();
        $resultSetMapping->addEntityResult(GroupMember::class, 'agm_1');
        $resultSetMapping->addEntityResult(GroupMember::class, 'agm_2');
        $resultSetMapping->addFieldResult('agm_1', 'parent_group_identifier', 'group');
        $resultSetMapping->addFieldResult('agm_1', 'child_group_identifier', 'childGroup');
        $resultSetMapping->addFieldResult('agm_1', 'user_identifier', 'userIdentifier');
        $resultSetMapping->addFieldResult('agm_2', 'parent_group_identifier', 'group');
        $resultSetMapping->addFieldResult('agm_2', 'child_group_identifier', 'childGroup');
        $resultSetMapping->addFieldResult('agm_2', 'user_identifier', 'userIdentifier');

        return count($this->entityManager->createNativeQuery('     
         with recursive cte as (
         select      agm_1.parent_group_identifier, agm_1.child_group_identifier, agm_1.user_identifier
          from       authorization_group_members agm_1
          where      agm_1.parent_group_identifier = :parent_group_identifier
          union all
          select     agm_2.parent_group_identifier, agm_2.child_group_identifier, agm_2.user_identifier
          from       authorization_group_members agm_2
          inner join cte
                  on agm_2.parent_group_identifier = cte.child_group_identifier
         )
         select user_identifier from cte where user_identifier = :user_identifier;', $resultSetMapping)
            ->setParameter(':parent_group_identifier', $groupIdentifier, AuthorizationUuidBinaryType::NAME)
            ->setParameter(':user_identifier', $userIdentifier)
            ->getResult()) > 0;
    }

    /**
     * @throws ApiError
     */
    public function getGroup(string $identifier): ?Group
    {
        try {
            return Uuid::isValid($identifier) ?
                $this->entityManager
                    ->getRepository(Group::class)
                    ->find($identifier) : null;
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
    public function getGroups(int $currentPageNumber, int $maxNumItemsPerPage): array
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

        $group->setIdentifier(Uuid::uuid7()->toString());
        $wasGroupAddedToAuthorization = false;
        try {
            $this->authorizationService->addGroup($group->getIdentifier());
            $wasGroupAddedToAuthorization = true;

            $this->entityManager->persist($group);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            if ($wasGroupAddedToAuthorization) {
                $this->authorizationService->removeGroup($group->getIdentifier());
            }
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Resource action could not be added!',
                self::ADDING_GROUP_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
        }

        return $group;
    }

    /**
     * @throws ApiError
     */
    public function removeGroup(Group $group): void
    {
        try {
            $this->entityManager->remove($group);
            $this->entityManager->flush();

            try {
                $this->authorizationService->removeGroup($group->getIdentifier());
            } catch (\Exception $e) {
                $this->logger->warning(sprintf('Failed to remove group resource \'%s\' from authorization: %s', $group->getIdentifier(), $e->getMessage()));
            }
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Group could not be removed!',
                self::REMOVING_GROUP_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    /**
     * @throws ApiError
     */
    public function addGroupMember(GroupMember $groupMember): GroupMember
    {
        $this->validateGroupMember($groupMember);

        $groupMember->setIdentifier(Uuid::uuid7()->toString());
        try {
            $this->entityManager->persist($groupMember);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Group could not be added!',
                self::ADDING_GROUP_MEMBER_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }

        return $groupMember;
    }

    /**
     * @throws ApiError
     */
    public function removeGroupMember(GroupMember $groupMember): void
    {
        try {
            $this->entityManager->remove($groupMember);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Group could not be removed!',
                self::REMOVING_GROUP_MEMBER_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    /**
     * @throws ApiError
     */
    public function getGroupMember(string $identifier): ?GroupMember
    {
        try {
            return Uuid::isValid($identifier) ? $this->entityManager
                ->getRepository(GroupMember::class)
                ->find($identifier) : null;
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get group member item!',
                self::GETTING_GROUP_MEMBER_ITEM_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    /**
     * @return GroupMember[]
     *
     * @throws ApiError
     */
    public function getGroupMembers(int $currentPageNumber, int $maxNumItemsPerPage, string $groupIdentifier): array
    {
        try {
            return Uuid::isValid($groupIdentifier) ? $this->entityManager
                ->getRepository(GroupMember::class)
                ->findBy(['group' => $groupIdentifier], null, $maxNumItemsPerPage,
                    Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage)) : [];
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get group member collection!',
                self::GETTING_GROUP_MEMBER_COLLECTION_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    /**
     * @throws ApiError
     */
    private function validateGroup(Group $group): void
    {
        if ($group->getName() === null) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'group is invalid: \'name\' is required', self::GROUP_INVALID_ERROR_ID, ['name']);
        }
    }

    /**
     * @throws ApiError
     */
    private function validateGroupMember(GroupMember $groupMember): void
    {
        if ($groupMember->getGroup() === null) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'group member is invalid: \'group\' is required', self::GROUP_MEMBER_INVALID_ERROR_ID, ['group']);
        }
        if ($groupMember->getUserIdentifier() === null
            && $groupMember->getChildGroup() === null
            && $groupMember->getPredefinedGroupIdentifier()) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'group member is invalid: \'userIdentifier\' or \'childGroup\' or \'predefinedGroupIdentifier\' is required',
                self::GROUP_MEMBER_INVALID_ERROR_ID);
        }
    }
}
