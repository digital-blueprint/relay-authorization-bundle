<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\TestUtils;

use Symfony\Component\DependencyInjection\ContainerInterface;

class AuthorizationTest
{
    public static function setUp(ContainerInterface $container): void
    {
        TestEntityManager::setUpAuthorizationEntityManager($container);
    }
}
