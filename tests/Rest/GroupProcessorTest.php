<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Rest\GroupProcessor;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProcessorTester;
use Symfony\Component\HttpFoundation\Response;

class GroupProcessorTest extends AbstractGroupControllerTestCase
{
    private DataProcessorTester $groupProcessorTester;

    protected function setUp(): void
    {
        parent::setUp();

        $groupProcessor = new GroupProcessor(
            $this->groupService, $this->authorizationService);
        $this->groupProcessorTester = DataProcessorTester::create($groupProcessor, Group::class);
    }

    public function testCreateGroupItemWithManageGrant(): void
    {
        // add a manage group resource collection grant for the current user
        $this->internalResourceActionGrantService->addResourceAndManageResourceGrantForUser(
            AuthorizationService::GROUP_RESOURCE_CLASS, null, self::CURRENT_USER_IDENTIFIER);

        $group = new Group();
        $group->setName(self::TEST_GROUP_NAME);

        $group = $this->groupProcessorTester->addItem($group);
        $groupPersistence = $this->testEntityManager->getGroup($group->getIdentifier());
        $this->assertEquals($group->getIdentifier(), $groupPersistence->getIdentifier());
        $this->assertEquals(self::TEST_GROUP_NAME, $groupPersistence->getName());
        $this->assertEmpty($group->getMembers());
    }

    public function testCreateGroupItemWithCreateGrant(): void
    {
        // add a manage group resource collection grant for the current user
        $manageGrant = $this->internalResourceActionGrantService->addResourceAndManageResourceGrantForUser(
            AuthorizationService::GROUP_RESOURCE_CLASS, null, self::CURRENT_USER_IDENTIFIER);

        $this->testEntityManager->addResourceActionGrant($manageGrant->getAuthorizationResource(), AuthorizationService::CREATE_GROUPS_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $group = new Group();
        $group->setName(self::TEST_GROUP_NAME);

        $group = $this->groupProcessorTester->addItem($group);
        $groupPersistence = $this->testEntityManager->getGroup($group->getIdentifier());
        $this->assertEquals($group->getIdentifier(), $groupPersistence->getIdentifier());
        $this->assertEquals(self::TEST_GROUP_NAME, $groupPersistence->getName());
        $this->assertEmpty($group->getMembers());
    }

    public function testCreateGroupItemWithPolicy(): void
    {
        // give the current user the required user attribute for the 'create group' policy to evaluate to 'true'
        $this->login(self::CURRENT_USER_IDENTIFIER, ['MAY_CREATE_GROUPS' => true]);

        $group = new Group();
        $group->setName(self::TEST_GROUP_NAME);

        $group = $this->groupProcessorTester->addItem($group);
        $groupPersistence = $this->testEntityManager->getGroup($group->getIdentifier());
        $this->assertEquals($group->getIdentifier(), $groupPersistence->getIdentifier());
        $this->assertEquals(self::TEST_GROUP_NAME, $groupPersistence->getName());
        $this->assertEmpty($group->getMembers());
    }

    public function testCreateGroupItemForbidden(): void
    {
        $group = new Group();
        $group->setName(self::TEST_GROUP_NAME);

        try {
            $this->groupProcessorTester->addItem($group);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testDeleteGroupItemWithManageGrant(): void
    {
        $group = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);
        $this->assertNotNull($this->testEntityManager->getGroup($group->getIdentifier()));
        $this->groupProcessorTester->removeItem($group->getIdentifier(), $group);
        $this->assertNull($this->testEntityManager->getGroup($group->getIdentifier()));
    }

    public function testDeleteGroupItemWithDeleteGrant(): void
    {
        $group = $this->testEntityManager->addGroup(self::TEST_GROUP_NAME);
        $manageGrant = $this->authorizationService->addGroup($group->getIdentifier());
        $this->assertNotNull($this->testEntityManager->getGroup($group->getIdentifier()));

        $this->testEntityManager->addResourceActionGrant($manageGrant->getAuthorizationResource(),
            AuthorizationService::DELETE_GROUP_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $this->groupProcessorTester->removeItem($group->getIdentifier(), $group);
        $this->assertNull($this->testEntityManager->getGroup($group->getIdentifier()));
    }

    public function testDeleteGroupItemForbidden(): void
    {
        $group = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        try {
            $this->groupProcessorTester->removeItem($group->getIdentifier(), $group);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }
}
