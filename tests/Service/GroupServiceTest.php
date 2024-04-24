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
        $this->authorizationService = new AuthorizationService($internalResourceActionGrantService);
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

    public function ttestGetGroupMemberItemInvalidId(): void
    {
        $this->assertNull($this->groupService->getGroupMember('404'));
    }
}
