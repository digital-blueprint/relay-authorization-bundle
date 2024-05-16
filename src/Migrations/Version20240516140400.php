<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240516140400 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'drop foreign key constraints and add foreign keys with ON DELETE CASCADE';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE authorization_group_members DROP CONSTRAINT FK_D819EFC5147F90EB');
        $this->addSql('ALTER TABLE authorization_group_members ADD FOREIGN KEY (parent_group_identifier) REFERENCES authorization_groups(identifier) ON DELETE CASCADE ON UPDATE CASCADE');

        $this->addSql('ALTER TABLE authorization_group_members DROP CONSTRAINT FK_D819EFC5E50822CE');
        $this->addSql('ALTER TABLE authorization_group_members ADD FOREIGN KEY (child_group_identifier) REFERENCES authorization_groups(identifier) ON DELETE CASCADE ON UPDATE CASCADE');

        $this->addSql('ALTER TABLE authorization_resource_action_grants DROP CONSTRAINT FK_A1022A98213E78E1');
        $this->addSql('ALTER TABLE authorization_resource_action_grants ADD FOREIGN KEY (authorization_resource_identifier) REFERENCES authorization_resources(identifier) ON DELETE CASCADE ON UPDATE CASCADE');

        $this->addSql('ALTER TABLE authorization_resource_action_grants DROP CONSTRAINT FK_A1022A98A0E3E2D8');
        $this->addSql('ALTER TABLE authorization_resource_action_grants ADD FOREIGN KEY (group_identifier) REFERENCES authorization_groups(identifier) ON DELETE CASCADE ON UPDATE CASCADE');
    }

    public function down(Schema $schema): void
    {
    }
}
