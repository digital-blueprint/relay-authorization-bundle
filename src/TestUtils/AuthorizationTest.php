<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\TestUtils;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AuthorizationTest
{
    public static function setUp(ContainerInterface $container): void
    {
        TestEntityManager::setUpEntityManager($container);
    }

    public static function tearDown(ContainerInterface $container): void
    {
        $authorizationService = self::getAuthorizationService($container);
        $authorizationService->clearRequestCache();
        $authorizationService->getCache()->clear();
    }

    public static function postRequestCleanup(ContainerInterface $container): void
    {
        self::getAuthorizationService($container)->clearRequestCache();
    }

    private static function getAuthorizationService(ContainerInterface $container): AuthorizationService
    {
        $authorizationService = $container->get(AuthorizationService::class);
        assert($authorizationService instanceof AuthorizationService);

        return $authorizationService;
    }
}
