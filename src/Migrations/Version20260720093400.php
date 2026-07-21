<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260720093400 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'add administrator role';
    }

    public function up(Schema $schema): void
    {
        InternalResourceActionGrantService::ensureManageActionsAndRoleAreAvailable($this->getEntityManager());
    }
}
