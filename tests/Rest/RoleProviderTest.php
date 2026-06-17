<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Entity\Role;
use Dbp\Relay\AuthorizationBundle\Rest\Common;
use Dbp\Relay\AuthorizationBundle\Rest\RoleProvider;
use Dbp\Relay\AuthorizationBundle\Tests\TestResources;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;

class RoleProviderTest extends AbstractResourceActionGrantControllerAuthorizationServiceTestCase
{
    private ?DataProviderTester $roleProviderTester = null;

    protected function setUp(): void
    {
        parent::setUp();

        $resourceActionGrantProvider = new RoleProvider($this->internalResourceActionGrantService);
        $this->roleProviderTester = DataProviderTester::create($resourceActionGrantProvider, Role::class);
    }

    public function testGetRoleByIdentifier(): void
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
        $role = $this->internalResourceActionGrantService->addRole(
            $localizedRoleNames, $roleActions
        );

        $roleReturned = $this->roleProviderTester->getItem($role->getIdentifier());
        $this->assertEquals($role->getIdentifier(), $roleReturned->getIdentifier());
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
    }

    public function testGetRoleByIdentifierNotExisting(): void
    {
        $roleReturned = $this->roleProviderTester->getItem('non-existing-identifier');
        $this->assertNull($roleReturned);
    }

    public function testGetRoles(): void
    {
        $roleActions = [];
        $roleActions[] = ResourceActionGrantService::createRoleAction(
            TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE);
        $localizedRoleNames = [
            'en' => 'Reader',
            'de' => 'Leser',
        ];
        $role1 = $this->internalResourceActionGrantService->addRole(
            $localizedRoleNames, $roleActions
        );

        $roleActions2 = [];
        $roleActions2[] = ResourceActionGrantService::createRoleAction(
            TestResources::TEST_RESOURCE_CLASS, TestResources::CREATE_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE);
        $roleActions2[] = ResourceActionGrantService::createRoleAction(
            TestResources::TEST_RESOURCE_CLASS, TestResources::UPDATE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE);
        $localizedRoleNames2 = [
            'en' => 'Editor',
            'de' => 'Redakteur',
        ];
        $role2 = $this->internalResourceActionGrantService->addRole(
            $localizedRoleNames2, $roleActions2
        );

        $rolesReturned = $this->roleProviderTester->getPage(1, 10);
        $this->assertCount(2, $rolesReturned);

        $this->assertEquals($role1->getIdentifier(), $rolesReturned[0]->getIdentifier());
        $roleNameEntities = $role1->getRoleNames();
        $this->assertCount(2, $roleNameEntities);
        $this->assertEquals('en', $roleNameEntities[0]->getLanguageTag());
        $this->assertEquals('Reader', $roleNameEntities[0]->getName());
        $this->assertEquals('de', $roleNameEntities[1]->getLanguageTag());
        $this->assertEquals('Leser', $roleNameEntities[1]->getName());
        $roleActionEntities = $role1->getRoleActions();
        $this->assertCount(1, $roleActionEntities);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $roleActionEntities[0]->getAvailableResourceClassAction()->getResourceClass());
        $this->assertEquals(TestResources::READ_ACTION, $roleActionEntities[0]->getAvailableResourceClassAction()->getAction());
        $this->assertEquals(ResourceActionGrantService::ITEM_ACTION_TYPE, $roleActionEntities[0]->getAvailableResourceClassAction()->getActionType());

        $this->assertEquals($role2->getIdentifier(), $rolesReturned[1]->getIdentifier());
        $roleNameEntities = $role2->getRoleNames();
        $this->assertCount(2, $roleNameEntities);
        $this->assertEquals('en', $roleNameEntities[0]->getLanguageTag());
        $this->assertEquals('Editor', $roleNameEntities[0]->getName());
        $this->assertEquals('de', $roleNameEntities[1]->getLanguageTag());
        $this->assertEquals('Redakteur', $roleNameEntities[1]->getName());
        $roleActionEntities = $role2->getRoleActions();
        $this->assertCount(2, $roleActionEntities);
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $roleActionEntities[0]->getAvailableResourceClassAction()->getResourceClass());
        $this->assertEquals(TestResources::TEST_RESOURCE_CLASS, $roleActionEntities[1]->getAvailableResourceClassAction()->getResourceClass());
        $this->assertEquals(TestResources::CREATE_ACTION, $roleActionEntities[0]->getAvailableResourceClassAction()->getAction());
        $this->assertEquals(TestResources::UPDATE_ACTION, $roleActionEntities[1]->getAvailableResourceClassAction()->getAction());
        $this->assertEquals(ResourceActionGrantService::COLLECTION_ACTION_TYPE, $roleActionEntities[0]->getAvailableResourceClassAction()->getActionType());
        $this->assertEquals(ResourceActionGrantService::ITEM_ACTION_TYPE, $roleActionEntities[1]->getAvailableResourceClassAction()->getActionType());
    }

    public function testGetRoleWithResourceClassFilter(): void
    {
        $role1 = $this->internalResourceActionGrantService->addRole([], [
            ResourceActionGrantService::createRoleAction(
                TestResources::TEST_RESOURCE_CLASS_2, TestResources::READ_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE),
            ResourceActionGrantService::createRoleAction(
                TestResources::TEST_RESOURCE_CLASS, TestResources::CREATE_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE),
        ]
        );
        $role2 = $this->internalResourceActionGrantService->addRole([], [
            ResourceActionGrantService::createRoleAction(
                TestResources::TEST_RESOURCE_CLASS_2, TestResources::CREATE_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE),
        ]
        );
        $role3 = $this->internalResourceActionGrantService->addRole([], [
            ResourceActionGrantService::createRoleAction(
                TestResources::TEST_RESOURCE_CLASS, TestResources::DELETE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE
            ),
            ResourceActionGrantService::createRoleAction(
                TestResources::TEST_RESOURCE_CLASS_3, TestResources::WRITE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
        ]
        );

        $rolesReturned = $this->roleProviderTester->getPage(1, 10, [
            Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS]);
        $this->assertCount(2, $rolesReturned);
        $this->assertCount(1, $this->selectWhere($rolesReturned, fn (Role $role) => $role->getIdentifier() === $role1->getIdentifier()));
        $this->assertCount(1, $this->selectWhere($rolesReturned, fn (Role $role) => $role->getIdentifier() === $role3->getIdentifier()));

        $rolesReturned = $this->roleProviderTester->getPage(1, 10, [
            Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS_2]);
        $this->assertCount(2, $rolesReturned);
        $this->assertCount(1, $this->selectWhere($rolesReturned, fn (Role $role) => $role->getIdentifier() === $role1->getIdentifier()));
        $this->assertCount(1, $this->selectWhere($rolesReturned, fn (Role $role) => $role->getIdentifier() === $role2->getIdentifier()));

        $rolesReturned = $this->roleProviderTester->getPage(1, 10, [
            Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS_3]);
        $this->assertCount(1, $rolesReturned);
        $this->assertCount(1, $this->selectWhere($rolesReturned, fn (Role $role) => $role->getIdentifier() === $role3->getIdentifier()));

        $rolesReturned = $this->roleProviderTester->getPage(1, 10, [
            Common::RESOURCE_CLASS_QUERY_PARAMETER => 'non-existing-resource-class']);
        $this->assertCount(0, $rolesReturned);
    }
}
