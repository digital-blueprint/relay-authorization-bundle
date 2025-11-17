<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassActionName;
use Doctrine\DBAL\Schema\Schema;
use Ramsey\Uuid\Uuid;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251112093200 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'create the tables '.AvailableResourceClassAction::TABLE_NAME.' and '.AvailableResourceClassActionName::TABLE_NAME.' and populate them with the group actions';
    }

    public function up(Schema $schema): void
    {
        $availableResourceClassActionsTable = AvailableResourceClassAction::TABLE_NAME;
        $identifierColumn = AvailableResourceClassAction::IDENTIFIER_COLUMN_NAME;
        $resourceClassColumn = AvailableResourceClassAction::RESOURCE_CLASS_COLUMN_NAME;
        $actionColumn = AvailableResourceClassAction::ACTION_COLUMN_NAME;
        $actionTypeColumn = AvailableResourceClassAction::ACTION_TYPE_COLUMN_NAME;

        $this->addSql("CREATE TABLE $availableResourceClassActionsTable ($identifierColumn BINARY(16) NOT NULL, $resourceClassColumn varchar(40) NOT NULL, $actionColumn varchar(40) NOT NULL, $actionTypeColumn tinyint(1), PRIMARY KEY (identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("ALTER TABLE $availableResourceClassActionsTable ADD CONSTRAINT unique_resource_class_action UNIQUE ($resourceClassColumn, $actionColumn, $actionTypeColumn)");

        $availableResourceClassActionNamesTable = AvailableResourceClassActionName::TABLE_NAME;
        $availableResourceClassActionIdentifierColumn = AvailableResourceClassActionName::AVAILABLE_RESOURCE_CLASS_ACTION_IDENTIFIER_COLUMN_NAME;
        $languageTagColumn = AvailableResourceClassActionName::LANGUAGE_TAG_COLUMN_NAME;
        $nameColumn = AvailableResourceClassActionName::NAME_COLUMN_NAME;

        $createStatement = "CREATE TABLE $availableResourceClassActionNamesTable ($availableResourceClassActionIdentifierColumn BINARY(16) NOT NULL, $languageTagColumn VARCHAR(2) NOT NULL, $nameColumn VARCHAR(64) NOT NULL, PRIMARY KEY($availableResourceClassActionIdentifierColumn, $languageTagColumn), FOREIGN KEY($availableResourceClassActionIdentifierColumn) REFERENCES $availableResourceClassActionsTable($identifierColumn) ON DELETE CASCADE)";
        $this->addSql($createStatement);

        foreach (AuthorizationService::GROUP_COLLECTION_ACTIONS as $action => $actionNames) {
            $identifierBinary = Uuid::uuid7()->getBytes();
            $this->addSql("INSERT INTO $availableResourceClassActionsTable ($identifierColumn, $resourceClassColumn, $actionColumn, $actionTypeColumn) VALUES (?, ?, ?, ?)", [
                $identifierBinary,
                AuthorizationService::GROUP_RESOURCE_CLASS,
                $action,
                AvailableResourceClassAction::COLLECTION_ACTION_TYPE,
            ]);
            foreach ($actionNames as $languageTag => $name) {
                $this->addSql("INSERT INTO $availableResourceClassActionNamesTable ($availableResourceClassActionIdentifierColumn, $languageTagColumn, $nameColumn) VALUES (?, ?, ?)", [
                    $identifierBinary,
                    $languageTag,
                    $name,
                ]);
            }
        }
        foreach (AuthorizationService::GROUP_ITEM_ACTIONS as $action => $actionNames) {
            $identifierBinary = Uuid::uuid7()->getBytes();
            $this->addSql("INSERT INTO $availableResourceClassActionsTable ($identifierColumn, $resourceClassColumn, $actionColumn, $actionTypeColumn) VALUES (?, ?, ?, ?)", [
                $identifierBinary,
                AuthorizationService::GROUP_RESOURCE_CLASS,
                $action,
                AvailableResourceClassAction::ITEM_ACTION_TYPE,
            ]);
            foreach ($actionNames as $languageTag => $name) {
                $this->addSql("INSERT INTO $availableResourceClassActionNamesTable ($availableResourceClassActionIdentifierColumn, $languageTagColumn, $nameColumn) VALUES (?, ?, ?)", [
                    $identifierBinary,
                    $languageTag,
                    $name,
                ]);
            }
        }
    }

    public function down(Schema $schema): void
    {
    }
}
