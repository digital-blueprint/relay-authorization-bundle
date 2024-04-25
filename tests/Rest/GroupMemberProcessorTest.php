<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Rest\GroupMemberProcessor;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProcessorTester;
use Proxies\__CG__\Dbp\Relay\AuthorizationBundle\Entity\GroupMember;
use Symfony\Component\HttpFoundation\Response;

class GroupMemberProcessorTest extends AbstractGroupControllerTest
{
    private DataProcessorTester $groupMemberProcessorTester;

    protected function setUp(): void
    {
        parent::setUp();

        $groupMemberProcessor = new GroupMemberProcessor(
            $this->groupService, $this->authorizationService);
        $this->groupMemberProcessorTester = new DataProcessorTester($groupMemberProcessor, GroupMember::class);
        DataProcessorTester::setUp($groupMemberProcessor);
    }

    public function testCreateGroupMemberItem(): void
    {
        $group = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);

        $groupMember = new GroupMember();
        $groupMember->setGroup($group);
        $groupMember->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $groupMember = $this->groupMemberProcessorTester->addItem($groupMember);
        $groupMemberPersistence = $this->testEntityManager->getGroupMember($groupMember->getIdentifier());
        $this->assertEquals($groupMember->getIdentifier(), $groupMemberPersistence->getIdentifier());
        $this->assertEquals($groupMember->getChildGroup(), $groupMemberPersistence->getChildGroup());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $groupMemberPersistence->getUserIdentifier());
        $this->assertEquals($groupMember->getPredefinedGroupIdentifier(), $groupMemberPersistence->getPredefinedGroupIdentifier());
    }

    public function testCreateGroupMemberItemForbidden(): void
    {
        $group = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);

        $groupMember = new GroupMember();
        $groupMember->setGroup($group);
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
        $group = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);
        $groupMember = $this->testEntityManager->addGroupMember($group, self::CURRENT_USER_IDENTIFIER);
        $this->assertNotNull($this->testEntityManager->getGroupMember($groupMember->getIdentifier()));

        $this->groupMemberProcessorTester->removeItem($groupMember->getIdentifier(), $groupMember);
        $this->assertNull($this->testEntityManager->getGroupMember($groupMember->getIdentifier()));
    }

    public function testDeleteGroupItemForbidden(): void
    {
        $group = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);
        $groupMember = $this->testEntityManager->addGroupMember($group, self::CURRENT_USER_IDENTIFIER);
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        try {
            $this->groupMemberProcessorTester->removeItem($groupMember->getIdentifier(), $groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }
}
