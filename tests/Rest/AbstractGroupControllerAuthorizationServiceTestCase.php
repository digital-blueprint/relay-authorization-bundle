<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Entity\UserGroup;
use Dbp\Relay\AuthorizationBundle\Service\UserGroupService;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractAuthorizationServiceTestCase;
use Psr\Log\NullLogger;

abstract class AbstractGroupControllerAuthorizationServiceTestCase extends AbstractAuthorizationServiceTestCase
{
    protected const TEST_GROUP_NAME = 'test_group';
    protected UserGroupService $groupService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->groupService = new UserGroupService($this->testEntityManager->getEntityManager());
        $this->groupService->setLogger(new NullLogger());
    }

    protected function addTestGroupAndManageGroupGrantForCurrentUser(string $name): UserGroup
    {
        $userGroup = $this->testEntityManager->addUserGroup($name);
        $this->authorizationService->addUserGroup($userGroup->getIdentifier());

        return $userGroup;
    }
}
