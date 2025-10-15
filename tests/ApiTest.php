<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests;

use Dbp\Relay\AuthorizationBundle\TestUtils\AuthorizationTest;
use Dbp\Relay\CoreBundle\TestUtils\AbstractApiTest;
use Symfony\Component\HttpFoundation\Response;

class ApiTest extends AbstractApiTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->testClient->setUpUser(userAttributes: ['MAY_CREATE_GROUPS' => false]);
        AuthorizationTest::setUp($this->testClient->getContainer());
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
        $response = $this->testClient->get('/authorization/available-resource-class-actions', token: null);

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testGetResourceActionGrantUnauthenticated(): void
    {
        $response = $this->testClient->get('/authorization/resource-action-grants/foo', token: null);

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

    public function testPostAndGetGroup(): void
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
        $groupFromGet = json_decode($response->getContent(), true);
        $this->assertEquals($group['identifier'], $groupFromGet['identifier']);
    }

    public function testGetGroupUnauthenticated(): void
    {
        $response = $this->testClient->get('/authorization/groups/foo', token: null);
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
        $response = $this->testClient->get('/authorization/groups', token: null);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testPostGroupMemberUnauthenticated(): void
    {
        $response = $this->testClient->postJson('/authorization/group-members', [
            'group' => '/authorization/groups/foo',
            'userIdentifier' => 'testuser',
        ], token: null);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        AuthorizationTest::postRequestCleanup($this->testClient->getContainer());
    }

    public function testPostGroupMemberForbidden(): void
    {
        $this->testClient->setUpUser('testuser', ['MAY_CREATE_GROUPS' => true]);
        $response = $this->testClient->postJson('/authorization/groups', [
            'name' => 'Test Group',
        ]);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        AuthorizationTest::postRequestCleanup($this->testClient->getContainer());
        $group = json_decode($response->getContent(), true);

        $this->testClient->setUpUser('anotheruser', ['MAY_CREATE_GROUPS' => false]);
        $response = $this->testClient->postJson('/authorization/group-members', [
            'group' => '/authorization/groups/'.$group['identifier'],
            'userIdentifier' => 'anotheruser',
        ]);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        AuthorizationTest::postRequestCleanup($this->testClient->getContainer());
    }

    public function testPostAndGetGroupMember(): void
    {
        $this->testClient->setUpUser('testuser', ['MAY_CREATE_GROUPS' => true]);
        $response = $this->testClient->postJson('/authorization/groups', [
            'name' => 'Test Group',
        ]);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        AuthorizationTest::postRequestCleanup($this->testClient->getContainer());
        $group = json_decode($response->getContent(), true);

        $this->testClient->setUpUser('testuser', ['MAY_CREATE_GROUPS' => true]);
        $response = $this->testClient->postJson('/authorization/group-members', [
            'group' => '/authorization/groups/'.$group['identifier'],
            'userIdentifier' => 'anotheruser',
        ]);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        AuthorizationTest::postRequestCleanup($this->testClient->getContainer());

        $groupMember = json_decode($response->getContent(), true);
        $response = $this->testClient->get('/authorization/group-members/'.$groupMember['identifier']);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $groupMemberFromGet = json_decode($response->getContent(), true);
        $this->assertEquals($groupMember['identifier'], $groupMemberFromGet['identifier']);
    }

    // TODO: add resource action grant tests
}
