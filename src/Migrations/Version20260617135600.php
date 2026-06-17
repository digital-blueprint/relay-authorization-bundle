<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260617135600 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'add tables authorization_roles, authorization_role_names, authorization_role_actions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE authorization_roles (
                 identifier BINARY(16) NOT NULL,
                 PRIMARY KEY(identifier)
            )
        ');

        $this->addSql('
            CREATE TABLE authorization_role_names (
                role_identifier BINARY(16) NOT NULL,
                language_tag VARCHAR(2) NOT NULL,
                name VARCHAR(64) NOT NULL,
                PRIMARY KEY(role_identifier, language_tag),
                CONSTRAINT FK_role_identifier FOREIGN KEY (role_identifier)
                    REFERENCES authorization_roles(identifier) ON DELETE CASCADE
            )
        ');

        $this->addSql('
            CREATE TABLE authorization_role_actions (
                role_identifier BINARY(16) NOT NULL,
                available_resource_class_action_identifier BINARY(16) NOT NULL,
                PRIMARY KEY(role_identifier, available_resource_class_action_identifier)
            )
        ');

        // Add foreign key constraint for role_identifier
        $this->addSql('
            ALTER TABLE authorization_role_actions
            ADD CONSTRAINT FK_role_action_role_identifier FOREIGN KEY (role_identifier)
                REFERENCES authorization_roles(identifier) ON DELETE CASCADE
        ');
        // Add foreign key constraint for available_resource_class_action_identifier
        $this->addSql('
            ALTER TABLE authorization_role_actions
            ADD CONSTRAINT FK_role_action_available_resource_class_action_identifier FOREIGN KEY (available_resource_class_action_identifier)
                REFERENCES authorization_available_resource_class_actions(identifier) ON DELETE CASCADE
        ');
    }
}
