<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Rest\GroupProcessor;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProcessorTester;
use Symfony\Component\HttpFoundation\Response;

class GroupProcessorTest extends AbstractGroupControllerTest
{
    private DataProcessorTester $groupProcessorTester;

    protected function setUp(): void
    {
        parent::setUp();

        $groupProcessor = new GroupProcessor(
            $this->groupService, $this->authorizationService);
        $this->groupProcessorTester = new DataProcessorTester($groupProcessor, Group::class);
        DataProcessorTester::setUp($groupProcessor);
    }

    public function testCreateGroupItem(): void
    {
        // grant current user manage group resource collection permission
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

    public function testDeleteGroupItem(): void
    {
        // grant current user manage group resource collection permission
        $this->internalResourceActionGrantService->addResourceAndManageResourceGrantForUser(
            AuthorizationService::GROUP_RESOURCE_CLASS, null, self::CURRENT_USER_IDENTIFIER);

        $group = $this->addTestGroupAndManageGroupGrantForCurrentUser(self::TEST_GROUP_NAME);
        $this->assertNotNull($this->testEntityManager->getGroup($group->getIdentifier()));
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
