<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceGroupMember;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260706085400 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'alter user (member) tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE authorization_group_resource_members TO '.ResourceGroupMember::TABLE_NAME);
        $this->addSql('ALTER TABLE '.AuthorizationResource::TABLE_NAME.' ADD COLUMN '.AuthorizationResource::RESOURCE_TYPE_COLUMN.' BOOLEAN NOT NULL DEFAULT FALSE');
    }
}
