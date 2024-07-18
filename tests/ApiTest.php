<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Dbp\Relay\AuthorizationBundle\TestUtils\AuthorizationTest;
use Dbp\Relay\CoreBundle\TestUtils\TestClient;
use Dbp\Relay\CoreBundle\TestUtils\UserAuthTrait;
use Symfony\Component\HttpFoundation\Response;

class ApiTest extends ApiTestCase
{
    use UserAuthTrait;

    private TestClient $testClient;

    protected function setUp(): void
    {
        $this->testClient = new TestClient(ApiTestCase::createClient());
        $this->testClient->setUpUser('testuser', ['MAY_CREATE_GROUPS' => false]);
        AuthorizationTest::setUp($this->testClient->getContainer());
        // the following allows multiple requests in one test:
        $this->testClient->getClient()->disableReboot();
    }

    protected function tearDown(): void
    {
        AuthorizationTest::tearDown($this->testClient->getContainer());
    }

    public function testContainer()
    {
        $this->assertNotNull($this->testClient->getContainer());
    }

    public function testGetAvailableResourceClassActionsUnauthenticated(): void
    {
        $response = $this->testClient->get('/authorization/available-resource-class-actions', [], [], 'd');

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testGetResourceActionGrantUnauthenticated(): void
    {
        $response = $this->testClient->get('/authorization/resource-action-grants/foo', [], [], 'd');

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testGetAuthorizationResourceUnauthenticated(): void
    {
        $response = $this->testClient->get('/authorization/resources/foo', [], [], null);

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testGetAuthorizationResourceNotFound(): void
    {
        $response = $this->testClient->get('/authorization/resources/foo');

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testGetAuthorizationResourceCollection(): void
    {
        $response = $this->testClient->get('/authorization/resources');
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testGetAuthorizationResourceCollectionUnauthenticated(): void
    {
        $response = $this->testClient->get('/authorization/resources/foo', [], [], null);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testPostGroupUnauthenticated(): void
    {
        $response = $this->testClient->postJson('/authorization/groups', [
            'name' => 'Test Group',
        ], [], null);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        AuthorizationTest::postRequestCleanup($this->testClient->getContainer());
    }

    public function testPostGroupForbidden(): void
    {
        $response = $this->testClient->postJson('/authorization/groups', [
            'name' => 'Test Group',
        ]);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        AuthorizationTest::postRequestCleanup($this->testClient->getContainer());
    }

    public function testGetGroup(): void
    {
        $this->testClient->setUpUser('testuser', ['MAY_CREATE_GROUPS' => true]);
        $response = $this->testClient->postJson('/authorization/groups', [
            'name' => 'Test Group',
        ]);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        AuthorizationTest::postRequestCleanup($this->testClient->getContainer());

        $group = json_decode($response->getContent(), true);
        $response = $this->testClient->get('/authorization/groups/'.$group['identifier']);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testGetGroupUnauthenticated(): void
    {
        $response = $this->testClient->get('/authorization/groups/foo', [], [], null);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testGetGroupForbidden(): void
    {
        $this->testClient->setUpUser('testuser', ['MAY_CREATE_GROUPS' => true]);
        $response = $this->testClient->postJson('/authorization/groups', [
            'name' => 'Test Group',
        ]);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        AuthorizationTest::postRequestCleanup($this->testClient->getContainer());

        $group = json_decode($response->getContent(), true);

        $this->testClient->setUpUser('another user');
        $response = $this->testClient->get('/authorization/groups/'.$group['identifier']);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /**
     * NOTE: testGetGroupCollection authenticated does not work because sqlite lacks some features mysql provides (e.g. unhex function).
     */
    public function testGetGroupCollectionUnauthenticated(): void
    {
        $response = $this->testClient->get('/authorization/groups', [], [], null);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }
}
