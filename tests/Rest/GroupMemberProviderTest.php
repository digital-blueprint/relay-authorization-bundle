<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Rest\Common;
use Dbp\Relay\AuthorizationBundle\Rest\GroupMemberProvider;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;
use Proxies\__CG__\Dbp\Relay\AuthorizationBundle\Entity\GroupMember;
use Symfony\Component\HttpFoundation\Response;

class GroupMemberProviderTest extends AbstractGroupControllerTestCase
{
    private DataProviderTester $groupMemberProviderTester;

    protected function setUp(): void
    {
        parent::setUp();

        $groupMemberProcessor = new GroupMemberProvider(
            $this->groupService, $this->authorizationService);
        $this->groupMemberProviderTester = DataProviderTester::create($groupMemberProcessor, GroupMember::class);
    }

    public function testGetGroupMemberItemWithManageGroupGrant(): void
    {
        $group = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);
        $groupMember = $this->testEntityManager->addGroupMember($group, self::CURRENT_USER_IDENTIFIER);

        $groupMemberPersistence = $this->groupMemberProviderTester->getItem($groupMember->getIdentifier());

        $this->assertEquals($groupMember->getIdentifier(), $groupMemberPersistence->getIdentifier());
        $this->assertEquals($groupMember->getChildGroup(), $groupMemberPersistence->getChildGroup());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $groupMemberPersistence->getUserIdentifier());
    }

    public function testGetGroupMemberItemWithReadGroupGrant(): void
    {
        $group = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $manageGrant = $this->authorizationService->addGroup($group->getIdentifier());
        $groupMember = $this->testEntityManager->addGroupMember($group, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($manageGrant->getAuthorizationResource(),
            AuthorizationService::READ_GROUP_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $groupMemberPersistence = $this->groupMemberProviderTester->getItem($groupMember->getIdentifier());

        $this->assertEquals($groupMember->getIdentifier(), $groupMemberPersistence->getIdentifier());
        $this->assertEquals($groupMember->getChildGroup(), $groupMemberPersistence->getChildGroup());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $groupMemberPersistence->getUserIdentifier());
    }

    public function testGetGroupMemberItemForbidden(): void
    {
        $group = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);
        $groupMember = $this->testEntityManager->addGroupMember($group, self::CURRENT_USER_IDENTIFIER);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        try {
            $this->groupMemberProviderTester->getItem($groupMember->getIdentifier());
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testGetMemberCollection(): void
    {
        $group = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);
        $groupMembers = $this->groupMemberProviderTester->getCollection(
            [GroupMemberProvider::GROUP_IDENTIFIER_QUERY_PARAMETER => $group->getIdentifier()]);
        $this->assertCount(0, $groupMembers);

        $groupMemberA = $this->testEntityManager->addGroupMember($group, 'a');
        $groupMemberB = $this->testEntityManager->addGroupMember($group, 'b');
        $groupMemberC = $this->testEntityManager->addGroupMember($group, 'c');

        $groupMembers = $this->groupMemberProviderTester->getCollection(
            [GroupMemberProvider::GROUP_IDENTIFIER_QUERY_PARAMETER => $group->getIdentifier()]);
        $this->assertCount(3, $groupMembers);
        $this->assertEquals($groupMemberA->getIdentifier(), $groupMembers[0]->getIdentifier());
        $this->assertEquals($groupMemberB->getIdentifier(), $groupMembers[1]->getIdentifier());
        $this->assertEquals($groupMemberC->getIdentifier(), $groupMembers[2]->getIdentifier());

        $groupMembers = $this->groupMemberProviderTester->getCollection([
            GroupMemberProvider::GROUP_IDENTIFIER_QUERY_PARAMETER => $group->getIdentifier(),
            'page' => 1,
            'perPage' => 2,
        ]);
        $this->assertCount(2, $groupMembers);
        $this->assertEquals($groupMemberA->getIdentifier(), $groupMembers[0]->getIdentifier());
        $this->assertEquals($groupMemberB->getIdentifier(), $groupMembers[1]->getIdentifier());

        $groupMembers = $this->groupMemberProviderTester->getCollection([
            GroupMemberProvider::GROUP_IDENTIFIER_QUERY_PARAMETER => $group->getIdentifier(),
            'page' => 2,
            'perPage' => 2,
        ]);
        $this->assertCount(1, $groupMembers);
        $this->assertEquals($groupMemberC->getIdentifier(), $groupMembers[0]->getIdentifier());
    }

    public function testGetMemberCollectionGroupIdentifierParameterMissing(): void
    {
        try {
            $this->groupMemberProviderTester->getCollection([]);
            $this->fail('Expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(Common::REQUIRED_PARAMETER_MISSION_ERROR_ID, $apiError->getErrorId());
        }
    }
}
