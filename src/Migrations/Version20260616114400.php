<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Uid\UuidV7;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260616114400 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'change authorization_resource_action_grants.action from action string to FK to authorization_available_resource_class_actions.identifier';
    }

    public function up(Schema $schema): void
    {
        $MANAGE_ITEM_ACTION_UUID = UuidV7::fromString(InternalResourceActionGrantService::MANAGE_ITEM_ACTION_UUID)->toHex();
        $MANAGE_COLLECTION_ACTION_UUID = UuidV7::fromString(InternalResourceActionGrantService::MANAGE_COLLECTION_ACTION_UUID)->toHex();
        $MANAGE_ACTION = AuthorizationService::MANAGE_ACTION;

        $this->addSql('
             ALTER TABLE authorization_resource_action_grants 
             CHANGE action action VARCHAR(40) DEFAULT NULL
        ');

        $this->addSql('
            ALTER TABLE authorization_available_resource_class_actions
            CHANGE resource_class resource_class VARCHAR(40) DEFAULT NULL
        ');

        $this->addSql("
            INSERT INTO authorization_available_resource_class_actions (identifier, resource_class, action, action_type)
            VALUES
                ($MANAGE_ITEM_ACTION_UUID, NULL, '$MANAGE_ACTION', 0),
                ($MANAGE_COLLECTION_ACTION_UUID, NULL, '$MANAGE_ACTION', 1)
        ");

        $this->addSql('
            ALTER TABLE authorization_resource_action_grants
            ADD available_resource_class_action_identifier BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid_binary)\'
        ');

        $this->addSql('
            ALTER TABLE authorization_resource_action_grants
            ADD CONSTRAINT FK_available_resource_class_action_identifier
                FOREIGN KEY (available_resource_class_action_identifier) REFERENCES authorization_available_resource_class_actions (identifier) ON DELETE CASCADE
        ');

        $this->addSql("
            UPDATE authorization_resource_action_grants rag
            JOIN authorization_resources ar
                ON ar.identifier = rag.authorization_resource_identifier
            JOIN authorization_available_resource_class_actions arca
                ON arca.resource_class = ar.resource_class AND arca.action = rag.action
                OR (arca.resource_class IS NULL AND rag.action = '$MANAGE_ACTION' AND ar.resource_identifier = 'null' AND arca.action_type = 1)
                OR (arca.resource_class IS NULL AND rag.action = '$MANAGE_ACTION' AND ar.resource_identifier != 'null' AND arca.action_type = 0)
            SET rag.available_resource_class_action_identifier = arca.identifier
        ");
    }
}
