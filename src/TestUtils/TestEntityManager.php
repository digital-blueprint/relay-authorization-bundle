<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\TestUtils;

use Dbp\Relay\AuthorizationBundle\Entity\Resource;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\KernelInterface;

class TestEntityManager
{
    private EntityManager $entityManager;

    //    private static bool $haveCustomTypesBeenAdded = false;
    //
    //    public static function create(): TestEntityManager
    //    {
    //        try {
    //            if (!self::$haveCustomTypesBeenAdded) {
    //                Type::addType('relay_authorization_uuid_binary', AuthorizationUuidBinaryType::class);
    //                self::$haveCustomTypesBeenAdded = true;
    //            }
    //            $config = ORMSetup::createAnnotationMetadataConfiguration([__DIR__.'/../../src/Entity'], true);
    //            $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
    //            $connection = DriverManager::getConnection( // EntityManager::create(
    //                [
    //                    'driver' => 'pdo_sqlite',
    //                    'memory' => true,
    //                ], $config
    //            );
    //            $connection->executeQuery('CREATE TABLE authorization_resources (identifier binary(16) NOT NULL,
    //               resource_class varchar(40) NOT NULL, resource_identifier varchar(40) DEFAULT NULL, PRIMARY KEY(identifier))');
    //            $connection->executeQuery('CREATE TABLE authorization_resource_action_grants (identifier binary(16) NOT NULL,
    //               authorization_resource_identifier binary(16) NOT NULL, action varchar(40) NOT NULL,
    //               user_identifier varchar(40) DEFAULT NULL, group_identifier binary(16) DEFAULT NULL, PRIMARY KEY(identifier),
    //               CONSTRAINT foreign_key_authorization_resource_identifier FOREIGN KEY (authorization_resource_identifier) REFERENCES authorization_resources(identifier))');
    //
    //            return new TestEntityManager(new EntityManager($connection, $config));
    //        } catch (\Exception $exception) {
    //            throw new \RuntimeException($exception->getMessage());
    //        }
    //    }

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

    public function addResourceActionGrant(string $authorizationResourceIdentifier, string $action, string $userIdentifier, ?string $groupIdentifer = null): ResourceActionGrant
    {
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setIdentifier(Uuid::uuid7()->toString());
        $resourceActionGrant->setAuthorizationResourceIdentifier($authorizationResourceIdentifier);
        $resourceActionGrant->setAction($action);
        $resourceActionGrant->setUserIdentifier($userIdentifier);
        $resourceActionGrant->setGroupIdentifier($groupIdentifer);

        try {
            $this->entityManager->persist($resourceActionGrant);
            $this->entityManager->flush();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }

        return $resourceActionGrant;
    }

    public function addResource(string $resourceClass, ?string $resourceIdentifier): Resource
    {
        $resource = new Resource();
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
                ->setParameter(':identifier', $identifier, 'relay_authorization_uuid_binary')
                ->getQuery()
                ->execute();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function deleteResource(string $identifier): void
    {
        try {
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder
                ->delete(Resource::class, 'r')
                ->where($queryBuilder->expr()->eq('r.identifier', ':identifier'))
                ->setParameter(':identifier', $identifier, 'relay_authorization_uuid_binary')
                ->getQuery()
                ->execute();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function getResourceActionGrant(string $identifier): ?ResourceActionGrant
    {
        try {
            return $this->entityManager->getRepository(ResourceActionGrant::class)
                ->findOneBy(['identifier' => $identifier]);
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function getResource(string $identifier): ?Resource
    {
        try {
            return $this->entityManager->getRepository(Resource::class)
                ->findOneBy(['identifier' => $identifier]);
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }
}
