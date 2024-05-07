<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Service;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Entity\GroupMember;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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

        $this->groupService = new GroupService($this->testEntityManager->getEntityManager(), $this->authorizationService);
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

    public function testDeleteGroup(): void
    {
        $group = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $this->assertEquals($group->getIdentifier(), $this->testEntityManager->getGroup($group->getIdentifier())->getIdentifier());

        $this->groupService->removeGroup($group);
        $this->assertNull($this->testEntityManager->getGroup($group->getIdentifier()));
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

    public function testAddGroupMember(): void
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
        $group = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $subGroup = $this->testEntityManager->addGroup('subgroup');
        $subSubGroup = $this->testEntityManager->addGroup('subsubgroup');

        $this->testEntityManager->addGroupMember($subSubGroup, self::CURRENT_USER_IDENTIFIER.'_3');

        $this->testEntityManager->addGroupMember($subGroup, self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addGroupMember($subGroup, null, $subSubGroup);

        $this->testEntityManager->addGroupMember($group, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addGroupMember($group, null, $subGroup);

        $this->assertTrue($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER, $group->getIdentifier()));
        $this->assertTrue($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_2', $group->getIdentifier()));
        $this->assertTrue($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_3', $group->getIdentifier()));
        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_4', $group->getIdentifier()));

        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER, $subGroup->getIdentifier()));
        $this->assertTrue($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_2', $subGroup->getIdentifier()));
        $this->assertTrue($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_3', $subGroup->getIdentifier()));
        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_4', $subGroup->getIdentifier()));

        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER, $subSubGroup->getIdentifier()));
        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_2', $subSubGroup->getIdentifier()));
        $this->assertTrue($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_3', $subSubGroup->getIdentifier()));
        $this->assertFalse($this->groupService->isUserMemberOfGroup(self::CURRENT_USER_IDENTIFIER.'_4', $subSubGroup->getIdentifier()));
    }

    public function testGetGroupsUserIsMemberOf(): void
    {
        $group = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $subGroup = $this->testEntityManager->addGroup('subgroup');
        $subSubGroup = $this->testEntityManager->addGroup('subsubgroup');

        $this->testEntityManager->addGroupMember($subSubGroup, self::CURRENT_USER_IDENTIFIER.'_3');

        $this->testEntityManager->addGroupMember($subGroup, self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addGroupMember($subGroup, null, $subSubGroup);

        $this->testEntityManager->addGroupMember($group, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addGroupMember($group, null, $subGroup);

        $groups = $this->groupService->getGroupsUserIsMemberOf(self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(1, $groups);
        $this->assertEquals($group->getIdentifier(), $groups[0]);

        $groups = $this->groupService->getGroupsUserIsMemberOf(self::CURRENT_USER_IDENTIFIER.'_2');
        $this->assertCount(2, $groups);
        $this->assertContains($group->getIdentifier(), $groups);
        $this->assertContains($subGroup->getIdentifier(), $groups);

        $groups = $this->groupService->getGroupsUserIsMemberOf(self::CURRENT_USER_IDENTIFIER.'_3');
        $this->assertCount(3, $groups);
        $this->assertContains($group->getIdentifier(), $groups);
        $this->assertContains($subGroup->getIdentifier(), $groups);
        $this->assertContains($subSubGroup->getIdentifier(), $groups);
    }
}
