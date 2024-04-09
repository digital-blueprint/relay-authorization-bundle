<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Create table formalize_submission.
 */
final class Version20240409084900 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'initially create tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE authorization_resource_actions (
            identifier VARCHAR(50) NOT NULL, namespace VARCHAR(64) NOT NULL, resource_identifier VARCHAR(64),
            action VARCHAR(64), PRIMARY KEY(identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE authorization_resource_actions');
    }
}
