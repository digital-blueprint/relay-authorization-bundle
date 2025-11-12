<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251112093200 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'create the available_resource_class_actions table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE authorization_available_resource_class_actions (resource_class varchar(40) NOT NULL, action varchar(40) NOT NULL, PRIMARY KEY (resource_class, action)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
    }
}
