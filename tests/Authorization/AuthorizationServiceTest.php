<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Authorization;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\AuthorizationBundle\Entity\GrantedActions;
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
                action: AuthorizationService::MANAGE_ACTION,
                userIdentifier: self::CURRENT_USER_IDENTIFIER);
            $this->fail('Expected ApiError to be thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
        }

        try {
            $this->authorizationService->addResourceActionGrant(
                self::TEST_RESOURCE_CLASS, 'foo'.UserAttributeProvider::SEPARATOR.'bar',
                action: AuthorizationService::MANAGE_ACTION,
                userIdentifier: self::CURRENT_USER_IDENTIFIER);
            $this->fail('Expected ApiError to be thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
        }
    }

    public function testManageResourceCollectionPolicy(): void
    {
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertNull($grantedActions);

        $attributes = $this->getDefaultUserAttributes();
        $attributes['MAY_MANAGE_TEST_RESOURCE_COLLECTION'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $attributes);

        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        $availableResourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertEquals([self::TEST_RESOURCE_CLASS], $availableResourceClasses);

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
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        // ----------------------------------------------------------------
        // user 2:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');

        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([TestResources::READ_ACTION], $grantedActions->getActions());

        // ----------------------------------------------------------------
        // user 3:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');

        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::UPDATE_ACTION,
            TestResources::READ_ACTION], $grantedActions->getActions());

        // ----------------------------------------------------------------
        // user 4:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_4', $userAttributes);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::UPDATE_ACTION,
            TestResources::READ_ACTION], $grantedActions->getActions());

        // ----------------------------------------------------------------
        // user 5:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_5', $userAttributes);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
            TestResources::DELETE_ACTION,
            TestResources::UPDATE_ACTION], $grantedActions->getActions());

        // ----------------------------------------------------------------
        // user 6:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_6');
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::DELETE_ACTION,
            TestResources::READ_ACTION], $grantedActions->getActions());

        // ----------------------------------------------------------------
        // user 7:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_7', $userAttributes);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
            TestResources::DELETE_ACTION,
            TestResources::UPDATE_ACTION], $grantedActions->getActions());

        // ----------------------------------------------------------------
        // user 8:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_8');
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([TestResources::READ_ACTION], $grantedActions->getActions());

        $this->login(userIdentifier: null);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([TestResources::READ_ACTION], $grantedActions->getActions());
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

        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertNull($grantedActions);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertNull($grantedActions);

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
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        // any action:
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        // any action:
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS.'_2',
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS.'_2', $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        // ----------------------------------------------------------------
        // user 2:
        // manage action:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');

        // any action:
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS_2, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([TestResources::CREATE_ACTION], $grantedActions->getActions());

        // ----------------------------------------------------------------
        // user 3:
        // manage action:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');

        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS_2, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertCount(2, $grantedActions->getActions());
        $this->assertContains(TestResources::CREATE_ACTION, $grantedActions->getActions());
        $this->assertContains(TestResources::READ_ACTION, $grantedActions->getActions());

        // ----------------------------------------------------------------
        // user 4:
        // manage action:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_4', $userAttributes);

        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS_2, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([TestResources::DELETE_ALL_ACTION], $grantedActions->getActions());

        // ----------------------------------------------------------------
        // user 5:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_5', $userAttributes);

        // delete action: user 5 has one personal grant and one grant via dynamic group 'employees'
        // -> expecting only 1 grant, since only unique resource actions should be returned
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS_2, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertContains(TestResources::READ_ACTION, $grantedActions->getActions());
        $this->assertContains(TestResources::CREATE_ACTION, $grantedActions->getActions());
        $this->assertContains(TestResources::DELETE_ALL_ACTION, $grantedActions->getActions());

        // any action:
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS_2,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS_2, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertCount(3, $grantedActions->getActions());
        $this->assertContains(TestResources::READ_ACTION, $grantedActions->getActions());
        $this->assertContains(TestResources::CREATE_ACTION, $grantedActions->getActions());
        $this->assertContains(TestResources::DELETE_ALL_ACTION, $grantedActions->getActions());

        // ----------------------------------------------------------------
        $this->login(null);
        $this->assertNull($this->authorizationService->getGrantedActionsForCurrentUser(
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

        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER
        );
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf(
            [TestResources::READ_ACTION, TestResources::WRITE_ACTION, TestResources::DELETE_ACTION, TestResources::UPDATE_ACTION],
            $grantedActions->getActions()
        );

        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            TestResources::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER
        );
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf(
            [TestResources::CREATE_ACTION, TestResources::READ_ACTION, TestResources::UPDATE_ACTION],
            $grantedActions->getActions());
    }

    public function testGetGrantedResourceActionsForCurrentUserWithGroupResources(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER
        );
        $resourceGroup = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE
        );

        $this->testEntityManager->addResourceToResourceGroup(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resource->getResourceIdentifier());

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
            action: AuthorizationService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource,
            action: TestResources::UPDATE_ACTION,
            userGroup: $group1);
        $this->testEntityManager->addResourceActionGrant($resource,
            dynamicUserGroupIdentifier: 'everybody',
            role: $roleReader);

        $this->testEntityManager->addResourceActionGrant($resourceGroup,
            dynamicUserGroupIdentifier: 'employees',
            role: $roleWriter
        );
        $this->testEntityManager->addResourceActionGrant($resourceGroup,
            action: AuthorizationService::MANAGE_ACTION,
            userIdentifier: 'admin'
        );
        $this->testEntityManager->addResourceActionGrant($resourceGroup,
            action: TestResources::WRITE_ACTION,
            userGroup: $group2
        );

        // add some noise:
        $resource2 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER_2);
        $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, 'somebody_else');

        // ----------------------------------------------------------------
        // current user:
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertNull($grantedActions);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertNull($grantedActions);

        // ----------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
            TestResources::UPDATE_ACTION,
        ], $grantedActions->getActions());
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertNull($grantedActions);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertNull($grantedActions);

        // ----------------------------------------------------------------
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
            TestResources::WRITE_ACTION,
        ], $grantedActions->getActions());
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertNull($grantedActions);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_GROUP_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::WRITE_ACTION,
        ], $grantedActions->getActions());

        // ----------------------------------------------------------------
        $this->login('admin');
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_GROUP_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            AuthorizationService::MANAGE_ACTION,
        ], $grantedActions->getActions());

        // ----------------------------------------------------------------
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login('some_employee', $userAttributes);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
            TestResources::WRITE_ACTION,
            TestResources::DELETE_ACTION,
        ], $grantedActions->getActions());
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertNull($grantedActions);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_GROUP_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::WRITE_ACTION,
            TestResources::DELETE_ACTION,
        ], $grantedActions->getActions());

        // ----------------------------------------------------------------
        // user 5:
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login('some_student', $userAttributes);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
        ], $grantedActions->getActions());
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertNull($grantedActions);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertNull($grantedActions);

        // ----------------------------------------------------------------
        $this->login('somebody_else');
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
        ], $grantedActions->getActions());
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertNull($grantedActions);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertNull($grantedActions);

        // ----------------------------------------------------------------
        $this->login('everybody_user');
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
        ], $grantedActions->getActions());
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertNull($grantedActions);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertNull($grantedActions);

        // ----------------------------------------------------------------
        $this->login(userIdentifier: null);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
        ], $grantedActions->getActions());
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertNull($grantedActions);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertNull($grantedActions);
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
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE
        );

        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([TestResources::CREATE_ACTION], $grantedActions->getActions());
        $this->assertNull($this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE
        ));

        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([ResourceActionGrantService::MANAGE_ACTION], $grantedActions->getActions());
        $this->assertNull($this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE
        ));

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');

        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([TestResources::CREATE_ACTION], $grantedActions->getActions());
        $this->assertNull($this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE
        ));

        $this->testEntityManager->addResourceToResourceGroup(
            $resourceCollection->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resourceCollection->getResourceIdentifier());

        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([TestResources::CREATE_ACTION], $grantedActions->getActions());
        $this->assertNull($this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE
        ));

        $this->testEntityManager->addResourceActionGrant($resourceGroup,
            action: TestResources::UPDATE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER.'_2'
        );

        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::CREATE_ACTION,
            TestResources::UPDATE_ACTION,
        ], $grantedActions->getActions());
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::UPDATE_ACTION,
        ], $grantedActions->getActions());

        $this->testEntityManager->addResourceActionGrant($resourceGroup,
            userIdentifier: self::CURRENT_USER_IDENTIFIER.'_2',
            role: $roleReadAll
        );

        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::CREATE_ACTION,
            TestResources::READ_ACTION,
            TestResources::UPDATE_ACTION,
        ], $grantedActions->getActions());
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertIsPermutationOf([
            TestResources::READ_ACTION,
            TestResources::UPDATE_ACTION,
        ], $grantedActions->getActions());

        $this->login(userIdentifier: null);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([TestResources::CREATE_ACTION], $grantedActions->getActions());
        $this->assertNull($this->authorizationService->getGrantedActionsForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE
        ));
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
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: AuthorizationService::MANAGE_ACTION
        );
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) use ($resource) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === $resource->getResourceIdentifier();
        }));

        // any action
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) use ($resource) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === $resource->getResourceIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) use ($resource_2) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === $resource_2->getResourceIdentifier();
        }));

        // unavailable action
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: 'foo');
        $this->assertCount(0, $usersResourceActions);

        // ----------------------------------------------------------------
        // user 2:
        // manage action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: AuthorizationService::MANAGE_ACTION);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }));

        // any action
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
        }));

        // ----------------------------------------------------------------
        // user 3:
        // manage action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: AuthorizationService::MANAGE_ACTION);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }));

        // any action
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(4, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::UPDATE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }));

        // test pagination (page size 3):
        $userResourceActionPage1 = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 0, maxNumResults: 3);
        $this->assertCount(3, $userResourceActionPage1);
        $userResourceActionPage2 = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 3, maxNumResults: 3);
        $this->assertCount(1, $userResourceActionPage2);

        $usersResourceActions = array_merge($userResourceActionPage1, $userResourceActionPage2);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::UPDATE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }));

        // ----------------------------------------------------------------
        // user 4:
        // manage action
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_4', $userAttributes);
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: AuthorizationService::MANAGE_ACTION);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }));

        // any action
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(4, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::UPDATE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }));

        // test pagination (page size 2):
        $userResourceActionPage1 = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 0, maxNumResults: 2);
        $this->assertCount(2, $userResourceActionPage1);
        $userResourceActionPage2 = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 2, maxNumResults: 2);
        $this->assertCount(2, $userResourceActionPage2);
        $userResourceActionPage3 = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 4, maxNumResults: 2);
        $this->assertCount(0, $userResourceActionPage3);

        $usersResourceActions = array_merge($userResourceActionPage1, $userResourceActionPage2);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::UPDATE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }));

        // ----------------------------------------------------------------
        // user 5 (student):
        // manage action
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_5', $userAttributes);
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: AuthorizationService::MANAGE_ACTION);
        $this->assertCount(0, $usersResourceActions);

        // read action
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: TestResources::READ_ACTION);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }));

        // test pagination (page size 1):
        $userResourceActionPage1 = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 0, maxNumResults: 1);
        $this->assertCount(1, $userResourceActionPage1);

        // test pagination (page size 0):
        $userResourceActionPage1 = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 0, maxNumResults: 0);
        $this->assertCount(0, $userResourceActionPage1);

        // ----------------------------------------------------------------
        // user 6:
        // any action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_6');
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(0, $usersResourceActions);

        // ----------------------------------------------------------------
        $this->login(userIdentifier: null);
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(0, $usersResourceActions);
    }

    public function testGetGrantedResourceActionsPageForCurrentUserWithGroupResourcesMany(): void
    {
        $resourceGroup = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE
        );
        $this->testEntityManager->addResourceActionGrant($resourceGroup,
            TestResources::READ_ACTION, 'controller_user'
        );

        $numResources = 1000;
        for ($i = 0; $i < $numResources; ++$i) {
            $resource = $this->testEntityManager->addAuthorizationResource(
                TestResources::TEST_RESOURCE_CLASS, (string) $i
            );
            $this->testEntityManager->addResourceToResourceGroup(
                $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(), $resource->getResourceIdentifier());
            $this->testEntityManager->addResourceActionGrant($resource,
                TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
            $this->testEntityManager->addResourceActionGrant($resource,
                TestResources::UPDATE_ACTION, self::CURRENT_USER_IDENTIFIER);
            $this->testEntityManager->addResourceActionGrant($resource,
                TestResources::DELETE_ACTION, self::CURRENT_USER_IDENTIFIER);
            $this->testEntityManager->getEntityManager()->clear();
        }

        $grantedResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            maxNumResults: 2 * $numResources);

        $this->assertCount($numResources, $grantedResourceActions);
        $identifiersReturned = [];
        foreach ($grantedResourceActions as $grantedActions) {
            $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
            $this->assertNotContains($grantedActions->getResourceIdentifier(), $identifiersReturned);
            $identifiersReturned[] = $grantedActions->getResourceIdentifier();
            $this->assertIsPermutationOf([
                TestResources::READ_ACTION,
                TestResources::UPDATE_ACTION,
                TestResources::DELETE_ACTION,
            ], $grantedActions->getActions());
        }

        // test pagination:
        $maxNumResults = 400;
        $grantedResourceActionsPage1 = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            firstResultIndex: 0,
            maxNumResults: $maxNumResults);
        $this->assertCount($maxNumResults, $grantedResourceActionsPage1);
        $grantedResourceActionsPage2 = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            firstResultIndex: $maxNumResults,
            maxNumResults: $maxNumResults);
        $this->assertCount($maxNumResults, $grantedResourceActionsPage2);
        $grantedResourceActionsPage3 = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            firstResultIndex: 2 * $maxNumResults,
            maxNumResults: $maxNumResults);
        $this->assertCount($numResources - (2 * $maxNumResults), $grantedResourceActionsPage3);

        $grantedResourceActions = array_merge($grantedResourceActionsPage1, $grantedResourceActionsPage2, $grantedResourceActionsPage3);
        $this->assertCount($numResources, $grantedResourceActions);

        $identifiersReturned = [];
        foreach ($grantedResourceActions as $grantedActions) {
            $this->assertEquals(self::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
            $this->assertNotContains($grantedActions->getResourceIdentifier(), $identifiersReturned);
            $identifiersReturned[] = $grantedActions->getResourceIdentifier();
            $this->assertIsPermutationOf([
                TestResources::READ_ACTION,
                TestResources::UPDATE_ACTION,
                TestResources::DELETE_ACTION,
            ], $grantedActions->getActions());
        }
    }

    public function testGetGrantedActionsCollectionForCurrentUserWithGroupResources(): void
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
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE
        );
        $this->testEntityManager->addResourceActionGrant($resourceGroup,
            TestResources::READ_ACTION, 'controller_user'
        );

        // managed by user
        // readable by user 2
        // readable by user 3
        // readable by user 4
        $resource = $this->testEntityManager->addAuthorizationResource(TestResources::TEST_RESOURCE_CLASS);
        $this->testEntityManager->addResourceToResourceGroup(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(), $resource->getResourceIdentifier());
        $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource,
            action: TestResources::READ_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceActionGrant($resource,
            action: TestResources::READ_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER.'_3');
        $this->testEntityManager->addResourceActionGrant($resource,
            action: TestResources::READ_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER.'_4');

        // managed by user 2
        // reviewable (read, update) by user
        // writable by group 'Testgroup'
        $resource_2 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceToResourceGroup(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(), $resource_2->getResourceIdentifier());
        $this->testEntityManager->addResourceActionGrant($resource_2,
            action: AuthorizationService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addResourceActionGrant($resource_2,
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
            role: $roleReviewer);
        $this->testEntityManager->addResourceActionGrant($resource_2,
            action: TestResources::UPDATE_ACTION,
            userGroup: $testGroup);

        // managed by user 3
        // writable by dynamic group 'employees'
        $resource_3 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_3');
        $this->testEntityManager->addResourceToResourceGroup(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(), $resource_3->getResourceIdentifier());
        $this->testEntityManager->addResourceActionGrant($resource_3,
            action: AuthorizationService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER.'_3');
        $this->testEntityManager->addResourceActionGrant($resource_3,
            action: TestResources::UPDATE_ACTION,
            dynamicUserGroupIdentifier: 'employees');

        // managed by group 'Testgroup'
        // readable by user 4
        $resource_4 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_4');
        $this->testEntityManager->addResourceToResourceGroup(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(), $resource_4->getResourceIdentifier());
        $this->testEntityManager->addResourceActionGrant($resource_4,
            action: AuthorizationService::MANAGE_ACTION,
            userGroup: $testGroup);
        $this->testEntityManager->addResourceActionGrant($resource_4,
            action: TestResources::READ_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER.'_4');

        // managed by dynamic group 'employees'
        // reviewable (read, update) and deletable by dynamic group 'students'
        $resource_5 = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_5');
        $this->testEntityManager->addResourceToResourceGroup(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(), $resource_5->getResourceIdentifier());
        $this->testEntityManager->addResourceActionGrant($resource_5,
            action: AuthorizationService::MANAGE_ACTION,
            dynamicUserGroupIdentifier: 'employees');
        $this->testEntityManager->addResourceActionGrant($resource_5,
            dynamicUserGroupIdentifier: 'students',
            role: $roleReviewer
        );
        $this->testEntityManager->addResourceActionGrant($resource_5,
            action: TestResources::DELETE_ACTION,
            dynamicUserGroupIdentifier: 'students'
        );

        $collectionResource = $this->testEntityManager->addAuthorizationResourceAndActionGrant(TestResources::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            action: AuthorizationService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );

        // add some noise:
        $resource_foo = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER.'_foo');
        $this->testEntityManager->addResourceActionGrant($resource_foo,
            action: AuthorizationService::MANAGE_ACTION,
            dynamicUserGroupIdentifier: 'foo');
        $this->testEntityManager->addResourceActionGrant($resource_foo,
            action: TestResources::READ_ACTION,
            dynamicUserGroupIdentifier: 'bar');

        // ----------------------------------------------------------------
        // user:
        // manage action
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: AuthorizationService::MANAGE_ACTION
        );
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) use ($resource) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === $resource->getResourceIdentifier();
        }));
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            resourceType: AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertCount(0, $usersResourceActions);

        // any action
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) use ($resource) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === $resource->getResourceIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) use ($resource_2) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $this->isPermutationOf([TestResources::READ_ACTION, TestResources::UPDATE_ACTION], $grantedActions->getActions())
                && $grantedActions->getResourceIdentifier() === $resource_2->getResourceIdentifier();
        }));
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            resourceType: AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertCount(0, $usersResourceActions);

        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            excludeCollectionResources: false
        );
        $this->assertCount(3, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions,
            function (GrantedActions $grantedActions) use ($collectionResource) {
                return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                    && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                    && $grantedActions->getResourceIdentifier() === $collectionResource->getResourceIdentifier();
            })
        );
        $this->assertCount(1, $this->selectWhere($usersResourceActions,
            function (GrantedActions $grantedActions) use ($resource) {
                return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                    && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                    && $grantedActions->getResourceIdentifier() === $resource->getResourceIdentifier()
                    && $grantedActions->getResourceType() === AuthorizationService::RESOURCE_RESOURCE_TYPE;
            }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions,
            function (GrantedActions $grantedActions) use ($resource_2) {
                return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                    && $this->isPermutationOf([TestResources::READ_ACTION, TestResources::UPDATE_ACTION], $grantedActions->getActions())
                    && $grantedActions->getResourceIdentifier() === $resource_2->getResourceIdentifier()
                    && $grantedActions->getResourceType() === AuthorizationService::RESOURCE_RESOURCE_TYPE;
            }));
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: AuthorizationService::MANAGE_ACTION,
            excludeCollectionResources: false
        );
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions,
            function (GrantedActions $grantedActions) use ($collectionResource) {
                return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                    && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                    && $grantedActions->getResourceIdentifier() === $collectionResource->getResourceIdentifier()
                    && $grantedActions->getResourceType() === AuthorizationService::RESOURCE_RESOURCE_TYPE;
            })
        );
        $this->assertCount(1, $this->selectWhere($usersResourceActions,
            function (GrantedActions $grantedActions) use ($resource) {
                return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                    && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                    && $grantedActions->getResourceIdentifier() === $resource->getResourceIdentifier()
                    && $grantedActions->getResourceType() === AuthorizationService::RESOURCE_RESOURCE_TYPE;
            }));

        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: TestResources::CREATE_ACTION,
            excludeCollectionResources: false
        );
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions,
            function (GrantedActions $grantedActions) use ($collectionResource) {
                return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                    && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                    && $grantedActions->getResourceIdentifier() === $collectionResource->getResourceIdentifier()
                    && $grantedActions->getResourceType() === AuthorizationService::RESOURCE_RESOURCE_TYPE;
            })
        );

        // ----------------------------------------------------------------
        // user 2:
        // manage action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: AuthorizationService::MANAGE_ACTION);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }));
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            resourceType: AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
            whereIsGrantedAction: AuthorizationService::MANAGE_ACTION
        );
        $this->assertCount(0, $usersResourceActions);

        // any action
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
        }));
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            resourceType: AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE
        );
        $this->assertCount(0, $usersResourceActions);

        // ----------------------------------------------------------------
        // user 3:
        // manage action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: AuthorizationService::MANAGE_ACTION);
        $this->assertCount(2, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }));
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            resourceType: AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
            whereIsGrantedAction: AuthorizationService::MANAGE_ACTION
        );
        $this->assertCount(0, $usersResourceActions);

        // any action
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(4, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::UPDATE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }));
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            resourceType: AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
        );
        $this->assertCount(0, $usersResourceActions);

        // test pagination (page size 3):
        $userResourceActionPage1 = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 0, maxNumResults: 3);
        $this->assertCount(3, $userResourceActionPage1);
        $userResourceActionPage2 = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 3, maxNumResults: 3);
        $this->assertCount(1, $userResourceActionPage2);

        $usersResourceActions = array_merge($userResourceActionPage1, $userResourceActionPage2);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::UPDATE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }));

        // ----------------------------------------------------------------
        // user 4:
        // manage action
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_4', $userAttributes);
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: AuthorizationService::MANAGE_ACTION);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }));
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            resourceType: AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
            whereIsGrantedAction: AuthorizationService::MANAGE_ACTION
        );
        $this->assertCount(0, $usersResourceActions);

        // any action
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(4, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::UPDATE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }));
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            resourceType: AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
        );
        $this->assertCount(0, $usersResourceActions);

        // test pagination (page size 2):
        $userResourceActionPage1 = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 0, maxNumResults: 2);
        $this->assertCount(2, $userResourceActionPage1);
        $userResourceActionPage2 = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 2, maxNumResults: 2);
        $this->assertCount(2, $userResourceActionPage2);
        $userResourceActionPage3 = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 4, maxNumResults: 2);
        $this->assertCount(0, $userResourceActionPage3);

        $usersResourceActions = array_merge($userResourceActionPage1, $userResourceActionPage2);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::UPDATE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }));

        // ----------------------------------------------------------------
        // user 5 (student):
        // manage action
        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER.'_5', $userAttributes);
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: AuthorizationService::MANAGE_ACTION);
        $this->assertCount(0, $usersResourceActions);
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            resourceType: AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
            whereIsGrantedAction: AuthorizationService::MANAGE_ACTION
        );
        $this->assertCount(0, $usersResourceActions);

        // read action
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: TestResources::READ_ACTION);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $this->isPermutationOf([TestResources::READ_ACTION, TestResources::UPDATE_ACTION, TestResources::DELETE_ACTION], $grantedActions->getActions())
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }));
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            resourceType: AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
            whereIsGrantedAction: TestResources::READ_ACTION
        );
        $this->assertCount(0, $usersResourceActions);

        // test pagination (page size 1):
        $userResourceActionPage1 = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 0, maxNumResults: 1);
        $this->assertCount(1, $userResourceActionPage1);

        // test pagination (page size 0):
        $userResourceActionPage1 = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS, firstResultIndex: 0, maxNumResults: 0);
        $this->assertCount(0, $userResourceActionPage1);

        // ----------------------------------------------------------------
        // user 6:
        // any action
        $this->login(self::CURRENT_USER_IDENTIFIER.'_6');
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(0, $usersResourceActions);
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            resourceType: AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
        );
        $this->assertCount(0, $usersResourceActions);

        // ----------------------------------------------------------------
        $this->login('controller_user');
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(5, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_2';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_3';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_4';
        }));
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER.'_5';
        }));
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            resourceType: AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->assertCount(1, $usersResourceActions);
        $this->assertCount(1, $this->selectWhere($usersResourceActions, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [TestResources::READ_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_GROUP_IDENTIFIER
                && $grantedActions->getResourceType() === AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE;
        }));

        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: AuthorizationService::MANAGE_ACTION
        );
        $this->assertCount(0, $usersResourceActions);
        $usersResourceActions = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
            resourceType: AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
            whereIsGrantedAction: AuthorizationService::MANAGE_ACTION
        );
        $this->assertCount(0, $usersResourceActions);

        // ----------------------------------------------------------------
        $this->login(null);
        $this->assertEmpty($this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS));
    }

    public function testGetGrantedResourceActionsPageForCurrentUserWithCommonResourceAttributes(): void
    {
        $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            action: AuthorizationService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );
        $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            self::TEST_RESOURCE_CLASS_2,
            self::TEST_RESOURCE_IDENTIFIER,
            action: AuthorizationService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );
        $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            resourceType: AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
            action: AuthorizationService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );
        $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            self::TEST_RESOURCE_CLASS_2,
            self::TEST_RESOURCE_IDENTIFIER_2,
            resourceType: AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
            action: AuthorizationService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );

        $grantedActionsCollection = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            self::TEST_RESOURCE_CLASS,
        );
        $this->assertCount(1, $grantedActionsCollection);
        $this->assertCount(1, $this->selectWhere($grantedActionsCollection, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
        }));

        $grantedActionsCollection = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            resourceIdentifier: self::TEST_RESOURCE_IDENTIFIER
        );
        $this->assertCount(2, $grantedActionsCollection);
        $this->assertCount(1, $this->selectWhere($grantedActionsCollection, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
        }));
        $this->assertCount(1, $this->selectWhere($grantedActionsCollection, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS_2
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER;
        }));

        $grantedActionsCollection = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            resourceIdentifier: self::TEST_RESOURCE_IDENTIFIER,
            resourceType: null
        );
        $this->assertCount(3, $grantedActionsCollection);
        $this->assertCount(1, $this->selectWhere($grantedActionsCollection, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER
                && $grantedActions->getResourceType() === AuthorizationService::RESOURCE_RESOURCE_TYPE;
        }));
        $this->assertCount(1, $this->selectWhere($grantedActionsCollection, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS_2
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER
                && $grantedActions->getResourceType() === AuthorizationService::RESOURCE_RESOURCE_TYPE;
        }));
        $this->assertCount(1, $this->selectWhere($grantedActionsCollection, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER
                && $grantedActions->getResourceType() === AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE;
        }));

        $grantedActionsCollection = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            resourceType: AuthorizationService::RESOURCE_RESOURCE_TYPE
        );
        $this->assertCount(2, $grantedActionsCollection);
        $this->assertCount(1, $this->selectWhere($grantedActionsCollection, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER
                && $grantedActions->getResourceType() === AuthorizationService::RESOURCE_RESOURCE_TYPE;
        }));
        $this->assertCount(1, $this->selectWhere($grantedActionsCollection, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS_2
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER
                && $grantedActions->getResourceType() === AuthorizationService::RESOURCE_RESOURCE_TYPE;
        }));

        $grantedActionsCollection = $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            resourceType: AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE
        );
        $this->assertCount(2, $grantedActionsCollection);
        $this->assertCount(1, $this->selectWhere($grantedActionsCollection, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER
                && $grantedActions->getResourceType() === AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE;
        }));
        $this->assertCount(1, $this->selectWhere($grantedActionsCollection, function (GrantedActions $grantedActions) {
            return $grantedActions->getResourceClass() === self::TEST_RESOURCE_CLASS_2
                && $grantedActions->getActions() === [AuthorizationService::MANAGE_ACTION]
                && $grantedActions->getResourceIdentifier() === self::TEST_RESOURCE_IDENTIFIER_2
                && $grantedActions->getResourceType() === AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE;
        }));
    }

    public function testIsCurrentUserGrantedItemActionWithManageGrant(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            'foo'));

        $this->testEntityManager->addResourceActionGrant($resource,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertTrue($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertTrue($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION, ResourceActionGrantService::RESOURCE_RESOURCE_TYPE));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION, ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE));
        $this->assertTrue($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            'foo'));

        $resourceGroup = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER,
            ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->testEntityManager->addResourceActionGrant($resourceGroup,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertTrue($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION, ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION, ResourceActionGrantService::RESOURCE_RESOURCE_TYPE));

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            'foo'));
    }

    public function testIsCurrentUserGrantedItemActionWithReadGrant(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            'foo'));

        $this->testEntityManager->addResourceActionGrant($resource,
            TestResources::READ_ACTION,
            self::CURRENT_USER_IDENTIFIER);

        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertTrue($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION, ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            'foo'));

        $resourceGroup = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER,
            ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->testEntityManager->addResourceActionGrant($resourceGroup,
            TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertTrue($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER,
            TestResources::READ_ACTION, ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER,
            TestResources::READ_ACTION, ResourceActionGrantService::RESOURCE_RESOURCE_TYPE));

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            'foo'));
    }

    public function testIsCurrentUserGrantedCollectionActionWithManageGrant(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);

        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            TestResources::CREATE_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            'foo'));

        $this->testEntityManager->addResourceActionGrant($resource,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertTrue($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertTrue($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            TestResources::CREATE_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            TestResources::CREATE_ACTION, ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            'foo'));

        $collectionResourceGroup = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER,
            ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->testEntityManager->addResourceActionGrant($collectionResourceGroup,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->assertTrue($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION, ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION, ResourceActionGrantService::RESOURCE_RESOURCE_TYPE));

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            TestResources::CREATE_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            'foo'));
    }

    public function testIsCurrentUserGrantedCollectionActionWithCreateGrant(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);

        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            TestResources::CREATE_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            'foo'));

        $this->testEntityManager->addResourceActionGrant($resource,
            TestResources::CREATE_ACTION,
            self::CURRENT_USER_IDENTIFIER);

        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertTrue($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            TestResources::CREATE_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            TestResources::CREATE_ACTION, ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            'foo'));

        $collectionResourceGroup = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE);
        $this->testEntityManager->addResourceActionGrant($collectionResourceGroup,
            TestResources::CREATE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertTrue($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            TestResources::CREATE_ACTION, ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE));

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION));
        $this->assertFalse($this->authorizationService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            'foo'));
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
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

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

        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS, $userGroup->getIdentifier());
        $this->assertEquals(AuthorizationService::GROUP_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals($userGroup->getIdentifier(), $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS, $userGroup->getIdentifier());
        $this->assertEquals(AuthorizationService::GROUP_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals($userGroup->getIdentifier(), $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::ADD_GROUP_MEMBERS_GROUP_ACTION], $grantedActions->getActions());
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

        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertNull($grantedActions);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['MAY_CREATE_GROUPS'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $userAttributes);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals(AuthorizationService::GROUP_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        $groupCollectionResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            AuthorizationService::GROUP_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);

        $this->testEntityManager->addResourceActionGrant($groupCollectionResource,
            action: AuthorizationService::CREATE_GROUPS_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($groupCollectionResource,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER,
            role: $roleGroupCreator);

        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals(AuthorizationService::GROUP_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());

        // ----------------------------------------------------------------
        // another user:
        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals(AuthorizationService::GROUP_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([AuthorizationService::CREATE_GROUPS_ACTION], $grantedActions->getActions());

        // ----------------------------------------------------------------
        // user 3:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertNull($grantedActions);

        // ----------------------------------------------------------------
        $this->login(null);
        $grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser(
            AuthorizationService::GROUP_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertNull($grantedActions);
    }

    public function testGetDynamicGroupsCurrentUserIsAuthorizedToRead(): void
    {
        $dynamicGroups = $this->authorizationService->getDynamicGroupsCurrentUserIsAuthorizedToRead();
        $this->assertCount(3, $dynamicGroups);
        $this->assertContains('students', $dynamicGroups);
        $this->assertContains('employees', $dynamicGroups);
        $this->assertContains('everybody', $dynamicGroups);
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
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE
        );

        $this->testEntityManager->addResourceToResourceGroup(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resource1->getResourceIdentifier());

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

        // NOTE: remove the already existing collection resource and manage resource collection grant (automatically created)
        $this->internalResourceActionGrantService->removeAuthorizationResourcesByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $resourceCollection = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $resourceGroup = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE
        );
        $resourceGroup2 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER.'_2',
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE
        );
        $collectionResourceGroup = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE
        );
        $superResourceGroup = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER.'_super',
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE
        );

        // grandparent -> parent
        $this->testEntityManager->addResourceToResourceGroup(
            $superResourceGroup->getResourceClass(), $superResourceGroup->getResourceIdentifier(),
            $resourceGroup->getResourceIdentifier(), AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);

        // parent -> child (resource item)
        $this->testEntityManager->addResourceToResourceGroup(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resource1->getResourceIdentifier());

        // parent -> child (resource item)
        $this->testEntityManager->addResourceToResourceGroup(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resource2->getResourceIdentifier());

        // parent -> child
        $this->testEntityManager->addResourceToResourceGroup(
            $resourceGroup2->getResourceClass(), $resourceGroup2->getResourceIdentifier(),
            $resource1->getResourceIdentifier());

        // parent -> child (resource collection)
        $this->testEntityManager->addResourceToResourceGroup(
            $collectionResourceGroup->getResourceClass(), $collectionResourceGroup->getResourceIdentifier(),
            $resourceCollection->getResourceIdentifier());

        $rag_1_manage = $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $rag_1_read = $this->testEntityManager->addResourceActionGrant($resource1,
            TestResources::READ_ACTION, dynamicUserGroupIdentifier: 'employees');
        $rag_2_manage = $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, dynamicUserGroupIdentifier: 'students');
        $rag_coll_manage = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            AuthorizationService::MANAGE_ACTION,
            userGroup: $group1);

        $rag_super_res_group_read = $this->testEntityManager->addResourceActionGrant($superResourceGroup,
            action: TestResources::READ_ACTION,
            userIdentifier: 'big_brother'
        );
        $rag_res_group_1_manage = $this->testEntityManager->addResourceActionGrant($resourceGroup,
            action: AuthorizationService::MANAGE_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER.'_2');
        $rag_res_group_2_read = $this->testEntityManager->addResourceActionGrant($resourceGroup2,
            TestResources::READ_ACTION,
            dynamicUserGroupIdentifier: 'students'
        );
        $rag_coll_res_group_create = $this->testEntityManager->addResourceActionGrant($collectionResourceGroup,
            TestResources::CREATE_ACTION,
            userGroup: $group1
        );

        $this->login(self::CURRENT_USER_IDENTIFIER);
        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead();
        $this->assertCount(5, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manage, ['delete']);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read, ['delete']);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_res_group_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_res_group_1_manage, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_res_group_2_read, $resource1);

        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(self::TEST_RESOURCE_CLASS);
        $this->assertCount(5, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manage, ['delete']);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read, ['delete']);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_res_group_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_res_group_1_manage, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_res_group_2_read, $resource1);

        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(5, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manage, ['delete']);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read, ['delete']);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_res_group_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_res_group_1_manage, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_res_group_2_read, $resource1);

        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(self::TEST_RESOURCE_GROUP_CLASS);
        $this->assertCount(0, $rags);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead();
        $this->assertCount(3, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_manage, ['delete']);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_res_group_create, []);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_res_group_create, $resourceCollection);

        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(self::TEST_RESOURCE_CLASS);
        $this->assertCount(3, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_manage, ['delete']);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_res_group_create, []);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_res_group_create, $resourceCollection);

        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertCount(3, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_manage, ['delete']);
        $this->assertContainsResourceActionGrant($rags, $rag_coll_res_group_create, []);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_coll_res_group_create, $resourceCollection);

        $this->login('big_brother');
        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead();
        $this->assertCount(4, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_super_res_group_read, []);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_res_group_read, $resourceGroup);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_res_group_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_res_group_read, $resource2);

        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');
        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead();
        $this->assertCount(10, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_res_group_1_manage, ['delete']);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_res_group_1_manage, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_res_group_1_manage, $resource2);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_res_group_2_read, $resource1);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manage, ['delete']);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read, ['delete']);
        $this->assertContainsResourceActionGrant($rags, $rag_2_manage, ['delete']);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_res_group_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_res_group_read, $resource2);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_res_group_read, $resourceGroup);

        // test pagination:
        $ragPage1 = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(
            firstResultIndex: 0, maxNumResults: 6);
        $this->assertCount(6, $ragPage1);
        $ragPage2 = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(
            firstResultIndex: 6, maxNumResults: 6);
        $this->assertCount(4, $ragPage2);
        $rags = array_merge($ragPage1, $ragPage2);
        $this->assertCount(10, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_res_group_1_manage, ['delete']);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_res_group_1_manage, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_res_group_1_manage, $resource2);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_res_group_2_read, $resource1);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manage, ['delete']);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read, ['delete']);
        $this->assertContainsResourceActionGrant($rags, $rag_2_manage, ['delete']);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_res_group_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_res_group_read, $resource2);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_res_group_read, $resourceGroup);

        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(5, $rags);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_res_group_1_manage, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_res_group_2_read, $resource1);
        $this->assertContainsResourceActionGrant($rags, $rag_1_manage);
        $this->assertContainsResourceActionGrant($rags, $rag_1_read);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_res_group_read, $resource1);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login('some_student', $userAttributes);
        $rags = $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead();
        $this->assertCount(5, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag_res_group_2_read);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_res_group_2_read, $resource1);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_res_group_1_manage, $resource2);
        $this->assertContainsInheritedResourceActionGrant($rags, $rag_super_res_group_read, $resource2);
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
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE
        );
        $resourceGroup2 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER.'_2',
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE
        );
        $superResourceGroup = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER.'_super',
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE
        );

        $this->testEntityManager->addResourceToResourceGroup(
            $superResourceGroup->getResourceClass(), $superResourceGroup->getResourceIdentifier(),
            $resourceGroup->getResourceIdentifier(), AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE);

        $this->testEntityManager->addResourceToResourceGroup(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resource1->getResourceIdentifier());
        $this->testEntityManager->addResourceToResourceGroup(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resource2->getResourceIdentifier());

        $this->testEntityManager->addResourceToResourceGroup(
            $resourceGroup2->getResourceClass(), $resourceGroup2->getResourceIdentifier(),
            $resource1->getResourceIdentifier());

        $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1,
            TestResources::READ_ACTION,
            dynamicUserGroupIdentifier: 'employees');
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            AuthorizationService::MANAGE_ACTION, null, $group1);

        $this->testEntityManager->addResourceActionGrant($superResourceGroup,
            TestResources::READ_ACTION, 'big_brother'
        );
        $this->testEntityManager->addResourceActionGrant($resourceGroup,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER.'_2',
        );
        $this->testEntityManager->addResourceActionGrant($resourceGroup2,
            TestResources::READ_ACTION,
            dynamicUserGroupIdentifier: 'students'
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
        $this->assertCount(1, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_3', $userAttributes);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(1, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_4', $userAttributes);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(1, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $userAttributes['IS_EMPLOYEE'] = true;
        $this->login(null, $userAttributes);
        $resourceClasses = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead();
        $this->assertCount(1, $resourceClasses);
        $this->assertContains(self::TEST_RESOURCE_CLASS, $resourceClasses);

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
            action: AuthorizationService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);

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
            action: AuthorizationService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);
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
