<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestResourceActionGrantServiceFactory;
use Dbp\Relay\CoreBundle\TestUtils\AbstractApiTest;
use Symfony\Component\HttpFoundation\Response;

class ApiTest extends AbstractApiTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->testClient->setUpUser(userAttributes: ['MAY_CREATE_GROUPS' => false]);

        TestResourceActionGrantServiceFactory::createTestEntityManager($this->testClient->getContainer(),
            availableResourceClassActions: [
                AuthorizationService::GROUP_RESOURCE_CLASS => [
                    AuthorizationService::GROUP_ITEM_ACTIONS,
                    AuthorizationService::GROUP_COLLECTION_ACTIONS,
                ],
            ]);
    }

    protected function tearDown(): void
    {
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
        $response = $this->testClient->postJson('/authorization/user-groups', [
            'name' => 'Test Group',
        ], [], null);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testPostGroupForbidden(): void
    {
        $response = $this->testClient->postJson('/authorization/user-groups', [
            'name' => 'Test Group',
        ]);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testPostAndGetGroup(): void
    {
        $this->testClient->setUpUser('testuser', ['MAY_CREATE_GROUPS' => true]);
        $response = $this->testClient->postJson('/authorization/user-groups', [
            'name' => 'Test Group',
        ]);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $userGroup = json_decode($response->getContent(), true);
        $this->assertEquals('Test Group', $userGroup['name']);

        $response = $this->testClient->get('/authorization/user-groups/'.$userGroup['identifier']);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $groupFromGet = json_decode($response->getContent(), true);
        $this->assertEquals($userGroup['identifier'], $groupFromGet['identifier']);
        $this->assertEquals($userGroup['name'], $groupFromGet['name']);
    }

    public function testGetGroupUnauthenticated(): void
    {
        $response = $this->testClient->get('/authorization/user-groups/foo', token: null);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testGetGroupForbidden(): void
    {
        $this->testClient->setUpUser('testuser', ['MAY_CREATE_GROUPS' => true]);
        $response = $this->testClient->postJson('/authorization/user-groups', [
            'name' => 'Test Group',
        ]);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        $userGroup = json_decode($response->getContent(), true);

        $this->testClient->setUpUser('another user', ['MAY_CREATE_GROUPS' => false]);
        $response = $this->testClient->get('/authorization/user-groups/'.$userGroup['identifier']);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /**
     * NOTE: testGetGroupCollection authenticated does not work because sqlite lacks some features mysql provides (e.g. unhex function).
     */
    public function testGetGroupCollectionUnauthenticated(): void
    {
        $response = $this->testClient->get('/authorization/user-groups', token: null);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testPostGroupMemberUnauthenticated(): void
    {
        $response = $this->testClient->postJson('/authorization/user-group-members', [
            'userGroup' => '/authorization/user-groups/foo',
            'userIdentifier' => 'testuser',
        ], token: null);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testPostGroupMemberForbidden(): void
    {
        $this->testClient->setUpUser('testuser', ['MAY_CREATE_GROUPS' => true]);
        $response = $this->testClient->postJson('/authorization/user-groups', [
            'name' => 'Test Group',
        ]);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $userGroup = json_decode($response->getContent(), true);

        $this->testClient->setUpUser('anotheruser', ['MAY_CREATE_GROUPS' => false]);
        $response = $this->testClient->postJson('/authorization/user-group-members', [
            'userGroup' => '/authorization/user-groups/'.$userGroup['identifier'],
            'userIdentifier' => 'anotheruser',
        ]);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testPostAndGetGroupMember(): void
    {
        $this->testClient->setUpUser('testuser', ['MAY_CREATE_GROUPS' => true]);
        $response = $this->testClient->postJson('/authorization/user-groups', [
            'name' => 'Test Group',
        ]);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $userGroup = json_decode($response->getContent(), true);
        $this->assertEmpty($userGroup['members']);

        $response = $this->testClient->postJson('/authorization/user-group-members', [
            'userGroup' => '/authorization/user-groups/'.$userGroup['identifier'],
            'userIdentifier' => 'anotheruser',
        ]);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $groupMember = json_decode($response->getContent(), true);
        $this->assertEquals('anotheruser', $groupMember['userIdentifier']);
        $this->assertEquals('/authorization/user-groups/'.$userGroup['identifier'], $groupMember['userGroup']);

        $response = $this->testClient->get('/authorization/user-groups/'.$userGroup['identifier']);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $userGroup = json_decode($response->getContent(), true);
        $this->assertCount(1, $userGroup['members']);
        $this->assertEquals($groupMember['identifier'], $userGroup['members'][0]['identifier']);
        $this->assertEquals('anotheruser', $userGroup['members'][0]['userIdentifier']);
    }

    // TODO: add resource action grant tests
}
