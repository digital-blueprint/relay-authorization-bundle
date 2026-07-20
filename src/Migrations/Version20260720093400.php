<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260720093400 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'add administrator role';
    }

    public function up(Schema $schema): void
    {
        $resourceActionGrantService = $this->container->get(ResourceActionGrantService::class);
        assert($resourceActionGrantService instanceof ResourceActionGrantService);

        $resourceActionGrantService->addRole(
            [
                'en' => 'Manager',
                'de' => 'Verwalter',
            ],
            [
                // works for both item and collection resources:
                ResourceActionGrantService::createRoleAction(
                    null, ResourceActionGrantService::MANAGE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    null, ResourceActionGrantService::MANAGE_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE),
            ],
            identifier: ResourceActionGrantService::MANAGER_ROLE_IDENTIFIER
        );

    }
}
