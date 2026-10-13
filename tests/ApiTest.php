<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestResourceActionGrantServiceFactory;
use Dbp\Relay\CoreBundle\TestUtils\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        $this->createTestClient();

        $this->testClient->setUpUser(userAttributes: ['MAY_CREATE_GROUPS' => false]);

        TestResourceActionGrantServiceFactory::createTestEntityManager($this->getContainer(),
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
        $this->assertNotNull($this->getContainer());
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

    public function testGetGrantedActionsCollectionUnauthenticated(): void
    {
        $response = $this->testClient->get('/authorization/granted-actions/DbpRelayAuthorizationGroup?perPage=9999', token: null);

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testGetGrantedActionsItemUnauthenticated(): void
    {
        $response = $this->testClient->get('/authorization/granted-actions/DbpRelayAuthorizationGroup/test-group', token: null);

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testGetGrantedActionsItemWithoutGrants(): void
    {
        $response = $this->testClient->get('/authorization/granted-actions/'.AuthorizationService::GROUP_RESOURCE_CLASS.'/test-group');

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $grantedActions = json_decode($response->getContent(), true);
        $this->assertEquals(AuthorizationService::GROUP_RESOURCE_CLASS, $grantedActions['resourceClass']);
        $this->assertEquals('test-group', $grantedActions['resourceIdentifier']);
        $this->assertEquals([], $grantedActions['actions']);
    }

    public function testGetGrantedActionsCollectionPagination(): void
    {
        $this->testClient->setUpUser('testuser', ['MAY_CREATE_GROUPS' => true]);
        $groupIdentifiers = [];
        foreach (['First Group', 'Second Group'] as $name) {
            $response = $this->testClient->postJson('/authorization/groups', ['name' => $name]);
            $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
            $groupIdentifiers[] = json_decode($response->getContent(), true)['identifier'];
        }

        $response = $this->testClient->get('/authorization/granted-actions/'.AuthorizationService::GROUP_RESOURCE_CLASS.'?perPage=1');
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $firstPage = json_decode($response->getContent(), true);
        $this->assertCount(1, $firstPage['hydra:member']);

        $response = $this->testClient->get($firstPage['hydra:view']['hydra:next']);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $secondPage = json_decode($response->getContent(), true);
        $this->assertCount(1, $secondPage['hydra:member']);

        $grants = array_merge($firstPage['hydra:member'], $secondPage['hydra:member']);
        $this->assertEqualsCanonicalizing($groupIdentifiers, array_column($grants, 'resourceIdentifier'));
        foreach ($grants as $grant) {
            $this->assertEquals(AuthorizationService::GROUP_RESOURCE_CLASS, $grant['resourceClass']);
            $this->assertEquals([AuthorizationService::MANAGE_ACTION], $grant['actions']);
            $response = $this->testClient->get($grant['@id']);
            $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        }
    }

    public function testPostGroupUnauthenticated(): void
    {
        $response = $this->testClient->postJson('/authorization/groups', [
            'name' => 'Test Group',
        ], [], null);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testPostGroupForbidden(): void
    {
        $response = $this->testClient->postJson('/authorization/groups', [
            'name' => 'Test Group',
        ]);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testPostAndGetGroup(): void
    {
        $this->testClient->setUpUser('testuser', ['MAY_CREATE_GROUPS' => true]);
        $response = $this->testClient->postJson('/authorization/groups', [
            'name' => 'Test Group',
        ]);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

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
    }

    public function testPostGroupMemberForbidden(): void
    {
        $this->testClient->setUpUser('testuser', ['MAY_CREATE_GROUPS' => true]);
        $response = $this->testClient->postJson('/authorization/groups', [
            'name' => 'Test Group',
        ]);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $group = json_decode($response->getContent(), true);

        $this->testClient->setUpUser('anotheruser', ['MAY_CREATE_GROUPS' => false]);
        $response = $this->testClient->postJson('/authorization/group-members', [
            'group' => '/authorization/groups/'.$group['identifier'],
            'userIdentifier' => 'anotheruser',
        ]);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testPostAndGetGroupMember(): void
    {
        $this->testClient->setUpUser('testuser', ['MAY_CREATE_GROUPS' => true]);
        $response = $this->testClient->postJson('/authorization/groups', [
            'name' => 'Test Group',
        ]);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $group = json_decode($response->getContent(), true);

        $this->testClient->setUpUser('testuser', ['MAY_CREATE_GROUPS' => true]);
        $response = $this->testClient->postJson('/authorization/group-members', [
            'group' => '/authorization/groups/'.$group['identifier'],
            'userIdentifier' => 'anotheruser',
        ]);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        // endpoint disabled:
        //        $groupMember = json_decode($response->getContent(), true);
        //        $response = $this->testClient->get('/authorization/group-members/'.$groupMember['identifier']);
        //        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        //        $groupMemberFromGet = json_decode($response->getContent(), true);
        //        $this->assertEquals($groupMember['identifier'], $groupMemberFromGet['identifier']);
    }

    // TODO: add resource action grant tests
}
