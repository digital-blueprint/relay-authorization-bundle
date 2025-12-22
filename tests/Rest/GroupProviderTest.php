<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Rest\GroupProvider;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;
use Symfony\Component\HttpFoundation\Response;

class GroupProviderTest extends AbstractGroupControllerAuthorizationServiceTestCase
{
    private DataProviderTester $groupProviderTester;

    protected function setUp(): void
    {
        parent::setUp();

        $groupProvider = new GroupProvider(
            $this->groupService, $this->authorizationService);
        $this->groupProviderTester = DataProviderTester::create($groupProvider, Group::class);
    }

    public function testGetGroupItem(): void
    {
        $group = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);
        $this->testEntityManager->addGroupMember($group, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->clear(); // prevent re-use of cached group entity
        $groupPersistence = $this->groupProviderTester->getItem($group->getIdentifier());

        $this->assertEquals($group->getIdentifier(), $groupPersistence->getIdentifier());
        $this->assertEquals(self::TEST_GROUP_NAME, $groupPersistence->getName());
        $this->assertCount(1, $groupPersistence->getMembers());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $groupPersistence->getMembers()[0]->getUserIdentifier());
    }

    public function testGetGroupItemWithReadGrant(): void
    {
        $group = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $childGroup = $this->testEntityManager->addGroup('child group');
        $this->testEntityManager->addGroupMember($group, childGroup: $childGroup);

        $manageGrant = $this->authorizationService->addGroup($group->getIdentifier());
        $this->testEntityManager->addResourceActionGrant($manageGrant->getAuthorizationResource(),
            AuthorizationService::READ_GROUP_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $this->testEntityManager->clear(); // prevent re-use of cached group entity
        $groupPersistence = $this->groupProviderTester->getItem($group->getIdentifier());
        $this->assertEquals($group->getIdentifier(), $groupPersistence->getIdentifier());
        $this->assertEquals(self::TEST_GROUP_NAME, $groupPersistence->getName());
        $this->assertCount(1, $groupPersistence->getMembers());
        $this->assertEquals($childGroup->getIdentifier(), $groupPersistence->getMembers()[0]->getChildGroup()->getIdentifier());
        $this->assertEquals($childGroup->getName(), $groupPersistence->getMembers()[0]->getChildGroup()->getName());
    }

    public function testGetGroupItemNotFound(): void
    {
        $this->assertNull($this->groupProviderTester->getItem('no'));
    }

    public function testGetGroupItemForbidden(): void
    {
        $group = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        try {
            $this->groupProviderTester->getItem($group->getIdentifier());
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testGetGroupCollection(): void
    {
        // current user has manage grants for groups 1 and 2, a read grant for group 3, and a write grant for group 5
        $this->login(self::CURRENT_USER_IDENTIFIER);
        $group1 = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME.'_1');
        $group2 = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME.'_2');

        $childGroup = $this->testEntityManager->addGroup('child group');

        $this->testEntityManager->addGroupMember($group1, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addGroupMember($group2, childGroup: $childGroup);
        $this->testEntityManager->clear(); // prevent re-use of cached group entity

        // another user has manage grants for groups 3, 4, and 5
        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $group3 = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME.'_3');
        $group3ManageGrant = $this->authorizationService->addGroup($group3->getIdentifier());
        $this->testEntityManager->addResourceActionGrant($group3ManageGrant->getAuthorizationResource(),
            AuthorizationService::READ_GROUP_ACTION, self::CURRENT_USER_IDENTIFIER);

        $group4 = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME.'_4');

        $group5 = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME.'_5');
        $group5ManageGrant = $this->authorizationService->addGroup($group5->getIdentifier());
        $this->testEntityManager->addResourceActionGrant($group5ManageGrant->getAuthorizationResource(),
            AuthorizationService::DELETE_GROUP_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->login(self::CURRENT_USER_IDENTIFIER);
        $groups = $this->groupProviderTester->getCollection([
            'page' => 1,
            'perPage' => 10,
        ]);
        $this->assertCount(3, $groups);
        $this->assertCount(1, $this->selectWhere($groups,
            function (Group $group) use ($group1): bool {
                return $group->getIdentifier() === $group1->getIdentifier()
                    && $group->getName() === $group1->getName()
                    && count($group->getMembers()) === 1
                    && $group->getMembers()[0]->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER;
            }));
        $this->assertCount(1, $this->selectWhere($groups,
            function (Group $group) use ($group2, $childGroup): bool {
                return $group->getIdentifier() === $group2->getIdentifier()
                    && $group->getName() === $group2->getName()
                    && count($group->getMembers()) === 1
                    && $group->getMembers()[0]->getChildGroup() !== null
                    && $group->getMembers()[0]->getChildGroup()->getIdentifier() === $childGroup->getIdentifier();
            }));
        $this->assertCount(1, $this->selectWhere($groups,
            function (Group $group) use ($group3): bool {
                return $group->getIdentifier() === $group3->getIdentifier()
                    && $group->getName() === $group3->getName()
                    && count($group->getMembers()) === 0;
            }));

        // test pagination
        $groupPage1 = $this->groupProviderTester->getCollection([
            'page' => 1,
            'perPage' => 2,
        ]);
        $this->assertCount(2, $groupPage1);

        $groupPage2 = $this->groupProviderTester->getCollection([
            'page' => 2,
            'perPage' => 2,
        ]);
        $this->assertCount(1, $groupPage2);

        $groups = array_merge($groupPage1, $groupPage2);
        $this->assertCount(3, $groups);
        $this->assertCount(1, $this->selectWhere($groups,
            function (Group $group) use ($group1): bool {
                return $group->getIdentifier() === $group1->getIdentifier();
            }));
        $this->assertCount(1, $this->selectWhere($groups,
            function (Group $group) use ($group2): bool {
                return $group->getIdentifier() === $group2->getIdentifier();
            }));
        $this->assertCount(1, $this->selectWhere($groups,
            function (Group $group) use ($group3): bool {
                return $group->getIdentifier() === $group3->getIdentifier();
            }));

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $groups = $this->groupProviderTester->getCollection([
            'page' => 1,
            'perPage' => 10,
        ]);
        $this->assertCount(3, $groups);
        $this->assertCount(1, $this->selectWhere($groups,
            function (Group $group) use ($group3): bool {
                return $group->getIdentifier() === $group3->getIdentifier()
                    && $group->getName() === $group3->getName()
                    && count($group->getMembers()) === 0;
            }));
        $this->assertCount(1, $this->selectWhere($groups,
            function (Group $group) use ($group4): bool {
                return $group->getIdentifier() === $group4->getIdentifier()
                    && $group->getName() === $group4->getName()
                    && count($group->getMembers()) === 0;
            }));
        $this->assertCount(1, $this->selectWhere($groups,
            function (Group $group) use ($group5): bool {
                return $group->getIdentifier() === $group5->getIdentifier()
                    && $group->getName() === $group5->getName()
                    && count($group->getMembers()) === 0;
            }));
    }

    public function testGetGroupCollectionWithSearchParameter(): void
    {
        // current user has manage grants for groups 1 and 2, a read grant for group 3, and a write grant for group 5
        $this->login(self::CURRENT_USER_IDENTIFIER);
        $group1 = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME.'_1');
        $group2 = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME.'_2');

        // another user has manage grants for groups 3, 4, and 5
        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $group3 = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME.'_3');
        $group3ManageGrant = $this->authorizationService->addGroup($group3->getIdentifier());
        $this->testEntityManager->addResourceActionGrant($group3ManageGrant->getAuthorizationResource(),
            AuthorizationService::READ_GROUP_ACTION, self::CURRENT_USER_IDENTIFIER);

        $group4 = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME.'_4');

        $group5 = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME.'_5');
        $group5ManageGrant = $this->authorizationService->addGroup($group5->getIdentifier());
        $this->testEntityManager->addResourceActionGrant($group5ManageGrant->getAuthorizationResource(),
            AuthorizationService::DELETE_GROUP_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->login(self::CURRENT_USER_IDENTIFIER);
        $groups = $this->groupProviderTester->getCollection([
            AuthorizationService::GROUP_SEARCH_FILTER => self::TEST_GROUP_NAME,
        ]);
        $this->assertCount(3, $groups);
        $this->assertTrue($this->containsResource($groups, $group1));
        $this->assertTrue($this->containsResource($groups, $group2));
        $this->assertTrue($this->containsResource($groups, $group3));
    }

    public function testGetGroupCollectionWithGetChildGroupCandidatesFilter(): void
    {
        $this->login(self::CURRENT_USER_IDENTIFIER);

        // all ancestors of the group, all child groups, and the group itself are disallowed group members
        $group0 = $this->addTestGroupAndManageGroupGrantForCurrentUser('Group 0');
        $group1 = $this->addTestGroupAndManageGroupGrantForCurrentUser('Group 1');
        $group2 = $this->addTestGroupAndManageGroupGrantForCurrentUser('Group 2');
        $group3 = $this->addTestGroupAndManageGroupGrantForCurrentUser('Group 3');

        $this->testEntityManager->addGroupMember($group0, childGroup: $group2);
        $this->testEntityManager->addGroupMember($group1, childGroup: $group2);
        $this->testEntityManager->addGroupMember($group2, childGroup: $group3);
        $this->testEntityManager->addGroupMember($group3, self::CURRENT_USER_IDENTIFIER);

        $groups = $this->groupProviderTester->getCollection([
            AuthorizationService::GET_CHILD_GROUP_CANDIDATES_FOR_GROUP_IDENTIFIER_FILTER => $group0->getIdentifier(),
        ]);
        // 1, 3 allowed
        $this->assertCount(2, $groups);
        $this->assertCount(1, $this->selectWhere($groups,
            function (Group $group) use ($group1): bool {
                return $group->getIdentifier() === $group1->getIdentifier();
            }));
        $this->assertCount(1, $this->selectWhere($groups,
            function (Group $group) use ($group3): bool {
                return $group->getIdentifier() === $group3->getIdentifier();
            }));

        $groups = $this->groupProviderTester->getCollection([
            AuthorizationService::GET_CHILD_GROUP_CANDIDATES_FOR_GROUP_IDENTIFIER_FILTER => $group1->getIdentifier(),
        ]);
        // 0. 3 allowed
        $this->assertCount(2, $groups);
        $this->assertCount(1, $this->selectWhere($groups,
            function (Group $group) use ($group0): bool {
                return $group->getIdentifier() === $group0->getIdentifier();
            }));
        $this->assertCount(1, $this->selectWhere($groups,
            function (Group $group) use ($group3): bool {
                return $group->getIdentifier() === $group3->getIdentifier();
            }));

        $groups = $this->groupProviderTester->getCollection([
            AuthorizationService::GET_CHILD_GROUP_CANDIDATES_FOR_GROUP_IDENTIFIER_FILTER => $group2->getIdentifier(),
        ]);
        // none allowed
        $this->assertCount(0, $groups);

        $groups = $this->groupProviderTester->getCollection([
            AuthorizationService::GET_CHILD_GROUP_CANDIDATES_FOR_GROUP_IDENTIFIER_FILTER => $group3->getIdentifier(),
        ]);
        // none allowed
        $this->assertCount(0, $groups);
    }
}
