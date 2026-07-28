<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
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
        $COLLECTION_RESOURCE_IDENTIFIER = AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER;
        $ITEM_ACTION_TYPE = AvailableResourceClassAction::ITEM_ACTION_TYPE;
        $COLLECTION_ACTION_TYPE = AvailableResourceClassAction::COLLECTION_ACTION_TYPE;

        $this->addSql('
             ALTER TABLE '.ResourceActionGrant::TABLE_NAME.'
             CHANGE action action VARCHAR(40) DEFAULT NULL
        ');

        $this->addSql('
            ALTER TABLE '.AvailableResourceClassAction::TABLE_NAME.'
            CHANGE '.AvailableResourceClassAction::RESOURCE_CLASS_COLUMN.' '.AvailableResourceClassAction::RESOURCE_CLASS_COLUMN.' VARCHAR(40) DEFAULT NULL
        ');

        $this->addSql('
            INSERT INTO '.AvailableResourceClassAction::TABLE_NAME." (identifier, resource_class, action, action_type)
            VALUES
                ($MANAGE_ITEM_ACTION_UUID, NULL, '$MANAGE_ACTION', $ITEM_ACTION_TYPE),
                ($MANAGE_COLLECTION_ACTION_UUID, NULL, '$MANAGE_ACTION', $COLLECTION_ACTION_TYPE)
        ");

        $this->addSql('
            ALTER TABLE '.ResourceActionGrant::TABLE_NAME.'
            ADD .'.ResourceActionGrant::AVAILABLE_RESOURCE_CLASS_ACTION_IDENTIFIER_COLUMN.' BINARY(16) DEFAULT NULL
        ');

        $this->addSql('
            ALTER TABLE '.ResourceActionGrant::TABLE_NAME.'
            ADD CONSTRAINT FK_available_resource_class_action_identifier
                FOREIGN KEY (available_resource_class_action_identifier) REFERENCES authorization_available_resource_class_actions (identifier) ON DELETE CASCADE
        ');

        $this->addSql('
            UPDATE '.ResourceActionGrant::TABLE_NAME.' rag
            JOIN '.AuthorizationResource::TABLE_NAME.' ar
                ON ar.identifier = rag.authorization_resource_identifier
            JOIN '.AvailableResourceClassAction::TABLE_NAME." arca
                ON rag.action = arca.action
                AND (
                    (ar.resource_identifier = '$COLLECTION_RESOURCE_IDENTIFIER' AND arca.action_type = $COLLECTION_ACTION_TYPE)
                    OR (ar.resource_identifier != '$COLLECTION_RESOURCE_IDENTIFIER' AND arca.action_type = $ITEM_ACTION_TYPE)
                )
                AND (
                    (arca.resource_class IS NOT NULL AND arca.resource_class = ar.resource_class)
                     OR (arca.resource_class IS NULL AND rag.action = '$MANAGE_ACTION')
                )
            SET rag.available_resource_class_action_identifier = arca.identifier
        ");
    }
}
