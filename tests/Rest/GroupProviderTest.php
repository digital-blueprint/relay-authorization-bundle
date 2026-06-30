<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroup;
use Dbp\Relay\AuthorizationBundle\Rest\UserGroupProvider;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;
use Symfony\Component\HttpFoundation\Response;

class GroupProviderTest extends AbstractGroupControllerAuthorizationServiceTestCase
{
    private DataProviderTester $groupProviderTester;

    protected function setUp(): void
    {
        parent::setUp();

        $groupProvider = new UserGroupProvider(
            $this->groupService, $this->authorizationService);
        $this->groupProviderTester = DataProviderTester::create($groupProvider, UserGroup::class);
    }

    public function testGetGroupItem(): void
    {
        $userGroup = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);
        $this->testEntityManager->addGroupMember($userGroup, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->clear(); // prevent re-use of cached group entity
        $groupPersistence = $this->groupProviderTester->getItem($userGroup->getIdentifier());

        $this->assertEquals($userGroup->getIdentifier(), $groupPersistence->getIdentifier());
        $this->assertEquals(self::TEST_GROUP_NAME, $groupPersistence->getName());
        $this->assertCount(1, $groupPersistence->getMembers());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $groupPersistence->getMembers()[0]->getUserIdentifier());
    }

    public function testGetGroupItemWithReadGrant(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $childGroup = $this->testEntityManager->addUserGroup('child group');
        $this->testEntityManager->addGroupMember($userGroup, childGroup: $childGroup);

        $manageGrant = $this->authorizationService->addUserGroup($userGroup->getIdentifier());
        $this->testEntityManager->addResourceActionGrant($manageGrant->getAuthorizationResource(),
            AuthorizationService::READ_GROUP_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $this->testEntityManager->clear(); // prevent re-use of cached group entity
        $groupPersistence = $this->groupProviderTester->getItem($userGroup->getIdentifier());
        $this->assertEquals($userGroup->getIdentifier(), $groupPersistence->getIdentifier());
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
        $userGroup = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        try {
            $this->groupProviderTester->getItem($userGroup->getIdentifier());
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

        $childGroup = $this->testEntityManager->addUserGroup('child group');

        $this->testEntityManager->addGroupMember($group1, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addGroupMember($group2, childGroup: $childGroup);
        $this->testEntityManager->clear(); // prevent re-use of cached group entity

        // another user has manage grants for groups 3, 4, and 5
        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $group3 = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME.'_3');
        $group3ManageGrant = $this->authorizationService->addUserGroup($group3->getIdentifier());
        $this->testEntityManager->addResourceActionGrant($group3ManageGrant->getAuthorizationResource(),
            AuthorizationService::READ_GROUP_ACTION, self::CURRENT_USER_IDENTIFIER);

        $group4 = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME.'_4');

        $group5 = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME.'_5');
        $group5ManageGrant = $this->authorizationService->addUserGroup($group5->getIdentifier());
        $this->testEntityManager->addResourceActionGrant($group5ManageGrant->getAuthorizationResource(),
            AuthorizationService::DELETE_GROUP_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->login(self::CURRENT_USER_IDENTIFIER);
        $groups = $this->groupProviderTester->getCollection([
            'page' => 1,
            'perPage' => 10,
        ]);
        $this->assertCount(3, $groups);
        $this->assertCount(1, $this->selectWhere($groups,
            function (UserGroup $userGroup) use ($group1): bool {
                return $userGroup->getIdentifier() === $group1->getIdentifier()
                    && $userGroup->getName() === $group1->getName()
                    && count($userGroup->getMembers()) === 1
                    && $userGroup->getMembers()[0]->getUserIdentifier() === self::CURRENT_USER_IDENTIFIER;
            }));
        $this->assertCount(1, $this->selectWhere($groups,
            function (UserGroup $userGroup) use ($group2, $childGroup): bool {
                return $userGroup->getIdentifier() === $group2->getIdentifier()
                    && $userGroup->getName() === $group2->getName()
                    && count($userGroup->getMembers()) === 1
                    && $userGroup->getMembers()[0]->getChildGroup() !== null
                    && $userGroup->getMembers()[0]->getChildGroup()->getIdentifier() === $childGroup->getIdentifier();
            }));
        $this->assertCount(1, $this->selectWhere($groups,
            function (UserGroup $userGroup) use ($group3): bool {
                return $userGroup->getIdentifier() === $group3->getIdentifier()
                    && $userGroup->getName() === $group3->getName()
                    && count($userGroup->getMembers()) === 0;
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
            function (UserGroup $userGroup) use ($group1): bool {
                return $userGroup->getIdentifier() === $group1->getIdentifier();
            }));
        $this->assertCount(1, $this->selectWhere($groups,
            function (UserGroup $userGroup) use ($group2): bool {
                return $userGroup->getIdentifier() === $group2->getIdentifier();
            }));
        $this->assertCount(1, $this->selectWhere($groups,
            function (UserGroup $userGroup) use ($group3): bool {
                return $userGroup->getIdentifier() === $group3->getIdentifier();
            }));

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $groups = $this->groupProviderTester->getCollection([
            'page' => 1,
            'perPage' => 10,
        ]);
        $this->assertCount(3, $groups);
        $this->assertCount(1, $this->selectWhere($groups,
            function (UserGroup $userGroup) use ($group3): bool {
                return $userGroup->getIdentifier() === $group3->getIdentifier()
                    && $userGroup->getName() === $group3->getName()
                    && count($userGroup->getMembers()) === 0;
            }));
        $this->assertCount(1, $this->selectWhere($groups,
            function (UserGroup $userGroup) use ($group4): bool {
                return $userGroup->getIdentifier() === $group4->getIdentifier()
                    && $userGroup->getName() === $group4->getName()
                    && count($userGroup->getMembers()) === 0;
            }));
        $this->assertCount(1, $this->selectWhere($groups,
            function (UserGroup $userGroup) use ($group5): bool {
                return $userGroup->getIdentifier() === $group5->getIdentifier()
                    && $userGroup->getName() === $group5->getName()
                    && count($userGroup->getMembers()) === 0;
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
        $group3 = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME.'_3');
        $group3ManageGrant = $this->authorizationService->addUserGroup($group3->getIdentifier());
        $this->testEntityManager->addResourceActionGrant($group3ManageGrant->getAuthorizationResource(),
            AuthorizationService::READ_GROUP_ACTION, self::CURRENT_USER_IDENTIFIER);

        $group4 = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME.'_4');

        $group5 = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME.'_5');
        $group5ManageGrant = $this->authorizationService->addUserGroup($group5->getIdentifier());
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
            function (UserGroup $userGroup) use ($group1): bool {
                return $userGroup->getIdentifier() === $group1->getIdentifier();
            }));
        $this->assertCount(1, $this->selectWhere($groups,
            function (UserGroup $userGroup) use ($group3): bool {
                return $userGroup->getIdentifier() === $group3->getIdentifier();
            }));

        $groups = $this->groupProviderTester->getCollection([
            AuthorizationService::GET_CHILD_GROUP_CANDIDATES_FOR_GROUP_IDENTIFIER_FILTER => $group1->getIdentifier(),
        ]);
        // 0. 3 allowed
        $this->assertCount(2, $groups);
        $this->assertCount(1, $this->selectWhere($groups,
            function (UserGroup $userGroup) use ($group0): bool {
                return $userGroup->getIdentifier() === $group0->getIdentifier();
            }));
        $this->assertCount(1, $this->selectWhere($groups,
            function (UserGroup $userGroup) use ($group3): bool {
                return $userGroup->getIdentifier() === $group3->getIdentifier();
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
