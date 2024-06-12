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
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, null, 1, 10);
        $this->assertNull($resourceCollectionActions);

        $attributes = $this->getDefaultUserAttributes();
        $attributes['MAY_CREATE_TEST_RESOURCES'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $attributes);
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, null, 1, 10);
        $this->assertEquals(null, $resourceCollectionActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceCollectionActions->getActions());
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

    public function testGetResourceItemActionsForCurrentUser(): void
    {
        // self::CURRENT_USER_IDENTIFIER has a 'manage' grant
        // self::CURRENT_USER_IDENTIFIER.'_2' has a 'read' grant
        // self::CURRENT_USER_IDENTIFIER.'_3' has a 'write' grant (as a member of group1)
        // self::CURRENT_USER_IDENTIFIER.'_4' has a 'delete' grant (as a member of dynamic group 'employees')
        // self::CURRENT_USER_IDENTIFIER.'_5' has a 'read', 'delete' grant, a 'write' grant (as a member of group1),
        // and a 'write' grant (as a member of dynamic group 'employees')
        // self::CURRENT_USER_IDENTIFIER.'_6' has a 'delete' grant, and a 'delete' grant (as a member of group2)
        // self::CURRENT_USER_IDENTIFIER.'_7' has a 'read', 'delete', a 'write' grant (as a member of group1),
        // a 'delete' grant (as a member of group2), a 'write' grant (as a member of dynamic group 'employees')

        $resource = $this->testEntityManager->addAuthorizationResource();

        $group1 = $this->testEntityManager->addGroup();
        $this->testEntityManager->addGroupMember($group1, self::CURRENT_USER_IDENTIFIER.'_3');
        $this->testEntityManager->addGroupMember($group1, self::CURRENT_USER_IDENTIFIER.'_5');
        $this->testEntityManager->addGroupMember($group1, self::CURRENT_USER_IDENTIFIER.'_7');

        $group2 = $this->testEntityManager->addGroup();
        $this->testEntityManager->addGroupMember($group2, self::CURRENT_USER_IDENTIFIER.'_6');
        $this->testEntityManager->addGroupMember($group2, self::CURRENT_USER_IDENTIFIER.'_7');

        $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource, 'read', self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceActionGrant($resource, 'read', self::CURRENT_USER_IDENTIFIER.'_5');
        $this->testEntityManager->addResourceActionGrant($resource, 'delete', self::CURRENT_USER_IDENTIFIER.'_5');
        $this->testEntityManager->addResourceActionGrant($resource, 'delete', self::CURRENT_USER_IDENTIFIER.'_6');
        $this->testEntityManager->addResourceActionGrant($resource, 'read', self::CURRENT_USER_IDENTIFIER.'_7');
        $this->testEntityManager->addResourceActionGrant($resource, 'delete', self::CURRENT_USER_IDENTIFIER.'_7');

        $this->testEntityManager->addResourceActionGrant($resource, 'write', null, $group1);
        $this->testEntityManager->addResourceActionGrant($resource, 'delete', null, $group2);
        $this->testEntityManager->addResourceActionGrant($resource, 'write', null, null, 'employees');

        // add some noise:
        $resource2 = $this->testEntityManager->addAuthorizationResource();
        $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, 'somebody_else');

        // ----------------------------------------------------------------
        // current user:
        $usersResourceActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER,
            [AuthorizationService::MANAGE_ACTION]);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $usersResourceActions->getActions());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $usersResourceActions->getResourceIdentifier());

        $usersResourceActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $usersResourceActions->getActions());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $usersResourceActions->getResourceIdentifier());

        // ----------------------------------------------------------------
        // user 2:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $usersResourceActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, ['read']);
        $this->assertEquals(['read'], $usersResourceActions->getActions());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $usersResourceActions->getResourceIdentifier());

        $usersResourceActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $this->assertEquals(['read'], $usersResourceActions->getActions());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $usersResourceActions->getResourceIdentifier());

        // ----------------------------------------------------------------
        // user 3:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');
        $usersResourceActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, ['write']);
        $this->assertEquals(['write'], $usersResourceActions->getActions());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $usersResourceActions->getResourceIdentifier());

        $usersResourceActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $this->assertEquals(['write'], $usersResourceActions->getActions());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $usersResourceActions->getResourceIdentifier());

        // ----------------------------------------------------------------
        // user 4:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_4', $userAttributes);
        $usersResourceActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, ['write']);
        $this->assertEquals(['write'], $usersResourceActions->getActions());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $usersResourceActions->getResourceIdentifier());

        // ----------------------------------------------------------------
        // user 5:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_5', $userAttributes);
        $usersResourceActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $this->assertCount(3, $usersResourceActions->getActions());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $usersResourceActions->getResourceIdentifier());
        $this->assertContains('read', $usersResourceActions->getActions());
        $this->assertContains('delete', $usersResourceActions->getActions());
        $this->assertContains('write', $usersResourceActions->getActions());

        // ----------------------------------------------------------------
        // user 6:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_6');
        $usersResourceActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $this->assertEquals(['delete'], $usersResourceActions->getActions());

        // ----------------------------------------------------------------
        // user 7:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_7', $userAttributes);
        $usersResourceActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $this->assertCount(3, $usersResourceActions->getActions());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $usersResourceActions->getResourceIdentifier());
        $this->assertContains('read', $usersResourceActions->getActions());
        $this->assertContains('delete', $usersResourceActions->getActions());
        $this->assertContains('write', $usersResourceActions->getActions());

        // ----------------------------------------------------------------
        // user 8:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_8');
        $usersResourceActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $this->assertNull($usersResourceActions);
    }

    public function testGetResourceItemActionsPageForCurrentUser(): void
    {
        $testGroup = $this->testEntityManager->addGroup();
        $this->testEntityManager->addGroupMember($testGroup, self::CURRENT_USER_IDENTIFIER.'_3');

        // managed by user
        // readable by user 2
        // readable by user 3
        // readable by user 4
        $resource = $this->testEntityManager->addAuthorizationResource();
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

        // add some noise:
        $resource_foo = $this->testEntityManager->addAuthorizationResource(TestEntityManager::DEFAULT_RESOURCE_CLASS,
            TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_foo');
        $this->testEntityManager->addResourceActionGrant($resource_foo,
            AuthorizationService::MANAGE_ACTION, 'foo');
        $this->testEntityManager->addResourceActionGrant($resource_foo,
            'read', null, null, 'bar');

        // ----------------------------------------------------------------
        // user:
        // manage action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS,
            [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $usersResourceActions);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $usersResourceActions[0]->getActions());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $usersResourceActions[0]->getResourceIdentifier());

        // any action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertCount(2, $usersResourceActions);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $usersResourceActions[0]->getActions());
        $this->assertEquals(['read'], $usersResourceActions[1]->getActions());

        // ----------------------------------------------------------------
        // user 2:
        // manage action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS,
            [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $usersResourceActions);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $usersResourceActions[0]->getActions());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_2', $usersResourceActions[0]->getResourceIdentifier());

        // any action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_2';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === ['read']
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }));

        // ----------------------------------------------------------------
        // user 3:
        // manage action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS,
            [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_3';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_4';
        }));

        // any action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertCount(4, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_3';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_4';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === ['read']
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === ['write']
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_2';
        }));

        // test pagination (page size 3):
        $userResourceActionPage1 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, 0, 3);
        $this->assertCount(3, $userResourceActionPage1);
        $userResourceActionPage2 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, 3, 3);
        $this->assertCount(1, $userResourceActionPage2);

        $usersResourceActions = array_merge($userResourceActionPage1, $userResourceActionPage2);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_3';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_4';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === ['read']
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === ['write']
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_2';
        }));

        // ----------------------------------------------------------------
        // user 4:
        // manage action
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_4', $userAttributes);
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS,
            [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $usersResourceActions);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $usersResourceActions[0]->getActions());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_5', $usersResourceActions[0]->getResourceIdentifier());

        // any action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertCount(4, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_5';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === ['read']
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_4';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === ['read']
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === ['write']
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_3';
        }));

        // test pagination (page size 2):
        $userResourceActionPage1 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, 0, 2);
        $this->assertCount(2, $userResourceActionPage1);
        $userResourceActionPage2 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, 2, 2);
        $this->assertCount(2, $userResourceActionPage2);
        $userResourceActionPage3 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, 4, 2);
        $this->assertCount(0, $userResourceActionPage3);

        $usersResourceActions = array_merge($userResourceActionPage1, $userResourceActionPage2);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_5';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === ['read']
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_4';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === ['read']
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === ['write']
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_3';
        }));

        // ----------------------------------------------------------------
        // user 5 (student):
        // manage action
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_5', $userAttributes);
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(0, $usersResourceActions);

        // read action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, ['read']);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceAction) {
            return $resourceAction->getActions() === ['read']
                && $resourceAction->getResourceIdentifier() === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_5';
        }));

        // test pagination (page size 1):
        $userResourceActionPage1 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, 0, 1);
        $this->assertCount(1, $userResourceActionPage1);

        // test pagination (page size 0):
        $userResourceActionPage1 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, 0, 0);
        $this->assertCount(0, $userResourceActionPage1);

        // ----------------------------------------------------------------
        // user 6:
        // any action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_6');
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertCount(0, $usersResourceActions);
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

        $resourceCollection1 = $this->testEntityManager->addAuthorizationResource(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $resourceCollection2 = $this->testEntityManager->addAuthorizationResource(
            TestEntityManager::DEFAULT_RESOURCE_CLASS.'_2', null);

        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $this->assertNull($resourceCollectionActions);

        $this->testEntityManager->addResourceActionGrant($resourceCollection1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resourceCollection1,
            'read', self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceActionGrant($resourceCollection1,
            'read', self::CURRENT_USER_IDENTIFIER.'_3');
        $this->testEntityManager->addResourceActionGrant($resourceCollection1,
            'read', self::CURRENT_USER_IDENTIFIER.'_5');
        $this->testEntityManager->addResourceActionGrant($resourceCollection1,
            'delete', self::CURRENT_USER_IDENTIFIER.'_5');
        $this->testEntityManager->addResourceActionGrant($resourceCollection1,
            'write', null, $testGroup);
        $this->testEntityManager->addResourceActionGrant($resourceCollection1,
            'delete', null, null, 'employees');

        $this->testEntityManager->addResourceActionGrant($resourceCollection2,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        // ----------------------------------------------------------------
        // current user:
        // manage action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, [AuthorizationService::MANAGE_ACTION]);
        $this->assertEquals(null, $resourceCollectionActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceCollectionActions->getActions());

        // any action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $this->assertEquals(null, $resourceCollectionActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceCollectionActions->getActions());

        // any action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS.'_2', null);
        $this->assertEquals(null, $resourceCollectionActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceCollectionActions->getActions());

        // ----------------------------------------------------------------
        // user 2:
        // manage action:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, [AuthorizationService::MANAGE_ACTION]);
        $this->assertNull($resourceCollectionActions);

        // read action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, ['read']);
        $this->assertEquals(null, $resourceCollectionActions->getResourceIdentifier());
        $this->assertEquals(['read'], $resourceCollectionActions->getActions());

        // any action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $this->assertEquals(null, $resourceCollectionActions->getResourceIdentifier());
        $this->assertEquals(['read'], $resourceCollectionActions->getActions());

        // ----------------------------------------------------------------
        // user 3:
        // manage action:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, [AuthorizationService::MANAGE_ACTION]);
        $this->assertNull($resourceCollectionActions);

        // read action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, ['read']);
        $this->assertEquals(null, $resourceCollectionActions->getResourceIdentifier());
        $this->assertEquals(['read'], $resourceCollectionActions->getActions());

        // any action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $this->assertCount(2, $resourceCollectionActions->getActions());
        $this->assertContains('read', $resourceCollectionActions->getActions());
        $this->assertContains('write', $resourceCollectionActions->getActions());
        $this->assertEquals(null, $resourceCollectionActions->getResourceIdentifier());

        // ----------------------------------------------------------------
        // user 4:
        // manage action:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_4', $userAttributes);
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, [AuthorizationService::MANAGE_ACTION]);
        $this->assertNull($resourceCollectionActions);

        // delete action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, ['delete']);
        $this->assertEquals(null, $resourceCollectionActions->getResourceIdentifier());
        $this->assertEquals(['delete'], $resourceCollectionActions->getActions());

        // any action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $this->assertEquals(null, $resourceCollectionActions->getResourceIdentifier());
        $this->assertEquals(['delete'], $resourceCollectionActions->getActions());

        // ----------------------------------------------------------------
        // user 5:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_5', $userAttributes);

        // delete action: user 5 has one personal grant and one grant via dynamic group 'employees'
        // -> expecting only 1 grant, since only unique resource actions should be returned
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, ['delete']);
        $this->assertEquals(null, $resourceCollectionActions->getResourceIdentifier());
        $this->assertEquals(['delete'], $resourceCollectionActions->getActions());

        // any action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $this->assertCount(3, $resourceCollectionActions->getActions());
        $this->assertContains('read', $resourceCollectionActions->getActions());
        $this->assertContains('write', $resourceCollectionActions->getActions());
        $this->assertContains('delete', $resourceCollectionActions->getActions());
        $this->assertEquals(null, $resourceCollectionActions->getResourceIdentifier());
    }

    public function testGetDynamicGroupsCurrentUserIsAuthorizedToRead(): void
    {
        $dynamicGroups = $this->authorizationService->getDynamicGroupsCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $dynamicGroups);
        $this->assertContains('students', $dynamicGroups);
        $this->assertContains('employees', $dynamicGroups);
    }

    public function testGetResourceClassesCurrentUserIsAuthorizedToRead(): void
    {
        $group1 = $this->testEntityManager->addGroup();
        $group2 = $this->testEntityManager->addGroup();

        $this->testEntityManager->addGroupMember($group1, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addGroupMember($group2, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addGroupMember($group2, self::ANOTHER_USER_IDENTIFIER.'_2');

        $resource1 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $resource2 = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier2');
        $resource3 = $this->testEntityManager->addAuthorizationResource('resourceClass_2', 'resourceIdentifier');
        $resource4 = $this->testEntityManager->addAuthorizationResource('resourceClass_2', 'resourceIdentifier3');
        $resourceCollection = $this->testEntityManager->addAuthorizationResource('resourceClass_3', null);

        $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, null, $group2);
        $this->testEntityManager->addResourceActionGrant($resource2,
            'write', null, null, 'students');
        $this->testEntityManager->addResourceActionGrant($resource3,
            AuthorizationService::MANAGE_ACTION, null, null, 'employees');
        $this->testEntityManager->addResourceActionGrant($resource3,
            'delete', null, $group1);
        $this->testEntityManager->addResourceActionGrant($resource4,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            AuthorizationService::MANAGE_ACTION, null, $group1);
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            'create', self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            'create', null, null, 'students');

        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(3, $resourceClasses);
        $this->assertContains('resourceClass', $resourceClasses);
        $this->assertContains('resourceClass_2', $resourceClasses);
        $this->assertContains('resourceClass_3', $resourceClasses);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $resourceClasses);
        $this->assertContains('resourceClass', $resourceClasses);
        $this->assertContains('resourceClass_2', $resourceClasses);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2', $userAttributes);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $resourceClasses);
        $this->assertContains('resourceClass', $resourceClasses);
        $this->assertContains('resourceClass_2', $resourceClasses);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_3', $userAttributes);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $resourceClasses);
        $this->assertContains('resourceClass', $resourceClasses);
        $this->assertContains('resourceClass_3', $resourceClasses);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_4', $userAttributes);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(3, $resourceClasses);
        $this->assertContains('resourceClass', $resourceClasses);
        $this->assertContains('resourceClass_2', $resourceClasses);
        $this->assertContains('resourceClass_3', $resourceClasses);

        $this->login(self::ANOTHER_USER_IDENTIFIER.'_foo');
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(0, $resourceClasses);
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
