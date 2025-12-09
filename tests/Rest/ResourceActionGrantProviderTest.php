<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Rest\Common;
use Dbp\Relay\AuthorizationBundle\Rest\ResourceActionGrantProvider;
use Dbp\Relay\AuthorizationBundle\Tests\TestResources;
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

        $resourceActionGrantProvider = new ResourceActionGrantProvider($this->authorizationService);
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
        $this->assertIsPermutationOf($resourceActionGrant->getGrantedActions(), ['delete']);
    }

    public function testGetResourceActionGrantItemWithDynamicGroupEverybodyWithManageRights(): void
    {
        $resourceActionGrant = $this->addResourceAndManageGrant();
        $readGrant = $this->addResourceActionGrant($resourceActionGrant->getAuthorizationResource(), 'read', dynamicGroupIdentifier: 'everybody');

        $readGrantItem = $this->resourceActionGrantProviderTester->getItem(
            $readGrant->getIdentifier());
        $this->assertEquals($readGrant->getIdentifier(), $readGrantItem->getIdentifier());
        $this->assertEquals($readGrant->getAuthorizationResource()->getIdentifier(), $readGrantItem->getAuthorizationResource()->getIdentifier());
        $this->assertEquals('read', $readGrantItem->getAction());
        $this->assertEquals('everybody', $readGrantItem->getDynamicGroupIdentifier());
        $this->assertIsPermutationOf($readGrantItem->getGrantedActions(), ['delete']);
    }

    public function testGetResourceActionGrantItemWithDynamicGroupEverybodyWithoutManageRights(): void
    {
        $resourceActionGrant = $this->addResourceAndManageGrant();
        $readGrant = $this->addResourceActionGrant($resourceActionGrant->getAuthorizationResource(), 'read', dynamicGroupIdentifier: 'everybody');

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $readGrantItem = $this->resourceActionGrantProviderTester->getItem(
            $readGrant->getIdentifier());
        $this->assertEquals($readGrant->getIdentifier(), $readGrantItem->getIdentifier());
        $this->assertEquals($readGrant->getAuthorizationResource()->getIdentifier(), $readGrantItem->getAuthorizationResource()->getIdentifier());
        $this->assertEquals('read', $readGrantItem->getAction());
        $this->assertEquals('everybody', $readGrantItem->getDynamicGroupIdentifier());
        $this->assertIsPermutationOf($readGrantItem->getGrantedActions(), []);
    }

    public function testGetResourceActionGrantItemNotFound(): void
    {
        $this->assertNull($this->resourceActionGrantProviderTester->getItem(Uuid::uuid7()->toString()));
    }

    public function testGetResourceActionGrantItemForbidden(): void
    {
        $resourceActionGrant = $this->addResourceAndManageGrant(self::TEST_RESOURCE_CLASS, 'resourceIdentifier',
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
        $this->authorizationService->setDebug(true);
        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection();
        $this->authorizationService->setDebug(false);

        $this->assertCount(1, $resourceActionGrantCollection);
        $resourceActionGrantItem = $resourceActionGrantCollection[0];
        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantItem->getIdentifier());
        $this->assertEquals($resourceActionGrant->getResourceClass(), $resourceActionGrantItem->getResourceClass());
        $this->assertEquals($resourceActionGrant->getResourceIdentifier(), $resourceActionGrantItem->getResourceIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantItem->getAction());
        $this->assertEquals($resourceActionGrant->getUserIdentifier(), $resourceActionGrantItem->getUserIdentifier());
        $this->assertIsPermutationOf($resourceActionGrantItem->getGrantedActions(), ['delete']);
    }

    public function testGetResourceActionGrantCollection2(): void
    {
        $resourceActionGrant = $this->addResourceAndManageGrant();
        $this->addResourceAndManageGrant(self::TEST_RESOURCE_CLASS, 'resourceIdentifier_2',
            'userIdentifier_2');
        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection();

        $this->assertCount(1, $resourceActionGrantCollection);
        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantCollection[0]->getIdentifier());
    }

    public function testGetResourceActionGrantCollection3(): void
    {
        // expecting:
        // * all grants of resources that the current user (self::CURRENT_USER_IDENTIFIER) is manager of and the
        // * grants of the user (self::CURRENT_USER_IDENTIFIER) of other resources
        $resourceActionGrant1 = $this->addResourceAndManageGrant();
        $resourceActionGrant2 = $this->addResourceAndManageGrant(self::TEST_RESOURCE_CLASS, 'resourceIdentifier_2',
            'userIdentifier_2');
        $resourceActionGrant3 = $this->addGrant($resourceActionGrant2->getAuthorizationResource(),
            'read', self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection();

        $this->assertCount(2, $resourceActionGrantCollection);
        $this->assertCount(1, $this->selectWhere($resourceActionGrantCollection,
            function (ResourceActionGrant $resourceActionGrant) use ($resourceActionGrant1) {
                return $resourceActionGrant->getIdentifier() === $resourceActionGrant1->getIdentifier()
                    && $this->isPermutationOf($resourceActionGrant->getGrantedActions(), ['delete']);
            }));
        $this->assertCount(1, $this->selectWhere($resourceActionGrantCollection,
            function (ResourceActionGrant $resourceActionGrant) use ($resourceActionGrant3) {
                return $resourceActionGrant->getIdentifier() === $resourceActionGrant3->getIdentifier()
                    && $this->isPermutationOf($resourceActionGrant->getGrantedActions(), []);
            }));
    }

    public function testGetResourceActionGrantCollection4(): void
    {
        // expecting:
        // * all grants of resources that the current user (self::CURRENT_USER_IDENTIFIER) is manager of and the
        // * grants of the user (self::CURRENT_USER_IDENTIFIER) of other resources
        $resource1Manage = $this->addResourceAndManageGrant();
        $resource1Read = $this->addGrant($resource1Manage->getAuthorizationResource(),
            'read', 'userIdentifier_2');
        $resource2Manage = $this->addResourceAndManageGrant(self::TEST_RESOURCE_CLASS, 'resourceIdentifier_2',
            'userIdentifier_2');
        $resource2Read = $this->addGrant($resource2Manage->getAuthorizationResource(),
            'read', self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection();

        $this->assertCount(3, $resourceActionGrantCollection);
        $this->assertCount(1, $this->selectWhere($resourceActionGrantCollection,
            function (ResourceActionGrant $resourceActionGrant) use ($resource1Manage) {
                return $resourceActionGrant->getIdentifier() === $resource1Manage->getIdentifier()
                    && $this->isPermutationOf($resourceActionGrant->getGrantedActions(), ['delete']);
            }));
        $this->assertCount(1, $this->selectWhere($resourceActionGrantCollection,
            function (ResourceActionGrant $resourceActionGrant) use ($resource1Read) {
                return $resourceActionGrant->getIdentifier() === $resource1Read->getIdentifier()
                    && $this->isPermutationOf($resourceActionGrant->getGrantedActions(), ['delete']);
            }));
        $this->assertCount(1, $this->selectWhere($resourceActionGrantCollection,
            function (ResourceActionGrant $resourceActionGrant) use ($resource2Read) {
                return $resourceActionGrant->getIdentifier() === $resource2Read->getIdentifier()
                    && $this->isPermutationOf($resourceActionGrant->getGrantedActions(), []);
            }));

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
        $this->assertCount(1, $this->selectWhere($resourceActionGrantCollection,
            function (ResourceActionGrant $resourceActionGrant) use ($resource1Manage) {
                return $resourceActionGrant->getIdentifier() === $resource1Manage->getIdentifier()
                    && $this->isPermutationOf($resourceActionGrant->getGrantedActions(), ['delete']);
            }));
        $this->assertCount(1, $this->selectWhere($resourceActionGrantCollection,
            function (ResourceActionGrant $resourceActionGrant) use ($resource1Read) {
                return $resourceActionGrant->getIdentifier() === $resource1Read->getIdentifier()
                    && $this->isPermutationOf($resourceActionGrant->getGrantedActions(), ['delete']);
            }));
        $this->assertCount(1, $this->selectWhere($resourceActionGrantCollection,
            function (ResourceActionGrant $resourceActionGrant) use ($resource2Read) {
                return $resourceActionGrant->getIdentifier() === $resource2Read->getIdentifier()
                    && $this->isPermutationOf($resourceActionGrant->getGrantedActions(), []);
            }));
    }

    public function testGetResourceActionGrantCollectionWithFilters(): void
    {
        $resource1Manage = $this->addResourceAndManageGrant(
            self::TEST_RESOURCE_CLASS,
            'resourceIdentifier');
        $resource1Read = $this->addGrant(
            $resource1Manage->getAuthorizationResource(),
            TestResources::READ_ACTION,
            'userIdentifier_2');

        $resource2Manage = $this->addResourceAndManageGrant(
            self::TEST_RESOURCE_CLASS_2,
            'resourceIdentifier_2',
            'userIdentifier_2');
        $resource2Update = $this->addGrant($resource2Manage->getAuthorizationResource(),
            TestResources::UPDATE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $resourceCollection = $this->addResourceAndManageGrant(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            'userIdentifier_3'
        );
        $resourceCollectionCreate = $this->addGrant($resourceCollection->getAuthorizationResource(),
            TestResources::CREATE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection();

        $this->assertCount(4, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Manage, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Read, $resourceActionGrantCollection);
        $this->assertContainsResource($resource2Update, $resourceActionGrantCollection);
        $this->assertContainsResource($resourceCollectionCreate, $resourceActionGrantCollection);

        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection([
            Common::RESOURCE_CLASS_QUERY_PARAMETER => self::TEST_RESOURCE_CLASS,
        ]);

        $this->assertCount(3, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Manage, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Read, $resourceActionGrantCollection);
        $this->assertContainsResource($resourceCollectionCreate, $resourceActionGrantCollection);

        // --------------------------------------------------------------------
        // test pagination:
        $resourceActionGrantPage1 = $this->resourceActionGrantProviderTester->getCollection([
            Common::RESOURCE_CLASS_QUERY_PARAMETER => self::TEST_RESOURCE_CLASS,
            'page' => 1,
            'perPage' => 2,
        ]);
        $this->assertCount(2, $resourceActionGrantPage1);

        $resourceActionGrantPage2 = $this->resourceActionGrantProviderTester->getCollection([
            Common::RESOURCE_CLASS_QUERY_PARAMETER => self::TEST_RESOURCE_CLASS,
            'page' => 2,
            'perPage' => 2,
        ]);
        $this->assertCount(1, $resourceActionGrantPage2);

        $resourceActionGrantCollection = array_merge($resourceActionGrantPage1, $resourceActionGrantPage2);
        $this->assertCount(3, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Manage, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Read, $resourceActionGrantCollection);
        $this->assertContainsResource($resourceCollectionCreate, $resourceActionGrantCollection);
        // --------------------------------------------------------------------

        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection([
            Common::RESOURCE_CLASS_QUERY_PARAMETER => self::TEST_RESOURCE_CLASS_2,
        ]);
        $this->assertCount(1, $resourceActionGrantCollection);
        $this->assertContainsResource($resource2Update, $resourceActionGrantCollection);

        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection([
            Common::RESOURCE_CLASS_QUERY_PARAMETER => 'resourceClass_foo',
        ]);
        $this->assertCount(0, $resourceActionGrantCollection);

        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection([
            Common::RESOURCE_CLASS_QUERY_PARAMETER => self::TEST_RESOURCE_CLASS,
            'resourceIdentifier' => 'resourceIdentifier',
        ]);
        $this->assertCount(2, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Manage, $resourceActionGrantCollection);
        $this->assertContainsResource($resource1Read, $resourceActionGrantCollection);

        // --------------------------------------------------------------------
        // test pagination:
        $resourceActionGrantPage1 = $this->resourceActionGrantProviderTester->getCollection([
            Common::RESOURCE_CLASS_QUERY_PARAMETER => self::TEST_RESOURCE_CLASS,
            'resourceIdentifier' => 'resourceIdentifier',
            'page' => 1,
            'perPage' => 1,
        ]);
        $this->assertCount(1, $resourceActionGrantPage1);

        $resourceActionGrantPage2 = $this->resourceActionGrantProviderTester->getCollection([
            Common::RESOURCE_CLASS_QUERY_PARAMETER => self::TEST_RESOURCE_CLASS,
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
            Common::RESOURCE_CLASS_QUERY_PARAMETER => self::TEST_RESOURCE_CLASS,
            'resourceIdentifier' => 'null',
        ]);
        $this->assertCount(1, $resourceActionGrantCollection);
        $this->assertContainsResource($resourceCollectionCreate, $resourceActionGrantCollection);

        $resourceActionGrantCollection = $this->resourceActionGrantProviderTester->getCollection([
            Common::RESOURCE_CLASS_QUERY_PARAMETER => self::TEST_RESOURCE_CLASS,
            'resourceIdentifier' => 'resourceIdentifier_foo',
        ]);
        $this->assertCount(0, $resourceActionGrantCollection);
    }
}
