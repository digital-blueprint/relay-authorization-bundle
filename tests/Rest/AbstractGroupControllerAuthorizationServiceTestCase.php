<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractAuthorizationServiceTestCase;

abstract class AbstractGroupControllerAuthorizationServiceTestCase extends AbstractAuthorizationServiceTestCase
{
    protected const TEST_GROUP_NAME = 'test_group';
    protected GroupService $groupService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->groupService = new GroupService($this->testEntityManager->getEntityManager());
    }

    protected function addTestGroupAndManageGroupGrantForCurrentUser(string $name): Group
    {
        $group = $this->testEntityManager->addGroup($name);
        $this->authorizationService->addGroup($group->getIdentifier());

        return $group;
    }
}
