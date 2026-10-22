<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroupMember;
use Dbp\Relay\AuthorizationBundle\Rest\UserGroupMemberProcessor;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractAuthorizationServiceTestCase;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProcessorTester;
use Symfony\Component\HttpFoundation\Response;

class UserGroupMemberProcessorTest extends AbstractAuthorizationServiceTestCase
{
    private DataProcessorTester $groupMemberProcessorTester;

    protected function setUp(): void
    {
        parent::setUp();

        $groupMemberProcessor = new UserGroupMemberProcessor($this->authorizationService);
        $this->groupMemberProcessorTester = DataProcessorTester::create($groupMemberProcessor, UserGroupMember::class);
    }

    public function testCreateGroupMemberItemWithManageGrant(): void
    {
        $userGroup = $this->addUserGroupAndOwnerGrantForCurrentUser(self::TEST_GROUP_NAME)[0];

        $groupMember = new UserGroupMember();
        $groupMember->setUserGroup($userGroup);
        $groupMember->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $groupMember = $this->groupMemberProcessorTester->addItem($groupMember);
        $groupMemberPersistence = $this->testEntityManager->getUserGroupMember($groupMember->getIdentifier());
        $this->assertEquals($groupMember->getIdentifier(), $groupMemberPersistence->getIdentifier());
        $this->assertEquals($groupMember->getChildGroup(), $groupMemberPersistence->getChildGroup());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $groupMemberPersistence->getUserIdentifier());
    }

    public function testCreateGroupMemberItemWithAddGroupMembersGrant(): void
    {
        [$userGroup, $manageGrant] = $this->addUserGroupAndOwnerGrantForCurrentUser(self::TEST_GROUP_NAME);

        $this->testEntityManager->addResourceActionGrant($manageGrant->getAuthorizationResource(),
            AuthorizationService::ADD_GROUP_MEMBERS_GROUP_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $groupMember = new UserGroupMember();
        $groupMember->setUserGroup($userGroup);
        $groupMember->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $groupMember = $this->groupMemberProcessorTester->addItem($groupMember);
        $groupMemberPersistence = $this->testEntityManager->getUserGroupMember($groupMember->getIdentifier());
        $this->assertEquals($groupMember->getIdentifier(), $groupMemberPersistence->getIdentifier());
        $this->assertEquals($groupMember->getChildGroup(), $groupMemberPersistence->getChildGroup());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $groupMemberPersistence->getUserIdentifier());
    }

    public function testAddGroupMemberItemForbiddenNotFound(): void
    {
        // another user tries to add a member to a user group that they can't see -> 404
        $userGroup = $this->addUserGroupAndOwnerGrantForCurrentUser(self::TEST_GROUP_NAME)[0];

        $groupMember = new UserGroupMember();
        $groupMember->setUserGroup($userGroup);
        $groupMember->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $this->login(self::ANOTHER_USER_IDENTIFIER);

        try {
            $this->groupMemberProcessorTester->addItem($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_NOT_FOUND, $apiError->getStatusCode());
        }
    }

    public function testAddGroupMemberItemForbidden(): void
    {
        // another user is granted read access to the group, but not add user group member access -> 403
        $userGroup = $this->addUserGroupAndOwnerGrantForCurrentUser(self::TEST_GROUP_NAME)[0];

        $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::GROUP_RESOURCE_CLASS,
            $userGroup->getIdentifier(),
            action: AuthorizationService::READ_GROUP_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER
        );

        $groupMember = new UserGroupMember();
        $groupMember->setUserGroup($userGroup);
        $groupMember->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        try {
            $this->groupMemberProcessorTester->addItem($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testDeleteGroupMemberItem(): void
    {
        $userGroup = $this->addUserGroupAndOwnerGrantForCurrentUser(self::TEST_GROUP_NAME)[0];
        $groupMember = $this->testEntityManager->addUserGroupMember($userGroup, self::CURRENT_USER_IDENTIFIER);
        $this->assertNotNull($this->testEntityManager->getUserGroupMember($groupMember->getIdentifier()));

        $this->groupMemberProcessorTester->removeItem($groupMember->getIdentifier(), $groupMember);
        $this->assertNull($this->testEntityManager->getUserGroupMember($groupMember->getIdentifier()));
    }

    public function testDeleteGroupMemberItemWithDeleteGroupMemberGrant(): void
    {
        [$userGroup, $manageGrant] = $this->addUserGroupAndOwnerGrantForCurrentUser();

        $groupMember = $this->testEntityManager->addUserGroupMember($userGroup, self::CURRENT_USER_IDENTIFIER);
        $this->assertNotNull($this->testEntityManager->getUserGroupMember($groupMember->getIdentifier()));

        $this->testEntityManager->addResourceActionGrant($manageGrant->getAuthorizationResource(),
            AuthorizationService::DELETE_GROUP_MEMBERS_GROUP_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $this->groupMemberProcessorTester->removeItem($groupMember->getIdentifier(), $groupMember);
        $this->assertNull($this->testEntityManager->getUserGroupMember($groupMember->getIdentifier()));
    }

    public function testDeleteGroupItemForbiddenNotFound(): void
    {
        // Another user tries to delete a group member of a group that they can't see -> 404
        $userGroup = $this->addUserGroupAndOwnerGrantForCurrentUser(self::TEST_GROUP_NAME)[0];
        $groupMember = $this->testEntityManager->addUserGroupMember($userGroup, self::CURRENT_USER_IDENTIFIER);

        $this->login(self::ANOTHER_USER_IDENTIFIER);

        try {
            $this->groupMemberProcessorTester->removeItem($groupMember->getIdentifier(), $groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_NOT_FOUND, $apiError->getStatusCode());
        }
    }

    public function testDeleteGroupItemForbidden(): void
    {
        // another user is granted read access to the group, but not delete user group member access -> 403
        $userGroup = $this->addUserGroupAndOwnerGrantForCurrentUser(self::TEST_GROUP_NAME)[0];
        $groupMember = $this->testEntityManager->addUserGroupMember($userGroup, self::CURRENT_USER_IDENTIFIER);

        $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::GROUP_RESOURCE_CLASS,
            $userGroup->getIdentifier(),
            action: AuthorizationService::READ_GROUP_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER
        );

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        try {
            $this->groupMemberProcessorTester->removeItem($groupMember->getIdentifier(), $groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }
}
