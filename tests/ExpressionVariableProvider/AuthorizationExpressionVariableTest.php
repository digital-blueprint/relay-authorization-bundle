<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\ExpressionVariableProvider;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\ExpressionVariableProvider\AuthorizationExpressionVariable;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractAuthorizationServiceTestCase;
use Dbp\Relay\AuthorizationBundle\Tests\TestResources;

class AuthorizationExpressionVariableTest extends AbstractAuthorizationServiceTestCase
{
    private AuthorizationExpressionVariable $expressionVariable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->expressionVariable = new AuthorizationExpressionVariable(
            $this->authorizationService,
            new GroupService($this->testEntityManager->getEntityManager())
        );
    }

    public function testIsGranted(): void
    {
        $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertTrue($this->expressionVariable->isGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER, TestResources::READ_ACTION));
        $this->assertFalse($this->expressionVariable->isGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER, TestResources::WRITE_ACTION));
    }

    public function testIsMemberOfGroup(): void
    {
        $memberGroup = $this->testEntityManager->addGroup('editors');
        $this->testEntityManager->addGroupMember($memberGroup, self::CURRENT_USER_IDENTIFIER);
        $otherGroup = $this->testEntityManager->addGroup('admins');

        $this->assertTrue($this->expressionVariable->isMemberOfGroup($memberGroup->getIdentifier()));
        $this->assertFalse($this->expressionVariable->isMemberOfGroup($otherGroup->getIdentifier()));
    }

    public function testIsMemberOfGroupReturnsFalseWhenUnauthenticated(): void
    {
        $group = $this->testEntityManager->addGroup('editors');
        $this->testEntityManager->addGroupMember($group, self::CURRENT_USER_IDENTIFIER);

        $this->login(null);

        $this->assertFalse($this->expressionVariable->isMemberOfGroup($group->getIdentifier()));
    }

    public function testIsMemberOfDynamicGroup(): void
    {
        $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::GROUP_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertTrue($this->expressionVariable->isMemberOfDynamicGroup(
            AuthorizationService::DYNAMIC_GROUP_IDENTIFIER_EVERYBODY));
    }
}
