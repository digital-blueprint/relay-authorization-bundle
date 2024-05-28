<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Rest\GroupProvider;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;
use Symfony\Component\HttpFoundation\Response;

class GroupProviderTest extends AbstractGroupControllerTestCase
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
        $group1 = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);
        $group2 = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);
        $group3 = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);

        $groups = $this->groupProviderTester->getCollection([
            'page' => 1,
            'perPage' => 10,
        ]);
        $this->assertCount(3, $groups);
        $this->assertEquals($group1->getIdentifier(), $groups[0]->getIdentifier());
        $this->assertEquals($group2->getIdentifier(), $groups[1]->getIdentifier());
        $this->assertEquals($group3->getIdentifier(), $groups[2]->getIdentifier());

        // test pagination
        $groups = $this->groupProviderTester->getCollection([
            'page' => 1,
            'perPage' => 2,
        ]);
        $this->assertCount(2, $groups);
        $this->assertEquals($group1->getIdentifier(), $groups[0]->getIdentifier());
        $this->assertEquals($group2->getIdentifier(), $groups[1]->getIdentifier());

        $groups = $this->groupProviderTester->getCollection([
            'page' => 2,
            'perPage' => 2,
        ]);
        $this->assertCount(1, $groups);
        $this->assertEquals($group3->getIdentifier(), $groups[0]->getIdentifier());
    }
}
