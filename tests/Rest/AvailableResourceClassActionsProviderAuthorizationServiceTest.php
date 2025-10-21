<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassActions;
use Dbp\Relay\AuthorizationBundle\Rest\AvailableResourceClassActionsProvider;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractAuthorizationServiceTestCase;
use Dbp\Relay\AuthorizationBundle\Tests\EventSubscriber\TestGetAvailableResourceClassActionsEventSubscriber;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;

class AvailableResourceClassActionsProviderAuthorizationServiceTest extends AbstractAuthorizationServiceTestCase
{
    private DataProviderTester $availableResourceClassActionsProviderTester;

    protected function setUp(): void
    {
        parent::setUp();

        $provider = new AvailableResourceClassActionsProvider($this->internalResourceActionGrantService,
            $this->authorizationService);
        $this->availableResourceClassActionsProviderTester = DataProviderTester::create($provider,
            AvailableResourceClassActions::class,
            ['AuthorizationAvailableResourceClassActions:output'], identifierName: 'resourceClass');
    }

    public function testGetAvailableResourceClassActionsItem(): void
    {
        $resource1 = $this->testEntityManager->addAuthorizationResource(
            TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS, 'resourceIdentifier');
        $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $availableResourceClassActions =
            $this->availableResourceClassActionsProviderTester->getItem(
                TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS);
        $this->assertEquals(TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS,
            $availableResourceClassActions->getResourceClass());

        $expectedItemActions = TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_ITEM_ACTIONS;
        $expectedItemActions[AuthorizationService::MANAGE_ACTION] = [
            'en' => 'Manage',
            'de' => 'Verwalten',
        ];
        $this->assertEquals($expectedItemActions, $availableResourceClassActions->getItemActions());

        $expectedCollectionActions = TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_COLLECTION_ACTIONS;
        $expectedCollectionActions[AuthorizationService::MANAGE_ACTION] = [
            'en' => 'Manage',
            'de' => 'Verwalten',
        ];
        $this->assertEquals($expectedCollectionActions, $availableResourceClassActions->getCollectionActions());
    }

    public function testGetAvailableResourceClassActionsItemNotFound(): void
    {
        $availableResourceClassActions =
            $this->availableResourceClassActionsProviderTester->getItem('404');

        $this->assertNull($availableResourceClassActions);
    }

