<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Doctrine\DBAL\Schema\Schema;

final class Version20260727134500 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'add columns share_of_identifier, creator_id, date_created to resource_action_grant table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE '.ResourceActionGrant::TABLE_NAME.' 
            ADD share_of_identifier BINARY(16) DEFAULT NULL,
            ADD shareable BOOLEAN NOT NULL DEFAULT FALSE,
            ADD creator_id VARCHAR(40) DEFAULT NULL, 
            ADD date_created DATETIME DEFAULT NULL,
            ADD CONSTRAINT FK_grant_share_of_identifier FOREIGN KEY ('.ResourceActionGrant::SHARE_OF_IDENTIFIER_COLUMN.')
            REFERENCES '.ResourceActionGrant::TABLE_NAME.' ('.ResourceActionGrant::IDENTIFIER_COLUMN.') ON DELETE CASCADE');
    }
}
