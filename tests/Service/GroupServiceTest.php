<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Service;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Entity\GroupMember;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class GroupServiceTest extends WebTestCase
{
    private const CURRENT_USER_IDENTIFIER = 'userIdentifier';
    private const TEST_GROUP_NAME = 'test_group';

    private GroupService $groupService;
    private AuthorizationService $authorizationService;
    private TestEntityManager $testEntityManager;

    protected function setUp(): void
    {
        $this->testEntityManager = new TestEntityManager(self::bootKernel());
        $internalResourceActionGrantService = new InternalResourceActionGrantService($this->testEntityManager->getEntityManager());
        $this->authorizationService = new AuthorizationService(
            $internalResourceActionGrantService, new GroupService($this->testEntityManager->getEntityManager()));
        TestAuthorizationService::setUp($this->authorizationService, self::CURRENT_USER_IDENTIFIER);

        $this->groupService = new GroupService($this->testEntityManager->getEntityManager());
    }

    public function testAddGroup(): void
    {
        $group = new Group();
        $group->setName(self::TEST_GROUP_NAME);

        $group = $this->groupService->addGroup($group);
        $groupPersistence = $this->testEntityManager->getGroup($group->getIdentifier());

        $this->assertEquals($group->getIdentifier(), $groupPersistence->getIdentifier());
        $this->assertEquals(self::TEST_GROUP_NAME, $groupPersistence->getName());
        $this->assertEmpty($group->getMembers());
    }

    public function testRemoveGroup(): void
    {
        $group = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $this->assertEquals($group->getIdentifier(), $this->testEntityManager->getGroup($group->getIdentifier())->getIdentifier());

        $this->groupService->removeGroup($group);
        $this->assertNull($this->testEntityManager->getGroup($group->getIdentifier()));
    }

    public function testRemoveParentGroup(): void
    {
        // test ON DELETE CASCADE for 'group' (parent group)
        $group = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $this->assertEquals($group->getIdentifier(), $this->testEntityManager->getGroup($group->getIdentifier())->getIdentifier());

        $groupMember = $this->testEntityManager->addGroupMember($group, self::CURRENT_USER_IDENTIFIER);
        $this->assertEquals($groupMember->getIdentifier(),
            $this->testEntityManager->getGroupMember($groupMember->getIdentifier())->getIdentifier());

        $this->groupService->removeGroup($group);
        $this->assertNull($this->testEntityManager->getGroup($group->getIdentifier()));
        $this->assertNull($this->testEntityManager->getGroupMember($groupMember->getIdentifier()));
    }

    public function testRemoveChildGroup(): void
    {
        // test ON DELETE CASCADE for 'childGroup' (= group that is a member of another group)
        $group = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $this->assertEquals($group->getIdentifier(), $this->testEntityManager->getGroup($group->getIdentifier())->getIdentifier());
        $childGroup = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME.'_2');
        $this->assertEquals($childGroup->getIdentifier(), $this->testEntityManager->getGroup($childGroup->getIdentifier())->getIdentifier());

        $groupMember = $this->testEntityManager->addGroupMember($group, null, $childGroup);
        $this->assertNotNull($this->testEntityManager->getGroupMember($groupMember->getIdentifier()));

        $this->groupService->removeGroup($childGroup);
        $this->assertNotNull($this->testEntityManager->getGroup($group->getIdentifier()));
        $this->assertNull($this->testEntityManager->getGroup($childGroup->getIdentifier()));
        $this->assertNull($this->testEntityManager->getGroupMember($groupMember->getIdentifier()));
    }

    public function testRemoveResourceActionGrantGroup(): void
    {
        // test ON DELETE CASCADE for ResourceActionGrant::group (= group that holds a grant)
        $group = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $this->assertEquals($group->getIdentifier(), $this->testEntityManager->getGroup($group->getIdentifier())->getIdentifier());

        $resourceActionGrant = $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            TestEntityManager::DEFAULT_RESOURCE_CLASS,
            TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, 'read', null, $group);
        $this->assertEquals($resourceActionGrant->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier())->getIdentifier());

        $this->groupService->removeGroup($group);
        $this->assertNull($this->testEntityManager->getGroup($group->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier()));
    }

    public function testGetGroupItem(): void
    {
        $group = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);

        $groupPersistence = $this->groupService->getGroup($group->getIdentifier());
        $this->assertEquals($group->getIdentifier(), $groupPersistence->getIdentifier());
        $this->assertEquals(self::TEST_GROUP_NAME, $groupPersistence->getName());
        $this->assertEmpty($group->getMembers());
    }

    public function testGetGroupItemNotFound(): void
    {
        $this->assertNull($this->groupService->getGroup(Uuid::uuid7()->toString()));
    }

    public function testGetGroupItemNotFoundInvalidId(): void
    {
        $this->assertNull($this->groupService->getGroup('404'));
    }

    public function testGetGroupCollection(): void
    {
        $group1 = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $group2 = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME.'_2');
        $group3 = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME.'_3');

        $groups = $this->groupService->getGroups(1, 10);
        $this->assertCount(3, $groups);
        $this->assertEquals($group1->getIdentifier(), $groups[0]->getIdentifier());
        $this->assertEquals($group2->getIdentifier(), $groups[1]->getIdentifier());
        $this->assertEquals($group3->getIdentifier(), $groups[2]->getIdentifier());

        $groups = $this->groupService->getGroups(1, 2);
        $this->assertCount(2, $groups);
        $this->assertEquals($group1->getIdentifier(), $groups[0]->getIdentifier());
        $this->assertEquals($group2->getIdentifier(), $groups[1]->getIdentifier());

        $groups = $this->groupService->getGroups(2, 2);
        $this->assertCount(1, $groups);
        $this->assertEquals($group3->getIdentifier(), $groups[0]->getIdentifier());
    }

    public function testAddGroupMemberWithUserIdentifier(): void
    {
        $group = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $groupMember = new GroupMember();
        $groupMember->setGroup($group);
        $groupMember->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);
        $groupMember = $this->groupService->addGroupMember($groupMember);

        $groupMemberPersistence = $this->testEntityManager->getGroupMember($groupMember->getIdentifier());
        $this->assertEquals($groupMember->getIdentifier(), $groupMemberPersistence->getIdentifier());
        $this->assertEquals($group->getIdentifier(), $groupMemberPersistence->getGroup()->getIdentifier());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $groupMemberPersistence->getUserIdentifier());
    }

    public function testAddGroupMemberWithChildGroup(): void
    {
        $group = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $childGroup = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME.'_2');
        $groupMember = new GroupMember();
        $groupMember->setGroup($group);
        $groupMember->setChildGroup($childGroup);
        $groupMember = $this->groupService->addGroupMember($groupMember);

        $groupMemberPersistence = $this->testEntityManager->getGroupMember($groupMember->getIdentifier());
        $this->assertEquals($groupMember->getIdentifier(), $groupMemberPersistence->getIdentifier());
        $this->assertEquals($group->getIdentifier(), $groupMemberPersistence->getGroup()->getIdentifier());
        $this->assertEquals(null, $groupMemberPersistence->getUserIdentifier());
        $this->assertEquals($childGroup->getIdentifier(), $groupMemberPersistence->getChildGroup()->getIdentifier());
    }

    public function testAddGroupMemberInvalidGroupNull(): void
    {
        $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $groupMember = new GroupMember();
        $groupMember->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->groupService->addGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(GroupService::GROUP_MEMBER_INVALID_ERROR_ID, $apiError->getErrorId());
        }
    }

    /**
     * Matching parent and child group would cause and endless loop.
     */
    public function testAddGroupMemberInvalidGroupAndChildGroupMatch(): void
    {
        $group = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $groupMember = new GroupMember();
        $groupMember->setGroup($group);
        $groupMember->setChildGroup($group);

        try {
            $this->groupService->addGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(GroupService::GROUP_MEMBER_INVALID_ERROR_ID, $apiError->getErrorId());
        }
    }

    /**
     * Matching parent and child group would cause and endless loop.
     */
    public function testAddGroupMemberInvalidChildGroupIsAncestorOfGroup(): void
    {
        $group0 = $this->testEntityManager->addGroup();
        $group1 = $this->testEntityManager->addGroup();
        $group2 = $this->testEntityManager->addGroup();
        $group3 = $this->testEntityManager->addGroup();

        $this->testEntityManager->addGroupMember($group0, null, $group2);
        $this->testEntityManager->addGroupMember($group1, null, $group2);
        $this->testEntityManager->addGroupMember($group2, null, $group3);
        $this->testEntityManager->addGroupMember($group3, self::CURRENT_USER_IDENTIFIER);

        $groupMember = new GroupMember();

        $groupMember->setGroup($group3);
        $groupMember->setChildGroup($group0);
        try {
            $this->groupService->addGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(GroupService::GROUP_MEMBER_INVALID_ERROR_ID, $apiError->getErrorId());
        }

        $groupMember->setGroup($group3);
        $groupMember->setChildGroup($group1);
        try {
            $this->groupService->addGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(GroupService::GROUP_MEMBER_INVALID_ERROR_ID, $apiError->getErrorId());
        }

        $groupMember->setGroup($group3);
        $groupMember->setChildGroup($group2);
        try {
            $this->groupService->addGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(GroupService::GROUP_MEMBER_INVALID_ERROR_ID, $apiError->getErrorId());
        }

        $groupMember->setGroup($group2);
        $groupMember->setChildGroup($group0);
        try {
            $this->groupService->addGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(GroupService::GROUP_MEMBER_INVALID_ERROR_ID, $apiError->getErrorId());
        }

        $groupMember->setGroup($group2);
        $groupMember->setChildGroup($group1);
        try {
            $this->groupService->addGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(GroupService::GROUP_MEMBER_INVALID_ERROR_ID, $apiError->getErrorId());
        }

        // OK:
        $groupMember->setGroup($group1);
        $groupMember->setChildGroup($group0);
        $this->groupService->addGroupMember($groupMember);
    }

    public function testAddGroupMemberInvalidBothUserAndChildGroupSet(): void
    {
        $group = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $childGroup = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME.'_2');
        $groupMember = new GroupMember();
        $groupMember->setGroup($group);
        $groupMember->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);
        $groupMember->setChildGroup($childGroup);

        try {
            $this->groupService->addGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(GroupService::GROUP_MEMBER_INVALID_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testAddGroupMemberInvalidNeitherUserNorChildGroupSet(): void
    {
        $group = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $groupMember = new GroupMember();
        $groupMember->setGroup($group);

        try {
            $this->groupService->addGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(GroupService::GROUP_MEMBER_INVALID_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testDeleteGroupMember(): void
    {
        $group = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $groupMember = $this->testEntityManager->addGroupMember($group, self::CURRENT_USER_IDENTIFIER);
        $this->assertEquals($groupMember->getIdentifier(),
            $this->testEntityManager->getGroupMember($groupMember->getIdentifier())->getIdentifier());
        $this->groupService->removeGroupMember($groupMember);
        $this->assertNull($this->testEntityManager->getGroupMember($groupMember->getIdentifier()));
    }

    public function testGetGroupMemberItem(): void
    {
        $group = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $groupMember = $this->testEntityManager->addGroupMember($group, self::CURRENT_USER_IDENTIFIER);

        $groupMemberPersistence = $this->groupService->getGroupMember($groupMember->getIdentifier());
        $this->assertEquals($groupMember->getIdentifier(), $groupMemberPersistence->getIdentifier());
        $this->assertEquals(self::TEST_GROUP_NAME, $groupMemberPersistence->getGroup()->getName());
    }

    public function testGetGroupMemberItemNotFound(): void
    {
        $this->assertNull($this->groupService->getGroupMember(Uuid::uuid7()->toString()));
    }

    public function testGetGroupMemberItemInvalidId(): void
    {
        $this->assertNull($this->groupService->getGroupMember('404'));
    }

    public function testGetGroupMemberCollection(): void
    {
        $group = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $subGroup = $this->testEntityManager->addGroup('subgroup');
        $groupMembers = $this->groupService->getGroupMembers(1, 10, $group->getIdentifier());
        $this->assertCount(0, $groupMembers);

        $subgroupMember = $this->testEntityManager->addGroupMember($subGroup, self::CURRENT_USER_IDENTIFIER.'_2');
        $groupMember1 = $this->testEntityManager->addGroupMember($group, self::CURRENT_USER_IDENTIFIER);
        $groupMember2 = $this->testEntityManager->addGroupMember($group, null, $subGroup);
        $groupMember3 = $this->testEntityManager->addGroupMember($group, self::CURRENT_USER_IDENTIFIER.'_3');

        $groupMembers = $this->groupService->getGroupMembers(1, 10, $subGroup->getIdentifier());
        $this->assertCount(1, $groupMembers);
        $this->assertEquals($subgroupMember->getIdentifier(), $groupMembers[0]->getIdentifier());

        $groupMembers = $this->groupService->getGroupMembers(1, 10, $group->getIdentifier());
        $this->assertCount(3, $groupMembers);
        $this->assertEquals($groupMember1->getIdentifier(), $groupMembers[0]->getIdentifier());
        $this->assertEquals($groupMember2->getIdentifier(), $groupMembers[1]->getIdentifier());
        $this->assertEquals($groupMember3->getIdentifier(), $groupMembers[2]->getIdentifier());

        $groupMembers = $this->groupService->getGroupMembers(1, 2, $group->getIdentifier());
        $this->assertCount(2, $groupMembers);
        $this->assertEquals($groupMember1->getIdentifier(), $groupMembers[0]->getIdentifier());
        $this->assertEquals($groupMember2->getIdentifier(), $groupMembers[1]->getIdentifier());

        $groupMembers = $this->groupService->getGroupMembers(2, 2, $group->getIdentifier());
        $this->assertCount(1, $groupMembers);
        $this->assertEquals($groupMember3->getIdentifier(), $groupMembers[0]->getIdentifier());
    }

    public function testIsUserMemberOfGroup(): void
    {
        $group1 = $this->testEntityManager->addGroup();
        $group2 = $this->testEntityManager->addGroup();
        $subGroup1 = $this->testEntityManager->addGroup();
        $subGroup2 = $this->testEntityManager->addGroup();
        $subSubGroup1 = $this->testEntityManager->addGroup();

        $this->testEntityManager->addGroupMember($subSubGroup1, self::CURRENT_USER_IDENTIFIER.'_3');

        $this->testEntityManager->addGroupMember($subGroup1, self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addGroupMember($subGroup1, null, $subSubGroup1);

        $this->testEntityManager->addGroupMember($subGroup2, self::CURRENT_USER_IDENTIFIER.'_4');

        $this->testEntityManager->addGroupMember($group1, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addGroupMember($group1, null, $subGroup1);
        $this->testEntityManager->addGroupMember($group1, null, $subGroup2);

        $this->testEntityManager->addGroupMember($group2, self::CURRENT_USER_IDENTIFIER.'_4');
        $this->testEntityManager->addGroupMember($group2, self::CURRENT_USER_IDENTIFIER.'_5');
        $this->testEntityManager->addGroupMember($group2, null, $subGroup1);

        $this->assertTrue($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER, $group1->getIdentifier()));
        $this->assertTrue($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_2', $group1->getIdentifier()));
        $this->assertTrue($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_3', $group1->getIdentifier()));
        $this->assertTrue($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_4', $group1->getIdentifier()));
        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_5', $group1->getIdentifier()));
        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_6', $group1->getIdentifier()));

        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER, $group2->getIdentifier()));
        $this->assertTrue($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_2', $group2->getIdentifier()));
        $this->assertTrue($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_3', $group2->getIdentifier()));
        $this->assertTrue($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_4', $group2->getIdentifier()));
        $this->assertTrue($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_5', $group2->getIdentifier()));
        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_6', $group2->getIdentifier()));

        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER, $subGroup1->getIdentifier()));
        $this->assertTrue($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_2', $subGroup1->getIdentifier()));
        $this->assertTrue($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_3', $subGroup1->getIdentifier()));
        $this->assertTrue($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_4', $group1->getIdentifier()));
        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_5', $subGroup1->getIdentifier()));
        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_6', $subGroup1->getIdentifier()));

        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER, $subGroup2->getIdentifier()));
        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_2', $subGroup2->getIdentifier()));
        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_3', $subGroup2->getIdentifier()));
        $this->assertTrue($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_4', $subGroup2->getIdentifier()));
        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_5', $subGroup2->getIdentifier()));
        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_6', $subGroup2->getIdentifier()));

        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER, $subSubGroup1->getIdentifier()));
        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_2', $subSubGroup1->getIdentifier()));
        $this->assertTrue($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_3', $subSubGroup1->getIdentifier()));
        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_4', $subSubGroup1->getIdentifier()));
        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_5', $subSubGroup1->getIdentifier()));
        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_6', $subSubGroup1->getIdentifier()));
    }

    public function testGetGroupsUserIsMemberOf(): void
    {
        $group1 = $this->testEntityManager->addGroup();
        $group2 = $this->testEntityManager->addGroup();
        $subGroup1 = $this->testEntityManager->addGroup();
        $subGroup2 = $this->testEntityManager->addGroup();
        $subSubGroup1 = $this->testEntityManager->addGroup();

        $this->testEntityManager->addGroupMember($subSubGroup1, self::CURRENT_USER_IDENTIFIER.'_3');

        $this->testEntityManager->addGroupMember($subGroup1, self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addGroupMember($subGroup1, null, $subSubGroup1);

        $this->testEntityManager->addGroupMember($subGroup2, self::CURRENT_USER_IDENTIFIER.'_4');

        $this->testEntityManager->addGroupMember($group1, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addGroupMember($group1, null, $subGroup1);
        $this->testEntityManager->addGroupMember($group1, null, $subGroup2);

        $this->testEntityManager->addGroupMember($group2, null, $subGroup1);
        $this->testEntityManager->addGroupMember($group2, self::CURRENT_USER_IDENTIFIER.'_3');

        $groups = $this->groupService->getGroupsUserIsMemberOf(self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(1, $groups);
        $this->assertEquals($group1->getIdentifier(), $groups[0]);

        $groups = $this->groupService->getGroupsUserIsMemberOf(self::CURRENT_USER_IDENTIFIER.'_2');
        $this->assertCount(3, $groups);
        $this->assertContains($group1->getIdentifier(), $groups);
        $this->assertContains($subGroup1->getIdentifier(), $groups);
        $this->assertContains($group2->getIdentifier(), $groups);

        $groups = $this->groupService->getGroupsUserIsMemberOf(self::CURRENT_USER_IDENTIFIER.'_3');
        $this->assertCount(4, $groups);
        $this->assertContains($group1->getIdentifier(), $groups);
        $this->assertContains($subGroup1->getIdentifier(), $groups);
        $this->assertContains($subSubGroup1->getIdentifier(), $groups);
        $this->assertContains($group2->getIdentifier(), $groups);

        $groups = $this->groupService->getGroupsUserIsMemberOf(self::CURRENT_USER_IDENTIFIER.'_4');
        $this->assertCount(2, $groups);
        $this->assertContains($group1->getIdentifier(), $groups);
        $this->assertContains($subGroup2->getIdentifier(), $groups);

        $groups = $this->groupService->getGroupsUserIsMemberOf(self::CURRENT_USER_IDENTIFIER.'_5');
        $this->assertCount(0, $groups);
    }
}
