<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\API;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\GrantedActions;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractAuthorizationServiceTestCase;
use Dbp\Relay\AuthorizationBundle\Tests\TestResources;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

class ResourceActionGrantServiceTest extends AbstractAuthorizationServiceTestCase
{
    private ResourceActionGrantService $resourceActionGrantService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resourceActionGrantService = new ResourceActionGrantService(
            $this->authorizationService);
    }

    public function testAddResourceActionGrantWithManageAction(): void
    {
        $this->resourceActionGrantService->addResourceActionGrant(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            action: ResourceActionGrantService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);

        $resourcePersistence = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals($resourcePersistence->getIdentifier(), $resourcePersistence->getIdentifier());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourcePersistence->getResourceIdentifier());
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $resourcePersistence->getResourceClass());

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrant(
            $resourcePersistence->getIdentifier(), ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->assertSame($resourcePersistence->getIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getIdentifier());
        $this->assertSame(ResourceActionGrantService::MANAGE_ACTION, $resourceActionGrantPersistence->getAction());
        $this->assertSame(self::CURRENT_USER_IDENTIFIER, $resourceActionGrantPersistence->getUserIdentifier());
    }

    public function testAddAvailableResourceClassActions(): void
    {
        $resourceClass = 'MyResourceClass';
        $itemActions = [
            'view' => [
                'en' => 'View',
                'fr' => 'Voir',
            ],
            'edit' => [
                'en' => 'Edit',
                'fr' => 'Éditer',
            ],
        ];
        $collectionActions = [
            'create' => [
                'en' => 'Create',
                'fr' => 'Créer',
            ],
        ];
        $this->resourceActionGrantService->setAvailableResourceClassActions($resourceClass, $itemActions, $collectionActions);

        [$retrievedItemActions, $retrievedCollectionActions] =
            $this->internalResourceActionGrantService->getAvailableResourceClassActions($resourceClass);

        $this->assertArrayHasKey(AuthorizationService::MANAGE_ACTION, $retrievedItemActions);
        $this->assertArrayHasKey(AuthorizationService::MANAGE_ACTION, $retrievedCollectionActions);
        unset($retrievedItemActions[AuthorizationService::MANAGE_ACTION]);
        unset($retrievedCollectionActions[AuthorizationService::MANAGE_ACTION]);

        $this->assertEquals($itemActions, $retrievedItemActions);
        $this->assertEquals($collectionActions, $retrievedCollectionActions);

        // test again, to see if available resource class actions are cleared before adding the new ones
        $this->resourceActionGrantService->setAvailableResourceClassActions($resourceClass, $itemActions, $collectionActions);

        [$retrievedItemActions, $retrievedCollectionActions] =
            $this->internalResourceActionGrantService->getAvailableResourceClassActions($resourceClass);

        $this->assertArrayHasKey(AuthorizationService::MANAGE_ACTION, $retrievedItemActions);
        $this->assertArrayHasKey(AuthorizationService::MANAGE_ACTION, $retrievedCollectionActions);
        unset($retrievedItemActions[AuthorizationService::MANAGE_ACTION]);
        unset($retrievedCollectionActions[AuthorizationService::MANAGE_ACTION]);

        $this->assertEquals($itemActions, $retrievedItemActions);
        $this->assertEquals($collectionActions, $retrievedCollectionActions);
    }

    public function testAddResourceActionGrantWithAction(): void
    {
        $action = TestResources::WRITE_ACTION;
        $userIdentifier = self::ANOTHER_USER_IDENTIFIER;

        $this->resourceActionGrantService->addResourceActionGrant(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            action: $action,
            userIdentifier: $userIdentifier);

        $resourcePersistence = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrant($resourcePersistence->getIdentifier(), $action, $userIdentifier);
        $this->assertSame($resourcePersistence->getIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getIdentifier());
        $this->assertSame($action, $resourceActionGrantPersistence->getAction());
        $this->assertSame($userIdentifier, $resourceActionGrantPersistence->getUserIdentifier());
    }

    public function testAddResourceActionGrantWithRoleIdentifier(): void
    {
        $rag = $this->resourceActionGrantService->addResourceActionGrant(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            roleIdentifier: ResourceActionGrantService::MANAGER_ROLE_IDENTIFIER,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);

        $this->assertTrue(UuidV7::isValid($rag->getIdentifier()));
        $this->assertEquals(ResourceActionGrantService::MANAGER_ROLE_IDENTIFIER, $rag->getRole()->getIdentifier());
        $this->assertEquals(null, $rag->getAction());
        $this->assertEquals(null, $rag->getAvailableResourceClassAction());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $rag->getUserIdentifier());
        $this->assertEquals(null, $rag->getUserGroup());
        $this->assertEquals(null, $rag->getDynamicUserGroupIdentifier());

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier($rag->getIdentifier());
        $this->assertEquals($rag->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());
        $this->assertEquals($rag->getRole(), $resourceActionGrantPersistence->getRole());
        $this->assertEquals($rag->getAction(), $resourceActionGrantPersistence->getAction());
        $this->assertEquals($rag->getAvailableResourceClassAction(), $resourceActionGrantPersistence->getAvailableResourceClassAction());
        $this->assertEquals($rag->getUserIdentifier(), $resourceActionGrantPersistence->getUserIdentifier());
        $this->assertEquals($rag->getUserGroup(), $resourceActionGrantPersistence->getUserGroup());
        $this->assertEquals($rag->getDynamicUserGroupIdentifier(), $resourceActionGrantPersistence->getDynamicUserGroupIdentifier());
    }

    public function testRemoveGrantsForResource(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource(TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $resourceActionGrant = $this->testEntityManager->addResourceActionGrant(
            $resource, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER, null);

        $this->assertEquals($resource->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier())->getIdentifier());

        $this->resourceActionGrantService->removeGrantsForResource(TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier()));
    }

    public function testRemoveGrantsForResourceWithResourceType(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource(TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $resourceActionGrant = $this->testEntityManager->addResourceActionGrant(
            $resource, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER, null);

        $this->assertEquals($resource->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier())->getIdentifier());

        $this->resourceActionGrantService->removeGrantsForResource(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            resourceType: ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE);

        $this->assertEquals($resource->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier())->getIdentifier());

        $resource2 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            resourceType: ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE);
        $resourceActionGrant2 = $this->testEntityManager->addResourceActionGrant(
            $resource2, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER, null);

        $this->assertEquals($resource2->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource2->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant2->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant2->getIdentifier())->getIdentifier());

        $this->resourceActionGrantService->removeGrantsForResource(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            resourceType: null);

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier()));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant2->getIdentifier()));
    }

    public function testRemoveGrantsForResourceWithoutResourceClass(): void
    {
        $resource1 = $this->testEntityManager->addAuthorizationResource(TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $resourceActionGrant1 = $this->testEntityManager->addResourceActionGrant(
            $resource1,
            action: ResourceActionGrantService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);

        $this->assertEquals($resource1->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource1->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant1->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant1->getIdentifier())->getIdentifier());

        $resource2 = $this->testEntityManager->addAuthorizationResource(TestResources::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER);
        $resourceActionGrant2 = $this->testEntityManager->addResourceActionGrant(
            $resource2,
            action: ResourceActionGrantService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);

        $this->assertEquals($resource2->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource2->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant2->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant2->getIdentifier())->getIdentifier());

        $this->resourceActionGrantService->removeGrantsForResource(resourceIdentifier: self::TEST_RESOURCE_IDENTIFIER);

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource1->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant1->getIdentifier()));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant2->getIdentifier()));
    }

    public function testRemoveGrantsForResources(): void
    {
        $resource1 = $this->testEntityManager->addAuthorizationResource(TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier1');
        $resource2 = $this->testEntityManager->addAuthorizationResource(TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier2');
        $resource3 = $this->testEntityManager->addAuthorizationResource(TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier3');

        $resourceActionGrant1 = $this->testEntityManager->addResourceActionGrant(
            $resource1, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant2 = $this->testEntityManager->addResourceActionGrant(
            $resource2, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant3 = $this->testEntityManager->addResourceActionGrant(
            $resource3, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertEquals($resource1->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource1->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant1->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant1->getIdentifier())->getIdentifier());
        $this->assertEquals($resource2->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource2->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant2->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant2->getIdentifier())->getIdentifier());
        $this->assertEquals($resource3->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource3->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant3->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant3->getIdentifier())->getIdentifier());

        $this->resourceActionGrantService->removeGrantsForResources(TestResources::TEST_RESOURCE_CLASS, ['resourceIdentifier2', 'resourceIdentifier3']);

        $this->assertNotNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource1->getIdentifier()));
        $this->assertNotNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant1->getIdentifier()));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource3->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant3->getIdentifier()));
    }

    public function testRemoveGrantsForResourcesWithoutResourceClass(): void
    {
        $resource1 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier1');
        $resource2 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS_2, 'resourceIdentifier2');
        $resource3 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier3');

        $resourceActionGrant1 = $this->testEntityManager->addResourceActionGrant(
            $resource1, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant2 = $this->testEntityManager->addResourceActionGrant(
            $resource2, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant3 = $this->testEntityManager->addResourceActionGrant(
            $resource3, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertEquals($resource1->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource1->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant1->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant1->getIdentifier())->getIdentifier());
        $this->assertEquals($resource2->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource2->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant2->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant2->getIdentifier())->getIdentifier());
        $this->assertEquals($resource3->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource3->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant3->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant3->getIdentifier())->getIdentifier());

        $this->resourceActionGrantService->removeGrantsForResources(resourceIdentifiers: ['resourceIdentifier1', 'resourceIdentifier3']);

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource1->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant1->getIdentifier()));
        $this->assertNotNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource2->getIdentifier()));
        $this->assertNotNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource3->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant3->getIdentifier()));
    }

    public function testRemoveGrantsForResourcesWithResourceType(): void
    {
        $resource1 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS_2, 'resourceIdentifier1');
        $resource2 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier2',
        );
        $resourceGroup2 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier2',
            resourceType: ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE
        );
        $resource3 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier3');

        $resourceActionGrant1 = $this->testEntityManager->addResourceActionGrant(
            $resource1, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant2 = $this->testEntityManager->addResourceActionGrant(
            $resource2, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceGroupActionGrant2 = $this->testEntityManager->addResourceActionGrant(
            $resourceGroup2, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant3 = $this->testEntityManager->addResourceActionGrant(
            $resource3, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertEquals($resource1->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource1->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant1->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant1->getIdentifier())->getIdentifier());
        $this->assertEquals($resource2->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource2->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant2->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant2->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceGroup2->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resourceGroup2->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceGroupActionGrant2->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceGroupActionGrant2->getIdentifier())->getIdentifier());
        $this->assertEquals($resource3->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($resource3->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrant3->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant3->getIdentifier())->getIdentifier());

        $this->resourceActionGrantService->removeGrantsForResources(TestResources::TEST_RESOURCE_CLASS,
            ['resourceIdentifier1', 'resourceIdentifier2', 'resourceIdentifier3'],
            resourceType: ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE
        );

        $this->assertNotNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource1->getIdentifier()));
        $this->assertNotNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant1->getIdentifier()));
        $this->assertNotNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource2->getIdentifier()));
        $this->assertNotNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resourceGroup2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceGroupActionGrant2->getIdentifier()));
        $this->assertNotNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource3->getIdentifier()));
        $this->assertNotNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant3->getIdentifier()));

        // -------------------------------------------------------------------------------------
        $resource1 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS_2, 'resourceIdentifier1');
        $resource2 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier2',
        );
        $resourceGroup2 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier2',
            resourceType: ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE
        );
        $resource3 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier3');

        $resourceActionGrant1 = $this->testEntityManager->addResourceActionGrant(
            $resource1, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant2 = $this->testEntityManager->addResourceActionGrant(
            $resource2, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceGroupActionGrant2 = $this->testEntityManager->addResourceActionGrant(
            $resourceGroup2, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant3 = $this->testEntityManager->addResourceActionGrant(
            $resource3, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->resourceActionGrantService->removeGrantsForResources(TestResources::TEST_RESOURCE_CLASS,
            ['resourceIdentifier1', 'resourceIdentifier2', 'resourceIdentifier3'],
            resourceType: ResourceActionGrantService::RESOURCE_RESOURCE_TYPE
        );

        $this->assertNotNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource1->getIdentifier()));
        $this->assertNotNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant1->getIdentifier()));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant2->getIdentifier()));
        $this->assertNotNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resourceGroup2->getIdentifier()));
        $this->assertNotNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceGroupActionGrant2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource3->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant3->getIdentifier()));

        // -------------------------------------------------------------------------------------
        $resource1 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS_2, 'resourceIdentifier1');
        $resource2 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier2',
        );
        $resourceGroup2 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier2',
            resourceType: ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE
        );
        $resource3 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier3');

        $resourceActionGrant1 = $this->testEntityManager->addResourceActionGrant(
            $resource1, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant2 = $this->testEntityManager->addResourceActionGrant(
            $resource2, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceGroupActionGrant2 = $this->testEntityManager->addResourceActionGrant(
            $resourceGroup2, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant3 = $this->testEntityManager->addResourceActionGrant(
            $resource3, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->resourceActionGrantService->removeGrantsForResources(TestResources::TEST_RESOURCE_CLASS,
            ['resourceIdentifier1', 'resourceIdentifier2', 'resourceIdentifier3'],
            resourceType: null
        );

        $this->assertNotNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource1->getIdentifier()));
        $this->assertNotNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant1->getIdentifier()));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resourceGroup2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceGroupActionGrant2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource3->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant3->getIdentifier()));

        // -------------------------------------------------------------------------------------
        $resource1 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS_2, 'resourceIdentifier1');
        $resource2 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier2',
        );
        $resourceGroup2 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier2',
            resourceType: ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE
        );
        $resource3 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier3');

        $resourceActionGrant1 = $this->testEntityManager->addResourceActionGrant(
            $resource1, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant2 = $this->testEntityManager->addResourceActionGrant(
            $resource2, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceGroupActionGrant2 = $this->testEntityManager->addResourceActionGrant(
            $resourceGroup2, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant3 = $this->testEntityManager->addResourceActionGrant(
            $resource3, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->resourceActionGrantService->removeGrantsForResources(null,
            ['resourceIdentifier1', 'resourceIdentifier2', 'resourceIdentifier3'],
            resourceType: ResourceActionGrantService::RESOURCE_RESOURCE_TYPE
        );

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource1->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant1->getIdentifier()));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant2->getIdentifier()));
        $this->assertNotNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resourceGroup2->getIdentifier()));
        $this->assertNotNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceGroupActionGrant2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource3->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant3->getIdentifier()));

        // -------------------------------------------------------------------------------------
        $resource1 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS_2, 'resourceIdentifier1');
        $resource2 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier2',
        );
        $resourceGroup2 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier2',
            resourceType: ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE
        );
        $resource3 = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, 'resourceIdentifier3');

        $resourceActionGrant1 = $this->testEntityManager->addResourceActionGrant(
            $resource1, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant2 = $this->testEntityManager->addResourceActionGrant(
            $resource2, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceGroupActionGrant2 = $this->testEntityManager->addResourceActionGrant(
            $resourceGroup2, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant3 = $this->testEntityManager->addResourceActionGrant(
            $resource3, ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->resourceActionGrantService->removeGrantsForResources(null,
            ['resourceIdentifier1', 'resourceIdentifier2', 'resourceIdentifier3'],
            resourceType: null
        );

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource1->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant1->getIdentifier()));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resourceGroup2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceGroupActionGrant2->getIdentifier()));
        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($resource3->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant3->getIdentifier()));
    }

    public function testGetGrantedActionsForCurrentUser(): void
    {
        $resource1 = $this->testEntityManager->addAuthorizationResource(TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $resource2 = $this->testEntityManager->addAuthorizationResource(TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER_2);

        $grantedActions = $this->resourceActionGrantService->getGrantedActionsForCurrentUser(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertNull($grantedActions);

        $this->testEntityManager->addResourceActionGrant($resource1,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1, 'write', self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1, 'read', self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2,
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2, 'read', self::CURRENT_USER_IDENTIFIER);

        $grantedActions = $this->resourceActionGrantService->getGrantedActionsForCurrentUser(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $grantedActions->getResourceIdentifier());
        $this->assertEquals([ResourceActionGrantService::MANAGE_ACTION], $grantedActions->getActions());

        $grantedActions = $this->resourceActionGrantService->getGrantedActionsForCurrentUser(
            TestResources::TEST_RESOURCE_CLASS, 'foo');
        $this->assertNull($grantedActions);

        // -----------------------------------------------------------------
        // another user:
        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $grantedActions = $this->resourceActionGrantService->getGrantedActionsForCurrentUser(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER_2);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $grantedActions->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER_2, $grantedActions->getResourceIdentifier());
        $this->assertEquals([ResourceActionGrantService::MANAGE_ACTION], $grantedActions->getActions());
    }

    public function testGetGrantedActionsPageForCurrentUser(): void
    {
        $resource1 = $this->testEntityManager->addAuthorizationResource(TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $resource2 = $this->testEntityManager->addAuthorizationResource(TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER_2);

        $grantedActionsPage = $this->resourceActionGrantService->getGrantedActionsCollectionForCurrentUser(
            TestResources::TEST_RESOURCE_CLASS);
        $this->assertEmpty($grantedActionsPage);

        $this->testEntityManager->addResourceActionGrant($resource1,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1, 'write', self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1, 'read', self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2,
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource2, 'read', self::CURRENT_USER_IDENTIFIER);

        $grantedActionsPage = $this->resourceActionGrantService->getGrantedActionsCollectionForCurrentUser(
            TestResources::TEST_RESOURCE_CLASS);
        $this->assertCount(2, $grantedActionsPage);
        $this->assertCount(1, $this->selectWhere($grantedActionsPage,
            function (GrantedActions $grantedActions) use ($resource1) {
                return $grantedActions->getResourceClass() === $resource1->getResourceClass()
                    && $grantedActions->getResourceIdentifier() === $resource1->getResourceIdentifier()
                    && $grantedActions->getActions() === [ResourceActionGrantService::MANAGE_ACTION];
            }
        ));
        $this->assertCount(1, $this->selectWhere($grantedActionsPage,
            function (GrantedActions $grantedActions) use ($resource2) {
                return $grantedActions->getResourceClass() === $resource2->getResourceClass()
                    && $grantedActions->getResourceIdentifier() === $resource2->getResourceIdentifier()
                    && $grantedActions->getActions() === [TestResources::READ_ACTION];
            }
        ));

        $grantedActionsPage = $this->resourceActionGrantService->getGrantedActionsCollectionForCurrentUser(
            TestResources::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: ResourceActionGrantService::MANAGE_ACTION);
        $this->assertCount(1, $grantedActionsPage);
        $this->assertCount(1, $this->selectWhere($grantedActionsPage,
            function (GrantedActions $grantedActions) use ($resource1) {
                return $grantedActions->getResourceClass() === $resource1->getResourceClass()
                    && $grantedActions->getResourceIdentifier() === $resource1->getResourceIdentifier()
                    && $grantedActions->getActions() === [ResourceActionGrantService::MANAGE_ACTION];
            }
        ));

        $grantedActionsPage = $this->resourceActionGrantService->getGrantedActionsCollectionForCurrentUser(
            TestResources::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: TestResources::WRITE_ACTION
        );
        $this->assertCount(1, $grantedActionsPage);
        $this->assertCount(1, $this->selectWhere($grantedActionsPage,
            function (GrantedActions $grantedActions) use ($resource1) {
                return $grantedActions->getResourceClass() === $resource1->getResourceClass()
                    && $grantedActions->getResourceIdentifier() === $resource1->getResourceIdentifier()
                    && $grantedActions->getActions() === [ResourceActionGrantService::MANAGE_ACTION];
            }
        ));

        $grantedActionsPage = $this->resourceActionGrantService->getGrantedActionsCollectionForCurrentUser(
            TestResources::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: TestResources::DELETE_ACTION);
        $this->assertCount(1, $grantedActionsPage);
        $this->assertCount(1, $this->selectWhere($grantedActionsPage,
            function (GrantedActions $grantedActions) use ($resource1) {
                return $grantedActions->getResourceClass() === $resource1->getResourceClass()
                    && $grantedActions->getResourceIdentifier() === $resource1->getResourceIdentifier()
                    && $grantedActions->getActions() === [ResourceActionGrantService::MANAGE_ACTION];
            }
        ));

        $grantedActionsPage = $this->resourceActionGrantService->getGrantedActionsCollectionForCurrentUser(
            TestResources::TEST_RESOURCE_CLASS_2);
        $this->assertCount(0, $grantedActionsPage);

        // -----------------------------------------------------------------
        // another user:
        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $grantedActionsPage = $this->resourceActionGrantService->getGrantedActionsCollectionForCurrentUser(
            TestResources::TEST_RESOURCE_CLASS);
        $this->assertCount(2, $grantedActionsPage);
        $this->assertCount(1, $this->selectWhere($grantedActionsPage,
            function (GrantedActions $grantedActions) use ($resource2) {
                return $grantedActions->getResourceClass() === $resource2->getResourceClass()
                    && $grantedActions->getResourceIdentifier() === $resource2->getResourceIdentifier()
                    && $grantedActions->getActions() === [ResourceActionGrantService::MANAGE_ACTION];
            }
        ));
        $this->assertCount(1, $this->selectWhere($grantedActionsPage,
            function (GrantedActions $grantedActions) use ($resource1) {
                return $grantedActions->getResourceClass() === $resource1->getResourceClass()
                    && $grantedActions->getResourceIdentifier() === $resource1->getResourceIdentifier()
                    && $grantedActions->getActions() === [TestResources::READ_ACTION];
            }
        ));

        $grantedActionsPage = $this->resourceActionGrantService->getGrantedActionsCollectionForCurrentUser(
            TestResources::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: ResourceActionGrantService::MANAGE_ACTION);
        $this->assertCount(1, $grantedActionsPage);
        $this->assertCount(1, $this->selectWhere($grantedActionsPage,
            function (GrantedActions $grantedActions) use ($resource2) {
                return $grantedActions->getResourceClass() === $resource2->getResourceClass()
                    && $grantedActions->getResourceIdentifier() === $resource2->getResourceIdentifier()
                    && $grantedActions->getActions() === [ResourceActionGrantService::MANAGE_ACTION];
            }
        ));

        $grantedActionsPage = $this->resourceActionGrantService->getGrantedActionsCollectionForCurrentUser(
            TestResources::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: TestResources::READ_ACTION);
        $this->assertCount(2, $grantedActionsPage);
        $this->assertCount(1, $this->selectWhere($grantedActionsPage,
            function (GrantedActions $grantedActions) use ($resource2) {
                return $grantedActions->getResourceClass() === $resource2->getResourceClass()
                    && $grantedActions->getResourceIdentifier() === $resource2->getResourceIdentifier()
                    && $grantedActions->getActions() === [ResourceActionGrantService::MANAGE_ACTION];
            }
        ));
        $this->assertCount(1, $this->selectWhere($grantedActionsPage,
            function (GrantedActions $grantedActions) use ($resource1) {
                return $grantedActions->getResourceClass() === $resource1->getResourceClass()
                    && $grantedActions->getResourceIdentifier() === $resource1->getResourceIdentifier()
                    && $grantedActions->getActions() === [TestResources::READ_ACTION];
            }
        ));

        $grantedActionsPage = $this->resourceActionGrantService->getGrantedActionsCollectionForCurrentUser(
            TestResources::TEST_RESOURCE_CLASS,
            whereIsGrantedAction: TestResources::WRITE_ACTION);
        $this->assertCount(1, $grantedActionsPage);
        $this->assertCount(1, $this->selectWhere($grantedActionsPage,
            function (GrantedActions $grantedActions) use ($resource2) {
                return $grantedActions->getResourceClass() === $resource2->getResourceClass()
                    && $grantedActions->getResourceIdentifier() === $resource2->getResourceIdentifier()
                    && $grantedActions->getActions() === [ResourceActionGrantService::MANAGE_ACTION];
            }
        ));
    }

    public function testIsCurrentUserGranted(): void
    {
        $resource = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            'foo'));

        $this->testEntityManager->addResourceActionGrant($resource,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION, ResourceActionGrantService::RESOURCE_RESOURCE_TYPE));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION, ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE));
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            'foo'));

        $resourceGroup = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER,
            ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE);

        $this->testEntityManager->addResourceActionGrant($resourceGroup,
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION, ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION, ResourceActionGrantService::RESOURCE_RESOURCE_TYPE));

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGranted(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            'foo'));
    }

    public function testRemoveResourceActionGrant(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $rag = $this->testEntityManager->addResourceActionGrant($authorizationResource,
            TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertNotNull($this->testEntityManager->getResourceActionGrantByIdentifier($rag->getIdentifier()));
        $this->resourceActionGrantService->removeResourceActionGrant($rag->getIdentifier());
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($rag->getIdentifier()));
    }

    public function testGetResourceActionGrantsForResourceClassAndIdentifier(): void
    {
        $rag1 = $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            action: array_keys(TestResources::TEST_RESOURCE_ITEM_ACTIONS)[0],
            userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $rag2 = $this->testEntityManager->addResourceActionGrant($rag1->getAuthorizationResource(),
            array_keys(TestResources::TEST_RESOURCE_ITEM_ACTIONS)[1], dynamicUserGroupIdentifier: 'everybody');

        $rags = $this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(2, $rags);
        $this->assertContainsResourceActionGrant($rags, $rag1);
        $this->assertContainsResourceActionGrant($rags, $rag2);

        $this->internalResourceActionGrantService->setAvailableResourceClassActions(
            TestResources::TEST_RESOURCE_CLASS, [], []);

        $rags = $this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            TestResources::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_CLASS_2);
        $this->assertCount(0, $rags);
    }

    public function testAddRole(): void
    {
        $roleActions = [];
        $roleActions[] = ResourceActionGrantService::createRoleAction(
            TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE);
        $roleActions[] = ResourceActionGrantService::createRoleAction(
            TestResources::TEST_RESOURCE_CLASS, TestResources::CREATE_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE);
        $localizedRoleNames = [
            'en' => 'Creator',
            'de' => 'Ersteller',
        ];
        $role = $this->resourceActionGrantService->addRole($localizedRoleNames, $roleActions);
        $this->assertTrue(UuidV7::isValid($role->getIdentifier()));
        $roleNameEntities = $role->getRoleNames();
        $this->assertCount(2, $roleNameEntities);
        $this->assertEquals('en', $roleNameEntities[0]->getLanguageTag());
        $this->assertEquals('Creator', $roleNameEntities[0]->getName());
        $this->assertEquals('de', $roleNameEntities[1]->getLanguageTag());
        $this->assertEquals('Ersteller', $roleNameEntities[1]->getName());
        $roleActionEntities = $role->getRoleActions();
        $this->assertCount(2, $roleActionEntities);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $roleActionEntities[0]->getAvailableResourceClassAction()->getResourceClass());
        $this->assertEquals(TestResources::READ_ACTION, $roleActionEntities[0]->getAvailableResourceClassAction()->getAction());
        $this->assertEquals(ResourceActionGrantService::ITEM_ACTION_TYPE, $roleActionEntities[0]->getAvailableResourceClassAction()->getActionType());
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $roleActionEntities[1]->getAvailableResourceClassAction()->getResourceClass());
        $this->assertEquals(TestResources::CREATE_ACTION, $roleActionEntities[1]->getAvailableResourceClassAction()->getAction());
        $this->assertEquals(ResourceActionGrantService::COLLECTION_ACTION_TYPE, $roleActionEntities[1]->getAvailableResourceClassAction()->getActionType());

        $roleIdentifier = Uuid::v7()->toRfc4122();
        $role = $this->resourceActionGrantService->addRole($localizedRoleNames, $roleActions, $roleIdentifier);
        $this->assertEquals($roleIdentifier, $role->getIdentifier());
    }
}
