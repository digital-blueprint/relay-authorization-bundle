<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\TestUtils;

use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Entity\GroupMember;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Helper\AuthorizationUuidBinaryType;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\KernelInterface;

class TestEntityManager
{
    public const DEFAULT_RESOURCE_CLASS = 'resourceClass';
    public const DEFAULT_RESOURCE_IDENTIFIER = 'resourceIdentifier';

    private EntityManager $entityManager;

    public function __construct(KernelInterface $kernel)
    {
        if ('test' !== $kernel->getEnvironment()) {
            throw new \RuntimeException('Execution only in Test environment possible!');
        }

        $this->initDatabase($kernel);

        $entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->entityManager = $entityManager;
    }

    private function initDatabase(KernelInterface $kernel): void
    {
        $entityManager = $kernel->getContainer()->get('doctrine.orm.entity_manager');
        $metaData = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->updateSchema($metaData);
    }

    public function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    public function addResourceActionGrant(AuthorizationResource $resource, string $action,
        ?string $userIdentifier, ?Group $group = null, ?string $dynamicGroupIdentifier = null): ResourceActionGrant
    {
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setIdentifier(Uuid::uuid7()->toString());
        $resourceActionGrant->setAuthorizationResource($resource);
        $resourceActionGrant->setAction($action);
        $resourceActionGrant->setUserIdentifier($userIdentifier);
        $resourceActionGrant->setGroup($group);
        $resourceActionGrant->setDynamicGroupIdentifier($dynamicGroupIdentifier);

        try {
            $this->entityManager->persist($resourceActionGrant);
            $this->entityManager->flush();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }

        return $resourceActionGrant;
    }

    public function addAuthorizationResource(string $resourceClass = self::DEFAULT_RESOURCE_CLASS,
        ?string $resourceIdentifier = self::DEFAULT_RESOURCE_IDENTIFIER): AuthorizationResource
    {
        $resource = new AuthorizationResource();
        $resource->setIdentifier(Uuid::uuid7()->toString());
        $resource->setResourceClass($resourceClass);
        $resource->setResourceIdentifier($resourceIdentifier);

        try {
            $this->entityManager->persist($resource);
            $this->entityManager->flush();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }

        return $resource;
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

    public function getResourceActionGrantByIdentifier(string $identifier): ?ResourceActionGrant
    {
        try {
            return $this->entityManager->getRepository(ResourceActionGrant::class)
                ->findOneBy(['identifier' => $identifier]);
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function getResourceActionGrant(string $authorizationResourceIdentifier, string $action, string $userIdentifier): ?ResourceActionGrant
    {
        try {
            return $this->entityManager->getRepository(ResourceActionGrant::class)
                ->findOneBy([
                    'authorizationResource' => $authorizationResourceIdentifier,
                    'action' => $action,
                    'userIdentifier' => $userIdentifier,
                ]);
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

    public function getAuthorizationResourceByClassAndIdentifier(string $resourceClass, string $resourceIdntifier): ?AuthorizationResource
    {
        try {
            return $this->entityManager->getRepository(AuthorizationResource::class)
                ->findOneBy([
                    'resourceClass' => $resourceClass,
                    'resourceIdentifier' => $resourceIdntifier,
                ]);
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function addGroup(string $name = 'Testgroup'): Group
    {
        $group = new Group();
        $group->setIdentifier(Uuid::uuid7()->toString());
        $group->setName($name);

        try {
            $this->entityManager->persist($group);
            $this->entityManager->flush();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }

        return $group;
    }

    public function deleteGroup(string $identifier): void
    {
        try {
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder
                ->delete(Group::class, 'g')
                ->where($queryBuilder->expr()->eq('g.identifier', ':identifier'))
                ->setParameter(':identifier', $identifier, AuthorizationUuidBinaryType::NAME)
                ->getQuery()
                ->execute();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function getGroup(string $identifier)
    {
        try {
            return $this->entityManager->getRepository(Group::class)
                ->findOneBy(['identifier' => $identifier]);
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function addGroupMember(Group $group, ?string $userIdentifier, ?Group $childGroup = null): GroupMember
    {
        $groupMember = new GroupMember();
        $groupMember->setIdentifier(Uuid::uuid7()->toString());
        $groupMember->setGroup($group);
        $groupMember->setUserIdentifier($userIdentifier);
        $groupMember->setChildGroup($childGroup);

        try {
            $this->entityManager->persist($groupMember);
            $this->entityManager->flush();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }

        return $groupMember;
    }

    public function deleteGroupMember(string $identifier): void
    {
        try {
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder
                ->delete(GroupMember::class, 'gm')
                ->where($queryBuilder->expr()->eq('gm.identifier', ':identifier'))
                ->setParameter(':identifier', $identifier, AuthorizationUuidBinaryType::NAME)
                ->getQuery()
                ->execute();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function getGroupMember(string $identifier): ?GroupMember
    {
        try {
            return $this->entityManager->getRepository(GroupMember::class)
                ->findOneBy(['identifier' => $identifier]);
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }
}
