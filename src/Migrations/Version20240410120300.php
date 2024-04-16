<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Create table formalize_submission.
 */
final class Version20240410120300 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'initially create group and authorized entity table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE authorization_groups (
            identifier VARCHAR(50) NOT NULL, name VARCHAR(64) NOT NULL,
            PRIMARY KEY(identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE authorization_authorized_entities (
            identifier VARCHAR(50) NOT NULL, resource_action_identifier VARCHAR(50) NOT NULL,
            user_identifier VARCHAR(64), group_identifier VARCHAR(64),
            PRIMARY KEY(identifier),
            CONSTRAINT user_xor_group_null CHECK((user_identifier IS NULL AND group_identifier IS NOT NULL) OR (user_identifier IS NOT NULL AND group_identifier IS NULL)))                          
            DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE authorization_groups');
        $this->addSql('DROP TABLE authorization_authorized_entities');
    }
}
