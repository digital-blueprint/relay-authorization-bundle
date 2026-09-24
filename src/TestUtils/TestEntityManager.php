<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\TestUtils;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\DependencyInjection\DbpRelayAuthorizationExtension;
use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceGroupMember;
use Dbp\Relay\AuthorizationBundle\Entity\Role;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroup;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroupMember;
use Dbp\Relay\AuthorizationBundle\Helper\AuthorizationUuidBinaryType;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\CoreBundle\TestUtils\TestEntityManager as CoreTestEntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Uid\Uuid;

class TestEntityManager extends CoreTestEntityManager
{
    public const DEFAULT_RESOURCE_CLASS = 'resourceClass';
    public const DEFAULT_RESOURCE_IDENTIFIER = 'resourceIdentifier';

    public function __construct(ContainerInterface $container)
    {
        assert($container instanceof Container);
        parent::__construct($container, DbpRelayAuthorizationExtension::AUTHORIZATION_ENTITY_MANAGER_ID);
        self::addAvailableGroupResourceClassActions($this->getEntityManager());
    }

    public function addResourceActionGrant(AuthorizationResource $resource, ?string $action = null,
        ?string $userIdentifier = null, ?UserGroup $userGroup = null, ?string $dynamicUserGroupIdentifier = null,
        ?string $roleIdentifier = null, ?string $creatorId = null, ?bool $shareable = null,
        ?ResourceActionGrant $shareOf = null): ResourceActionGrant
    {
        return $this->addResourceActionGrantInternal(
            $resource,
            action: $action,
            userIdentifier: $userIdentifier,
            userGroup: $userGroup,
            dynamicUserGroupIdentifier: $dynamicUserGroupIdentifier,
            roleIdentifier: $roleIdentifier,
            creatorId: $creatorId,
            shareable: $shareable,
            shareOf: $shareOf
        );
    }

    public function addAuthorizationResourceAndActionGrant(
        string $resourceClass, string $resourceIdentifier,
        int $resourceType = ResourceActionGrantService::RESOURCE_RESOURCE_TYPE,
        ?string $action = null,
        ?string $userIdentifier = null, ?UserGroup $userGroup = null, ?string $dynamicUserGroupIdentifier = null,
        ?string $roleIdentifier = null,
        ?string $creatorId = null): ResourceActionGrant
    {
        $authorizationResource = $this->addAuthorizationResource(
            $resourceClass, $resourceIdentifier, $resourceType);

        return $this->addResourceActionGrant($authorizationResource,
            action: $action,
            userIdentifier: $userIdentifier,
            userGroup: $userGroup,
            dynamicUserGroupIdentifier: $dynamicUserGroupIdentifier,
            roleIdentifier: $roleIdentifier,
            creatorId: $creatorId
        );
    }

    public function addAuthorizationResource(string $resourceClass = self::DEFAULT_RESOURCE_CLASS,
        string $resourceIdentifier = self::DEFAULT_RESOURCE_IDENTIFIER,
        int $resourceType = ResourceActionGrantService::RESOURCE_RESOURCE_TYPE): AuthorizationResource
    {
        $authorizationResource = $this->entityManager->getRepository(AuthorizationResource::class)->findOneBy([
            'resourceClass' => $resourceClass,
            'resourceIdentifier' => $resourceIdentifier,
            'resourceType' => $resourceType,
        ]);
        if (null === $authorizationResource) {
            $authorizationResource = new AuthorizationResource();
            $authorizationResource->setIdentifier(Uuid::v7()->toRfc4122());
            $authorizationResource->setResourceClass($resourceClass);
            $authorizationResource->setResourceIdentifier($resourceIdentifier);
            $authorizationResource->setResourceType($resourceType);

            try {
                $this->entityManager->persist($authorizationResource);
                $this->entityManager->flush();
            } catch (\Exception $exception) {
                throw new \RuntimeException($exception->getMessage());
            }
        }

        return $authorizationResource;
    }

