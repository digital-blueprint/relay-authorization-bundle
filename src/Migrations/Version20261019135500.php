<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261019135500 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'update owner actions and role names';
    }

    public function up(Schema $schema): void
    {
        InternalResourceActionGrantService::addOrUpdateOwnerActionsAndRoles($this->getEntityManager());
    }
}
