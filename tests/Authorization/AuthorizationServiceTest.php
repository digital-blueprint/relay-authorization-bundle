<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Authorization;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractAuthorizationServiceTestCase;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;

class AuthorizationServiceTest extends AbstractAuthorizationServiceTestCase
{
    private ?array $testConfig = null;

    protected function setUp(): void
    {
        if ($this->testConfig === null) {
            $this->testConfig = [];
            $this->testConfig[Configuration::RESOURCE_CLASSES] = [
                [
                    Configuration::IDENTIFIER => self::TEST_RESOURCE_CLASS,
                    Configuration::MANAGE_RESOURCE_COLLECTION_POLICY => 'user.get("MAY_CREATE_TEST_RESOURCES")',
                ],
            ];
            $this->testConfig[Configuration::DYNAMIC_GROUPS] = [
                [
                    Configuration::IDENTIFIER => 'students',
                    Configuration::IS_CURRENT_USER_GROUP_MEMBER_EXPRESSION => 'user.get("IS_STUDENT")',
                ],
                [
                    Configuration::IDENTIFIER => 'employees',
                    Configuration::IS_CURRENT_USER_GROUP_MEMBER_EXPRESSION => 'user.get("IS_EMPLOYEE")',
                ],
            ];
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->testConfig = null;
    }

    public function testIsCurrentUserAuthorizedToReadResource(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource('resourceClass', 'resourceIdentifier');
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToReadResource($resource));

        $this->authorizationService->clearRequestCache();

        $resourceActionGrant = $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->assertNotNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier()));

        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToReadResource($resource));
    }

    public function testManageResourceCollectionPolicy(): void
    {
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertEmpty($resourceCollectionActions);

        $attributes = $this->getDefaultUserAttributes();
        $attributes['MAY_CREATE_TEST_RESOURCES'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $attributes);
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceCollectionActions);

        $collectionResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, null);
        $this->testEntityManager->addResourceActionGrant($collectionResource,
            'read', self::CURRENT_USER_IDENTIFIER);

        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertIsPermutationOf([AuthorizationService::MANAGE_ACTION, 'read'], $resourceCollectionActions);
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
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER,
            [AuthorizationService::MANAGE_ACTION]);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceItemActions);

        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 2:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, ['read']);
        $this->assertEquals(['read'], $resourceItemActions);

        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $this->assertEquals(['read'], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 3:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, ['write']);
        $this->assertEquals(['write'], $resourceItemActions);

        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $this->assertEquals(['write'], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 4:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_4', $userAttributes);
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, ['write']);
        $this->assertEquals(['write'], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 5:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_5', $userAttributes);
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $this->assertCount(3, $resourceItemActions);
        $this->assertContains('read', $resourceItemActions);
        $this->assertContains('delete', $resourceItemActions);
        $this->assertContains('write', $resourceItemActions);

        // ----------------------------------------------------------------
        // user 6:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_6');
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $this->assertEquals(['delete'], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 7:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_7', $userAttributes);
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $this->assertCount(3, $resourceItemActions);
        $this->assertContains('read', $resourceItemActions);
        $this->assertContains('delete', $resourceItemActions);
        $this->assertContains('write', $resourceItemActions);

        // ----------------------------------------------------------------
        // user 8:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_8');
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        $this->assertEmpty($resourceItemActions);
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
            TestEntityManager::DEFAULT_RESOURCE_CLASS, [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) use ($resource) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === $resource->getResourceIdentifier();
        }, true));

        // any action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) use ($resource) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === $resource->getResourceIdentifier();
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) use ($resource_2) {
            return $resourceActions === ['read']
                && $resourceIdentifier === $resource_2->getResourceIdentifier();
        }, true));

        // ----------------------------------------------------------------
        // user 2:
        // manage action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS,
            [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_2';
        }, true));

        // any action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_2';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }, true));

        // ----------------------------------------------------------------
        // user 3:
        // manage action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS,
            [AuthorizationService::MANAGE_ACTION]);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_3';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_4';
        }, true));

        // any action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertCount(4, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_3';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_4';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['write']
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_2';
        }, true));

        // test pagination (page size 3):
        $userResourceActionPage1 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, 0, 3);
        $this->assertCount(3, $userResourceActionPage1);
        $userResourceActionPage2 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, 3, 3);
        $this->assertCount(1, $userResourceActionPage2);

        $usersResourceActions = array_merge($userResourceActionPage1, $userResourceActionPage2);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_3';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_4';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['write']
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_2';
        }, true));

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
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_5';
        }, true));

        // any action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertCount(4, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_5';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_4';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['write']
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_3';
        }, true));

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
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_5';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_4';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['write']
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_3';
        }, true));

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
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER.'_5';
        }, true));

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
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertEmpty($resourceCollectionActions);

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
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceCollectionActions);

        // any action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceCollectionActions);

        // any action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS.'_2');
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceCollectionActions);

        // ----------------------------------------------------------------
        // user 2:
        // manage action:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, [AuthorizationService::MANAGE_ACTION]);
        $this->assertEmpty($resourceCollectionActions);

        // read action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, ['read']);
        $this->assertEquals(['read'], $resourceCollectionActions);

        // any action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertEquals(['read'], $resourceCollectionActions);

        // ----------------------------------------------------------------
        // user 3:
        // manage action:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, [AuthorizationService::MANAGE_ACTION]);
        $this->assertEmpty($resourceCollectionActions);

        // read action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, ['read']);
        $this->assertCount(2, $resourceCollectionActions);
        $this->assertContains('read', $resourceCollectionActions);
        $this->assertContains('write', $resourceCollectionActions);

        // any action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertCount(2, $resourceCollectionActions);
        $this->assertContains('read', $resourceCollectionActions);
        $this->assertContains('write', $resourceCollectionActions);

        // ----------------------------------------------------------------
        // user 4:
        // manage action:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_4', $userAttributes);
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, [AuthorizationService::MANAGE_ACTION]);
        $this->assertEmpty($resourceCollectionActions);

        // delete action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, ['delete']);
        $this->assertEquals(['delete'], $resourceCollectionActions);

        // any action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS);
        $this->assertEquals(['delete'], $resourceCollectionActions);

        // ----------------------------------------------------------------
        // user 5:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_5', $userAttributes);

        // delete action: user 5 has one personal grant and one grant via dynamic group 'employees'
        // -> expecting only 1 grant, since only unique resource actions should be returned
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, ['delete']);
        $this->assertContains('read', $resourceCollectionActions);
        $this->assertContains('write', $resourceCollectionActions);
        $this->assertContains('delete', $resourceCollectionActions);

        // any action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null);
        $this->assertCount(3, $resourceCollectionActions);
        $this->assertContains('read', $resourceCollectionActions);
        $this->assertContains('write', $resourceCollectionActions);
        $this->assertContains('delete', $resourceCollectionActions);
    }

    public function testGetGroupCollectionActionGrants(): void
    {
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS);
        $this->assertEmpty($resourceCollectionActions);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['MAY_CREATE_GROUPS'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $userAttributes);
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceCollectionActions);

        $groupCollectionResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            AuthorizationService::GROUP_RESOURCE_CLASS, null);

        $this->testEntityManager->addResourceActionGrant($groupCollectionResource,
            'create', self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($groupCollectionResource,
            'read', self::ANOTHER_USER_IDENTIFIER);

        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS);
        $this->assertIsPermutationOf([AuthorizationService::MANAGE_ACTION, 'create'], $resourceCollectionActions);

        // ----------------------------------------------------------------
        // another user:
        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS);
        $this->assertEquals(['read'], $resourceCollectionActions);

        // ----------------------------------------------------------------
        // user 3:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS);
        $this->assertEmpty($resourceCollectionActions);
    }

    public function testGetDynamicGroupsCurrentUserIsAuthorizedToRead(): void
    {
        $dynamicGroups = $this->authorizationService->getDynamicGroupsCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $dynamicGroups);
        $this->assertContains('students', $dynamicGroups);
        $this->assertContains('employees', $dynamicGroups);
    }

    public function testGetAuthorizationResourcesCurrentUserIsAuthorizedToRead(): void
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

        $isWritable = function ($authorizationResource) {
            return $authorizationResource->getWritable();
        };
        $isNotWritable = function ($authorizationResource) {
            return $authorizationResource->getWritable() === false;
        };

        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead();
        $this->assertCount(3, $authorizationResources);
        $this->assertContainsResourceWhere($resource1, $authorizationResources, $isWritable);
        $this->assertContainsResourceWhere($resource3, $authorizationResources, $isNotWritable);
        $this->assertContainsResourceWhere($resourceCollection, $authorizationResources, $isWritable);

        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead('resourceClass');
        $this->assertCount(1, $authorizationResources);
        $this->assertContainsResourceWhere($resource1, $authorizationResources, $isWritable);

        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead('resourceClass_2');
        $this->assertCount(1, $authorizationResources);
        $this->assertContainsResourceWhere($resource3, $authorizationResources, $isNotWritable);

        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead('resourceClass_3');
        $this->assertCount(1, $authorizationResources);
        $this->assertContainsResourceWhere($resourceCollection, $authorizationResources, $isWritable);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $authorizationResources);
        $this->assertContainsResourceWhere($resource2, $authorizationResources, $isWritable);
        $this->assertContainsResourceWhere($resource4, $authorizationResources, $isWritable);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2', $userAttributes);
        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $authorizationResources);
        $this->assertContainsResourceWhere($resource2, $authorizationResources, $isWritable);
        $this->assertContainsResourceWhere($resource3, $authorizationResources, $isWritable);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_3', $userAttributes);
        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $authorizationResources);
        $this->assertContainsResourceWhere($resource2, $authorizationResources, $isNotWritable);
        $this->assertContainsResourceWhere($resourceCollection, $authorizationResources, $isNotWritable);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_4', $userAttributes);
        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead();
        $this->assertCount(3, $authorizationResources);
        $this->assertContainsResourceWhere($resource2, $authorizationResources, $isNotWritable);
        $this->assertContainsResourceWhere($resource3, $authorizationResources, $isWritable);
        $this->assertContainsResourceWhere($resourceCollection, $authorizationResources, $isNotWritable);

        // ----------------------------------------------------------------
        // test pagination:
        $authorizationResourcePage1 = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead(
            null, 0, 2);
        $this->assertCount(2, $authorizationResourcePage1);
        $authorizationResourcePage2 = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead(
            null, 2, 2);
        $this->assertCount(1, $authorizationResourcePage2);

        $authorizationResources = array_merge($authorizationResourcePage1, $authorizationResourcePage2);
        $this->assertContainsResourceWhere($resource2, $authorizationResources, $isNotWritable);
        $this->assertContainsResourceWhere($resource3, $authorizationResources, $isWritable);
        $this->assertContainsResourceWhere($resourceCollection, $authorizationResources, $isNotWritable);
        // ----------------------------------------------------------------

        $this->login(self::ANOTHER_USER_IDENTIFIER.'_foo');
        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead();
        $this->assertCount(0, $authorizationResources);
    }

    public function testGetResourceActionGrantsUserIsAuthorizedToRead(): void
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
        $resourceCollection = $this->testEntityManager->addAuthorizationResource('resourceClass_2', null);

        $r1ManageCU = $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $r1ReadAU3 = $this->testEntityManager->addResourceActionGrant($resource1,
            'read', self::ANOTHER_USER_IDENTIFIER.'_3');
        $r2ManageG2 = $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, null, $group2);
        $r2WriteStudents = $this->testEntityManager->addResourceActionGrant($resource2,
            'write', null, null, 'students');
        $r3ManageEmployees = $this->testEntityManager->addResourceActionGrant($resource3,
            AuthorizationService::MANAGE_ACTION, null, null, 'employees');
        $r3DeleteG1 = $this->testEntityManager->addResourceActionGrant($resource3,
            'delete', null, $group1);
        $r4ManageAU = $this->testEntityManager->addResourceActionGrant($resource4,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $r4UpdateG2 = $this->testEntityManager->addResourceActionGrant($resource4,
            'update', null, $group2);
        $rcManageG1 = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            AuthorizationService::MANAGE_ACTION, null, $group1);
        $rcCreateCU = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            'create', self::CURRENT_USER_IDENTIFIER);
        $rcCreateStudents = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            'create', null, null, 'students');

        // -------------------------------------------------------------------------------------------
        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead();
        $this->assertCount(6, $resourceActionsGrants);
        $this->assertContainsResource($r1ManageCU, $resourceActionsGrants);
        $this->assertContainsResource($r1ReadAU3, $resourceActionsGrants);
        $this->assertContainsResource($r3DeleteG1, $resourceActionsGrants);
        $this->assertContainsResource($rcManageG1, $resourceActionsGrants);
        $this->assertContainsResource($rcCreateCU, $resourceActionsGrants);
        $this->assertContainsResource($rcCreateStudents, $resourceActionsGrants);

        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead('resourceClass');
        $this->assertCount(2, $resourceActionsGrants);
        $this->assertContainsResource($r1ManageCU, $resourceActionsGrants);
        $this->assertContainsResource($r1ReadAU3, $resourceActionsGrants);

        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead('resourceClass_2');
        $this->assertCount(4, $resourceActionsGrants);
        $this->assertContainsResource($r3DeleteG1, $resourceActionsGrants);
        $this->assertContainsResource($rcManageG1, $resourceActionsGrants);
        $this->assertContainsResource($rcCreateCU, $resourceActionsGrants);
        $this->assertContainsResource($rcCreateStudents, $resourceActionsGrants);

        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(
            'resourceClass', 'resourceIdentifier');
        $this->assertCount(2, $resourceActionsGrants);
        $this->assertContainsResource($r1ManageCU, $resourceActionsGrants);
        $this->assertContainsResource($r1ReadAU3, $resourceActionsGrants);

        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(
            'resourceClass_2', 'resourceIdentifier');
        $this->assertCount(1, $resourceActionsGrants);
        $this->assertContainsResource($r3DeleteG1, $resourceActionsGrants);

        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(
            'resourceClass_2', AuthorizationService::IS_NULL);
        $this->assertCount(3, $resourceActionsGrants);
        $this->assertContainsResource($rcManageG1, $resourceActionsGrants);
        $this->assertContainsResource($rcCreateCU, $resourceActionsGrants);
        $this->assertContainsResource($rcCreateStudents, $resourceActionsGrants);

        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(
            'resourceClass', 'resourceIdentifier2');
        $this->assertCount(0, $resourceActionsGrants);

        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(
            'resourceClass_foo');
        $this->assertCount(0, $resourceActionsGrants);

        // -------------------------------------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead();
        $this->assertCount(4, $resourceActionsGrants);
        $this->assertContainsResource($r2ManageG2, $resourceActionsGrants);
        $this->assertContainsResource($r2WriteStudents, $resourceActionsGrants);
        $this->assertContainsResource($r4ManageAU, $resourceActionsGrants);
        $this->assertContainsResource($r4UpdateG2, $resourceActionsGrants);

        // -------------------------------------------------------------------------------------------
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2', $userAttributes);
        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead();
        $this->assertCount(5, $resourceActionsGrants);
        $this->assertContainsResource($r2ManageG2, $resourceActionsGrants);
        $this->assertContainsResource($r2WriteStudents, $resourceActionsGrants);
        $this->assertContainsResource($r3ManageEmployees, $resourceActionsGrants);
        $this->assertContainsResource($r3DeleteG1, $resourceActionsGrants);
        $this->assertContainsResource($r4UpdateG2, $resourceActionsGrants);

        // -------------------------------------------------------------------------------------------
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_3', $userAttributes);
        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead();
        $this->assertCount(3, $resourceActionsGrants);
        $this->assertContainsResource($r1ReadAU3, $resourceActionsGrants);
        $this->assertContainsResource($r2WriteStudents, $resourceActionsGrants);
        $this->assertContainsResource($rcCreateStudents, $resourceActionsGrants);

        // -------------------------------------------------------------------------------------------
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_4', $userAttributes);
        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead();
        $this->assertCount(4, $resourceActionsGrants);
        $this->assertContainsResource($r2WriteStudents, $resourceActionsGrants);
        $this->assertContainsResource($rcCreateStudents, $resourceActionsGrants);
        $this->assertContainsResource($r3ManageEmployees, $resourceActionsGrants);
        $this->assertContainsResource($r3DeleteG1, $resourceActionsGrants);

        // -------------------------------------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_foo');
        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead();
        $this->assertCount(0, $resourceActionsGrants);
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

    public function testUpdateManageResourceCollectionPolicyGrantsA(): void
    {
        $this->assertNotNull($collectionResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, null
        ));

        $this->assertCount(1, $this->testEntityManager->getResourceActionGrants(
            $collectionResource->getIdentifier(), AuthorizationService::MANAGE_ACTION));

        // test path (A): resource class was removed from config, no other grants
        $this->testConfig[Configuration::RESOURCE_CLASSES] = [];
        $this->setUp();

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, null
        ));
        $this->assertCount(0, $this->testEntityManager->getResourceActionGrants(
            $collectionResource->getIdentifier(), AuthorizationService::MANAGE_ACTION));
    }

    public function testUpdateManageResourceCollectionPolicyGrantsB(): void
    {
        // test path (B): resource class was removed from config, other grants exist -> collection resource mustn't be deleted
        $this->assertNotNull($collectionResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, null));

        $resourceActionGrant = $this->testEntityManager->addResourceActionGrant($collectionResource,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->testConfig[Configuration::RESOURCE_CLASSES] = [];
        $this->setUp();

        $this->assertNotNull($this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, null));

        $resourceActionGrants = $this->testEntityManager->getResourceActionGrants(
            $collectionResource->getIdentifier());
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());
    }

    public function testUpdateManageResourceCollectionPolicyGrantsC(): void
    {
        // test path (C): resource class is still in config -> nothing to do
        $this->assertNotNull($collectionResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, null));

        // add some noise
        $this->testEntityManager->addAuthorizationResourceAndActionGrant(self::TEST_RESOURCE_CLASS_2, null,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrants = $this->testEntityManager->getResourceActionGrants(
            $collectionResource->getIdentifier());
        $this->assertCount(1, $resourceActionGrants);
        $resourceActionGrant = $resourceActionGrants[0];

        $this->setUp();

        $this->assertNotNull($this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, null));

        $resourceActionGrants = $this->testEntityManager->getResourceActionGrants(
            $collectionResource->getIdentifier());
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());
    }

    public function testUpdateManageResourceCollectionPolicyGrantsD(): void
    {
        // test path (D) the manage resource collection policy is present in config,
        // the resource collection resource is present in DB, but the policy grant is missing in DB -> auto-add the policy grant to DB
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS_2, null));

        $testResource2CollectionResource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS_2, null);
        $this->assertNotNull($this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS_2, null));

        $this->testEntityManager->addResourceActionGrant($testResource2CollectionResource,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->assertCount(1,
            $this->testEntityManager->getResourceActionGrants($testResource2CollectionResource->getIdentifier()));

        $this->testConfig[Configuration::RESOURCE_CLASSES][] =
            [
                Configuration::IDENTIFIER => self::TEST_RESOURCE_CLASS_2,
                Configuration::MANAGE_RESOURCE_COLLECTION_POLICY => 'user.get("MAY_CREATE_TEST_RESOURCES")',
            ];

        $this->setUp();

        $this->assertNotNull($this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS_2, null));

        $resourceActionGrants = $this->testEntityManager->getResourceActionGrants(
            $testResource2CollectionResource->getIdentifier());
        $this->assertCount(2, $resourceActionGrants);
    }

    public function testUpdateManageResourceCollectionPolicyGrantsX(): void
    {
        // test path (D) the manage resource collection policy is present in config,
        // the resource collection resource is present in DB, but the policy grant is missing in DB -> auto-add the policy grant to DB
        $this->assertNotNull($testResourceCollectionResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, null));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS_2, null));

        $this->assertCount(1,
            $this->testEntityManager->getResourceActionGrants($testResourceCollectionResource->getIdentifier()));

        $this->testConfig[Configuration::RESOURCE_CLASSES] = [
            [
                Configuration::IDENTIFIER => self::TEST_RESOURCE_CLASS_2,
                Configuration::MANAGE_RESOURCE_COLLECTION_POLICY => 'user.get("MAY_CREATE_TEST_RESOURCES")',
            ],
        ];

        $this->setUp();

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, null));
        $this->assertNotNull($testResource2CollectionResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS_2, null));

        $resourceActionGrants = $this->testEntityManager->getResourceActionGrants(
            $testResourceCollectionResource->getIdentifier());
        $this->assertCount(0, $resourceActionGrants);
        $resourceActionGrants = $this->testEntityManager->getResourceActionGrants(
            $testResource2CollectionResource->getIdentifier());
        $this->assertCount(1, $resourceActionGrants);
    }

    public function testUpdateManageResourceCollectionPolicyGrantsE(): void
    {
        // test path (E) the resource collection policy was added to config and the collection resource is not yet present in the DB
        // -> auto-add collection resource and manage collection grant
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS_2, null));

        $this->testConfig[Configuration::RESOURCE_CLASSES][] =
            [
                Configuration::IDENTIFIER => self::TEST_RESOURCE_CLASS_2,
                Configuration::MANAGE_RESOURCE_COLLECTION_POLICY => 'user.get("MAY_CREATE_TEST_RESOURCES")',
            ];
        $this->setUp();

        $this->assertNotNull($testResource2CollectionResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS_2, null));

        $resourceActionGrants = $this->testEntityManager->getResourceActionGrants(
            $testResource2CollectionResource->getIdentifier());
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($testResource2CollectionResource->getIdentifier(), $resourceActionGrants[0]->getAuthorizationResource()->getIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $resourceActionGrants[0]->getAction());
        $this->assertEquals(AuthorizationService::MANAGE_RESOURCE_COLLECTION_POLICY_PREFIX.self::TEST_RESOURCE_CLASS_2, $resourceActionGrants[0]->getDynamicGroupIdentifier());
    }

    public function testUpdateManageResourceCollectionPolicyGrantsF(): void
    {
        // test path (F) the resource collection policy was added to config and a childless collection resource is present in the DB
        // -> auto-add the manage collection grant only
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS_2, null));

        $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS_2, null);

        $this->testConfig[Configuration::RESOURCE_CLASSES][] =
            [
                Configuration::IDENTIFIER => self::TEST_RESOURCE_CLASS_2,
                Configuration::MANAGE_RESOURCE_COLLECTION_POLICY => 'user.get("MAY_CREATE_TEST_RESOURCES")',
            ];
        $this->setUp();

        $this->assertNotNull($testResource2CollectionResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS_2, null));

        $resourceActionGrants = $this->testEntityManager->getResourceActionGrants(
            $testResource2CollectionResource->getIdentifier());
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($testResource2CollectionResource->getIdentifier(), $resourceActionGrants[0]->getAuthorizationResource()->getIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $resourceActionGrants[0]->getAction());
        $this->assertEquals(AuthorizationService::MANAGE_RESOURCE_COLLECTION_POLICY_PREFIX.self::TEST_RESOURCE_CLASS_2,
            $resourceActionGrants[0]->getDynamicGroupIdentifier());
    }

    protected function getTestConfig(): array
    {
        return array_merge(parent::getTestConfig(), $this->testConfig);
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
