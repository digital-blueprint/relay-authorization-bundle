<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Authorization;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Service\UserAttributeProvider;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractAuthorizationServiceTestCase;
use Dbp\Relay\AuthorizationBundle\Tests\TestResources;
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
    }

    public function testManageResourceCollectionPolicy(): void
    {
        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEmpty($resourceCollectionActions,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);

        $attributes = $this->getDefaultUserAttributes();
        $attributes['MAY_MANAGE_TEST_RESOURCE_COLLECTION'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $attributes);

        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceCollectionActions);

        $availableResourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertEquals([self::TEST_RESOURCE_CLASS], $availableResourceClasses);

        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(1, $authorizationResources);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $authorizationResources[0]->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $authorizationResources[0]->getResourceIdentifier());

        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER
        );
        $this->assertCount(1, $authorizationResources);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $authorizationResources[0]->getResourceClass());
        $this->assertEquals(
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            $authorizationResources[0]->getResourceIdentifier()
        );

        $resourceActionGrants = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrants[0]->getResourceClass());
        $this->assertEquals(
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            $resourceActionGrants[0]->getResourceIdentifier()
        );
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $resourceActionGrants[0]->getAction());

        $resourceActionGrants = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER
        );
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrants[0]->getResourceClass());
        $this->assertEquals(
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            $resourceActionGrants[0]->getResourceIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $resourceActionGrants[0]->getAction());
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
        $currentUsersDynamicGroups = $this->authorizationService->getDynamicGroupsCurrentUserIsMemberOf();
        $this->assertEquals(['everybody'], $currentUsersDynamicGroups);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['MAY_MANAGE_TEST_RESOURCE_COLLECTION'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $userAttributes);

        $currentUsersDynamicGroups = $this->authorizationService->getDynamicGroupsCurrentUserIsMemberOf();
        $this->assertIsPermutationOf(['everybody', '@resourceClass'], $currentUsersDynamicGroups);

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

    public function testGetGrantedResourceActionsForCurrentUser(): void
    {
        // everybody has a TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION grant
        // self::CURRENT_USER_IDENTIFIER has a 'manage' grant
        // self::CURRENT_USER_IDENTIFIER.'_2' has a TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION grant
        // self::CURRENT_USER_IDENTIFIER.'_3' has a TestGetAvailableResourceClassActionsEventSubscriber::UPDATE_ACTION grant (as a member of group1)
        // self::CURRENT_USER_IDENTIFIER.'_4' has a TestGetAvailableResourceClassActionsEventSubscriber::DELETE_ACTION grant (as a member of dynamic group 'employees')
        // self::CURRENT_USER_IDENTIFIER.'_5' has a TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, TestGetAvailableResourceClassActionsEventSubscriber::DELETE_ACTION grant, a TestGetAvailableResourceClassActionsEventSubscriber::UPDATE_ACTION grant (as a member of group1),
        // and a TestGetAvailableResourceClassActionsEventSubscriber::UPDATE_ACTION grant (as a member of dynamic group 'employees')
        // self::CURRENT_USER_IDENTIFIER.'_6' has a TestGetAvailableResourceClassActionsEventSubscriber::DELETE_ACTION grant, and a TestGetAvailableResourceClassActionsEventSubscriber::DELETE_ACTION grant (as a member of group2)
        // self::CURRENT_USER_IDENTIFIER.'_7' has a TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, TestGetAvailableResourceClassActionsEventSubscriber::DELETE_ACTION, a TestGetAvailableResourceClassActionsEventSubscriber::UPDATE_ACTION grant (as a member of group1),
        // a TestGetAvailableResourceClassActionsEventSubscriber::DELETE_ACTION grant (as a member of group2), a TestGetAvailableResourceClassActionsEventSubscriber::UPDATE_ACTION grant (as a member of dynamic group 'employees')

        $resource = $this->testEntityManager->addAuthorizationResource();

        $group1 = $this->testEntityManager->addUserGroup();
        $this->testEntityManager->addGroupMember($group1, self::CURRENT_USER_IDENTIFIER.'_3');
        $this->testEntityManager->addGroupMember($group1, self::CURRENT_USER_IDENTIFIER.'_5');
        $this->testEntityManager->addGroupMember($group1, self::CURRENT_USER_IDENTIFIER.'_7');

        $group2 = $this->testEntityManager->addUserGroup();
        $this->testEntityManager->addGroupMember($group2, self::CURRENT_USER_IDENTIFIER.'_6');
        $this->testEntityManager->addGroupMember($group2, self::CURRENT_USER_IDENTIFIER.'_7');

        $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource, TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceActionGrant($resource, TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER.'_5');
        $this->testEntityManager->addResourceActionGrant($resource, TestResources::DELETE_ACTION, self::CURRENT_USER_IDENTIFIER.'_5');
        $this->testEntityManager->addResourceActionGrant($resource, TestResources::DELETE_ACTION, self::CURRENT_USER_IDENTIFIER.'_6');
        $this->testEntityManager->addResourceActionGrant($resource, TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER.'_7');
        $this->testEntityManager->addResourceActionGrant($resource, TestResources::DELETE_ACTION, self::CURRENT_USER_IDENTIFIER.'_7');

        $this->testEntityManager->addResourceActionGrant($resource, TestResources::UPDATE_ACTION, null, $group1);
        $this->testEntityManager->addResourceActionGrant($resource, TestResources::DELETE_ACTION, null, $group2);
        $this->testEntityManager->addResourceActionGrant($resource, TestResources::UPDATE_ACTION, null, null, 'employees');
        $this->testEntityManager->addResourceActionGrant($resource, TestResources::READ_ACTION, null, null, 'everybody');

        // add some noise:
        $resource2 = $this->testEntityManager->addAuthorizationResource();
        $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, 'somebody_else');

        // ----------------------------------------------------------------
        // current user:
        $resourceItemActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([AuthorizationService::MANAGE_ACTION], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 2:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');

        $resourceItemActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([TestResources::READ_ACTION], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 3:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');

        $resourceItemActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([
            TestResources::UPDATE_ACTION,
            TestResources::READ_ACTION], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 4:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_4', $userAttributes);
        $resourceItemActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([
            TestResources::UPDATE_ACTION,
            TestResources::READ_ACTION], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 5:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_5', $userAttributes);
        $resourceItemActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
            TestResources::DELETE_ACTION,
            TestResources::UPDATE_ACTION], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 6:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_6');
        $resourceItemActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([
            TestResources::DELETE_ACTION,
            TestResources::READ_ACTION], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 7:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_7', $userAttributes);
        $resourceItemActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
            TestResources::DELETE_ACTION,
            TestResources::UPDATE_ACTION], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 8:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_8');
        $resourceItemActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals([TestResources::READ_ACTION], $resourceItemActions);

        $this->login(userIdentifier: null);
        $resourceItemActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals([TestResources::READ_ACTION], $resourceItemActions);
    }

    public function testGetGrantedResourceActionsForCurrentUserForCollectionResource(): void
    {
        // user: manage
        // user 2: read
        // user 3: read, write (as member of 'Testgroup')
        // user 4: delete (as member of dynamic group 'employees')
        // user 5: read, delete, write (as member of 'Testgroup'), delete (as member of dynamic group 'employees')
        $testGroup = $this->testEntityManager->addUserGroup();
        $this->testEntityManager->addGroupMember($testGroup, self::CURRENT_USER_IDENTIFIER.'_3');
        $this->testEntityManager->addGroupMember($testGroup, self::CURRENT_USER_IDENTIFIER.'_5');

        $resourceCollection1 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $resourceCollection2 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS_2, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);

        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEmpty($resourceCollectionActions);
        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEmpty($resourceCollectionActions);

        $this->testEntityManager->addResourceActionGrant($resourceCollection2,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resourceCollection2,
            TestResources::CREATE_ACTION, self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceActionGrant($resourceCollection2,
            TestResources::CREATE_ACTION, self::CURRENT_USER_IDENTIFIER.'_3');
        $this->testEntityManager->addResourceActionGrant($resourceCollection2,
            TestResources::CREATE_ACTION, self::CURRENT_USER_IDENTIFIER.'_5');
        $this->testEntityManager->addResourceActionGrant($resourceCollection2,
            TestResources::DELETE_ALL_ACTION, self::CURRENT_USER_IDENTIFIER.'_5');
        $this->testEntityManager->addResourceActionGrant($resourceCollection2,
            TestResources::READ_ACTION, null, $testGroup);
        $this->testEntityManager->addResourceActionGrant($resourceCollection2,
            TestResources::DELETE_ALL_ACTION, null, null, 'employees');

        $this->testEntityManager->addResourceActionGrant($resourceCollection1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        // ----------------------------------------------------------------
        // current user:
        // manage action:
        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceCollectionActions);

        // any action:
        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceCollectionActions);

        // any action:
        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS.'_2',
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceCollectionActions);

        // ----------------------------------------------------------------
        // user 2:
        // manage action:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');

        // any action:
        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals([TestResources::CREATE_ACTION], $resourceCollectionActions);

        // ----------------------------------------------------------------
        // user 3:
        // manage action:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');

        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertCount(2, $resourceCollectionActions);
        $this->assertContains(TestResources::CREATE_ACTION, $resourceCollectionActions);
        $this->assertContains(TestResources::READ_ACTION, $resourceCollectionActions);

        // ----------------------------------------------------------------
        // user 4:
        // manage action:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_4', $userAttributes);

        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals([TestResources::DELETE_ALL_ACTION], $resourceCollectionActions);

        // ----------------------------------------------------------------
        // user 5:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_5', $userAttributes);

        // delete action: user 5 has one personal grant and one grant via dynamic group 'employees'
        // -> expecting only 1 grant, since only unique resource actions should be returned
        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertContains(TestResources::READ_ACTION, $resourceCollectionActions);
        $this->assertContains(TestResources::CREATE_ACTION, $resourceCollectionActions);
        $this->assertContains(TestResources::DELETE_ALL_ACTION, $resourceCollectionActions);

        // any action:
        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertCount(3, $resourceCollectionActions);
        $this->assertContains(TestResources::READ_ACTION, $resourceCollectionActions);
        $this->assertContains(TestResources::CREATE_ACTION, $resourceCollectionActions);
        $this->assertContains(TestResources::DELETE_ALL_ACTION, $resourceCollectionActions);

        // ----------------------------------------------------------------
        $this->login(null);
        $this->assertEmpty($this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER)
        );
    }

    public function testGetGrantedResourceActionsForCurrentUserWithRoles(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup();
        $this->testEntityManager->addGroupMember($userGroup, self::CURRENT_USER_IDENTIFIER);

        $roleEditor = $this->internalResourceActionGrantService->addRole([],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::WRITE_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::CREATE_ACTION, AvailableResourceClassAction::COLLECTION_ACTION_TYPE),
                // add some noise:
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS_2, TestResources::UPDATE_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS_2, TestResources::DELETE_ALL_ACTION, AvailableResourceClassAction::COLLECTION_ACTION_TYPE),
            ]
        );
        $roleItemUpdater = $this->internalResourceActionGrantService->addRole([],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::UPDATE_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
            ]
        );
        $roleCollectionUpdater = $this->internalResourceActionGrantService->addRole([],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::UPDATE_ACTION, AvailableResourceClassAction::COLLECTION_ACTION_TYPE),
            ]
        );

        $resourceItem = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER
        );
        $resourceCollection = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER
        );

        $this->testEntityManager->addResourceActionGrant($resourceItem,
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
            role: $roleEditor
        );
        $this->testEntityManager->addResourceActionGrant($resourceItem,
            dynamicUserGroupIdentifier: 'everybody',
            role: $roleItemUpdater,
        );
        $this->testEntityManager->addResourceActionGrant($resourceItem,
            action: TestResources::DELETE_ACTION,
            userGroup: $userGroup
        );
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
            role: $roleEditor
        );
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            action: TestResources::READ_ACTION,
            userGroup: $userGroup
        );
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            dynamicUserGroupIdentifier: 'everybody',
            role: $roleCollectionUpdater
        );

        $grantedItemActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER
        );
        $this->assertIsPermutationOf(
            [TestResources::READ_ACTION, TestResources::WRITE_ACTION, TestResources::DELETE_ACTION, TestResources::UPDATE_ACTION],
            $grantedItemActions
        );

        $grantedCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            TestResources::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER
        );
        $this->assertIsPermutationOf(
            [TestResources::CREATE_ACTION, TestResources::READ_ACTION, TestResources::UPDATE_ACTION],
            $grantedCollectionActions);
    }

    public function testGetGrantedResourceActionsForCurrentUserWithGroupResources(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER
        );
        $resourceGroup = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_GROUP_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER);

        $roleWriter = $this->internalResourceActionGrantService->addRole([],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::WRITE_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::DELETE_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
                // add some noise:
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, AvailableResourceClassAction::COLLECTION_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS_2, TestResources::UPDATE_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
            ]
        );
        $roleReader = $this->internalResourceActionGrantService->addRole([],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
                // add some noise:
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::CREATE_ACTION, AvailableResourceClassAction::COLLECTION_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS_2, TestResources::DELETE_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
            ]
        );

        $group1 = $this->testEntityManager->addUserGroup();
        $group2 = $this->testEntityManager->addUserGroup();
        $this->testEntityManager->addGroupMember($group1, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addGroupMember($group2, self::ANOTHER_USER_IDENTIFIER.'_2');

        $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource,
            TestResources::UPDATE_ACTION, userGroup: $group1);
        $this->testEntityManager->addResourceActionGrant($resource,
            dynamicUserGroupIdentifier: 'everybody',
            role: $roleReader);

        $this->testEntityManager->addResourceToGroupResource(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resource->getResourceClass(), $resource->getResourceIdentifier());

        $this->testEntityManager->addResourceActionGrant($resourceGroup,
            dynamicUserGroupIdentifier: 'employees',
            actionResourceClass: TestResources::TEST_RESOURCE_CLASS,
            role: $roleWriter
        );
        $this->testEntityManager->addResourceActionGrant($resourceGroup,
            AuthorizationService::MANAGE_ACTION, 'admin',
            actionResourceClass: TestResources::TEST_RESOURCE_CLASS
        );
        $this->testEntityManager->addResourceActionGrant($resourceGroup,
            TestResources::WRITE_ACTION,
            userGroup: $group2,
            actionResourceClass: TestResources::TEST_RESOURCE_CLASS
        );

        // add some noise:
        $resource2 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER_2);
        $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, 'somebody_else');

        // ----------------------------------------------------------------
        // current user:
        $resourceItemActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([AuthorizationService::MANAGE_ACTION], $resourceItemActions);

        // ----------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $resourceItemActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
            TestResources::UPDATE_ACTION,
        ], $resourceItemActions);

        // ----------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');
        $resourceItemActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
            TestResources::WRITE_ACTION,
        ], $resourceItemActions);

        // ----------------------------------------------------------------
        $this->login('admin');
        $resourceItemActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceItemActions);

        // ----------------------------------------------------------------
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login('some_employee', $userAttributes);
        $resourceItemActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
            TestResources::WRITE_ACTION,
            TestResources::DELETE_ACTION,
        ], $resourceItemActions);

        // ----------------------------------------------------------------
        // user 5:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login('some_student', $userAttributes);
        $resourceItemActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
        ], $resourceItemActions);

        // ----------------------------------------------------------------
        $this->login('somebody_else');
        $resourceItemActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
        ], $resourceItemActions);

        // ----------------------------------------------------------------
        $this->login('everybody_user');
        $resourceItemActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
        ], $resourceItemActions);

        // ----------------------------------------------------------------
        $this->login(userIdentifier: null);
        $resourceItemActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
        ], $resourceItemActions);
    }

    public function testGetGrantedResourceActionsForCurrentUserForCollectionResourceWithGroupResources(): void
    {
        $roleReadAll = $this->internalResourceActionGrantService->addRole([],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, AvailableResourceClassAction::COLLECTION_ACTION_TYPE),
                // add some noise:
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS_2, TestResources::DELETE_ALL_ACTION, AvailableResourceClassAction::COLLECTION_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::WRITE_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
            ]
        );
        $roleCreator = $this->internalResourceActionGrantService->addRole([],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::CREATE_ACTION, AvailableResourceClassAction::COLLECTION_ACTION_TYPE),
            ]
        );

        $resourceCollection = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);

        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            dynamicUserGroupIdentifier: 'everybody',
            role: $roleCreator
        );

        $resourceGroup = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_GROUP_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER);

        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals([TestResources::CREATE_ACTION], $resourceCollectionActions);

        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals([ResourceActionGrantService::MANAGE_ACTION], $resourceCollectionActions);

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');

        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals([TestResources::CREATE_ACTION], $resourceCollectionActions);

        $this->testEntityManager->addResourceToGroupResource(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resourceCollection->getResourceClass(), $resourceCollection->getResourceIdentifier());

        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals([TestResources::CREATE_ACTION], $resourceCollectionActions);

        $this->testEntityManager->addResourceActionGrant($resourceGroup,
            action: TestResources::UPDATE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER.'_2',
            actionResourceClass: TestResources::TEST_RESOURCE_CLASS,
            actionType: AvailableResourceClassAction::COLLECTION_ACTION_TYPE);

        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([TestResources::CREATE_ACTION, TestResources::UPDATE_ACTION],
            $resourceCollectionActions);

        $this->testEntityManager->addResourceActionGrant($resourceGroup,
            userIdentifier: self::CURRENT_USER_IDENTIFIER.'_2',
            role: $roleReadAll
        );

        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertIsPermutationOf([TestResources::CREATE_ACTION, TestResources::READ_ACTION, TestResources::UPDATE_ACTION],
            $resourceCollectionActions);

        $this->login(userIdentifier: null);
        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals([TestResources::CREATE_ACTION], $resourceCollectionActions);
    }

    public function testGetGrantedResourceActionsPageForCurrentUser(): void
    {
        $testGroup = $this->testEntityManager->addUserGroup();
        $this->testEntityManager->addGroupMember($testGroup, self::CURRENT_USER_IDENTIFIER.'_3');

        $roleReader = $this->internalResourceActionGrantService->addRole([],
            [
                ResourceActionGrantService::createRoleAction(
                    self::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
            ],
        );

        // managed by user
        // readable by user 2
        // readable by user 3
        // readable by user 4
        $resource = $this->testEntityManager->addAuthorizationResource();
        $this->testEntityManager->addResourceActionGrant($resource,
            action: AuthorizationService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );
        $this->testEntityManager->addResourceActionGrant($resource,
            action: TestResources::READ_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER.'_2'
        );
        $this->testEntityManager->addResourceActionGrant($resource,
            userIdentifier: self::CURRENT_USER_IDENTIFIER.'_3',
            role: $roleReader
        );
        $this->testEntityManager->addResourceActionGrant($resource,
            action: TestResources::READ_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER.'_4');

        // managed by user 2
        // readable by user
        // writable by group 'Testgroup'
        $resource_2 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceActionGrant($resource_2,
            action: AuthorizationService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER.'_2'
        );
        $this->testEntityManager->addResourceActionGrant($resource_2,
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
            role: $roleReader
        );
        $this->testEntityManager->addResourceActionGrant($resource_2, TestResources::UPDATE_ACTION, null, $testGroup);

        // managed by user 3
        // writable by dynamic group 'employees'
        $resource_3 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_3');
        $this->testEntityManager->addResourceActionGrant($resource_3,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER.'_3');
        $this->testEntityManager->addResourceActionGrant($resource_3, TestResources::UPDATE_ACTION, null, null, 'employees');

        // managed by group 'Testgroup'
        // readable by user 4
        $resource_4 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_4');
        $this->testEntityManager->addResourceActionGrant($resource_4,
            AuthorizationService::MANAGE_ACTION, null, $testGroup);
        $this->testEntityManager->addResourceActionGrant($resource_4,
            TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER.'_4');

        // managed by dynamic group 'employees'
        // readable by dynamic group 'students'
        $resource_5 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_5');
        $this->testEntityManager->addResourceActionGrant($resource_5,
            action: AuthorizationService::MANAGE_ACTION,
            dynamicUserGroupIdentifier: 'employees'
        );
        $this->testEntityManager->addResourceActionGrant($resource_5,
            dynamicUserGroupIdentifier: 'students',
            role: $roleReader
        );

        // add some noise:
        $resource_foo = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_foo');
        $this->testEntityManager->addResourceActionGrant($resource_foo,
            AuthorizationService::MANAGE_ACTION, 'foo');
        $this->testEntityManager->addResourceActionGrant($resource_foo,
            TestResources::READ_ACTION, null, null, 'bar');

        // ----------------------------------------------------------------
        // user:
        // manage action
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) use ($resource) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === $resource->getResourceIdentifier();
        }, true));

        // any action
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) use ($resource) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === $resource->getResourceIdentifier();
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) use ($resource_2) {
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === $resource_2->getResourceIdentifier();
        }, true));

        // unavailable action
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, 'foo');
        $this->assertCount(0, $usersResourceActions);

        // ----------------------------------------------------------------
        // user 2:
        // manage action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }, true));

        // any action
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));

        // ----------------------------------------------------------------
        // user 3:
        // manage action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
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
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
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
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::UPDATE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }, true));

        // test pagination (page size 3):
        $userResourceActionPage1 = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 0, maxNumResults: 3);
        $this->assertCount(3, $userResourceActionPage1);
        $userResourceActionPage2 = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 3, maxNumResults: 3);
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
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::UPDATE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }, true));

        // ----------------------------------------------------------------
        // user 4:
        // manage action
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_4', $userAttributes);
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }, true));

        // any action
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(4, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::UPDATE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }, true));

        // test pagination (page size 2):
        $userResourceActionPage1 = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 0, maxNumResults: 2);
        $this->assertCount(2, $userResourceActionPage1);
        $userResourceActionPage2 = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 2, maxNumResults: 2);
        $this->assertCount(2, $userResourceActionPage2);
        $userResourceActionPage3 = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 4, maxNumResults: 2);
        $this->assertCount(0, $userResourceActionPage3);

        $usersResourceActions = array_merge($userResourceActionPage1, $userResourceActionPage2);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::UPDATE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }, true));

        // ----------------------------------------------------------------
        // user 5 (student):
        // manage action
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_5', $userAttributes);
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION);
        $this->assertCount(0, $usersResourceActions);

        // read action
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, TestResources::READ_ACTION);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }, true));

        // test pagination (page size 1):
        $userResourceActionPage1 = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 0, maxNumResults: 1);
        $this->assertCount(1, $userResourceActionPage1);

        // test pagination (page size 0):
        $userResourceActionPage1 = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 0, maxNumResults: 0);
        $this->assertCount(0, $userResourceActionPage1);

        // ----------------------------------------------------------------
        // user 6:
        // any action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_6');
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(0, $usersResourceActions);

        // ----------------------------------------------------------------
        $this->login(userIdentifier: null);
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(0, $usersResourceActions);
    }

    public function testGetGrantedResourceActionsPageForCurrentUserWithGroupResourcesMany(): void
    {
        $collectionResource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_GROUP_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($collectionResource,
            TestResources::READ_ACTION, 'controller_user',
            actionResourceClass: TestResources::TEST_RESOURCE_CLASS
        );

        $numResources = 1000;
        for ($i = 0; $i < $numResources; ++$i) {
            $resource = $this->testEntityManager->addAuthorizationResource(
                TestResources::TEST_RESOURCE_CLASS, (string) $i
            );
            $this->testEntityManager->addResourceToGroupResource(
                $collectionResource->getResourceClass(), $collectionResource->getResourceIdentifier(),
                $resource->getResourceClass(), $resource->getResourceIdentifier());
            $this->testEntityManager->addResourceActionGrant($resource,
                TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
            $this->testEntityManager->addResourceActionGrant($resource,
                TestResources::UPDATE_ACTION, self::CURRENT_USER_IDENTIFIER);
            $this->testEntityManager->addResourceActionGrant($resource,
                TestResources::DELETE_ACTION, self::CURRENT_USER_IDENTIFIER);
            $this->testEntityManager->getEntityManager()->clear();
        }

        $grantedResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            maxNumResults: 2 * $numResources);

        $this->assertCount($numResources, $grantedResourceActions);
        $identifiersReturned = [];
        foreach ($grantedResourceActions as $resourceIdentifier => $actions) {
            $this->assertNotContains($resourceIdentifier, $identifiersReturned);
            $identifiersReturned[] = $resourceIdentifier;
            $this->assertIsPermutationOf([
                TestResources::READ_ACTION,
                TestResources::UPDATE_ACTION,
                TestResources::DELETE_ACTION,
            ], $actions);
        }

        // test pagination:
        $maxNumResults = 400;
        $grantedResourceActionsPage1 = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            firstResultIndex: 0,
            maxNumResults: $maxNumResults);
        $this->assertCount($maxNumResults, $grantedResourceActionsPage1);
        $grantedResourceActionsPage2 = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            firstResultIndex: $maxNumResults,
            maxNumResults: $maxNumResults);
        $this->assertCount($maxNumResults, $grantedResourceActionsPage2);
        $grantedResourceActionsPage3 = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            firstResultIndex: 2 * $maxNumResults,
            maxNumResults: $maxNumResults);
        $this->assertCount($numResources - (2 * $maxNumResults), $grantedResourceActionsPage3);

        $grantedResourceActions = array_merge($grantedResourceActionsPage1, $grantedResourceActionsPage2, $grantedResourceActionsPage3);
        $this->assertCount($numResources, $grantedResourceActions);

        $identifiersReturned = [];
        foreach ($grantedResourceActions as $resourceIdentifier => $actions) {
            $this->assertNotContains($resourceIdentifier, $identifiersReturned);
            $identifiersReturned[] = $resourceIdentifier;
            $this->assertIsPermutationOf([
                TestResources::READ_ACTION,
                TestResources::UPDATE_ACTION,
                TestResources::DELETE_ACTION,
            ], $actions);
        }
    }

    public function testGetGrantedResourceActionsPageForCurrentUserWithGroupResources(): void
    {
        $testGroup = $this->testEntityManager->addUserGroup();
        $this->testEntityManager->addGroupMember($testGroup, self::CURRENT_USER_IDENTIFIER.'_3');

        $roleReviewer = $this->internalResourceActionGrantService->addRole([],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::UPDATE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
            ]
        );

        $resourceGroup = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_GROUP_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resourceGroup,
            TestResources::READ_ACTION, 'controller_user',
            actionResourceClass: TestResources::TEST_RESOURCE_CLASS
        );

        // managed by user
        // readable by user 2
        // readable by user 3
        // readable by user 4
        $resource = $this->testEntityManager->addAuthorizationResource();
        $this->testEntityManager->addResourceToGroupResource(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resource->getResourceClass(), $resource->getResourceIdentifier());
        $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource, TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceActionGrant($resource, TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER.'_3');
        $this->testEntityManager->addResourceActionGrant($resource, TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER.'_4');

        // managed by user 2
        // reviewable (read, update) by user
        // writable by group 'Testgroup'
        $resource_2 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceToGroupResource(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resource_2->getResourceClass(), $resource_2->getResourceIdentifier());
        $this->testEntityManager->addResourceActionGrant($resource_2,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceActionGrant($resource_2,
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
            role: $roleReviewer);
        $this->testEntityManager->addResourceActionGrant($resource_2, TestResources::UPDATE_ACTION, null, $testGroup);

        // managed by user 3
        // writable by dynamic group 'employees'
        $resource_3 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_3');
        $this->testEntityManager->addResourceToGroupResource(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resource_3->getResourceClass(), $resource_3->getResourceIdentifier());
        $this->testEntityManager->addResourceActionGrant($resource_3,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER.'_3');
        $this->testEntityManager->addResourceActionGrant($resource_3, TestResources::UPDATE_ACTION, null, null, 'employees');

        // managed by group 'Testgroup'
        // readable by user 4
        $resource_4 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_4');
        $this->testEntityManager->addResourceToGroupResource(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resource_4->getResourceClass(), $resource_4->getResourceIdentifier());
        $this->testEntityManager->addResourceActionGrant($resource_4,
            AuthorizationService::MANAGE_ACTION, null, $testGroup);
        $this->testEntityManager->addResourceActionGrant($resource_4,
            TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER.'_4');

        // managed by dynamic group 'employees'
        // reviewable (read, update) and deletable by dynamic group 'students'
        $resource_5 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_5');
        $this->testEntityManager->addResourceToGroupResource(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resource_5->getResourceClass(), $resource_5->getResourceIdentifier());
        $this->testEntityManager->addResourceActionGrant($resource_5,
            AuthorizationService::MANAGE_ACTION, null, null, 'employees');
        $this->testEntityManager->addResourceActionGrant($resource_5,
            dynamicUserGroupIdentifier: 'students',
            role: $roleReviewer
        );
        $this->testEntityManager->addResourceActionGrant($resource_5,
            action: TestResources::DELETE_ACTION,
            dynamicUserGroupIdentifier: 'students'
        );

        // add some noise:
        $resource_foo = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_foo');
        $this->testEntityManager->addResourceActionGrant($resource_foo,
            AuthorizationService::MANAGE_ACTION, 'foo');
        $this->testEntityManager->addResourceActionGrant($resource_foo,
            TestResources::READ_ACTION, null, null, 'bar');

        // ----------------------------------------------------------------
        // user:
        // manage action
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) use ($resource) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === $resource->getResourceIdentifier();
        }, true));

        // any action
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) use ($resource) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === $resource->getResourceIdentifier();
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) use ($resource_2) {
            return $this->isPermutationOf([TestResources::READ_ACTION, TestResources::UPDATE_ACTION], $resourceActions)
                && $resourceIdentifier === $resource_2->getResourceIdentifier();
        }, true));

        // unavailable action
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, 'foo');
        $this->assertCount(0, $usersResourceActions);

        // ----------------------------------------------------------------
        // user 2:
        // manage action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }, true));

        // any action
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));

        // ----------------------------------------------------------------
        // user 3:
        // manage action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
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
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
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
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::UPDATE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }, true));

        // test pagination (page size 3):
        $userResourceActionPage1 = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 0, maxNumResults: 3);
        $this->assertCount(3, $userResourceActionPage1);
        $userResourceActionPage2 = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 3, maxNumResults: 3);
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
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::UPDATE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }, true));

        // ----------------------------------------------------------------
        // user 4:
        // manage action
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_4', $userAttributes);
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }, true));

        // any action
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(4, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::UPDATE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }, true));

        // test pagination (page size 2):
        $userResourceActionPage1 = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 0, maxNumResults: 2);
        $this->assertCount(2, $userResourceActionPage1);
        $userResourceActionPage2 = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 2, maxNumResults: 2);
        $this->assertCount(2, $userResourceActionPage2);
        $userResourceActionPage3 = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 4, maxNumResults: 2);
        $this->assertCount(0, $userResourceActionPage3);

        $usersResourceActions = array_merge($userResourceActionPage1, $userResourceActionPage2);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [AuthorizationService::MANAGE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::UPDATE_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }, true));

        // ----------------------------------------------------------------
        // user 5 (student):
        // manage action
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_5', $userAttributes);
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION);
        $this->assertCount(0, $usersResourceActions);

        // read action
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, TestResources::READ_ACTION);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $this->isPermutationOf([TestResources::READ_ACTION, TestResources::UPDATE_ACTION, TestResources::DELETE_ACTION], $resourceActions)
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }, true));

        // test pagination (page size 1):
        $userResourceActionPage1 = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 0, maxNumResults: 1);
        $this->assertCount(1, $userResourceActionPage1);

        // test pagination (page size 0):
        $userResourceActionPage1 = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 0, maxNumResults: 0);
        $this->assertCount(0, $userResourceActionPage1);

        // ----------------------------------------------------------------
        // user 6:
        // any action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_6');
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(0, $usersResourceActions);

        // ----------------------------------------------------------------
        $this->login('controller_user');
        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(5, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER;
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }, true));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function ($resourceActions, $resourceIdentifier) {
            return $resourceActions === [TestResources::READ_ACTION]
                && $resourceIdentifier === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }, true));

        $usersResourceActions = $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION);
        $this->assertCount(0, $usersResourceActions);

        // ----------------------------------------------------------------
        $this->login(null);
        $this->assertEmpty($this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            self::TEST_RESOURCE_CLASS));
    }

    public function testGetResourceActionGrantsForResourceClassAndIdentifier(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup();
        $resource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $resourceCollection = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);

        $rag_1_manage = $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $rag_1_read = $this->testEntityManager->addResourceActionGrant($resource,
            TestResources::READ_ACTION, userGroup: $userGroup);
        $rag_coll_create = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            TestResources::CREATE_ACTION, dynamicUserGroupIdentifier: 'everybody');

        $rags = $this->authorizationService->getResourceActionGrantsForResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(2, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manage);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read);

        $rags = $this->authorizationService->getResourceActionGrantsForResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertCount(2, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_create);
        $this->assertCount(1, $this->selectWhere($rags,
            function (ResourceActionGrant $rag): bool {
                return $rag->getAction() === AuthorizationService::MANAGE_ACTION
                    && $rag->getResourceClass() === self::TEST_RESOURCE_CLASS
                    && $rag->getResourceIdentifier() === AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER
                    && $rag->getUserIdentifier() === null
                    && $rag->getUserGroup() === null
                    && $rag->getDynamicUserGroupIdentifier() === AuthorizationService::MANAGE_RESOURCE_COLLECTION_POLICY_PREFIX.self::TEST_RESOURCE_CLASS;
            }));
    }

    public function testGetResourceActionGrantsForResourceClassAndIdentifierWithRoles(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup();
        $resource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $resourceCollection = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);

        $roleManager = $this->internalResourceActionGrantService->addRole([],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                // add some noise:
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS_2, TestResources::UPDATE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
            ]);
        $roleEditor = $this->internalResourceActionGrantService->addRole([],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::WRITE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                // add some noise
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS_2, TestResources::DELETE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
            ]
        );
        $roleCreator = $this->internalResourceActionGrantService->addRole([],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::CREATE_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::DELETE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS_2, TestResources::DELETE_ALL_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE),
            ]
        );

        $rag_1_manager = $this->testEntityManager->addResourceActionGrant($resource,
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
            role: $roleManager
        );
        $rag_1_reader = $this->testEntityManager->addResourceActionGrant($resource,
            userGroup: $userGroup,
            role: $roleEditor
        );
        $rag_1_write = $this->testEntityManager->addResourceActionGrant($resource,
            action: TestResources::WRITE_ACTION,
            dynamicUserGroupIdentifier: 'everybody'
        );
        $rag_coll_mangage = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            action: AuthorizationService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
        );
        $rag_coll_creator = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            dynamicUserGroupIdentifier: 'everybody',
            role: $roleCreator
        );
        $rag_coll_create = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            action: TestResources::CREATE_ACTION,
            userGroup: $userGroup
        );

        $rags = $this->authorizationService->getResourceActionGrantsForResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(3, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manager);
        $this->assertContainsResourceActionGrant($rags, $rag_1_reader);
        $this->assertContainsResourceActionGrant($rags, $rag_1_write);

        $rags = $this->authorizationService->getResourceActionGrantsForResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertCount(4, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_mangage);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_creator);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_create);
        $this->assertCount(1, $this->selectWhere($rags,
            function (ResourceActionGrant $rag): bool {
                return $rag->getAction() === AuthorizationService::MANAGE_ACTION
                    && $rag->getResourceClass() === self::TEST_RESOURCE_CLASS
                    && $rag->getResourceIdentifier() === AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER
                    && $rag->getUserIdentifier() === null
                    && $rag->getUserGroup() === null
                    && $rag->getDynamicUserGroupIdentifier() === AuthorizationService::MANAGE_RESOURCE_COLLECTION_POLICY_PREFIX.self::TEST_RESOURCE_CLASS;
            }));
    }

    public function testGetGrantedResourceActionsForCurrentForGroupItemResource(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup();
        $this->testEntityManager->addGroupMember($userGroup, self::ANOTHER_USER_IDENTIFIER);

        $groupItemResource = $this->testEntityManager->addAuthorizationResource(
            AuthorizationService::GROUP_RESOURCE_CLASS, $userGroup->getIdentifier());

        $roleGroupManager = $this->internalResourceActionGrantService->addRole(
            ['en' => 'Group Manager', 'de' => 'Gruppenverwalter'],
            [
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::GROUP_RESOURCE_CLASS, AuthorizationService::MANAGE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                // add some noise:
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::GROUP_RESOURCE_CLASS, AuthorizationService::CREATE_GROUPS_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE),
            ]);

        $this->testEntityManager->addResourceActionGrant($groupItemResource,
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
            role: $roleGroupManager);
        $this->testEntityManager->addResourceActionGrant($groupItemResource,
            action: AuthorizationService::ADD_GROUP_MEMBERS_GROUP_ACTION,
            userGroup: $userGroup);

        $actions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS, $userGroup->getIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $actions);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $actions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS, $userGroup->getIdentifier());
        $this->assertEquals([AuthorizationService::ADD_GROUP_MEMBERS_GROUP_ACTION], $actions);
    }

    public function testGetGrantedResourceActionsForCurrentForGroupCollectionResource(): void
    {
        $roleGroupCreator = $this->internalResourceActionGrantService->addRole(
            ['en' => 'Group Creator', 'de' => 'Gruppenersteller'],
            [
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::GROUP_RESOURCE_CLASS,
                    AuthorizationService::CREATE_GROUPS_ACTION,
                    ResourceActionGrantService::COLLECTION_ACTION_TYPE),
            ]
        );

        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEmpty($resourceCollectionActions);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['MAY_CREATE_GROUPS'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $userAttributes);
        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceCollectionActions);

        $groupCollectionResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            AuthorizationService::GROUP_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);

        $this->testEntityManager->addResourceActionGrant($groupCollectionResource,
            action: AuthorizationService::CREATE_GROUPS_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($groupCollectionResource,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER,
            role: $roleGroupCreator);

        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $resourceCollectionActions);

        // ----------------------------------------------------------------
        // another user:
        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals([AuthorizationService::CREATE_GROUPS_ACTION], $resourceCollectionActions);

        // ----------------------------------------------------------------
        // user 3:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');
        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEmpty($resourceCollectionActions);

        // ----------------------------------------------------------------
        $this->login(null);
        $resourceCollectionActions = $this->authorizationService->getGrantedResourceActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
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
        $roleEditor = $this->internalResourceActionGrantService->addRole([],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::UPDATE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                // add some noise:
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS_2, TestResources::DELETE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
            ]
        );
        $roleCreator = $this->internalResourceActionGrantService->addRole([],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::CREATE_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE),
                // add some noise:
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS_2, TestResources::DELETE_ALL_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE),
            ]
        );

        $group1 = $this->testEntityManager->addUserGroup();
        $group2 = $this->testEntityManager->addUserGroup();

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
            self::TEST_RESOURCE_CLASS_3, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $collectionResource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_GROUP_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER);

        $this->testEntityManager->addResourceToGroupResource(
            $collectionResource->getResourceClass(), $collectionResource->getResourceIdentifier(),
            $resource1->getResourceClass(), $resource1->getResourceIdentifier());

        $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, null, $group2);
        $this->testEntityManager->addResourceActionGrant($resource2,
            TestResources::UPDATE_ACTION, null, null, 'students');
        $this->testEntityManager->addResourceActionGrant($resource3,
            AuthorizationService::MANAGE_ACTION, null, null, 'employees');
        $this->testEntityManager->addResourceActionGrant($resource3,
            userGroup: $group1,
            role: $roleEditor
        );
        $this->testEntityManager->addResourceActionGrant($resource4,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            AuthorizationService::MANAGE_ACTION, null, $group1);
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            TestResources::CREATE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            dynamicUserGroupIdentifier: 'students',
            role: $roleCreator);
        $this->testEntityManager->addResourceActionGrant($collectionResource,
            action: TestResources::READ_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER.'_5',
            actionResourceClass: TestResources::TEST_RESOURCE_CLASS
        );

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

        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead(self::TEST_RESOURCE_CLASS_3);
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
        // group and member resource:
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
            firstResultIndex: 0, maxNumResults: 2);
        $this->assertCount(2, $authorizationResourcePage1);
        $authorizationResourcePage2 = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead(
            firstResultIndex: 2, maxNumResults: 2);
        $this->assertCount(1, $authorizationResourcePage2);

        $authorizationResources = array_merge($authorizationResourcePage1, $authorizationResourcePage2);
        $this->assertContainsResource($resource2, $authorizationResources);
        $this->assertContainsResource($resource3, $authorizationResources);
        $this->assertContainsResource($resourceCollection, $authorizationResources);

        // ----------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_foo');
        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead();
        $this->assertCount(0, $authorizationResources);

        // ----------------------------------------------------------------
        $this->login(null);
        $authorizationResources = $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead();
        $this->assertCount(0, $authorizationResources);
    }

    public function testGetResourceActionGrantsUserIsAuthorizedToRead(): void
    {
        $roleEditor = $this->internalResourceActionGrantService->addRole([],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::UPDATE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                // add some noise
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS_2, TestResources::UPDATE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::CREATE_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE),
            ]);

        $roleCreator = $this->internalResourceActionGrantService->addRole([],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::CREATE_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                // add some noise
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS_2, TestResources::DELETE_ALL_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE),
            ]
        );

        $group1 = $this->testEntityManager->addUserGroup();
        $group2 = $this->testEntityManager->addUserGroup();

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
            self::TEST_RESOURCE_CLASS_2, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);

        $resourceGroup = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_GROUP_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER);

        $this->testEntityManager->addResourceToGroupResource(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resource1->getResourceClass(), $resource1->getResourceIdentifier());

        $r1ManageCU = $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $r1EditAU3 = $this->testEntityManager->addResourceActionGrant($resource1,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER.'_3',
            role: $roleEditor
        );
        $r2ManageG2 = $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, null, $group2);
        $r2WriteStudents = $this->testEntityManager->addResourceActionGrant($resource2,
            TestResources::UPDATE_ACTION, null, null, 'students');
        $r3ManageEmployees = $this->testEntityManager->addResourceActionGrant($resource3,
            AuthorizationService::MANAGE_ACTION, null, null, 'employees');
        $r3DeleteG1 = $this->testEntityManager->addResourceActionGrant($resource3,
            TestResources::DELETE_ACTION, null, $group1);
        $r4ManageAU = $this->testEntityManager->addResourceActionGrant($resource4,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $r4UpdateG2 = $this->testEntityManager->addResourceActionGrant($resource4,
            'update', null, $group2);
        $rcManageG1 = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            AuthorizationService::MANAGE_ACTION, null, $group1);
        $rcCreateCU = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            TestResources::CREATE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $rcCreatorStudents = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            dynamicUserGroupIdentifier: 'students',
            role: $roleCreator
        );

        // -------------------------------------------------------------------------------------------
        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead();
        $this->assertContainsResource($r1ManageCU, $resourceActionsGrants);
        $this->assertContainsResource($r1EditAU3, $resourceActionsGrants);
        $this->assertContainsResource($r3DeleteG1, $resourceActionsGrants);
        $this->assertContainsResource($rcManageG1, $resourceActionsGrants);
        $this->assertContainsResource($rcCreateCU, $resourceActionsGrants);
        $this->assertContainsResource($rcCreatorStudents, $resourceActionsGrants);

        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(self::TEST_RESOURCE_CLASS);
        $this->assertCount(2, $resourceActionsGrants);
        $this->assertContainsResource($r1ManageCU, $resourceActionsGrants);
        $this->assertContainsResource($r1EditAU3, $resourceActionsGrants);

        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(self::TEST_RESOURCE_CLASS_2);
        $this->assertCount(4, $resourceActionsGrants);
        $this->assertContainsResource($r3DeleteG1, $resourceActionsGrants);
        $this->assertContainsResource($rcManageG1, $resourceActionsGrants);
        $this->assertContainsResource($rcCreateCU, $resourceActionsGrants);
        $this->assertContainsResource($rcCreatorStudents, $resourceActionsGrants);

        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(2, $resourceActionsGrants);
        $this->assertContainsResource($r1ManageCU, $resourceActionsGrants);
        $this->assertContainsResource($r1EditAU3, $resourceActionsGrants);

        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(1, $resourceActionsGrants);
        $this->assertContainsResource($r3DeleteG1, $resourceActionsGrants);

        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS_2, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertCount(3, $resourceActionsGrants);
        $this->assertContainsResource($rcManageG1, $resourceActionsGrants);
        $this->assertContainsResource($rcCreateCU, $resourceActionsGrants);
        $this->assertContainsResource($rcCreatorStudents, $resourceActionsGrants);

        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER_2);
        $this->assertCount(0, $resourceActionsGrants);

        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(
            'resourceClass_foo');
        $this->assertCount(0, $resourceActionsGrants);

        // -------------------------------------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead();
        $this->assertCount(4, $resourceActionsGrants);
        $this->assertContainsResource($r2ManageG2, $resourceActionsGrants);
        $this->assertContainsResource($r2WriteStudents, $resourceActionsGrants);
        $this->assertContainsResource($r4ManageAU, $resourceActionsGrants);
        $this->assertContainsResource($r4UpdateG2, $resourceActionsGrants);

        // -------------------------------------------------------------------------------------------
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2', $userAttributes);
        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead();
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
        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead();
        $this->assertCount(3, $resourceActionsGrants);
        $this->assertContainsResource($r1EditAU3, $resourceActionsGrants);
        $this->assertContainsResource($r2WriteStudents, $resourceActionsGrants);
        $this->assertContainsResource($rcCreatorStudents, $resourceActionsGrants);

        // -------------------------------------------------------------------------------------------
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_4', $userAttributes);
        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead();
        $this->assertCount(4, $resourceActionsGrants);
        $this->assertContainsResource($r2WriteStudents, $resourceActionsGrants);
        $this->assertContainsResource($rcCreatorStudents, $resourceActionsGrants);
        $this->assertContainsResource($r3ManageEmployees, $resourceActionsGrants);
        $this->assertContainsResource($r3DeleteG1, $resourceActionsGrants);

        // -------------------------------------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_foo');
        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead();
        $this->assertCount(0, $resourceActionsGrants);

        // ----------------------------------------------------------------
        $this->login(null);
        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead();
        $this->assertCount(0, $resourceActionsGrants);
    }

    public function testGetResourceActionsGrantsUserIsAuthorizedToReadWithResourceGroups(): void
    {
        $group1 = $this->testEntityManager->addUserGroup();
        $this->testEntityManager->addGroupMember($group1, self::ANOTHER_USER_IDENTIFIER);

        $resource1 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $resource2 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER_2);
        $resourceCollection = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $resourceGroup = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_GROUP_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER);
        $resourceGroup2 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_GROUP_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER.'_2');
        $resourceGroupCollection = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_GROUP_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $superResourceGroup = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_GROUP_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER.'_super');

        // grandparent -> parent
        $this->testEntityManager->addResourceToGroupResource(
            $superResourceGroup->getResourceClass(), $superResourceGroup->getResourceIdentifier(),
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier());

        // parent -> child (resource item)
        $this->testEntityManager->addResourceToGroupResource(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resource1->getResourceClass(), $resource1->getResourceIdentifier());

        // parent -> child (resource item)
        $this->testEntityManager->addResourceToGroupResource(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resource2->getResourceClass(), $resource2->getResourceIdentifier());

        // parent -> child
        $this->testEntityManager->addResourceToGroupResource(
            $resourceGroup2->getResourceClass(), $resourceGroup2->getResourceIdentifier(),
            $resource1->getResourceClass(), $resource1->getResourceIdentifier());

        // parent -> child (resource collection)
        $this->testEntityManager->addResourceToGroupResource(
            $resourceGroupCollection->getResourceClass(), $resourceGroupCollection->getResourceIdentifier(),
            $resourceCollection->getResourceClass(), $resourceCollection->getResourceIdentifier());

        $rag_1_manage = $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $rag_1_read = $this->testEntityManager->addResourceActionGrant($resource1,
            TestResources::READ_ACTION, dynamicUserGroupIdentifier: 'employees');
        $rag_2_manage = $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, dynamicUserGroupIdentifier: 'students');
        $rag_coll_manage = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            AuthorizationService::MANAGE_ACTION, null, $group1);

        $rag_super_coll_read = $this->testEntityManager->addResourceActionGrant($superResourceGroup,
            TestResources::READ_ACTION, 'big_brother',
            actionResourceClass: TestResources::TEST_RESOURCE_CLASS
        );
        $rag_group_1_manage = $this->testEntityManager->addResourceActionGrant($resourceGroup,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER.'_2');
        $rag_group_2_read = $this->testEntityManager->addResourceActionGrant($resourceGroup2,
            TestResources::READ_ACTION,
            dynamicUserGroupIdentifier: 'students',
            actionResourceClass: TestResources::TEST_RESOURCE_CLASS
        );
        $rag_group_coll_create = $this->testEntityManager->addResourceActionGrant($resourceGroupCollection,
            TestResources::CREATE_ACTION,
            userGroup: $group1,
            actionResourceClass: TestResources::TEST_RESOURCE_CLASS,
            actionType: AvailableResourceClassAction::COLLECTION_ACTION_TYPE
        );

        $this->login(self::CURRENT_USER_IDENTIFIER);
        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead();
        $this->assertCount(5, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manage, ['delete']);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read, ['delete']);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_group_1_manage, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_group_2_read, $resource1);

        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(self::TEST_RESOURCE_CLASS);
        $this->assertCount(5, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manage, ['delete']);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read, ['delete']);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_group_1_manage, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_group_2_read, $resource1);

        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(5, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manage, ['delete']);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read, ['delete']);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_group_1_manage, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_group_2_read, $resource1);

        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(self::TEST_RESOURCE_GROUP_CLASS);
        $this->assertCount(0, $rags);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead();
        $this->assertCount(3, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_manage, ['delete']);
        $this->assertContainsResourceActionGrant($rags, $rag_group_coll_create, []);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_group_coll_create, $resourceCollection);

        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(self::TEST_RESOURCE_CLASS);
        $this->assertCount(2, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_manage, ['delete']);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_group_coll_create, $resourceCollection);

        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(self::TEST_RESOURCE_GROUP_CLASS);
        $this->assertCount(1, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_group_coll_create, []);

        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertCount(2, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_manage, ['delete']);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_group_coll_create, $resourceCollection);

        $this->login('big_brother');
        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead();
        $this->assertCount(4, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_super_coll_read, []);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resourceGroup);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource2);

        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');
        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead();
        $this->assertCount(10, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_group_1_manage, ['delete']);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_group_1_manage, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_group_1_manage, $resource2);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_group_2_read, $resource1);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manage, ['delete']);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read, ['delete']);
        $this->assertContainsResourceActionGrant($rags, $rag_2_manage, ['delete']);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource2);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resourceGroup);

        // test pagination:
        $ragPage1 = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(
            firstResultIndex: 0, maxNumResults: 6);
        $this->assertCount(6, $ragPage1);
        $ragPage2 = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(
            firstResultIndex: 6, maxNumResults: 6);
        $this->assertCount(4, $ragPage2);
        $rags = array_merge($ragPage1, $ragPage2);
        $this->assertCount(10, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_group_1_manage, ['delete']);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_group_1_manage, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_group_1_manage, $resource2);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_group_2_read, $resource1);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manage, ['delete']);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read, ['delete']);
        $this->assertContainsResourceActionGrant($rags, $rag_2_manage, ['delete']);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource2);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resourceGroup);

        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(5, $rags);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_group_1_manage, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_group_2_read, $resource1);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manage);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource1);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login('some_student', $userAttributes);
        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead();
        $this->assertCount(5, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_group_2_read);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_group_2_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_group_1_manage, $resource2);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_coll_read, $resource2);
        $this->assertContainsResourceActionGrant($rags, $rag_2_manage);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login('some_employee', $userAttributes);
        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead();
        $this->assertCount(1, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read);

        // ----------------------------------------------------------------
        $this->login(null);
        $resourceActionsGrants = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead();
        $this->assertCount(0, $resourceActionsGrants);
    }

    public function testGetResourceClassesCurrentUserIsAuthorizedToRead(): void
    {
        $roleUpdater = $this->internalResourceActionGrantService->addRole([],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS_2, TestResources::DELETE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS_2, TestResources::UPDATE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
            ]
        );
        $roleRC3Delete = $this->internalResourceActionGrantService->addRole([],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS_3, TestResources::WRITE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
            ]
        );

        $group1 = $this->testEntityManager->addUserGroup();
        $group2 = $this->testEntityManager->addUserGroup();

        $this->testEntityManager->addGroupMember($group1, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addGroupMember($group2, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addGroupMember($group2, self::ANOTHER_USER_IDENTIFIER.'_2');

        $rc_1 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $rc_2 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER_2);
        $rc2_1 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER);
        $rc2_2 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER.'_3');
        $rc3_coll = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS_3, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);

        $this->testEntityManager->addResourceActionGrant($rc_1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($rc_2,
            AuthorizationService::MANAGE_ACTION, null, $group2);
        $this->testEntityManager->addResourceActionGrant($rc_2,
            dynamicUserGroupIdentifier: 'students',
            role: $roleUpdater
        );
        $this->testEntityManager->addResourceActionGrant($rc2_1,
            AuthorizationService::MANAGE_ACTION, null, null, 'employees');
        $this->testEntityManager->addResourceActionGrant($rc2_1,
            TestResources::DELETE_ACTION, null, $group1);
        $this->testEntityManager->addResourceActionGrant($rc2_2,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($rc2_2,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER.'_5',
            role: $roleRC3Delete);
        $this->testEntityManager->addResourceActionGrant($rc3_coll,
            AuthorizationService::MANAGE_ACTION, null, $group1);
        $this->testEntityManager->addResourceActionGrant($rc3_coll,
            TestResources::CREATE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($rc3_coll,
            TestResources::CREATE_ACTION, null, null, 'students');

        // ----------------------------------------------------------------
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(3, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS_2, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS_3, $resourceClasses);

        // ----------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS_2, $resourceClasses);

        // ----------------------------------------------------------------
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2', $userAttributes);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS_2, $resourceClasses);

        // ----------------------------------------------------------------
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_3', $userAttributes);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS_3, $resourceClasses);

        // ----------------------------------------------------------------
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_4', $userAttributes);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(3, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS_2, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS_3, $resourceClasses);

        // ----------------------------------------------------------------
        // NOTE: Even though the role is not effective on resource class 2,
        // the user should still be able to read resource class 2 because they have a grant for a resource of that class.
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_5');
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(1, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS_2, $resourceClasses);

        // ----------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_foo');
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(0, $resourceClasses);

        // ----------------------------------------------------------------
        $this->login(null);
        $resourceActionsGrants = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(0, $resourceActionsGrants);
    }

    public function testGetResourceClassesCurrentUserIsAuthorizedToReadWithGroupResources(): void
    {
        $group1 = $this->testEntityManager->addUserGroup();
        $this->testEntityManager->addGroupMember($group1, self::ANOTHER_USER_IDENTIFIER);

        $resource1 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $resource2 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER_2);
        $resourceCollection = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $resourceGroup = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_GROUP_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER);
        $resourceGroup2 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_GROUP_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER.'_2');
        $superResourceGroup = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_GROUP_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER.'_super');

        $this->testEntityManager->addResourceToGroupResource(
            $superResourceGroup->getResourceClass(), $superResourceGroup->getResourceIdentifier(),
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier());

        $this->testEntityManager->addResourceToGroupResource(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resource1->getResourceClass(), $resource1->getResourceIdentifier());
        $this->testEntityManager->addResourceToGroupResource(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resource2->getResourceClass(), $resource2->getResourceIdentifier());

        $this->testEntityManager->addResourceToGroupResource(
            $resourceGroup2->getResourceClass(), $resourceGroup2->getResourceIdentifier(),
            $resource1->getResourceClass(), $resource1->getResourceIdentifier());

        $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1,
            TestResources::READ_ACTION,
            dynamicUserGroupIdentifier: 'employees');
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            AuthorizationService::MANAGE_ACTION, null, $group1);

        $this->testEntityManager->addResourceActionGrant($superResourceGroup,
            TestResources::READ_ACTION, 'big_brother',
            actionResourceClass: TestResources::TEST_RESOURCE_CLASS
        );
        $this->testEntityManager->addResourceActionGrant($resourceGroup,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER.'_2',
        );
        $this->testEntityManager->addResourceActionGrant($resourceGroup2,
            TestResources::READ_ACTION,
            dynamicUserGroupIdentifier: 'students',
            actionResourceClass: TestResources::TEST_RESOURCE_CLASS
        );

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
        $this->assertContains(self::TEST_RESOURCE_GROUP_CLASS, $resourceClasses);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_3', $userAttributes);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_GROUP_CLASS, $resourceClasses);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_4', $userAttributes);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(2, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_GROUP_CLASS, $resourceClasses);

        $this->login(self::ANOTHER_USER_IDENTIFIER.'_foo');
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(0, $resourceClasses);

        // ----------------------------------------------------------------
        $this->login(null);
        $resourceActionsGrants = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(0, $resourceActionsGrants);
    }

    public function testUpdateManageResourceCollectionPolicyGrantsA(): void
    {
        $this->assertNotNull($collectionResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER
        ));

        $this->assertCount(1, $this->testEntityManager->getResourceActionGrants(
            $collectionResource->getIdentifier(), AuthorizationService::MANAGE_ACTION));

        // test path (A): resource class was removed from config, no other grants
        $this->testConfig[Configuration::RESOURCE_CLASSES] = [];
        $this->setUp();

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER
        ));
        $this->assertCount(0, $this->testEntityManager->getResourceActionGrants(
            $collectionResource->getIdentifier(), AuthorizationService::MANAGE_ACTION));
    }

    public function testUpdateManageResourceCollectionPolicyGrantsB(): void
    {
        // test path (B): resource class was removed from config, other grants exist -> collection resource mustn't be deleted
        $this->assertNotNull($collectionResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER));

        $resourceActionGrant = $this->testEntityManager->addResourceActionGrant($collectionResource,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->testConfig[Configuration::RESOURCE_CLASSES] = [];
        $this->setUp();

        $this->assertNotNull($this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER));

        $resourceActionGrants = $this->testEntityManager->getResourceActionGrants(
            $collectionResource->getIdentifier());
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());
    }

    public function testUpdateManageResourceCollectionPolicyGrantsC(): void
    {
        // test path (C): resource class is still in config -> nothing to do
        $this->assertNotNull($collectionResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER));

        // add some noise
        $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrants = $this->testEntityManager->getResourceActionGrants(
            $collectionResource->getIdentifier());
        $this->assertCount(1, $resourceActionGrants);
        $resourceActionGrant = $resourceActionGrants[0];

        $this->setUp();

        $this->assertNotNull($this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER));

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
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER));

        $testResource2CollectionResource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertNotNull($this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER));

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
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER));

        $resourceActionGrants = $this->testEntityManager->getResourceActionGrants(
            $testResource2CollectionResource->getIdentifier());
        $this->assertCount(2, $resourceActionGrants);
    }

    public function testUpdateManageResourceCollectionPolicyGrantsX(): void
    {
        // test path (D) the manage resource collection policy is present in config,
        // the resource collection resource is present in DB, but the policy grant is missing in DB -> auto-add the policy grant to DB
        $this->assertNotNull($testResourceCollectionResource =
            $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
                self::TEST_RESOURCE_CLASS,
                AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER
            ));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER));

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
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER));
        $this->assertNotNull($testResource2CollectionResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER));

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
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER));

        $this->testConfig[Configuration::RESOURCE_CLASSES][] =
            [
                Configuration::IDENTIFIER => self::TEST_RESOURCE_CLASS_2,
                Configuration::MANAGE_RESOURCE_COLLECTION_POLICY => 'user.get("MAY_MANAGE_TEST_RESOURCE_COLLECTION")',
            ];
        $this->setUp();

        $this->assertNotNull($testResource2CollectionResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER));

        $resourceActionGrants = $this->testEntityManager->getResourceActionGrants(
            $testResource2CollectionResource->getIdentifier());
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($testResource2CollectionResource->getIdentifier(), $resourceActionGrants[0]->getAuthorizationResource()->getIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $resourceActionGrants[0]->getAction());
        $this->assertEquals(AuthorizationService::MANAGE_RESOURCE_COLLECTION_POLICY_PREFIX.self::TEST_RESOURCE_CLASS_2, $resourceActionGrants[0]->getDynamicUserGroupIdentifier());
    }

    public function testUpdateManageResourceCollectionPolicyGrantsF(): void
    {
        // test path (F) the resource collection policy was added to config and a childless collection resource is present in the DB
        // -> auto-add the manage collection grant only
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER));

        $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS_2, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);

        $this->testConfig[Configuration::RESOURCE_CLASSES][] =
            [
                Configuration::IDENTIFIER => self::TEST_RESOURCE_CLASS_2,
                Configuration::MANAGE_RESOURCE_COLLECTION_POLICY => 'user.get("MAY_MANAGE_TEST_RESOURCE_COLLECTION")',
            ];
        $this->setUp();

        $this->assertNotNull($testResource2CollectionResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER));

        $resourceActionGrants = $this->testEntityManager->getResourceActionGrants(
            $testResource2CollectionResource->getIdentifier());
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($testResource2CollectionResource->getIdentifier(), $resourceActionGrants[0]->getAuthorizationResource()->getIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $resourceActionGrants[0]->getAction());
        $this->assertEquals(AuthorizationService::MANAGE_RESOURCE_COLLECTION_POLICY_PREFIX.self::TEST_RESOURCE_CLASS_2,
            $resourceActionGrants[0]->getDynamicUserGroupIdentifier());
    }

    public function testAddGroup(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup('Testgroup');
        $manageGroupGrant = $this->authorizationService->addUserGroup($userGroup->getIdentifier());

        $manageGroupGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier(
            $manageGroupGrant->getIdentifier());
        $this->assertEquals($manageGroupGrant->getIdentifier(), $manageGroupGrantPersistence->getIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $manageGroupGrantPersistence->getAction());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $manageGroupGrantPersistence->getUserIdentifier());

        $authorizationResource = $this->testEntityManager->getAuthorizationResourceByIdentifier(
            $manageGroupGrant->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($manageGroupGrant->getAuthorizationResource()->getIdentifier(),
            $authorizationResource->getIdentifier());
        $this->assertEquals($userGroup->getIdentifier(), $authorizationResource->getResourceIdentifier());
        $this->assertEquals(AuthorizationService::GROUP_RESOURCE_CLASS, $authorizationResource->getResourceClass());
    }

    public function testRemoveGroup(): void
    {
        [$userGroup, $manageGroupGrant] = $this->addGroupAndManageGroupGrantForCurrentUser();

        $this->assertNotNull($this->testEntityManager->getAuthorizationResourceByIdentifier(
            $manageGroupGrant->getAuthorizationResource()->getIdentifier()));
        $this->assertNotNull($this->testEntityManager->getResourceActionGrantByIdentifier($manageGroupGrant->getIdentifier()));

        $this->authorizationService->removeUserGroup($userGroup->getIdentifier());

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier(
            $manageGroupGrant->getAuthorizationResource()->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($manageGroupGrant->getIdentifier()));
    }

    public function testIsCurrentUserAuthorizedToAddGroups(): void
    {
        $manageGroupCollectionGrant = $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::GROUP_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
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

        $this->login(null);
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToAddGroups());
    }

    public function testIsCurrentUserAuthorizedToUpdateGroup(): void
    {
        [$userGroup, $manageGroupGrant] = $this->addGroupAndManageGroupGrantForCurrentUser();

        $this->testEntityManager->addResourceActionGrant($manageGroupGrant->getAuthorizationResource(),
            AuthorizationService::UPDATE_GROUP_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToUpdateGroup($userGroup));

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToUpdateGroup($userGroup));

        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToUpdateGroup($userGroup));

        $this->login(null);
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToUpdateGroup($userGroup));
    }

    public function testIsCurrentUserAuthorizedToRemoveGroup(): void
    {
        [$userGroup, $manageGroupGrant] = $this->addGroupAndManageGroupGrantForCurrentUser();

        $this->testEntityManager->addResourceActionGrant($manageGroupGrant->getAuthorizationResource(),
            AuthorizationService::DELETE_GROUP_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToRemoveGroup($userGroup));

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToRemoveGroup($userGroup));

        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToRemoveGroup($userGroup));

        $this->login(null);
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToRemoveGroup($userGroup));
    }

    public function testIsCurrentUserAuthorizedToReadGroup(): void
    {
        [$userGroup, $manageGroupGrant] = $this->addGroupAndManageGroupGrantForCurrentUser();

        $this->testEntityManager->addResourceActionGrant($manageGroupGrant->getAuthorizationResource(),
            AuthorizationService::READ_GROUP_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToReadGroup($userGroup));

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToReadGroup($userGroup));

        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToReadGroup($userGroup));

        $this->login(null);
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToReadGroup($userGroup));
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
