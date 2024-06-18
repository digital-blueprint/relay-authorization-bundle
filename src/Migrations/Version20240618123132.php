<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240618123132 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'replace the false authorization_group_members.child_group_identifier unique constraint by a normal index';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE authorization_group_members DROP INDEX UNIQ_D819EFC5E50822CE, ADD INDEX IDX_D819EFC5E50822CE (child_group_identifier)');
    }

    public function down(Schema $schema): void
    {
    }
}
