<?php

declare(strict_types=1);

namespace Authorization;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractTest;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;

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

    public function testGetResourceItemActionGrantsForCurrentUserWithSingleResource(): void
    {
        // self::CURRENT_USER_IDENTIFIER has a 'manage' grant
        // self::CURRENT_USER_IDENTIFIER.'_2' has a 'read' grant
        // self::CURRENT_USER_IDENTIFIER.'_3' has a 'write' grant (as a member of 'Testgroup')
        // self::CURRENT_USER_IDENTIFIER.'_4' has a 'delete' grant (as a member of dynamic group 'employees')

        $resource = $this->testEntityManager->addAuthorizationResource();
        $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->testEntityManager->addResourceActionGrant($resource, 'read', self::CURRENT_USER_IDENTIFIER.'_2');

        $group = $this->testEntityManager->addGroup();
        $this->testEntityManager->addGroupMember($group, self::CURRENT_USER_IDENTIFIER.'_3');
        $this->testEntityManager->addResourceActionGrant($resource, 'write', null, $group);

        $this->testEntityManager->addResourceActionGrant($resource, 'write', null, null, 'employees');

        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER,
            [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $usersGrants);
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $usersGrants[0]->getUserIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $usersGrants[0]->getAction());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_CLASS, $usersGrants[0]->getAuthorizationResource()->getResourceClass());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $usersGrants[0]->getAuthorizationResource()->getResourceIdentifier());

        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $this->assertCount(1, $usersGrants);
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $usersGrants[0]->getAction());

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, ['read']);
        $this->assertCount(1, $usersGrants);
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER.'_2', $usersGrants[0]->getUserIdentifier());
        $this->assertEquals('read', $usersGrants[0]->getAction());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_CLASS, $usersGrants[0]->getAuthorizationResource()->getResourceClass());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $usersGrants[0]->getAuthorizationResource()->getResourceIdentifier());

        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $this->assertCount(1, $usersGrants);
        $this->assertEquals('read', $usersGrants[0]->getAction());

        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');
        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, ['write']);
        $this->assertCount(1, $usersGrants);
        $this->assertEquals(null, $usersGrants[0]->getUserIdentifier());
        $this->assertEquals($group, $usersGrants[0]->getGroup());
        $this->assertEquals('write', $usersGrants[0]->getAction());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_CLASS, $usersGrants[0]->getAuthorizationResource()->getResourceClass());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $usersGrants[0]->getAuthorizationResource()->getResourceIdentifier());

        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $this->assertCount(1, $usersGrants);
        $this->assertEquals(null, $usersGrants[0]->getUserIdentifier());
        $this->assertEquals($group, $usersGrants[0]->getGroup());
        $this->assertEquals('write', $usersGrants[0]->getAction());

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_4', $userAttributes);
        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, ['write']);
        $this->assertCount(1, $usersGrants);
    }

    public function testGetResourceItemActionGrantsForCurrentUserForAllResources(): void
    {
        $group = $this->testEntityManager->addGroup();
        $this->testEntityManager->addGroupMember($group, self::CURRENT_USER_IDENTIFIER.'_3');

        // managed by user
        // readable by user 2
        // readable by user 3
        // readable by user 4
        $resource = $this->testEntityManager->addAuthorizationResource(TestEntityManager::DEFAULT_RESOURCE_CLASS,
            TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource, 'read', self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceActionGrant($resource, 'read', self::CURRENT_USER_IDENTIFIER.'_3');
        $this->testEntityManager->addResourceActionGrant($resource, 'read', self::CURRENT_USER_IDENTIFIER.'_4');

        // managed by user 2
        // readable by user
        // writable by group 'Testgroup'
        $resource_2 = $this->testEntityManager->addAuthorizationResource(TestEntityManager::DEFAULT_RESOURCE_CLASS,
            TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceActionGrant($resource_2,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceActionGrant($resource_2, 'read', self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource_2, 'write', null, $group);

        // managed by user 3
        // readably by dynamic group 'employees'
        $resource_3 = $this->testEntityManager->addAuthorizationResource(TestEntityManager::DEFAULT_RESOURCE_CLASS,
            TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_3');
        $this->testEntityManager->addResourceActionGrant($resource_3,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER.'_3');
        $this->testEntityManager->addResourceActionGrant($resource_3, 'write', null, null, 'employees');

        // managed by group 'Testgroup'
        // readable by user 4
        $resource_4 = $this->testEntityManager->addAuthorizationResource(TestEntityManager::DEFAULT_RESOURCE_CLASS,
            TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_4');
        $this->testEntityManager->addResourceActionGrant($resource_4,
            AuthorizationService::MANAGE_ACTION, null, $group);
        $this->testEntityManager->addResourceActionGrant($resource_4,
            'read', self::CURRENT_USER_IDENTIFIER.'_4');

        // managed by dynamic group 'employees'
        // readable by dynamic group 'students'
        $resource_5 = $this->testEntityManager->addAuthorizationResource(TestEntityManager::DEFAULT_RESOURCE_CLASS,
            TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_5');
        $this->testEntityManager->addResourceActionGrant($resource_5,
            AuthorizationService::MANAGE_ACTION, null, null, 'employees');
        $this->testEntityManager->addResourceActionGrant($resource_5,
            'read', null, null, 'students');

        // manage action
        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null,
            [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $usersGrants);
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $usersGrants[0]->getUserIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $usersGrants[0]->getAction());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_CLASS, $usersGrants[0]->getAuthorizationResource()->getResourceClass());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $usersGrants[0]->getAuthorizationResource()->getResourceIdentifier());

        // any action
        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertCount(2, $usersGrants);
        $this->assertCountWhere(1, $usersGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER
                && $resourceActionGrant->getAction() === AuthorizationService::MANAGE_ACTION
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        });
        $this->assertCountWhere(1, $usersGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER
                && $resourceActionGrant->getAction() === 'read'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_2';
        });
    }

    protected function assertCountWhere(int $expectedCount, array $results, callable $isTrue): void
    {
        $this->assertCount($expectedCount, array_filter($results, $isTrue));
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
