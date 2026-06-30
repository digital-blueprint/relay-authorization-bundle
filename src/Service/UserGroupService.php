<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Service;

use Dbp\Relay\AuthorizationBundle\Entity\UserGroup;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroupMember;
use Dbp\Relay\AuthorizationBundle\Helper\AuthorizationUuidBinaryType;
use Dbp\Relay\AuthorizationBundle\Helper\UuidUtils;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
class UserGroupService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const ADDING_GROUP_FAILED_ERROR_ID = 'authorization:adding-user-group-failed';
    private const UPDATING_GROUP_FAILED_ERROR_ID = 'authorization:updating-user-group-failed';
    private const REMOVING_GROUP_FAILED_ERROR_ID = 'authorization:removing-user-group-failed';
    private const GROUP_INVALID_ERROR_ID = 'authorization:user-group-invalid';
    private const GROUP_NOT_FOUND_ERROR_ID = 'authorization:user-group-not-found';
    private const GETTING_GROUP_COLLECTION_FAILED_ERROR_ID = 'authorization:getting-user-group-collection-failed';
    private const GETTING_GROUP_ITEM_FAILED_ERROR_ID = 'authorization:getting-user-group-item-failed';
    private const REMOVING_GROUP_MEMBER_FAILED_ERROR_ID = 'authorization:removing-user-group-member-failed';
    private const ADDING_GROUP_MEMBER_FAILED_ERROR_ID = 'authorization:adding-user-group-member-failed';
    public const GROUP_MEMBER_INVALID_ERROR_ID = 'authorization:user-group-member-invalid';
    private const GETTING_GROUP_MEMBER_ITEM_FAILED_ERROR_ID = 'authorization:getting-user-group-member-item-failed';
    private const GETTING_GROUP_MEMBER_COLLECTION_FAILED_ERROR_ID = 'authorization:getting-user-group-member-collection-failed';

    public function __construct(
        private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @throws ApiError
     */
    public function isUserMemberOfUserGroup(string $userIdentifier, string $groupIdentifier): bool
    {
        $sql = 'with recursive cte as (
             select      agm_1.user_group_identifier, agm_1.child_group_identifier, agm_1.user_identifier
              from       authorization_user_group_members agm_1
              where      agm_1.user_group_identifier = :user_group_identifier
              union all
              select     agm_2.user_group_identifier, agm_2.child_group_identifier, agm_2.user_identifier
              from       authorization_user_group_members agm_2
              inner join cte
                      on agm_2.user_group_identifier = cte.child_group_identifier)
             select user_identifier from cte where user_identifier = :user_identifier;';

        try {
            $sqlStatement = $this->entityManager->getConnection()->prepare($sql);
            $sqlStatement->bindValue(':user_group_identifier',
                UuidUtils::toBinaryUuid($groupIdentifier), ParameterType::BINARY);
            $sqlStatement->bindValue(':user_identifier', $userIdentifier);
            $userIdentifiers = $sqlStatement->executeQuery()->fetchFirstColumn();

            return count($userIdentifiers) > 0;
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to check if user is member of group: '.$throwable->getMessage(), [
                'exception' => $throwable,
            ]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to check if user is member of group');
        }
    }

    /**
     * @return string[] The list of group identifiers
     */
    public function getUserGroupsUserIsMemberOf(string $userIdentifier): array
    {
        $sql = 'with recursive cte as (
             select      agm_1.user_group_identifier, agm_1.child_group_identifier, agm_1.user_identifier
                 from       authorization_user_group_members agm_1
                 where      agm_1.user_identifier = :userIdentifier
                 union all
                 select     agm_2.user_group_identifier, agm_2.child_group_identifier, agm_2.user_identifier
                 from       authorization_user_group_members agm_2
                 inner join cte
                 on agm_2.child_group_identifier = cte.user_group_identifier)
             select user_group_identifier from cte group by user_group_identifier;';

        try {
            $sqlStatement = $this->entityManager->getConnection()->prepare($sql);
            $sqlStatement->bindValue(':userIdentifier', $userIdentifier);
            $groupIdentifiersBinary = $sqlStatement->executeQuery()->fetchFirstColumn();

            return UuidUtils::toStringUuids($groupIdentifiersBinary);
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to get groups user is member of: '.$throwable->getMessage(), [
                'exception' => $throwable,
            ]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to get groups user is member of');
        }
    }

    /**
     * @throws ApiError
     */
    public function tryGetUserGroup(string $identifier): ?UserGroup
    {
        return $this->tryGetUserGroupInternal($identifier);
    }

    /**
     * @throws ApiError
     */
    public function getUserGroup(string $identifier): UserGroup
    {
        $userGroup = $this->tryGetUserGroupInternal($identifier);
        if ($userGroup === null) {
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND, 'Group not found', self::GROUP_NOT_FOUND_ERROR_ID,
                ['identifier' => $identifier]);
        }

        return $userGroup;
    }

    /**
     * @return UserGroup[]
     *
     * @throws ApiError
     *
     * @deprecated
     */
    public function getGroups(int $firstResultIndex, int $maxNumResults): array
    {
        $GROUP_ENTITY_ALIAS = 'g';

        try {
            $queryBuilder = $this->entityManager->createQueryBuilder()
                ->select($GROUP_ENTITY_ALIAS)
                ->from(UserGroup::class, $GROUP_ENTITY_ALIAS);

            return $queryBuilder
                ->getQuery()
                ->setFirstResult($firstResultIndex)
                ->setMaxResults($maxNumResults)
                ->getResult();
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to get groups', ['exception' => $throwable]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get group collection',
                self::GETTING_GROUP_COLLECTION_FAILED_ERROR_ID, ['message' => $throwable->getMessage()]);
        }
    }

    /**
     * @return UserGroup[]
     *
     * @throws ApiError
     */
    public function getUserGroupsByIdentifiers(array $groupIdentifiers, int $firstResultIndex, int $maxNumResults): array
    {
        try {
            $GROUP_ENTITY_ALIAS = 'g';
            $queryBuilder = $this->entityManager->createQueryBuilder();

            return $queryBuilder
                ->select($GROUP_ENTITY_ALIAS)
                ->from(UserGroup::class, $GROUP_ENTITY_ALIAS)
                ->where($queryBuilder->expr()->in("$GROUP_ENTITY_ALIAS.identifier", ':groupIdentifiers'))
                ->setParameter(':groupIdentifiers',
                    UuidUtils::toBinaryUuids($groupIdentifiers), ArrayParameterType::BINARY)
                ->getQuery()
                ->setFirstResult($firstResultIndex)
                ->setMaxResults($maxNumResults)
                ->getResult();
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to get group collection by identifiers: '.$throwable->getMessage(), [
                'exception' => $throwable,
            ]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to get group collection',
                self::GETTING_GROUP_COLLECTION_FAILED_ERROR_ID);
        }
    }

    /**
     * @throws ApiError
     */
    public function addUserGroup(UserGroup $userGroup): UserGroup
    {
        $this->validateUserGroup($userGroup);

        $userGroup->setIdentifier(Uuid::v7()->toRfc4122());
        try {
            $this->entityManager->persist($userGroup);
            $this->entityManager->flush();
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to add group: '.$throwable->getMessage(), ['exception' => $throwable]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Group could not be added',
                self::ADDING_GROUP_FAILED_ERROR_ID, ['message' => $throwable->getMessage()]);
        }

        return $userGroup;
    }

    /**
     * @throws ApiError
     */
    public function updateUserGroup(UserGroup $userGroup): UserGroup
    {
        $this->validateUserGroup($userGroup);

        try {
            $this->entityManager->persist($userGroup);
            $this->entityManager->flush();
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to update group: '.$throwable->getMessage(), [
                'exception' => $throwable,
                'identifier' => $userGroup->getIdentifier(),
            ]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Group could not be updated',
                self::UPDATING_GROUP_FAILED_ERROR_ID);
        }

        return $userGroup;
    }

    /**
     * @throws ApiError
     */
    public function removeUserGroup(UserGroup $userGroup): void
    {
        try {
            $this->entityManager->remove($userGroup);
            $this->entityManager->flush();
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to remove group', [
                'exception' => $throwable,
                'identifier' => $userGroup->getIdentifier(),
            ]
            );
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Group could not be removed',
                self::REMOVING_GROUP_FAILED_ERROR_ID);
        }
    }

    /**
     * @throws ApiError
     */
    public function addUserGroupMember(UserGroupMember $groupMember): UserGroupMember
    {
        $this->validateUserGroupMember($groupMember);

        $groupMember->setIdentifier(Uuid::v7()->toRfc4122());
        try {
            $this->entityManager->persist($groupMember);
            $this->entityManager->flush();
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to add group member', [
                'exception' => $throwable,
                'groupIdentifier' => $groupMember->getUserGroup()?->getIdentifier(),
            ]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Group member could not be added',
                self::ADDING_GROUP_MEMBER_FAILED_ERROR_ID);
        }

        return $groupMember;
    }

    /**
     * @throws ApiError
     */
    public function removeUserGroupMember(UserGroupMember $groupMember): void
    {
        try {
            $this->entityManager->remove($groupMember);
            $this->entityManager->flush();
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to remove group member', [
                'exception' => $throwable,
                'identifier' => $groupMember->getIdentifier(),
            ]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Group member could not be removed',
                self::REMOVING_GROUP_MEMBER_FAILED_ERROR_ID);
        }
    }

    /**
     * @throws ApiError
     */
    public function getUserGroupMember(string $identifier): ?UserGroupMember
    {
        try {
            return Uuid::isValid($identifier) ? $this->entityManager
                ->getRepository(UserGroupMember::class)
                ->find($identifier) : null;
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to get group member: '.$throwable->getMessage(), ['identifier' => $identifier, 'exception' => $throwable]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get group member',
                self::GETTING_GROUP_MEMBER_ITEM_FAILED_ERROR_ID);
        }
    }

    /**
     * @return UserGroupMember[]
     *
     * @throws ApiError
     */
    public function getUserGroupMembers(int $firstResultIndex, int $maxNumResults, string $userGroupIdentifier): array
    {
        try {
            return Uuid::isValid($userGroupIdentifier) ? $this->entityManager
                ->getRepository(UserGroupMember::class)
                ->findBy(['userGroup' => $userGroupIdentifier], null, $maxNumResults,
                    $firstResultIndex) : [];
        } catch (\Throwable $throwable) {
            $this->logger->error('getting group members failed: '.$throwable->getMessage(), [
                'userGroupIdentifier' => $userGroupIdentifier,
                'exception' => $throwable,
            ]);
            dump($throwable);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get group member collection',
                self::GETTING_GROUP_MEMBER_COLLECTION_FAILED_ERROR_ID);
        }
    }

    private function tryGetUserGroupInternal(string $identifier): ?UserGroup
    {
        try {
            return Uuid::isValid($identifier) ?
                $this->entityManager
                    ->getRepository(UserGroup::class)
                    ->find($identifier) : null;
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to get group: '.$throwable->getMessage(), [
                'identifier' => $identifier,
                'exception' => $throwable,
            ]
            );
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get group',
                self::GETTING_GROUP_ITEM_FAILED_ERROR_ID);
        }
    }

    /**
     * @throws ApiError
     */
    private function validateUserGroup(UserGroup $userGroup): void
    {
        if ($userGroup->getName() === null) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'user group is invalid: \'name\' is required', self::GROUP_INVALID_ERROR_ID, ['name']);
        }
    }

    /**
     * @throws ApiError
     */
    private function validateUserGroupMember(UserGroupMember $groupMember): void
    {
        if ($groupMember->getUserGroup() === null) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'group member is invalid: \'userGroup\' is required', self::GROUP_MEMBER_INVALID_ERROR_ID, ['userGroup']);
        }
        // Matching parent and child group would cause and endless loop
        if ($groupMember->getChildGroup() !== null
            && $this->isAllowedChildGroupOf($groupMember->getChildGroup(), $groupMember->getUserGroup())) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'group member is invalid:  \'childGroup\' must not be the same or an ancestor of \'group\' (causes infinite loop)',
                self::GROUP_MEMBER_INVALID_ERROR_ID, ['childGroup']);
        }
        if (($groupMember->getUserIdentifier() === null && $groupMember->getChildGroup() === null)
            || ($groupMember->getUserIdentifier() !== null && $groupMember->getChildGroup() !== null)) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'group member is invalid: exactly one of \'userIdentifier\' or \'childGroup\' must be given',
                self::GROUP_MEMBER_INVALID_ERROR_ID);
        }
    }

    /**
     * @return string[] The array of **binary** group identifiers
     */
    public function getDisallowedChildGroupIdentifiersBinaryFor(string $groupIdentifier): array
    {
        return $this->getDisallowedChildGroupIdentifiersBinaryInternal($this->getUserGroup($groupIdentifier));
    }

    private function isAllowedChildGroupOf(UserGroup $childGroupCandidate, UserGroup $userGroup): bool
    {
        return in_array(
            UuidUtils::toBinaryUuid($childGroupCandidate->getIdentifier()),
            $this->getDisallowedChildGroupIdentifiersBinaryInternal($userGroup), true);
    }

    /**
     * @return string[]
     */
    private function getDisallowedChildGroupIdentifiersBinaryInternal(UserGroup $gruserGroup): array
    {
        // all ancestors of the group, all child groups, and the group itself are forbidden
        $forbiddenChildGroupIdentifiers = array_merge(
            $this->getAncestorGroupIdentifiersBinaryInternal($gruserGroup),
            $this->getChildGroupIdentifiersBinary($gruserGroup));
        $forbiddenChildGroupIdentifiers[] = UuidUtils::toBinaryUuid($gruserGroup->getIdentifier());

        return $forbiddenChildGroupIdentifiers;
    }

    /**
     * @return string[]
     */
    private function getChildGroupIdentifiersBinary(UserGroup $userGroup): array
    {
        $GROUP_MEMBER_ALIAS = 'gm';

        return $this->entityManager->createQueryBuilder()
            ->select("IDENTITY($GROUP_MEMBER_ALIAS.childGroup)")
            ->from(UserGroupMember::class, $GROUP_MEMBER_ALIAS)
            ->where($this->entityManager->getExpressionBuilder()->eq("$GROUP_MEMBER_ALIAS.userGroup", ':group'))
            ->andWhere($this->entityManager->getExpressionBuilder()->isNotNull("$GROUP_MEMBER_ALIAS.childGroup"))
            ->setParameter(':group', $userGroup->getIdentifier(), AuthorizationUuidBinaryType::NAME)
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * @return string[]
     */
    private function getAncestorGroupIdentifiersBinaryInternal(UserGroup $userGroup): array
    {
        $sql = 'with recursive cte as (
             select      agm_1.user_group_identifier, agm_1.child_group_identifier, agm_1.user_identifier
                 from       authorization_user_group_members agm_1
                 where      agm_1.child_group_identifier = :childGroupIdentifier
                 union all
                 select     agm_2.user_group_identifier, agm_2.child_group_identifier, agm_2.user_identifier
                 from       authorization_user_group_members agm_2
                 inner join cte
                 on agm_2.child_group_identifier = cte.user_group_identifier)
             select user_group_identifier from cte;';

        try {
            $sqlStatement = $this->entityManager->getConnection()->prepare($sql);
            $sqlStatement->bindValue(':childGroupIdentifier',
                UuidUtils::toBinaryUuid($userGroup->getIdentifier()), ParameterType::BINARY);

            return $sqlStatement->executeQuery()->fetchFirstColumn();
        } catch (\Exception $exception) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'gettting group ancestors failed: '.$exception->getMessage());
        }
    }
}
