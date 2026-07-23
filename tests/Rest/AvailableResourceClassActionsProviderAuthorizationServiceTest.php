<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\AuthorizationBundle\Rest\AvailableResourceClassActionProvider;
use Dbp\Relay\AuthorizationBundle\Rest\Common;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractAuthorizationServiceTestCase;
use Dbp\Relay\AuthorizationBundle\Tests\TestResources;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;

class AvailableResourceClassActionsProviderAuthorizationServiceTest extends AbstractAuthorizationServiceTestCase
{
    private DataProviderTester $availableResourceClassActionsProviderTester;

    protected function setUp(): void
    {
        parent::setUp();

        $provider = new AvailableResourceClassActionProvider($this->authorizationService);
        $this->availableResourceClassActionsProviderTester = DataProviderTester::create($provider,
            AvailableResourceClassAction::class,
            ['AuthorizationAvailableResourceClassAction:output']);
    }

    public function testGetAvailableResourceClassActionsCollection(): void
    {
        $editorRole = $this->internalResourceActionGrantService->addOrUpdateRole([], [
            ResourceActionGrantService::createRoleAction(TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
            ResourceActionGrantService::createRoleAction(TestResources::TEST_RESOURCE_CLASS, TestResources::WRITE_ACTION, AvailableResourceClassAction::ITEM_ACTION_TYPE),
        ]);

        $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            action: AuthorizationService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );
        $rag = $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER_2,
            action: TestResources::UPDATE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );
        $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER_2,
            roleIdentifier: $editorRole->getIdentifier(),
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );
        $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            TestResources::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            action: TestResources::CREATE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );
        $resourceGroup = $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER,
            AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
            action: TestResources::DELETE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );

        // noise:
        $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            TestResources::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER,
            action: TestResources::DELETE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );

        $collection = $this->availableResourceClassActionsProviderTester->getCollection([
            Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
            Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => 'someItemIdentifier',
            Common::RESOURCE_TYPE_QUERY_PARAMETER => ResourceActionGrantService::RESOURCE_RESOURCE_TYPE,
        ]);
        $this->assertCount(0, $collection);

        // user has manage rights -> expecting all actions plus manage action
        $collection = $this->availableResourceClassActionsProviderTester->getCollection([
            Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
            Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
            Common::RESOURCE_TYPE_QUERY_PARAMETER => ResourceActionGrantService::RESOURCE_RESOURCE_TYPE,
        ]);
        $allTestResourceActionsPlusManage = TestResources::TEST_RESOURCE_ITEM_ACTIONS;
        $allTestResourceActionsPlusManage[AuthorizationService::MANAGE_ACTION] = [];
        $this->assertCount(count($allTestResourceActionsPlusManage), $collection);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS,
            $allTestResourceActionsPlusManage, AvailableResourceClassAction::ITEM_ACTION_TYPE);

        // expecting actions from role and single actions combined:
        $collection = $this->availableResourceClassActionsProviderTester->getCollection([
            Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
            Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER_2,
            Common::RESOURCE_TYPE_QUERY_PARAMETER => ResourceActionGrantService::RESOURCE_RESOURCE_TYPE,
        ]);
        $this->assertCount(3, $collection);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS,
            [
                TestResources::UPDATE_ACTION => [],
                TestResources::READ_ACTION => [],
                TestResources::WRITE_ACTION => [],
            ], AvailableResourceClassAction::ITEM_ACTION_TYPE);

        // become a member of resource group -> inherit delete action from resource group
        $this->testEntityManager->addResourceToResourceGroup(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $rag->getAuthorizationResource()->getResourceIdentifier());

        $collection = $this->availableResourceClassActionsProviderTester->getCollection([
            Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
            Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER_2,
            Common::RESOURCE_TYPE_QUERY_PARAMETER => ResourceActionGrantService::RESOURCE_RESOURCE_TYPE,
        ]);
        $this->assertCount(4, $collection);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS,
            [
                TestResources::UPDATE_ACTION => [],
                TestResources::READ_ACTION => [],
                TestResources::WRITE_ACTION => [],
                TestResources::DELETE_ACTION => [],
            ], AvailableResourceClassAction::ITEM_ACTION_TYPE);

        // collection resource:
        $collection = $this->availableResourceClassActionsProviderTester->getCollection([
            Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER => InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER,
            Common::RESOURCE_TYPE_QUERY_PARAMETER => ResourceActionGrantService::RESOURCE_RESOURCE_TYPE,
        ]);
        $this->assertCount(1, $collection);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS,
            [TestResources::CREATE_ACTION => []], AvailableResourceClassAction::COLLECTION_ACTION_TYPE);

        // query resource group itself:
        $collection = $this->availableResourceClassActionsProviderTester->getCollection([
            Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
            Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_GROUP_IDENTIFIER,
            Common::RESOURCE_TYPE_QUERY_PARAMETER => ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE,
        ]);
        $this->assertCount(1, $collection);
        $this->assertContainsActions($collection, TestResources::TEST_RESOURCE_CLASS,
            [
                TestResources::DELETE_ACTION => [],
            ], AvailableResourceClassAction::ITEM_ACTION_TYPE);
    }

    protected function getTestConfig(): array
    {
        $config = parent::getTestConfig();
        $config[Configuration::DYNAMIC_GROUPS] = [
            [
                Configuration::IDENTIFIER => 'students',
                Configuration::IS_CURRENT_USER_GROUP_MEMBER_EXPRESSION => 'user.get("IS_STUDENT")',
            ],
        ];

        return $config;
    }

    protected function getDefaultUserAttributes(): array
    {
        $defaultUserAttributes = parent::getDefaultUserAttributes();
        $defaultUserAttributes['IS_STUDENT'] = false;

        return $defaultUserAttributes;
    }
}
