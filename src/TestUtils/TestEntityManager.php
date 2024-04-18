<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\TestUtils;

use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Helper\AuthorizationUuidBinaryType;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Ramsey\Uuid\Uuid;

class TestEntityManager
{
    private EntityManager $entityManager;

    public static function create(): TestEntityManager
    {
        try {
            Type::addType('relay_authorization_uuid_binary', AuthorizationUuidBinaryType::class);
            $config = ORMSetup::createAnnotationMetadataConfiguration([__DIR__.'/../../src/Entity'], true);
            $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
            $connection = DriverManager::getConnection( // EntityManager::create(
                [
                    'driver' => 'pdo_sqlite',
                    'memory' => true,
                ], $config
            );
            $connection->executeQuery('CREATE TABLE authorization_resource_action_grants (identifier binary(16) NOT NULL,
               namespace varchar(40) NOT NULL, resource_identifier varchar(40) DEFAULT NULL, action varchar(40) NOT NULL,
               user_identifier varchar(40) DEFAULT NULL, group_identifier binary(16) DEFAULT NULL, PRIMARY KEY(identifier))');

            return new TestEntityManager(new EntityManager($connection, $config));
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    public function addResourceActionGrant(string $namespace, ?string $resourceIdentifier, string $action, string $userIdentifier, ?string $groupIdentifer = null): ResourceActionGrant
    {
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setIdentifier(Uuid::uuid7()->toString());
        $resourceActionGrant->setNamespace($namespace);
        $resourceActionGrant->setResourceIdentifier($resourceIdentifier);
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

    public function deleteResourceActionGrant(string $identifier): void
    {
        try {
            $this->entityManager->createQueryBuilder()
                ->delete(ResourceActionGrant::class, 'r')
                ->where('r.identifier = :identifier')
                ->setParameter('identifier', $identifier)
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
}