    public function deleteResourceActionGrant(string $identifier): void
    {
        try {
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder
                ->delete(ResourceActionGrant::class, 'r')
                ->where($queryBuilder->expr()->eq('r.identifier', ':identifier'))
                ->setParameter(':identifier', $identifier, AuthorizationUuidBinaryType::NAME)
                ->getQuery()
                ->execute();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function deleteAuthorizationResource(string $identifier): void
    {
        try {
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder
                ->delete(AuthorizationResource::class, 'r')
                ->where($queryBuilder->expr()->eq('r.identifier', ':identifier'))
                ->setParameter(':identifier', $identifier, AuthorizationUuidBinaryType::NAME)
                ->getQuery()
                ->execute();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function getRoleByIdentifier(string $identifier): ?Role
    {
        try {
            $role = $this->entityManager->getRepository(Role::class)
                ->findOneBy(['identifier' => $identifier]);
        } catch (\Exception $exception) {
            throw new \RuntimeException('failed to get role: '.$exception->getMessage());
        }

        if (null === $role) {
            throw new \RuntimeException("role with identifier {$identifier} not found");
        }

        return $role;
    }

    public function getResourceActionGrantByIdentifier(string $identifier): ?ResourceActionGrant
    {
        try {
            return $this->entityManager->getRepository(ResourceActionGrant::class)
                ->findOneBy(['identifier' => $identifier]);
        } catch (\Exception $exception) {
            throw new \RuntimeException('failed to get resource action grant: '.$exception->getMessage());
        }
    }

    public function getResourceActionGrant(string $authorizationResourceIdentifier, string $action, string $userIdentifier): ?ResourceActionGrant
    {
        try {
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder
                ->select('rag')
                ->from(ResourceActionGrant::class, 'rag')
                ->innerJoin(AvailableResourceClassAction::class, 'arca', Join::WITH, 'rag.availableResourceClassAction = arca.identifier')
                ->where($queryBuilder->expr()->eq('rag.authorizationResource', ':authorizationResource'))
                ->setParameter(':authorizationResource', $authorizationResourceIdentifier, AuthorizationUuidBinaryType::NAME)
                ->andWhere($queryBuilder->expr()->eq('arca.action', ':action'))
                ->setParameter(':action', $action);

            return $queryBuilder
                ->getQuery()
                ->getOneOrNullResult();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    /**
     * @return ResourceActionGrant[]
     */
    public function getResourceActionGrants(string $authorizationResourceIdentifier, ?string $action = null): array
    {
        try {
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder
                ->select('rag')
                ->from(ResourceActionGrant::class, 'rag')
                ->innerJoin(AvailableResourceClassAction::class, 'arca', Join::WITH, 'rag.availableResourceClassAction = arca.identifier')
                ->where($queryBuilder->expr()->eq('rag.authorizationResource', ':authorizationResource'))
                ->setParameter(':authorizationResource', $authorizationResourceIdentifier, AuthorizationUuidBinaryType::NAME);

            if ($action !== null) {
                $queryBuilder
                    ->andWhere($queryBuilder->expr()->eq('arca.action', ':action'))
                    ->setParameter(':action', $action);
            }

            return $queryBuilder
                ->getQuery()
                ->getResult();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function getAuthorizationResourceByIdentifier(string $identifier): ?AuthorizationResource
    {
        try {
            return $this->entityManager->getRepository(AuthorizationResource::class)
                ->findOneBy(['identifier' => $identifier]);
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function getAuthorizationResourceByResourceClassAndIdentifier(
        string $resourceClass, string $resourceIdentifier,
        int $resourceType = ResourceActionGrantService::RESOURCE_RESOURCE_TYPE): ?AuthorizationResource
    {
        $AUTHORIZATION_RESOURCE_ALIAS = 'ar';
        $expressionBuilder = $this->entityManager->getExpressionBuilder();
        try {
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder
                ->select($AUTHORIZATION_RESOURCE_ALIAS)
                ->from(AuthorizationResource::class, $AUTHORIZATION_RESOURCE_ALIAS)
                ->where($expressionBuilder->eq("$AUTHORIZATION_RESOURCE_ALIAS.resourceClass", ':resourceClass'))
                ->setParameter(':resourceClass', $resourceClass)
                ->andWhere($expressionBuilder->eq("$AUTHORIZATION_RESOURCE_ALIAS.resourceIdentifier", ':resourceIdentifier'))
                ->setParameter(':resourceIdentifier', $resourceIdentifier)
                ->andWhere($expressionBuilder->eq("$AUTHORIZATION_RESOURCE_ALIAS.resourceType", ':resourceType'))
                ->setParameter(':resourceType', $resourceType);

            return $queryBuilder
                ->getQuery()
                ->getResult()[0] ?? null;
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function addUserGroup(string $name = 'Testgroup'): UserGroup
    {
        $userGroup = new UserGroup();
        $userGroup->setIdentifier(Uuid::v7()->toRfc4122());
        $userGroup->setName($name);

        try {
            $this->entityManager->persist($userGroup);
            $this->entityManager->flush();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }

        return $userGroup;
    }

    public function deleteUserGroup(string $identifier): void
    {
        try {
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder
                ->delete(UserGroup::class, 'g')
                ->where($queryBuilder->expr()->eq('g.identifier', ':identifier'))
                ->setParameter(':identifier', $identifier, AuthorizationUuidBinaryType::NAME)
                ->getQuery()
                ->execute();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function getUserGroup(string $identifier): ?UserGroup
    {
        try {
            return $this->entityManager->getRepository(UserGroup::class)
                ->findOneBy(['identifier' => $identifier]);
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function addUserGroupMember(UserGroup $userGroup, ?string $userIdentifier = null, ?UserGroup $childGroup = null): UserGroupMember
    {
        $userGroupMember = new UserGroupMember();
        $userGroupMember->setIdentifier(Uuid::v7()->toRfc4122());
        $userGroupMember->setUserGroup($userGroup);
        $userGroupMember->setUserIdentifier($userIdentifier);
        $userGroupMember->setChildGroup($childGroup);

        try {
            $this->entityManager->persist($userGroupMember);
            $this->entityManager->flush();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }

        return $userGroupMember;
    }

    public function deleteUserGroupMember(string $identifier): void
    {
        try {
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder
                ->delete(UserGroupMember::class, 'gm')
                ->where($queryBuilder->expr()->eq('gm.identifier', ':identifier'))
                ->setParameter(':identifier', $identifier, AuthorizationUuidBinaryType::NAME)
                ->getQuery()
                ->execute();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function getUserGroupMember(string $identifier): ?UserGroupMember
    {
        try {
            return $this->entityManager->getRepository(UserGroupMember::class)
                ->findOneBy(['identifier' => $identifier]);
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    private function addResourceActionGrantInternal(AuthorizationResource $authorizationResource, ?string $action,
        ?string $userIdentifier = null, ?UserGroup $userGroup = null, ?string $dynamicUserGroupIdentifier = null,
        ?string $roleIdentifier = null, ?string $creatorId = null, ?bool $shareable = null, ?ResourceActionGrant $shareOf = null): ResourceActionGrant
    {
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setIdentifier(Uuid::v7()->toRfc4122());
        $resourceActionGrant->setAuthorizationResource($authorizationResource);
        $resourceActionGrant->setAction($action);
        if ($action !== null) {
            $resourceActionGrant->setAvailableResourceClassAction(
                InternalResourceActionGrantService::getAvailableResourceClassActionStatic(
                    $this->entityManager,
                    $resourceActionGrant->getResourceClass(),
                    $action,
                    AvailableResourceClassAction::getActionTypeForResourceIdentifier($authorizationResource->getResourceIdentifier())
                )
            );
            if ($resourceActionGrant->getAvailableResourceClassAction() === null) {
                throw new \RuntimeException("action {$action} not defined for resource class {$resourceActionGrant->getResourceClass()}");
            }
        }
        if (null !== $roleIdentifier) {
            $resourceActionGrant->setRole($this->getRoleByIdentifier($roleIdentifier));
        }
        $resourceActionGrant->setUserIdentifier($userIdentifier);
        $resourceActionGrant->setUserGroup($userGroup);
        $resourceActionGrant->setDynamicUserGroupIdentifier($dynamicUserGroupIdentifier);
        $resourceActionGrant->setCreatorId($creatorId);
        if ($shareable !== null) {
            $resourceActionGrant->setShareable($shareable);
        }
        if ($shareOf !== null) {
            $resourceActionGrant->setShareOf($shareOf);
        }
        $resourceActionGrant->setDateCreated(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        try {
            $this->entityManager->persist($resourceActionGrant);
            $this->entityManager->flush();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }

        return $resourceActionGrant;
    }

    public function getResourceGroupMember(string $identifier): ?ResourceGroupMember
    {
        try {
            return $this->entityManager->getRepository(ResourceGroupMember::class)
                ->findOneBy(['identifier' => $identifier]);
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function addResourceToResourceGroup(string $resourceClass, string $resourceGroupResourceIdentifier,
        string $resourceIdentifier, int $resourceType = ResourceActionGrantService::RESOURCE_RESOURCE_TYPE): ResourceGroupMember
    {
        $groupAuthorizationResourceMember = new ResourceGroupMember();
        $groupAuthorizationResourceMember->setIdentifier(Uuid::v7()->toRfc4122());
        $groupAuthorizationResourceMember->setGroupAuthorizationResource(
            $this->getAuthorizationResourceByResourceClassAndIdentifier(
                $resourceClass, $resourceGroupResourceIdentifier, ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE)
        );
        $groupAuthorizationResourceMember->setMemberAuthorizationResource(
            $this->getAuthorizationResourceByResourceClassAndIdentifier($resourceClass, $resourceIdentifier, $resourceType)
        );
        assert($groupAuthorizationResourceMember->getGroupAuthorizationResource() !== null);
        assert($groupAuthorizationResourceMember->getMemberAuthorizationResource() !== null);

        try {
            $this->entityManager->persist($groupAuthorizationResourceMember);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            throw new \RuntimeException($exception->getMessage());
        }

        return $groupAuthorizationResourceMember;
    }

    public function isMemberOfResourceGroup(string $resourceClass,
        string $resourceGroupResourceIdentifier,
        string $resourceIdentifier,
        int $resourceType = ResourceActionGrantService::RESOURCE_RESOURCE_TYPE): bool
    {
        try {
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder
                ->select('COUNT(rgm.identifier)')
                ->from(ResourceGroupMember::class, 'rgm')
                ->innerJoin(AuthorizationResource::class, 'gar',
                    Join::WITH, 'rgm.groupAuthorizationResource = gar.identifier')
                ->innerJoin(AuthorizationResource::class, 'mar',
                    Join::WITH, 'rgm.memberAuthorizationResource = mar.identifier')
                ->where($queryBuilder->expr()->eq('gar.resourceClass', ':resourceClass'))
                ->setParameter(':resourceClass', $resourceClass)
                ->andWhere($queryBuilder->expr()->eq('gar.resourceIdentifier', ':groupResourceIdentifier'))
                ->setParameter(':groupResourceIdentifier', $resourceGroupResourceIdentifier)
                ->andWhere($queryBuilder->expr()->eq('mar.resourceClass', ':resourceClass'))
                ->setParameter(':resourceClass', $resourceClass)
                ->andWhere($queryBuilder->expr()->eq('mar.resourceIdentifier', ':memberResourceIdentifier'))
                ->setParameter(':memberResourceIdentifier', $resourceIdentifier)
                ->andWhere($queryBuilder->expr()->eq('mar.resourceType', ':memberResourceType'))
                ->setParameter(':memberResourceType', $resourceType)
            ;

            return (int) $queryBuilder
                    ->getQuery()
                    ->getSingleScalarResult() > 0;
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    private static function addAvailableGroupResourceClassActions(EntityManagerInterface $entityManager): void
    {
        InternalResourceActionGrantService::addOrUpdateAvailableResourceClassActionsStatic($entityManager,
            AuthorizationService::GROUP_RESOURCE_CLASS,
            AuthorizationService::GROUP_ITEM_ACTIONS,
            AuthorizationService::GROUP_COLLECTION_ACTIONS);
    }

    public function clear(): void
    {
        $this->entityManager->clear();
    }

    /**
     * @return AvailableResourceClassAction[]
     */
    public function getAvailableResourceClassActions(string $resourceClass, ?int $actionType): array
    {
        $criteria = [
            'resourceClass' => $resourceClass,
        ];
        if ($actionType !== null) {
            $criteria['actionType'] = $actionType;
        }

        try {
            return $this->entityManager->getRepository(AvailableResourceClassAction::class)
                ->findBy($criteria);
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    /**
     * Drops all tables in the database to simulate a database error.
     */
    public function prepareDBError(): void
    {
        $connection = $this->entityManager->getConnection();
        try {
            $this->entityManager->clear(); // Clear the entity manager to avoid finding cached entities
            $connection->executeStatement('PRAGMA foreign_keys = OFF');
            $schemaManager = $connection->createSchemaManager();
            $tableNames = array_map(fn ($tableName) => $tableName->getUnqualifiedName()->getValue(), $schemaManager->introspectTableNames());
            foreach ($tableNames as $tableName) {
                $this->entityManager->getConnection()->executeStatement('DROP TABLE IF EXISTS `'.$tableName.'`');
            }
        } catch (\Throwable $throwable) {
            throw new \RuntimeException('Failed to drop all tables in the database: '.$throwable->getMessage());
        } finally {
            try {
                $connection->executeStatement('PRAGMA foreign_keys = ON');
            } catch (\Throwable $throwable) {
                throw new \RuntimeException('Failed to re-enable foreign key checks: '.$throwable->getMessage());
            }
        }
    }
}