    public function testGetAvailableResourceClassActionsCollection(): void
    {
        $group1 = $this->testEntityManager->addGroup();
        $group2 = $this->testEntityManager->addGroup();
        $group3 = $this->testEntityManager->addGroup();

        $this->testEntityManager->addGroupMember($group1, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addGroupMember($group2, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addGroupMember($group3, self::ANOTHER_USER_IDENTIFIER.'_3');

        // noise:
        $group4 = $this->testEntityManager->addGroup();
        $this->testEntityManager->addGroupMember($group4, self::ANOTHER_USER_IDENTIFIER.'_4');
        // -----

        $resource1 = $this->testEntityManager->addAuthorizationResource(
            TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS, 'resourceIdentifier');
        $resource2 = $this->testEntityManager->addAuthorizationResource(
            TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS_2, 'resourceIdentifier_2');
        $resource3 = $this->testEntityManager->addAuthorizationResource(
            TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS_2, 'resourceIdentifier_3');
        $resourceCollection = $this->testEntityManager->addAuthorizationResource(
            TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS_3, null);
        $collectionResource = $this->testEntityManager->addAuthorizationResource(
            TestGetAvailableResourceClassActionsEventSubscriber::TEST_COLLECTION_RESOURCE_CLASS, 'collectionResourceIdentifier');

        $this->testEntityManager->addGrantInheritance(
            $collectionResource->getResourceClass(), $collectionResource->getResourceIdentifier(),
            $resource1->getResourceClass(), $resource1->getResourceIdentifier());

        $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, null, $group2);
        $this->testEntityManager->addResourceActionGrant($resource3,
            AuthorizationService::MANAGE_ACTION, null, null, 'students');
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            'create', self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            'create', null, null, 'students');
        $this->testEntityManager->addResourceActionGrant($resourceCollection,
            'create', null, $group1);

        $this->testEntityManager->addResourceActionGrant($collectionResource,
            TestGetAvailableResourceClassActionsEventSubscriber::CREATE_ACTION, group: $group3);

        $testResourceClassActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS);
        $testResourceClass2Actions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS_2);
        $testResourceClass3Actions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS_3);
        $testCollectionResourceActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            TestGetAvailableResourceClassActionsEventSubscriber::TEST_COLLECTION_RESOURCE_CLASS);

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $userAttributes);
        $availableResourceClassActionCollection =
            $this->availableResourceClassActionsProviderTester->getCollection();
        $this->assertCount(3, $availableResourceClassActionCollection);
        $this->assertCount(1, $this->selectWhere($availableResourceClassActionCollection,
            function ($availableResourceClassActions) use ($testResourceClassActions) {
                return $availableResourceClassActions->getResourceClass() === TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS
                    && $availableResourceClassActions->getItemActions() === $testResourceClassActions[0]
                    && $availableResourceClassActions->getCollectionActions() === $testResourceClassActions[1];
            }));
        $this->assertCount(1, $this->selectWhere($availableResourceClassActionCollection,
            function ($availableResourceClassActions) use ($testResourceClass2Actions) {
                return $availableResourceClassActions->getResourceClass() === TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS_2
                    && $availableResourceClassActions->getItemActions() === $testResourceClass2Actions[0]
                    && $availableResourceClassActions->getCollectionActions() === $testResourceClass2Actions[1];
            }));
        $this->assertCount(1, $this->selectWhere($availableResourceClassActionCollection,
            function ($availableResourceClassActions) use ($testResourceClass3Actions) {
                return $availableResourceClassActions->getResourceClass() === TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS_3
                    && $availableResourceClassActions->getItemActions() === $testResourceClass3Actions[0]
                    && $availableResourceClassActions->getCollectionActions() === $testResourceClass3Actions[1];
            }));

        // test pagination:
        $availableResourceClassActionPage1 =
            $this->availableResourceClassActionsProviderTester->getCollection([
                'page' => 1,
                'perPage' => 2,
            ]);
        $this->assertCount(2, $availableResourceClassActionPage1);

        $availableResourceClassActionPage2 =
            $this->availableResourceClassActionsProviderTester->getCollection([
                'page' => 2,
                'perPage' => 2,
            ]);
        $this->assertCount(1, $availableResourceClassActionPage2);

        $availableResourceClassActionCollection = array_merge($availableResourceClassActionPage1, $availableResourceClassActionPage2);
        $this->assertCount(3, $availableResourceClassActionCollection);
        $this->assertCount(1, $this->selectWhere($availableResourceClassActionCollection,
            function ($availableResourceClassActions) use ($testResourceClassActions) {
                return $availableResourceClassActions->getResourceClass() === TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS
                    && $availableResourceClassActions->getItemActions() === $testResourceClassActions[0]
                    && $availableResourceClassActions->getCollectionActions() === $testResourceClassActions[1];
            }));
        $this->assertCount(1, $this->selectWhere($availableResourceClassActionCollection,
            function ($availableResourceClassActions) use ($testResourceClass2Actions) {
                return $availableResourceClassActions->getResourceClass() === TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS_2
                    && $availableResourceClassActions->getItemActions() === $testResourceClass2Actions[0]
                    && $availableResourceClassActions->getCollectionActions() === $testResourceClass2Actions[1];
            }));
        $this->assertCount(1, $this->selectWhere($availableResourceClassActionCollection,
            function ($availableResourceClassActions) use ($testResourceClass3Actions) {
                return $availableResourceClassActions->getResourceClass() === TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS_3
                    && $availableResourceClassActions->getItemActions() === $testResourceClass3Actions[0]
                    && $availableResourceClassActions->getCollectionActions() === $testResourceClass3Actions[1];
            }));

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $availableResourceClassActionCollection =
            $this->availableResourceClassActionsProviderTester->getCollection();
        $this->assertCount(1, $availableResourceClassActionCollection);
        $this->assertCount(1, $this->selectWhere($availableResourceClassActionCollection,
            function ($availableResourceClassActions) use ($testResourceClass3Actions) {
                return $availableResourceClassActions->getResourceClass() === TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS_3
                    && $availableResourceClassActions->getItemActions() === $testResourceClass3Actions[0]
                    && $availableResourceClassActions->getCollectionActions() === $testResourceClass3Actions[1];
            }));

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['IS_STUDENT'] = true;
        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2', $userAttributes);
        $availableResourceClassActionCollection =
            $this->availableResourceClassActionsProviderTester->getCollection();
        $this->assertCount(2, $availableResourceClassActionCollection);
        $this->assertCount(1, $this->selectWhere($availableResourceClassActionCollection,
            function ($availableResourceClassActions) use ($testResourceClass2Actions) {
                return $availableResourceClassActions->getResourceClass() === TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS_2
                    && $availableResourceClassActions->getItemActions() === $testResourceClass2Actions[0]
                    && $availableResourceClassActions->getCollectionActions() === $testResourceClass2Actions[1];
            }));
        $this->assertCount(1, $this->selectWhere($availableResourceClassActionCollection,
            function ($availableResourceClassActions) use ($testResourceClass3Actions) {
                return $availableResourceClassActions->getResourceClass() === TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS_3
                    && $availableResourceClassActions->getItemActions() === $testResourceClass3Actions[0]
                    && $availableResourceClassActions->getCollectionActions() === $testResourceClass3Actions[1];
            }));

        $this->login(self::ANOTHER_USER_IDENTIFIER.'_3');
        $availableResourceClassActionCollection =
            $this->availableResourceClassActionsProviderTester->getCollection();
        $this->assertCount(2, $availableResourceClassActionCollection);
        // source target resource class of the grant inheritance:
        $this->assertCount(1, $this->selectWhere($availableResourceClassActionCollection,
            function ($availableResourceClassActions) use ($testResourceClassActions) {
                return $availableResourceClassActions->getResourceClass() === TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS
                    && $availableResourceClassActions->getItemActions() === $testResourceClassActions[0]
                    && $availableResourceClassActions->getCollectionActions() === $testResourceClassActions[1];
            }));
        $this->assertCount(1, $this->selectWhere($availableResourceClassActionCollection,
            function ($availableResourceClassActions) use ($testCollectionResourceActions) {
                return $availableResourceClassActions->getResourceClass() === TestGetAvailableResourceClassActionsEventSubscriber::TEST_COLLECTION_RESOURCE_CLASS
                    && $availableResourceClassActions->getItemActions() === $testCollectionResourceActions[0]
                    && $availableResourceClassActions->getCollectionActions() === $testCollectionResourceActions[1];
            }));

        $this->login(self::ANOTHER_USER_IDENTIFIER.'_4');
        $availableResourceClassActionCollection =
            $this->availableResourceClassActionsProviderTester->getCollection();
        $this->assertCount(0, $availableResourceClassActionCollection);
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
