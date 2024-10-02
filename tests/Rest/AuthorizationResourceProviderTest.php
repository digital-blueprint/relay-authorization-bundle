<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Rest\AuthorizationResourceProvider;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class AuthorizationResourceProviderTest extends AbstractResourceActionGrantControllerAuthorizationServiceTestCase
{
    private DataProviderTester $resourceProviderTester;

    protected function setUp(): void
    {
        parent::setUp();

        $resourceProvider = new AuthorizationResourceProvider($this->authorizationService);
        $this->resourceProviderTester = DataProviderTester::create($resourceProvider, AuthorizationResource::class);
    }

    public function testGetResourceItemAsManager(): void
    {
        $resource = $this->addResource();
        $this->addManageGrant($resource);

        $resourceItem = $this->resourceProviderTester->getItem(
            $resource->getIdentifier());

        $this->assertEquals($resource->getIdentifier(), $resourceItem->getIdentifier());
        $this->assertEquals($resource->getResourceClass(), $resourceItem->getResourceClass());
        $this->assertEquals($resource->getResourceIdentifier(), $resourceItem->getResourceIdentifier());
        $this->assertTrue($resource->getWritable());
    }

    public function testGetResourceItemNotAsManager(): void
    {
        // we are not manager, just resource reader -> get resource is ok
        $resource = $this->addResource();
        $this->addManageGrant($resource, self::CURRENT_USER_IDENTIFIER.'_2');
        $this->addGrant($resource, 'read');

        $resourceItem = $this->resourceProviderTester->getItem(
            $resource->getIdentifier());

        $this->assertEquals($resource->getIdentifier(), $resourceItem->getIdentifier());
        $this->assertEquals($resource->getResourceClass(), $resourceItem->getResourceClass());
        $this->assertEquals($resource->getResourceIdentifier(), $resourceItem->getResourceIdentifier());
        $this->assertFalse($resource->getWritable());
    }

    public function testGetResourceItemNotFound(): void
    {
        $this->assertNull($this->resourceProviderTester->getItem(Uuid::uuid7()->toString()));
    }

    public function testGetResourceItemForbidden(): void
    {
        $manageGrant = $this->addResourceAndManageGrant('resourceClass', 'resourceIdentifier',
            self::CURRENT_USER_IDENTIFIER.'_2');

        try {
            $this->resourceProviderTester->getItem($manageGrant->getAuthorizationResource()->getIdentifier());
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testGetResourceCollection(): void
    {
        // expecting:
        // * all resources that the current user ('userIdentifier') has a grant for
        $resource = $this->addResource();
        $this->addManageGrant($resource);

        $resourceCollection = $this->resourceProviderTester->getCollection();

        $this->assertCount(1, $resourceCollection);
        $this->assertEquals($resource->getIdentifier(), $resourceCollection[0]->getIdentifier());
        $this->assertEquals($resource->getResourceClass(), $resourceCollection[0]->getResourceClass());
        $this->assertEquals($resource->getResourceIdentifier(), $resourceCollection[0]->getResourceIdentifier());
        $this->assertTrue($resourceCollection[0]->getWritable());
    }

    public function testGetResourceCollection2(): void
    {
        // expecting:
        // * all resources that the current user ('userIdentifier') has a grant for
        $resource = $this->addResource('resourceClass', 'resourceIdentifier');
        $this->addManageGrant($resource);
        $this->addResourceAndManageGrant('resourceClass', 'resourceIdentifier_2', self::ANOTHER_USER_IDENTIFIER);

        $resourceCollection = $this->resourceProviderTester->getCollection();

        $this->assertCount(1, $resourceCollection);
        $this->assertEquals($resource->getIdentifier(), $resourceCollection[0]->getIdentifier());
        $this->assertEquals($resource->getResourceClass(), $resourceCollection[0]->getResourceClass());
        $this->assertEquals($resource->getResourceIdentifier(), $resourceCollection[0]->getResourceIdentifier());
        $this->assertTrue($resourceCollection[0]->getWritable());
    }

    public function testGetResourceCollection3(): void
    {
        // expecting:
        // * all resources that the current user ('userIdentifier') has a grant for
        $resource1 = $this->addResource('resourceClass', 'resourceIdentifier');
        $this->addManageGrant($resource1);
        $resource2 = $this->addResource('resourceClass', 'resourceIdentifier_2');
        $this->addManageGrant($resource2, self::ANOTHER_USER_IDENTIFIER);
        $this->addGrant($resource2, 'write');

        $resourceCollection = $this->resourceProviderTester->getCollection();

        $this->assertCount(2, $resourceCollection);
        $this->assertCount(1, $this->selectWhere($resourceCollection, function ($resource) use ($resource1) {
            return $resource->getIdentifier() === $resource1->getIdentifier()
                && $resource->getWritable() === true;
        }));
        $this->assertCount(1, $this->selectWhere($resourceCollection, function ($resource) use ($resource2) {
            return $resource->getIdentifier() === $resource2->getIdentifier()
                && $resource->getWritable() === false;
        }));
    }

    public function testGetResourceCollectionPagination(): void
    {
        // expecting:
        // * all resources that the current user ('userIdentifier') has a grant for
        $resource1 = $this->addResource('resourceClass', 'resourceIdentifier');
        $this->addManageGrant($resource1);
        $resource2 = $this->addResource('resourceClass', 'resourceIdentifier_2');
        $this->addManageGrant($resource2, self::ANOTHER_USER_IDENTIFIER);
        $this->addGrant($resource2, 'write');
        $resource3 = $this->addResource('resourceClass', 'resourceIdentifier_3');
        $this->addManageGrant($resource3, self::ANOTHER_USER_IDENTIFIER);
        $this->addGrant($resource3, 'read');

        $resourceCollection = $this->resourceProviderTester->getCollection();

        $this->assertCount(3, $resourceCollection);
        $this->assertCount(1, $this->selectWhere($resourceCollection, function ($resource) use ($resource1) {
            return $resource->getIdentifier() === $resource1->getIdentifier()
                && $resource->getWritable() === true;
        }));
        $this->assertCount(1, $this->selectWhere($resourceCollection, function ($resource) use ($resource2) {
            return $resource->getIdentifier() === $resource2->getIdentifier()
                && $resource->getWritable() === false;
        }));
        $this->assertCount(1, $this->selectWhere($resourceCollection, function ($resource) use ($resource3) {
            return $resource->getIdentifier() === $resource3->getIdentifier()
                && $resource->getWritable() === false;
        }));

        $resourcePage1 = $this->resourceProviderTester->getCollection([
            'page' => 1,
            'perPage' => 2,
        ]);
        $this->assertCount(2, $resourcePage1);

        $resourcePage2 = $this->resourceProviderTester->getCollection([
            'page' => 2,
            'perPage' => 2,
        ]);
        $this->assertCount(1, $resourcePage2);

        $resourceCollection = array_merge($resourcePage1, $resourcePage2);
        $this->assertCount(1, $this->selectWhere($resourceCollection, function ($resource) use ($resource1) {
            return $resource->getIdentifier() === $resource1->getIdentifier()
                && $resource->getWritable() === true;
        }));
        $this->assertCount(1, $this->selectWhere($resourceCollection, function ($resource) use ($resource2) {
            return $resource->getIdentifier() === $resource2->getIdentifier()
                && $resource->getWritable() === false;
        }));
        $this->assertCount(1, $this->selectWhere($resourceCollection, function ($resource) use ($resource3) {
            return $resource->getIdentifier() === $resource3->getIdentifier()
                && $resource->getWritable() === false;
        }));
    }
}
