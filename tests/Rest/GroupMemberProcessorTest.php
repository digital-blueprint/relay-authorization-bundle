<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroupMember;
use Dbp\Relay\AuthorizationBundle\Rest\UserGroupMemberProcessor;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProcessorTester;
use Symfony\Component\HttpFoundation\Response;

class GroupMemberProcessorTest extends AbstractGroupControllerAuthorizationServiceTestCase
{
    private DataProcessorTester $groupMemberProcessorTester;

    protected function setUp(): void
    {
        parent::setUp();

        $groupMemberProcessor = new UserGroupMemberProcessor(
            $this->groupService, $this->authorizationService);
        $this->groupMemberProcessorTester = DataProcessorTester::create($groupMemberProcessor, UserGroupMember::class);
    }

    public function testCreateGroupMemberItemWithManageGrant(): void
    {
        $userGroup = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);

        $groupMember = new UserGroupMember();
        $groupMember->setUserGroup($userGroup);
        $groupMember->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $groupMember = $this->groupMemberProcessorTester->addItem($groupMember);
        $groupMemberPersistence = $this->testEntityManager->getGroupMember($groupMember->getIdentifier());
        $this->assertEquals($groupMember->getIdentifier(), $groupMemberPersistence->getIdentifier());
        $this->assertEquals($groupMember->getChildGroup(), $groupMemberPersistence->getChildGroup());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $groupMemberPersistence->getUserIdentifier());
    }

    public function testCreateGroupMemberItemWithAddGroupMembersGrant(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $manageGrant = $this->authorizationService->addUserGroup($userGroup->getIdentifier());

        $this->testEntityManager->addResourceActionGrant($manageGrant->getAuthorizationResource(),
            AuthorizationService::ADD_GROUP_MEMBERS_GROUP_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $groupMember = new UserGroupMember();
        $groupMember->setUserGroup($userGroup);
        $groupMember->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $groupMember = $this->groupMemberProcessorTester->addItem($groupMember);
        $groupMemberPersistence = $this->testEntityManager->getGroupMember($groupMember->getIdentifier());
        $this->assertEquals($groupMember->getIdentifier(), $groupMemberPersistence->getIdentifier());
        $this->assertEquals($groupMember->getChildGroup(), $groupMemberPersistence->getChildGroup());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $groupMemberPersistence->getUserIdentifier());
    }

    public function testCreateGroupMemberItemForbidden(): void
    {
        $userGroup = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);

        $groupMember = new UserGroupMember();
        $groupMember->setUserGroup($userGroup);
        $groupMember->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        try {
            $this->groupMemberProcessorTester->addItem($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testDeleteGroupMemberItem(): void
    {
        $userGroup = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);
        $groupMember = $this->testEntityManager->addGroupMember($userGroup, self::CURRENT_USER_IDENTIFIER);
        $this->assertNotNull($this->testEntityManager->getGroupMember($groupMember->getIdentifier()));

        $this->groupMemberProcessorTester->removeItem($groupMember->getIdentifier(), $groupMember);
        $this->assertNull($this->testEntityManager->getGroupMember($groupMember->getIdentifier()));
    }

    public function testDeleteGroupMemberItemWithDeleteGroupMemberGrant(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $manageGrant = $this->authorizationService->addUserGroup($userGroup->getIdentifier());
        $groupMember = $this->testEntityManager->addGroupMember($userGroup, self::CURRENT_USER_IDENTIFIER);
        $this->assertNotNull($this->testEntityManager->getGroupMember($groupMember->getIdentifier()));

        $this->testEntityManager->addResourceActionGrant($manageGrant->getAuthorizationResource(),
            AuthorizationService::DELETE_GROUP_MEMBERS_GROUP_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $this->groupMemberProcessorTester->removeItem($groupMember->getIdentifier(), $groupMember);
        $this->assertNull($this->testEntityManager->getGroupMember($groupMember->getIdentifier()));
    }

    public function testDeleteGroupItemForbidden(): void
    {
        $userGroup = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);
        $groupMember = $this->testEntityManager->addGroupMember($userGroup, self::CURRENT_USER_IDENTIFIER);
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        try {
            $this->groupMemberProcessorTester->removeItem($groupMember->getIdentifier(), $groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }
}
