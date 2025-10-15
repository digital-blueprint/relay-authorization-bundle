<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Rest\Common;
use Dbp\Relay\AuthorizationBundle\Rest\ResourceActionGrantProvider;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class ResourceActionGrantProviderTest extends AbstractResourceActionGrantControllerAuthorizationServiceTestCase
{
    private DataProviderTester $resourceActionGrantProviderTester;

    protected function setUp(): void
    {
        parent::setUp();

        $resourceActionGrantProvider = new ResourceActionGrantProvider(
            $this->internalResourceActionGrantService, $this->authorizationService);
        $this->resourceActionGrantProviderTester = DataProviderTester::create($resourceActionGrantProvider, ResourceActionGrant::class);
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

    public function testGetResourceActionGrantItemWithDynamicGroupEverybody(): void
    {
        $resourceActionGrant = $this->addResourceAndManageGrant();
        $readGrant = $this->addResourceActionGrant($resourceActionGrant->getAuthorizationResource(), 'read', dynamicGroupIdentifier: 'everybody');
        $readGrantItem = $this->resourceActionGrantProviderTester->getItem(
            $readGrant->getIdentifier());

        $this->assertEquals($readGrant->getIdentifier(), $readGrantItem->getIdentifier());
        $this->assertEquals($readGrant->getAuthorizationResource()->getIdentifier(), $readGrantItem->getAuthorizationResource()->getIdentifier());
        $this->assertEquals('read', $readGrantItem->getAction());
        $this->assertEquals('everybody', $readGrantItem->getDynamicGroupIdentifier());
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
        $this->assertEquals($resourceActionGrant->getResourceClass(), $resourceActionGrantItem->getResourceClass());
        $this->assertEquals($resourceActionGrant->getResourceIdentifier(), $resourceActionGrantItem->getResourceIdentifier());
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
        $resource1Manage = $this->addResourceAndManageGrant();
        $resource1Read = $this->addGrant($resource1Manage->getAuthorizationResource(),
            'read', 'userIdentifier_2');
        $resource2Manage = $this->addResourceAndManageGrant('resourceClass', 'resourceIdentifier_2',
            'userIdentifier_2');
        $resource2Read = $this->addGrant($resource2Manage->getAuthorizationResource(),
            'read', 'userIdentifier');

        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection();

        $this->assertCount(3, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Manage, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Read, $resourceActionGrantCollection);
        $this->assertContainsResource($resource2Read, $resourceActionGrantCollection);

        // test pagination
        $resourceActionGrantPage1 = $this->resourceActionGrantProviderTester->getCollection([
            'page' => 1,
            'perPage' => 2,
        ]);
        $this->assertCount(2, $resourceActionGrantPage1);

        $resourceActionGrantPage2 = $this->resourceActionGrantProviderTester->getCollection([
            'page' => 2,
            'perPage' => 2,
        ]);
        $this->assertCount(1, $resourceActionGrantPage2);

        $resourceActionGrantCollection = array_merge($resourceActionGrantPage1, $resourceActionGrantPage2);
        $this->assertCount(3, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Manage, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Read, $resourceActionGrantCollection);
        $this->assertContainsResource($resource2Read, $resourceActionGrantCollection);
    }

    public function testGetResourceActionGrantCollectionWithFilters(): void
    {
        $resource1Manage = $this->addResourceAndManageGrant('resourceClass', 'resourceIdentifier');
        $resource1Read = $this->addGrant($resource1Manage->getAuthorizationResource(),
            'read', 'userIdentifier_2');

        $resource2Manage = $this->addResourceAndManageGrant('resourceClass_2', 'resourceIdentifier_2',
            'userIdentifier_2');
        $resource2Read = $this->addGrant($resource2Manage->getAuthorizationResource(),
            'read', 'userIdentifier');

        $resourceCollection = $this->addResourceAndManageGrant('resourceClass', null,
            'userIdentifier_3');
        $resourceCollectionRead = $this->addGrant($resourceCollection->getAuthorizationResource(),
            'read', 'userIdentifier');

        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection();

        $this->assertCount(4, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Manage, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Read, $resourceActionGrantCollection);
        $this->assertContainsResource($resource2Read, $resourceActionGrantCollection);
        $this->assertContainsResource($resourceCollectionRead, $resourceActionGrantCollection);

        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection([
            'resourceClass' => 'resourceClass',
        ]);

        $this->assertCount(3, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Manage, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Read, $resourceActionGrantCollection);
        $this->assertContainsResource($resourceCollectionRead, $resourceActionGrantCollection);

        // --------------------------------------------------------------------
        // test pagination:
        $resourceActionGrantPage1 = $this->resourceActionGrantProviderTester->getCollection([
            'resourceClass' => 'resourceClass',
            'page' => 1,
            'perPage' => 2,
        ]);
        $this->assertCount(2, $resourceActionGrantPage1);

        $resourceActionGrantPage2 = $this->resourceActionGrantProviderTester->getCollection([
            'resourceClass' => 'resourceClass',
            'page' => 2,
            'perPage' => 2,
        ]);
        $this->assertCount(1, $resourceActionGrantPage2);

        $resourceActionGrantCollection = array_merge($resourceActionGrantPage1, $resourceActionGrantPage2);
        $this->assertCount(3, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Manage, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Read, $resourceActionGrantCollection);
        $this->assertContainsResource($resourceCollectionRead, $resourceActionGrantCollection);
        // --------------------------------------------------------------------

        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection([
            'resourceClass' => 'resourceClass_2',
        ]);
        $this->assertCount(1, $resourceActionGrantCollection);
        $this->assertContainsResource($resource2Read, $resourceActionGrantCollection);

        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection([
            'resourceClass' => 'resourceClass_foo',
        ]);
        $this->assertCount(0, $resourceActionGrantCollection);

        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection([
            'resourceClass' => 'resourceClass',
            'resourceIdentifier' => 'resourceIdentifier',
        ]);
        $this->assertCount(2, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Manage, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Read, $resourceActionGrantCollection);

        // --------------------------------------------------------------------
        // test pagination:
        $resourceActionGrantPage1 = $this->resourceActionGrantProviderTester->getCollection([
            'resourceClass' => 'resourceClass',
            'resourceIdentifier' => 'resourceIdentifier',
            'page' => 1,
            'perPage' => 1,
        ]);
        $this->assertCount(1, $resourceActionGrantPage1);

        $resourceActionGrantPage2 = $this->resourceActionGrantProviderTester->getCollection([
            'resourceClass' => 'resourceClass',
            'resourceIdentifier' => 'resourceIdentifier',
            'page' => 2,
            'perPage' => 1,
        ]);
        $this->assertCount(1, $resourceActionGrantPage2);

        $resourceActionGrantCollection = array_merge($resourceActionGrantPage1, $resourceActionGrantPage2);
        $this->assertContainsResource($resource1Manage, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Read, $resourceActionGrantCollection);
        // --------------------------------------------------------------------

        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection([
            'resourceClass' => 'resourceClass',
            'resourceIdentifier' => Common::IS_NULL_FILTER,
        ]);
        $this->assertCount(1, $resourceActionGrantCollection);
        $this->assertContainsResource($resourceCollectionRead, $resourceActionGrantCollection);

        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection([
            'resourceClass' => 'resourceClass',
            'resourceIdentifier' => 'resourceIdentifier_foo',
        ]);
        $this->assertCount(0, $resourceActionGrantCollection);
    }
}
