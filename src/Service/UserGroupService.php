<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Service;

use Dbp\Relay\AuthorizationBundle\Entity\UserGroup;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroupMember;
use Dbp\Relay\AuthorizationBundle\Helper\AuthorizationUuidBinaryType;
use Dbp\Relay\AuthorizationBundle\Helper\UuidUtils;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class UserGroupService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const ADDING_USER_GROUP_FAILED_ERROR_ID = 'authorization:adding-user-group-failed';
    public const USER_GROUP_MEMBER_INVALID_ERROR_ID = 'authorization:user-group-member-invalid';
    public const UPDATING_USER_GROUP_FAILED_ERROR_ID = 'authorization:updating-user-group-failed';
    public const REMOVING_USER_GROUP_FAILED_ERROR_ID = 'authorization:removing-user-group-failed';
    public const USER_GROUP_INVALID_ERROR_ID = 'authorization:user-group-invalid';
    public const USER_GROUP_NOT_FOUND_ERROR_ID = 'authorization:user-group-not-found';
    public const USER_GROUP_MEMBER_NOT_FOUND_ERROR_ID = 'authorization:user-group-member-not-found';
    public const GETTING_USER_GROUP_COLLECTION_FAILED_ERROR_ID = 'authorization:getting-user-group-collection-failed';
    public const GETTING_USER_GROUP_ITEM_FAILED_ERROR_ID = 'authorization:getting-user-group-item-failed';
    public const REMOVING_USER_GROUP_MEMBER_FAILED_ERROR_ID = 'authorization:removing-user-group-member-failed';
    public const ADDING_USER_GROUP_MEMBER_FAILED_ERROR_ID = 'authorization:adding-user-group-member-failed';
    public const GETTING_USER_GROUP_MEMBER_ITEM_FAILED_ERROR_ID = 'authorization:getting-user-group-member-item-failed';
    public const GETTING_USER_GROUP_MEMBER_COLLECTION_FAILED_ERROR_ID = 'authorization:getting-user-group-member-collection-failed';

    public function __construct(
        private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @throws ApiError
     */
    public function isUserMemberOfUserGroup(string $userIdentifier, string $userGroupIdentifier): bool
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
                UuidUtils::toBinaryUuid($userGroupIdentifier), ParameterType::BINARY);
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
     *
     * @throws ApiError
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
     * @return string[] The list of user identifiers
     *
     * @throws ApiError
     */
    public function getMembersOfUserGroup(string $userGroupIdentifier): array
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
             select user_identifier from cte where user_identifier is not null;';

        try {
            $sqlStatement = $this->entityManager->getConnection()->prepare($sql);
            $sqlStatement->bindValue(':user_group_identifier',
                UuidUtils::toBinaryUuid($userGroupIdentifier), ParameterType::BINARY);

            return $sqlStatement->executeQuery()->fetchFirstColumn();
        } catch (\Throwable $exception) {
            $this->logger->error('getting members of group failed', ['exception' => $exception]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'getting members of group failed');
        }
    }

    /**
     * @throws ApiError
     */
    public function tryGetUserGroupByIdentifier(string $identifier): ?UserGroup
    {
        return $this->tryGetUserGroupInternal($identifier);
    }

    /**
     * @throws ApiError
     */
    public function getUserGroupByIdentifier(string $identifier): UserGroup
    {
        $userGroup = $this->tryGetUserGroupInternal($identifier);
        if ($userGroup === null) {
            $this->throwUserGroupNotFound($identifier);
        }

        return $userGroup;
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
                self::ADDING_USER_GROUP_FAILED_ERROR_ID);
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
                self::UPDATING_USER_GROUP_FAILED_ERROR_ID);
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
                self::REMOVING_USER_GROUP_FAILED_ERROR_ID);
        }
    }

    /**
     * @throws ApiError
     */
    public function addUserGroupMember(UserGroupMember $userGroupMember): UserGroupMember
    {
        $this->validateUserGroupMember($userGroupMember);

        $userGroupMember->setIdentifier(Uuid::v7()->toRfc4122());
        try {
            $this->entityManager->persist($userGroupMember);
            $this->entityManager->flush();
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to add group member', [
                'exception' => $throwable,
                'groupIdentifier' => $userGroupMember->getUserGroup()?->getIdentifier(),
            ]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Group member could not be added',
                self::ADDING_USER_GROUP_MEMBER_FAILED_ERROR_ID);
        }

        return $userGroupMember;
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
                self::REMOVING_USER_GROUP_MEMBER_FAILED_ERROR_ID);
        }
    }

    /**
     * @throws ApiError
     */
    public function getUserGroupMemberByIdentifier(string $identifier): UserGroupMember
    {
        try {
            $userGroupMember = Uuid::isValid($identifier) ? $this->entityManager
                ->getRepository(UserGroupMember::class)
                ->find($identifier) : null;
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to get group member: '.$throwable->getMessage(), ['identifier' => $identifier, 'exception' => $throwable]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get group member',
                self::GETTING_USER_GROUP_MEMBER_ITEM_FAILED_ERROR_ID);
        }

        if (null === $userGroupMember) {
            $this->throwUserGroupMemberNotFound($identifier);
        }

        return $userGroupMember;
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
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to get group member collection',
                self::GETTING_USER_GROUP_MEMBER_COLLECTION_FAILED_ERROR_ID);
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
                self::GETTING_USER_GROUP_ITEM_FAILED_ERROR_ID);
        }
    }

    /**
     * @throws ApiError
     */
    private function validateUserGroup(UserGroup $userGroup): void
    {
        if ($userGroup->getName() === null) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'user group is invalid: \'name\' is required', self::USER_GROUP_INVALID_ERROR_ID, ['name']);
        }
    }

    /**
     * @throws ApiError
     */
    private function validateUserGroupMember(UserGroupMember $userGroupMember): void
    {
        if (null === $userGroupMember->getUserGroup()) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'group member is invalid: \'userGroup\' is required',
                self::USER_GROUP_MEMBER_INVALID_ERROR_ID,
                ['userGroup']);
        }

        // Matching parent and child group would cause and endless loop
        if ($userGroupMember->getChildGroup() !== null
            && $this->isAllowedChildGroupOf($userGroupMember->getChildGroup(), $userGroupMember->getUserGroup())) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'group member is invalid:  \'childGroup\' must not be the same or an ancestor of \'group\' (causes infinite loop)',
                self::USER_GROUP_MEMBER_INVALID_ERROR_ID, ['childGroup']);
        }

        if (($userGroupMember->getUserIdentifier() === null && $userGroupMember->getChildGroup() === null)
            || ($userGroupMember->getUserIdentifier() !== null && $userGroupMember->getChildGroup() !== null)) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'group member is invalid: exactly one of \'userIdentifier\' or \'childGroup\' must be given',
                self::USER_GROUP_MEMBER_INVALID_ERROR_ID);
        }
    }

    /**
     * @throws ApiError
     */
    public function throwUserGroupNotFound(string $identifier)
    {
        throw ApiError::withDetails(Response::HTTP_NOT_FOUND,
            'User group not found',
            self::USER_GROUP_NOT_FOUND_ERROR_ID,
            ['identifier' => $identifier]);
    }

    /**
     * @throws ApiError
     */
    public function throwUserGroupMemberNotFound(string $identifier)
    {
        throw ApiError::withDetails(Response::HTTP_NOT_FOUND,
            'User group member not found',
            self::USER_GROUP_MEMBER_NOT_FOUND_ERROR_ID,
            ['identifier' => $identifier]);
    }

    /**
     * @return string[] The array of **binary** group identifiers
     */
    public function getDisallowedChildGroupIdentifiersBinaryFor(string $groupIdentifier): array
    {
        return $this->getDisallowedChildGroupIdentifiersBinaryInternal($this->getUserGroupByIdentifier($groupIdentifier));
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

        try {
            return $this->entityManager->createQueryBuilder()
                ->select("IDENTITY($GROUP_MEMBER_ALIAS.childGroup)")
                ->from(UserGroupMember::class, $GROUP_MEMBER_ALIAS)
                ->where($this->entityManager->getExpressionBuilder()->eq("$GROUP_MEMBER_ALIAS.userGroup", ':userGroup'))
                ->andWhere($this->entityManager->getExpressionBuilder()->isNotNull("$GROUP_MEMBER_ALIAS.childGroup"))
                ->setParameter(':userGroup', $userGroup->getIdentifier(), AuthorizationUuidBinaryType::NAME)
                ->getQuery()
                ->getSingleColumnResult();
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to get child group identifiers', ['exception' => $exception]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'getting child groups failed');
        }
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
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to get group ancestors', ['exception' => $exception]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'getting group ancestors failed');
        }
    }
}
