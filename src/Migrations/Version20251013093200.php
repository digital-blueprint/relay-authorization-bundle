<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251013093200 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'add the authorization_grant_inheritance table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE authorization_grant_inheritances (identifier BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid_binary)\', source_authorization_resource_identifier BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid_binary)\', target_authorization_resource_identifier BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid_binary)\', PRIMARY KEY (identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE authorization_grant_inheritances ADD CONSTRAINT FK_5C8C8E3E213E78E1 FOREIGN KEY (source_authorization_resource_identifier) REFERENCES authorization_resources (identifier) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE authorization_grant_inheritances ADD CONSTRAINT FK_5C8C8E3E6F5DFD0B FOREIGN KEY (target_authorization_resource_identifier) REFERENCES authorization_resources (identifier) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
    }
}
