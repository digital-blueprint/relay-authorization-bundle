<?php

declare(strict_types=1);

namespace Authorization;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Tests\Rest\AbstractTest;

class AuthorizationServiceTest extends AbstractTest
{
    private const TEST_RESOURCE_CLASS = 'Vendor/Package/TestResource';

    public function testIsCurrentUserAuthorizedToReadResource(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToReadResource($resource));

        $resourceActionGrant = $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->assertNotNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier()));

        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToReadResource($resource));
    }

    public function testManageResourceCollectionPolicy(): void
    {
        $grants = $this->authorizationService->getResourceCollectionActionGrants(
            self::TEST_RESOURCE_CLASS, null, 1, 10);
        $this->assertCount(0, $grants);

        $attributes = $this->getDefaultUserAttributes();
        $attributes['MAY_CREATE_TEST_RESOURCES'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $attributes);
        $grants = $this->authorizationService->getResourceCollectionActionGrants(
            self::TEST_RESOURCE_CLASS, null, 1, 10);
        $this->assertCount(1, $grants);
    }

    protected function getTestConfig(): array
    {
        $config = parent::getTestConfig();
        $config['resource_classes'] = [
            [
                Configuration::RESOURCE_CLASS_IDENTIFIER => self::TEST_RESOURCE_CLASS,
                Configuration::MANAGE_RESOURCE_COLLECTION_POLICY => 'user.get("MAY_CREATE_TEST_RESOURCES")',
            ],
        ];

        return $config;
    }

    protected function getDefaultUserAttributes(): array
    {
        $defaultUserAttributes = parent::getDefaultUserAttributes();
        $defaultUserAttributes['MAY_CREATE_TEST_RESOURCES'] = false;

        return $defaultUserAttributes;
    }
}
