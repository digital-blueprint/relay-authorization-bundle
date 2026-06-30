<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroup;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroupMember;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260630083200 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'alter user (member) tables';
    }

    public function up(Schema $schema): void
    {
        // rename 'authorization_groups' to 'authorization_user_groups'
        $this->addSql('ALTER TABLE authorization_groups RENAME TO '.UserGroup::TABLE_NAME);
        // rename 'authorization_group_members' to 'authorization_user_group_members'
        $this->addSql('ALTER TABLE authorization_group_members RENAME TO '.UserGroupMember::TABLE_NAME);
        // rename authorization_user_group_members.parent_group_identifier to 'authorization_user_group_members.user_group_identifier
        $this->addSql('ALTER TABLE '.UserGroupMember::TABLE_NAME.' RENAME COLUMN parent_group_identifier TO '.UserGroupMember::USER_GROUP_IDENTIFIER_COLUMN);
        $this->addSql('ALTER TABLE '.ResourceActionGrant::TABLE_NAME.' RENAME COLUMN group_identifier TO '.ResourceActionGrant::USER_GROUP_IDENTIFIER_COLUMN);
        $this->addSql('ALTER TABLE '.ResourceActionGrant::TABLE_NAME.' RENAME COLUMN dynamic_group_identifier TO '.ResourceActionGrant::DYNAMIC_USER_GROUP_IDENTIFIER_COLUMN);
    }
}
