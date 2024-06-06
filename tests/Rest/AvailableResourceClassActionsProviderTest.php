<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassActions;
use Dbp\Relay\AuthorizationBundle\Rest\AvailableResourceClassActionsProvider;
use Dbp\Relay\AuthorizationBundle\Rest\Common;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractTestCase;
use Dbp\Relay\AuthorizationBundle\Tests\EventSubscriber\TestGetAvailableResourceClassActionsEventSubscriber;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;

class AvailableResourceClassActionsProviderTest extends AbstractTestCase
{
    private DataProviderTester $availableResourceClassActionsProviderTester;

    protected function setUp(): void
    {
        parent::setUp();

        $provider = new AvailableResourceClassActionsProvider($this->internalResourceActionGrantService);
        $this->availableResourceClassActionsProviderTester = DataProviderTester::create($provider,
            AvailableResourceClassActions::class,
            ['AuthorizationAvailableResourceClassActions:output']);
    }

    public function testGetAvailableResourceClassActions(): void
    {
        $availableResourceClassActions =
            $this->availableResourceClassActionsProviderTester->getItem('', [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS,
            ]);

        $expectedItemActions = TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_ITEM_ACTIONS;
        if (!in_array(AuthorizationService::MANAGE_ACTION, $expectedItemActions, true)) {
            $expectedItemActions[] = AuthorizationService::MANAGE_ACTION;
        }
        $this->assertEquals($expectedItemActions, $availableResourceClassActions->getItemActions());
        $expectedCollectionActions = TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_COLLECTION_ACTIONS;
        if (!in_array(AuthorizationService::MANAGE_ACTION, $expectedCollectionActions, true)) {
            $expectedCollectionActions[] = AuthorizationService::MANAGE_ACTION;
        }
        $this->assertEquals($expectedCollectionActions, $availableResourceClassActions->getCollectionActions());
    }

    public function testGetAvailableResourceClassActionsNoSubscribers(): void
    {
        $availableResourceClassActions =
            $this->availableResourceClassActionsProviderTester->getItem('', [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => 'NoSubscribersResourceClass',
            ]);

        $this->assertEquals(null, $availableResourceClassActions->getItemActions());
        $this->assertEquals(null, $availableResourceClassActions->getCollectionActions());
    }
}
