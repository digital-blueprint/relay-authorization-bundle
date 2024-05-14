<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class EntityManagerMigration extends AbstractMigration
{
    private const EM_NAME = 'dbp_relay_authorization_bundle';

    private ContainerInterface $container;

    #[Required]
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function preUp(Schema $schema): void
    {
        $this->skipInvalidDB();
    }

    public function preDown(Schema $schema): void
    {
        $this->skipInvalidDB();
    }

    private function getEntityManager(): EntityManager
    {
        $name = self::EM_NAME;
        $res = $this->container->get("doctrine.orm.{$name}_entity_manager");
        assert($res instanceof EntityManager);

        return $res;
    }

    private function skipInvalidDB(): void
    {
        $em = self::EM_NAME;
        $this->skipIf($this->connection !== $this->getEntityManager()->getConnection(),
            "Migration can't be executed on this connection, use --em={$em} to select the right one.'");
    }
}
