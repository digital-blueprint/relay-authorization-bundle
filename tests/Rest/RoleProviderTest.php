<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
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

        $resourceActionGrantProvider = new RoleProvider($this->authorizationService);
        $this->roleProviderTester = DataProviderTester::create($resourceActionGrantProvider, Role::class);
    }

    public function testGetRolesWithManageGrant(): void
    {
        $rolesReturned = $this->roleProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => ResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => ResourceActionGrantService::RESOURCE_RESOURCE_TYPE,
            ]
        );
        $this->assertCount(0, $rolesReturned);

        $rolesReturned = $this->roleProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => ResourceActionGrantService::RESOURCE_RESOURCE_TYPE,
            ]
        );
        $this->assertCount(0, $rolesReturned);

        $this->addResourceAndGrant(
            TestResources::TEST_RESOURCE_CLASS,
            ResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );

        $rolesReturned = $this->roleProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => ResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => ResourceActionGrantService::RESOURCE_RESOURCE_TYPE,
            ]
        );
        $this->assertCount(1, $rolesReturned);
        $this->assertCount(1, $this->selectWhere($rolesReturned,
            function (Role $role): bool {
                return $role->getIdentifier() === ResourceActionGrantService::MANAGER_ROLE_IDENTIFIER
                    && count($role->getRoleActions()) === 2
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getResourceClass() === null
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getAction() === ResourceActionGrantService::MANAGE_ACTION
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getActionType() === ResourceActionGrantService::ITEM_ACTION_TYPE
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getResourceClass() === null
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getAction() === ResourceActionGrantService::MANAGE_ACTION
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getActionType() === ResourceActionGrantService::COLLECTION_ACTION_TYPE
                    && $role->getRoleNames()[0]->getLanguageTag() === 'en'
                    && $role->getRoleNames()[0]->getName() === 'Manager'
                    && $role->getRoleNames()[1]->getLanguageTag() === 'de'
                    && $role->getRoleNames()[1]->getName() === 'Verwalter';
            }
        ));

        $rolesReturned = $this->roleProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => ResourceActionGrantService::RESOURCE_RESOURCE_TYPE,
            ]
        );
        $this->assertCount(0, $rolesReturned);

        $this->addResourceAndGrant(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            ResourceActionGrantService::MANAGE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );

        $rolesReturned = $this->roleProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => ResourceActionGrantService::RESOURCE_RESOURCE_TYPE,
            ]
        );
        $this->assertCount(1, $rolesReturned);
        $this->assertCount(1, $this->selectWhere($rolesReturned,
            function (Role $role): bool {
                return $role->getIdentifier() === ResourceActionGrantService::MANAGER_ROLE_IDENTIFIER
                    && count($role->getRoleActions()) === 2
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getResourceClass() === null
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getAction() === ResourceActionGrantService::MANAGE_ACTION
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getActionType() === ResourceActionGrantService::ITEM_ACTION_TYPE
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getResourceClass() === null
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getAction() === ResourceActionGrantService::MANAGE_ACTION
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getActionType() === ResourceActionGrantService::COLLECTION_ACTION_TYPE
                    && $role->getRoleNames()[0]->getLanguageTag() === 'en'
                    && $role->getRoleNames()[0]->getName() === 'Manager'
                    && $role->getRoleNames()[1]->getLanguageTag() === 'de'
                    && $role->getRoleNames()[1]->getName() === 'Verwalter';
            }
        ));

        $roleActionRead = ResourceActionGrantService::createRoleAction(
            TestResources::TEST_RESOURCE_CLASS,
            TestResources::READ_ACTION,
            ResourceActionGrantService::ITEM_ACTION_TYPE);
        $roleActionCreate = ResourceActionGrantService::createRoleAction(
            TestResources::TEST_RESOURCE_CLASS,
            TestResources::CREATE_ACTION,
            ResourceActionGrantService::COLLECTION_ACTION_TYPE);

        $roleReadCreate = $this->internalResourceActionGrantService->addRole(
            [
                'en' => 'Creator',
                'de' => 'Ersteller',
            ],
            [
                $roleActionRead,
                $roleActionCreate,
            ]
        );
        $roleRead = $this->internalResourceActionGrantService->addRole([],
            [
                $roleActionRead,
            ]
        );
        $roleCreate = $this->internalResourceActionGrantService->addRole([],
            [
                $roleActionCreate,
            ]
        );

        $rolesReturned = $this->roleProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => ResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => ResourceActionGrantService::RESOURCE_RESOURCE_TYPE,
            ]
        );
        $this->assertCount(3, $rolesReturned);
        $this->assertCount(1, $this->selectWhere($rolesReturned,
            function (Role $role): bool {
                return $role->getIdentifier() === ResourceActionGrantService::MANAGER_ROLE_IDENTIFIER
                    && count($role->getRoleActions()) === 2
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getResourceClass() === null
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getAction() === ResourceActionGrantService::MANAGE_ACTION
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getActionType() === ResourceActionGrantService::ITEM_ACTION_TYPE
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getResourceClass() === null
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getAction() === ResourceActionGrantService::MANAGE_ACTION
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getActionType() === ResourceActionGrantService::COLLECTION_ACTION_TYPE
                    && $role->getRoleNames()[0]->getLanguageTag() === 'en'
                    && $role->getRoleNames()[0]->getName() === 'Manager'
                    && $role->getRoleNames()[1]->getLanguageTag() === 'de'
                    && $role->getRoleNames()[1]->getName() === 'Verwalter';
            }
        ));
        $this->assertCount(1, $this->selectWhere($rolesReturned,
            function (Role $role) use ($roleReadCreate): bool {
                return $role->getIdentifier() === $roleReadCreate->getIdentifier()
                    && count($role->getRoleActions()) === 2
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getResourceClass() === TestResources::TEST_RESOURCE_CLASS
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getAction() === TestResources::READ_ACTION
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getActionType() === ResourceActionGrantService::ITEM_ACTION_TYPE
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getResourceClass() === TestResources::TEST_RESOURCE_CLASS
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getAction() === TestResources::CREATE_ACTION
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getActionType() === ResourceActionGrantService::COLLECTION_ACTION_TYPE
                    && count($role->getRoleNames()) === 2
                    && $role->getRoleNames()[0]->getLanguageTag() === 'en'
                    && $role->getRoleNames()[0]->getName() === 'Creator'
                    && $role->getRoleNames()[1]->getLanguageTag() === 'de'
                    && $role->getRoleNames()[1]->getName() === 'Ersteller';
            }
        ));
        $this->assertCount(1, $this->selectWhere($rolesReturned,
            function (Role $role) use ($roleCreate): bool {
                return $role->getIdentifier() === $roleCreate->getIdentifier()
                    && count($role->getRoleActions()) === 1
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getResourceClass() === TestResources::TEST_RESOURCE_CLASS
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getAction() === TestResources::CREATE_ACTION
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getActionType() === ResourceActionGrantService::COLLECTION_ACTION_TYPE;
            }
        ));

        $rolesReturned = $this->roleProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => ResourceActionGrantService::RESOURCE_RESOURCE_TYPE,
            ]
        );
        $this->assertCount(3, $rolesReturned);
        $this->assertCount(1, $this->selectWhere($rolesReturned,
            function (Role $role): bool {
                return $role->getIdentifier() === ResourceActionGrantService::MANAGER_ROLE_IDENTIFIER
                    && count($role->getRoleActions()) === 2
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getResourceClass() === null
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getAction() === ResourceActionGrantService::MANAGE_ACTION
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getActionType() === ResourceActionGrantService::ITEM_ACTION_TYPE
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getResourceClass() === null
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getAction() === ResourceActionGrantService::MANAGE_ACTION
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getActionType() === ResourceActionGrantService::COLLECTION_ACTION_TYPE
                    && $role->getRoleNames()[0]->getLanguageTag() === 'en'
                    && $role->getRoleNames()[0]->getName() === 'Manager'
                    && $role->getRoleNames()[1]->getLanguageTag() === 'de'
                    && $role->getRoleNames()[1]->getName() === 'Verwalter';
            }
        ));
        $this->assertCount(1, $this->selectWhere($rolesReturned,
            function (Role $role) use ($roleReadCreate): bool {
                return $role->getIdentifier() === $roleReadCreate->getIdentifier()
                    && count($role->getRoleActions()) === 2
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getResourceClass() === TestResources::TEST_RESOURCE_CLASS
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getAction() === TestResources::READ_ACTION
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getActionType() === ResourceActionGrantService::ITEM_ACTION_TYPE
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getResourceClass() === TestResources::TEST_RESOURCE_CLASS
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getAction() === TestResources::CREATE_ACTION
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getActionType() === ResourceActionGrantService::COLLECTION_ACTION_TYPE
                    && count($role->getRoleNames()) === 2
                    && $role->getRoleNames()[0]->getLanguageTag() === 'en'
                    && $role->getRoleNames()[0]->getName() === 'Creator'
                    && $role->getRoleNames()[1]->getLanguageTag() === 'de'
                    && $role->getRoleNames()[1]->getName() === 'Ersteller';
            }
        ));
        $this->assertCount(1, $this->selectWhere($rolesReturned,
            function (Role $role) use ($roleRead): bool {
                return $role->getIdentifier() === $roleRead->getIdentifier()
                    && count($role->getRoleActions()) === 1
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getResourceClass() === TestResources::TEST_RESOURCE_CLASS
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getAction() === TestResources::READ_ACTION
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getActionType() === ResourceActionGrantService::ITEM_ACTION_TYPE;
            }
        ));
    }

    public function testGetRolesItemResource(): void
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
            TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE);
        $roleActions2[] = ResourceActionGrantService::createRoleAction(
            TestResources::TEST_RESOURCE_CLASS, TestResources::UPDATE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE);
        $localizedRoleNames2 = [
            'en' => 'Editor',
            'de' => 'Redakteur',
        ];
        $role2 = $this->internalResourceActionGrantService->addRole(
            $localizedRoleNames2, $roleActions2
        );

        // noise:
        $this->internalResourceActionGrantService->addRole([],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE),
            ]
        );

        $rolesReturned = $this->roleProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => ResourceActionGrantService::RESOURCE_RESOURCE_TYPE,
            ]
        );
        $this->assertCount(0, $rolesReturned);

        $this->addResourceAndGrant(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );

        $rolesReturned = $this->roleProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => ResourceActionGrantService::RESOURCE_RESOURCE_TYPE,
            ]
        );
        $this->assertCount(1, $rolesReturned);
        $this->assertCount(1, $this->selectWhere($rolesReturned,
            function (Role $role) use ($role1): bool {
                return $role->getIdentifier() === $role1->getIdentifier()
                    && count($role->getRoleActions()) === 1
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getResourceClass() === TestResources::TEST_RESOURCE_CLASS
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getAction() === TestResources::READ_ACTION
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getActionType() === ResourceActionGrantService::ITEM_ACTION_TYPE
                    && count($role->getRoleNames()) === 2
                    && $role->getRoleNames()[0]->getLanguageTag() === 'en'
                    && $role->getRoleNames()[0]->getName() === 'Reader'
                    && $role->getRoleNames()[1]->getLanguageTag() === 'de'
                    && $role->getRoleNames()[1]->getName() === 'Leser';
            }
        ));

        $this->addResourceAndGrant(
            TestResources::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            TestResources::UPDATE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );

        $rolesReturned = $this->roleProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => self::TEST_RESOURCE_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => ResourceActionGrantService::RESOURCE_RESOURCE_TYPE,
            ]
        );
        $this->assertCount(2, $rolesReturned);
        $this->assertCount(1, $this->selectWhere($rolesReturned,
            function (Role $role) use ($role1): bool {
                return $role->getIdentifier() === $role1->getIdentifier()
                    && count($role->getRoleActions()) === 1
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getResourceClass() === TestResources::TEST_RESOURCE_CLASS
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getAction() === TestResources::READ_ACTION
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getActionType() === ResourceActionGrantService::ITEM_ACTION_TYPE
                    && count($role->getRoleNames()) === 2
                    && $role->getRoleNames()[0]->getLanguageTag() === 'en'
                    && $role->getRoleNames()[0]->getName() === 'Reader'
                    && $role->getRoleNames()[1]->getLanguageTag() === 'de'
                    && $role->getRoleNames()[1]->getName() === 'Leser';
            }
        ));
        $this->assertCount(1, $this->selectWhere($rolesReturned,
            function (Role $role) use ($role2): bool {
                return $role->getIdentifier() === $role2->getIdentifier()
                    && count($role->getRoleActions()) === 2
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getResourceClass() === TestResources::TEST_RESOURCE_CLASS
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getAction() === TestResources::READ_ACTION
                    && $role->getRoleActions()[0]->getAvailableResourceClassAction()->getActionType() === ResourceActionGrantService::ITEM_ACTION_TYPE
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getResourceClass() === TestResources::TEST_RESOURCE_CLASS
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getAction() === TestResources::UPDATE_ACTION
                    && $role->getRoleActions()[1]->getAvailableResourceClassAction()->getActionType() === ResourceActionGrantService::ITEM_ACTION_TYPE
                    && count($role->getRoleNames()) === 2
                    && $role->getRoleNames()[0]->getLanguageTag() === 'en'
                    && $role->getRoleNames()[0]->getName() === 'Editor'
                    && $role->getRoleNames()[1]->getLanguageTag() === 'de'
                    && $role->getRoleNames()[1]->getName() === 'Redakteur';
            }
        ));
    }

    public function testGetRolesCollectionResource(): void
    {
        $role1 = $this->internalResourceActionGrantService->addRole([], [
            ResourceActionGrantService::createRoleAction(
                TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE),
            ResourceActionGrantService::createRoleAction(
                TestResources::TEST_RESOURCE_CLASS, TestResources::CREATE_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE),
        ]
        );
        $role2 = $this->internalResourceActionGrantService->addRole([], [
            ResourceActionGrantService::createRoleAction(
                TestResources::TEST_RESOURCE_CLASS, TestResources::CREATE_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE),
        ]
        );
        // noise:
        $this->internalResourceActionGrantService->addRole([], [
            ResourceActionGrantService::createRoleAction(
                TestResources::TEST_RESOURCE_CLASS, TestResources::DELETE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE
            ),
            ResourceActionGrantService::createRoleAction(
                TestResources::TEST_RESOURCE_CLASS, TestResources::WRITE_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
        ]
        );
        $this->internalResourceActionGrantService->addRole([], [
            ResourceActionGrantService::createRoleAction(
                TestResources::TEST_RESOURCE_CLASS_2, TestResources::CREATE_ACTION, ResourceActionGrantService::COLLECTION_ACTION_TYPE
            ),
        ]
        );

        $rolesReturned = $this->roleProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => ResourceActionGrantService::RESOURCE_RESOURCE_TYPE,
            ]
        );
        $this->assertCount(0, $rolesReturned);

        $this->addResourceAndGrant(
            TestResources::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            TestResources::CREATE_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );

        $rolesReturned = $this->roleProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => ResourceActionGrantService::RESOURCE_RESOURCE_TYPE,
            ]
        );
        $this->assertCount(1, $rolesReturned);
        $this->assertCount(1, $this->selectWhere($rolesReturned, fn (Role $role) => $role->getIdentifier() === $role2->getIdentifier()));

        $this->addResourceAndGrant(
            TestResources::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );

        $rolesReturned = $this->roleProviderTester->getCollection(
            filters: [
                Common::RESOURCE_CLASS_QUERY_PARAMETER => TestResources::TEST_RESOURCE_CLASS,
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER => AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
                Common::RESOURCE_TYPE_QUERY_PARAMETER => ResourceActionGrantService::RESOURCE_RESOURCE_TYPE,
            ]
        );
        $this->assertCount(2, $rolesReturned);
        $this->assertCount(1, $this->selectWhere($rolesReturned, fn (Role $role) => $role->getIdentifier() === $role1->getIdentifier()));
        $this->assertCount(1, $this->selectWhere($rolesReturned, fn (Role $role) => $role->getIdentifier() === $role2->getIdentifier()));
    }
}
