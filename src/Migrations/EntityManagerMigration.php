<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Migrations;

use Dbp\Relay\CoreBundle\Doctrine\AbstractEntityManagerMigration;

abstract class EntityManagerMigration extends AbstractEntityManagerMigration
{
    private const EM_NAME = 'dbp_relay_authorization_bundle';

    protected function getEntityManagerId(): string
    {
        return self::EM_NAME;
    }
}
