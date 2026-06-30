<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Service;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractInternalResourceActionGrantServiceTestCase;
use Dbp\Relay\AuthorizationBundle\Tests\TestResources;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class InternalResourceActionGrantServiceTest extends AbstractInternalResourceActionGrantServiceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->internalResourceActionGrantService->ensureManageActionsAreAvailable();
        $this->internalResourceActionGrantService->setAvailableResourceClassActions(self::TEST_RESOURCE_CLASS,
            TestResources::TEST_RESOURCE_ITEM_ACTIONS,
            TestResources::TEST_RESOURCE_COLLECTION_ACTIONS);
        $this->internalResourceActionGrantService->setAvailableResourceClassActions(self::TEST_RESOURCE_GROUP_CLASS,
            TestResources::TEST_RESOURCE_ITEM_ACTIONS,
            TestResources::TEST_RESOURCE_COLLECTION_ACTIONS);
        $this->internalResourceActionGrantService->setAvailableResourceClassActions(self::TEST_RESOURCE_CLASS_2,
            TestResources::TEST_RESOURCE_2_ITEM_ACTIONS,
            TestResources::TEST_RESOURCE_2_COLLECTION_ACTIONS);
        $this->internalResourceActionGrantService->setAvailableResourceClassActions(self::TEST_RESOURCE_CLASS_3,
            TestResources::TEST_RESOURCE_3_ITEM_ACTIONS,
            TestResources::TEST_RESOURCE_3_COLLECTION_ACTIONS);
    }

    public function testAddResourceActionGrantByResourceClassAndIdentifier(): void
    {
        // resource item, user grant
        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrantByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            AuthorizationService::MANAGE_ACTION,
            self::CURRENT_USER_IDENTIFIER);

        $this->assertTrue(Uuid::isValid($resourceActionGrant->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getResourceIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $resourceActionGrant->getAction());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $resourceActionGrant->getUserIdentifier());
        $this->assertEquals(null, $resourceActionGrant->getUserGroup());
        $this->assertEquals(null, $resourceActionGrant->getDynamicUserGroupIdentifier());
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getAuthorizationResource()->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier());
        $this->assertTrue(Uuid::isValid($resourceActionGrant->getAuthorizationResource()->getIdentifier()));

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());
        $this->assertEquals($resourceActionGrant->getResourceClass(), $resourceActionGrantPersistence->getResourceClass());
        $this->assertEquals($resourceActionGrant->getResourceIdentifier(), $resourceActionGrantPersistence->getResourceIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantPersistence->getAction());
        $this->assertEquals($resourceActionGrant->getUserIdentifier(), $resourceActionGrantPersistence->getUserIdentifier());
        $this->assertEquals($resourceActionGrant->getUserGroup(), $resourceActionGrantPersistence->getUserGroup());
        $this->assertEquals($resourceActionGrant->getDynamicUserGroupIdentifier(), $resourceActionGrantPersistence->getDynamicUserGroupIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getResourceClass(), $resourceActionGrantPersistence->getAuthorizationResource()->getResourceClass());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getResourceIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getResourceIdentifier());

        $authorizationResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals($authorizationResource->getIdentifier(), $resourceActionGrant->getAuthorizationResource()->getIdentifier());
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $authorizationResource->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $authorizationResource->getResourceIdentifier());

        // resource collection, dynamic group grant
        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrantByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            AuthorizationService::MANAGE_ACTION, null, null, 'everybody');

        $this->assertTrue(Uuid::isValid($resourceActionGrant->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getResourceClass());
        $this->assertEquals(
            InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER,
            $resourceActionGrant->getResourceIdentifier()
        );
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $resourceActionGrant->getAction());
        $this->assertEquals(null, $resourceActionGrant->getUserIdentifier());
        $this->assertEquals(null, $resourceActionGrant->getUserGroup());
        $this->assertEquals('everybody', $resourceActionGrant->getDynamicUserGroupIdentifier());
        $this->assertEquals(
            self::TEST_RESOURCE_CLASS,
            $resourceActionGrant->getAuthorizationResource()->getResourceClass()
        );
        $this->assertEquals(
            InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER,
            $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier()
        );
        $this->assertTrue(Uuid::isValid($resourceActionGrant->getAuthorizationResource()->getIdentifier()));

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());
        $this->assertEquals($resourceActionGrant->getResourceClass(), $resourceActionGrantPersistence->getResourceClass());
        $this->assertEquals($resourceActionGrant->getResourceIdentifier(), $resourceActionGrantPersistence->getResourceIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantPersistence->getAction());
        $this->assertEquals($resourceActionGrant->getUserIdentifier(), $resourceActionGrantPersistence->getUserIdentifier());
        $this->assertEquals($resourceActionGrant->getUserGroup(), $resourceActionGrantPersistence->getUserGroup());
        $this->assertEquals($resourceActionGrant->getDynamicUserGroupIdentifier(), $resourceActionGrantPersistence->getDynamicUserGroupIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getResourceClass(), $resourceActionGrantPersistence->getAuthorizationResource()->getResourceClass());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getResourceIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getResourceIdentifier());

        $authorizationResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS,
            InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertEquals($authorizationResource->getIdentifier(),
            $resourceActionGrant->getAuthorizationResource()->getIdentifier());
        $this->assertEquals(self::TEST_RESOURCE_CLASS,
            $authorizationResource->getResourceClass());
        $this->assertEquals(InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER,
            $authorizationResource->getResourceIdentifier());

        $userGroup = $this->testEntityManager->addUserGroup();
        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrantByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER,
            TestResources::UPDATE_ACTION, null, $userGroup);

        $this->assertTrue(Uuid::isValid($resourceActionGrant->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS_2, $resourceActionGrant->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getResourceIdentifier());
        $this->assertEquals(TestResources::UPDATE_ACTION, $resourceActionGrant->getAction());
        $this->assertEquals(null, $resourceActionGrant->getUserIdentifier());
        $this->assertEquals($userGroup, $resourceActionGrant->getUserGroup());
        $this->assertEquals(null, $resourceActionGrant->getDynamicUserGroupIdentifier());
        $this->assertEquals(self::TEST_RESOURCE_CLASS_2, $resourceActionGrant->getAuthorizationResource()->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier());
        $this->assertTrue(Uuid::isValid($resourceActionGrant->getAuthorizationResource()->getIdentifier()));

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());
        $this->assertEquals($resourceActionGrant->getResourceClass(), $resourceActionGrantPersistence->getResourceClass());
        $this->assertEquals($resourceActionGrant->getResourceIdentifier(), $resourceActionGrantPersistence->getResourceIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantPersistence->getAction());
        $this->assertEquals($resourceActionGrant->getUserIdentifier(), $resourceActionGrantPersistence->getUserIdentifier());
        $this->assertEquals($resourceActionGrant->getUserGroup(), $resourceActionGrantPersistence->getUserGroup());
        $this->assertEquals($resourceActionGrant->getDynamicUserGroupIdentifier(), $resourceActionGrantPersistence->getDynamicUserGroupIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getResourceClass(), $resourceActionGrantPersistence->getAuthorizationResource()->getResourceClass());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getResourceIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getResourceIdentifier());
    }

    public function testAddResourceActionGrantWithAction(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($authorizationResource);
        $resourceActionGrant->setAction(AuthorizationService::MANAGE_ACTION);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrant(
            $resourceActionGrant, self::CURRENT_USER_IDENTIFIER);
        $this->assertTrue(Uuid::isValid($resourceActionGrant->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getResourceIdentifier());
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $resourceActionGrant->getAction());
        $this->assertEquals(AvailableResourceClassAction::ITEM_ACTION_TYPE, $resourceActionGrant->getActionType());
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getActionResourceClass());
        $this->assertEquals(null, $resourceActionGrant->getAvailableResourceClassAction()->getResourceClass()); // only for manage action
        $this->assertEquals(AuthorizationService::MANAGE_ACTION, $resourceActionGrant->getAvailableResourceClassAction()->getAction());
        $this->assertEquals(AvailableResourceClassAction::ITEM_ACTION_TYPE, $resourceActionGrant->getAvailableResourceClassAction()->getActionType());
        $this->assertEquals(null, $resourceActionGrant->getRole());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $resourceActionGrant->getUserIdentifier());
        $this->assertEquals(null, $resourceActionGrant->getUserGroup());
        $this->assertEquals(null, $resourceActionGrant->getDynamicUserGroupIdentifier());
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getAuthorizationResource()->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier());
        $this->assertEquals($authorizationResource->getIdentifier(), $resourceActionGrant->getAuthorizationResource()->getIdentifier());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $resourceActionGrant->getCreatorId());
        $this->assertNotNull($resourceActionGrant->getDateCreated());
        $this->assertFalse($resourceActionGrant->getShareable());

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());
        $this->assertEquals($resourceActionGrant->getResourceClass(), $resourceActionGrantPersistence->getResourceClass());
        $this->assertEquals($resourceActionGrant->getResourceIdentifier(), $resourceActionGrantPersistence->getResourceIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantPersistence->getAction());
        $this->assertEquals($resourceActionGrant->getActionType(), $resourceActionGrantPersistence->getActionType());
        $this->assertEquals($resourceActionGrant->getActionResourceClass(), $resourceActionGrantPersistence->getActionResourceClass());
        $this->assertEquals($resourceActionGrant->getAvailableResourceClassAction()->getResourceClass(), $resourceActionGrantPersistence->getAvailableResourceClassAction()->getResourceClass());
        $this->assertEquals($resourceActionGrant->getAvailableResourceClassAction()->getAction(), $resourceActionGrantPersistence->getAvailableResourceClassAction()->getAction());
        $this->assertEquals($resourceActionGrant->getAvailableResourceClassAction()->getActionType(), $resourceActionGrantPersistence->getAvailableResourceClassAction()->getActionType());
        $this->assertEquals($resourceActionGrant->getRole(), $resourceActionGrantPersistence->getRole());
        $this->assertEquals($resourceActionGrant->getUserIdentifier(), $resourceActionGrantPersistence->getUserIdentifier());
        $this->assertEquals($resourceActionGrant->getUserGroup(), $resourceActionGrantPersistence->getUserGroup());
        $this->assertEquals($resourceActionGrant->getDynamicUserGroupIdentifier(), $resourceActionGrantPersistence->getDynamicUserGroupIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getResourceClass(), $resourceActionGrantPersistence->getAuthorizationResource()->getResourceClass());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getResourceIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getResourceIdentifier());
        $this->assertEquals($resourceActionGrant->getCreatorId(), $resourceActionGrantPersistence->getCreatorId());
        $this->assertEquals($resourceActionGrant->getShareable(), $resourceActionGrantPersistence->getShareable());

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($authorizationResource);
        $resourceActionGrant->setAction(TestResources::READ_ACTION);
        $resourceActionGrant->setShareable(true);

        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrant(
            $resourceActionGrant, self::CURRENT_USER_IDENTIFIER);
        $this->assertEquals(TestResources::READ_ACTION, $resourceActionGrant->getAction());
        $this->assertTrue($resourceActionGrant->getShareable());

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantPersistence->getAction());
        $this->assertEquals($resourceActionGrant->getShareable(), $resourceActionGrantPersistence->getShareable());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());

        $authorizationGroupResource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER
        );

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($authorizationGroupResource);
        $resourceActionGrant->setAction(TestResources::CREATE_ACTION);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrant(
            $resourceActionGrant, null);
        $this->assertEquals(
            InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER,
            $resourceActionGrant->getResourceIdentifier()
        );
        $this->assertEquals(null, $resourceActionGrant->getCreatorId());

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());
        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());
        $this->assertEquals(
            InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER,
            $resourceActionGrantPersistence->getResourceIdentifier()
        );
    }

    public function testAddResourceActionGrantWithRole(): void
    {
        $roleReader = $this->internalResourceActionGrantService->addRole(
            ['en' => 'Reader', 'de' => 'Leser'],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
            ]
        );

        $authorizationResource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($authorizationResource);
        $resourceActionGrant->setRole($roleReader);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrant(
            $resourceActionGrant, self::CURRENT_USER_IDENTIFIER);
        $this->assertTrue(Uuid::isValid($resourceActionGrant->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getResourceIdentifier());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $resourceActionGrant->getCreatorId());
        $this->assertEquals(null, $resourceActionGrant->getAction());
        $this->assertEquals(null, $resourceActionGrant->getActionType());
        $this->assertEquals(null, $resourceActionGrant->getActionResourceClass());
        $this->assertEquals(null, $resourceActionGrant->getAvailableResourceClassAction());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $resourceActionGrant->getUserIdentifier());
        $this->assertEquals(null, $resourceActionGrant->getUserGroup());
        $this->assertEquals(null, $resourceActionGrant->getDynamicUserGroupIdentifier());
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getAuthorizationResource()->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier());
        $this->assertEquals($authorizationResource->getIdentifier(), $resourceActionGrant->getAuthorizationResource()->getIdentifier());
        $this->assertNotNull($resourceActionGrant->getDateCreated());
        $role = $resourceActionGrant->getRole();
        $this->assertEquals($roleReader->getIdentifier(), $role->getIdentifier());
        $this->assertEquals($roleReader->getRoleNames(), $role->getRoleNames());
        $this->assertEquals($roleReader->getRoleActions(), $role->getRoleActions());

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());
        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());
        $this->assertEquals($resourceActionGrant->getResourceClass(), $resourceActionGrantPersistence->getResourceClass());
        $this->assertEquals($resourceActionGrant->getResourceIdentifier(), $resourceActionGrantPersistence->getResourceIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantPersistence->getAction());
        $this->assertEquals($resourceActionGrant->getActionType(), $resourceActionGrantPersistence->getActionType());
        $this->assertEquals($resourceActionGrant->getActionResourceClass(), $resourceActionGrantPersistence->getActionResourceClass());
        $this->assertEquals($resourceActionGrant->getAvailableResourceClassAction(), $resourceActionGrantPersistence->getAvailableResourceClassAction());
        $this->assertEquals($resourceActionGrant->getRole()->getIdentifier(), $resourceActionGrantPersistence->getRole()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getRole()->getRoleNames(), $resourceActionGrantPersistence->getRole()->getRoleNames());
        $this->assertEquals($resourceActionGrant->getRole()->getRoleActions(), $resourceActionGrantPersistence->getRole()->getRoleActions());
        $this->assertEquals($resourceActionGrant->getUserIdentifier(), $resourceActionGrantPersistence->getUserIdentifier());
        $this->assertEquals($resourceActionGrant->getUserGroup(), $resourceActionGrantPersistence->getUserGroup());
        $this->assertEquals($resourceActionGrant->getDynamicUserGroupIdentifier(), $resourceActionGrantPersistence->getDynamicUserGroupIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getIdentifier());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getResourceClass(), $resourceActionGrantPersistence->getAuthorizationResource()->getResourceClass());
        $this->assertEquals($resourceActionGrant->getAuthorizationResource()->getResourceIdentifier(), $resourceActionGrantPersistence->getAuthorizationResource()->getResourceIdentifier());
        $this->assertEquals($resourceActionGrant->getCreatorId(), $resourceActionGrantPersistence->getCreatorId());
        $this->assertEquals($resourceActionGrant->getShareable(), $resourceActionGrantPersistence->getShareable());
    }

    public function testAddResourceInvalidActionMissing(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($authorizationResource);
        $resourceActionGrant->setUserIdentifier('userIdentifier');

        try {
            $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant, self::CURRENT_USER_IDENTIFIER);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ACTION_AND_ROLE_MISSING_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testAddResourceInvalidActionUndefined(): void
    {
        $itemResource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($itemResource);
        // action is only defined for resource collections -> fail
        $resourceActionGrant->setAction(TestResources::CREATE_ACTION);
        $resourceActionGrant->setUserIdentifier('userIdentifier');

        try {
            $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant, self::CURRENT_USER_IDENTIFIER);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ACTION_UNDEFINED_ERROR_ID, $apiError->getErrorId());
        }

        $collectionResource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($collectionResource);
        // action is only defined for resource items -> fail
        $resourceActionGrant->setAction(TestResources::WRITE_ACTION);
        $resourceActionGrant->setUserIdentifier('userIdentifier');

        try {
            $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant, self::CURRENT_USER_IDENTIFIER);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ACTION_UNDEFINED_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testRemoveAuthorizationResourceCascadeDelete(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals($authorizationResource->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($authorizationResource->getIdentifier())->getIdentifier());

        $resourceActionGrant = $this->testEntityManager->addResourceActionGrant(
            $authorizationResource, AuthorizationService::MANAGE_ACTION, 'userIdentifier');
        $this->assertEquals($resourceActionGrant->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier())->getIdentifier());

        $this->internalResourceActionGrantService->removeAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($authorizationResource->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier()));
    }

    public function testRemoveAuthorizationResourceByResourceClassAndIdentifier(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals($authorizationResource->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($authorizationResource->getIdentifier())->getIdentifier());

        $this->internalResourceActionGrantService->removeAuthorizationResourceByResourceClassAndIdentifier(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($authorizationResource->getIdentifier()));
    }

    public function testRemoveAuthorizationResourceByResourceClassAndIdentifierCascadeDelete(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $userGroup = $this->testEntityManager->addUserGroup();

        $this->assertEquals($authorizationResource->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($authorizationResource->getIdentifier())->getIdentifier());
        $this->assertEquals($userGroup->getIdentifier(),
            $this->testEntityManager->getUserGroup($userGroup->getIdentifier())->getIdentifier());

        $resourceActionGrant = $this->testEntityManager->addResourceActionGrant(
            $authorizationResource, AuthorizationService::MANAGE_ACTION, 'userIdentifier');
        $resourceActionGrantGroup = $this->testEntityManager->addResourceActionGrant(
            $authorizationResource, AuthorizationService::MANAGE_ACTION, null, $userGroup);

        $this->assertEquals($resourceActionGrant->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrantGroup->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrantGroup->getIdentifier())->getIdentifier());

        $this->internalResourceActionGrantService->removeAuthorizationResourceByResourceClassAndIdentifier(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($authorizationResource->getIdentifier()));
        // assert that group has not been deleted alongside with group grant
        $this->assertEquals($userGroup->getIdentifier(),
            $this->testEntityManager->getUserGroup($userGroup->getIdentifier())->getIdentifier());

        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrantGroup->getIdentifier()));
    }

    public function testRemoveResourceActionGrant(): void
    {
        $resourceActionGrant = $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->internalResourceActionGrantService->removeResourceActionGrant($resourceActionGrant);

        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier()));
    }

    public function testRemoveResourceActionGrants(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup();

        $resource1_1 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $resource1_2 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER.'_2');

        $resource2 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER);

        $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::READ_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::UPDATE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->testEntityManager->addResourceActionGrant($resource1_2,
            TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1_2,
            TestResources::UPDATE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, userGroup: $userGroup);
        $this->testEntityManager->addResourceActionGrant($resource2,
            TestResources::DELETE_ACTION, dynamicUserGroupIdentifier: 'everybody');

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource();
        $this->assertCount(7, $resourceActionGrants);

        $this->internalResourceActionGrantService->removeResourceActionGrants();
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource();
        $this->assertCount(0, $resourceActionGrants);

        $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::READ_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::UPDATE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->testEntityManager->addResourceActionGrant($resource1_2,
            TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1_2,
            TestResources::UPDATE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, userGroup: $userGroup);
        $this->testEntityManager->addResourceActionGrant($resource2,
            TestResources::UPDATE_ACTION, dynamicUserGroupIdentifier: 'everybody');

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(5, $resourceActionGrants);

        $this->internalResourceActionGrantService->removeResourceActionGrants(self::TEST_RESOURCE_CLASS);
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(0, $resourceActionGrants);

        $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::READ_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::UPDATE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->testEntityManager->addResourceActionGrant($resource1_2,
            TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1_2,
            TestResources::UPDATE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(3, $resourceActionGrants);

        $this->internalResourceActionGrantService->removeResourceActionGrants(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER);
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(0, $resourceActionGrants);

        $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::READ_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::UPDATE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            null, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(5, $resourceActionGrants);

        $this->internalResourceActionGrantService->removeResourceActionGrants(null,
            self::TEST_RESOURCE_IDENTIFIER);
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            null, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(0, $resourceActionGrants);

        $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::READ_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $updateGrant = $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::UPDATE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, userGroup: $userGroup);
        $this->testEntityManager->addResourceActionGrant($resource2,
            TestResources::UPDATE_ACTION, dynamicUserGroupIdentifier: 'everybody');

        $this->assertCount(3,
            $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
                self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER));
        $this->internalResourceActionGrantService->removeResourceActionGrants(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER, [TestResources::READ_ACTION]);
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($updateGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());

        $this->internalResourceActionGrantService->removeResourceActionGrants(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER, [TestResources::UPDATE_ACTION]);
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(0, $resourceActionGrants);

        $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
        $anotherUserGrant = $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::READ_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::UPDATE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->internalResourceActionGrantService->removeResourceActionGrants(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER, userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($anotherUserGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());

        $this->internalResourceActionGrantService->removeResourceActionGrants(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER, userIdentifier: self::ANOTHER_USER_IDENTIFIER);
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(0, $resourceActionGrants);

        $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::READ_ACTION, self::CURRENT_USER_IDENTIFIER);
        $anotherUserGrant = $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::READ_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $updateGrant = $this->testEntityManager->addResourceActionGrant($resource1_1,
            TestResources::UPDATE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->internalResourceActionGrantService->removeResourceActionGrants(self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER, [TestResources::READ_ACTION],
            self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(2, $resourceActionGrants);
        $this->assertCount(1, $this->selectWhere($resourceActionGrants, function (ResourceActionGrant $arg) use ($anotherUserGrant) {
            return $arg->getIdentifier() === $anotherUserGrant->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($resourceActionGrants, function (ResourceActionGrant $arg) use ($updateGrant) {
            return $arg->getIdentifier() === $updateGrant->getIdentifier();
        }));

        $this->assertCount(1,
            $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
                groupIdentifiers: [$userGroup->getIdentifier()]));
        $this->internalResourceActionGrantService->removeResourceActionGrants(groupIdentifiers: [$userGroup->getIdentifier()]);
        $this->assertCount(0,
            $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
                groupIdentifiers: [$userGroup->getIdentifier()]));

        $this->assertCount(1,
            $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
                dynamicUserGroupIdentifiers: ['everybody']));
        $this->internalResourceActionGrantService->removeResourceActionGrants(dynamicUserGroupIdentifiers: ['everybody']);
        $this->assertCount(0,
            $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
                dynamicUserGroupIdentifiers: ['everybody']));

        $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, userGroup: $userGroup);
        $this->testEntityManager->addResourceActionGrant($resource2,
            TestResources::UPDATE_ACTION, dynamicUserGroupIdentifier: 'everybody');

        $this->assertCount(1,
            $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
                self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER,
                groupIdentifiers: [$userGroup->getIdentifier()]));
        $this->internalResourceActionGrantService->removeResourceActionGrants(
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER,
            groupIdentifiers: [$userGroup->getIdentifier()]);
        $this->assertCount(0,
            $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
                self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER,
                groupIdentifiers: [$userGroup->getIdentifier()]));

        $this->assertCount(1,
            $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
                self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER,
                dynamicUserGroupIdentifiers: ['everybody']));
        $this->internalResourceActionGrantService->removeResourceActionGrants(
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER,
            dynamicUserGroupIdentifiers: ['everybody']);
        $this->assertCount(0,
            $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
                self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER,
                dynamicUserGroupIdentifiers: ['everybody']));
    }

    public function testGetResourceActionGrantsForResourceClassAndIdentifierUserGrantsOnly(): void
    {
        $resource1 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $resource2 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, 'resourceIdentifier_2');
        $resourceCollection = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $resourceClass2Resource = $this->testEntityManager->addAuthorizationResource(
            'resourceClass_2', self::TEST_RESOURCE_IDENTIFIER);

        $collectionResource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_GROUP_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource();
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrant1_1 = $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant1_2 = $this->testEntityManager->addResourceActionGrant($resource1,
            'read', self::ANOTHER_USER_IDENTIFIER);
        $resourceActionGrant2_1 = $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $resourceActionGrant2_2 = $this->testEntityManager->addResourceActionGrant($resource2,
            'delete', self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrantCollection1_1 = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrantCollection1_2 = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            'create', self::ANOTHER_USER_IDENTIFIER.'_2');
        $resourceClass2ResourceActionGrant = $this->testEntityManager->addResourceActionGrant($resourceClass2Resource,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER.'_2');

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource();
        $this->assertCount(7, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_2);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant2_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant2_2);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_2);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceClass2ResourceActionGrant);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(6, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_2);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant2_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant2_2);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_2);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            'resourceClass_2');
        $this->assertCount(1, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceClass2ResourceActionGrant);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            'resourceClass_3');
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(2, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_2);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, 'resourceIdentifier_2');
        $this->assertCount(2, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant2_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant2_2);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertCount(2, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_2);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, 'resourceIdentifier_3');
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_1);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            null, null,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(3, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant2_2);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_1);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            userIdentifier: 'userIdentifier_2');
        $this->assertCount(0, $resourceActionGrants);

        // test pagination:
        $resourceActionGrantPage1 = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            null, null,
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
            firstResultIndex: 0,
            maxNumResults: 2);
        $this->assertCount(2, $resourceActionGrantPage1);

        $resourceActionGrantPage2 = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
            firstResultIndex: 2,
            maxNumResults: 2);
        $this->assertCount(1, $resourceActionGrantPage2);

        $resourceActionGrants = array_merge($resourceActionGrantPage1, $resourceActionGrantPage2);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant2_2);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_1);
    }

    public function testGetResourceActionGrantsForResourceClassAndIdentifierWithGroupResources(): void
    {
        $resource1 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER
        );
        $resourceCollection = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER
        );
        $resourceClass2Resource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS_2,
            self::TEST_RESOURCE_IDENTIFIER
        );

        $collectionResource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_GROUP_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER
        );
        $collectionResourceCollection = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_GROUP_CLASS,
            AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER
        );

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource();
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrant1_1 = $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant1_2 = $this->testEntityManager->addResourceActionGrant($resource1,
            'read', self::ANOTHER_USER_IDENTIFIER);
        $resourceActionGrantCollection1_1 = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrantCollection1_2 = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            'create', self::ANOTHER_USER_IDENTIFIER.'_2');
        $resourceClass2ResourceActionGrant = $this->testEntityManager->addResourceActionGrant($resourceClass2Resource,
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER.'_2');

        $this->testEntityManager->addResourceToGroupResource(
            $collectionResource->getResourceClass(), $collectionResource->getResourceIdentifier(),
            $resource1->getResourceClass(), $resource1->getResourceIdentifier());

        $this->testEntityManager->addResourceToGroupResource(
            $collectionResourceCollection->getResourceClass(), $collectionResourceCollection->getResourceIdentifier(),
            $resourceActionGrantCollection1_2->getResourceClass(), $resourceActionGrantCollection1_2->getResourceIdentifier());

        $collectionResourceActionGrant = $this->testEntityManager->addResourceActionGrant($collectionResource,
            TestResources::DELETE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $collectionResourceCollectionActionGrant = $this->testEntityManager->addResourceActionGrant($collectionResourceCollection,
            TestResources::CREATE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource();
        $this->assertCount(9, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_2);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_2);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceClass2ResourceActionGrant);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $collectionResourceActionGrant);
        $this->assertContainsInheritedResourceActionGrant($resourceActionGrants, $collectionResourceActionGrant, $resource1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $collectionResourceCollectionActionGrant);
        $this->assertContainsInheritedResourceActionGrant($resourceActionGrants, $collectionResourceCollectionActionGrant, $resourceCollection);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(6, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_2);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_2);
        $this->assertContainsInheritedResourceActionGrant($resourceActionGrants, $collectionResourceActionGrant, $resource1);
        $this->assertContainsInheritedResourceActionGrant($resourceActionGrants, $collectionResourceCollectionActionGrant, $resourceCollection);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS_2);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceClass2ResourceActionGrant);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_GROUP_CLASS);
        $this->assertCount(2, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $collectionResourceActionGrant);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $collectionResourceCollectionActionGrant);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_GROUP_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $collectionResourceCollectionActionGrant);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            'resourceClass_3');
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(3, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_2);
        $this->assertContainsInheritedResourceActionGrant($resourceActionGrants, $collectionResourceActionGrant, $resource1);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertCount(3, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_2);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, 'resourceIdentifier_3');
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_1);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            null, null,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(4, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $collectionResourceCollectionActionGrant);
        $this->assertContainsInheritedResourceActionGrant($resourceActionGrants, $collectionResourceCollectionActionGrant, $resourceCollection);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            null, null,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER);
        $this->assertCount(3, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_2);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $collectionResourceActionGrant);
        $this->assertContainsInheritedResourceActionGrant($resourceActionGrants, $collectionResourceActionGrant, $resource1);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, null,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER);
        $this->assertCount(2, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_2);
        $this->assertContainsInheritedResourceActionGrant($resourceActionGrants, $collectionResourceActionGrant, $resource1);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER, 'userIdentifier_2');
        $this->assertCount(0, $resourceActionGrants);

        // test pagination:
        $resourceActionGrantPage1 = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            userIdentifier: self::ANOTHER_USER_IDENTIFIER,
            maxNumResults: 2
        );
        $this->assertCount(2, $resourceActionGrantPage1);

        $resourceActionGrantPage2 = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            userIdentifier: self::ANOTHER_USER_IDENTIFIER,
            firstResultIndex: 2,
            maxNumResults: 2
        );
        $this->assertCount(1, $resourceActionGrantPage2);

        $resourceActionGrants = array_merge($resourceActionGrantPage1, $resourceActionGrantPage2);
        $this->assertCount(3, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_2);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $collectionResourceActionGrant);
        $this->assertContainsInheritedResourceActionGrant($resourceActionGrants, $collectionResourceActionGrant, $resource1);
    }

    public function testGetResourceActionGrantsForResourceClassAndIdentifierWithGroupGrants(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup();

        $resource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $userResourceActionGrant = $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $groupResourceActionGrant = $this->testEntityManager->addResourceActionGrant($resource,
            'read', null, $userGroup);
        $dyamicUserGroupResourceActionGrant = $this->testEntityManager->addResourceActionGrant($resource,
            'read', null, null, 'dyamicUserGroup');

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(3, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($userResourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            groupIdentifiers: [$userGroup->getIdentifier()]);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($groupResourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            dynamicUserGroupIdentifiers: ['dyamicUserGroup']
        );
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($dyamicUserGroupResourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());

        // user, group and dynamic group ID criteria is combined with OR conjunction
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
            groupIdentifiers: [$userGroup->getIdentifier()],
            dynamicUserGroupIdentifiers: ['dyamicUserGroup']
        );
        $this->assertCount(3, $resourceActionGrants);
        $this->assertEquals($userResourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());
        $this->assertEquals($groupResourceActionGrant->getIdentifier(), $resourceActionGrants[1]->getIdentifier());
        $this->assertEquals($dyamicUserGroupResourceActionGrant->getIdentifier(), $resourceActionGrants[2]->getIdentifier());

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            groupIdentifiers: [$userGroup->getIdentifier()],
            dynamicUserGroupIdentifiers: ['dyamicUserGroup']
        );
        $this->assertCount(2, $resourceActionGrants);
        $this->assertEquals($groupResourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());
        $this->assertEquals($dyamicUserGroupResourceActionGrant->getIdentifier(), $resourceActionGrants[1]->getIdentifier());

        // test pagination:
        $resourceActionGrantPage1 = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
            groupIdentifiers: [$userGroup->getIdentifier()],
            dynamicUserGroupIdentifiers: ['dyamicUserGroup'],
            firstResultIndex: 0,
            maxNumResults: 2
        );
        $this->assertCount(2, $resourceActionGrantPage1);
        $this->assertEquals($userResourceActionGrant->getIdentifier(), $resourceActionGrantPage1[0]->getIdentifier());
        $this->assertEquals($groupResourceActionGrant->getIdentifier(), $resourceActionGrantPage1[1]->getIdentifier());

        $resourceActionGrantPage2 = $this->internalResourceActionGrantService->getResourceActionGrantsForResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
            groupIdentifiers: [$userGroup->getIdentifier()],
            dynamicUserGroupIdentifiers: ['dyamicUserGroup'],
            firstResultIndex: 2,
            maxNumResults: 2
        );
        $this->assertCount(1, $resourceActionGrantPage2);
        $this->assertEquals($dyamicUserGroupResourceActionGrant->getIdentifier(), $resourceActionGrantPage2[0]->getIdentifier());
    }

    public function testAddResourceToGroupResource(): void
    {
        $groupAuthorizationResourceMember = $this->internalResourceActionGrantService->addResourceToGroupResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER.'_heir');

        $this->assertTrue(Uuid::isValid($groupAuthorizationResourceMember->getIdentifier()));
        $this->assertTrue(Uuid::isValid($groupAuthorizationResourceMember->getGroupAuthorizationResource()->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $groupAuthorizationResourceMember->getGroupAuthorizationResource()->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $groupAuthorizationResourceMember->getGroupAuthorizationResource()->getResourceIdentifier());
        $this->assertTrue(Uuid::isValid($groupAuthorizationResourceMember->getMemberAuthorizationResource()->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS_2, $groupAuthorizationResourceMember->getMemberAuthorizationResource()->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER.'_heir', $groupAuthorizationResourceMember->getMemberAuthorizationResource()->getResourceIdentifier());

        $groupAuthorizationResourceMemberPersistence = $this->testEntityManager->getGroupAuthorizationResourceMember($groupAuthorizationResourceMember->getIdentifier());
        $this->assertEquals($groupAuthorizationResourceMember->getIdentifier(), $groupAuthorizationResourceMemberPersistence->getIdentifier());
        $this->assertEquals($groupAuthorizationResourceMember->getGroupAuthorizationResource()->getIdentifier(),
            $groupAuthorizationResourceMemberPersistence->getGroupAuthorizationResource()->getIdentifier());
        $this->assertEquals($groupAuthorizationResourceMember->getMemberAuthorizationResource()->getIdentifier(),
            $groupAuthorizationResourceMemberPersistence->getMemberAuthorizationResource()->getIdentifier());

        $sourceAuthorizationResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals($groupAuthorizationResourceMember->getGroupAuthorizationResource()->getIdentifier(),
            $sourceAuthorizationResource->getIdentifier());

        $targetAuthorizationResource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER.'_heir');
        $this->assertEquals($groupAuthorizationResourceMember->getMemberAuthorizationResource()->getIdentifier(),
            $targetAuthorizationResource->getIdentifier());
    }

    public function testRemoveResourceFromGroupResource(): void
    {
        $groupAuthorizationResourceMember =
            $this->internalResourceActionGrantService->addResourceToGroupResource(
                self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
                self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER.'_heir');

        $this->assertNotNull($this->testEntityManager->getGroupAuthorizationResourceMember($groupAuthorizationResourceMember->getIdentifier()));

        $this->internalResourceActionGrantService->removeResourceFromGroupResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER.'_heir');

        $this->assertNull($this->testEntityManager->getGroupAuthorizationResourceMember($groupAuthorizationResourceMember->getIdentifier()));

        // source is a collection resource
        $groupAuthorizationResourceMember = $this->internalResourceActionGrantService->addResourceToGroupResource(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER.'_heir');

        $this->assertNotNull($this->testEntityManager->getGroupAuthorizationResourceMember($groupAuthorizationResourceMember->getIdentifier()));

        $this->internalResourceActionGrantService->removeResourceFromGroupResource(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            self::TEST_RESOURCE_CLASS_2, self::TEST_RESOURCE_IDENTIFIER.'_heir');

        $this->assertNull($this->testEntityManager->getGroupAuthorizationResourceMember($groupAuthorizationResourceMember->getIdentifier()));

        // target is a collection resource
        $groupAuthorizationResourceMember = $this->internalResourceActionGrantService->addResourceToGroupResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            self::TEST_RESOURCE_CLASS_2, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);

        $this->assertNotNull($this->testEntityManager->getGroupAuthorizationResourceMember($groupAuthorizationResourceMember->getIdentifier()));

        $this->internalResourceActionGrantService->removeResourceFromGroupResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            self::TEST_RESOURCE_CLASS_2, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);

        $this->assertNull($this->testEntityManager->getGroupAuthorizationResourceMember($groupAuthorizationResourceMember->getIdentifier()));

        // both are collection resources
        $groupAuthorizationResourceMember = $this->internalResourceActionGrantService->addResourceToGroupResource(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            self::TEST_RESOURCE_CLASS_2, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);

        $this->assertNotNull($this->testEntityManager->getGroupAuthorizationResourceMember($groupAuthorizationResourceMember->getIdentifier()));

        $this->internalResourceActionGrantService->removeResourceFromGroupResource(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER,
            self::TEST_RESOURCE_CLASS_2, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);

        $this->assertNull($this->testEntityManager->getGroupAuthorizationResourceMember($groupAuthorizationResourceMember->getIdentifier()));

        // same resource class
        $groupAuthorizationResourceMember = $this->internalResourceActionGrantService->addResourceToGroupResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER.'_heir');

        $this->assertNotNull($this->testEntityManager->getGroupAuthorizationResourceMember($groupAuthorizationResourceMember->getIdentifier()));

        $this->internalResourceActionGrantService->removeResourceFromGroupResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER.'_heir');

        $this->assertNull($this->testEntityManager->getGroupAuthorizationResourceMember($groupAuthorizationResourceMember->getIdentifier()));
    }

    public function testAddResourceToGroupResourceErrorResourcesIdentical(): void
    {
        try {
            $this->internalResourceActionGrantService->addResourceToGroupResource(
                self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
                self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
            $this->fail('Expected ApiError not thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(InternalResourceActionGrantService::ADDING_RESOURCE_TO_GROUP_RESOURCE_FAILED_ERROR_ID, $apiError->getErrorId());
        }
    }
}
