<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251204142100 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'replace collection resource identifier NULL with "null" string';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE authorization_resources ar
            SET ar.resource_identifier = 'null'
            WHERE ar.resource_identifier IS NULL");
    }
}
