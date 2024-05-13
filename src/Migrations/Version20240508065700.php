<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240508065700 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'delete deprecate version of table authorization_resource_actions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE authorization_resource_actions');
    }

    public function down(Schema $schema): void
    {
    }
}
