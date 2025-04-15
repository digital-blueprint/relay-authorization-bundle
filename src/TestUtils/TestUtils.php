<?php

declare(strict_types=1);

use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;

class TestUtils
{
    public static function getTestConfig(): array
    {
        return
            [
                Configuration::DATABASE_URL => 'sqlite:///:memory:',
            ];
    }
}
