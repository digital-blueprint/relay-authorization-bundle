<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

abstract class AbstractAuthorizationServiceTestCase extends AbstractInternalResourceActionGrantServiceTestCase
{
    protected AuthorizationService $authorizationService;
    protected ?ArrayAdapter $cachePool = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorizationService = new AuthorizationService(
            $this->internalResourceActionGrantService,
            new GroupService($this->testEntityManager->getEntityManager()),
            $this->testEntityManager->getEntityManager());

        $this->eventDispatcher->addSubscriber($this->authorizationService);

        $this->authorizationService->setConfig($this->getTestConfig());
        $this->authorizationService->setCache($this->cachePool = new ArrayAdapter());

        TestAuthorizationService::setUp($this->authorizationService,
            self::CURRENT_USER_IDENTIFIER, $this->getDefaultUserAttributes());
    }

    protected function login(string $userIdentifier, ?array $userAttributes = null): void
    {
        TestAuthorizationService::setUp($this->authorizationService, $userIdentifier,
            $userAttributes ?? $this->getDefaultUserAttributes());
    }

    protected function selectWhere(array $results, callable $where, bool $passInKeyToo = false): array
    {
        return array_filter($results, $where, $passInKeyToo ? ARRAY_FILTER_USE_BOTH : 0);
    }

    protected function containsResource(array $resources, mixed $resource): bool
    {
        foreach ($resources as $res) {
            if ($resource->getIdentifier() === $res->getIdentifier()) {
                return true;
            }
        }

        return false;
    }

    protected function assertIsPermutationOf(array $array1, array $array2): void
    {
        $this->assertTrue(count($array1) === count($array2)
            && count($array1) === count(array_intersect($array1, $array2)), 'arrays are no permutations of each other');
    }

    protected function getTestConfig(): array
    {
        return [
            'create_groups_policy' => 'user.get("MAY_CREATE_GROUPS")',
        ];
    }

    protected function getDefaultUserAttributes(): array
    {
        return [
            'MAY_CREATE_GROUPS' => false,
        ];
    }
}
