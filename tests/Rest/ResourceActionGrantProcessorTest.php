<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Rest\ResourceActionGrantProcessor;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProcessorTester;
use Symfony\Component\HttpFoundation\Response;

class ResourceActionGrantProcessorTest extends AbstractResourceActionGrantControllerTestCase
{
    private DataProcessorTester $resourceActionGrantProcessorTester;

    protected function setUp(): void
    {
        parent::setUp();

        $resourceActionGrantProcessor = new ResourceActionGrantProcessor(
            $this->internalResourceActionGrantService, $this->authorizationService);
        $this->resourceActionGrantProcessorTester = DataProcessorTester::create(
            $resourceActionGrantProcessor, ResourceActionGrant::class);
    }

    public function testCreateResourceActionGrant(): void
    {
        $manageResourceGrant = $this->addResourceAndManageGrant();
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($manageResourceGrant->getAuthorizationResource());
        $resourceActionGrant->setAction('read');
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
        $resourceActionGrant->setAction('write');
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
            self::ANOTHER_USER_IDENTIFIER);
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
