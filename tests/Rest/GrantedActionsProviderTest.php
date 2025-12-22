<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\GrantedActions;
use Dbp\Relay\AuthorizationBundle\Rest\Common;
use Dbp\Relay\AuthorizationBundle\Rest\GrantedActionsProvider;
use Dbp\Relay\AuthorizationBundle\Tests\TestResources;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;

class GrantedActionsProviderTest extends AbstractResourceActionGrantControllerAuthorizationServiceTestCase
{
    private ?DataProviderTester $grantedActionsProviderTester = null;

    protected function setUp(): void
    {
        parent::setUp();

        $provider = new GrantedActionsProvider($this->authorizationService);
        $this->grantedActionsProviderTester = DataProviderTester::create($provider,
            GrantedActions::class,
            ['AuthorizationGrantedActions:output']);
    }

    public function testGetGrantedItemActions(): void
    {
        $manageGrant = $this->addResourceAndManageGrant();
        $this->addGrant($manageGrant->getAuthorizationResource(), TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->addGrant($manageGrant->getAuthorizationResource(), TestResources::UPDATE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $grantedActions = $this->grantedActionsProviderTester->getItem(
            uriVariables: [
                Common::RESOURCE_CLASS_URI_VARIABLE_NAME => TestEntityManager::DEFAULT_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_URI_VARIABLE_NAME => TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER,
            ]);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $grantedActions = $this->grantedActionsProviderTester->getItem(
            uriVariables: [
                Common::RESOURCE_CLASS_URI_VARIABLE_NAME => TestEntityManager::DEFAULT_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_URI_VARIABLE_NAME => TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER,
            ]);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals([TestResources::UPDATE_ACTION], $grantedActions->getActions());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
    }

    public function testGetGrantedCollectionActions(): void
    {
        $manageGrant = $this->addResourceAndManageGrant(
            self::TEST_RESOURCE_CLASS_3,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            self::CURRENT_USER_IDENTIFIER);
        $this->addGrant($manageGrant->getAuthorizationResource(), TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->addGrant($manageGrant->getAuthorizationResource(), TestResources::CREATE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $grantedActions = $this->grantedActionsProviderTester->getItem(
            uriVariables: [
                Common::RESOURCE_CLASS_URI_VARIABLE_NAME => self::TEST_RESOURCE_CLASS_3,
                Common::RESOURCE_IDENTIFIER_URI_VARIABLE_NAME => AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            ]);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());
        $this->assertEquals(self::TEST_RESOURCE_CLASS_3, $grantedActions->getResourceClass());
        $this->assertEquals(
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            $grantedActions->getResourceIdentifier()
        );

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $grantedActions = $this->grantedActionsProviderTester->getItem(
            uriVariables: [
                Common::RESOURCE_CLASS_URI_VARIABLE_NAME => self::TEST_RESOURCE_CLASS_3,
                Common::RESOURCE_IDENTIFIER_URI_VARIABLE_NAME => AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            ]);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals([TestResources::CREATE_ACTION], $grantedActions->getActions());
        $this->assertEquals(self::TEST_RESOURCE_CLASS_3, $grantedActions->getResourceClass());
        $this->assertEquals(
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            $grantedActions->getResourceIdentifier()
        );
    }
}
