<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Dbp\Relay\AuthorizationBundle\DependencyInjection\DbpRelayAuthorizationExtension;
use Dbp\Relay\CoreBundle\Doctrine\AbstractEntityManagerMigration;

abstract class EntityManagerMigration extends AbstractEntityManagerMigration
{
    protected function getEntityManagerId(): string
    {
        return DbpRelayAuthorizationExtension::AUTHORIZATION_ENTITY_MANAGER_ID;
    }
}
