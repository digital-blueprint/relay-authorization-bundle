<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Authorization;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Entity\GrantedActions;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Service\UserAttributeProvider;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractAuthorizationServiceTestCase;
use Dbp\Relay\AuthorizationBundle\Tests\EventSubscriber\TestGetAvailableResourceClassActionsEventSubscriber;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Component\HttpFoundation\Response;

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
                    Configuration::MANAGE_RESOURCE_COLLECTION_POLICY => 'user.get("MAY_MANAGE_TEST_RESOURCE_COLLECTION")',
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

    public function testRegisterResourceWithReservedCharacterError(): void
    {
        try {
            $this->authorizationService->addResourceActionGrant(
                'foo'.UserAttributeProvider::SEPARATOR.'bar', self::TEST_RESOURCE_IDENTIFIER,
                AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
            $this->fail('Expected ApiError to be thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
        }

        try {
            $this->authorizationService->addResourceActionGrant(
                self::TEST_RESOURCE_CLASS, 'foo'.UserAttributeProvider::SEPARATOR.'bar',
                AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
            $this->fail('Expected ApiError to be thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
        }

        try {
            $this->authorizationService->addResourceActionGrant(
                'foo'.GrantedActions::ID_SEPARATOR.'bar', self::TEST_RESOURCE_IDENTIFIER,
                AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
            $this->fail('Expected ApiError to be thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
        }

        try {
            $this->authorizationService->addResourceActionGrant(
                self::TEST_RESOURCE_CLASS, 'foo'.GrantedActions::ID_SEPARATOR.'bar',
                AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
            $this->fail('Expected ApiError to be thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
        }
    }

    public function testIsCurrentUserAuthorizedToReadResource(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
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
        $attributes['MAY_MANAGE_TEST_RESOURCE_COLLECTION'] = true;
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
        $this->assertTrue($this->authorizationService->isCurrentUserMemberOfDynamicGroup('everybody'));
        $this->assertFalse($this->authorizationService->isCurrentUserMemberOfDynamicGroup('students'));
        $this->assertFalse($this->authorizationService->isCurrentUserMemberOfDynamicGroup('employees'));

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $userAttributes);

        $this->assertTrue($this->authorizationService->isCurrentUserMemberOfDynamicGroup('everybody'));
        $this->assertTrue($this->authorizationService->isCurrentUserMemberOfDynamicGroup('students'));
        $this->assertFalse($this->authorizationService->isCurrentUserMemberOfDynamicGroup('employees'));

        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $userAttributes);

        $this->assertTrue($this->authorizationService->isCurrentUserMemberOfDynamicGroup('everybody'));
        $this->assertTrue($this->authorizationService->isCurrentUserMemberOfDynamicGroup('students'));
        $this->assertTrue($this->authorizationService->isCurrentUserMemberOfDynamicGroup('employees'));
    }

    public function testGetDynamicGroupsCurrentUserIsMemberOf(): void
    {
        // create some noise by letting manage_resource_collection_policy of self::TEST_RESOURCE_CLASS evaluate to true
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['MAY_MANAGE_TEST_RESOURCE_COLLECTION'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $userAttributes);

        $currentUsersDynamicGroups = $this->authorizationService->getDynamicGroupsCurrentUserIsMemberOf();
        $this->assertCount(1, $currentUsersDynamicGroups);
        $this->assertEquals('everybody', $currentUsersDynamicGroups[0]);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $userAttributes);

        $currentUsersDynamicGroups = $this->authorizationService->getDynamicGroupsCurrentUserIsMemberOf();
        $this->assertCount(2, $currentUsersDynamicGroups);
        $this->assertContains('students', $currentUsersDynamicGroups);
        $this->assertContains('everybody', $currentUsersDynamicGroups);

        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $userAttributes);

        $currentUsersDynamicGroups = $this->authorizationService->getDynamicGroupsCurrentUserIsMemberOf();
        $this->assertCount(3, $currentUsersDynamicGroups);
        $this->assertContains('students', $currentUsersDynamicGroups);
        $this->assertContains('employees', $currentUsersDynamicGroups);
        $this->assertContains('everybody', $currentUsersDynamicGroups);
    }

    public function testGetResourceItemActionsForCurrentUser(): void
    {
        // everybody has a 'get_title' grant
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
        $this->testEntityManager->addResourceActionGrant($resource, 'get_title', null, null, 'everybody');

        // add some noise:
        $resource2 = $this->testEntityManager->addAuthorizationResource();
        $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, 'somebody_else');

        // ----------------------------------------------------------------
        // current user:
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([AuthorizationService::MANAGE_ACTION, 'get_title'], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 2:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');

        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf(['read', 'get_title'], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 3:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');

        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf(['write', 'get_title'], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 4:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_4', $userAttributes);
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf(['write', 'get_title'], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 5:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_5', $userAttributes);
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf(['read', 'delete', 'write', 'get_title'], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 6:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_6');
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf(['delete', 'get_title'], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 7:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_7', $userAttributes);
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf(['read', 'delete', 'write', 'get_title'], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 8:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_8');
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals(['get_title'], $resourceItemActions);
    }

    public function testGetResourceItemActionsForCurrentUserWithInheritance(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource();
        $collectionResource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_COLLECTION_RESOURCE_CLASS, self::TEST_COLLECTION_RESOURCE_IDENTIFIER);

        $group1 = $this->testEntityManager->addGroup();
        $group2 = $this->testEntityManager->addGroup();
        $this->testEntityManager->addGroupMember($group1, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addGroupMember($group2, self::ANOTHER_USER_IDENTIFIER.'_2');

        $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource,
            TestGetAvailableResourceClassActionsEventSubscriber::UPDATE_ACTION, group: $group1);
        $this->testEntityManager->addResourceActionGrant($resource,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, dynamicGroupIdentifier: 'everybody');

        $this->testEntityManager->addGrantInheritance(
            $collectionResource->getResourceClass(), $collectionResource->getResourceIdentifier(),
            $resource->getResourceClass(), $resource->getResourceIdentifier());

        $this->testEntityManager->addResourceActionGrant($collectionResource,
            TestGetAvailableResourceClassActionsEventSubscriber::DELETE_ACTION, dynamicGroupIdentifier: 'employees');
        $this->testEntityManager->addResourceActionGrant($collectionResource,
            AuthorizationService::MANAGE_ACTION, 'admin');
        $this->testEntityManager->addResourceActionGrant($collectionResource,
            TestGetAvailableResourceClassActionsEventSubscriber::WRITE_ACTION, group: $group2);

        // add some noise:
        $resource2 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER_2);
        $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, 'somebody_else');

        // ----------------------------------------------------------------
        // current user:
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION,
            AuthorizationService::MANAGE_ACTION,
        ], $resourceItemActions);

        // ----------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION,
            TestGetAvailableResourceClassActionsEventSubscriber::UPDATE_ACTION,
        ], $resourceItemActions);

        // ----------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION,
            TestGetAvailableResourceClassActionsEventSubscriber::WRITE_ACTION,
        ], $resourceItemActions);

        // ----------------------------------------------------------------
        $this->login('admin');
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION,
            AuthorizationService::MANAGE_ACTION,
        ], $resourceItemActions);

        // ----------------------------------------------------------------
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login('some_employee', $userAttributes);
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION,
            TestGetAvailableResourceClassActionsEventSubscriber::DELETE_ACTION,
        ], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 5:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login('some_student', $userAttributes);
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION,
        ], $resourceItemActions);

        // ----------------------------------------------------------------
        $this->login('somebody_else');
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION,
        ], $resourceItemActions);

        // ----------------------------------------------------------------
        $this->login('everybody_user');
        $resourceItemActions = $this->authorizationService->getResourceItemActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION,
        ], $resourceItemActions);
    }

    public function testGetResourceCollectionActionsForCurrentUserWithInheritance(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, null);

        $collectionResource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_COLLECTION_RESOURCE_CLASS, self::TEST_COLLECTION_RESOURCE_IDENTIFIER);

        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertEmpty($resourceCollectionActions);

        $this->testEntityManager->addResourceActionGrant($resource,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertEquals([ResourceActionGrantService::MANAGE_ACTION], $resourceCollectionActions);

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertEmpty($resourceCollectionActions);

        $this->testEntityManager->addGrantInheritance(
            $collectionResource->getResourceClass(), $collectionResource->getResourceIdentifier(),
            $resource->getResourceClass(), $resource->getResourceIdentifier());

        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertEmpty($resourceCollectionActions);

        $this->testEntityManager->addResourceActionGrant($collectionResource,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, self::CURRENT_USER_IDENTIFIER.'_2');

        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertEquals([TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION], $resourceCollectionActions);
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
        $resource_2 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceActionGrant($resource_2,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceActionGrant($resource_2, 'read', self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource_2, 'write', null, $testGroup);

        // managed by user 3
        // writable by dynamic group 'employees'
        $resource_3 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_3');
        $this->testEntityManager->addResourceActionGrant($resource_3,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER.'_3');
        $this->testEntityManager->addResourceActionGrant($resource_3, 'write', null, null, 'employees');

        // managed by group 'Testgroup'
        // readable by user 4
        $resource_4 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_4');
        $this->testEntityManager->addResourceActionGrant($resource_4,
            AuthorizationService::MANAGE_ACTION, null, $testGroup);
        $this->testEntityManager->addResourceActionGrant($resource_4,
            'read', self::CURRENT_USER_IDENTIFIER.'_4');

        // managed by dynamic group 'employees'
        // readable by dynamic group 'students'
        $resource_5 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_5');
        $this->testEntityManager->addResourceActionGrant($resource_5,
            AuthorizationService::MANAGE_ACTION, null, null, 'employees');
        $this->testEntityManager->addResourceActionGrant($resource_5,
            'read', null, null, 'students');

        // add some noise:
        $resource_foo = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_foo');
        $this->testEntityManager->addResourceActionGrant($resource_foo,
            AuthorizationService::MANAGE_ACTION, 'foo');
        $this->testEntityManager->addResourceActionGrant($resource_foo,
            'read', null, null, 'bar');

        // ----------------------------------------------------------------
        // user:
        // manage action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) use ($resource) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === $resource->getResourceIdentifier();
        }, true));

        // any action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) use ($resource) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === $resource->getResourceIdentifier();
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) use ($resource_2) {
            return $resourceActions === ['read']
                && $resourceIdentifier === $resource_2->getResourceIdentifier();
        }, true));

        // unavailable action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, 'foo');
        $this->assertCount(0, $usersResourceActions);

        // ----------------------------------------------------------------
        // user 2:
        // manage action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }, true));

        // any action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));

        // ----------------------------------------------------------------
        // user 3:
        // manage action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }, true));

        // any action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(4, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['write']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }, true));

        // test pagination (page size 3):
        $userResourceActionPage1 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, null, 0, 3);
        $this->assertCount(3, $userResourceActionPage1);
        $userResourceActionPage2 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, null, 3, 3);
        $this->assertCount(1, $userResourceActionPage2);

        $usersResourceActions = array_merge($userResourceActionPage1, $userResourceActionPage2);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['write']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }, true));

        // ----------------------------------------------------------------
        // user 4:
        // manage action
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_4', $userAttributes);
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }, true));

        // any action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(4, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['write']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }, true));

        // test pagination (page size 2):
        $userResourceActionPage1 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, null, 0, 2);
        $this->assertCount(2, $userResourceActionPage1);
        $userResourceActionPage2 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, null, 2, 2);
        $this->assertCount(2, $userResourceActionPage2);
        $userResourceActionPage3 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, null, 4, 2);
        $this->assertCount(0, $userResourceActionPage3);

        $usersResourceActions = array_merge($userResourceActionPage1, $userResourceActionPage2);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['write']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }, true));

        // ----------------------------------------------------------------
        // user 5 (student):
        // manage action
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_5', $userAttributes);
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION);
        $this->assertCount(0, $usersResourceActions);

        // read action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, 'read');
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }, true));

        // test pagination (page size 1):
        $userResourceActionPage1 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, null, 0, 1);
        $this->assertCount(1, $userResourceActionPage1);

        // test pagination (page size 0):
        $userResourceActionPage1 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, null, 0, 0);
        $this->assertCount(0, $userResourceActionPage1);

        // ----------------------------------------------------------------
        // user 6:
        // any action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_6');
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(0, $usersResourceActions);
    }

    public function testGetResourceItemActionsPageForCurrentUserWithInheritance(): void
    {
        $testGroup = $this->testEntityManager->addGroup();
        $this->testEntityManager->addGroupMember($testGroup, self::CURRENT_USER_IDENTIFIER.'_3');

        $collectionResource = $this->testEntityManager->addAuthorizationResource(self::TEST_COLLECTION_RESOURCE_CLASS,
            self::TEST_COLLECTION_RESOURCE_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($collectionResource,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, 'controller_user');

        // managed by user
        // readable by user 2
        // readable by user 3
        // readable by user 4
        $resource = $this->testEntityManager->addAuthorizationResource();
        $this->testEntityManager->addGrantInheritance(
            $collectionResource->getResourceClass(), $collectionResource->getResourceIdentifier(),
            $resource->getResourceClass(), $resource->getResourceIdentifier());
        $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource, 'read', self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceActionGrant($resource, 'read', self::CURRENT_USER_IDENTIFIER.'_3');
        $this->testEntityManager->addResourceActionGrant($resource, 'read', self::CURRENT_USER_IDENTIFIER.'_4');

        // managed by user 2
        // readable by user
        // writable by group 'Testgroup'
        $resource_2 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_2');
        $this->testEntityManager->addGrantInheritance(
            $collectionResource->getResourceClass(), $collectionResource->getResourceIdentifier(),
            $resource_2->getResourceClass(), $resource_2->getResourceIdentifier());
        $this->testEntityManager->addResourceActionGrant($resource_2,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceActionGrant($resource_2, 'read', self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource_2, 'write', null, $testGroup);

        // managed by user 3
        // writable by dynamic group 'employees'
        $resource_3 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_3');
        $this->testEntityManager->addGrantInheritance(
            $collectionResource->getResourceClass(), $collectionResource->getResourceIdentifier(),
            $resource_3->getResourceClass(), $resource_3->getResourceIdentifier());
        $this->testEntityManager->addResourceActionGrant($resource_3,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER.'_3');
        $this->testEntityManager->addResourceActionGrant($resource_3, 'write', null, null, 'employees');

        // managed by group 'Testgroup'
        // readable by user 4
        $resource_4 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_4');
        $this->testEntityManager->addGrantInheritance(
            $collectionResource->getResourceClass(), $collectionResource->getResourceIdentifier(),
            $resource_4->getResourceClass(), $resource_4->getResourceIdentifier());
        $this->testEntityManager->addResourceActionGrant($resource_4,
            AuthorizationService::MANAGE_ACTION, null, $testGroup);
        $this->testEntityManager->addResourceActionGrant($resource_4,
            'read', self::CURRENT_USER_IDENTIFIER.'_4');

        // managed by dynamic group 'employees'
        // readable by dynamic group 'students'
        $resource_5 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_5');
        $this->testEntityManager->addGrantInheritance(
            $collectionResource->getResourceClass(), $collectionResource->getResourceIdentifier(),
            $resource_5->getResourceClass(), $resource_5->getResourceIdentifier());
        $this->testEntityManager->addResourceActionGrant($resource_5,
            AuthorizationService::MANAGE_ACTION, null, null, 'employees');
        $this->testEntityManager->addResourceActionGrant($resource_5,
            'read', null, null, 'students');

        // add some noise:
        $resource_foo = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_foo');
        $this->testEntityManager->addResourceActionGrant($resource_foo,
            AuthorizationService::MANAGE_ACTION, 'foo');
        $this->testEntityManager->addResourceActionGrant($resource_foo,
            'read', null, null, 'bar');

        // ----------------------------------------------------------------
        // user:
        // manage action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) use ($resource) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === $resource->getResourceIdentifier();
        }, true));

        // any action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) use ($resource) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === $resource->getResourceIdentifier();
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) use ($resource_2) {
            return $resourceActions === ['read']
                && $resourceIdentifier === $resource_2->getResourceIdentifier();
        }, true));

        // unavailable action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, 'foo');
        $this->assertCount(0, $usersResourceActions);

        // ----------------------------------------------------------------
        // user 2:
        // manage action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }, true));

        // any action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));

        // ----------------------------------------------------------------
        // user 3:
        // manage action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }, true));

        // any action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(4, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['write']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }, true));

        // test pagination (page size 3):
        $userResourceActionPage1 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, null, 0, 3);
        $this->assertCount(3, $userResourceActionPage1);
        $userResourceActionPage2 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, null, 3, 3);
        $this->assertCount(1, $userResourceActionPage2);

        $usersResourceActions = array_merge($userResourceActionPage1, $userResourceActionPage2);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['write']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }, true));

        // ----------------------------------------------------------------
        // user 4:
        // manage action
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_4', $userAttributes);
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }, true));

        // any action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(4, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['write']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }, true));

        // test pagination (page size 2):
        $userResourceActionPage1 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, null, 0, 2);
        $this->assertCount(2, $userResourceActionPage1);
        $userResourceActionPage2 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, null, 2, 2);
        $this->assertCount(2, $userResourceActionPage2);
        $userResourceActionPage3 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, null, 4, 2);
        $this->assertCount(0, $userResourceActionPage3);

        $usersResourceActions = array_merge($userResourceActionPage1, $userResourceActionPage2);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['write']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }, true));

        // ----------------------------------------------------------------
        // user 5 (student):
        // manage action
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_5', $userAttributes);
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION);
        $this->assertCount(0, $usersResourceActions);

        // read action
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, 'read');
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === ['read']
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }, true));

        // test pagination (page size 1):
        $userResourceActionPage1 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, null, 0, 1);
        $this->assertCount(1, $userResourceActionPage1);

        // test pagination (page size 0):
        $userResourceActionPage1 = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, null, 0, 0);
        $this->assertCount(0, $userResourceActionPage1);

        // ----------------------------------------------------------------
        // user 6:
        // any action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_6');
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(0, $usersResourceActions);

        $this->login('controller_user');
        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(5, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }, true));

        $usersResourceActions = $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION);
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
            self::TEST_RESOURCE_CLASS, null);
        $resourceCollection2 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS.'_2', null);

        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS);
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
            self::TEST_RESOURCE_CLASS);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceCollectionActions);

        // any action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceCollectionActions);

        // any action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS.'_2');
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceCollectionActions);

        // ----------------------------------------------------------------
        // user 2:
        // manage action:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');

        // any action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertEquals(['read'], $resourceCollectionActions);

        // ----------------------------------------------------------------
        // user 3:
        // manage action:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');

        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS);
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
            self::TEST_RESOURCE_CLASS);
        $this->assertEquals(['delete'], $resourceCollectionActions);

        // ----------------------------------------------------------------
        // user 5:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_5', $userAttributes);

        // delete action: user 5 has one personal grant and one grant via dynamic group 'employees'
        // -> expecting only 1 grant, since only unique resource actions should be returned
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertContains('read', $resourceCollectionActions);
        $this->assertContains('write', $resourceCollectionActions);
        $this->assertContains('delete', $resourceCollectionActions);

        // any action:
        $resourceCollectionActions = $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(3, $resourceCollectionActions);
        $this->assertContains('read', $resourceCollectionActions);
        $this->assertContains('write', $resourceCollectionActions);
        $this->assertContains('delete', $resourceCollectionActions);
    }

    public function testGetResourceActionGrantsByResourceClassAndIdentifier(): void
    {
        $group = $this->testEntityManager->addGroup();
        $resource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $resourceCollection = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, null);

        $rag_1_manage = $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $rag_1_read = $this->testEntityManager->addResourceActionGrant($resource,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, group: $group);
        $rag_coll_create = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            TestGetAvailableResourceClassActionsEventSubscriber::CREATE_ACTION, dynamicGroupIdentifier: 'everybody');

        $rags = $this->authorizationService->getResourceActionGrantsForResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(2, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manage);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read);

        $rags = $this->authorizationService->getResourceActionGrantsForResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, null);
        $this->assertCount(2, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_create);
        $this->assertCount(1, $this->selectWhere($rags,
            function (ResourceActionGrant $rag): bool {
                return $rag->getAction() === AuthorizationService::MANAGE_ACTION
                    && $rag->getResourceClass() === self::TEST_RESOURCE_CLASS
                    && $rag->getResourceIdentifier() === null
                    && $rag->getUserIdentifier() === null
                    && $rag->getGroup() === null
                    && $rag->getDynamicGroupIdentifier() === AuthorizationService::MANAGE_RESOURCE_COLLECTION_POLICY_PREFIX.self::TEST_RESOURCE_CLASS;
            }));
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
        $this->assertCount(3, $dynamicGroups);
        $this->assertContains('students', $dynamicGroups);
        $this->assertContains('employees', $dynamicGroups);
        $this->assertContains('everybody', $dynamicGroups);
    }

    public function testGetAuthorizationResourcesCurrentUserIsAuthorizedToRead(): void
    {
        $group1 = $this->testEntityManager->addGroup();
        $group2 = $this->testEntityManager->addGroup();

        $this->testEntityManager->addGroupMember($group1, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addGroupMember($group2, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addGroupMember($group2, self::ANOTHER_USER_IDENTIFIER.'_2');

        $resource1 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $resource2 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER_2);
        $resource3 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER);
        $resource4 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER.'_3');
        $resourceCollection = $this->testEntityManager->addAuthorizationResource('resourceClass_3', null);
        $collectionResource = $this->testEntityManager->addAuthorizationResource(self::TEST_COLLECTION_RESOURCE_CLASS, self::TEST_COLLECTION_RESOURCE_IDENTIFIER);

        $this->testEntityManager->addGrantInheritance(
            $collectionResource->getResourceClass(), $collectionResource->getResourceIdentifier(),
            $resource1->getResourceClass(), $resource1->getResourceIdentifier());

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
        $this->testEntityManager->addResourceActionGrant($collectionResource, 'read', self::ANOTHER_USER_IDENTIFIER.'_5');

        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead();
        $this->assertCount(3, $authorizationResources);
        $this->assertContainsResource($resource1, $authorizationResources);
        $this->assertContainsResource($resource3, $authorizationResources);
        $this->assertContainsResource($resourceCollection, $authorizationResources);

        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead(self::TEST_RESOURCE_CLASS);
        $this->assertCount(1, $authorizationResources);
        $this->assertContainsResource($resource1, $authorizationResources);

        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead(self::TEST_RESOURCE_CLASS_2);
        $this->assertCount(1, $authorizationResources);
        $this->assertContainsResource($resource3, $authorizationResources);

        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead('resourceClass_3');
        $this->assertCount(1, $authorizationResources);
        $this->assertContainsResource($resourceCollection, $authorizationResources);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $authorizationResources);
        $this->assertContainsResource($resource2, $authorizationResources);
        $this->assertContainsResource($resource4, $authorizationResources);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2', $userAttributes);
        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $authorizationResources);
        $this->assertContainsResource($resource2, $authorizationResources);
        $this->assertContainsResource($resource3, $authorizationResources);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_3', $userAttributes);
        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $authorizationResources);
        $this->assertContainsResource($resource2, $authorizationResources);
        $this->assertContainsResource($resourceCollection, $authorizationResources);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_4', $userAttributes);
        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead();
        $this->assertCount(3, $authorizationResources);
        $this->assertContainsResource($resource2, $authorizationResources);
        $this->assertContainsResource($resource3, $authorizationResources);
        $this->assertContainsResource($resourceCollection, $authorizationResources);

        $this->login(self::ANOTHER_USER_IDENTIFIER.'_5');
        // source and target resource of grant inheritance:
        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $authorizationResources);
        $this->assertContainsResource($resource1, $authorizationResources);
        $this->assertContainsResource($collectionResource, $authorizationResources);
        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead(self::TEST_RESOURCE_CLASS);
        $this->assertCount(1, $authorizationResources);
        $this->assertContainsResource($resource1, $authorizationResources);

        // ----------------------------------------------------------------
        // test pagination:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_4', $userAttributes);
        $authorizationResourcePage1 = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead(
            null, 0, 2);
        $this->assertCount(2, $authorizationResourcePage1);
        $authorizationResourcePage2 = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead(
            null, 2, 2);
        $this->assertCount(1, $authorizationResourcePage2);

        $authorizationResources = array_merge($authorizationResourcePage1, $authorizationResourcePage2);
        $this->assertContainsResource($resource2, $authorizationResources);
        $this->assertContainsResource($resource3, $authorizationResources);
        $this->assertContainsResource($resourceCollection, $authorizationResources);
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

        $resource1 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $resource2 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER_2);
        $resource3 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER);
        $resource4 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER.'_3');
        $resourceCollection = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS_2, null);

        $collectionResource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_COLLECTION_RESOURCE_CLASS, 'collectionResourceIdentifier');

        $this->testEntityManager->addGrantInheritance(
            $collectionResource->getResourceClass(), $collectionResource->getResourceIdentifier(),
            $resource1->getResourceClass(), $resource1->getResourceIdentifier());

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

        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(self::TEST_RESOURCE_CLASS);
        $this->assertCount(2, $resourceActionsGrants);
        $this->assertContainsResource($r1ManageCU, $resourceActionsGrants);
        $this->assertContainsResource($r1ReadAU3, $resourceActionsGrants);

        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(self::TEST_RESOURCE_CLASS_2);
        $this->assertCount(4, $resourceActionsGrants);
        $this->assertContainsResource($r3DeleteG1, $resourceActionsGrants);
        $this->assertContainsResource($rcManageG1, $resourceActionsGrants);
        $this->assertContainsResource($rcCreateCU, $resourceActionsGrants);
        $this->assertContainsResource($rcCreateStudents, $resourceActionsGrants);

        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(2, $resourceActionsGrants);
        $this->assertContainsResource($r1ManageCU, $resourceActionsGrants);
        $this->assertContainsResource($r1ReadAU3, $resourceActionsGrants);

        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(1, $resourceActionsGrants);
        $this->assertContainsResource($r3DeleteG1, $resourceActionsGrants);

        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS_2, AuthorizationService::IS_NULL);
        $this->assertCount(3, $resourceActionsGrants);
        $this->assertContainsResource($rcManageG1, $resourceActionsGrants);
        $this->assertContainsResource($rcCreateCU, $resourceActionsGrants);
        $this->assertContainsResource($rcCreateStudents, $resourceActionsGrants);

        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER_2);
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

    public function testGetResourceActionsGrantsUserIsAuthorizedToReadWithGrantInheritance(): void
    {
        $group1 = $this->testEntityManager->addGroup();
        $this->testEntityManager->addGroupMember($group1, self::ANOTHER_USER_IDENTIFIER);

        $resource1 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $resource2 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER_2);
        $resourceCollection = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, null);
        $collectionResource1 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_COLLECTION_RESOURCE_CLASS, self::TEST_COLLECTION_RESOURCE_IDENTIFIER);
        $collectionResource2 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_COLLECTION_RESOURCE_CLASS, self::TEST_COLLECTION_RESOURCE_IDENTIFIER.'_2');
        $collectionResourceCollection = $this->testEntityManager->addAuthorizationResource(
            self::TEST_COLLECTION_RESOURCE_CLASS, null);
        $superCollectionResource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_COLLECTION_RESOURCE_CLASS, self::TEST_COLLECTION_RESOURCE_IDENTIFIER.'_super');

        // grandparent -> parent
        $this->testEntityManager->addGrantInheritance(
            $superCollectionResource->getResourceClass(), $superCollectionResource->getResourceIdentifier(),
            $collectionResource1->getResourceClass(), $collectionResource1->getResourceIdentifier());

        // parent -> child (resource item)
        $this->testEntityManager->addGrantInheritance(
            $collectionResource1->getResourceClass(), $collectionResource1->getResourceIdentifier(),
            $resource1->getResourceClass(), $resource1->getResourceIdentifier());

        // parent -> child (resource item)
        $this->testEntityManager->addGrantInheritance(
            $collectionResource1->getResourceClass(), $collectionResource1->getResourceIdentifier(),
            $resource2->getResourceClass(), $resource2->getResourceIdentifier());

        // parent -> child
        $this->testEntityManager->addGrantInheritance(
            $collectionResource2->getResourceClass(), $collectionResource2->getResourceIdentifier(),
            $resource1->getResourceClass(), $resource1->getResourceIdentifier());

        // parent -> child (resource collection)
        $this->testEntityManager->addGrantInheritance(
            $collectionResourceCollection->getResourceClass(), $collectionResourceCollection->getResourceIdentifier(),
            $resourceCollection->getResourceClass(), $resourceCollection->getResourceIdentifier());

        $rag_1_manage = $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $rag_1_read = $this->testEntityManager->addResourceActionGrant($resource1,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, dynamicGroupIdentifier: 'employees');
        $rag_2_manage = $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, dynamicGroupIdentifier: 'students');
        $rag_coll_manage = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            AuthorizationService::MANAGE_ACTION, null, $group1);

        $rag_super_coll_read = $this->testEntityManager->addResourceActionGrant($superCollectionResource,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, 'big_brother');
        $rag_coll_1_manage = $this->testEntityManager->addResourceActionGrant($collectionResource1,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER.'_2');
        $rag_coll_2_read = $this->testEntityManager->addResourceActionGrant($collectionResource2,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, dynamicGroupIdentifier: 'students');
        $rag_coll_coll_create = $this->testEntityManager->addResourceActionGrant($collectionResourceCollection,
            TestGetAvailableResourceClassActionsEventSubscriber::CREATE_ACTION, group: $group1);

        $this->login(self::CURRENT_USER_IDENTIFIER);
        $rags = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead();
        $this->assertCount(5, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manage);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_1_manage, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_2_read, $resource1);

        $rags = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(self::TEST_RESOURCE_CLASS);
        $this->assertCount(5, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manage);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_1_manage, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_2_read, $resource1);

        $rags = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(5, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manage);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_1_manage, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_2_read, $resource1);

        $rags = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(self::TEST_COLLECTION_RESOURCE_CLASS);
        $this->assertCount(0, $rags);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $rags = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead();
        $this->assertCount(3, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_manage);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_coll_create);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_coll_create, $resourceCollection);

        $rags = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(self::TEST_RESOURCE_CLASS);
        $this->assertCount(2, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_manage);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_coll_create, $resourceCollection);

        $rags = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(self::TEST_COLLECTION_RESOURCE_CLASS);
        $this->assertCount(1, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_coll_create);

        $rags = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS, InternalResourceActionGrantService::IS_NULL);
        $this->assertCount(2, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_manage);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_coll_create, $resourceCollection);

        $this->login('big_brother');
        $rags = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead();
        $this->assertCount(4, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_super_coll_read);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $collectionResource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource2);

        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');
        $rags = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead();
        $this->assertCount(10, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_1_manage);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_1_manage, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_1_manage, $resource2);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_2_read, $resource1);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manage);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read);
        $this->assertContainsResourceActionGrant($rags, $rag_2_manage);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource2);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $collectionResource1);

        // test pagination:
        $ragPage1 = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(
            firstResultIndex: 0, maxNumResults: 6);
        $this->assertCount(6, $ragPage1);
        $ragPage2 = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(
            firstResultIndex: 6, maxNumResults: 6);
        $this->assertCount(4, $ragPage2);
        $rags = array_merge($ragPage1, $ragPage2);
        $this->assertCount(10, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_1_manage);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_1_manage, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_1_manage, $resource2);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_2_read, $resource1);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manage);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read);
        $this->assertContainsResourceActionGrant($rags, $rag_2_manage);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource2);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $collectionResource1);

        $rags = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(5, $rags);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_1_manage, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_2_read, $resource1);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manage);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource1);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login('some_student', $userAttributes);
        $rags = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead();
        $this->assertCount(5, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_2_read);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_2_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_1_manage, $resource2);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource2);
        $this->assertContainsResourceActionGrant($rags, $rag_2_manage);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login('some_employee', $userAttributes);
        $rags = $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead();
        $this->assertCount(1, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read);
    }

    public function testGetResourceClassesCurrentUserIsAuthorizedToRead(): void
    {
        $group1 = $this->testEntityManager->addGroup();
        $group2 = $this->testEntityManager->addGroup();

        $this->testEntityManager->addGroupMember($group1, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addGroupMember($group2, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addGroupMember($group2, self::ANOTHER_USER_IDENTIFIER.'_2');

        $resource1 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $resource2 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER_2);
        $resource3 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER);
        $resource4 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER.'_3');
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
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS_2, $resourceClasses);
        $this->assertContains('resourceClass_3', $resourceClasses);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS_2, $resourceClasses);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2', $userAttributes);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS_2, $resourceClasses);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_3', $userAttributes);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);
        $this->assertContains('resourceClass_3', $resourceClasses);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_4', $userAttributes);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(3, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS_2, $resourceClasses);
        $this->assertContains('resourceClass_3', $resourceClasses);

        $this->login(self::ANOTHER_USER_IDENTIFIER.'_foo');
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(0, $resourceClasses);
    }

    public function testGetResourceClassesCurrentUserIsAuthorizedToReadWithGrantInheritance(): void
    {
        $group1 = $this->testEntityManager->addGroup();
        $this->testEntityManager->addGroupMember($group1, self::ANOTHER_USER_IDENTIFIER);

        $resource1 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $resource2 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER_2);
        $resourceCollection = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, null);
        $collectionResource = $this->testEntityManager->addAuthorizationResource(self::TEST_COLLECTION_RESOURCE_CLASS, self::TEST_COLLECTION_RESOURCE_IDENTIFIER);
        $collectionResource2 = $this->testEntityManager->addAuthorizationResource(self::TEST_COLLECTION_RESOURCE_CLASS, self::TEST_COLLECTION_RESOURCE_IDENTIFIER.'_2');
        $superCollectionResource = $this->testEntityManager->addAuthorizationResource(self::TEST_COLLECTION_RESOURCE_CLASS, self::TEST_COLLECTION_RESOURCE_IDENTIFIER.'_super');

        $this->testEntityManager->addGrantInheritance(
            $superCollectionResource->getResourceClass(), $superCollectionResource->getResourceIdentifier(),
            $collectionResource->getResourceClass(), $collectionResource->getResourceIdentifier());

        $this->testEntityManager->addGrantInheritance(
            $collectionResource->getResourceClass(), $collectionResource->getResourceIdentifier(),
            $resource1->getResourceClass(), $resource1->getResourceIdentifier());
        $this->testEntityManager->addGrantInheritance(
            $collectionResource->getResourceClass(), $collectionResource->getResourceIdentifier(),
            $resource2->getResourceClass(), $resource2->getResourceIdentifier());

        $this->testEntityManager->addGrantInheritance(
            $collectionResource2->getResourceClass(), $collectionResource2->getResourceIdentifier(),
            $resource1->getResourceClass(), $resource1->getResourceIdentifier());

        $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, dynamicGroupIdentifier: 'employees');
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            AuthorizationService::MANAGE_ACTION, null, $group1);

        $this->testEntityManager->addResourceActionGrant($superCollectionResource,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, 'big_brother');
        $this->testEntityManager->addResourceActionGrant($collectionResource,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceActionGrant($collectionResource2,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, dynamicGroupIdentifier: 'students');

        $this->login(self::CURRENT_USER_IDENTIFIER);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(1, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(1, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2', $userAttributes);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);
        $this->assertContains(self::TEST_COLLECTION_RESOURCE_CLASS, $resourceClasses);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_3', $userAttributes);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);
        $this->assertContains(self::TEST_COLLECTION_RESOURCE_CLASS, $resourceClasses);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_4', $userAttributes);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);
        $this->assertContains(self::TEST_COLLECTION_RESOURCE_CLASS, $resourceClasses);

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
                Configuration::MANAGE_RESOURCE_COLLECTION_POLICY => 'user.get("MAY_MANAGE_TEST_RESOURCE_COLLECTION")',
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
                Configuration::MANAGE_RESOURCE_COLLECTION_POLICY => 'user.get("MAY_MANAGE_TEST_RESOURCE_COLLECTION")',
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
                Configuration::MANAGE_RESOURCE_COLLECTION_POLICY => 'user.get("MAY_MANAGE_TEST_RESOURCE_COLLECTION")',
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
                Configuration::MANAGE_RESOURCE_COLLECTION_POLICY => 'user.get("MAY_MANAGE_TEST_RESOURCE_COLLECTION")',
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

    public function testAddGroup(): void
    {
        $group = $this->testEntityManager->addGroup('Testgroup');
        $manageGroupGrant = $this->authorizationService->addGroup($group->getIdentifier());

        $manageGroupGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier(
            $manageGroupGrant->getIdentifier());
        $this->assertEquals($manageGroupGrant->getIdentifier(), $manageGroupGrantPersistence->getIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $manageGroupGrantPersistence->getAction());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $manageGroupGrantPersistence->getUserIdentifier());

        $authorizationResource = $this->testEntityManager->getAuthorizationResourceByIdentifier(
            $manageGroupGrant->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($manageGroupGrant->getAuthorizationResource()->getIdentifier(),
            $authorizationResource->getIdentifier());
        $this->assertEquals($group->getIdentifier(), $authorizationResource->getResourceIdentifier());
        $this->assertEquals(AuthorizationService::GROUP_RESOURCE_CLASS, $authorizationResource->getResourceClass());
    }

    public function testRemoveGroup(): void
    {
        [$group, $manageGroupGrant] = $this->addGroupAndManageGroupGrantForCurrentUser();

        $this->assertNotNull($this->testEntityManager->getAuthorizationResourceByIdentifier(
            $manageGroupGrant->getAuthorizationResource()->getIdentifier()));
        $this->assertNotNull($this->testEntityManager->getResourceActionGrantByIdentifier($manageGroupGrant->getIdentifier()));

        $this->authorizationService->removeGroup($group->getIdentifier());

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier(
            $manageGroupGrant->getAuthorizationResource()->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($manageGroupGrant->getIdentifier()));
    }

    public function testIsCurrentUserAuthorizedToAddGroups(): void
    {
        $manageGroupCollectionGrant = $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::GROUP_RESOURCE_CLASS, null,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($manageGroupCollectionGrant->getAuthorizationResource(),
            AuthorizationService::CREATE_GROUPS_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToAddGroups());

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToAddGroups());

        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToAddGroups());

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['MAY_CREATE_GROUPS'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2', $userAttributes);
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToAddGroups());
    }

    public function testIsCurrentUserAuthorizedToUpdateGroup(): void
    {
        [$group, $manageGroupGrant] = $this->addGroupAndManageGroupGrantForCurrentUser();

        $this->testEntityManager->addResourceActionGrant($manageGroupGrant->getAuthorizationResource(),
            AuthorizationService::UPDATE_GROUP_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToUpdateGroup($group));

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToUpdateGroup($group));

        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToUpdateGroup($group));
    }

    public function testIsCurrentUserAuthorizedToRemoveGroup(): void
    {
        [$group, $manageGroupGrant] = $this->addGroupAndManageGroupGrantForCurrentUser();

        $this->testEntityManager->addResourceActionGrant($manageGroupGrant->getAuthorizationResource(),
            AuthorizationService::DELETE_GROUP_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToRemoveGroup($group));

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToRemoveGroup($group));

        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToRemoveGroup($group));
    }

    public function testIsCurrentUserAuthorizedToReadGroup(): void
    {
        [$group, $manageGroupGrant] = $this->addGroupAndManageGroupGrantForCurrentUser();

        $this->testEntityManager->addResourceActionGrant($manageGroupGrant->getAuthorizationResource(),
            AuthorizationService::READ_GROUP_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToReadGroup($group));

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToReadGroup($group));

        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToReadGroup($group));
    }

    protected function getTestConfig(): array
    {
        return array_merge(parent::getTestConfig(), $this->testConfig);
    }

    protected function getDefaultUserAttributes(): array
    {
        $defaultUserAttributes = parent::getDefaultUserAttributes();
        $defaultUserAttributes['MAY_MANAGE_TEST_RESOURCE_COLLECTION'] = false;
        $defaultUserAttributes['IS_STUDENT'] = false;
        $defaultUserAttributes['IS_EMPLOYEE'] = false;

        return $defaultUserAttributes;
    }
}
