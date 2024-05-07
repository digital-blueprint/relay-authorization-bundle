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
        $grants = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            self::TEST_RESOURCE_CLASS, null, 1, 10);
        $this->assertCount(0, $grants);

        $attributes = $this->getDefaultUserAttributes();
        $attributes['MAY_CREATE_TEST_RESOURCES'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $attributes);
        $grants = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            self::TEST_RESOURCE_CLASS, null, 1, 10);
        $this->assertCount(1, $grants);
    }

    public function testIsCurrentUserMemberOfDynamicGroup(): void
    {
        $this->assertFalse($this->authorizationService->isCurrentUserMemberOfDynamicGroup('students'));
        $this->assertFalse($this->authorizationService->isCurrentUserMemberOfDynamicGroup('employees'));

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $userAttributes);

        $this->assertTrue($this->authorizationService->isCurrentUserMemberOfDynamicGroup('students'));
        $this->assertFalse($this->authorizationService->isCurrentUserMemberOfDynamicGroup('employees'));

        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $userAttributes);

        $this->assertTrue($this->authorizationService->isCurrentUserMemberOfDynamicGroup('students'));
        $this->assertTrue($this->authorizationService->isCurrentUserMemberOfDynamicGroup('employees'));
    }

    public function testGetDynamicGroupsCurrentUserIsMemberOf(): void
    {
        $currentUsersDynamicGroups = $this->authorizationService->getDynamicGroupsCurrentUserIsMemberOf();
        $this->assertCount(0, $currentUsersDynamicGroups);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $userAttributes);

        $currentUsersDynamicGroups = $this->authorizationService->getDynamicGroupsCurrentUserIsMemberOf();
        $this->assertCount(1, $currentUsersDynamicGroups);
        $this->assertEquals('students', $currentUsersDynamicGroups[0]);

        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $userAttributes);

        $currentUsersDynamicGroups = $this->authorizationService->getDynamicGroupsCurrentUserIsMemberOf();
        $this->assertCount(2, $currentUsersDynamicGroups);
        $this->assertEquals('students', $currentUsersDynamicGroups[0]);
        $this->assertEquals('employees', $currentUsersDynamicGroups[1]);
    }

    protected function getTestConfig(): array
    {
        $config = parent::getTestConfig();
        $config[Configuration::RESOURCE_CLASSES] = [
            [
                Configuration::IDENTIFIER => self::TEST_RESOURCE_CLASS,
                Configuration::MANAGE_RESOURCE_COLLECTION_POLICY => 'user.get("MAY_CREATE_TEST_RESOURCES")',
            ],
        ];
        $config[Configuration::DYNAMIC_GROUPS] = [
            [
                Configuration::IDENTIFIER => 'students',
                Configuration::IS_CURRENT_USER_GROUP_MEMBER_EXPRESSION => 'user.get("IS_STUDENT")',
            ],
            [
                Configuration::IDENTIFIER => 'employees',
                Configuration::IS_CURRENT_USER_GROUP_MEMBER_EXPRESSION => 'user.get("IS_EMPLOYEE")',
            ],
        ];

        return $config;
    }

    protected function getDefaultUserAttributes(): array
    {
        $defaultUserAttributes = parent::getDefaultUserAttributes();
        $defaultUserAttributes['MAY_CREATE_TEST_RESOURCES'] = false;
        $defaultUserAttributes['IS_STUDENT'] = false;
        $defaultUserAttributes['IS_EMPLOYEE'] = false;

        return $defaultUserAttributes;
    }
}
