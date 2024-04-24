<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Rest\ResourceActionGrantProcessor;
use Dbp\Relay\AuthorizationBundle\Rest\ResourceActionGrantProvider;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProcessorTester;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class ResourceActionGrantProviderControllerTest extends AbstractControllerTest
{
    private DataProviderTester $resourceActionGrantProviderTester;
    private DataProcessorTester $resourceActionGrantProcessorTester;

    protected function setUp(): void
    {
        parent::setUp();

        $resourceActionGrantProvider = new ResourceActionGrantProvider(
            $this->internalResourceActionGrantService, $this->authorizationService);
        $this->resourceActionGrantProviderTester = new DataProviderTester($resourceActionGrantProvider, ResourceActionGrant::class);
        DataProviderTester::setUp($resourceActionGrantProvider);

        $resourceActionGrantProcessor = new ResourceActionGrantProcessor(
            $this->internalResourceActionGrantService, $this->authorizationService);
        $this->resourceActionGrantProcessorTester = new DataProcessorTester($resourceActionGrantProcessor, ResourceActionGrant::class);
        DataProcessorTester::setUp($resourceActionGrantProcessor);
    }

    public function testGetResourceActionGrantItem(): void
    {
        $resourceActionGrant = $this->addResourceAndManageGrant();
        $resourceActionGrantItem = $this->resourceActionGrantProviderTester->getItem(
            $resourceActionGrant->getIdentifier());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantItem->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantItem->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantItem->getAction());
        $this->assertEquals($resourceActionGrant->getUserIdentifier(), $resourceActionGrantItem->getUserIdentifier());
    }

    public function testGetResourceActionGrantItemNotFound(): void
    {
        $this->assertNull($this->resourceActionGrantProviderTester->getItem(Uuid::uuid7()->toString()));
    }

    public function testGetResourceActionGrantItemForbidden(): void
    {
        $resourceActionGrant = $this->addResourceAndManageGrant('resourceClass', 'resourceIdentifier',
            self::CURRENT_USER_IDENTIFIER.'_2');
        try {
            $this->resourceActionGrantProviderTester->getItem(
                $resourceActionGrant->getIdentifier());
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testGetResourceActionGrantCollection(): void
    {
        $resourceActionGrant = $this->addResourceAndManageGrant();
        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection();

        $this->assertCount(1, $resourceActionGrantCollection);
        $resourceActionGrantItem = $resourceActionGrantCollection[0];
        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantItem->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantItem->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantItem->getAction());
        $this->assertEquals($resourceActionGrant->getUserIdentifier(), $resourceActionGrantItem->getUserIdentifier());
    }

    public function testGetResourceActionGrantCollection2(): void
    {
        $resourceActionGrant = $this->addResourceAndManageGrant();
        $this->addResourceAndManageGrant('resourceClass', 'resourceIdentifier_2',
            'userIdentifier_2');
        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection();

        $this->assertCount(1, $resourceActionGrantCollection);
        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantCollection[0]->getIdentifier());
    }

    public function testGetResourceActionGrantCollection3(): void
    {
        // expecting:
        // * all grants of resources that the current user ('userIdentifier') is manager of and the
        // * grants of the user ('userIdentifier') of other resources
        $resourceActionGrant = $this->addResourceAndManageGrant();
        $resourceActionGrant2 = $this->addResourceAndManageGrant('resourceClass', 'resourceIdentifier_2',
            'userIdentifier_2');
        $resourceActionGrant3 = $this->addGrant($resourceActionGrant2->getAuthorizationResource(),
            'read', 'userIdentifier');

        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection();

        $this->assertCount(2, $resourceActionGrantCollection);
        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantCollection[0]->getIdentifier());
        $this->assertEquals($resourceActionGrant3->getIdentifier(), $resourceActionGrantCollection[1]->getIdentifier());
    }

    public function testGetResourceActionGrantCollection4(): void
    {
        // expecting:
        // * all grants of resources that the current user ('userIdentifier') is manager of and the
        // * grants of the user ('userIdentifier') of other resources
        $resourceActionGrant = $this->addResourceAndManageGrant();
        $resourceActionGrant2 = $this->addGrant($resourceActionGrant->getAuthorizationResource(),
            'read', 'userIdentifier_2');
        $resourceActionGrant3 = $this->addResourceAndManageGrant('resourceClass', 'resourceIdentifier_2',
            'userIdentifier_2');
        $resourceActionGrant4 = $this->addGrant($resourceActionGrant2->getAuthorizationResource(),
            'read', 'userIdentifier');

        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection();

        $this->assertCount(3, $resourceActionGrantCollection);
        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantCollection[0]->getIdentifier());
        $this->assertEquals($resourceActionGrant2->getIdentifier(), $resourceActionGrantCollection[1]->getIdentifier());
        $this->assertEquals($resourceActionGrant4->getIdentifier(), $resourceActionGrantCollection[2]->getIdentifier());
    }

    public function testCreateResourceActionGrant(): void
    {
        $manageResourceGrant = $this->addResourceAndManageGrant();
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($manageResourceGrant->getAuthorizationResource());
        $resourceActionGrant->setAction('action');
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);
        $resourceActionGrantItem = $this->getResourceActionGrant($resourceActionGrant->getIdentifier());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantItem->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantItem->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantItem->getAction());
        $this->assertEquals($resourceActionGrant->getUserIdentifier(), $resourceActionGrantItem->getUserIdentifier());
    }

    public function testCreateResourceActionGrantItemForbidden1(): void
    {
        // you need to be a resource manager to be authorized to create grants for it
        $manageResourceGrant = $this->addResourceAndManageGrant('resourceClass', 'resourceIdentifier',
            self::CURRENT_USER_IDENTIFIER.'_2');
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($manageResourceGrant->getAuthorizationResource());
        $resourceActionGrant->setAction('action');
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testCreateResourceActionGrantItemForbidden2(): void
    {
        // a read grant is not enough to create new grants for the resource
        $manageResourceGrant = $this->addResourceAndManageGrant('resourceClass', 'resourceIdentifier',
            self::CURRENT_USER_IDENTIFIER.'_2');
        $this->addGrant($manageResourceGrant->getAuthorizationResource(), 'read', self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($manageResourceGrant->getAuthorizationResource());
        $resourceActionGrant->setAction('write');
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->resourceActionGrantProcessorTester->addItem($resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testDeleteResourceActionGrant(): void
    {
        $manageResourceGrant = $this->addResourceAndManageGrant();
        $this->assertNotNull($this->getResourceActionGrant($manageResourceGrant->getIdentifier()));

        $this->resourceActionGrantProcessorTester->removeItem($manageResourceGrant->getIdentifier(), $manageResourceGrant);
        $this->assertNull($this->getResourceActionGrant($manageResourceGrant->getIdentifier()));
    }

    public function testDeleteResourceActionGrantItemForbidden(): void
    {
        // only the resource manager is authorized to delete grants for a resource
        $manageResourceGrant = $this->addResourceAndManageGrant('resourceClass', 'resourceIdentifier',
            self::CURRENT_USER_IDENTIFIER.'_2');
        $resourceActionGrant = $this->addGrant($manageResourceGrant->getAuthorizationResource(),
            'read', self::CURRENT_USER_IDENTIFIER);

        try {
            $this->resourceActionGrantProcessorTester->removeItem($resourceActionGrant->getIdentifier(), $resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }
}
