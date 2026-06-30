<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroup;
use Dbp\Relay\AuthorizationBundle\Rest\UserGroupProcessor;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProcessorTester;
use Symfony\Component\HttpFoundation\Response;

class GroupProcessorTest extends AbstractGroupControllerAuthorizationServiceTestCase
{
    private DataProcessorTester $groupProcessorTester;

    protected function setUp(): void
    {
        parent::setUp();

        $groupProcessor = new UserGroupProcessor(
            $this->groupService, $this->authorizationService);
        $this->groupProcessorTester = DataProcessorTester::create($groupProcessor, UserGroup::class);
    }

    public function testCreateGroupItemWithManageGrant(): void
    {
        $this->addManageGroupCollectionGrantForCurrentUser();

        $userGroup = new UserGroup();
        $userGroup->setName(self::TEST_GROUP_NAME);

        $userGroup = $this->groupProcessorTester->addItem($userGroup);
        $groupPersistence = $this->testEntityManager->getUserGroup($userGroup->getIdentifier());
        $this->assertEquals($userGroup->getIdentifier(), $groupPersistence->getIdentifier());
        $this->assertEquals(self::TEST_GROUP_NAME, $groupPersistence->getName());
        $this->assertEmpty($userGroup->getMembers());
    }

    public function testCreateGroupItemWithCreateGrant(): void
    {
        $manageGrant = $this->addManageGroupCollectionGrantForCurrentUser();

        $this->testEntityManager->addResourceActionGrant($manageGrant->getAuthorizationResource(), AuthorizationService::CREATE_GROUPS_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $userGroup = new UserGroup();
        $userGroup->setName(self::TEST_GROUP_NAME);

        $userGroup = $this->groupProcessorTester->addItem($userGroup);
        $groupPersistence = $this->testEntityManager->getUserGroup($userGroup->getIdentifier());
        $this->assertEquals($userGroup->getIdentifier(), $groupPersistence->getIdentifier());
        $this->assertEquals(self::TEST_GROUP_NAME, $groupPersistence->getName());
        $this->assertEmpty($userGroup->getMembers());
    }

    public function testCreateGroupItemWithPolicy(): void
    {
        // give the current user the required user attribute for the 'create group' policy to evaluate to 'true'
        $this->login(self::CURRENT_USER_IDENTIFIER, ['MAY_CREATE_GROUPS' => true]);

        $userGroup = new UserGroup();
        $userGroup->setName(self::TEST_GROUP_NAME);

        $userGroup = $this->groupProcessorTester->addItem($userGroup);
        $groupPersistence = $this->testEntityManager->getUserGroup($userGroup->getIdentifier());
        $this->assertEquals($userGroup->getIdentifier(), $groupPersistence->getIdentifier());
        $this->assertEquals(self::TEST_GROUP_NAME, $groupPersistence->getName());
        $this->assertEmpty($userGroup->getMembers());
    }

    public function testCreateGroupItemForbidden(): void
    {
        $userGroup = new UserGroup();
        $userGroup->setName(self::TEST_GROUP_NAME);

        try {
            $this->groupProcessorTester->addItem($userGroup);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testUpdateGroupItemWithManageGrant(): void
    {
        $userGroup = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);
        $this->assertEquals(self::TEST_GROUP_NAME, $this->testEntityManager->getUserGroup($userGroup->getIdentifier())->getName());
        $previousGroup = clone $userGroup;
        $userGroup->setName(self::TEST_GROUP_NAME.'_updated');
        $this->groupProcessorTester->updateItem($userGroup->getIdentifier(), $userGroup, $previousGroup);
        $this->assertEquals(self::TEST_GROUP_NAME.'_updated', $this->testEntityManager->getUserGroup($userGroup->getIdentifier())->getName());
    }

    public function testUpdateGroupItemWithUpdateGrant(): void
    {
        [$userGroup, $manageGroupGrant] = $this->addGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);

        $this->testEntityManager->addResourceActionGrant($manageGroupGrant->getAuthorizationResource(),
            AuthorizationService::UPDATE_GROUP_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $this->assertEquals(self::TEST_GROUP_NAME, $this->testEntityManager->getUserGroup($userGroup->getIdentifier())->getName());
        $previousGroup = clone $userGroup;
        $userGroup->setName(self::TEST_GROUP_NAME.'_updated');
        $this->groupProcessorTester->updateItem($userGroup->getIdentifier(), $userGroup, $previousGroup);
        $this->assertEquals(self::TEST_GROUP_NAME.'_updated', $this->testEntityManager->getUserGroup($userGroup->getIdentifier())->getName());
    }

    public function testUpdateGroupItemForbidden(): void
    {
        $userGroup = $this->addGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME)[0];
        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $this->assertEquals(self::TEST_GROUP_NAME, $this->testEntityManager->getUserGroup($userGroup->getIdentifier())->getName());
        $previousGroup = clone $userGroup;
        $userGroup->setName(self::TEST_GROUP_NAME.'_updated');
        try {
            $this->groupProcessorTester->updateItem($userGroup->getIdentifier(), $userGroup, $previousGroup);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testDeleteGroupItemWithManageGrant(): void
    {
        $userGroup = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);
        $this->assertNotNull($this->testEntityManager->getUserGroup($userGroup->getIdentifier()));
        $this->groupProcessorTester->removeItem($userGroup->getIdentifier(), $userGroup);
        $this->assertNull($this->testEntityManager->getUserGroup($userGroup->getIdentifier()));
    }

    public function testDeleteGroupItemWithDeleteGrant(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $manageGrant = $this->authorizationService->addUserGroup($userGroup->getIdentifier());
        $this->assertNotNull($this->testEntityManager->getUserGroup($userGroup->getIdentifier()));

        $this->testEntityManager->addResourceActionGrant($manageGrant->getAuthorizationResource(),
            AuthorizationService::DELETE_GROUP_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $this->groupProcessorTester->removeItem($userGroup->getIdentifier(), $userGroup);
        $this->assertNull($this->testEntityManager->getUserGroup($userGroup->getIdentifier()));
    }

    public function testDeleteGroupItemForbidden(): void
    {
        $userGroup = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        try {
            $this->groupProcessorTester->removeItem($userGroup->getIdentifier(), $userGroup);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    private function addManageGroupCollectionGrantForCurrentUser(): ResourceActionGrant
    {
        $groupCollection = $this->internalResourceActionGrantService->getAuthorizationResourceByResourceClassAndIdentifier(
            AuthorizationService::GROUP_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER
        );

        $manageGroupCollectionGrant = new ResourceActionGrant();
        $manageGroupCollectionGrant->setAuthorizationResource($groupCollection);
        $manageGroupCollectionGrant->setAction(AuthorizationService::MANAGE_ACTION);
        $manageGroupCollectionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        return $this->internalResourceActionGrantService->addResourceActionGrant(
            $manageGroupCollectionGrant, self::CURRENT_USER_IDENTIFIER);
    }
}
