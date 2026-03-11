<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\TestUtils;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AuthorizationTest
{
    public static function setUp(ContainerInterface $container): void
    {
        TestEntityManager::setUpAuthorizationEntityManager($container);
        // $container->get(AuthorizationService::class)->updateManageResourceCollectionPolicyGrants();
    }
}
