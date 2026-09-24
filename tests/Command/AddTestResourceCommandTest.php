<?php

declare(strict_types=1);

namespace Command;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Command\AddTestResourceCommand;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractAuthorizationServiceTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class AddTestResourceCommandTest extends AbstractAuthorizationServiceTestCase
{
    public function testAddTestResourceCommand(): void
    {
        $command = new AddTestResourceCommand($this->internalResourceActionGrantService);
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'resourceClass' => self::TEST_RESOURCE_CLASS,
            'resourceIdentifier' => self::TEST_RESOURCE_IDENTIFIER,
            'userIdentifier' => self::CURRENT_USER_IDENTIFIER,
        ]);

        $resource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER
        );
        $this->assertNotNull($resource);

        $resourceActionGrants = $this->testEntityManager->getResourceActionGrants($resource->getIdentifier());
        $this->assertCount(1, $resourceActionGrants);
        $resourceActionGrant = $resourceActionGrants[0];
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $resourceActionGrant->getUserIdentifier());
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getResourceIdentifier());
        $this->assertEquals(ResourceActionGrantService::MANAGE_ACTION, $resourceActionGrant->getAction());
    }
}
