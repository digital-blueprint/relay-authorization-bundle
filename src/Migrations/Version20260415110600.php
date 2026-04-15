<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260415110600 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'replace collection resource identifier NULL with "null" string';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE authorization_groups MODIFY name VARCHAR(128) NOT NULL');
    }
}
