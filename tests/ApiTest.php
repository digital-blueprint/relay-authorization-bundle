<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestResourceActionGrantServiceFactory;
use Dbp\Relay\CoreBundle\TestUtils\AbstractApiTest;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Symfony\Component\HttpFoundation\Response;

class ApiTest extends AbstractApiTest
{
    protected const TEST_RESOURCE_IDENTIFIER = 'test-resource';
    protected const CURRENT_USER_IDENTIFIER = TestAuthorizationService::TEST_USER_IDENTIFIER;
    protected const ANOTHER_USER_IDENTIFIER = 'testuser2';

    protected ?TestEntityManager $testEntityManager = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testClient->setUpUser(userAttributes: ['MAY_CREATE_GROUPS' => false]);

        $this->testEntityManager = TestResourceActionGrantServiceFactory::createTestEntityManager(
            $this->testClient->getContainer(),
            availableResourceClassActions: TestResources::getAvailableResourceClassActions()
        );
    }

    protected function tearDown(): void
    {
    }

    public function testGetAvailableResourceClassActionsUnauthenticated(): void
    {
        $response = $this->testClient->get('/authorization/available-resource-class-actions', token: null);

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

    public function testPostResourceActionGrantUnauthenticated(): void
    {
        $response = $this->testClient->postJson('/authorization/resource-action-grants', [
            'resourceClass' => 'TestResource',
            'resourceIdentifier' => 'test-resource-1',
            'action' => 'read',
            'userIdentifier' => 'testuser',
        ], token: null);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testPostResourceActionGrantForbidden(): void
    {
        // user may read but not post
        $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            action: TestResources::READ_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );

        $response = $this->testClient->postJson('/authorization/resource-action-grants', [
            'resourceClass' => TestResources::TEST_RESOURCE_CLASS,
            'resourceIdentifier' => self::TEST_RESOURCE_IDENTIFIER,
            'action' => TestResources::READ_ACTION,
            'userIdentifier' => self::ANOTHER_USER_IDENTIFIER,
        ]);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testPostAndGetResourceActionGrant(): void
    {
        // user is manager of the resource and may post grants
        $manageGrant = $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
            roleIdentifier: AuthorizationService::MANAGER_ROLE_IDENTIFIER,
            creatorId: self::CURRENT_USER_IDENTIFIER
        );

        $response = $this->testClient->get('/authorization/resource-action-grants/'.$manageGrant->getIdentifier());
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $manageGrantFromGet = json_decode($response->getContent(), true);
        $this->assertEquals($manageGrant->getIdentifier(), $manageGrantFromGet['identifier']);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $manageGrantFromGet['resourceClass']);
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $manageGrantFromGet['resourceIdentifier']);
        $this->assertEquals(AuthorizationResource::RESOURCE_RESOURCE_TYPE, $manageGrantFromGet['resourceType']);
        $this->assertEquals(null, $manageGrantFromGet['action']);
        $this->assertEquals('/authorization/roles/'.AuthorizationService::MANAGER_ROLE_IDENTIFIER, $manageGrantFromGet['role']);
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $manageGrantFromGet['userIdentifier']);
        $this->assertEquals(null, $manageGrantFromGet['userGroup']);
        $this->assertEquals(null, $manageGrantFromGet['dynamicUserGroupIdentifier']);
        $this->assertEquals(false, $manageGrantFromGet['shareable']);
        $this->assertEquals(null, $manageGrantFromGet['shareOf']);
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $manageGrantFromGet['creatorId']);
        $this->assertIsValidUtcDateTimeString($manageGrantFromGet['dateCreated']);

        $response = $this->testClient->postJson('/authorization/resource-action-grants', [
            'resourceClass' => TestResources::TEST_RESOURCE_CLASS,
            'resourceIdentifier' => self::TEST_RESOURCE_IDENTIFIER,
            'action' => TestResources::READ_ACTION,
            'userIdentifier' => self::ANOTHER_USER_IDENTIFIER,
        ]);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $resourceActionGrant = json_decode($response->getContent(), true);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $resourceActionGrant['resourceClass']);
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant['resourceIdentifier']);
        $this->assertEquals(TestResources::READ_ACTION, $resourceActionGrant['action']);
        $this->assertEquals(null, $resourceActionGrant['role']);
        $this->assertEquals(self::ANOTHER_USER_IDENTIFIER, $resourceActionGrant['userIdentifier']);
        $this->assertEquals(null, $resourceActionGrant['userGroup']);
        $this->assertEquals(null, $resourceActionGrant['dynamicUserGroupIdentifier']);
        $this->assertEquals(false, $resourceActionGrant['shareable']);
        $this->assertEquals(null, $resourceActionGrant['shareOf']);
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $resourceActionGrant['creatorId']);
        $this->assertIsValidUtcDateTimeString($resourceActionGrant['dateCreated']);

        $response = $this->testClient->get('/authorization/resource-action-grants', [
            'resourceClass' => TestResources::TEST_RESOURCE_CLASS,
            'resourceIdentifier' => self::TEST_RESOURCE_IDENTIFIER,
        ]);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $resourceActionGrantsFromGet = json_decode($response->getContent(), true)['hydra:member'];
        $this->assertCount(2, $resourceActionGrantsFromGet);
        $resourceActionGrantFromGet = $this->selectWhere(
            $resourceActionGrantsFromGet,
            fn ($rag) => $rag['userIdentifier'] === self::ANOTHER_USER_IDENTIFIER
        )[0];
        $this->assertEquals($resourceActionGrant['identifier'], $resourceActionGrantFromGet['identifier']);
        $this->assertEquals($resourceActionGrant['resourceClass'], $resourceActionGrantFromGet['resourceClass']);
        $this->assertEquals($resourceActionGrant['resourceIdentifier'], $resourceActionGrantFromGet['resourceIdentifier']);
        $this->assertEquals($resourceActionGrant['action'], $resourceActionGrantFromGet['action']);
        $this->assertEquals($resourceActionGrant['role'], $resourceActionGrantFromGet['role']);
        $this->assertEquals($resourceActionGrant['userIdentifier'], $resourceActionGrantFromGet['userIdentifier']);
        $this->assertEquals($resourceActionGrant['userGroup'], $resourceActionGrantFromGet['userGroup']);
        $this->assertEquals($resourceActionGrant['dynamicUserGroupIdentifier'], $resourceActionGrantFromGet['dynamicUserGroupIdentifier']);
        $this->assertIsValidUtcDateTimeString($resourceActionGrantFromGet['dateCreated']);
    }

    public function testPostAndDeleteResourceActionGrant(): void
    {
        // user is manager of the resource and may post grants
        $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
            roleIdentifier: AuthorizationService::MANAGER_ROLE_IDENTIFIER,
            creatorId: self::CURRENT_USER_IDENTIFIER
        );

        $response = $this->testClient->postJson('/authorization/resource-action-grants', [
            'resourceClass' => TestResources::TEST_RESOURCE_CLASS,
            'resourceIdentifier' => self::TEST_RESOURCE_IDENTIFIER,
            'action' => TestResources::READ_ACTION,
            'userIdentifier' => self::ANOTHER_USER_IDENTIFIER,
        ]);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $resourceActionGrant = json_decode($response->getContent(), true);

        $response = $this->testClient->delete('/authorization/resource-action-grants/'.$resourceActionGrant['identifier']);
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $response = $this->testClient->get('/authorization/resource-action-grants', [
            'resourceClass' => TestResources::TEST_RESOURCE_CLASS,
            'resourceIdentifier' => self::TEST_RESOURCE_IDENTIFIER,
        ]);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $resourceActionGrantsFromGet = json_decode($response->getContent(), true)['hydra:member'];
        $this->assertCount(1, $resourceActionGrantsFromGet);
        $this->assertCount(1, $this->selectWhere(
            $resourceActionGrantsFromGet,
            fn ($rag) => $rag['userIdentifier'] === self::CURRENT_USER_IDENTIFIER
        ));
    }

    protected function selectWhere(array $results, callable $where, bool $passInKeyToo = false): array
    {
        return array_values(
            array_filter($results, $where, $passInKeyToo ? ARRAY_FILTER_USE_BOTH : 0)
        );
    }

    protected function assertIsValidUtcDateTimeString(string $dateTimeString): void
    {
        // we require ISO 8601 with the UTC “Zulu” designator (Z) and milliseconds,
        // e.g., 2026-07-29T08:22:24.000Z
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/',
            $dateTimeString
        );
    }
}
