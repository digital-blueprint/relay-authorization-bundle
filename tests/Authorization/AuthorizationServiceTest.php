<?php

declare(strict_types=1);

namespace Authorization;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractTestCase;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;

class AuthorizationServiceTest extends AbstractTestCase
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
        // self::CURRENT_USER_IDENTIFIER.'_5' has a 'read', 'delete', and 'write' grant (as a member of 'Testgroup')
        // self::CURRENT_USER_IDENTIFIER.'_6' has a 'read', 'delete', 'write' grant (as a member of 'Testgroup'), a 'delete' grant (as a member of dynamic group 'employees')

        $resource = $this->testEntityManager->addAuthorizationResource();
        $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->testEntityManager->addResourceActionGrant($resource, 'read', self::CURRENT_USER_IDENTIFIER.'_2');

        $group = $this->testEntityManager->addGroup();
        $this->testEntityManager->addGroupMember($group, self::CURRENT_USER_IDENTIFIER.'_3');
        $this->testEntityManager->addGroupMember($group, self::CURRENT_USER_IDENTIFIER.'_5');
        $this->testEntityManager->addGroupMember($group, self::CURRENT_USER_IDENTIFIER.'_6');

        $this->testEntityManager->addResourceActionGrant($resource, 'write', null, $group);

        $this->testEntityManager->addResourceActionGrant($resource, 'write', null, null, 'employees');

        $this->testEntityManager->addResourceActionGrant($resource, 'read', self::CURRENT_USER_IDENTIFIER.'_5');
        $this->testEntityManager->addResourceActionGrant($resource, 'delete', self::CURRENT_USER_IDENTIFIER.'_5');

        $this->testEntityManager->addResourceActionGrant($resource, 'read', self::CURRENT_USER_IDENTIFIER.'_6');
        $this->testEntityManager->addResourceActionGrant($resource, 'delete', self::CURRENT_USER_IDENTIFIER.'_6');

        // ----------------------------------------------------------------
        // user:
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

        // ----------------------------------------------------------------
        // user 2:
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

        // ----------------------------------------------------------------
        // user 3:
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

        // ----------------------------------------------------------------
        // user 4:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_4', $userAttributes);
        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, ['write']);
        $this->assertCount(1, $usersGrants);
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === null
                && $resourceActionGrant->getGroup() === null
                && $resourceActionGrant->getDynamicGroupIdentifier() === 'employees'
                && $resourceActionGrant->getAction() === 'write'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }));

        // ----------------------------------------------------------------
        // user 5:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_5');
        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $this->assertCount(3, $usersGrants);
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER.'_5'
                && $resourceActionGrant->getAction() === 'read'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER.'_5'
                && $resourceActionGrant->getAction() === 'delete'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) use ($group) {
            return $resourceActionGrant->getUserIdentifier() === null
                && $resourceActionGrant->getGroup() === $group
                && $resourceActionGrant->getAction() === 'write'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }));

        // test pagination (page size 2):
        $usersGrantsPage1 = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER,
            null, 0, 2);
        $this->assertCount(2, $usersGrantsPage1);
        $usersGrantsPage2 = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER,
            null, 2, 2);
        $this->assertCount(1, $usersGrantsPage2);
        $usersGrantsPage3 = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER,
            null, 4, 2);
        $this->assertCount(0, $usersGrantsPage3);
        $this->assertEmpty(array_uintersect($usersGrantsPage1, $usersGrantsPage2, function ($rag1, $rag2) {
            return strcmp($rag1->getIdentifier(), $rag2->getIdentifier());
        }));

        // ----------------------------------------------------------------
        // user 6:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_6', $userAttributes);
        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $this->assertCount(4, $usersGrants);
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER.'_6'
                && $resourceActionGrant->getAction() === 'read'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER.'_6'
                && $resourceActionGrant->getAction() === 'delete'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) use ($group) {
            return $resourceActionGrant->getUserIdentifier() === null
                && $resourceActionGrant->getGroup() === $group
                && $resourceActionGrant->getAction() === 'write'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === null
                && $resourceActionGrant->getGroup() === null
                && $resourceActionGrant->getDynamicGroupIdentifier() === 'employees'
                && $resourceActionGrant->getAction() === 'write'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }));

        // test pagination (page size 3):
        $usersGrantsPage1 = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, null, 0, 3);
        $this->assertCount(3, $usersGrantsPage1);
        $usersGrantsPage2 = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, null, 3, 3);
        $this->assertCount(1, $usersGrantsPage2);
        $usersGrantsPage3 = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, null, 6, 3);
        $this->assertCount(0, $usersGrantsPage3);
        $this->assertEmpty(array_uintersect($usersGrantsPage1, $usersGrantsPage2, function ($rag1, $rag2) {
            return strcmp($rag1->getIdentifier(), $rag2->getIdentifier());
        }));

        // test pagination (page size 0):
        $usersGrantsPage2 = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, null, 0, 0);
        $this->assertCount(0, $usersGrantsPage2);
    }

    public function testGetResourceItemActionGrantsForCurrentUserForAllResources(): void
    {
        $testGroup = $this->testEntityManager->addGroup();
        $this->testEntityManager->addGroupMember($testGroup, self::CURRENT_USER_IDENTIFIER.'_3');

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
        $this->testEntityManager->addResourceActionGrant($resource_2, 'write', null, $testGroup);

        // managed by user 3
        // writable by dynamic group 'employees'
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
            AuthorizationService::MANAGE_ACTION, null, $testGroup);
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

        // ----------------------------------------------------------------
        // user:
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
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER
                && $resourceActionGrant->getAction() === AuthorizationService::MANAGE_ACTION
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER
                && $resourceActionGrant->getAction() === 'read'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_2';
        }));

        // ----------------------------------------------------------------
        // user 2:
        // manage action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null,
            [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $usersGrants);
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER.'_2', $usersGrants[0]->getUserIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $usersGrants[0]->getAction());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_CLASS, $usersGrants[0]->getAuthorizationResource()->getResourceClass());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_2', $usersGrants[0]->getAuthorizationResource()->getResourceIdentifier());

        // any action
        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertCount(2, $usersGrants);
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER.'_2'
                && $resourceActionGrant->getAction() === AuthorizationService::MANAGE_ACTION
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_2';
        }));
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER.'_2'
                && $resourceActionGrant->getAction() === 'read'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }));

        // ----------------------------------------------------------------
        // user 3:
        // manage action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');
        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null,
            [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(2, $usersGrants);
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER.'_3'
                && $resourceActionGrant->getAction() === AuthorizationService::MANAGE_ACTION
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_3';
        }));
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) use ($testGroup) {
            return $resourceActionGrant->getUserIdentifier() === null
                && $resourceActionGrant->getGroup() === $testGroup
                && $resourceActionGrant->getAction() === AuthorizationService::MANAGE_ACTION
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_4';
        }));

        // any action
        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertCount(4, $usersGrants);
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER.'_3'
                && $resourceActionGrant->getAction() === AuthorizationService::MANAGE_ACTION
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_3';
        }));
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) use ($testGroup) {
            return $resourceActionGrant->getUserIdentifier() === null
                && $resourceActionGrant->getGroup() === $testGroup
                && $resourceActionGrant->getAction() === AuthorizationService::MANAGE_ACTION
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_4';
        }));
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER.'_3'
                && $resourceActionGrant->getAction() === 'read'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) use ($testGroup) {
            return $resourceActionGrant->getUserIdentifier() === null
                && $resourceActionGrant->getGroup() === $testGroup
                && $resourceActionGrant->getAction() === 'write'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_2';
        }));

        // test pagination (page size 3):
        $usersGrantsPage1 = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, null, 0, 3);
        $this->assertCount(3, $usersGrantsPage1);
        $usersGrantsPage2 = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, null, 3, 3);
        $this->assertCount(1, $usersGrantsPage2);
        $this->assertEmpty(array_uintersect($usersGrantsPage1, $usersGrantsPage2, function ($rag1, $rag2) {
            return strcmp($rag1->getIdentifier(), $rag2->getIdentifier());
        }));

        // ----------------------------------------------------------------
        // user 4:
        // manage action
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_4', $userAttributes);
        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null,
            [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $usersGrants);
        $this->assertEquals(null, $usersGrants[0]->getUserIdentifier());
        $this->assertEquals(null, $usersGrants[0]->getGroup());
        $this->assertEquals('employees', $usersGrants[0]->getDynamicGroupIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $usersGrants[0]->getAction());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_CLASS, $usersGrants[0]->getAuthorizationResource()->getResourceClass());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_5', $usersGrants[0]->getAuthorizationResource()->getResourceIdentifier());

        // any action
        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertCount(4, $usersGrants);
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === null
                && $resourceActionGrant->getGroup() === null
                && $resourceActionGrant->getDynamicGroupIdentifier() === 'employees'
                && $resourceActionGrant->getAction() === AuthorizationService::MANAGE_ACTION
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_5';
        }));
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER.'_4'
                && $resourceActionGrant->getAction() === 'read'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_4';
        }));
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER.'_4'
                && $resourceActionGrant->getAction() === 'read'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === null
                && $resourceActionGrant->getGroup() === null
                && $resourceActionGrant->getDynamicGroupIdentifier() === 'employees'
                && $resourceActionGrant->getAction() === 'write'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_3';
        }));

        // test pagination (page size 2):
        $usersGrantsPage1 = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, null, 0, 2);
        $this->assertCount(2, $usersGrantsPage1);
        $usersGrantsPage2 = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, null, 2, 2);
        $this->assertCount(2, $usersGrantsPage2);
        $usersGrantsPage3 = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, null, 4, 2);
        $this->assertCount(0, $usersGrantsPage3);
        $this->assertEmpty(array_uintersect($usersGrantsPage1, $usersGrantsPage2, function ($rag1, $rag2) {
            return strcmp($rag1->getIdentifier(), $rag2->getIdentifier());
        }));

        // ----------------------------------------------------------------
        // user 5 (student):
        // manage action
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_5', $userAttributes);
        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null,
            [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(0, $usersGrants);

        // read action
        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, ['read']);
        $this->assertCount(1, $usersGrants);
        $this->assertCount(1, $this->selectWhere($usersGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === null
                && $resourceActionGrant->getGroup() === null
                && $resourceActionGrant->getDynamicGroupIdentifier() === 'students'
                && $resourceActionGrant->getAction() === 'read'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_5';
        }));

        // test pagination (page size 1):
        $usersGrantsPage1 = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, null, 0, 1);
        $this->assertCount(1, $usersGrantsPage1);

        // test pagination (page size 0):
        $usersGrantsPage1 = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, null, 0, 0);
        $this->assertCount(0, $usersGrantsPage1);

        // ----------------------------------------------------------------
        // user 6:
        // any action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_6');
        $usersGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertCount(0, $usersGrants);
    }

    public function testGetResourceCollectionActionGrantsForCurrentUser(): void
    {
        // user: manage
        // user 2: read
        // user 3: read, write (as member of 'Testgroup')
        // user 4: delete (as member of dynamic group 'employees')
        // user 5: read, delete, write (as member of 'Testgroup'), delete (as member of dynamic group 'employees')
        $testGroup = $this->testEntityManager->addGroup();
        $this->testEntityManager->addGroupMember($testGroup, self::CURRENT_USER_IDENTIFIER.'_3');
        $this->testEntityManager->addGroupMember($testGroup, self::CURRENT_USER_IDENTIFIER.'_5');

        $resource = $this->testEntityManager->addAuthorizationResource(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);

        $userGrants = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $this->assertCount(0, $userGrants);

        $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource,
            'read', self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceActionGrant($resource,
            'read', self::CURRENT_USER_IDENTIFIER.'_3');
        $this->testEntityManager->addResourceActionGrant($resource,
            'read', self::CURRENT_USER_IDENTIFIER.'_5');
        $this->testEntityManager->addResourceActionGrant($resource,
            'delete', self::CURRENT_USER_IDENTIFIER.'_5');
        $this->testEntityManager->addResourceActionGrant($resource,
            'write', null, $testGroup);
        $this->testEntityManager->addResourceActionGrant($resource,
            'delete', null, null, 'employees');

        // ----------------------------------------------------------------
        // user:
        // manage action:
        $userGrants = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $userGrants);
        $this->assertCount(1, $this->selectWhere($userGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER
                && $resourceActionGrant->getAction() === AuthorizationService::MANAGE_ACTION
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === null;
        }));

        // any action:
        $userGrants = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $this->assertCount(1, $userGrants);
        $this->assertCount(1, $this->selectWhere($userGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER
                && $resourceActionGrant->getAction() === AuthorizationService::MANAGE_ACTION
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === null;
        }));

        // ----------------------------------------------------------------
        // user 2:
        // manage action:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $userGrants = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(0, $userGrants);

        // read action:
        $userGrants = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, ['read']);
        $this->assertCount(1, $userGrants);
        $this->assertCount(1, $this->selectWhere($userGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER.'_2'
                && $resourceActionGrant->getAction() === 'read'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === null;
        }));

        // any action:
        $userGrants = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $this->assertCount(1, $userGrants);
        $this->assertCount(1, $this->selectWhere($userGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER.'_2'
                && $resourceActionGrant->getAction() === 'read'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === null;
        }));

        // ----------------------------------------------------------------
        // user 3:
        // manage action:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');
        $userGrants = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(0, $userGrants);

        // read action:
        $userGrants = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, ['read']);
        $this->assertCount(1, $userGrants);
        $this->assertCount(1, $this->selectWhere($userGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER.'_3'
                && $resourceActionGrant->getAction() === 'read'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === null;
        }));

        // any action:
        $userGrants = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $this->assertCount(2, $userGrants);
        $this->assertCount(1, $this->selectWhere($userGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER.'_3'
                && $resourceActionGrant->getAction() === 'read'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === null;
        }));
        $this->assertCount(1, $this->selectWhere($userGrants, function ($resourceActionGrant) use ($testGroup) {
            return $resourceActionGrant->getUserIdentifier() === null
                && $resourceActionGrant->getGroup() === $testGroup
                && $resourceActionGrant->getAction() === 'write'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === null;
        }));

        // ----------------------------------------------------------------
        // user 4:
        // manage action:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_4', $userAttributes);
        $userGrants = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(0, $userGrants);

        // delete action:
        $userGrants = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, ['delete']);
        $this->assertCount(1, $userGrants);
        $this->assertCount(1, $this->selectWhere($userGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === null
                && $resourceActionGrant->getGroup() === null
                && $resourceActionGrant->getDynamicGroupIdentifier() === 'employees'
                && $resourceActionGrant->getAction() === 'delete'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === null;
        }));

        // any action:
        $userGrants = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $this->assertCount(1, $userGrants);
        $this->assertCount(1, $this->selectWhere($userGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === null
                && $resourceActionGrant->getGroup() === null
                && $resourceActionGrant->getDynamicGroupIdentifier() === 'employees'
                && $resourceActionGrant->getAction() === 'delete'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === null;
        }));

        // ----------------------------------------------------------------
        // user 5:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_5', $userAttributes);

        // delete action:
        $userGrants = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, ['delete']);
        $this->assertCount(2, $userGrants);
        $this->assertCount(1, $this->selectWhere($userGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === null
                && $resourceActionGrant->getGroup() === null
                && $resourceActionGrant->getDynamicGroupIdentifier() === 'employees'
                && $resourceActionGrant->getAction() === 'delete'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === null;
        }));
        $this->assertCount(1, $this->selectWhere($userGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER.'_5'
                && $resourceActionGrant->getGroup() === null
                && $resourceActionGrant->getDynamicGroupIdentifier() === null
                && $resourceActionGrant->getAction() === 'delete'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === null;
        }));

        // any action:
        $userGrants = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $this->assertCount(4, $userGrants);
        $this->assertCount(1, $this->selectWhere($userGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === null
                && $resourceActionGrant->getGroup() === null
                && $resourceActionGrant->getDynamicGroupIdentifier() === 'employees'
                && $resourceActionGrant->getAction() === 'delete'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === null;
        }));
        $this->assertCount(1, $this->selectWhere($userGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER.'_5'
                && $resourceActionGrant->getGroup() === null
                && $resourceActionGrant->getDynamicGroupIdentifier() === null
                && $resourceActionGrant->getAction() === 'delete'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === null;
        }));
        $this->assertCount(1, $this->selectWhere($userGrants, function ($resourceActionGrant) {
            return $resourceActionGrant->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER.'_5'
                && $resourceActionGrant->getGroup() === null
                && $resourceActionGrant->getDynamicGroupIdentifier() === null
                && $resourceActionGrant->getAction() === 'read'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === null;
        }));
        $this->assertCount(1, $this->selectWhere($userGrants, function ($resourceActionGrant) use ($testGroup) {
            return $resourceActionGrant->getUserIdentifier() === null
                && $resourceActionGrant->getGroup() === $testGroup
                && $resourceActionGrant->getDynamicGroupIdentifier() === null
                && $resourceActionGrant->getAction() === 'write'
                && $resourceActionGrant->getAuthorizationResource()->getResourceClass() === TestEntityManager::DEFAULT_RESOURCE_CLASS
                && $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier() === null;
        }));

        // test pagination (page size 2):
        $usersGrantsPage1 = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, 0, 2);
        $this->assertCount(2, $usersGrantsPage1);
        $usersGrantsPage2 = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, 2, 2);
        $this->assertCount(2, $usersGrantsPage2);
        $usersGrantsPage3 = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, 4, 2);
        $this->assertCount(0, $usersGrantsPage3);
        $this->assertEmpty(array_uintersect($usersGrantsPage1, $usersGrantsPage2, function ($rag1, $rag2) {
            return strcmp($rag1->getIdentifier(), $rag2->getIdentifier());
        }));

        // test pagination (page size 0):
        $usersGrantsPage1 = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, 0, 0);
        $this->assertCount(0, $usersGrantsPage1);
    }

    protected function selectWhere(array $results, callable $where): array
    {
        return array_filter($results, $where);
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
