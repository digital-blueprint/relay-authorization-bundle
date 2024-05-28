<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Rest\ResourceActionGrantProvider;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class ResourceActionGrantProviderTest extends AbstractResourceActionGrantControllerTestCase
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

        // test pagination
        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection([
            'page' => 1,
            'perPage' => 2,
        ]);
        $this->assertCount(2, $resourceActionGrantCollection);
        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantCollection[0]->getIdentifier());
        $this->assertEquals($resourceActionGrant2->getIdentifier(), $resourceActionGrantCollection[1]->getIdentifier());

        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection([
            'page' => 2,
            'perPage' => 2,
        ]);
        $this->assertEquals($resourceActionGrant4->getIdentifier(), $resourceActionGrantCollection[0]->getIdentifier());
    }
}
