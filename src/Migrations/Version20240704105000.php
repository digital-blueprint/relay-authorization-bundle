<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240704105000 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'increase the length of dynamic group identifiers to fit the prefix for manage resource collection policies (resource class max length + 1)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE authorization_resource_action_grants MODIFY dynamic_group_identifier VARCHAR(41) NULL DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
    }
}
