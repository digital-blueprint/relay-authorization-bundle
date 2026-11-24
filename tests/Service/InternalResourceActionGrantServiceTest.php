<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Service;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Enums\ResourceType;
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

        AuthorizationService::addOrUpdateActionsAndRoles(
            $this->testEntityManager->getEntityManager());

        foreach (TestResources::getAvailableResourceClassActions() as $resourceClass => $availableResourceClassActions) {
            $this->internalResourceActionGrantService->addOrUpdateAvailableResourceClassActions(
                $resourceClass,
                $availableResourceClassActions[AvailableResourceClassAction::ITEM_ACTION_TYPE],
                $availableResourceClassActions[AvailableResourceClassAction::COLLECTION_ACTION_TYPE]
            );
        }
    }

    public function testAddResourceActionGrantActionGrantWithAction(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($authorizationResource);
        $resourceActionGrant->setAction(AuthorizationService::OWN_ACTION);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant->setCreatorId(self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrant(
            $resourceActionGrant);
        $this->assertTrue(Uuid::isValid($resourceActionGrant->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getResourceIdentifier());
        $this->assertEquals(AuthorizationService::OWN_ACTION, $resourceActionGrant->getAction());
        $this->assertEquals(null, $resourceActionGrant->getAvailableResourceClassAction()->getResourceClass()); // only for manage action
        $this->assertEquals(AuthorizationService::OWN_ACTION, $resourceActionGrant->getAvailableResourceClassAction()->getAction());
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
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant->setCreatorId(self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrant(
            $resourceActionGrant);
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
        $resourceActionGrant->setCreatorId(self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrant(
            $resourceActionGrant);
        $this->assertEquals(
            InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER,
            $resourceActionGrant->getResourceIdentifier()
        );
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $resourceActionGrant->getCreatorId());

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());
        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());
        $this->assertEquals(
            InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER,
            $resourceActionGrantPersistence->getResourceIdentifier()
        );
    }

    public function testAddResourceActionGrantActionGrantWithActionCreatingNewResource(): void
    {
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass(self::TEST_RESOURCE_CLASS);
        $resourceActionGrant->setResourceIdentifier(self::TEST_RESOURCE_IDENTIFIER);
        $resourceActionGrant->setAction(AuthorizationService::OWN_ACTION);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant->setCreatorId(self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrant(
            $resourceActionGrant);
        $this->assertTrue(Uuid::isValid($resourceActionGrant->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getResourceIdentifier());
        $this->assertEquals(AuthorizationService::OWN_ACTION, $resourceActionGrant->getAction());
        $this->assertEquals(null, $resourceActionGrant->getAvailableResourceClassAction()->getResourceClass()); // only for manage action
        $this->assertEquals(AuthorizationService::OWN_ACTION, $resourceActionGrant->getAvailableResourceClassAction()->getAction());
        $this->assertEquals(AvailableResourceClassAction::ITEM_ACTION_TYPE, $resourceActionGrant->getAvailableResourceClassAction()->getActionType());
        $this->assertEquals(null, $resourceActionGrant->getRole());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $resourceActionGrant->getUserIdentifier());
        $this->assertEquals(null, $resourceActionGrant->getUserGroup());
        $this->assertEquals(null, $resourceActionGrant->getDynamicUserGroupIdentifier());
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getAuthorizationResource()->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $resourceActionGrant->getCreatorId());
        $this->assertNotNull($resourceActionGrant->getDateCreated());
        $this->assertFalse($resourceActionGrant->getShareable());

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());

        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());
        $this->assertEquals($resourceActionGrant->getResourceClass(), $resourceActionGrantPersistence->getResourceClass());
        $this->assertEquals($resourceActionGrant->getResourceIdentifier(), $resourceActionGrantPersistence->getResourceIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantPersistence->getAction());
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
        $resourceActionGrant->setResourceClass(self::TEST_RESOURCE_CLASS);
        $resourceActionGrant->setResourceIdentifier(self::TEST_RESOURCE_IDENTIFIER);
        $resourceActionGrant->setAction(TestResources::READ_ACTION);
        $resourceActionGrant->setShareable(true);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant->setCreatorId(self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrant(
            $resourceActionGrant);
        $this->assertEquals(TestResources::READ_ACTION, $resourceActionGrant->getAction());
        $this->assertTrue($resourceActionGrant->getShareable());

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());
        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());
        $this->assertEquals($resourceActionGrant->getResourceClass(), $resourceActionGrantPersistence->getResourceClass());
        $this->assertEquals($resourceActionGrant->getResourceIdentifier(), $resourceActionGrantPersistence->getResourceIdentifier());
        $this->assertEquals($resourceActionGrant->getAction(), $resourceActionGrantPersistence->getAction());
        $this->assertEquals($resourceActionGrant->getShareable(), $resourceActionGrantPersistence->getShareable());

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass(self::TEST_RESOURCE_CLASS);
        $resourceActionGrant->setResourceIdentifier(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $resourceActionGrant->setAction(TestResources::CREATE_ACTION);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant->setCreatorId(self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrant(
            $resourceActionGrant);
        $this->assertEquals(
            InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER,
            $resourceActionGrant->getResourceIdentifier()
        );
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $resourceActionGrant->getCreatorId());

        $resourceActionGrantPersistence = $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier());
        $this->assertEquals($resourceActionGrant->getIdentifier(), $resourceActionGrantPersistence->getIdentifier());
        $this->assertEquals(
            InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER,
            $resourceActionGrantPersistence->getResourceIdentifier()
        );
    }

    public function testAddResourceActionGrantActionGrantWithRole(): void
    {
        $roleReader = $this->internalResourceActionGrantService->addOrUpdateRole(
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
        $resourceActionGrant->setCreatorId(self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
        $this->assertTrue(Uuid::isValid($resourceActionGrant->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getResourceIdentifier());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $resourceActionGrant->getCreatorId());
        $this->assertEquals(null, $resourceActionGrant->getAction());
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

    public function testAddResourceActionGrantActionGrantWithRoleCreatingResources(): void
    {
        $roleReader = $this->internalResourceActionGrantService->addOrUpdateRole(
            ['en' => 'Reader', 'de' => 'Leser'],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
            ]
        );

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass(self::TEST_RESOURCE_CLASS);
        $resourceActionGrant->setResourceIdentifier(self::TEST_RESOURCE_IDENTIFIER);
        $resourceActionGrant->setRole($roleReader);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant->setCreatorId(self::CURRENT_USER_IDENTIFIER);

        $resourceActionGrant = $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
        $this->assertTrue(Uuid::isValid($resourceActionGrant->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getResourceIdentifier());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $resourceActionGrant->getCreatorId());
        $this->assertEquals(null, $resourceActionGrant->getAction());
        $this->assertEquals(null, $resourceActionGrant->getAvailableResourceClassAction());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $resourceActionGrant->getUserIdentifier());
        $this->assertEquals(null, $resourceActionGrant->getUserGroup());
        $this->assertEquals(null, $resourceActionGrant->getDynamicUserGroupIdentifier());
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceActionGrant->getAuthorizationResource()->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceActionGrant->getAuthorizationResource()->getResourceIdentifier());
        $this->assertNotNull($resourceActionGrant->getDateCreated());
        $role = $resourceActionGrant->getRole();
        $this->assertEquals($roleReader->getIdentifier(), $role->getIdentifier());
        $this->assertEquals($roleReader->getRoleNames(), $role->getRoleNames());
        $this->assertEquals($roleReader->getRoleActions(), $role->getRoleActions());
    }

    public function testAddResourceActionGrantInvalidActionMissing(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($authorizationResource);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant->setCreatorId(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ERROR_ID, $apiError->getErrorId());
            $this->assertEquals("Exactly one of 'action' or 'role' must be provided", $apiError->getMessage());
        }
    }

    public function testAddResourceActionGrantInvalidActionAndRoleProvided(): void
    {
        $roleReader = $this->internalResourceActionGrantService->addOrUpdateRole(
            ['en' => 'Reader', 'de' => 'Leser'],
            [
                ResourceActionGrantService::createRoleAction(
                    TestResources::TEST_RESOURCE_CLASS, TestResources::READ_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
            ]
        );

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass(self::TEST_RESOURCE_CLASS);
        $resourceActionGrant->setResourceIdentifier(self::TEST_RESOURCE_IDENTIFIER);
        $resourceActionGrant->setAction(AuthorizationService::OWN_ACTION);
        $resourceActionGrant->setRole($roleReader);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant->setCreatorId(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ERROR_ID, $apiError->getErrorId());
            $this->assertEquals("Exactly one of 'action' or 'role' must be provided", $apiError->getMessage());
        }
    }

    public function testAddResourceActionGrantInvalidActionUndefined(): void
    {
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass(self::TEST_RESOURCE_CLASS);
        $resourceActionGrant->setResourceIdentifier(self::TEST_RESOURCE_IDENTIFIER);
        $resourceActionGrant->setAction('undefinedAction');
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant->setCreatorId(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ERROR_ID, $apiError->getErrorId());
            $this->assertEquals("action is invalid: action 'undefinedAction' is not defined for resource class '".self::TEST_RESOURCE_CLASS."'", $apiError->getMessage());
        }
    }

    public function testAddResourceActionGrantInvalidGrantHolderMissing(): void
    {
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass(self::TEST_RESOURCE_CLASS);
        $resourceActionGrant->setResourceIdentifier(self::TEST_RESOURCE_IDENTIFIER);
        $resourceActionGrant->setAction(AuthorizationService::OWN_ACTION);
        $resourceActionGrant->setCreatorId(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(
                InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ERROR_ID,
                $apiError->getErrorId()
            );
            $this->assertEquals(
                'Exactly one of userIdentifier, userGroup or dynamicUserGroupIdentifier must be provided',
                $apiError->getMessage()
            );
        }
    }

    public function testAddResourceActionGrantInvalidMoreThanOneGrantHolderProvided(): void
    {
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass(self::TEST_RESOURCE_CLASS);
        $resourceActionGrant->setResourceIdentifier(self::TEST_RESOURCE_IDENTIFIER);
        $resourceActionGrant->setAction(AuthorizationService::OWN_ACTION);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant->setUserGroup($this->testEntityManager->addUserGroup('testGroup'));
        $resourceActionGrant->setCreatorId(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(
                InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ERROR_ID,
                $apiError->getErrorId()
            );
            $this->assertEquals(
                'Exactly one of userIdentifier, userGroup or dynamicUserGroupIdentifier must be provided',
                $apiError->getMessage()
            );
        }
    }

    public function testAddResourceActionGrantInvalidResourceClassUndefined(): void
    {
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass('undefinedResourceClass');
        $resourceActionGrant->setResourceIdentifier(self::TEST_RESOURCE_IDENTIFIER);
        $resourceActionGrant->setAction(AuthorizationService::OWN_ACTION);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(InternalResourceActionGrantService::ADDING_AUTHORIZATION_RESOURCE_FAILED_ERROR_ID, $apiError->getErrorId());
            $this->assertEquals('resource class \'undefinedResourceClass\' is not defined', $apiError->getMessage());
        }
    }

    public function testAddResourceActionGrantInvalidResourceClassMissing(): void
    {
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceIdentifier(self::TEST_RESOURCE_IDENTIFIER);
        $resourceActionGrant->setAction(AuthorizationService::OWN_ACTION);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant->setCreatorId(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(
                InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ERROR_ID,
                $apiError->getErrorId()
            );
            $this->assertEquals('resourceClass must be provided', $apiError->getMessage());
        }
    }

    public function testAddResourceActionGrantInvalidResourceIdentifierMissing(): void
    {
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass(self::TEST_RESOURCE_CLASS);
        $resourceActionGrant->setAction(AuthorizationService::OWN_ACTION);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant->setCreatorId(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(
                InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ERROR_ID,
                $apiError->getErrorId()
            );
            $this->assertEquals('resourceIdentifier must be provided', $apiError->getMessage());
        }
    }

    public function testAddResourceActionGrantInvalidCollectionResourceGroupNotSupported(): void
    {
        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setResourceClass(self::TEST_RESOURCE_CLASS);
        $resourceActionGrant->setResourceIdentifier(AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $resourceActionGrant->setResourceType(ResourceType::RESOURCE_GROUP);
        $resourceActionGrant->setAction(TestResources::CREATE_ACTION);
        $resourceActionGrant->setUserGroup($this->testEntityManager->addUserGroup('testGroup'));
        $resourceActionGrant->setCreatorId(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(
                InternalResourceActionGrantService::ADDING_AUTHORIZATION_RESOURCE_FAILED_ERROR_ID,
                $apiError->getErrorId()
            );
            $this->assertEquals('resource groups can not be collection resources', $apiError->getMessage());
        }
    }

    public function testRemoveAuthorizationResourceCascadeDelete(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals($authorizationResource->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($authorizationResource->getIdentifier())->getIdentifier());

        $resourceActionGrant = $this->testEntityManager->addResourceActionGrant(
            $authorizationResource, AuthorizationService::OWN_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->assertEquals($resourceActionGrant->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier())->getIdentifier());

        $this->internalResourceActionGrantService->removeAuthorizationResourcesByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($authorizationResource->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier()));
    }

    public function testAddResourceActionGrantFailed(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setAuthorizationResource($authorizationResource);
        $resourceActionGrant->setAction(AuthorizationService::OWN_ACTION);
        $resourceActionGrant->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->testEntityManager->prepareDBError(ResourceActionGrant::TABLE_NAME);
            $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $apiError->getStatusCode());
            $this->assertEquals(
                InternalResourceActionGrantService::ADDING_RESOURCE_ACTION_GRANT_FAILED_ERROR_ID,
                $apiError->getErrorId());
        }
    }

    public function testRemoveAuthorizationResourceByResourceClassAndIdentifier(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertEquals($authorizationResource->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($authorizationResource->getIdentifier())->getIdentifier());

        $this->internalResourceActionGrantService->removeAuthorizationResourcesByResourceClassAndIdentifier(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($authorizationResource->getIdentifier()));
    }

    public function testRemoveAuthorizationResourceByResourceClassAndIdentifierCascadeDelete(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $userGroup = $this->testEntityManager->addUserGroup();

        $this->assertEquals($authorizationResource->getIdentifier(),
            $this->testEntityManager->getAuthorizationResourceByIdentifier($authorizationResource->getIdentifier())->getIdentifier());
        $this->assertEquals($userGroup->getIdentifier(),
            $this->testEntityManager->getUserGroupByIdentifier($userGroup->getIdentifier())->getIdentifier());

        $resourceActionGrant = $this->testEntityManager->addResourceActionGrant(
            $authorizationResource, AuthorizationService::OWN_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER
        );
        $resourceActionGrantGroup = $this->testEntityManager->addResourceActionGrant(
            $authorizationResource, AuthorizationService::OWN_ACTION,
            userGroup: $userGroup
        );

        $this->assertEquals($resourceActionGrant->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier())->getIdentifier());
        $this->assertEquals($resourceActionGrantGroup->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrantGroup->getIdentifier())->getIdentifier());

        $this->internalResourceActionGrantService->removeAuthorizationResourcesByResourceClassAndIdentifier(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $this->assertNull($this->testEntityManager->getAuthorizationResourceByIdentifier($authorizationResource->getIdentifier()));
        // assert that group has not been deleted alongside with group grant
        $this->assertEquals($userGroup->getIdentifier(),
            $this->testEntityManager->getUserGroupByIdentifier($userGroup->getIdentifier())->getIdentifier());

        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrantGroup->getIdentifier()));
    }

    public function testRemoveResourceActionGrant(): void
    {
        $resourceActionGrant = $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            action: TestResources::READ_ACTION,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);

        $this->internalResourceActionGrantService->removeResourceActionGrant($resourceActionGrant);

        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier()));
    }

    public function testGetResourceActionGrantsWithDynamicResourceGroups(): void
    {
        $action = array_keys(TestResources::TEST_RESOURCE_ITEM_ACTIONS)[0];
        $dynamicResourceGroupGrant = $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            self::TEST_RESOURCE_CLASS,
            self::TEST_DYNAMIC_RESOURCE_GROUP_IDENTIFIER,
            resourceType: ResourceType::DYNAMIC_RESOURCE_GROUP,
            action: $action
        );

        $resourceIds = [
            '1',
            '2',
            '3',
        ];

        $this->testResolvingDynamicResourceGroupRequestedEventSubscriber->setMemberResourceIdentifiers(
            self::TEST_RESOURCE_CLASS,
            self::TEST_DYNAMIC_RESOURCE_GROUP_IDENTIFIER,
            $resourceIds
        );

        $assertContainsInheritedGrants = function (array $rags, array $resourceIds, ResourceActionGrant $dynamicResourceGroupGrant) {
            foreach ($resourceIds as $resourceId) {
                $this->assertContainsResourceWhere($rags,
                    function (ResourceActionGrant $resourceActionGrant) use ($resourceId, $dynamicResourceGroupGrant) {
                        return $resourceActionGrant->getIdentifier() === $dynamicResourceGroupGrant->getIdentifier()
                            && $resourceActionGrant->isInherited()
                            && $resourceActionGrant->getResourceClass() === self::TEST_RESOURCE_CLASS
                            && $resourceActionGrant->getResourceIdentifier() === $resourceId
                            && $resourceActionGrant->getAction() === $dynamicResourceGroupGrant->getAction();
                    }
                );
            }
        };

        $rags = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS,
            resourceIdentifier: null,
            resourceType: null
        );
        $this->assertCount(count($resourceIds) + 1, $rags);
        $assertContainsInheritedGrants($rags, $resourceIds, $dynamicResourceGroupGrant);
        $this->assertContainsResourceWhere($rags,
            function (ResourceActionGrant $resourceActionGrant) use ($dynamicResourceGroupGrant) {
                return $resourceActionGrant->getIdentifier() === $dynamicResourceGroupGrant->getIdentifier()
                    && $resourceActionGrant->getResourceClass() === self::TEST_RESOURCE_CLASS
                    && $resourceActionGrant->getResourceIdentifier() === self::TEST_DYNAMIC_RESOURCE_GROUP_IDENTIFIER
                    && $resourceActionGrant->getResourceType() === ResourceType::DYNAMIC_RESOURCE_GROUP
                    && $resourceActionGrant->getAction() === $dynamicResourceGroupGrant->getAction();
            }
        );

        $rags = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS,
            resourceIdentifier: null,
            resourceType: ResourceType::RESOURCE
        );
        $this->assertCount(count($resourceIds), $rags);
        $assertContainsInheritedGrants($rags, $resourceIds, $dynamicResourceGroupGrant);

        $this->assertEmpty($this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS,
            resourceIdentifier: null,
            resourceType: ResourceType::RESOURCE_GROUP
        ));

        $rags = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS,
            resourceIdentifier: null,
            resourceType: ResourceType::DYNAMIC_RESOURCE_GROUP
        );
        $this->assertCount(1, $rags);
        $this->assertContainsResourceWhere($rags,
            function (ResourceActionGrant $resourceActionGrant) use ($dynamicResourceGroupGrant) {
                return $resourceActionGrant->getIdentifier() === $dynamicResourceGroupGrant->getIdentifier()
                    && $resourceActionGrant->getResourceClass() === self::TEST_RESOURCE_CLASS
                    && $resourceActionGrant->getResourceIdentifier() === self::TEST_DYNAMIC_RESOURCE_GROUP_IDENTIFIER
                    && $resourceActionGrant->getResourceType() === ResourceType::DYNAMIC_RESOURCE_GROUP
                    && $resourceActionGrant->getAction() === $dynamicResourceGroupGrant->getAction();
            }
        );

        foreach ($resourceIds as $resourceId) {
            $rags = $this->internalResourceActionGrantService->getResourceActionGrants(
                self::TEST_RESOURCE_CLASS,
                resourceIdentifier: $resourceId,
                resourceType: null
            );
            $assertContainsInheritedGrants($rags, [$resourceId], $dynamicResourceGroupGrant);

            $rags = $this->internalResourceActionGrantService->getResourceActionGrants(
                self::TEST_RESOURCE_CLASS,
                $resourceId,
                resourceType: ResourceType::RESOURCE
            );
            $this->assertCount(1, $rags);
            $assertContainsInheritedGrants($rags, [$resourceId], $dynamicResourceGroupGrant);

            $this->assertEmpty($this->internalResourceActionGrantService->getResourceActionGrants(
                self::TEST_RESOURCE_CLASS,
                $resourceId,
                resourceType: ResourceType::RESOURCE_GROUP
            ));
            $this->assertEmpty($this->internalResourceActionGrantService->getResourceActionGrants(
                self::TEST_RESOURCE_CLASS,
                $resourceId,
                resourceType: ResourceType::DYNAMIC_RESOURCE_GROUP
            ));
        }

        $this->assertEmpty($this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS,
            '404',
            resourceType: null
        ));

        $this->assertEmpty($this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS,
            self::TEST_DYNAMIC_RESOURCE_GROUP_IDENTIFIER,
            resourceType: ResourceType::RESOURCE
        ));

        $this->assertEmpty($this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS,
            self::TEST_DYNAMIC_RESOURCE_GROUP_IDENTIFIER,
            resourceType: ResourceType::RESOURCE_GROUP
        ));
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

        //        $collectionResource = $this->testEntityManager->addAuthorizationResource(
        //            self::TEST_RESOURCE_GROUP_CLASS, self::TEST_RESOURCE_GROUP_IDENTIFIER);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants();
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrant1_1 = $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::OWN_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrant1_2 = $this->testEntityManager->addResourceActionGrant($resource1,
            'read', self::ANOTHER_USER_IDENTIFIER);
        $resourceActionGrant2_1 = $this->testEntityManager->addResourceActionGrant($resource2,
            AuthorizationService::OWN_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $resourceActionGrant2_2 = $this->testEntityManager->addResourceActionGrant($resource2,
            'delete', self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrantCollection1_1 = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            AuthorizationService::OWN_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resourceActionGrantCollection1_2 = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            'create', self::ANOTHER_USER_IDENTIFIER.'_2');
        $resourceClass2ResourceActionGrant = $this->testEntityManager->addResourceActionGrant($resourceClass2Resource,
            AuthorizationService::OWN_ACTION, self::ANOTHER_USER_IDENTIFIER.'_2');

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants();
        $this->assertCount(7, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_2);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant2_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant2_2);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_2);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceClass2ResourceActionGrant);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(6, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_2);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant2_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant2_2);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_2);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            'resourceClass_2');
        $this->assertCount(1, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceClass2ResourceActionGrant);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            'resourceClass_3');
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(2, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_2);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS, 'resourceIdentifier_2');
        $this->assertCount(2, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant2_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant2_2);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertCount(2, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_2);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS, 'resourceIdentifier_3');
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_1);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            null, null,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(3, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant1_1);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrant2_2);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resourceActionGrantCollection1_1);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            userIdentifier: 'userIdentifier_2');
        $this->assertCount(0, $resourceActionGrants);

        // test pagination:
        $resourceActionGrantPage1 = $this->internalResourceActionGrantService->getResourceActionGrants(
            null, null,
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
            firstResultIndex: 0,
            maxNumResults: 2);
        $this->assertCount(2, $resourceActionGrantPage1);

        $resourceActionGrantPage2 = $this->internalResourceActionGrantService->getResourceActionGrants(
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
        $rc2Resource1 = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS_2,
            self::TEST_RESOURCE_IDENTIFIER
        );

        $resourceGroup = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_GROUP_IDENTIFIER,
            ResourceType::RESOURCE_GROUP
        );

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants();
        $this->assertCount(0, $resourceActionGrants);

        $res1_manage = $this->testEntityManager->addResourceActionGrant($resource1,
            AuthorizationService::OWN_ACTION, self::CURRENT_USER_IDENTIFIER);
        $res1_read = $this->testEntityManager->addResourceActionGrant($resource1,
            TestResources::READ_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $resColl_manage = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            AuthorizationService::OWN_ACTION, self::CURRENT_USER_IDENTIFIER);
        $resColl_create = $this->testEntityManager->addResourceActionGrant($resourceCollection,
            TestResources::CREATE_ACTION, self::ANOTHER_USER_IDENTIFIER.'_2');
        $rc2_res1_manage = $this->testEntityManager->addResourceActionGrant($rc2Resource1,
            AuthorizationService::OWN_ACTION, self::ANOTHER_USER_IDENTIFIER.'_2');

        $this->testEntityManager->addResourceToResourceGroup(
            $resourceGroup->getResourceClass(), $resourceGroup->getResourceIdentifier(),
            $resource1->getResourceIdentifier());

        $resGroup_delete = $this->testEntityManager->addResourceActionGrant($resourceGroup,
            TestResources::DELETE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants();
        $this->assertCount(7, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $res1_manage);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $res1_read);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resColl_manage);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resColl_create);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $rc2_res1_manage);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resGroup_delete);
        $this->assertContainsInheritedResourceActionGrant($resourceActionGrants, $resGroup_delete, $resource1);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS);
        $this->assertCount(6, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $res1_manage);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $res1_read);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resColl_manage);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resColl_create);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resGroup_delete);
        $this->assertContainsInheritedResourceActionGrant($resourceActionGrants, $resGroup_delete, $resource1);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS_2);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $rc2_res1_manage);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            'resourceClass_3');
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(3, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $res1_manage);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $res1_read);
        $this->assertContainsInheritedResourceActionGrant($resourceActionGrants, $resGroup_delete, $resource1);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS, AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER);
        $this->assertCount(2, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resColl_manage);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resColl_create);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS, 'resourceIdentifier_3');
        $this->assertCount(0, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $res1_manage);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            null, null,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(2, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $res1_manage);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resColl_manage);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            userIdentifier: self::ANOTHER_USER_IDENTIFIER);
        $this->assertCount(3, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $res1_read);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resGroup_delete);
        $this->assertContainsInheritedResourceActionGrant($resourceActionGrants, $resGroup_delete, $resource1);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER);
        $this->assertCount(3, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $res1_read);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resGroup_delete);
        $this->assertContainsInheritedResourceActionGrant($resourceActionGrants, $resGroup_delete, $resource1);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            userIdentifier: 'userIdentifier_2');
        $this->assertCount(0, $resourceActionGrants);

        // test pagination:
        $resourceActionGrantPage1 = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS,
            firstResultIndex: 0,
            maxNumResults: 4
        );
        $this->assertCount(4, $resourceActionGrantPage1);

        $resourceActionGrantPage2 = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS,
            firstResultIndex: 4,
            maxNumResults: 4
        );
        $this->assertCount(2, $resourceActionGrantPage2);

        $resourceActionGrants = array_merge($resourceActionGrantPage1, $resourceActionGrantPage2);
        $this->assertCount(6, $resourceActionGrants);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $res1_manage);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $res1_read);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resColl_manage);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resColl_create);
        $this->assertContainsResourceActionGrant($resourceActionGrants, $resGroup_delete);
        $this->assertContainsInheritedResourceActionGrant($resourceActionGrants, $resGroup_delete, $resource1);
    }

    public function testGetResourceActionGrantsForResourceClassAndIdentifierWithUserGroupGrants(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup();

        $resource = $this->testEntityManager->addAuthorizationResource(self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $userResourceActionGrant = $this->testEntityManager->addResourceActionGrant($resource,
            AuthorizationService::OWN_ACTION, self::CURRENT_USER_IDENTIFIER);
        $groupResourceActionGrant = $this->testEntityManager->addResourceActionGrant($resource,
            'read', null, $userGroup);
        $dynamicUserGroupResourceActionGrant = $this->testEntityManager->addResourceActionGrant($resource,
            'read', null, null, 'dynamicUserGroup');

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);
        $this->assertCount(3, $resourceActionGrants);

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            userIdentifier: self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($userResourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            groupIdentifiers: [$userGroup->getIdentifier()]);
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($groupResourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            dynamicUserGroupIdentifiers: ['dynamicUserGroup']
        );
        $this->assertCount(1, $resourceActionGrants);
        $this->assertEquals($dynamicUserGroupResourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());

        // user, group and dynamic group ID criteria is combined with OR conjunction
        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
            groupIdentifiers: [$userGroup->getIdentifier()],
            dynamicUserGroupIdentifiers: ['dynamicUserGroup']
        );
        $this->assertCount(3, $resourceActionGrants);
        $this->assertEquals($userResourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());
        $this->assertEquals($groupResourceActionGrant->getIdentifier(), $resourceActionGrants[1]->getIdentifier());
        $this->assertEquals($dynamicUserGroupResourceActionGrant->getIdentifier(), $resourceActionGrants[2]->getIdentifier());

        $resourceActionGrants = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            groupIdentifiers: [$userGroup->getIdentifier()],
            dynamicUserGroupIdentifiers: ['dynamicUserGroup']
        );
        $this->assertCount(2, $resourceActionGrants);
        $this->assertEquals($groupResourceActionGrant->getIdentifier(), $resourceActionGrants[0]->getIdentifier());
        $this->assertEquals($dynamicUserGroupResourceActionGrant->getIdentifier(), $resourceActionGrants[1]->getIdentifier());

        // test pagination:
        $resourceActionGrantPage1 = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
            groupIdentifiers: [$userGroup->getIdentifier()],
            dynamicUserGroupIdentifiers: ['dynamicUserGroup'],
            firstResultIndex: 0,
            maxNumResults: 2
        );
        $this->assertCount(2, $resourceActionGrantPage1);
        $this->assertEquals($userResourceActionGrant->getIdentifier(), $resourceActionGrantPage1[0]->getIdentifier());
        $this->assertEquals($groupResourceActionGrant->getIdentifier(), $resourceActionGrantPage1[1]->getIdentifier());

        $resourceActionGrantPage2 = $this->internalResourceActionGrantService->getResourceActionGrants(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            userIdentifier: self::CURRENT_USER_IDENTIFIER,
            groupIdentifiers: [$userGroup->getIdentifier()],
            dynamicUserGroupIdentifiers: ['dynamicUserGroup'],
            firstResultIndex: 2,
            maxNumResults: 2
        );
        $this->assertCount(1, $resourceActionGrantPage2);
        $this->assertEquals($dynamicUserGroupResourceActionGrant->getIdentifier(), $resourceActionGrantPage2[0]->getIdentifier());
    }

    public function testAddResourceActionGrantToGroupResource(): void
    {
        $groupAuthorizationResourceMember = $this->internalResourceActionGrantService->addResourceToResourceGroup(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            self::TEST_RESOURCE_IDENTIFIER.'_member');

        $this->assertTrue(Uuid::isValid($groupAuthorizationResourceMember->getIdentifier()));
        $this->assertTrue(Uuid::isValid($groupAuthorizationResourceMember->getGroupAuthorizationResource()->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $groupAuthorizationResourceMember->getGroupAuthorizationResource()->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $groupAuthorizationResourceMember->getGroupAuthorizationResource()->getResourceIdentifier());
        $this->assertEquals(ResourceType::RESOURCE_GROUP, $groupAuthorizationResourceMember->getGroupAuthorizationResource()->getResourceType());
        $this->assertTrue(Uuid::isValid($groupAuthorizationResourceMember->getMemberAuthorizationResource()->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $groupAuthorizationResourceMember->getMemberAuthorizationResource()->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER.'_member', $groupAuthorizationResourceMember->getMemberAuthorizationResource()->getResourceIdentifier());
        $this->assertEquals(ResourceType::RESOURCE, $groupAuthorizationResourceMember->getMemberAuthorizationResource()->getResourceType());

        $groupAuthorizationResourceMemberPersistence = $this->testEntityManager->getResourceGroupMember($groupAuthorizationResourceMember->getIdentifier());
        $this->assertEquals($groupAuthorizationResourceMember->getIdentifier(), $groupAuthorizationResourceMemberPersistence->getIdentifier());
        $this->assertEquals($groupAuthorizationResourceMember->getGroupAuthorizationResource()->getIdentifier(),
            $groupAuthorizationResourceMemberPersistence->getGroupAuthorizationResource()->getIdentifier());
        $this->assertEquals($groupAuthorizationResourceMember->getMemberAuthorizationResource()->getIdentifier(),
            $groupAuthorizationResourceMemberPersistence->getMemberAuthorizationResource()->getIdentifier());

        $resourceGroup = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER,
            ResourceType::RESOURCE_GROUP
        );
        $this->assertEquals($groupAuthorizationResourceMember->getGroupAuthorizationResource()->getIdentifier(),
            $resourceGroup->getIdentifier());
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resourceGroup->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $resourceGroup->getResourceIdentifier());
        $this->assertEquals(ResourceType::RESOURCE_GROUP, $resourceGroup->getResourceType());

        $resource = $this->testEntityManager->getAuthorizationResourceByResourceClassAndIdentifier(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER.'_member');
        $this->assertEquals($groupAuthorizationResourceMember->getMemberAuthorizationResource()->getIdentifier(),
            $resource->getIdentifier());
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $resource->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER.'_member', $resource->getResourceIdentifier());
        $this->assertEquals(ResourceType::RESOURCE, $resource->getResourceType());

        // equal resource identifiers is ok, if one is a group and the other is a resource:
        $groupAuthorizationResourceMember = $this->internalResourceActionGrantService->addResourceToResourceGroup(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            self::TEST_RESOURCE_IDENTIFIER);
        $this->assertTrue(Uuid::isValid($groupAuthorizationResourceMember->getIdentifier()));
        $this->assertTrue(Uuid::isValid($groupAuthorizationResourceMember->getGroupAuthorizationResource()->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $groupAuthorizationResourceMember->getGroupAuthorizationResource()->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $groupAuthorizationResourceMember->getGroupAuthorizationResource()->getResourceIdentifier());
        $this->assertEquals(ResourceType::RESOURCE_GROUP, $groupAuthorizationResourceMember->getGroupAuthorizationResource()->getResourceType());
        $this->assertTrue(Uuid::isValid($groupAuthorizationResourceMember->getMemberAuthorizationResource()->getIdentifier()));
        $this->assertEquals(self::TEST_RESOURCE_CLASS, $groupAuthorizationResourceMember->getMemberAuthorizationResource()->getResourceClass());
        $this->assertEquals(self::TEST_RESOURCE_IDENTIFIER, $groupAuthorizationResourceMember->getMemberAuthorizationResource()->getResourceIdentifier());
        $this->assertEquals(ResourceType::RESOURCE, $groupAuthorizationResourceMember->getMemberAuthorizationResource()->getResourceType());
    }

    public function testRemoveResourceFromGroupResource(): void
    {
        $groupAuthorizationResourceMember =
            $this->internalResourceActionGrantService->addResourceToResourceGroup(
                self::TEST_RESOURCE_CLASS,
                self::TEST_RESOURCE_IDENTIFIER,
                self::TEST_RESOURCE_IDENTIFIER.'_member');

        $this->assertNotNull($this->testEntityManager->getResourceGroupMember($groupAuthorizationResourceMember->getIdentifier()));

        $this->internalResourceActionGrantService->removeResourceFromGroupResource(
            self::TEST_RESOURCE_CLASS,
            self::TEST_RESOURCE_IDENTIFIER,
            self::TEST_RESOURCE_IDENTIFIER.'_member');

        $this->assertNull($this->testEntityManager->getResourceGroupMember($groupAuthorizationResourceMember->getIdentifier()));
    }

    public function testAddResourceActionGrantToGroupResourceCollectionResourceNotSupportedMember(): void
    {
        try {
            $this->internalResourceActionGrantService->addResourceToResourceGroup(
                self::TEST_RESOURCE_CLASS,
                self::TEST_RESOURCE_IDENTIFIER,
                InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER);
            $this->fail('Expected ApiError not thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(InternalResourceActionGrantService::ADDING_RESOURCE_TO_GROUP_RESOURCE_FAILED_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testAddResourceActionGrantToGroupResourceCollectionResourceNotSupportedGroup(): void
    {
        try {
            $this->internalResourceActionGrantService->addResourceToResourceGroup(
                self::TEST_RESOURCE_CLASS,
                InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER,
                self::TEST_RESOURCE_IDENTIFIER
            );
            $this->fail('Expected ApiError not thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(InternalResourceActionGrantService::ADDING_AUTHORIZATION_RESOURCE_FAILED_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testGetAvailableResourceClassActions()
    {
        $getAction = function (AvailableResourceClassAction $availableResourceClassAction) {
            return $availableResourceClassAction->getAction();
        };

        $availableResourceClassActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            self::TEST_RESOURCE_CLASS,
            AvailableResourceClassAction::ITEM_ACTION_TYPE,
            whereActionsIn: []);
        $this->assertEmpty($availableResourceClassActions);

        $availableResourceClassActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            self::TEST_RESOURCE_CLASS,
            AvailableResourceClassAction::COLLECTION_ACTION_TYPE,
            whereActionsIn: []);
        $this->assertEmpty($availableResourceClassActions);

        $availableResourceClassActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            self::TEST_RESOURCE_CLASS,
            AvailableResourceClassAction::ITEM_ACTION_TYPE,
            whereActionsIn: ['not there']);
        $this->assertEmpty($availableResourceClassActions);

        $availableResourceClassActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            self::TEST_RESOURCE_CLASS,
            AvailableResourceClassAction::COLLECTION_ACTION_TYPE,
            whereActionsIn: ['not there']);
        $this->assertEmpty($availableResourceClassActions);

        $availableResourceClassActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            'not there',
            AvailableResourceClassAction::ITEM_ACTION_TYPE
        );
        $this->assertEmpty($availableResourceClassActions);

        $availableResourceClassActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            'not there',
            AvailableResourceClassAction::COLLECTION_ACTION_TYPE
        );
        $this->assertEmpty($availableResourceClassActions);

        $availableResourceClassActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            self::TEST_RESOURCE_CLASS,
            AvailableResourceClassAction::ITEM_ACTION_TYPE
        );
        $this->assertIsPermutationOf(
            array_merge([AuthorizationService::OWN_ACTION], array_keys(TestResources::TEST_RESOURCE_ITEM_ACTIONS)),
            array_map($getAction, $availableResourceClassActions)
        );

        $testResourceItemActions = array_keys(TestResources::TEST_RESOURCE_ITEM_ACTIONS);
        $testResourceCollectionActions = array_keys(TestResources::TEST_RESOURCE_COLLECTION_ACTIONS);

        // NOTE: 'manage' action has to be added
        $availableResourceClassActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            self::TEST_RESOURCE_CLASS,
            AvailableResourceClassAction::ITEM_ACTION_TYPE
        );
        $this->assertIsPermutationOf(
            array_merge([AuthorizationService::OWN_ACTION], $testResourceItemActions),
            array_map($getAction, $availableResourceClassActions)
        );

        // NOTE: 'manage' action has to be added
        $availableResourceClassActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            self::TEST_RESOURCE_CLASS,
            AvailableResourceClassAction::COLLECTION_ACTION_TYPE
        );
        $this->assertIsPermutationOf(
            array_merge([AuthorizationService::OWN_ACTION], $testResourceCollectionActions),
            array_map($getAction, $availableResourceClassActions)
        );

        $availableResourceClassActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            self::TEST_RESOURCE_CLASS,
            AvailableResourceClassAction::ITEM_ACTION_TYPE,
            whereActionsIn: [$testResourceItemActions[0]]
        );
        $this->assertEquals([$testResourceItemActions[0]], array_map($getAction, $availableResourceClassActions));

        $availableResourceClassActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            self::TEST_RESOURCE_CLASS,
            AvailableResourceClassAction::COLLECTION_ACTION_TYPE,
            whereActionsIn: [$testResourceCollectionActions[0]]
        );
        $this->assertEquals([$testResourceCollectionActions[0]], array_map($getAction, $availableResourceClassActions));

        $availableResourceClassActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            self::TEST_RESOURCE_CLASS,
            AvailableResourceClassAction::ITEM_ACTION_TYPE,
            whereActionsIn: [AuthorizationService::OWN_ACTION]
        );
        $this->assertCount(1, $availableResourceClassActions);
        $availableResourceClassAction = $availableResourceClassActions[0];
        $this->assertEquals(AuthorizationService::OWN_ITEM_ACTION_UUID, $availableResourceClassAction->getIdentifier());
        $this->assertEquals(AuthorizationService::OWN_ACTION, $availableResourceClassAction->getAction());
        $this->assertEquals(AvailableResourceClassAction::ITEM_ACTION_TYPE, $availableResourceClassAction->getActionType());

        $availableResourceClassActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            self::TEST_RESOURCE_CLASS,
            AvailableResourceClassAction::COLLECTION_ACTION_TYPE,
            whereActionsIn: [AuthorizationService::OWN_ACTION]
        );
        $this->assertCount(1, $availableResourceClassActions);
        $availableResourceClassAction = $availableResourceClassActions[0];
        $this->assertEquals(AuthorizationService::OWN_COLLECTION_ACTION_UUID, $availableResourceClassAction->getIdentifier());
        $this->assertEquals(AuthorizationService::OWN_ACTION, $availableResourceClassAction->getAction());
        $this->assertEquals(AvailableResourceClassAction::COLLECTION_ACTION_TYPE, $availableResourceClassAction->getActionType());

        $availableResourceClassActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            'not there',
            AvailableResourceClassAction::ITEM_ACTION_TYPE,
            whereActionsIn: [AuthorizationService::OWN_ACTION]
        );
        $this->assertCount(0, $availableResourceClassActions);

        $availableResourceClassActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            'not there',
            AvailableResourceClassAction::COLLECTION_ACTION_TYPE,
            whereActionsIn: [AuthorizationService::OWN_ACTION]
        );
        $this->assertCount(0, $availableResourceClassActions);

        $availableResourceClassActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            self::TEST_RESOURCE_CLASS,
            AvailableResourceClassAction::ITEM_ACTION_TYPE,
            whereActionsIn: [AuthorizationService::OWN_ACTION, $testResourceItemActions[0]]
        );
        $this->assertCount(2, $availableResourceClassActions);
        $this->assertIsPermutationOf(
            [AuthorizationService::OWN_ACTION, $testResourceItemActions[0]],
            array_map($getAction, $availableResourceClassActions)
        );

        $availableResourceClassActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            self::TEST_RESOURCE_CLASS,
            AvailableResourceClassAction::COLLECTION_ACTION_TYPE,
            whereActionsIn: [AuthorizationService::OWN_ACTION, $testResourceCollectionActions[0]]
        );
        $this->assertCount(2, $availableResourceClassActions);
        $this->assertIsPermutationOf(
            [AuthorizationService::OWN_ACTION, $testResourceCollectionActions[0]],
            array_map($getAction, $availableResourceClassActions)
        );

        $availableResourceClassActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            self::TEST_RESOURCE_CLASS,
            AvailableResourceClassAction::ITEM_ACTION_TYPE,
            whereActionsIn: [$testResourceItemActions[0], $testResourceItemActions[1]]
        );
        $this->assertCount(2, $availableResourceClassActions);
        $this->assertIsPermutationOf(
            [$testResourceItemActions[0], $testResourceItemActions[1]],
            array_map($getAction, $availableResourceClassActions)
        );

        $availableResourceClassActions = $this->internalResourceActionGrantService->getAvailableResourceClassActions(
            self::TEST_RESOURCE_CLASS,
            AvailableResourceClassAction::COLLECTION_ACTION_TYPE,
            whereActionsIn: [$testResourceCollectionActions[0], $testResourceCollectionActions[1]]
        );
        $this->assertCount(2, $availableResourceClassActions);
        $this->assertIsPermutationOf(
            [$testResourceCollectionActions[0], $testResourceCollectionActions[1]],
            array_map($getAction, $availableResourceClassActions)
        );
    }

    public function testIsAvailableResourceClass(): void
    {
        $this->assertTrue($this->internalResourceActionGrantService->isAvailableResourceClass(self::TEST_RESOURCE_CLASS));
        $this->internalResourceActionGrantService->reset();
        $this->assertFalse($this->internalResourceActionGrantService->isAvailableResourceClass('not there'));
    }

    public function testIsAvailableResourceClassCaching(): void
    {
        $this->assertTrue($this->internalResourceActionGrantService->isAvailableResourceClass(self::TEST_RESOURCE_CLASS));
        $this->testEntityManager->prepareDBError();
        $this->assertTrue($this->internalResourceActionGrantService->isAvailableResourceClass(self::TEST_RESOURCE_CLASS));
    }

    public function testIsAvailableResourceClassAction(): void
    {
        $this->assertTrue($this->internalResourceActionGrantService->isAvailableResourceClassAction(
            self::TEST_RESOURCE_CLASS, AuthorizationService::OWN_ACTION, self::TEST_RESOURCE_IDENTIFIER));
        $this->internalResourceActionGrantService->reset();
        $this->assertTrue($this->internalResourceActionGrantService->isAvailableResourceClassAction(
            self::TEST_RESOURCE_CLASS, array_keys(TestResources::TEST_RESOURCE_ITEM_ACTIONS)[0], self::TEST_RESOURCE_IDENTIFIER));
        $this->internalResourceActionGrantService->reset();
        $this->assertFalse($this->internalResourceActionGrantService->isAvailableResourceClassAction(
            self::TEST_RESOURCE_CLASS, 'not there', self::TEST_RESOURCE_IDENTIFIER));
        $this->internalResourceActionGrantService->reset();
        $this->assertFalse($this->internalResourceActionGrantService->isAvailableResourceClassAction(
            'not there', AuthorizationService::OWN_ACTION, self::TEST_RESOURCE_IDENTIFIER));
        $this->internalResourceActionGrantService->reset();

        $this->assertTrue($this->internalResourceActionGrantService->isAvailableResourceClassAction(
            self::TEST_RESOURCE_CLASS, AuthorizationService::OWN_ACTION, InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER));
        $this->internalResourceActionGrantService->reset();
        $this->assertTrue($this->internalResourceActionGrantService->isAvailableResourceClassAction(
            self::TEST_RESOURCE_CLASS, array_keys(TestResources::TEST_RESOURCE_COLLECTION_ACTIONS)[0], InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER));
        $this->internalResourceActionGrantService->reset();
        $this->assertFalse($this->internalResourceActionGrantService->isAvailableResourceClassAction(
            self::TEST_RESOURCE_CLASS, 'not there', InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER));
        $this->internalResourceActionGrantService->reset();
        $this->assertFalse($this->internalResourceActionGrantService->isAvailableResourceClassAction(
            'not there', AuthorizationService::OWN_ACTION, InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER));
    }

    public function testIsAvailableResourceClassActionCaching(): void
    {
        $this->assertTrue($this->internalResourceActionGrantService->isAvailableResourceClassAction(
            self::TEST_RESOURCE_CLASS, AuthorizationService::OWN_ACTION, self::TEST_RESOURCE_IDENTIFIER));
        $this->testEntityManager->prepareDBError();
        $this->assertTrue($this->internalResourceActionGrantService->isAvailableResourceClassAction(
            self::TEST_RESOURCE_CLASS, AuthorizationService::OWN_ACTION, self::TEST_RESOURCE_IDENTIFIER));
    }
}
