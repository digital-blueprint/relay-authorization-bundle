<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestResourceActionGrantServiceFactory;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Psr\Cache\CacheItemPoolInterface;

abstract class AbstractAuthorizationServiceTestCase extends AbstractInternalResourceActionGrantServiceTestCase
{
    protected AuthorizationService $authorizationService;
    protected ?CacheItemPoolInterface $cachePool = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorizationService = TestResourceActionGrantServiceFactory::createTestAuthorizationService(
            $this->testEntityManager->getEntityManager(),
            $this->eventDispatcher,
            $this->internalResourceActionGrantService,
            $this->getTestConfig(),
            self::CURRENT_USER_IDENTIFIER,
            $this->getDefaultUserAttributes());

        $this->cachePool = $this->authorizationService->getCache();
    }

    protected function login(?string $userIdentifier, ?array $userAttributes = null): void
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
            Configuration::CREATE_GROUPS_POLICY => 'user.get("MAY_CREATE_GROUPS")',
        ];
    }

    protected function getDefaultUserAttributes(): array
    {
        return [
            'MAY_CREATE_GROUPS' => false,
        ];
    }

    protected function addGroupAndManageGroupGrantForCurrentUser(string $groupName = 'Testgroup'): array
    {
        $group = $this->testEntityManager->addGroup($groupName);
        $manageGroupGrant = $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::GROUP_RESOURCE_CLASS, $group->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        return [$group, $manageGroupGrant];
    }
}
