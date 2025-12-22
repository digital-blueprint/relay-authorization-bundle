<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\GrantedActions;
use Dbp\Relay\AuthorizationBundle\Rest\GrantedActionsProvider;
use Dbp\Relay\AuthorizationBundle\Tests\TestResources;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;
use Symfony\Component\HttpFoundation\Response;

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
            TestEntityManager::DEFAULT_RESOURCE_CLASS.':'.TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $grantedActions = $this->grantedActionsProviderTester->getItem(
            TestEntityManager::DEFAULT_RESOURCE_CLASS.':'.TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals([TestResources::UPDATE_ACTION], $grantedActions->getActions());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
    }

    public function testGetGrantedCollectionActions(): void
    {
        $manageGrant = $this->addResourceAndManageGrant(
            self::TEST_RESOURCE_CLASS_3, null, self::CURRENT_USER_IDENTIFIER);
        $this->addGrant($manageGrant->getAuthorizationResource(), TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->addGrant($manageGrant->getAuthorizationResource(), TestResources::CREATE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $grantedActions = $this->grantedActionsProviderTester->getItem(
            self::TEST_RESOURCE_CLASS_3.':');
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grantedActions->getActions());
        $this->assertEquals(self::TEST_RESOURCE_CLASS_3, $grantedActions->getResourceClass());
        $this->assertNull($grantedActions->getResourceIdentifier());

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $grantedActions = $this->grantedActionsProviderTester->getItem(
            self::TEST_RESOURCE_CLASS_3.':');
        assert($grantedActions instanceof GrantedActions);
        $this->assertEquals([TestResources::CREATE_ACTION], $grantedActions->getActions());
        $this->assertEquals(self::TEST_RESOURCE_CLASS_3, $grantedActions->getResourceClass());
        $this->assertNull($grantedActions->getResourceIdentifier());
    }

    public function testGetGrantedActionsMissingResourceClass(): void
    {
        try {
            $this->grantedActionsProviderTester->getItem(':'.TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
            $this->fail('ApiError not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('Resource class is mandatory', $apiError->getDetail());
        }
    }
}
