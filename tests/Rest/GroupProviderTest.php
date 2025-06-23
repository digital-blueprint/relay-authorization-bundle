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
        $groupPersistence = $this->groupProviderTester->getItem($group->getIdentifier());

        $this->assertEquals($group->getIdentifier(), $groupPersistence->getIdentifier());
    }

    public function testGetGroupItemWithReadGrant(): void
    {
        $group = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $manageGrant = $this->authorizationService->addGroup($group->getIdentifier());
        $this->assertNotNull($this->testEntityManager->getGroup($group->getIdentifier()));

        $this->testEntityManager->addResourceActionGrant($manageGrant->getAuthorizationResource(),
            AuthorizationService::READ_GROUP_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $groupPersistence = $this->groupProviderTester->getItem($group->getIdentifier());
        $this->assertEquals($group->getIdentifier(), $groupPersistence->getIdentifier());
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
        $this->assertTrue($this->containsResource($groups, $group1));
        $this->assertTrue($this->containsResource($groups, $group2));
        $this->assertTrue($this->containsResource($groups, $group3));

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
        $this->assertTrue($this->containsResource($groups, $group1));
        $this->assertTrue($this->containsResource($groups, $group2));
        $this->assertTrue($this->containsResource($groups, $group3));

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $groups = $this->groupProviderTester->getCollection([
            'page' => 1,
            'perPage' => 10,
        ]);
        $this->assertCount(3, $groups);
        $this->assertTrue($this->containsResource($groups, $group3));
        $this->assertTrue($this->containsResource($groups, $group4));
        $this->assertTrue($this->containsResource($groups, $group5));
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
            'search' => self::TEST_GROUP_NAME,
        ]);
        $this->assertCount(3, $groups);
        $this->assertTrue($this->containsResource($groups, $group1));
        $this->assertTrue($this->containsResource($groups, $group2));
        $this->assertTrue($this->containsResource($groups, $group3));
    }
}
