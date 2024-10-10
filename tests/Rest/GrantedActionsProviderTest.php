<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\GrantedActions;
use Dbp\Relay\AuthorizationBundle\Rest\GrantedActionsProvider;
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
        $this->addGrant($manageGrant->getAuthorizationResource(), 'read', self::CURRENT_USER_IDENTIFIER);
        $this->addGrant($manageGrant->getAuthorizationResource(), 'post', self::ANOTHER_USER_IDENTIFIER);

        $grantedActions = $this->grantedActionsProviderTester->getItem(
            TestEntityManager::DEFAULT_RESOURCE_CLASS.':'.TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        assert($grantedActions instanceof GrantedActions);
        $this->assertIsPermutationOf([AuthorizationService::MANAGE_ACTION, 'read'], $grantedActions->getActions());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $grantedActions = $this->grantedActionsProviderTester->getItem(
            TestEntityManager::DEFAULT_RESOURCE_CLASS.':'.TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER);
        assert($grantedActions instanceof GrantedActions);
        $this->assertIsPermutationOf(['post'], $grantedActions->getActions());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
    }

    public function testGetGrantedCollectionActions(): void
    {
        $manageGrant = $this->addResourceAndManageGrant(
            TestEntityManager::DEFAULT_RESOURCE_CLASS, null, self::CURRENT_USER_IDENTIFIER);
        $this->addGrant($manageGrant->getAuthorizationResource(), 'read', self::CURRENT_USER_IDENTIFIER);
        $this->addGrant($manageGrant->getAuthorizationResource(), 'post', self::ANOTHER_USER_IDENTIFIER);

        $grantedActions = $this->grantedActionsProviderTester->getItem(
            TestEntityManager::DEFAULT_RESOURCE_CLASS.':');
        assert($grantedActions instanceof GrantedActions);
        $this->assertIsPermutationOf([AuthorizationService::MANAGE_ACTION, 'read'], $grantedActions->getActions());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertNull($grantedActions->getResourceIdentifier());

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $grantedActions = $this->grantedActionsProviderTester->getItem(
            TestEntityManager::DEFAULT_RESOURCE_CLASS.':');
        assert($grantedActions instanceof GrantedActions);
        $this->assertIsPermutationOf(['post'], $grantedActions->getActions());
        $this->assertEquals(TestEntityManager::DEFAULT_RESOURCE_CLASS, $grantedActions->getResourceClass());
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
